<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Access Denied');
}

$config = get_config();
$site_name = $config['site_name'];
$current_year = date('Y');
?>
<div class="footer-extra">
    <div class="footer-extra-section">
        <h4>关于 Jay影视</h4>
        <p>Jay影视是一个基于PHP和SQLite的轻量级影视观看平台，提供电影、电视剧、综艺、动漫等多种精彩内容。</p>
    </div>
    <div class="footer-extra-section">
        <h4>友情链接</h4>
        <a href="https://www.themoviedb.org" target="_blank" rel="nofollow">TMDB</a>
        <a href="https://github.com" target="_blank" rel="nofollow">GitHub</a>
    </div>
    <div class="footer-extra-section">
        <h4>联系方式</h4>
        <p>如有问题请通过反馈功能联系我们</p>
    </div>
    <div class="footer-bottom-bar">
        <p>&copy; <?php echo $current_year; ?> <?php echo $site_name; ?>. 保留所有权利.</p>
        <p>Powered by Jay影视 &middot; TMDB API</p>
    </div>
</div>