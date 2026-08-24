<?php
if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Access Denied');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$currentPage = basename($_SERVER['PHP_SELF']);
$adminName = isset($_SESSION['admin_name']) ? $_SESSION['admin_name'] : '管理员';
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar-header">
        <div class="admin-sidebar-brand">
            <div class="brand-icon">🎬</div>
            <span>Jay影视<span class="dev-tag-sm">开发者</span></span>
        </div>
    </div>
    <nav class="admin-sidebar-nav">
        <div class="admin-nav-section">
            <div class="admin-nav-label">概览</div>
            <a href="dashboard.php" class="admin-nav-item <?php echo $currentPage === 'dashboard.php' ? 'active' : ''; ?>">
                <span class="nav-icon">📊</span>仪表盘
            </a>
        </div>
        <div class="admin-nav-section">
            <div class="admin-nav-label">管理</div>
            <a href="users.php" class="admin-nav-item <?php echo $currentPage === 'users.php' ? 'active' : ''; ?>">
                <span class="nav-icon">👥</span>用户管理
            </a>
            <a href="sources.php" class="admin-nav-item <?php echo $currentPage === 'sources.php' ? 'active' : ''; ?>">
                <span class="nav-icon">📺</span>播放源管理
            </a>
            <a href="announcements.php" class="admin-nav-item <?php echo $currentPage === 'announcements.php' ? 'active' : ''; ?>">
                <span class="nav-icon">📢</span>公告管理
            </a>
            <a href="feedback.php" class="admin-nav-item <?php echo $currentPage === 'feedback.php' ? 'active' : ''; ?>">
                <span class="nav-icon">💬</span>反馈管理
            </a>
        </div>
        <div class="admin-nav-section">
            <div class="admin-nav-label">工具</div>
            <a href="emails.php" class="admin-nav-item <?php echo $currentPage === 'emails.php' ? 'active' : ''; ?>">
                <span class="nav-icon">📧</span>邮件管理
            </a>
            <a href="theme.php" class="admin-nav-item <?php echo $currentPage === 'theme.php' ? 'active' : ''; ?>">
                <span class="nav-icon">🎨</span>主题定制
            </a>
        </div>
        <div class="admin-nav-section">
            <div class="admin-nav-label">系统</div>
            <a href="../index.php" class="admin-nav-item">
                <span class="nav-icon">🏠</span>返回前台
            </a>
            <a href="logout.php" class="admin-nav-item">
                <span class="nav-icon">🚪</span>退出登录
            </a>
        </div>
    </nav>
    <div class="admin-sidebar-footer">
        <div class="admin-user">
            <div class="avatar"><?php echo mb_substr($adminName, 0, 1); ?></div>
            <div class="admin-user-info">
                <span class="admin-user-name"><?php echo clean($adminName); ?></span>
                <span class="admin-user-role">超级管理员</span>
            </div>
        </div>
        <div class="sidebar-version">v1.0.0</div>
    </div>
</aside>