<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_admin();

if (isset($_GET['clear_history'])) {
    unset($_SESSION['email_history']);
    redirect('emails.php');
}

$db = get_db();
$message = '';
$messageType = '';

$users = $db->fetchAll("SELECT id, username, email FROM users ORDER BY username ASC");

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'send') {
        $toType = $_POST['to_type'];
        $subject = trim($_POST['subject']);
        $content = trim($_POST['content']);
        $template = $_POST['template'];

        if (empty($subject) || empty($content)) {
            $message = '主题和内容不能为空';
            $messageType = 'error';
        } else {
            $mailer = new Mailer();
            $recipients = array();

            if ($toType === 'all') {
                $recipients = $users;
            } elseif ($toType === 'single') {
                $userId = intval($_POST['user_id']);
                $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", array($userId));
                if ($user) {
                    $recipients = array($user);
                }
            }

            $successCount = 0;
            $errorCount = 0;
            $historyLog = array();

            foreach ($recipients as $recipient) {
                $vars = array('content' => $content, 'title' => $subject, 'icon' => '📢');
                $result = $mailer->send_template($recipient['email'], $subject, $template, $vars);
                if ($result) {
                    $successCount++;
                    $historyLog[] = array(
                        'to' => $recipient['email'],
                        'subject' => $subject,
                        'sent_at' => date('Y-m-d H:i:s'),
                        'status' => '成功'
                    );
                } else {
                    $errorCount++;
                    $historyLog[] = array(
                        'to' => $recipient['email'],
                        'subject' => $subject,
                        'sent_at' => date('Y-m-d H:i:s'),
                        'status' => '失败'
                    );
                }
            }

            if ($successCount > 0) {
                $_SESSION['email_history'] = isset($_SESSION['email_history']) ? array_merge($historyLog, $_SESSION['email_history']) : $historyLog;
                if (count($_SESSION['email_history']) > 50) {
                    $_SESSION['email_history'] = array_slice($_SESSION['email_history'], 0, 50);
                }
            }

            $message = "邮件已发送：成功 {$successCount} 封，失败 {$errorCount} 封";
            $messageType = $errorCount > 0 ? 'error' : 'success';
        }
    }

    if ($action === 'test') {
        $subject = trim($_POST['subject']);
        $content = trim($_POST['content']);
        $testEmail = $_POST['test_email'];
        $mailer = new Mailer();
        $vars = array('content' => $content, 'title' => $subject, 'icon' => '📢');
        $result = $mailer->send_template($testEmail, $subject, 'default', $vars);
        if ($result) {
            $message = '测试邮件已发送到 ' . $testEmail;
            $messageType = 'success';
            $logEntry = array(
                'to' => $testEmail,
                'subject' => $subject,
                'sent_at' => date('Y-m-d H:i:s'),
                'status' => '成功'
            );
            $_SESSION['email_history'] = isset($_SESSION['email_history']) ? array_merge(array($logEntry), $_SESSION['email_history']) : array($logEntry);
        } else {
            $message = '测试邮件发送失败';
            $messageType = 'error';
        }
    }
}

$emailHistory = isset($_SESSION['email_history']) ? $_SESSION['email_history'] : array();

$templates = array(
    'verification' => '验证码邮件',
    'banned' => '封禁通知',
    'unbanned' => '解封通知',
    'feedback_reply' => '反馈回复',
    'default' => '自定义邮件'
);

$page_title = '邮件管理';
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
        .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 24px; }
        .panel { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; overflow: hidden; }
        .panel-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .panel-title { font-size: 1rem; font-weight: 600; }
        .panel-body { padding: 24px; }
        .form-group { margin-bottom: 18px; }
        .form-label { display: block; margin-bottom: 8px; font-weight: 600; font-size: 0.85rem; color: var(--muted); }
        .form-control { width: 100%; padding: 12px 16px; background: rgba(4,7,13,0.6); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; color: var(--text); font-size: 0.9rem; outline: none; }
        .form-control:focus { border-color: var(--primary); }
        textarea.form-control { resize: vertical; min-height: 140px; }
        .btn-primary { padding: 12px 24px; background: linear-gradient(135deg, var(--primary), var(--accent)); color: var(--background); border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-secondary { padding: 12px 24px; background: rgba(255,255,255,0.1); color: var(--text); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; font-weight: 500; cursor: pointer; transition: all 0.15s ease; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-secondary:hover { background: rgba(255,255,255,0.15); }
        .btn-danger { padding: 8px 16px; background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); border-radius: 10px; font-weight: 500; cursor: pointer; transition: all 0.15s ease; }
        .btn-danger:hover { background: rgba(239,68,68,0.25); }
        .email-type-options { display: flex; gap: 8px; margin-bottom: 12px; }
        .email-type-option { padding: 10px 16px; background: rgba(4,7,13,0.5); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; cursor: pointer; font-size: 0.85rem; transition: all 0.15s ease; flex: 1; text-align: center; }
        .email-type-option:hover { border-color: var(--primary); }
        .email-type-option.selected { background: rgba(1,180,228,0.15); border-color: var(--primary); color: var(--primary); }
        .history-list { max-height: 400px; overflow-y: auto; }
        .history-item { padding: 14px 16px; border-bottom: 1px solid rgba(255,255,255,0.05); display: flex; align-items: center; gap: 12px; }
        .history-item:last-child { border-bottom: none; }
        .history-icon { width: 36px; height: 36px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-size: 1rem; flex-shrink: 0; }
        .history-icon.success { background: rgba(34,197,94,0.15); color: #22c55e; }
        .history-icon.error { background: rgba(239,68,68,0.15); color: #ef4444; }
        .history-details { flex: 1; min-width: 0; }
        .history-to { font-weight: 500; font-size: 0.9rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .history-subject { color: var(--muted); font-size: 0.8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .history-time { color: var(--muted); font-size: 0.75rem; }
        .empty-state { text-align: center; padding: 40px 20px; color: var(--muted); }
        .template-info { padding: 12px 16px; background: rgba(1,180,228,0.05); border: 1px solid rgba(1,180,228,0.2); border-radius: 10px; margin-bottom: 16px; font-size: 0.85rem; color: var(--text-secondary); }
        @media (max-width: 768px) {
            .admin-layout { grid-template-columns: 1fr; }
            .admin-sidebar { position: static; width: 100%; }
            .admin-main { margin-left: 0; }
            .content-grid { grid-template-columns: 1fr; }
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
                    <a href="feedback.php" class="admin-nav-item"><span class="nav-icon">💬</span>反馈管理</a>
                </div>
                <div class="admin-nav-section">
                    <div class="admin-nav-label">工具</div>
                    <a href="emails.php" class="admin-nav-item active"><span class="nav-icon">📧</span>邮件管理</a>
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
                <h1>邮件管理</h1>
                <div class="admin-user">
                    <div class="avatar"><?php echo mb_substr($_SESSION['admin_name'], 0, 1); ?></div>
                    <span class="admin-user-name"><?php echo clean($_SESSION['admin_name']); ?></span>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="message-toast <?php echo $messageType; ?>"><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?> <?php echo clean($message); ?></div>
            <?php endif; ?>

            <div class="content-grid">
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">📤 发送邮件</span>
                    </div>
                    <div class="panel-body">
                        <form method="post" id="emailForm">
                            <input type="hidden" name="action" value="send">
                            <div class="form-group">
                                <label class="form-label">发送对象</label>
                                <div class="email-type-options" id="toTypeOptions">
                                    <div class="email-type-option selected" data-value="all">全体用户</div>
                                    <div class="email-type-option" data-value="single">单个用户</div>
                                </div>
                                <input type="hidden" name="to_type" id="toType" value="all">
                            </div>
                            <div class="form-group" id="userSelectGroup" style="display:none;">
                                <label class="form-label">选择用户</label>
                                <select name="user_id" class="form-control">
                                    <?php foreach ($users as $user): ?>
                                    <option value="<?php echo $user['id']; ?>"><?php echo clean($user['username']); ?> (<?php echo clean($user['email']); ?>)</option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">邮件模板</label>
                                <select name="template" class="form-control" id="templateSelect">
                                    <?php foreach ($templates as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $key === 'default' ? 'selected' : ''; ?>><?php echo $label; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label class="form-label">邮件主题 *</label>
                                <input type="text" name="subject" class="form-control" placeholder="请输入邮件主题" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">邮件内容 *</label>
                                <textarea name="content" class="form-control" placeholder="请输入邮件内容..." required></textarea>
                            </div>
                            <button type="submit" class="btn-primary" style="width: 100%;">发送邮件</button>
                        </form>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">🧪 测试邮件</span>
                    </div>
                    <div class="panel-body">
                        <div class="template-info">
                            发送测试邮件到指定地址，验证SMTP配置是否正常工作。
                        </div>
                        <form method="post">
                            <input type="hidden" name="action" value="test">
                            <div class="form-group">
                                <label class="form-label">测试邮箱地址</label>
                                <input type="email" name="test_email" class="form-control" placeholder="your@email.com" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">邮件主题</label>
                                <input type="text" name="subject" class="form-control" value="Jay影视 管理后台测试邮件" required>
                            </div>
                            <div class="form-group">
                                <label class="form-label">测试内容</label>
                                <textarea name="content" class="form-control" required>这是一封来自 Jay影视 管理后台的测试邮件。
如果您收到此邮件，说明SMTP配置正确，邮件系统运行正常。

发送时间: <?php echo date('Y-m-d H:i:s'); ?></textarea>
                            </div>
                            <button type="submit" class="btn-primary" style="width: 100%;">发送测试邮件</button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <span class="panel-title">📋 邮件发送历史</span>
                    <?php if (!empty($emailHistory)): ?>
                    <a href="?clear_history=1" class="btn-danger" style="border:none; padding:6px 12px; border-radius:8px; cursor:pointer; font-size:0.8rem; text-decoration:none;">清空历史</a>
                    <?php endif; ?>
                </div>
                <div class="panel-body">
                    <?php if (empty($emailHistory)): ?>
                    <div class="empty-state">暂无邮件发送记录</div>
                    <?php else: ?>
                    <div class="history-list">
                        <?php foreach ($emailHistory as $log): ?>
                        <div class="history-item">
                            <div class="history-icon <?php echo $log['status'] === '成功' ? 'success' : 'error'; ?>">
                                <?php echo $log['status'] === '成功' ? '✉️' : '❌'; ?>
                            </div>
                            <div class="history-details">
                                <div class="history-to"><?php echo clean($log['to']); ?></div>
                                <div class="history-subject"><?php echo clean($log['subject']); ?></div>
                            </div>
                            <div class="history-time"><?php echo clean($log['sent_at']); ?></div>
                            <span class="status-badge <?php echo $log['status'] === '成功' ? 'active' : 'inactive'; ?>" style="<?php echo $log['status'] === '成功' ? 'background: rgba(34,197,94,0.15);color:#22c55e;' : 'background: rgba(239,68,68,0.15);color:#ef4444;'; ?>">
                                <?php echo $log['status']; ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script>
        document.querySelectorAll('.email-type-option').forEach(function(opt) {
            opt.addEventListener('click', function() {
                document.querySelectorAll('.email-type-option').forEach(function(o) { o.classList.remove('selected'); });
                opt.classList.add('selected');
                document.getElementById('toType').value = opt.dataset.value;
                document.getElementById('userSelectGroup').style.display = opt.dataset.value === 'single' ? 'block' : 'none';
            });
        });
    </script>
</body>
</html>
