<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_admin();

$db = get_db();
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'ban':
            $userId = intval($_POST['user_id']);
            $reason = isset($_POST['ban_reason']) ? trim($_POST['ban_reason']) : '';
            $duration = isset($_POST['ban_duration']) ? trim($_POST['ban_duration']) : 'permanent';
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", array($userId));

            if ($user) {
                $banUntil = null;
                if ($duration !== 'permanent') {
                    $hours = intval($duration);
                    $banUntil = date('Y-m-d H:i:s', time() + $hours * 3600);
                }
                $db->update('users', array(
                    'status' => 'banned',
                    'ban_until' => $banUntil,
                    'ban_reason' => $reason
                ), 'id = ?', array($userId));

                $mailer = new Mailer();
                $mailer->send_template($user['email'], '账号封禁通知', 'banned', array(
                    'username' => $user['username'],
                    'reason' => $reason,
                    'until' => $banUntil ? format_date($banUntil) : '永久'
                ));

                $message = '用户已被封禁';
                $messageType = 'success';
            }
            break;

        case 'unban':
            $userId = intval($_POST['user_id']);
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", array($userId));

            if ($user) {
                $db->update('users', array(
                    'status' => 'normal',
                    'ban_until' => null,
                    'ban_reason' => ''
                ), 'id = ?', array($userId));

                $mailer = new Mailer();
                $mailer->send_template($user['email'], '账号解封通知', 'unbanned', array(
                    'username' => $user['username']
                ));

                $message = '用户已被解封';
                $messageType = 'success';
            }
            break;

        case 'delete':
            $userId = intval($_POST['user_id']);
            $db->delete('users', 'id = ?', array($userId));
            $message = '用户已删除';
            $messageType = 'success';
            break;
    }
}

$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';

$where = array();
$params = array();

if (!empty($search)) {
    $where[] = "(username LIKE ? OR email LIKE ?)";
    $params[] = '%' . $search . '%';
    $params[] = '%' . $search . '%';
}

if ($filterStatus === 'normal') {
    $where[] = "status = 'normal'";
} elseif ($filterStatus === 'banned') {
    $where[] = "status = 'banned'";
}

$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';
$users = $db->fetchAll("SELECT * FROM users $whereSql ORDER BY id DESC", $params);

$viewUserId = isset($_GET['view']) ? intval($_GET['view']) : 0;
$viewUser = null;
$viewFavorites = array();
$viewHistory = array();

if ($viewUserId > 0) {
    $viewUser = $db->fetchOne("SELECT * FROM users WHERE id = ?", array($viewUserId));
    if ($viewUser) {
        $viewFavorites = $db->fetchAll("SELECT * FROM favorites WHERE user_id = ? ORDER BY created_at DESC", array($viewUserId));
        $viewHistory = $db->fetchAll("SELECT * FROM watch_history WHERE user_id = ? ORDER BY updated_at DESC", array($viewUserId));
    }
}

$page_title = '用户管理';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($page_title); ?> - Jay影视管理</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root { --primary: #01b4e4; --secondary: #0d253f; --accent: #1f80e0; --background: #04070d; --text: #ffffff; --muted: #9aa0a6; }
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
        .admin-user { display: flex; align-items: center; gap: 10px; padding: 6px 14px 6px 6px; background: var(--secondary); border-radius: 20px; cursor: pointer; }
        .admin-user .avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; }
        .admin-user-name { font-size: 0.85rem; font-weight: 500; }
        .toolbar { display: flex; align-items: center; gap: 16px; margin-bottom: 24px; flex-wrap: wrap; }
        .search-box { flex: 1; min-width: 240px; position: relative; }
        .search-box input { width: 100%; padding: 12px 16px 12px 44px; background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: var(--text); font-size: 0.9rem; outline: none; }
        .search-box input:focus { border-color: var(--primary); }
        .search-box .search-icon { position: absolute; left: 16px; top: 50%; transform: translateY(-50%); color: var(--muted); }
        .filter-select { padding: 12px 16px; background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: var(--text); font-size: 0.9rem; outline: none; cursor: pointer; }
        .filter-select:focus { border-color: var(--primary); }
        .panel { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; overflow: hidden; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th { text-align: left; padding: 14px 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(4,7,13,0.5); }
        .admin-table td { padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        .admin-table tbody tr:hover { background: rgba(255,255,255,0.03); }
        .admin-table tbody tr:last-child td { border-bottom: none; }
        .user-cell { display: flex; align-items: center; gap: 12px; }
        .user-avatar-sm { width: 40px; height: 40px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; flex-shrink: 0; }
        .user-info-sm .name { font-weight: 600; }
        .user-info-sm .email { color: var(--muted); font-size: 0.8rem; }
        .status-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-badge.active { background: rgba(34,197,94,0.15); color: #22c55e; }
        .status-badge.banned { background: rgba(239,68,68,0.15); color: #ef4444; }
        .table-actions { display: flex; gap: 6px; }
        .action-btn { padding: 6px 14px; border-radius: 8px; font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: all 0.15s ease; border: none; }
        .action-btn.ban { background: rgba(239,68,68,0.15); color: #ef4444; }
        .action-btn.ban:hover { background: rgba(239,68,68,0.25); }
        .action-btn.unban { background: rgba(34,197,94,0.15); color: #22c55e; }
        .action-btn.unban:hover { background: rgba(34,197,94,0.25); }
        .action-btn.view { background: rgba(1,180,228,0.15); color: var(--primary); }
        .action-btn.view:hover { background: rgba(1,180,228,0.25); }
        .action-btn.delete { background: rgba(239,68,68,0.15); color: #ef4444; }
        .action-btn.delete:hover { background: rgba(239,68,68,0.25); }
        .message-toast { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .message-toast.success { background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; }
        .message-toast.error { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; }
        .ban-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 200; padding: 20px; }
        .ban-modal.active { display: flex; }
        .ban-modal-content { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 32px; width: 100%; max-width: 480px; }
        .ban-modal-content h3 { margin-bottom: 20px; }
        .ban-modal-close { float: right; background: none; border: none; color: var(--muted); font-size: 1.3rem; cursor: pointer; }
        .ban-modal-close:hover { color: var(--text); }
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.85rem; color: var(--muted); }
        .form-control { width: 100%; padding: 12px 16px; background: rgba(4,7,13,0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: var(--text); font-size: 0.9rem; outline: none; }
        .form-control:focus { border-color: var(--primary); }
        textarea.form-control { resize: vertical; min-height: 80px; }
        .duration-options { display: flex; gap: 8px; margin-top: 8px; }
        .duration-option { padding: 8px 14px; background: rgba(4,7,13,0.5); border: 1px solid rgba(255,255,255,0.1); border-radius: 8px; cursor: pointer; font-size: 0.85rem; transition: all 0.15s ease; }
        .duration-option:hover { border-color: var(--primary); }
        .duration-option.selected { background: rgba(1,180,228,0.15); border-color: var(--primary); color: var(--primary); }
        .btn-primary { padding: 12px 24px; background: linear-gradient(135deg, var(--primary), var(--accent)); color: var(--background); border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-secondary { padding: 12px 24px; background: rgba(255,255,255,0.1); color: var(--text); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; font-weight: 500; cursor: pointer; transition: all 0.15s ease; }
        .btn-secondary:hover { background: rgba(255,255,255,0.15); }
        .modal-actions { display: flex; gap: 12px; justify-content: flex-end; margin-top: 20px; }
        .view-user-profile { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; margin-bottom: 24px; overflow: hidden; }
        .view-user-header { padding: 24px; display: flex; align-items: center; gap: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .view-user-avatar { width: 64px; height: 64px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); display: flex; align-items: center; justify-content: center; font-weight: 800; font-size: 1.5rem; }
        .view-user-stats { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 16px; padding: 24px; }
        .view-user-stat { text-align: center; }
        .view-user-stat .value { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .view-user-stat .label { font-size: 0.8rem; color: var(--muted); }
        .detail-section { padding: 16px 24px; border-bottom: 1px solid rgba(255,255,255,0.05); }
        .detail-section h4 { font-size: 0.95rem; margin-bottom: 12px; display: flex; align-items: center; gap: 8px; }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
        @media (max-width: 768px) {
            .admin-layout { grid-template-columns: 1fr; }
            .admin-sidebar { position: static; width: 100%; }
            .admin-main { margin-left: 0; }
            .toolbar { flex-direction: column; align-items: stretch; }
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
                    <a href="dashboard.php" class="admin-nav-item"><span class="nav-icon">📊</span>仪表盘</a>
                </div>
                <div class="admin-nav-section">
                    <div class="admin-nav-label">管理</div>
                    <a href="users.php" class="admin-nav-item active"><span class="nav-icon">👥</span>用户管理</a>
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
                <h1>用户管理</h1>
                <div class="admin-user">
                    <div class="avatar"><?php echo mb_substr($_SESSION['admin_name'], 0, 1); ?></div>
                    <span class="admin-user-name"><?php echo clean($_SESSION['admin_name']); ?></span>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="message-toast <?php echo $messageType; ?>">✅ <?php echo clean($message); ?></div>
            <?php endif; ?>

            <div class="toolbar">
                <form method="get" style="display: flex; gap: 12px; width: 100%; align-items: center;">
                    <div class="search-box">
                        <span class="search-icon">🔍</span>
                        <input type="text" name="search" placeholder="搜索用户名或邮箱..." value="<?php echo clean($search); ?>">
                    </div>
                    <select name="status" class="filter-select" onchange="this.form.submit()">
                        <option value="all" <?php echo $filterStatus === 'all' ? 'selected' : ''; ?>>全部状态</option>
                        <option value="normal" <?php echo $filterStatus === 'normal' ? 'selected' : ''; ?>>正常用户</option>
                        <option value="banned" <?php echo $filterStatus === 'banned' ? 'selected' : ''; ?>>封禁用户</option>
                    </select>
                    <button type="submit" class="btn-primary" style="padding: 12px 24px;">搜索</button>
                </form>
            </div>

            <?php if ($viewUser): ?>
            <div class="view-user-profile">
                <div class="view-user-header">
                    <div class="view-user-avatar"><?php echo mb_substr($viewUser['username'], 0, 1); ?></div>
                    <div style="flex: 1;">
                        <div style="font-size: 1.2rem; font-weight: 700;"><?php echo clean($viewUser['username']); ?></div>
                        <div style="color: var(--muted);"><?php echo clean($viewUser['email']); ?> · 注册于 <?php echo format_date($viewUser['created_at']); ?></div>
                    </div>
                    <a href="users.php" class="btn-secondary">关闭详情</a>
                </div>
                <div class="view-user-stats">
                    <div class="view-user-stat"><div class="value"><?php echo count($viewFavorites); ?></div><div class="label">收藏数</div></div>
                    <div class="view-user-stat"><div class="value"><?php echo count($viewHistory); ?></div><div class="label">观看记录</div></div>
                    <div class="view-user-stat"><div class="value"><?php echo $viewUser['status'] === 'banned' ? '已封禁' : '正常'; ?></div><div class="label">账号状态</div></div>
                </div>
                <?php if (!empty($viewFavorites)): ?>
                <div class="detail-section">
                    <h4>⭐ 收藏列表</h4>
                    <table class="admin-table">
                        <thead><tr><th>影片</th><th>类型</th><th>时间</th></tr></thead>
                        <tbody>
                            <?php foreach ($viewFavorites as $fav): ?>
                            <tr>
                                <td><?php echo clean($fav['title']); ?></td>
                                <td><?php echo $fav['media_type'] === 'movie' ? '电影' : ($fav['media_type'] === 'tv' ? '电视剧' : $fav['media_type']); ?></td>
                                <td style="color: var(--muted);"><?php echo format_date($fav['created_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
                <?php if (!empty($viewHistory)): ?>
                <div class="detail-section">
                    <h4>📺 观看历史</h4>
                    <table class="admin-table">
                        <thead><tr><th>影片</th><th>进度</th><th>时间</th></tr></thead>
                        <tbody>
                            <?php foreach ($viewHistory as $h): ?>
                            <tr>
                                <td><?php echo clean($h['title']); ?></td>
                                <td style="color: var(--primary);"><?php echo round($h['progress'] * 100); ?>%</td>
                                <td style="color: var(--muted);"><?php echo format_date($h['updated_at']); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="panel">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>用户</th>
                            <th>邮箱</th>
                            <th>状态</th>
                            <th>注册时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($users)): ?>
                        <tr><td colspan="5" class="empty-state">未找到用户</td></tr>
                        <?php else: ?>
                        <?php foreach ($users as $user): ?>
                        <tr>
                            <td>
                                <div class="user-cell">
                                    <div class="user-avatar-sm"><?php echo mb_substr($user['username'], 0, 1); ?></div>
                                    <div class="user-info-sm">
                                        <div class="name"><?php echo clean($user['username']); ?></div>
                                        <div class="email">ID: <?php echo $user['id']; ?></div>
                                    </div>
                                </div>
                            </td>
                            <td style="color: var(--muted);"><?php echo clean($user['email']); ?></td>
                            <td>
                                <?php if ($user['status'] === 'banned'): ?>
                                <span class="status-badge banned">🚫 封禁</span>
                                <?php else: ?>
                                <span class="status-badge active">✅ 正常</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--muted); font-size: 0.85rem;"><?php echo format_date($user['created_at']); ?></td>
                            <td>
                                <div class="table-actions">
                                    <a href="users.php?view=<?php echo $user['id']; ?>" class="action-btn view">查看</a>
                                    <?php if ($user['status'] === 'banned'): ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('确定解封用户？');">
                                        <input type="hidden" name="action" value="unban">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="action-btn unban">解封</button>
                                    </form>
                                    <?php else: ?>
                                    <button type="button" class="action-btn ban" onclick="openBanModal(<?php echo $user['id']; ?>, '<?php echo clean($user['username']); ?>')">封禁</button>
                                    <?php endif; ?>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('确定删除用户？此操作不可恢复！');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="action-btn delete">删除</button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </main>
    </div>

    <div class="ban-modal" id="banModal">
        <div class="ban-modal-content">
            <button class="ban-modal-close" onclick="closeBanModal()">✕</button>
            <h3>封禁用户 <span id="banUserName" style="color: var(--primary);"></span></h3>
            <form method="post" id="banForm">
                <input type="hidden" name="action" value="ban">
                <input type="hidden" name="user_id" id="banUserId">
                <div class="form-group">
                    <label class="form-label">封禁时长</label>
                    <div class="duration-options" id="durationOptions">
                        <div class="duration-option" data-value="1">1小时</div>
                        <div class="duration-option" data-value="24">24小时</div>
                        <div class="duration-option" data-value="72">3天</div>
                        <div class="duration-option" data-value="168">7天</div>
                        <div class="duration-option selected" data-value="permanent">永久</div>
                    </div>
                    <input type="hidden" name="ban_duration" id="banDuration" value="permanent">
                </div>
                <div class="form-group">
                    <label class="form-label">封禁原因</label>
                    <textarea name="ban_reason" class="form-control" placeholder="请输入封禁原因..." required></textarea>
                </div>
                <div class="modal-actions">
                    <button type="button" class="btn-secondary" onclick="closeBanModal()">取消</button>
                    <button type="submit" class="btn-primary">确认封禁</button>
                </div>
            </form>
        </div>
    </div>

    <script>
        function openBanModal(userId, username) {
            document.getElementById('banUserId').value = userId;
            document.getElementById('banUserName').textContent = username;
            document.getElementById('banModal').classList.add('active');
        }
        function closeBanModal() {
            document.getElementById('banModal').classList.remove('active');
        }
        document.querySelectorAll('.duration-option').forEach(function(option) {
            option.addEventListener('click', function() {
                document.querySelectorAll('.duration-option').forEach(function(o) { o.classList.remove('selected'); });
                option.classList.add('selected');
                document.getElementById('banDuration').value = option.dataset.value;
            });
        });
    </script>
</body>
</html>
