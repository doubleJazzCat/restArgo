<?php
/**
 * restArgo 路由网关
 * 适用场景：PHP 内置服务器 (Built-in Server) 环境下的安全拦截与请求转发
 */

// 统一解析请求路径
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// 定义受保护的敏感资源路径
$protected_patterns = [
    '/data/',           // 数据库存储目录
    '/config.php',      // 运行配置文件
    '/config-sample.php', // 配置模版文件
    '/.'                // 所有点文件 (如 .git, .env, .gitignore)
];

// 安全拦截逻辑：匹配敏感路径则返回 403
foreach ($protected_patterns as $pattern) {
    if (strpos($uri, $pattern) !== false) {
        http_response_code(403);
        die("Access Denied: Restricted Resource.");
    }
}

// 静态资源与实体文件放行逻辑
// 如果请求的是已存在的物理文件（如 index.php, api.php 或 assets 下的资源），则由服务器直接处理
if (file_exists(__DIR__ . $uri) && !is_dir(__DIR__ . $uri)) {
    return false;
}

// 默认路由：将所有不存在的路径请求重定向至主入口
include __DIR__ . '/index.php';