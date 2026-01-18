<?php
/**
 * restArgo 路由网关
 * 适用场景：PHP 内置服务器 (Built-in Server) 环境下的安全拦截与请求转发
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// 安全拦截规则：禁止访问数据、配置及点文件
$protected_patterns = [
    '/data/',
    '/config.php',
    '/config-sample.php', 
    '/.'
];

foreach ($protected_patterns as $pattern) {
    if (strpos($uri, $pattern) !== false) {
        http_response_code(403);
        die("Access Denied.");
    }
}

// 静态资源放行
if (file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

// 默认入口
include __DIR__ . '/index.php';