<?php
$page_title = '反馈中心';
require_once __DIR__ . '/includes/header.php';

$db = get_db();
$current_user = current_user();

try {
    $db->query("ALTER TABLE feedback ADD COLUMN title TEXT DEFAULT ''");
} catch (Exception $e) {}
try {
    $db->query("ALTER TABLE feedback ADD COLUMN type TEXT DEFAULT '建议'");
} catch (Exception $e) {}

$action = isset($_GET['action']) ? $_GET['action'] : '';

if ($action === 'like' && $current_user) {
    $target_id = intval($_GET['id']);
    $target_type = isset($_GET['type']) ? $_GET['type'] : 'feedback';
    header('Content-Type: application/json; charset=utf-8');

    if ($target_type === 'feedback') {
        $result = like_feedback($current_user['id'], $target_id);
        if ($result) {
            $row = $db->fetchOne("SELECT likes FROM feedback WHERE id = ?", array($target_id));
            echo json_encode(array('success' => true, 'likes' => intval($row['likes'])));
        } else {
            echo json_encode(array('success' => false, 'message' => '您已点赞过该反馈'));
        }
    } elseif ($target_type === 'reply') {
        $result = like_reply($current_user['id'], $target_id);
        if ($result) {
            $row = $db->fetchOne("SELECT likes FROM feedback_replies WHERE id = ?", array($target_id));
            echo json_encode(array('success' => true, 'likes' => intval($row['likes'])));
        } else {
            echo json_encode(array('success' => false, 'message' => '您已点赞过该回复'));
        }
    }
    exit;
}

if ($action === 'reply' && $_SERVER['REQUEST_METHOD'] === 'POST' && $current_user) {
    $feedback_id = intval($_POST['feedback_id']);
    $content = trim($_POST['content']);
    $is_admin = is_admin();

    header('Content-Type: application/json; charset=utf-8');

    if (empty($content)) {
        echo json_encode(array('success' => false, 'message' => '回复内容不能为空'));
        exit;
    }
    if (mb_strlen($content) > 500) {
        echo json_encode(array('success' => false, 'message' => '回复内容不能超过500字'));
        exit;
    }

    $db->insert('feedback_replies', array(
        'feedback_id' => $feedback_id,
        'user_id' => $current_user['id'],
        'content' => $content,
        'is_admin' => $is_admin ? 1 : 0,
        'likes' => 0,
        'created_at' => date('Y-m-d H:i:s')
    ));

    $user_row = $db->fetchOne("SELECT username, avatar FROM users WHERE id = ?", array($current_user['id']));
    echo json_encode(array(
        'success' => true,
        'username' => $user_row['username'],
        'avatar' => $user_row['avatar'],
        'is_admin' => $is_admin,
        'content' => $content,
        'created_at' => date('Y-m-d H:i:s')
    ));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$action) {
    $sub_action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($sub_action === 'submit_feedback' && $current_user) {
        $title = trim($_POST['title']);
        $content = trim($_POST['content']);
        $type = isset($_POST['type']) ? $_POST['type'] : '建议';

        $errors = array();
        if (empty($title)) $errors[] = '标题不能为空';
        elseif (mb_strlen($title) > 100) $errors[] = '标题不能超过100字';
        if (empty($content)) $errors[] = '内容不能为空';
        elseif (mb_strlen($content) > 2000) $errors[] = '内容不能超过2000字';

        $allowed_types = array('建议', '问题', '其他');
        if (!in_array($type, $allowed_types)) $type = '建议';

        if (empty($errors)) {
            $db->insert('feedback', array(
                'user_id' => $current_user['id'],
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'status' => 'open',
                'likes' => 0,
                'created_at' => date('Y-m-d H:i:s')
            ));
            $message = '反馈提交成功，感谢您的意见！';
            $message_type = 'success';
        } else {
            $message = implode('；', $errors);
            $message_type = 'error';
        }
    }
}

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'newest';
$filter_status = isset($_GET['status']) ? $_GET['status'] : 'all';
$filter_type = isset($_GET['type']) ? $_GET['type'] : 'all';

$where = array();
$params = array();
if ($filter_status !== 'all') {
    $where[] = 'f.status = ?';
    $params[] = $filter_status;
}
if ($filter_type !== 'all') {
    $where[] = 'f.type = ?';
    $params[] = $filter_type;
}
$where_sql = empty($where) ? '' : 'WHERE ' . implode(' AND ', $where);

if ($sort === 'likes') {
    $order_sql = 'ORDER BY f.likes DESC, f.created_at DESC';
} else {
    $order_sql = 'ORDER BY f.created_at DESC';
}

$feedbacks = $db->fetchAll(
    "SELECT f.*, u.username, u.avatar FROM feedback f LEFT JOIN users u ON f.user_id = u.id $where_sql $order_sql",
    $params
);

$liked_feedback_ids = array();
$liked_reply_ids = array();
if ($current_user) {
    $all_likes = $db->fetchAll(
        "SELECT target_id, target_type FROM feedback_likes WHERE user_id = ?",
        array($current_user['id'])
    );
    foreach ($all_likes as $like) {
        if ($like['target_type'] === 'feedback') $liked_feedback_ids[] = $like['target_id'];
        elseif ($like['target_type'] === 'reply') $liked_reply_ids[] = $like['target_id'];
    }
}

$feedback_counts = array();
foreach ($feedbacks as $fb) {
    $reply_count = $db->fetchOne("SELECT COUNT(*) as cnt FROM feedback_replies WHERE feedback_id = ?", array($fb['id']));
    $feedback_counts[$fb['id']] = intval($reply_count['cnt']);
}
?>

<style>
.feedback-page { padding: calc(var(--header-height) + 32px) 0 60px; }
.feedback-layout { max-width: 860px; margin: 0 auto; }

.feedback-page-header {
    text-align: center; margin-bottom: 32px;
}
.feedback-page-header h1 {
    font-size: clamp(1.8rem, 4vw, 2.4rem);
    background: linear-gradient(135deg, #fff 0%, var(--primary) 100%);
    -webkit-background-clip: text;
    background-clip: text;
    -webkit-text-fill-color: transparent;
    margin-bottom: 8px;
}
.feedback-page-header p { color: var(--text-secondary); font-size: 1rem; }

.feedback-form-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 28px; margin-bottom: 32px;
    transition: border-color var(--transition-fast);
}
.feedback-form-card:focus-within { border-color: var(--primary); }

.feedback-form-title {
    font-size: 1.1rem; font-weight: 700; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.feedback-form-title::before {
    content: ''; width: 4px; height: 24px;
    background: var(--primary); border-radius: 2px;
}

.feedback-type-options { display: flex; gap: 10px; margin-bottom: 16px; flex-wrap: wrap; }
.feedback-type-option {
    padding: 10px 18px; background: var(--background);
    border: 2px solid var(--border); border-radius: var(--radius-md);
    cursor: pointer; transition: all var(--transition-fast);
    display: flex; align-items: center; gap: 8px;
    font-size: 0.9rem;
}
.feedback-type-option:hover { border-color: var(--primary); }
.feedback-type-option.selected {
    border-color: var(--primary);
    background: var(--primary-light);
    color: var(--primary);
}
.feedback-type-option .type-icon { font-size: 1.1rem; }
.feedback-type-option input { display: none; }

.feedback-form textarea { min-height: 120px; resize: vertical; }
.feedback-form .char-count {
    font-size: 0.8rem; color: var(--muted); text-align: right; margin-top: 4px;
}
.feedback-form .submit-row {
    display: flex; justify-content: space-between; align-items: center;
    margin-top: 20px;
}
.feedback-login-hint {
    display: flex; align-items: center; gap: 8px;
    padding: 10px 16px; background: var(--primary-light);
    border: 1px solid var(--border); border-radius: var(--radius-md);
    font-size: 0.85rem; color: var(--text-secondary);
}
.feedback-login-hint a { color: var(--primary); font-weight: 600; }

.feedback-filters {
    display: flex; flex-wrap: wrap; gap: 12px;
    margin-bottom: 24px; padding: 16px 20px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.filter-group { display: flex; align-items: center; gap: 6px; }
.filter-label { font-size: 0.85rem; color: var(--muted); font-weight: 500; }
.filter-chip {
    padding: 6px 14px; background: var(--background);
    border: 1px solid var(--border); border-radius: var(--radius-full);
    font-size: 0.8rem; cursor: pointer; transition: all var(--transition-fast);
}
.filter-chip:hover { border-color: var(--primary); color: var(--primary); }
.filter-chip.active { background: var(--primary); border-color: var(--primary); color: var(--background); }

.feedback-items { display: flex; flex-direction: column; gap: 20px; }

.feedback-item {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
    transition: all var(--transition);
}
.feedback-item:hover { border-color: var(--primary); box-shadow: var(--shadow-md); }

.feedback-item-main { padding: 24px; }

.feedback-user-row { display: flex; align-items: center; gap: 12px; margin-bottom: 14px; }
.feedback-item-avatar { width: 44px; height: 44px; border-radius: 50%; overflow: hidden; flex-shrink: 0; border: 2px solid var(--border); }
.feedback-item-avatar img { width: 100%; height: 100%; object-fit: cover; }
.feedback-user-info { flex: 1; min-width: 0; }
.feedback-item-username { font-weight: 700; font-size: 0.95rem; display: flex; align-items: center; gap: 8px; }
.feedback-item-time { font-size: 0.8rem; color: var(--muted); }

.admin-badge {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px; 
    background: linear-gradient(135deg, #ef4444, #dc2626);
    color: white; border-radius: 10px;
    font-size: 0.65rem; font-weight: 700;
    letter-spacing: 0.5px;
    box-shadow: 0 2px 8px rgba(239, 68, 68, 0.3);
    margin-left: 4px;
    position: relative;
    overflow: hidden;
}
.admin-badge::before {
    content: '⚡';
    font-size: 0.7rem;
    margin-right: 2px;
}
.admin-badge::after {
    content: '';
    position: absolute;
    top: 0; right: -30px;
    width: 20px; height: 100%;
    background: linear-gradient(90deg, transparent, rgba(255,255,255,0.3), transparent);
    animation: shine 3s infinite;
}
@keyframes shine {
    0% { right: -30px; }
    50%, 100% { right: 110%; }
}

.feedback-item-title { font-size: 1.1rem; font-weight: 700; margin-bottom: 10px; line-height: 1.4; }
.feedback-item-content { color: var(--text-secondary); line-height: 1.75; font-size: 0.95rem; white-space: pre-wrap; word-break: break-word; }

.feedback-item-meta {
    display: flex; align-items: center; gap: 12px; margin-top: 16px;
    flex-wrap: wrap;
}
.feedback-meta-chip {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 4px 12px; background: var(--background);
    border: 1px solid var(--border); border-radius: var(--radius-full);
    font-size: 0.8rem;
}
.feedback-type-chip建议 { background: rgba(1, 180, 228, 0.15); color: var(--primary); border-color: transparent; }
.feedback-type-chip问题 { background: rgba(239, 68, 68, 0.15); color: var(--danger); border-color: transparent; }
.feedback-type-chip其他 { background: rgba(122, 140, 158, 0.15); color: var(--muted); border-color: transparent; }

.feedback-status-chip { padding: 4px 12px; border-radius: var(--radius-full); font-size: 0.8rem; font-weight: 600; }
.status-open { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.status-resolved { background: rgba(34, 197, 94, 0.15); color: var(--success); }
.status-closed { background: rgba(122, 140, 158, 0.15); color: var(--muted); }

.feedback-item-actions {
    display: flex; gap: 8px; margin-top: 16px;
    padding-top: 16px; border-top: 1px solid var(--border);
}
.action-btn {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 8px 16px; background: var(--background);
    border: 1px solid var(--border); border-radius: var(--radius-full);
    font-size: 0.85rem; cursor: pointer; transition: all var(--transition-fast);
    color: var(--text-secondary);
}
.action-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-light); }
.action-btn.liked { background: var(--primary); border-color: var(--primary); color: var(--background); }
.action-btn.reply-btn { margin-left: auto; }

.feedback-replies {
    background: var(--background); border-top: 1px solid var(--border);
    padding: 20px 24px;
}
.reply-item {
    display: flex; gap: 12px; padding: 12px 0;
    border-bottom: 1px solid var(--border);
}
.reply-item:last-child { border-bottom: none; }
.reply-item.hidden-reply { display: none; }
.reply-item.visible { display: flex; }

.reply-avatar { width: 32px; height: 32px; border-radius: 50%; overflow: hidden; flex-shrink: 0; }
.reply-avatar img { width: 100%; height: 100%; object-fit: cover; }
.reply-body { flex: 1; min-width: 0; }
.reply-header { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; flex-wrap: wrap; }
.reply-username { font-weight: 600; font-size: 0.85rem; }
.reply-time { font-size: 0.75rem; color: var(--muted); }
.reply-content { color: var(--text-secondary); font-size: 0.9rem; line-height: 1.6; word-break: break-word; }
.reply-actions { display: flex; gap: 12px; margin-top: 6px; }
.reply-like { font-size: 0.8rem; color: var(--muted); cursor: pointer; display: flex; align-items: center; gap: 4px; transition: color var(--transition-fast); }
.reply-like:hover { color: var(--primary); }
.reply-like.liked { color: var(--primary); }

.reply-form {
    display: flex; gap: 10px; margin-top: 12px;
}
.reply-form input, .reply-form textarea {
    flex: 1; padding: 10px 14px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md); color: var(--text);
    font-size: 0.9rem; outline: none; transition: border-color var(--transition-fast);
    resize: none;
}
.reply-form input:focus, .reply-form textarea:focus { border-color: var(--primary); }
.reply-form button {
    padding: 0 18px; background: var(--primary);
    color: var(--background); border-radius: var(--radius-md);
    font-weight: 600; font-size: 0.9rem; cursor: pointer;
    transition: all var(--transition-fast);
}
.reply-form button:hover { background: var(--primary-hover); }

.reply-toggle-btn {
    width: 100%; padding: 12px; margin-top: 10px;
    background: transparent; border: 1px dashed var(--border);
    border-radius: var(--radius-md); color: var(--primary);
    font-size: 0.85rem; cursor: pointer; transition: all var(--transition-fast);
    display: flex; align-items: center; justify-content: center; gap: 6px;
}
.reply-toggle-btn:hover { background: var(--primary-light); border-color: var(--primary); }

.reply-form-inline {
    margin-top: 12px; padding: 12px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-md);
}
.reply-form-inline textarea {
    width: 100%; padding: 10px 14px;
    background: var(--background); border: 1px solid var(--border);
    border-radius: var(--radius-md); color: var(--text);
    font-size: 0.9rem; outline: none; resize: vertical;
    min-height: 80px; transition: border-color var(--transition-fast);
}
.reply-form-inline textarea:focus { border-color: var(--primary); }
.reply-form-inline-actions { display: flex; gap: 8px; margin-top: 10px; justify-content: flex-end; }

.feedback-empty {
    text-align: center; padding: 60px 20px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg);
}
.feedback-empty-icon { font-size: 4rem; color: var(--muted); margin-bottom: 16px; }
.feedback-empty h3 { margin-bottom: 8px; }
.feedback-empty p { color: var(--muted); }

.login-prompt-overlay {
    position: fixed; inset: 0; z-index: 9999;
    background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
    display: none; align-items: center; justify-content: center;
    padding: 20px;
}
.login-prompt-overlay.active { display: flex; animation: fadeIn 0.2s ease; }
.login-prompt-box {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-xl); padding: 32px;
    max-width: 380px; width: 100%; text-align: center;
    box-shadow: var(--shadow-xl);
    animation: slideUp 0.3s ease;
}
.login-prompt-icon { font-size: 3rem; margin-bottom: 16px; }
.login-prompt-box h3 { margin-bottom: 8px; }
.login-prompt-box p { color: var(--text-secondary); margin-bottom: 20px; }
.login-prompt-box .btn-row { display: flex; gap: 12px; }
.login-prompt-box .btn-row a { flex: 1; }
.login-prompt-box .btn-row .btn { width: 100%; }

@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
@keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }

.alert-toast {
    position: fixed; top: 80px; right: 24px; z-index: 10000;
    padding: 14px 20px; border-radius: var(--radius-md);
    display: flex; align-items: center; gap: 10px;
    animation: slideInRight 0.3s ease;
    box-shadow: var(--shadow-lg);
}
.alert-toast.success { background: var(--success); color: white; }
.alert-toast.error { background: var(--danger); color: white; }
.alert-toast.info { background: var(--primary); color: var(--background); }

@keyframes slideInRight { from { transform: translateX(120%); opacity: 0; } to { transform: translateX(0); opacity: 1; } }

@media (max-width: 768px) {
    .feedback-page { padding: calc(var(--header-height) + 16px) 0 40px; }
    .feedback-form-card { padding: 20px; }
    .feedback-filters { padding: 12px; gap: 8px; }
    .filter-group { flex-wrap: wrap; }
    .feedback-item-main { padding: 18px; }
    .feedback-item-actions { flex-wrap: wrap; }
    .action-btn { padding: 6px 12px; font-size: 0.8rem; }
    .feedback-replies { padding: 16px; }
    .reply-form { flex-direction: column; }
    .feedback-type-options { gap: 8px; }
    .feedback-type-option { padding: 8px 14px; font-size: 0.85rem; }
}
</style>

<div class="container feedback-page">
    <div class="feedback-layout">
        <div class="feedback-page-header">
            <h1>反馈中心</h1>
            <p>您的声音对我们很重要，告诉我们您的想法</p>
        </div>

        <?php if (isset($message)): ?>
        <div class="alert alert-<?php echo $message_type; ?>" style="padding:14px 20px;border-radius:var(--radius-md);margin-bottom:24px;display:flex;align-items:center;gap:10px;background:<?php echo $message_type === 'success' ? 'rgba(34,197,94,0.15)' : 'rgba(239,68,68,0.15)'; ?>;border:1px solid <?php echo $message_type === 'success' ? 'var(--success)' : 'var(--danger)'; ?>;color:<?php echo $message_type === 'success' ? 'var(--success)' : 'var(--danger)'; ?>;">
            <span><?php echo $message_type === 'success' ? '✓' : '⚠'; ?></span>
            <span><?php echo clean($message); ?></span>
        </div>
        <?php endif; ?>

        <div class="feedback-form-card">
            <div class="feedback-form-title">提交反馈</div>
            <form method="POST" class="feedback-form" id="feedbackForm">
                <input type="hidden" name="action" value="submit_feedback">

                <?php if (!$current_user): ?>
                <div class="feedback-login-hint" style="margin-bottom:16px;">
                    <span>🔒</span>
                    <span>您需要<a href="login.php">登录</a>后才能提交反馈</span>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">反馈类型</label>
                    <div class="feedback-type-options">
                        <label class="feedback-type-option selected" data-type="建议">
                            <span class="type-icon">💡</span>
                            <span>建议</span>
                            <input type="radio" name="type" value="建议" checked>
                        </label>
                        <label class="feedback-type-option" data-type="问题">
                            <span class="type-icon">🐛</span>
                            <span>问题</span>
                            <input type="radio" name="type" value="问题">
                        </label>
                        <label class="feedback-type-option" data-type="其他">
                            <span class="type-icon">💬</span>
                            <span>其他</span>
                            <input type="radio" name="type" value="其他">
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">标题<span class="required" style="color:var(--danger);">*</span></label>
                    <input type="text" name="title" class="form-control" placeholder="简要描述您的反馈主题" required maxlength="100">
                </div>

                <div class="form-group">
                    <label class="form-label">详细内容<span class="required" style="color:var(--danger);">*</span></label>
                    <textarea name="content" class="form-control" placeholder="请详细描述您的建议、问题或意见... (最多2000字)" required maxlength="2000" id="feedbackContent"></textarea>
                    <div class="char-count"><span id="charCount">0</span> / 2000</div>
                </div>

                <div class="submit-row">
                    <span class="form-hint">我们会尽快回复您</span>
                    <button type="submit" class="btn btn-primary" <?php echo !$current_user ? 'disabled' : ''; ?>>
                        <span>📨</span> 提交反馈
                    </button>
                </div>
            </form>
        </div>

        <div class="feedback-filters">
            <div class="filter-group">
                <span class="filter-label">排序:</span>
                <a href="?sort=newest&status=<?php echo $filter_status; ?>&type=<?php echo $filter_type; ?>" class="filter-chip <?php echo $sort === 'newest' ? 'active' : ''; ?>">最新</a>
                <a href="?sort=likes&status=<?php echo $filter_status; ?>&type=<?php echo $filter_type; ?>" class="filter-chip <?php echo $sort === 'likes' ? 'active' : ''; ?>">最热</a>
            </div>
            <div class="filter-group">
                <span class="filter-label">状态:</span>
                <a href="?sort=<?php echo $sort; ?>&status=all&type=<?php echo $filter_type; ?>" class="filter-chip <?php echo $filter_status === 'all' ? 'active' : ''; ?>">全部</a>
                <a href="?sort=<?php echo $sort; ?>&status=open&type=<?php echo $filter_type; ?>" class="filter-chip <?php echo $filter_status === 'open' ? 'active' : ''; ?>">待处理</a>
                <a href="?sort=<?php echo $sort; ?>&status=resolved&type=<?php echo $filter_type; ?>" class="filter-chip <?php echo $filter_status === 'resolved' ? 'active' : ''; ?>">已解决</a>
                <a href="?sort=<?php echo $sort; ?>&status=closed&type=<?php echo $filter_type; ?>" class="filter-chip <?php echo $filter_status === 'closed' ? 'active' : ''; ?>">已关闭</a>
            </div>
            <div class="filter-group">
                <span class="filter-label">类型:</span>
                <a href="?sort=<?php echo $sort; ?>&status=<?php echo $filter_status; ?>&type=all" class="filter-chip <?php echo $filter_type === 'all' ? 'active' : ''; ?>">全部</a>
                <a href="?sort=<?php echo $sort; ?>&status=<?php echo $filter_status; ?>&type=建议" class="filter-chip <?php echo $filter_type === '建议' ? 'active' : ''; ?>">建议</a>
                <a href="?sort=<?php echo $sort; ?>&status=<?php echo $filter_status; ?>&type=问题" class="filter-chip <?php echo $filter_type === '问题' ? 'active' : ''; ?>">问题</a>
                <a href="?sort=<?php echo $sort; ?>&status=<?php echo $filter_status; ?>&type=其他" class="filter-chip <?php echo $filter_type === '其他' ? 'active' : ''; ?>">其他</a>
            </div>
        </div>

        <?php if (empty($feedbacks)): ?>
        <div class="feedback-empty">
            <div class="feedback-empty-icon">💭</div>
            <h3>暂无反馈</h3>
            <p>成为第一个提交反馈的人吧！</p>
        </div>
        <?php else: ?>
        <div class="feedback-items" id="feedbackItems">
            <?php foreach ($feedbacks as $fb): ?>
            <?php
            $reply_count = isset($feedback_counts[$fb['id']]) ? $feedback_counts[$fb['id']] : 0;
            $replies = get_feedback_replies($fb['id']);
            $is_liked = in_array($fb['id'], $liked_feedback_ids);
            $status_map = array('open' => '待处理', 'resolved' => '已解决', 'closed' => '已关闭');
            $type_class = 'feedback-type-chip' . $fb['type'];
            ?>
            <div class="feedback-item" id="fb-<?php echo intval($fb['id']); ?>" data-feedback-id="<?php echo intval($fb['id']); ?>">
                <div class="feedback-item-main">
                    <div class="feedback-user-row">
                        <div class="feedback-item-avatar">
                            <?php if ($fb['avatar']): ?>
                            <img src="<?php echo clean($fb['avatar']); ?>" alt="<?php echo clean($fb['username']); ?>">
                            <?php else: ?>
                            <div style="width:100%;height:100%;background:var(--primary-light);display:flex;align-items:center;justify-content:center;color:var(--primary);font-weight:700;font-size:1.2rem;"><?php echo clean(mb_substr($fb['username'], 0, 1)); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="feedback-user-info">
                            <div class="feedback-item-username">
                                <?php echo clean($fb['username']); ?>
                                <?php if ($fb['user_id'] && is_admin()): ?>
                                <span class="admin-badge" style="background:linear-gradient(135deg, #ef4444, #dc2626);">开发者</span>
                                <?php endif; ?>
                            </div>
                            <div class="feedback-item-time"><?php echo format_date($fb['created_at'], 'Y-m-d H:i'); ?></div>
                        </div>
                    </div>

                    <?php if (!empty($fb['title'])): ?>
                    <div class="feedback-item-title"><?php echo clean($fb['title']); ?></div>
                    <?php endif; ?>

                    <div class="feedback-item-content"><?php echo clean($fb['content']); ?></div>

                    <div class="feedback-item-meta">
                        <span class="feedback-meta-chip <?php echo $type_class; ?>"><?php echo clean($fb['type']); ?></span>
                        <span class="feedback-status-chip status-<?php echo clean($fb['status']); ?>"><?php echo isset($status_map[$fb['status']]) ? $status_map[$fb['status']] : $fb['status']; ?></span>
                    </div>

                    <div class="feedback-item-actions">
                        <button class="action-btn like-btn <?php echo $is_liked ? 'liked' : ''; ?>" data-like-id="<?php echo intval($fb['id']); ?>" data-like-type="feedback">
                            <span>👍</span>
                            <span data-like-count><?php echo intval($fb['likes']); ?></span>
                        </button>
                        <button class="action-btn reply-btn" data-reply-toggle-id="<?php echo intval($fb['id']); ?>">
                            <span>💬</span> 回复 (<?php echo $reply_count; ?>)
                        </button>
                    </div>
                </div>

                <?php if (!empty($replies)): ?>
                <div class="feedback-replies">
                    <?php
                    $admin_replies = array();
                    $user_replies = array();
                    foreach ($replies as $r) {
                        if ($r['is_admin']) $admin_replies[] = $r;
                        else $user_replies[] = $r;
                    }
                    $sorted_replies = array_merge($admin_replies, $user_replies);
                    $total_replies = count($sorted_replies);
                    $show_limit = 3;
                    $hidden_count = max(0, $total_replies - $show_limit);
                    $show_toggle = $hidden_count > 0;
                    $toggle_class = $show_toggle ? ' style="display:none;"' : '';
                    ?>

                    <?php foreach ($sorted_replies as $idx => $reply): ?>
                    <?php
                    $is_admin_reply = $reply['is_admin'] == 1;
                    $reply_liked = in_array($reply['id'], $liked_reply_ids);
                    $hidden_class = '';
                    if ($show_toggle && $idx >= $show_limit) $hidden_class = ' hidden-reply';
                    ?>
                    <div class="reply-item<?php echo $hidden_class; ?>" data-reply-id="<?php echo intval($reply['id']); ?>">
                        <div class="reply-avatar">
                            <?php if ($reply['avatar']): ?>
                            <img src="<?php echo clean($reply['avatar']); ?>" alt="<?php echo clean($reply['username']); ?>">
                            <?php else: ?>
                            <div style="width:100%;height:100%;background:var(--surface);display:flex;align-items:center;justify-content:center;color:var(--muted);font-size:0.8rem;"><?php echo clean(mb_substr($reply['username'], 0, 1)); ?></div>
                            <?php endif; ?>
                        </div>
                        <div class="reply-body">
                            <div class="reply-header">
                                <span class="reply-username"><?php echo clean($reply['username']); ?></span>
                                <?php if ($is_admin_reply): ?>
                                <span class="admin-badge">开发者</span>
                                <?php endif; ?>
                                <span class="reply-time"><?php echo format_date($reply['created_at'], 'Y-m-d H:i'); ?></span>
                            </div>
                            <div class="reply-content"><?php echo clean($reply['content']); ?></div>
                            <div class="reply-actions">
                                <span class="reply-like <?php echo $reply_liked ? 'liked' : ''; ?>" data-like-id="<?php echo intval($reply['id']); ?>" data-like-type="reply">
                                    👍 <span data-like-count><?php echo intval($reply['likes']); ?></span>
                                </span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <?php if ($show_toggle): ?>
                    <button class="reply-toggle-btn" data-fb-id="<?php echo intval($fb['id']); ?>">
                        <span>展开更多回复 (<?php echo $hidden_count; ?>)</span>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
</div>

<div class="login-prompt-overlay" id="loginPrompt">
    <div class="login-prompt-box">
        <div class="login-prompt-icon">🔒</div>
        <h3>需要登录</h3>
        <p>请先登录后再进行此操作</p>
        <div class="btn-row">
            <a href="login.php" class="btn btn-primary">立即登录</a>
            <a href="register.php" class="btn btn-outline">注册账号</a>
        </div>
        <p style="margin-top:12px;">
            <button class="btn btn-text" id="loginPromptClose" style="color:var(--muted);background:none;border:none;cursor:pointer;font-size:0.85rem;">取消</button>
        </p>
    </div>
</div>

<script>
(function() {
    var currentUserId = <?php echo $current_user ? intval($current_user['id']) : 'null'; ?>;

    function showToast(message, type) {
        var toast = document.createElement('div');
        toast.className = 'alert-toast ' + (type || 'info');
        toast.innerHTML = '<span>' + (type === 'success' ? '✓' : type === 'error' ? '⚠' : 'ℹ') + '</span><span>' + message + '</span>';
        document.body.appendChild(toast);
        setTimeout(function() {
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(120%)';
            setTimeout(function() { toast.remove(); }, 300);
        }, 2500);
    }

    function requireLogin(callback) {
        if (!currentUserId) {
            var overlay = document.getElementById('loginPrompt');
            if (overlay) overlay.classList.add('active');
            if (callback) {
                var closeBtn = document.getElementById('loginPromptClose');
                var handler = function() {
                    overlay.classList.remove('active');
                    closeBtn.removeEventListener('click', handler);
                };
                closeBtn.addEventListener('click', handler);
            }
            return false;
        }
        if (callback) callback();
        return true;
    }

    var loginPrompt = document.getElementById('loginPrompt');
    if (loginPrompt) {
        loginPrompt.addEventListener('click', function(e) {
            if (e.target === loginPrompt) loginPrompt.classList.remove('active');
        });
    }

    var typeOptions = document.querySelectorAll('.feedback-type-option');
    typeOptions.forEach(function(opt) {
        opt.addEventListener('click', function() {
            typeOptions.forEach(function(o) { o.classList.remove('selected'); });
            opt.classList.add('selected');
            var radio = opt.querySelector('input[type="radio"]');
            if (radio) radio.checked = true;
        });
    });

    var feedbackContent = document.getElementById('feedbackContent');
    var charCount = document.getElementById('charCount');
    if (feedbackContent && charCount) {
        feedbackContent.addEventListener('input', function() {
            charCount.textContent = feedbackContent.value.length;
        });
    }

    document.getElementById('feedbackForm').addEventListener('submit', function(e) {
        if (!currentUserId) {
            e.preventDefault();
            requireLogin();
            return;
        }
    });

    document.addEventListener('click', function(e) {
        var likeBtn = e.target.closest('.like-btn');
        if (likeBtn) {
            e.preventDefault();
            if (!requireLogin()) return;
            var id = likeBtn.dataset.likeId;
            var type = likeBtn.dataset.likeType || 'feedback';
            fetch('?action=like&id=' + id + '&type=' + type, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(res) { return res.json(); }).then(function(data) {
                if (data.success) {
                    likeBtn.classList.add('liked');
                    var countEl = likeBtn.querySelector('[data-like-count]');
                    if (countEl) countEl.textContent = data.likes;
                    showToast('点赞成功', 'success');
                } else {
                    showToast(data.message || '操作失败', 'error');
                }
            }).catch(function() { showToast('操作失败', 'error'); });
            return;
        }

        var replyLike = e.target.closest('.reply-like');
        if (replyLike) {
            e.preventDefault();
            if (!requireLogin()) return;
            var id = replyLike.dataset.likeId;
            fetch('?action=like&id=' + id + '&type=reply', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            }).then(function(res) { return res.json(); }).then(function(data) {
                if (data.success) {
                    replyLike.classList.add('liked');
                    var countEl = replyLike.querySelector('[data-like-count]');
                    if (countEl) countEl.textContent = data.likes;
                    showToast('点赞成功', 'success');
                } else {
                    showToast(data.message || '操作失败', 'error');
                }
            }).catch(function() { showToast('操作失败', 'error'); });
            return;
        }

        var replyBtn = e.target.closest('.reply-btn');
        if (replyBtn) {
            e.preventDefault();
            if (!requireLogin()) return;
            var fbId = replyBtn.dataset.replyToggleId;
            var item = document.getElementById('fb-' + fbId);
            if (!item) return;
            var existingForm = item.querySelector('.reply-form-inline');
            if (existingForm) {
                existingForm.remove();
                return;
            }
            var formDiv = document.createElement('div');
            formDiv.className = 'reply-form-inline';
            formDiv.innerHTML = '<textarea placeholder="写下您的回复..." required maxlength="500"></textarea>' +
                '<div class="reply-form-inline-actions">' +
                '<button type="button" class="btn btn-text reply-cancel-btn" style="color:var(--muted);background:none;">取消</button>' +
                '<button type="button" class="btn btn-primary reply-send-btn">发送回复</button>' +
                '</div>';
            var repliesSection = item.querySelector('.feedback-replies');
            if (repliesSection) {
                repliesSection.appendChild(formDiv);
            } else {
                var newReplies = document.createElement('div');
                newReplies.className = 'feedback-replies';
                item.appendChild(newReplies);
                newReplies.appendChild(formDiv);
            }
            formDiv.querySelector('.reply-cancel-btn').addEventListener('click', function() { formDiv.remove(); });
            var sendBtn = formDiv.querySelector('.reply-send-btn');
            var textarea = formDiv.querySelector('textarea');
            sendBtn.addEventListener('click', function() {
                var content = textarea.value.trim();
                if (!content) { showToast('回复内容不能为空', 'error'); return; }
                var formData = new FormData();
                formData.append('action', 'reply');
                formData.append('feedback_id', fbId);
                formData.append('content', content);
                fetch('?action=reply', {
                    method: 'POST',
                    body: formData,
                    headers: { 'X-Requested-With': 'XMLHttpRequest' }
                }).then(function(res) { return res.json(); }).then(function(data) {
                    if (data.success) {
                        showToast('回复成功', 'success');
                        formDiv.remove();
                        location.reload();
                    } else {
                        showToast(data.message || '回复失败', 'error');
                    }
                }).catch(function() { showToast('回复失败', 'error'); });
            });
            textarea.focus();
            return;
        }

        var toggleBtn = e.target.closest('.reply-toggle-btn');
        if (toggleBtn) {
            e.preventDefault();
            var fbId = toggleBtn.dataset.fbId;
            var item = document.getElementById('fb-' + fbId);
            if (!item) return;
            var hiddenReplies = item.querySelectorAll('.reply-item.hidden-reply');
            var expanded = toggleBtn.classList.contains('expanded');
            if (expanded) {
                hiddenReplies.forEach(function(r) { r.style.display = 'none'; });
                toggleBtn.innerHTML = '<span>展开更多回复 (' + hiddenReplies.length + ')</span>';
                toggleBtn.classList.remove('expanded');
            } else {
                hiddenReplies.forEach(function(r) { r.style.display = 'flex'; });
                toggleBtn.innerHTML = '<span>收起回复</span>';
                toggleBtn.classList.add('expanded');
            }
            return;
        }
    });

    document.querySelectorAll('.reply-item.hidden-reply').forEach(function(item) {
        item.style.display = 'none';
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>