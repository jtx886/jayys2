<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_admin();

$db = get_db();
$config = get_config();

$stats = get_dashboard_stats();

$recentRegistrations = $db->fetchAll("SELECT * FROM users ORDER BY created_at DESC LIMIT 5");
$recentFeedback = $db->fetchAll("SELECT f.*, u.username FROM feedback f LEFT JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC LIMIT 5");
$recentHistory = $db->fetchAll("SELECT wh.*, u.username FROM watch_history wh LEFT JOIN users u ON wh.user_id = u.id ORDER BY wh.updated_at DESC LIMIT 5");
$recentFavorites = $db->fetchAll("SELECT fav.*, u.username FROM favorites fav LEFT JOIN users u ON fav.user_id = u.id ORDER BY fav.created_at DESC LIMIT 5");

$registrationTrend = array();
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-{$i} days"));
    $count = $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE DATE(created_at) = ?", array($date))['count'];
    $registrationTrend[] = array('date' => $date, 'count' => $count);
}

$maxReg = max(1, max(array_column($registrationTrend, 'count')));

$selectedUserData = null;
$selectedUserFavorites = array();
$selectedUserHistory = array();
$usersList = $db->fetchAll("SELECT id, username, status FROM users ORDER BY id ASC");

if (isset($_GET['user_id']) && is_numeric($_GET['user_id'])) {
    $userId = intval($_GET['user_id']);
    $selectedUserData = $db->fetchOne("SELECT * FROM users WHERE id = ?", array($userId));
    if ($selectedUserData) {
        $selectedUserFavorites = $db->fetchAll("SELECT * FROM favorites WHERE user_id = ? ORDER BY created_at DESC LIMIT 10", array($userId));
        $selectedUserHistory = $db->fetchAll("SELECT * FROM watch_history WHERE user_id = ? ORDER BY updated_at DESC LIMIT 10", array($userId));
    }
}

$filterHistoryUser = isset($_GET['filter_history_user']) ? intval($_GET['filter_history_user']) : 0;
$filterFavUser = isset($_GET['filter_fav_user']) ? intval($_GET['filter_fav_user']) : 0;

$filteredHistory = $recentHistory;
if ($filterHistoryUser > 0) {
    $filteredHistory = $db->fetchAll("SELECT wh.*, u.username FROM watch_history wh LEFT JOIN users u ON wh.user_id = u.id WHERE wh.user_id = ? ORDER BY wh.updated_at DESC LIMIT 10", array($filterHistoryUser));
}

$filteredFavorites = $recentFavorites;
if ($filterFavUser > 0) {
    $filteredFavorites = $db->fetchAll("SELECT fav.*, u.username FROM favorites fav LEFT JOIN users u ON fav.user_id = u.id WHERE fav.user_id = ? ORDER BY fav.created_at DESC LIMIT 10", array($filterFavUser));
}

$page_title = '仪表盘';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($page_title); ?> - Jay影视管理</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary: #01b4e4;
            --secondary: #0d253f;
            --accent: #1f80e0;
            --background: #04070d;
            --text: #ffffff;
            --muted: #9aa0a6;
        }
        .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .admin-sidebar { background: var(--secondary); border-right: 1px solid rgba(255,255,255,0.1); padding: 24px 0; position: fixed; top: 0; left: 0; bottom: 0; width: 260px; overflow-y: auto; z-index: 50; }
        .admin-sidebar-header { padding: 0 24px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 16px; }
        .admin-sidebar-brand { display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 800; }
        .admin-sidebar-brand .brand-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; }
        .dev-tag-sm { display: inline-block; padding: 2px 8px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; font-size: 0.65rem; font-weight: 700; border-radius: 5px; margin-left: 4px; }
        .admin-sidebar-nav { padding: 0 16px; }
        .admin-nav-section { margin-bottom: 20px; }
        .admin-nav-label { padding: 8px 12px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); }
        .admin-nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-radius: 8px; color: #b3c4d4; font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.15s ease; margin-bottom: 4px; text-decoration: none; }
        .admin-nav-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
        .admin-nav-item.active { background: rgba(1,180,228,0.15); color: var(--primary); }
        .admin-nav-item .nav-icon { width: 20px; text-align: center; }
        .admin-main { margin-left: 260px; padding: 24px; }
        .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
        .admin-header h1 { font-size: 1.5rem; }
        .admin-header-actions { display: flex; gap: 12px; align-items: center; }
        .admin-user { display: flex; align-items: center; gap: 10px; padding: 6px 14px 6px 6px; background: var(--secondary); border-radius: 20px; cursor: pointer; }
        .admin-user:hover { background: rgba(255,255,255,0.05); }
        .admin-user .avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; }
        .admin-user-name { font-size: 0.85rem; font-weight: 500; }
        .dashboard-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 20px; margin-bottom: 32px; }
        .stat-card { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 24px; transition: all 0.25s ease; }
        .stat-card:hover { transform: translateY(-4px); box-shadow: 0 12px 32px rgba(0,0,0,0.4); border-color: var(--primary); }
        .stat-card-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
        .stat-card-title { font-size: 0.85rem; color: var(--muted); font-weight: 500; }
        .stat-card-icon { width: 40px; height: 40px; border-radius: 10px; display: flex; align-items: center; justify-content: center; font-size: 1.2rem; }
        .stat-card-icon.primary { background: rgba(1,180,228,0.15); color: var(--primary); }
        .stat-card-icon.accent { background: rgba(255,183,3,0.15); color: #ffb703; }
        .stat-card-icon.success { background: rgba(34,197,94,0.15); color: #22c55e; }
        .stat-card-icon.info { background: rgba(59,130,246,0.15); color: #3b82f6; }
        .stat-card-value { font-size: 2rem; font-weight: 800; margin-bottom: 4px; }
        .stat-card-footer { margin-top: 12px; font-size: 0.8rem; color: var(--muted); }
        .content-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px; margin-bottom: 32px; }
        .panel { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; overflow: hidden; }
        .panel-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .panel-title { font-size: 1rem; font-weight: 600; display: flex; align-items: center; gap: 8px; }
        .panel-body { padding: 16px 24px; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th { text-align: left; padding: 12px 16px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); border-bottom: 1px solid rgba(255,255,255,0.1); }
        .admin-table td { padding: 12px 16px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        .admin-table tbody tr:hover { background: rgba(255,255,255,0.03); }
        .admin-table tbody tr:last-child td { border-bottom: none; }
        .user-cell { display: flex; align-items: center; gap: 10px; }
        .user-avatar-sm { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.8rem; flex-shrink: 0; }
        .user-info-sm .name { font-weight: 600; font-size: 0.85rem; }
        .user-info-sm .email { color: var(--muted); font-size: 0.75rem; }
        .status-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-badge.active { background: rgba(34,197,94,0.15); color: #22c55e; }
        .status-badge.inactive { background: rgba(122,140,158,0.15); color: var(--muted); }
        .status-badge.pending { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .status-badge.banned { background: rgba(239,68,68,0.15); color: #ef4444; }
        .trend-chart { padding: 20px 24px; }
        .bar-chart { display: flex; align-items: flex-end; gap: 12px; height: 160px; padding: 10px 0; }
        .bar-item { flex: 1; display: flex; flex-direction: column; align-items: center; gap: 6px; height: 100%; justify-content: flex-end; }
        .bar { width: 100%; max-width: 40px; background: linear-gradient(to top, var(--primary), var(--accent)); border-radius: 6px 6px 0 0; transition: all 0.3s ease; position: relative; }
        .bar:hover { background: linear-gradient(to top, var(--accent), var(--primary)); transform: scaleY(1.05); }
        .bar-value { position: absolute; top: -20px; left: 50%; transform: translateX(-50%); font-size: 0.75rem; font-weight: 700; color: var(--primary); opacity: 0; transition: opacity 0.2s; }
        .bar:hover .bar-value { opacity: 1; }
        .bar-label { font-size: 0.7rem; color: var(--muted); }
        .quick-actions { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; padding: 20px 24px; }
        .quick-action-btn { display: flex; align-items: center; gap: 10px; padding: 14px 16px; background: rgba(4,7,13,0.5); border: 1px solid rgba(255,255,255,0.08); border-radius: 10px; color: var(--text); font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.2s ease; text-decoration: none; }
        .quick-action-btn:hover { background: rgba(1,180,228,0.1); border-color: var(--primary); color: var(--primary); transform: translateY(-2px); }
        .quick-action-btn .qa-icon { font-size: 1.2rem; }
        .filter-select { padding: 8px 12px; background: rgba(4,7,13,0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; color: var(--text); font-size: 0.85rem; outline: none; cursor: pointer; }
        .filter-select:focus { border-color: var(--primary); }
        .section-title-bar { display: flex; align-items: center; justify-content: space-between; margin: 32px 0 16px; }
        .section-title-bar h2 { font-size: 1.2rem; }
        .user-stats-panel { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; margin-bottom: 32px; }
        .user-stats-header { display: flex; align-items: center; gap: 20px; padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); flex-wrap: wrap; }
        .user-stats-body { padding: 24px; }
        .user-stat-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 16px; margin-bottom: 24px; }
        .user-stat-card { background: rgba(4,7,13,0.5); border-radius: 10px; padding: 16px; text-align: center; }
        .user-stat-card .value { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .user-stat-card .label { font-size: 0.8rem; color: var(--muted); }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
        @media (max-width: 768px) {
            .admin-layout { grid-template-columns: 1fr; }
            .admin-sidebar { position: static; width: 100%; }
            .admin-main { margin-left: 0; }
            .content-grid { grid-template-columns: 1fr; }
            .bar-chart { gap: 6px; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
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
                    <a href="dashboard.php" class="admin-nav-item active"><span class="nav-icon">📊</span>仪表盘</a>
                </div>
                <div class="admin-nav-section">
                    <div class="admin-nav-label">管理</div>
                    <a href="users.php" class="admin-nav-item"><span class="nav-icon">👥</span>用户管理</a>
                    <a href="sources.php" class="admin-nav-item"><span class="nav-icon">📺</span>播放源管理</a>
                    <a href="announcements.php" class="admin-nav-item"><span class="nav-icon">📢</span>公告管理</a>
                    <a href="feedback.php" class="admin-nav-item"><span class="nav-icon">💬</span>反馈管理</a>
                </div>
                <div class="admin-nav-section">
                    <div class="admin-nav-label">工具</div>
                    <a href="emails.php" class="admin-nav-item"><span class="nav-icon">📧</span>邮件管理</a>
                    <a href="theme.php" class="admin-nav-item"><span class="nav-icon">🎨</span>主题定制</a>
                </div>
                <div class="admin-nav-section">
                    <div class="admin-nav-label">系统</div>
                    <a href="../index.php" class="admin-nav-item"><span class="nav-icon">🏠</span>返回前台</a>
                    <a href="logout.php" class="admin-nav-item"><span class="nav-icon">🚪</span>退出登录</a>
                </div>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>仪表盘</h1>
                <div class="admin-header-actions">
                    <div class="admin-user">
                        <div class="avatar"><?php echo mb_substr($_SESSION['admin_name'], 0, 1); ?></div>
                        <span class="admin-user-name"><?php echo clean($_SESSION['admin_name']); ?></span>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">总用户数</span>
                        <span class="stat-card-icon primary">👥</span>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['total_users']; ?></div>
                    <div class="stat-card-footer">系统注册用户</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">活跃用户</span>
                        <span class="stat-card-icon success">✅</span>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['active_users']; ?></div>
                    <div class="stat-card-footer">正常状态用户</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">封禁用户</span>
                        <span class="stat-card-icon accent">🚫</span>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['banned_users']; ?></div>
                    <div class="stat-card-footer">已被封禁的账号</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">总收藏数</span>
                        <span class="stat-card-icon primary">⭐</span>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['total_favorites']; ?></div>
                    <div class="stat-card-footer">用户收藏总数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">观看历史</span>
                        <span class="stat-card-icon info">📺</span>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['total_history']; ?></div>
                    <div class="stat-card-footer">累计观看记录</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">反馈数量</span>
                        <span class="stat-card-icon accent">💬</span>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['total_feedback']; ?></div>
                    <div class="stat-card-footer">用户反馈总数</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">近7天新增</span>
                        <span class="stat-card-icon success">📈</span>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['new_users']; ?></div>
                    <div class="stat-card-footer">最近一周注册</div>
                </div>
                <div class="stat-card">
                    <div class="stat-card-header">
                        <span class="stat-card-title">活跃率</span>
                        <span class="stat-card-icon info">📊</span>
                    </div>
                    <div class="stat-card-value"><?php echo $stats['total_users'] > 0 ? round($stats['active_users'] / $stats['total_users'] * 100, 1) : 0; ?>%</div>
                    <div class="stat-card-footer">活跃用户占比</div>
                </div>
            </div>

            <div class="panel" style="margin-bottom: 32px;">
                <div class="panel-header">
                    <span class="panel-title">📈 用户注册趋势 (近7天)</span>
                </div>
                <div class="trend-chart">
                    <div class="bar-chart">
                        <?php foreach ($registrationTrend as $day): ?>
                        <div class="bar-item">
                            <div class="bar" style="height: <?php echo $day['count'] > 0 ? max(4, ($day['count'] / $maxReg) * 140) : 4; ?>px;">
                                <span class="bar-value"><?php echo $day['count']; ?></span>
                            </div>
                            <span class="bar-label"><?php echo date('m/d', strtotime($day['date'])); ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <div class="quick-actions" style="margin-bottom: 32px;">
                <a href="users.php" class="quick-action-btn"><span class="qa-icon">👥</span>管理用户</a>
                <a href="announcements.php" class="quick-action-btn"><span class="qa-icon">📢</span>发布公告</a>
                <a href="feedback.php" class="quick-action-btn"><span class="qa-icon">💬</span>处理反馈</a>
                <a href="sources.php" class="quick-action-btn"><span class="qa-icon">📺</span>播放源</a>
                <a href="emails.php" class="quick-action-btn"><span class="qa-icon">📧</span>发送邮件</a>
                <a href="theme.php" class="quick-action-btn"><span class="qa-icon">🎨</span>主题设置</a>
            </div>

            <?php if ($selectedUserData): ?>
            <div class="user-stats-panel">
                <div class="user-stats-header">
                    <div>
                        <strong style="font-size: 1rem;">用户详情: <?php echo clean($selectedUserData['username']); ?></strong>
                        <div style="color: var(--muted); font-size: 0.8rem;"><?php echo clean($selectedUserData['email']); ?> · 注册于 <?php echo format_date($selectedUserData['created_at']); ?></div>
                    </div>
                    <a href="dashboard.php" class="btn btn-secondary btn-sm">清除筛选</a>
                </div>
                <div class="user-stats-body">
                    <div class="user-stat-cards">
                        <div class="user-stat-card"><div class="value"><?php echo count($selectedUserFavorites); ?></div><div class="label">收藏数</div></div>
                        <div class="user-stat-card"><div class="value"><?php echo count($selectedUserHistory); ?></div><div class="label">观看记录</div></div>
                        <div class="user-stat-card"><div class="value"><?php echo $selectedUserData['status'] === 'banned' ? '已封禁' : '正常'; ?></div><div class="label">账号状态</div></div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <div class="content-grid">
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">👥 最近注册</span>
                    </div>
                    <div class="panel-body" style="padding: 0;">
                        <?php if (empty($recentRegistrations)): ?>
                        <div class="empty-state">暂无注册数据</div>
                        <?php else: ?>
                        <table class="admin-table">
                            <thead><tr><th>用户</th><th>邮箱</th><th>状态</th><th>注册时间</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentRegistrations as $user): ?>
                                <tr>
                                    <td>
                                        <div class="user-cell">
                                            <div class="user-avatar-sm"><?php echo mb_substr($user['username'], 0, 1); ?></div>
                                            <span><?php echo clean($user['username']); ?></span>
                                        </div>
                                    </td>
                                    <td style="color: var(--muted);"><?php echo clean($user['email']); ?></td>
                                    <td><span class="status-badge <?php echo $user['status'] === 'banned' ? 'banned' : 'active'; ?>"><?php echo $user['status'] === 'banned' ? '封禁' : '正常'; ?></span></td>
                                    <td style="color: var(--muted); font-size: 0.8rem;"><?php echo format_date($user['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">💬 最近反馈</span>
                    </div>
                    <div class="panel-body" style="padding: 0;">
                        <?php if (empty($recentFeedback)): ?>
                        <div class="empty-state">暂无反馈</div>
                        <?php else: ?>
                        <table class="admin-table">
                            <thead><tr><th>用户</th><th>内容</th><th>状态</th><th>时间</th></tr></thead>
                            <tbody>
                                <?php foreach ($recentFeedback as $fb): ?>
                                <tr>
                                    <td style="font-weight: 600;"><?php echo clean($fb['username']); ?></td>
                                    <td style="max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo clean(mb_substr($fb['content'], 0, 40)); ?></td>
                                    <td><span class="status-badge <?php echo $fb['status'] === 'open' ? 'pending' : ($fb['status'] === 'resolved' ? 'active' : 'inactive'); ?>"><?php echo $fb['status'] === 'open' ? '待处理' : ($fb['status'] === 'resolved' ? '已解决' : '已关闭'); ?></span></td>
                                    <td style="color: var(--muted); font-size: 0.8rem;"><?php echo format_date($fb['created_at']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="section-title-bar">
                <h2>📺 观看历史</h2>
                <form method="get" style="display: flex; gap: 12px; align-items: center;">
                    <input type="hidden" name="user_id" value="<?php echo $selectedUserData ? $selectedUserData['id'] : ''; ?>">
                    <select name="filter_history_user" class="filter-select" onchange="this.form.submit()">
                        <option value="0">全部用户</option>
                        <?php foreach ($usersList as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filterHistoryUser == $u['id'] ? 'selected' : ''; ?>><?php echo clean($u['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="panel" style="margin-bottom: 32px;">
                <div class="panel-body" style="padding: 0;">
                    <?php if (empty($filteredHistory)): ?>
                    <div class="empty-state">暂无观看记录</div>
                    <?php else: ?>
                    <table class="admin-table">
                        <thead><tr><th>用户</th><th>影片</th><th>进度</th><th>更新时间</th></tr></thead>
                        <tbody>
                            <?php foreach ($filteredHistory as $h): ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo clean($h['username']); ?></td>
                                <td><?php echo clean($h['title']); ?></td>
                                <td style="color: var(--primary);"><?php echo round($h['progress'] * 100); ?>%</td>
                                <td style="color: var(--muted); font-size: 0.8rem;"><?php echo format_date($h['updated_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

            <div class="section-title-bar">
                <h2>⭐ 用户收藏</h2>
                <form method="get" style="display: flex; gap: 12px; align-items: center;">
                    <input type="hidden" name="user_id" value="<?php echo $selectedUserData ? $selectedUserData['id'] : ''; ?>">
                    <select name="filter_fav_user" class="filter-select" onchange="this.form.submit()">
                        <option value="0">全部用户</option>
                        <?php foreach ($usersList as $u): ?>
                        <option value="<?php echo $u['id']; ?>" <?php echo $filterFavUser == $u['id'] ? 'selected' : ''; ?>><?php echo clean($u['username']); ?></option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <div class="panel">
                <div class="panel-body" style="padding: 0;">
                    <?php if (empty($filteredFavorites)): ?>
                    <div class="empty-state">暂无收藏记录</div>
                    <?php else: ?>
                    <table class="admin-table">
                        <thead><tr><th>用户</th><th>影片</th><th>类型</th><th>收藏时间</th></tr></thead>
                        <tbody>
                            <?php foreach ($filteredFavorites as $f): ?>
                            <tr>
                                <td style="font-weight: 600;"><?php echo clean($f['username']); ?></td>
                                <td><?php echo clean($f['title']); ?></td>
                                <td><span class="status-badge active"><?php echo $f['media_type'] === 'movie' ? '电影' : ($f['media_type'] === 'tv' ? '电视剧' : $f['media_type']); ?></span></td>
                                <td style="color: var(--muted); font-size: 0.8rem;"><?php echo format_date($f['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</body>
</html>
