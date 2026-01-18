<?php
/**
 * restArgo 配置文件模版
 */

// 环境变量读取辅助函数
function env($key, $default = '') {
    $val = getenv($key);
    return ($val !== false) ? $val : $default;
}

return [
    // --- 基础设置 ---

    // 基础路径: 若部署在子目录，此处必须填写且以 / 结尾
    'BASE_URL'              => env('RESTARGO_BASE_URL', ''),
    
    // 访问密码: 留空则允许匿名访问
    'UI_ACCESS_PASSWORD'    => env('RESTARGO_UI_PASS', 'argo'),
    
    // 跨域设置
    'ALLOW_CORS'            => true,

    // --- 数据存储 ---

    // 数据库类型: sqlite (默认), mysql, pgsql
    'DB_TYPE'               => env('RESTARGO_DB_TYPE', 'sqlite'),
    
    // 存储配额
    'DB_SIZE_LIMIT'         => env('RESTARGO_DB_LIMIT', '1GB'),
    
    // SQLite 路径
    'DB_PATH'               => __DIR__ . '/data/data.db',
    
    // MySQL / PgSQL 连接配置
    'DB_HOST'               => env('RESTARGO_DB_HOST', '127.0.0.1'),
    'DB_PORT'               => env('RESTARGO_DB_PORT', '3306'),
    'DB_NAME'               => env('RESTARGO_DB_NAME', 'restargo'),
    'DB_USER'               => env('RESTARGO_DB_USER', 'root'),
    'DB_PASS'               => env('RESTARGO_DB_PASS', 'password'),
];