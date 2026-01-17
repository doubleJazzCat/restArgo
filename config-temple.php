<?php
// config.php - restArgo v1.1
function env($key, $default = '') {
    $val = getenv($key);
    return ($val !== false) ? $val : $default;
}

return [
    // 1. 基础与安全
    // Base URL: 如 '/tools/restargo/' (必须以 / 结尾)
    'BASE_URL'              => env('RESTARGO_BASE_URL', ''),
    
    // UI 访问密码 (留空则无锁)
    'UI_ACCESS_PASSWORD'    => env('RESTARGO_UI_PASS', 'argo'),
    
    // 允许跨域 (本地开发建议开启)
    'ALLOW_CORS'            => true,

    // 2. 数据库与配额
    'DB_TYPE'               => env('RESTARGO_DB_TYPE', 'sqlite'),
    'DB_SIZE_LIMIT'         => env('RESTARGO_DB_LIMIT', '1GB'),
    
    // 数据库路径 (restArgo 默认使用 data.db)
    'DB_PATH'               => __DIR__ . '/data/data.db',
    
    // MySQL / PgSQL 配置
    'DB_HOST'               => env('RESTARGO_DB_HOST', '127.0.0.1'),
    'DB_PORT'               => env('RESTARGO_DB_PORT', '3306'),
    'DB_NAME'               => env('RESTARGO_DB_NAME', 'restargo'),
    'DB_USER'               => env('RESTARGO_DB_USER', 'root'),
    'DB_PASS'               => env('RESTARGO_DB_PASS', 'root'),
];
