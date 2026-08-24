<?php
// 配置文件 - Jay影视
// 兼容所有PHP版本

// 数据库配置 - InfinityFree用户请修改这里
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'jay_movie');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// SMTP邮箱配置
define('SMTP_HOST', 'smtp.163.com');
define('SMTP_PORT', 465);
define('SMTP_USER', 'jtxnb886@163.com');
define('SMTP_PASS', 'FLLRDtadYAfGXp9Y');
define('SMTP_FROM', 'jtxnb886@163.com');
define('SMTP_FROM_NAME', 'Jay影视');

// 网站配置
define('SITE_URL', (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST']);
define('ROOT_PATH', dirname(__FILE__));

// 错误报告（生产环境建议关闭）
error_reporting(E_ALL);
ini_set('display_errors', 0);

// 时区设置
date_default_timezone_set('Asia/Shanghai');

// Session配置
if (session_status() == PHP_SESSION_NONE) {
    session_name('jay_movie_session');
    session_start();
}

// 自动加载
spl_autoload_register(function ($class) {
    $file = ROOT_PATH . '/includes/class.' . strtolower($class) . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
?>
