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
        case 'reply':
            $feedbackId = intval($_POST['feedback_id']);
            $content = trim($_POST['content']);
            if (empty($content)) {
                $message = '回复内容不能为空';
                $messageType = 'error';
            } else {
                $feedback = $db->fetchOne("SELECT * FROM feedback WHERE id = ?", array($feedbackId));
                if ($feedback) {
                    $db->insert('feedback_replies', array(
                        'feedback_id' => $feedbackId,
                        'user_id' => 0,
                        'content' => $content,
                        'is_admin' => 1,
                        'likes' => 0
                    ));
                    $db->update('feedback', array('status' => 'resolved'), 'id = ?', array($feedbackId));
                    $message = '回复成功';
                    $messageType = 'success';
                }
            }
            break;

        case 'update_status':
            $feedbackId = intval($_POST['feedback_id']);
            $status = $_POST['status'];
            if (in_array($status, array('open', 'resolved', 'closed'))) {
                $db->update('feedback', array('status' => $status), 'id = ?', array($feedbackId));
                $message = '状态已更新';
                $messageType = 'success';
            }
            break;

        case 'delete':
            $feedbackId = intval($_POST['feedback_id']);
            $db->delete('feedback', 'id = ?', array($feedbackId));
            $message = '反馈已删除';
            $messageType = 'success';
            break;
    }
}

$filterStatus = isset($_GET['status']) ? $_GET['status'] : 'all';
$viewId = isset($_GET['view']) ? intval($_GET['view']) : 0;

$where = array();
$params = array();
if ($filterStatus !== 'all') {
    $where[] = "f.status = ?";
    $params[] = $filterStatus;
}
$whereSql = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

$feedbacks = $db->fetchAll("SELECT f.*, u.username, u.avatar FROM feedback f LEFT JOIN users u ON f.user_id = u.id $whereSql ORDER BY f.created_at DESC", $params);

$viewFeedback = null;
$viewReplies = array();
if ($viewId > 0) {
    $viewFeedback = $db->fetchOne("SELECT f.*, u.username, u.avatar FROM feedback f LEFT JOIN users u ON f.user_id = u.id WHERE f.id = ?", array($viewId));
    if ($viewFeedback) {
        $viewReplies = $db->fetchAll("SELECT fr.*, u.username, u.avatar FROM feedback_replies fr LEFT JOIN users u ON fr.user_id = u.id WHERE fr.feedback_id = ? ORDER BY CASE WHEN fr.is_admin = 1 THEN 0 ELSE 1 END, fr.created_at ASC", array($viewId));
    }
}

$page_title = '反馈管理';
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
        .message-toast { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .message-toast.success { background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; }
        .message-toast.error { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; }
        .filter-bar { display: flex; gap: 12px; margin-bottom: 20px; align-items: center; flex-wrap: wrap; }
        .filter-select { padding: 10px 16px; background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: var(--text); font-size: 0.9rem; outline: none; cursor: pointer; }
        .filter-select:focus { border-color: var(--primary); }
        .panel { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; overflow: hidden; margin-bottom: 24px; }
        .panel-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .panel-title { font-size: 1rem; font-weight: 600; }
        .panel-body { padding: 24px; }
        .feedback-list { display: flex; flex-direction: column; gap: 16px; }
        .feedback-item { background: rgba(4,7,13,0.5); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 20px; transition: all 0.15s ease; }
        .feedback-item:hover { border-color: var(--primary); }
        .feedback-header { display: flex; align-items: center; gap: 12px; margin-bottom: 12px; }
        .feedback-user { width: 36px; height: 36px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.9rem; }
        .feedback-user-info { flex: 1; }
        .feedback-username { font-weight: 600; font-size: 0.9rem; }
        .feedback-time { color: var(--muted); font-size: 0.8rem; }
        .feedback-content { color: var(--text-secondary); line-height: 1.7; margin-bottom: 16px; white-space: pre-wrap; }
        .feedback-meta { display: flex; align-items: center; gap: 12px; font-size: 0.8rem; color: var(--muted); margin-bottom: 12px; }
        .status-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-badge.open { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .status-badge.resolved { background: rgba(34,197,94,0.15); color: #22c55e; }
        .status-badge.closed { background: rgba(122,140,158,0.15); color: var(--muted); }
        .feedback-actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .action-btn { padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: all 0.15s ease; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .action-btn.primary { background: rgba(1,180,228,0.15); color: var(--primary); }
        .action-btn.primary:hover { background: rgba(1,180,228,0.25); }
        .action-btn.success { background: rgba(34,197,94,0.15); color: #22c55e; }
        .action-btn.success:hover { background: rgba(34,197,94,0.25); }
        .action-btn.warning { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .action-btn.warning:hover { background: rgba(245,158,11,0.25); }
        .action-btn.danger { background: rgba(239,68,68,0.15); color: #ef4444; }
        .action-btn.danger:hover { background: rgba(239,68,68,0.25); }
        .reply-box { background: rgba(4,7,13,0.5); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px; margin-top: 16px; }
        .reply-item { padding: 12px 16px; background: rgba(1,180,228,0.05); border-left: 3px solid var(--primary); border-radius: 0 10px 10px 0; margin-bottom: 10px; }
        .reply-item.admin { background: rgba(1,180,228,0.1); border-left-color: var(--primary); }
        .reply-header { display: flex; align-items: center; gap: 8px; margin-bottom: 6px; }
        .reply-badge { display: inline-block; padding: 2px 8px; background: var(--primary); color: var(--background); font-size: 0.65rem; font-weight: 700; border-radius: 4px; }
        .reply-username { font-weight: 600; font-size: 0.85rem; }
        .reply-time { color: var(--muted); font-size: 0.75rem; margin-left: auto; }
        .reply-content { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6; white-space: pre-wrap; }
        .reply-form { display: flex; gap: 10px; margin-top: 12px; }
        .reply-form textarea { flex: 1; padding: 10px 14px; background: rgba(4,7,13,0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: var(--text); font-size: 0.9rem; outline: none; resize: vertical; min-height: 60px; }
        .reply-form textarea:focus { border-color: var(--primary); }
        .btn-primary { padding: 12px 24px; background: linear-gradient(135deg, var(--primary), var(--accent)); color: var(--background); border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; }
        .btn-secondary { padding: 12px 24px; background: rgba(255,255,255,0.1); color: var(--text); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; font-weight: 500; cursor: pointer; transition: all 0.15s ease; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
        @media (max-width: 768px) {
            .admin-layout { grid-template-columns: 1fr; }
            .admin-sidebar { position: static; width: 100%; }
            .admin-main { margin-left: 0; }
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
                    <a href="users.php" class="admin-nav-item"><span class="nav-icon">👥</span>用户管理</a>
                    <a href="sources.php" class="admin-nav-item"><span class="nav-icon">📺</span>播放源管理</a>
                    <a href="announcements.php" class="admin-nav-item"><span class="nav-icon">📢</span>公告管理</a>
                    <a href="feedback.php" class="admin-nav-item active"><span class="nav-icon">💬</span>反馈管理</a>
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
                <h1>反馈管理</h1>
                <div class="admin-user">
                    <div class="avatar"><?php echo mb_substr($_SESSION['admin_name'], 0, 1); ?></div>
                    <span class="admin-user-name"><?php echo clean($_SESSION['admin_name']); ?></span>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="message-toast <?php echo $messageType; ?>"><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?> <?php echo clean($message); ?></div>
            <?php endif; ?>

            <?php if ($viewFeedback): ?>
            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">📝 反馈详情 #<?php echo $viewFeedback['id']; ?></span>
                    <a href="feedback.php" class="btn-secondary">← 返回列表</a>
                </div>
                <div class="panel-body">
                    <div class="feedback-item">
                        <div class="feedback-header">
                            <div class="feedback-user"><?php echo mb_substr($viewFeedback['username'], 0, 1); ?></div>
                            <div class="feedback-user-info">
                                <div class="feedback-username"><?php echo clean($viewFeedback['username']); ?></div>
                                <div class="feedback-time"><?php echo format_date($viewFeedback['created_at']); ?></div>
                            </div>
                            <span class="status-badge <?php echo $viewFeedback['status']; ?>"><?php echo $viewFeedback['status'] === 'open' ? '待处理' : ($viewFeedback['status'] === 'resolved' ? '已解决' : '已关闭'); ?></span>
                        </div>
                        <div class="feedback-content"><?php echo clean($viewFeedback['content']); ?></div>
                        <div class="feedback-meta">
                            <span>👍 <?php echo $viewFeedback['likes']; ?></span>
                        </div>
                        <form method="post" style="display: flex; gap: 10px; margin-bottom: 12px;">
                            <input type="hidden" name="action" value="update_status">
                            <input type="hidden" name="feedback_id" value="<?php echo $viewFeedback['id']; ?>">
                            <select name="status" class="filter-select" style="flex: 1;" onchange="this.form.submit()">
                                <option value="open" <?php echo $viewFeedback['status'] === 'open' ? 'selected' : ''; ?>>待处理</option>
                                <option value="resolved" <?php echo $viewFeedback['status'] === 'resolved' ? 'selected' : ''; ?>>已解决</option>
                                <option value="closed" <?php echo $viewFeedback['status'] === 'closed' ? 'selected' : ''; ?>>已关闭</option>
                            </select>
                        </form>
                    </div>

                    <?php if (!empty($viewReplies)): ?>
                    <div style="margin-top: 20px;">
                        <h4 style="margin-bottom: 12px; font-size: 0.95rem;">💬 回复 (<?php echo count($viewReplies); ?>)</h4>
                        <?php foreach ($viewReplies as $reply): ?>
                        <div class="reply-item <?php echo $reply['is_admin'] ? 'admin' : ''; ?>">
                            <div class="reply-header">
                                <span class="reply-username"><?php echo $reply['is_admin'] ? '管理员' : clean($reply['username']); ?></span>
                                <?php if ($reply['is_admin']): ?><span class="reply-badge">管理员</span><?php endif; ?>
                                <span class="reply-time"><?php echo format_date($reply['created_at']); ?></span>
                            </div>
                            <div class="reply-content"><?php echo clean($reply['content']); ?></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>

                    <div class="reply-box">
                        <form method="post" class="reply-form" style="flex-direction: column; gap: 10px;">
                            <input type="hidden" name="action" value="reply">
                            <input type="hidden" name="feedback_id" value="<?php echo $viewFeedback['id']; ?>">
                            <textarea name="content" placeholder="输入管理员回复内容...管理员回复将带有特殊徽章显示在前端" required></textarea>
                            <div style="display: flex; gap: 10px; justify-content: flex-end;">
                                <button type="submit" class="btn-primary" style="padding: 10px 20px;">发送回复</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            <?php else: ?>

            <div class="filter-bar">
                <span style="color: var(--muted); font-size: 0.9rem;">筛选:</span>
                <a href="feedback.php" class="filter-select" style="<?php echo $filterStatus === 'all' ? 'border-color: var(--primary); color: var(--primary);' : ''; ?>">全部</a>
                <a href="?status=open" class="filter-select" style="<?php echo $filterStatus === 'open' ? 'border-color: var(--primary); color: var(--primary);' : ''; ?>">待处理</a>
                <a href="?status=resolved" class="filter-select" style="<?php echo $filterStatus === 'resolved' ? 'border-color: var(--primary); color: var(--primary);' : ''; ?>">已解决</a>
                <a href="?status=closed" class="filter-select" style="<?php echo $filterStatus === 'closed' ? 'border-color: var(--primary); color: var(--primary);' : ''; ?>">已关闭</a>
            </div>

            <div class="panel">
                <div class="panel-body" style="padding: 20px;">
                    <?php if (empty($feedbacks)): ?>
                    <div class="empty-state">暂无反馈</div>
                    <?php else: ?>
                    <div class="feedback-list">
                        <?php foreach ($feedbacks as $fb): ?>
                        <div class="feedback-item">
                            <div class="feedback-header">
                                <div class="feedback-user"><?php echo mb_substr($fb['username'], 0, 1); ?></div>
                                <div class="feedback-user-info">
                                    <div class="feedback-username"><?php echo clean($fb['username']); ?></div>
                                    <div class="feedback-time"><?php echo format_date($fb['created_at']); ?></div>
                                </div>
                                <span class="status-badge <?php echo $fb['status']; ?>"><?php echo $fb['status'] === 'open' ? '待处理' : ($fb['status'] === 'resolved' ? '已解决' : '已关闭'); ?></span>
                            </div>
                            <div class="feedback-content"><?php echo clean(mb_substr($fb['content'], 0, 200)); ?><?php echo mb_strlen($fb['content']) > 200 ? '...' : ''; ?></div>
                            <div class="feedback-actions">
                                <a href="?view=<?php echo $fb['id']; ?>" class="action-btn primary">查看详情</a>
                                <a href="?view=<?php echo $fb['id']; ?>#reply" class="action-btn success">回复</a>
                                <form method="post" style="display:inline;" onsubmit="return confirm('确定删除此反馈？');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="feedback_id" value="<?php echo $fb['id']; ?>">
                                    <button type="submit" class="action-btn danger">删除</button>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php endif; ?>
        </main>
    </div>
</body>
</html>
