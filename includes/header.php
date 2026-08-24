<?php
// 共享头部
$config = require __DIR__ . '/../config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/functions.php';

$theme = get_theme();
$site_name = $config['site_name'];
$current_user = current_user();
$announcements = get_active_announcements();
$dismissed_ids = array();

if ($current_user) {
    $dismissed = $GLOBALS['db']->fetchAll(
        "SELECT announcement_id FROM announcement_dismissed WHERE user_id = ?",
        array($current_user['id'])
    );
    foreach ($dismissed as $d) {
        $dismissed_ids[] = $d['announcement_id'];
    }
}

$active_announcements = array();
foreach ($announcements as $ann) {
    if (!in_array($ann['id'], $dismissed_ids)) {
        $active_announcements[] = $ann;
    }
}

$scriptName = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
<meta name="theme-color" content="<?php echo $theme['primary']; ?>">
<title><?php echo isset($page_title) ? clean($page_title) . ' - ' : ''; ?><?php echo $site_name; ?></title>
<link rel="stylesheet" href="assets/css/style.css">
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Noto+Sans+SC:wght@400;500;700;900&display=swap">
<style>
:root {
    --primary: <?php echo $theme['primary']; ?>;
    --secondary: <?php echo $theme['secondary']; ?>;
    --accent: <?php echo $theme['accent']; ?>;
    --background: <?php echo $theme['background']; ?>;
    --text: <?php echo $theme['text']; ?>;
    --muted: <?php echo $theme['muted']; ?>;
}
</style>
</head>
<body data-page="<?php echo $scriptName; ?>">

<!-- 公告弹窗 -->
<?php if (!empty($active_announcements) && $scriptName === 'index.php'): ?>
<div class="announcement-modal" id="announcementModal" data-announcement-id="<?php echo (int)$active_announcements[0]['id']; ?>">
    <div class="announcement-overlay"></div>
    <div class="announcement-content">
        <div class="announcement-icon">📢</div>
        <h2 class="announcement-title"><?php echo clean($active_announcements[0]['title']); ?></h2>
        <div class="announcement-body"><?php echo nl2br(clean($active_announcements[0]['content'])); ?></div>
        <div class="announcement-actions">
            <label class="announcement-dismiss">
                <input type="checkbox" id="announcementDismiss">
                <span>不再提示</span>
            </label>
            <button class="btn btn-primary" id="announcementClose">我知道了</button>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- 顶部导航 -->
<header class="site-header" id="siteHeader">
    <div class="header-inner">
        <div class="header-left">
            <button class="menu-toggle" id="menuToggle" aria-label="菜单">
                <span></span><span></span><span></span>
            </button>
            <a href="index.php" class="site-logo">
                <span class="logo-icon">🎬</span>
                <span class="logo-text">Jay影视</span>
            </a>
        </div>
        
        <nav class="main-nav" id="mainNav">
            <a href="index.php" class="nav-link <?php echo $scriptName === 'index.php' ? 'active' : ''; ?>">
                <span class="nav-icon">🏠</span>首页
            </a>
            <a href="category.php?type=movie" class="nav-link <?php echo isset($_GET['type']) && $_GET['type'] === 'movie' ? 'active' : ''; ?>">
                <span class="nav-icon">🎬</span>电影
            </a>
            <a href="category.php?type=tv" class="nav-link <?php echo isset($_GET['type']) && $_GET['type'] === 'tv' ? 'active' : ''; ?>">
                <span class="nav-icon">📺</span>电视剧
            </a>
            <a href="category.php?type=variety" class="nav-link <?php echo isset($_GET['type']) && $_GET['type'] === 'variety' ? 'active' : ''; ?>">
                <span class="nav-icon">🎤</span>综艺
            </a>
            <a href="category.php?type=anime" class="nav-link <?php echo isset($_GET['type']) && $_GET['type'] === 'anime' ? 'active' : ''; ?>">
                <span class="nav-icon">🌸</span>动漫
            </a>
            <a href="feedback.php" class="nav-link <?php echo $scriptName === 'feedback.php' ? 'active' : ''; ?>">
                <span class="nav-icon">💬</span>反馈
            </a>
        </nav>
        
        <div class="header-right">
            <form class="search-form" action="search.php" method="get">
                <input type="text" name="q" placeholder="搜索影片、电视剧..." value="<?php echo isset($_GET['q']) ? clean($_GET['q']) : ''; ?>" class="search-input">
                <button type="submit" class="search-btn" aria-label="搜索">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                </button>
            </form>
            
            <?php if ($current_user): ?>
            <div class="user-menu" id="userMenu">
                <button class="user-avatar-btn">
                    <img src="<?php echo clean($current_user['avatar']); ?>" alt="avatar" class="user-avatar">
                </button>
                <div class="user-dropdown">
                    <div class="user-info">
                        <img src="<?php echo clean($current_user['avatar']); ?>" alt="avatar" class="user-avatar-large">
                        <div>
                            <div class="user-name"><?php echo clean($current_user['username']); ?></div>
                            <div class="user-email"><?php echo clean($current_user['email']); ?></div>
                        </div>
                    </div>
                    <div class="user-actions">
                        <a href="profile.php" class="dropdown-item">
                            <span>👤</span>个人中心
                        </a>
                        <a href="profile.php?tab=favorites" class="dropdown-item">
                            <span>⭐</span>我的收藏
                        </a>
                        <a href="profile.php?tab=history" class="dropdown-item">
                            <span>📺</span>观看历史
                        </a>
                        <a href="feedback.php" class="dropdown-item">
                            <span>💬</span>我的反馈
                        </a>
                        <?php if (is_admin()): ?>
                        <a href="admin/dashboard.php" class="dropdown-item admin-item">
                            <span>⚙️</span>管理后台
                        </a>
                        <?php endif; ?>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" class="dropdown-item logout">
                            <span>🚪</span>退出登录
                        </a>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <a href="login.php" class="btn btn-primary btn-sm">登录</a>
            <a href="register.php" class="btn btn-outline btn-sm">注册</a>
            <?php endif; ?>
        </div>
    </div>
</header>

<!-- 移动端菜单遮罩 -->
<div class="menu-overlay" id="menuOverlay"></div>
