<?php
// Jay影视 - 配置文件
// 所有PHP版本兼容

// 防止直接访问
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Access Denied');
}

// 站点配置
$config = array(
    'site_name' => 'Jay影视',
    'site_url' => 'http://localhost',
    'site_description' => 'Jay影视 - 精彩影视在线观看',
    
    // 管理员账号
    'admin_username' => '杰同学',
    'admin_password' => '101113',
    
    // TMDB API
    'tmdb_api_key' => 'cb44223c5dee5676ed3a839f42ed27e3',
    'tmdb_base_url' => 'https://api.themoviedb.org/3',
    'tmdb_image_url' => 'https://image.tmdb.org/t/p',
    
    // 播放源API
    'play_source_api' => 'https://api.yyzy-tv.vip/inc/apijson.php',
    
    // 解析播放器
    'parser_url' => 'https://svip.ffzyplay.com/?url=',
    
    // SMTP配置
    'smtp' => array(
        'host' => 'smtp.163.com',
        'port' => 465,
        'user' => 'jtxnb886@163.com',
        'pass' => 'FLLRDtadYAfGXp9Y',
        'from' => 'jtxnb886@163.com',
        'from_name' => 'Jay影视'
    ),
    
    // 数据库配置 (SQLite)
    'db' => array(
        'type' => 'sqlite',
        'path' => __DIR__ . '/data/jaymovie.db'
    ),
    
    // 默认主题颜色
    'theme' => array(
        'primary' => '#01b4e4',
        'secondary' => '#0d253f',
        'accent' => '#1f80e0',
        'background' => '#04070d',
        'text' => '#ffffff',
        'muted' => '#9aa0a6'
    ),
    
    // 上传目录
    'upload_dir' => __DIR__ . '/assets/images',
    
    // Session配置
    'session_lifetime' => 7200
);

// 创建data目录
if (!is_dir(__DIR__ . '/data')) {
    @mkdir(__DIR__ . '/data', 0755, true);
}

return $config;
