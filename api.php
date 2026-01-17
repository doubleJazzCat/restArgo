<?php
// api.php - restArgo v1.4
// 修复: 自动升级数据库表结构，防止字段缺失报错
error_reporting(0);

// 1. 致命错误捕获
register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE || $error['type'] === E_CORE_ERROR)) {
        if (ob_get_length()) ob_clean(); 
        header('Content-Type: application/json');
        echo json_encode(['error' => 'Fatal Error: ' . $error['message'] . ' on line ' . $error['line']]);
        exit;
    }
});

$CONFIG = [];
if (file_exists('config.php')) {
    $temp = require 'config.php';
    if (is_array($temp)) $CONFIG = $temp;
}

// CORS
if ($CONFIG['ALLOW_CORS'] ?? true) {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Headers: Content-Type');
    header('Access-Control-Allow-Methods: POST, GET, DELETE, OPTIONS');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
}
header('Content-Type: application/json');

// Routing
$action = $_GET['action'] ?? 'proxy';
$input = json_decode(file_get_contents('php://input'), true) ?? [];

if ($action === 'proxy') {
    handleProxyRequest($input);
} else {
    try {
        $pdo = getPDO($CONFIG);
        initDB($pdo, $CONFIG['DB_TYPE'] ?? 'sqlite');
        // 🟢 自动升级表结构 (Add Missing Columns)
        upgradeDB($pdo, $CONFIG['DB_TYPE'] ?? 'sqlite');
        
        if (in_array($action, ['history_add', 'collection_add', 'folder_add'])) {
            $stats = getStorageStats($pdo, $CONFIG);
            if ($stats['used'] >= $stats['limit'] && $stats['limit'] > 0) {
                echo json_encode(['error' => 'Storage limit reached']); exit;
            }
        }
        handleStorageRequest($action, $pdo, $input, $CONFIG);
    } catch (Exception $e) {
        echo json_encode(['error' => 'Storage Error: ' . $e->getMessage()]);
    }
}

// ==========================================
// 逻辑实现
// ==========================================

function handleStorageRequest($action, $pdo, $input, $config) {
    switch ($action) {
        case 'storage_stats':
            echo json_encode(getStorageStats($pdo, $config)); break;

        // Folder
        case 'folder_list':
            $stmt = $pdo->query("SELECT * FROM folders ORDER BY name ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); break;
        case 'folder_add':
            $stmt = $pdo->prepare("INSERT INTO folders (name, parent_id, created_at) VALUES (?, ?, ?)");
            $stmt->execute([$input['name'], $input['parent_id'] ?? 0, time()]);
            echo json_encode(['status' => 'ok', 'id' => $pdo->lastInsertId()]); break;
        case 'folder_delete':
            $id = $input['id'] ?? 0;
            $mode = $input['mode'] ?? 'move_root';
            if ($mode === 'delete_all') deleteFolderRecursive($pdo, $id);
            elseif ($mode === 'move_root') {
                $pdo->prepare("UPDATE folders SET parent_id = 0 WHERE parent_id = ?")->execute([$id]);
                $pdo->prepare("UPDATE collections SET folder_id = 0 WHERE folder_id = ?")->execute([$id]);
                $pdo->prepare("DELETE FROM folders WHERE id = ?")->execute([$id]);
            }
            echo json_encode(['status' => 'ok']); break;
        case 'item_move':
            $stmt = ($input['type'] === 'folder') ? $pdo->prepare("UPDATE folders SET parent_id = ? WHERE id = ?") : $pdo->prepare("UPDATE collections SET folder_id = ? WHERE id = ?");
            $stmt->execute([$input['target_id'], $input['id']]);
            echo json_encode(['status' => 'ok']); break;

        // Collection
        case 'collection_list':
            $stmt = $pdo->query("SELECT * FROM collections ORDER BY name ASC");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); break;
        case 'collection_add':
            $stmt = $pdo->prepare("INSERT INTO collections (name, folder_id, method, url, req_data, res_status, res_time, res_size, res_type, res_body, req_headers, res_headers, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $input['name'], $input['folder_id'] ?? 0, 
                $input['method'], $input['url'], json_encode($input['fullReq']), 
                $input['res_status']??0, $input['res_time']??0, $input['res_size']??0, $input['res_type']??'', $input['res_body']??'', 
                $input['req_headers']??'', $input['res_headers']??'', time()
            ]);
            echo json_encode(['status' => 'ok', 'id' => $pdo->lastInsertId()]); break;
        case 'collection_delete':
            $stmt = $pdo->prepare("DELETE FROM collections WHERE id = ?");
            $stmt->execute([$_GET['id'] ?? 0]); echo json_encode(['status' => 'ok']); break;

        // History
        case 'history_list':
            $stmt = $pdo->query("SELECT * FROM history ORDER BY id DESC LIMIT 100");
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); break;
        case 'history_add':
            $stmt = $pdo->prepare("INSERT INTO history (method, url, req_data, res_status, res_time, res_size, res_type, res_body, req_headers, res_headers, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $input['method'], $input['url'], json_encode($input['fullReq']), 
                $input['res_status']??0, $input['res_time']??0, $input['res_size']??0, $input['res_type']??'', $input['res_body']??'', 
                $input['req_headers']??'', $input['res_headers']??'', time()
            ]);
            $pdo->exec("DELETE FROM history WHERE id NOT IN (SELECT id FROM (SELECT id FROM history ORDER BY id DESC LIMIT 100) AS t)");
            echo json_encode(['status' => 'ok', 'id' => $pdo->lastInsertId()]); break;
        case 'history_delete':
            $stmt = $pdo->prepare("DELETE FROM history WHERE id = ?");
            $stmt->execute([$_GET['id'] ?? 0]); echo json_encode(['status' => 'ok']); break;
        case 'history_clear':
            $pdo->exec("DELETE FROM history"); if(strtolower($config['DB_TYPE']??'sqlite')==='sqlite') $pdo->exec("VACUUM"); echo json_encode(['status' => 'ok']); break;

        default: echo json_encode(['error' => 'Unknown action']);
    }
}

function handleProxyRequest($input) {
    $url = $input['url'] ?? ''; $method = $input['method'] ?? 'GET'; $headers = $input['headers'] ?? []; $body = $input['body'] ?? '';
    $timeout = isset($input['timeout']) ? (int)$input['timeout'] : 60;
    if ($timeout < 1) $timeout = 60;

    if (!$url) { echo json_encode(['error' => 'URL missing']); exit; }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, $timeout); // Timeout
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip,deflate');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if (defined('CURLINFO_HEADER_OUT')) curl_setopt($ch, CURLINFO_HEADER_OUT, true);

    $resHeaderList = [];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$resHeaderList) {
        $h = trim($header); if (!empty($h)) $resHeaderList[] = $h; return strlen($header);
    });

    if (!in_array($method, ['GET', 'HEAD']) && !empty($body)) { curl_setopt($ch, CURLOPT_POSTFIELDS, $body); }

    $start = microtime(true);
    $response = curl_exec($ch); 
    $end = microtime(true);
    
    $info = curl_getinfo($ch);
    $err = curl_error($ch);
    $reqHeaderStr = defined('CURLINFO_HEADER_OUT') ? curl_getinfo($ch, CURLINFO_HEADER_OUT) : 'Header info not supported';
    curl_close($ch);

    echo json_encode([
        'status' => $info['http_code'],
        'time' => round(($end - $start) * 1000),
        'size' => $info['size_download'],
        'contentType' => $info['content_type'],
        'body' => base64_encode($response), 
        'isBase64' => true,
        'error' => $err,
        'reqHeaders' => $reqHeaderStr,
        'resHeaders' => implode("\n", $resHeaderList)
    ]);
}

function initDB($pdo, $type) {
    $id = ($type==='mysql') ? "INT AUTO_INCREMENT PRIMARY KEY" : (($type==='pgsql') ? "SERIAL PRIMARY KEY" : "INTEGER PRIMARY KEY AUTOINCREMENT");
    $pdo->exec("CREATE TABLE IF NOT EXISTS folders (id $id, parent_id INTEGER DEFAULT 0, name VARCHAR(255), created_at INTEGER)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS history (id $id, method VARCHAR(10), url TEXT, req_data TEXT, res_status INTEGER, res_time INTEGER, res_size INTEGER, res_type VARCHAR(100), res_body TEXT, req_headers TEXT, res_headers TEXT, created_at INTEGER)");
    $pdo->exec("CREATE TABLE IF NOT EXISTS collections (id $id, folder_id INTEGER DEFAULT 0, name VARCHAR(255), method VARCHAR(10), url TEXT, req_data TEXT, res_status INTEGER, res_time INTEGER, res_size INTEGER, res_type VARCHAR(100), res_body TEXT, req_headers TEXT, res_headers TEXT, created_at INTEGER)");
}

// 🟢 自动升级数据库：如果表里缺少 headers 字段，自动添加
function upgradeDB($pdo, $type) {
    // 仅针对 SQLite 简单处理。MySQL 忽略错误即可。
    if ($type !== 'sqlite') return; 
    $cols = $pdo->query("PRAGMA table_info(history)")->fetchAll(PDO::FETCH_ASSOC);
    $hasReq = false; foreach($cols as $c) { if($c['name'] == 'req_headers') $hasReq = true; }
    if (!$hasReq) {
        $pdo->exec("ALTER TABLE history ADD COLUMN req_headers TEXT");
        $pdo->exec("ALTER TABLE history ADD COLUMN res_headers TEXT");
        $pdo->exec("ALTER TABLE collections ADD COLUMN req_headers TEXT");
        $pdo->exec("ALTER TABLE collections ADD COLUMN res_headers TEXT");
    }
}

function deleteFolderRecursive($pdo, $folderId) { $stmt=$pdo->prepare("DELETE FROM collections WHERE folder_id = ?"); $stmt->execute([$folderId]); $stmt=$pdo->prepare("SELECT id FROM folders WHERE parent_id = ?"); $stmt->execute([$folderId]); $children=$stmt->fetchAll(PDO::FETCH_COLUMN); foreach($children as $cid) deleteFolderRecursive($pdo, $cid); $stmt=$pdo->prepare("DELETE FROM folders WHERE id = ?"); $stmt->execute([$folderId]); }
function getPDO($config) { $type=strtolower($config['DB_TYPE']??'sqlite'); $opt=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]; if($type==='mysql')return new PDO("mysql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']};charset=utf8mb4",$config['DB_USER'],$config['DB_PASS'],$opt); if($type==='pgsql')return new PDO("pgsql:host={$config['DB_HOST']};port={$config['DB_PORT']};dbname={$config['DB_NAME']}",$config['DB_USER'],$config['DB_PASS'],$opt); $path=$config['DB_PATH']??(__DIR__.'/data/data.db'); if(!is_dir(dirname($path)))@mkdir(dirname($path),0777,true); return new PDO("sqlite:$path",null,null,$opt); }
function getStorageStats($pdo, $config) { $type=strtolower($config['DB_TYPE']??'sqlite'); $used=0; try{ if($type==='sqlite'){ $path=$config['DB_PATH']??(__DIR__.'/data/data.db'); if(file_exists($path))$used=filesize($path); } elseif($type==='mysql'){ $stmt=$pdo->prepare("SELECT SUM(data_length + index_length) FROM information_schema.tables WHERE table_schema = ?"); $stmt->execute([$config['DB_NAME']??'restargo']); $used=(int)$stmt->fetchColumn(); } elseif($type==='pgsql'){ $stmt=$pdo->prepare("SELECT pg_database_size(?)"); $stmt->execute([$config['DB_NAME']??'restargo']); $used=(int)$stmt->fetchColumn(); } }catch(Exception $e){} $str=strtoupper(trim($config['DB_SIZE_LIMIT']??'1GB')); $unit=substr($str,-2); $limit=(float)$str; if($unit==='GB')$limit*=1073741824; elseif($unit==='MB')$limit*=1048576; elseif($unit==='KB')$limit*=1024; return ['used'=>$used, 'limit'=>$limit, 'percent'=>$limit>0?round(($used/$limit)*100,1):0, 'limit_str'=>$str]; }
?>