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
$editAnnouncement = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    switch ($action) {
        case 'add':
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            if (empty($title) || empty($content)) {
                $message = '标题和内容不能为空';
                $messageType = 'error';
            } else {
                $db->insert('announcements', array(
                    'title' => $title,
                    'content' => $content,
                    'is_active' => 1
                ));
                $message = '公告发布成功';
                $messageType = 'success';
            }
            break;

        case 'edit':
            $id = intval($_POST['id']);
            $title = trim($_POST['title']);
            $content = trim($_POST['content']);
            if (empty($title) || empty($content)) {
                $message = '标题和内容不能为空';
                $messageType = 'error';
            } else {
                $db->update('announcements', array(
                    'title' => $title,
                    'content' => $content
                ), 'id = ?', array($id));
                $message = '公告更新成功';
                $messageType = 'success';
            }
            break;

        case 'toggle':
            $id = intval($_POST['id']);
            $ann = $db->fetchOne("SELECT * FROM announcements WHERE id = ?", array($id));
            $newStatus = $ann['is_active'] ? 0 : 1;
            $db->update('announcements', array('is_active' => $newStatus), 'id = ?', array($id));
            $message = $newStatus ? '公告已启用' : '公告已禁用';
            $messageType = 'success';
            break;

        case 'delete':
            $id = intval($_POST['id']);
            $db->delete('announcements', 'id = ?', array($id));
            $message = '公告已删除';
            $messageType = 'success';
            break;
    }
}

if (isset($_GET['edit'])) {
    $editId = intval($_GET['edit']);
    $editAnnouncement = $db->fetchOne("SELECT * FROM announcements WHERE id = ?", array($editId));
}

$announcements = $db->fetchAll("SELECT * FROM announcements ORDER BY id DESC");

$page_title = '公告管理';
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
        .form-panel { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 24px; margin-bottom: 24px; }
        .form-panel h2 { font-size: 1.1rem; margin-bottom: 20px; }
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.85rem; color: var(--muted); }
        .form-control { width: 100%; padding: 12px 16px; background: rgba(4,7,13,0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: var(--text); font-size: 0.9rem; outline: none; }
        .form-control:focus { border-color: var(--primary); }
        textarea.form-control { resize: vertical; min-height: 120px; }
        .btn-primary { padding: 12px 24px; background: linear-gradient(135deg, var(--primary), var(--accent)); color: var(--background); border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-secondary { padding: 12px 24px; background: rgba(255,255,255,0.1); color: var(--text); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; font-weight: 500; cursor: pointer; transition: all 0.15s ease; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-secondary:hover { background: rgba(255,255,255,0.15); }
        .preview-btn { padding: 12px 24px; background: rgba(255,183,3,0.15); color: #ffb703; border: 1px solid rgba(255,183,3,0.3); border-radius: 10px; font-weight: 600; cursor: pointer; transition: all 0.15s ease; }
        .preview-btn:hover { background: rgba(255,183,3,0.25); }
        .panel { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; overflow: hidden; }
        .admin-table { width: 100%; border-collapse: collapse; }
        .admin-table th { text-align: left; padding: 14px 20px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.5px; color: var(--muted); border-bottom: 1px solid rgba(255,255,255,0.1); background: rgba(4,7,13,0.5); }
        .admin-table td { padding: 14px 20px; border-bottom: 1px solid rgba(255,255,255,0.05); font-size: 0.9rem; }
        .admin-table tbody tr:hover { background: rgba(255,255,255,0.03); }
        .admin-table tbody tr:last-child td { border-bottom: none; }
        .status-badge { display: inline-flex; align-items: center; padding: 3px 10px; border-radius: 20px; font-size: 0.7rem; font-weight: 600; }
        .status-badge.active { background: rgba(34,197,94,0.15); color: #22c55e; }
        .status-badge.inactive { background: rgba(122,140,158,0.15); color: var(--muted); }
        .table-actions { display: flex; gap: 6px; flex-wrap: wrap; }
        .action-btn { padding: 6px 12px; border-radius: 8px; font-size: 0.8rem; font-weight: 500; cursor: pointer; transition: all 0.15s ease; border: none; text-decoration: none; display: inline-flex; align-items: center; gap: 4px; }
        .action-btn.primary { background: rgba(1,180,228,0.15); color: var(--primary); }
        .action-btn.primary:hover { background: rgba(1,180,228,0.25); }
        .action-btn.success { background: rgba(34,197,94,0.15); color: #22c55e; }
        .action-btn.success:hover { background: rgba(34,197,94,0.25); }
        .action-btn.warning { background: rgba(245,158,11,0.15); color: #f59e0b; }
        .action-btn.warning:hover { background: rgba(245,158,11,0.25); }
        .action-btn.danger { background: rgba(239,68,68,0.15); color: #ef4444; }
        .action-btn.danger:hover { background: rgba(239,68,68,0.25); }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
        .preview-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 200; padding: 20px; }
        .preview-modal.active { display: flex; }
        .preview-modal-content { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 16px; padding: 32px; width: 100%; max-width: 480px; position: relative; }
        .preview-modal-close { position: absolute; top: 16px; right: 16px; background: none; border: none; color: var(--muted); font-size: 1.3rem; cursor: pointer; }
        .preview-modal-close:hover { color: var(--text); }
        .announcement-preview-modal { position: fixed; inset: 0; background: rgba(0,0,0,0.8); backdrop-filter: blur(8px); display: none; align-items: center; justify-content: center; z-index: 300; padding: 20px; }
        .announcement-preview-modal.active { display: flex; }
        .announcement-preview-content { background: var(--surface); border-radius: 16px; overflow: hidden; max-width: 500px; width: 100%; border-top: 4px solid var(--primary); }
        .announcement-preview-body { padding: 40px; text-align: center; }
        .announcement-preview-icon { width: 80px; height: 80px; margin: 0 auto 20px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 2rem; }
        .announcement-preview-title { color: var(--primary); font-size: 1.3rem; margin-bottom: 12px; }
        .announcement-preview-text { color: var(--text-secondary); line-height: 1.8; }
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
                    <a href="announcements.php" class="admin-nav-item active"><span class="nav-icon">📢</span>公告管理</a>
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
                <h1>公告管理</h1>
                <div class="admin-user">
                    <div class="avatar"><?php echo mb_substr($_SESSION['admin_name'], 0, 1); ?></div>
                    <span class="admin-user-name"><?php echo clean($_SESSION['admin_name']); ?></span>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="message-toast <?php echo $messageType; ?>"><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?> <?php echo clean($message); ?></div>
            <?php endif; ?>

            <div class="form-panel">
                <h2><?php echo $editAnnouncement ? '✏️ 编辑公告' : '➕ 发布新公告'; ?></h2>
                <form method="post">
                    <?php if ($editAnnouncement): ?>
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?php echo $editAnnouncement['id']; ?>">
                    <?php else: ?>
                    <input type="hidden" name="action" value="add">
                    <?php endif; ?>
                    <div class="form-group">
                        <label class="form-label">公告标题 *</label>
                        <input type="text" name="title" class="form-control" placeholder="公告标题" value="<?php echo $editAnnouncement ? clean($editAnnouncement['title']) : ''; ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">公告内容 *</label>
                        <textarea name="content" class="form-control" placeholder="请输入公告内容，支持多行文本..." required><?php echo $editAnnouncement ? clean($editAnnouncement['content']) : ''; ?></textarea>
                    </div>
                    <div style="display: flex; gap: 12px; align-items: center;">
                        <button type="submit" class="btn-primary"><?php echo $editAnnouncement ? '保存修改' : '发布公告'; ?></button>
                        <?php if ($editAnnouncement): ?>
                        <a href="announcements.php" class="btn-secondary">取消编辑</a>
                        <?php endif; ?>
                        <button type="button" class="preview-btn" onclick="previewAnnouncement()">👁️ 预览效果</button>
                    </div>
                </form>
            </div>

            <div class="panel">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>标题</th>
                            <th>内容预览</th>
                            <th>状态</th>
                            <th>创建时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($announcements)): ?>
                        <tr><td colspan="6" class="empty-state">暂无公告</td></tr>
                        <?php else: ?>
                        <?php foreach ($announcements as $ann): ?>
                        <tr>
                            <td style="color: var(--muted);">#<?php echo $ann['id']; ?></td>
                            <td style="font-weight: 600;"><?php echo clean($ann['title']); ?></td>
                            <td style="color: var(--muted); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;"><?php echo clean(mb_substr($ann['content'], 0, 60)); ?>...</td>
                            <td>
                                <?php if ($ann['is_active']): ?>
                                <span class="status-badge active">✅ 已启用</span>
                                <?php else: ?>
                                <span class="status-badge inactive">⏸️ 已禁用</span>
                                <?php endif; ?>
                            </td>
                            <td style="color: var(--muted); font-size: 0.85rem;"><?php echo format_date($ann['created_at']); ?></td>
                            <td>
                                <div class="table-actions">
                                    <button type="button" class="action-btn primary" onclick="previewAnnouncementItem('<?php echo addslashes($ann['title']); ?>', '<?php echo addslashes($ann['content']); ?>')">预览</button>
                                    <a href="?edit=<?php echo $ann['id']; ?>" class="action-btn success">编辑</a>
                                    <form method="post" style="display:inline;">
                                        <input type="hidden" name="action" value="toggle">
                                        <input type="hidden" name="id" value="<?php echo $ann['id']; ?>">
                                        <button type="submit" class="action-btn warning"><?php echo $ann['is_active'] ? '禁用' : '启用'; ?></button>
                                    </form>
                                    <form method="post" style="display:inline;" onsubmit="return confirm('确定删除此公告？');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo $ann['id']; ?>">
                                        <button type="submit" class="action-btn danger">删除</button>
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

    <div class="announcement-preview-modal" id="announcementPreviewModal">
        <div class="preview-modal-content">
            <button class="preview-modal-close" onclick="document.getElementById('announcementPreviewModal').classList.remove('active')">✕</button>
            <div class="announcement-preview-body">
                <div class="announcement-preview-icon">📢</div>
                <div class="announcement-preview-title" id="previewTitle">公告标题</div>
                <div class="announcement-preview-text" id="previewContent">公告内容预览...</div>
            </div>
        </div>
    </div>

    <script>
        function previewAnnouncement() {
            var title = document.querySelector('input[name="title"]').value || '公告标题';
            var content = document.querySelector('textarea[name="content"]').value || '公告内容预览...';
            document.getElementById('previewTitle').textContent = title;
            document.getElementById('previewContent').textContent = content;
            document.getElementById('announcementPreviewModal').classList.add('active');
        }
        function previewAnnouncementItem(title, content) {
            document.getElementById('previewTitle').textContent = title;
            document.getElementById('previewContent').textContent = content;
            document.getElementById('announcementPreviewModal').classList.add('active');
        }
    </script>
</body>
</html>
