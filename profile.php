<?php
$page_title = '个人中心';
require_once __DIR__ . '/includes/header.php';
require_login();

$user = current_user();
$db = get_db();
$message = '';
$message_type = 'success';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = isset($_POST['action']) ? $_POST['action'] : '';

    if ($action === 'update_username') {
        $new_username = trim($_POST['username']);
        if (empty($new_username)) {
            $message = '用户名不能为空';
            $message_type = 'error';
        } elseif (strlen($new_username) < 3 || strlen($new_username) > 20) {
            $message = '用户名长度需在3-20位之间';
            $message_type = 'error';
        } else {
            $existing = $db->fetchOne("SELECT id FROM users WHERE username = ? AND id != ?", array($new_username, $user['id']));
            if ($existing) {
                $message = '该用户名已被使用';
                $message_type = 'error';
            } else {
                $db->update('users', array('username' => $new_username), 'id = ?', array($user['id']));
                $_SESSION['username'] = $new_username;
                $user = current_user();
                $message = '用户名修改成功';
            }
        }
    }

    elseif ($action === 'update_password') {
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        if (!verify_password($current_password, $user['password'])) {
            $message = '当前密码不正确';
            $message_type = 'error';
        } elseif (strlen($new_password) < 6) {
            $message = '新密码至少6位';
            $message_type = 'error';
        } elseif ($new_password !== $confirm_password) {
            $message = '两次输入的密码不一致';
            $message_type = 'error';
        } else {
            $db->update('users', array('password' => hash_password($new_password)), 'id = ?', array($user['id']));
            $message = '密码修改成功';
        }
    }

    elseif ($action === 'update_avatar') {
        $avatar_type = isset($_POST['avatar_type']) ? $_POST['avatar_type'] : 'preset';

        if ($avatar_type === 'preset') {
            $color = isset($_POST['avatar_color']) ? $_POST['avatar_color'] : '01b4e4';
            $letter = strtoupper(mb_substr($user['username'], 0, 1));
            $avatar_data = 'data:image/svg+xml;base64,' . base64_encode(
                '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><circle cx="100" cy="100" r="100" fill="#' . clean($color) . '"/><text x="100" y="130" font-family="Arial, PingFang SC, Microsoft YaHei" font-size="90" font-weight="700" fill="white" text-anchor="middle">' . clean($letter) . '</text></svg>'
            );
            $db->update('users', array('avatar' => $avatar_data), 'id = ?', array($user['id']));
            $user = current_user();
            $message = '头像更新成功';
        } elseif ($avatar_type === 'upload') {
            if (isset($_FILES['avatar_upload']) && $_FILES['avatar_upload']['error'] === UPLOAD_ERR_OK) {
                $file_info = getimagesize($_FILES['avatar_upload']['tmp_name']);
                if ($file_info === false) {
                    $message = '上传的文件不是有效图片';
                    $message_type = 'error';
                } else {
                    $allowed_types = array('image/jpeg', 'image/png', 'image/gif', 'image/webp');
                    if (!in_array($file_info['mime'], $allowed_types)) {
                        $message = '只支持 JPG、PNG、GIF、WEBP 格式';
                        $message_type = 'error';
                    } elseif ($_FILES['avatar_upload']['size'] > 5 * 1024 * 1024) {
                        $message = '图片大小不能超过5MB';
                        $message_type = 'error';
                    } else {
                        $avatar_data = file_get_contents($_FILES['avatar_upload']['tmp_name']);
                        $avatar_base64 = 'data:' . $file_info['mime'] . ';base64,' . base64_encode($avatar_data);
                        $db->update('users', array('avatar' => $avatar_base64), 'id = ?', array($user['id']));
                        $user = current_user();
                        $message = '头像上传成功';
                    }
                }
            } else {
                $message = '请选择要上传的图片';
                $message_type = 'error';
            }
        }
    }

    elseif ($action === 'remove_favorite') {
        $media_id = isset($_POST['media_id']) ? $_POST['media_id'] : '';
        if ($media_id) {
            remove_favorite($user['id'], $media_id);
            $message = '已取消收藏';
        }
    }

    elseif ($action === 'remove_history') {
        $history_id = isset($_POST['history_id']) ? intval($_POST['history_id']) : 0;
        if ($history_id) {
            $db->delete('watch_history', 'id = ? AND user_id = ?', array($history_id, $user['id']));
            $message = '已删除观看记录';
        }
    }

    elseif ($action === 'clear_history') {
        clear_watch_history($user['id']);
        $message = '已清空观看历史';
    }
}

$tab = isset($_GET['tab']) ? $_GET['tab'] : 'favorites';
$favorites = get_user_favorites($user['id']);
$history = get_user_history($user['id']);
$my_feedback = $db->fetchAll(
    "SELECT * FROM feedback WHERE user_id = ? ORDER BY created_at DESC",
    array($user['id'])
);

$favorite_count = count($favorites);
$history_count = count($history);
$feedback_count = count($my_feedback);

$preset_colors = array('01b4e4', '1f80e0', 'e401b4', 'b4e401', 'e4b401', 'e40101', '01e4b4', '801fe4', 'ff6b6b', '2ecc71');
?>

<style>
.profile-page { padding: calc(var(--header-height) + 32px) 0 60px; }

.profile-layout { display: grid; grid-template-columns: 320px 1fr; gap: 32px; }

.profile-sidebar {
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 28px;
    height: fit-content;
    position: sticky;
    top: calc(var(--header-height) + 24px);
}

.profile-avatar-section { text-align: center; margin-bottom: 24px; }
.profile-avatar-display {
    width: 120px; height: 120px; margin: 0 auto 16px;
    border-radius: 50%; overflow: hidden;
    border: 3px solid var(--primary);
    box-shadow: 0 0 24px rgba(1, 180, 228, 0.25);
    transition: transform var(--transition);
}
.profile-avatar-display:hover { transform: scale(1.05); }
.profile-avatar-display img { width: 100%; height: 100%; object-fit: cover; }

.profile-name { font-size: 1.3rem; font-weight: 700; margin-bottom: 4px; }
.profile-email { color: var(--muted); font-size: 0.85rem; margin-bottom: 16px; word-break: break-all; }

.profile-join-date {
    display: inline-flex; align-items: center; gap: 6px;
    padding: 6px 14px; background: var(--primary-light);
    color: var(--primary); border-radius: var(--radius-full);
    font-size: 0.8rem; font-weight: 500;
}

.profile-stat-row { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; margin: 24px 0; padding: 20px 0; border-top: 1px solid var(--border); border-bottom: 1px solid var(--border); }
.profile-stat-block { text-align: center; cursor: pointer; padding: 8px 4px; border-radius: var(--radius-md); transition: background var(--transition-fast); }
.profile-stat-block:hover { background: var(--primary-light); }
.profile-stat-num { font-size: 1.5rem; font-weight: 800; color: var(--primary); }
.profile-stat-label { font-size: 0.75rem; color: var(--muted); }

.avatar-customize-btn {
    width: 100%; padding: 12px; background: var(--surface-hover);
    border: 1px solid var(--border); border-radius: var(--radius-md);
    color: var(--text); font-weight: 500; cursor: pointer;
    transition: all var(--transition-fast); display: flex; align-items: center; justify-content: center; gap: 8px;
}
.avatar-customize-btn:hover { border-color: var(--primary); color: var(--primary); }

.avatar-customize-panel {
    margin-top: 16px; padding: 16px;
    background: var(--background); border: 1px solid var(--border);
    border-radius: var(--radius-md); display: none;
}
.avatar-customize-panel.active { display: block; }

.avatar-panel-title { font-size: 0.85rem; font-weight: 600; color: var(--text-secondary); margin-bottom: 12px; }

.color-presets { display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px; margin-bottom: 16px; }
.color-preset {
    width: 100%; aspect-ratio: 1; border-radius: 50%;
    cursor: pointer; border: 3px solid transparent;
    transition: all var(--transition-fast);
    position: relative;
}
.color-preset:hover { transform: scale(1.1); }
.color-preset.selected { border-color: var(--text); box-shadow: 0 0 0 2px var(--primary); }
.color-preset.selected::after {
    content: '✓'; position: absolute; inset: 0;
    display: flex; align-items: center; justify-content: center;
    color: white; font-size: 0.8rem; font-weight: 700;
    text-shadow: 0 1px 3px rgba(0,0,0,0.5);
}

.avatar-upload-area {
    border: 2px dashed var(--border); border-radius: var(--radius-md);
    padding: 20px; text-align: center; cursor: pointer;
    transition: all var(--transition-fast);
}
.avatar-upload-area:hover { border-color: var(--primary); background: var(--primary-light); }
.avatar-upload-area.has-file { border-color: var(--success); background: rgba(34, 197, 94, 0.1); }
.avatar-upload-icon { font-size: 2rem; margin-bottom: 8px; }
.avatar-upload-text { font-size: 0.85rem; color: var(--muted); }
.avatar-upload-input { display: none; }

.profile-main { min-width: 0; }

.alert {
    padding: 14px 20px; border-radius: var(--radius-md);
    margin-bottom: 24px; display: flex; align-items: center; gap: 12px;
    animation: slideIn 0.3s ease-out;
}
.alert-success { background: rgba(34, 197, 94, 0.15); border: 1px solid var(--success); color: var(--success); }
.alert-error { background: rgba(239, 68, 68, 0.15); border: 1px solid var(--danger); color: var(--danger); }
.alert-icon { font-size: 1.2rem; }
.alert-close { margin-left: auto; cursor: pointer; opacity: 0.7; transition: opacity var(--transition-fast); }
.alert-close:hover { opacity: 1; }

@keyframes slideIn { from { opacity: 0; transform: translateY(-10px); } to { opacity: 1; transform: translateY(0); } }

.tabs-nav {
    display: flex; gap: 4px;
    background: var(--surface);
    border: 1px solid var(--border);
    border-radius: var(--radius-lg);
    padding: 6px;
    margin-bottom: 24px;
}
.tab-btn {
    flex: 1; padding: 12px 20px;
    background: transparent; border-radius: var(--radius-md);
    color: var(--text-secondary); font-weight: 500;
    cursor: pointer; transition: all var(--transition-fast);
    display: flex; align-items: center; justify-content: center; gap: 8px;
    white-space: nowrap; font-size: 0.9rem;
}
.tab-btn:hover { color: var(--text); background: var(--surface-hover); }
.tab-btn.active { background: var(--primary); color: var(--background); }

.tab-content { display: none; animation: fadeIn 0.3s ease-out; }
.tab-content.active { display: block; }
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }

.favorites-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); gap: 20px; }
.fav-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); overflow: hidden;
    transition: all var(--transition); position: relative;
}
.fav-card:hover { transform: translateY(-4px); border-color: var(--primary); box-shadow: var(--shadow-lg); }
.fav-poster { aspect-ratio: 2 / 3; overflow: hidden; position: relative; background: var(--card); }
.fav-poster img { width: 100%; height: 100%; object-fit: cover; transition: transform var(--transition-slow); }
.fav-card:hover .fav-poster img { transform: scale(1.08); }
.fav-remove {
    position: absolute; top: 10px; right: 10px;
    width: 32px; height: 32px; border-radius: 50%;
    background: rgba(0,0,0,0.7); backdrop-filter: blur(4px);
    display: flex; align-items: center; justify-content: center;
    color: white; cursor: pointer; font-size: 0.9rem;
    opacity: 0; transition: all var(--transition-fast);
}
.fav-card:hover .fav-remove { opacity: 1; }
.fav-remove:hover { background: var(--danger); transform: scale(1.1); }
.fav-info { padding: 12px 14px; }
.fav-title { font-size: 0.95rem; font-weight: 600; margin-bottom: 4px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.fav-meta { font-size: 0.8rem; color: var(--muted); display: flex; align-items: center; gap: 8px; }

.history-list { display: flex; flex-direction: column; gap: 12px; }
.history-card {
    display: flex; gap: 16px; padding: 16px;
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); transition: all var(--transition-fast);
}
.history-card:hover { border-color: var(--primary); }
.history-thumb { width: 80px; height: 120px; border-radius: var(--radius-md); overflow: hidden; flex-shrink: 0; background: var(--card); }
.history-thumb img { width: 100%; height: 100%; object-fit: cover; }
.history-info { flex: 1; display: flex; flex-direction: column; justify-content: space-between; min-width: 0; }
.history-title { font-size: 1rem; font-weight: 600; margin-bottom: 6px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.history-meta { font-size: 0.8rem; color: var(--muted); margin-bottom: 8px; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.progress-wrapper { margin-top: auto; }
.progress-bar { width: 100%; height: 4px; background: var(--card); border-radius: 2px; overflow: hidden; }
.progress-fill { height: 100%; background: linear-gradient(90deg, var(--primary), var(--accent)); border-radius: 2px; transition: width var(--transition-slow); }
.progress-text { font-size: 0.75rem; color: var(--muted); margin-top: 4px; }
.history-actions { display: flex; flex-direction: column; gap: 8px; align-items: flex-end; }
.history-delete-btn {
    width: 32px; height: 32px; border-radius: 50%;
    display: flex; align-items: center; justify-content: center;
    color: var(--muted); transition: all var(--transition-fast); cursor: pointer;
}
.history-delete-btn:hover { background: var(--danger); color: white; }

.clear-history-bar { display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px; }
.clear-history-bar h3 { margin: 0; }
.clear-history-btn { font-size: 0.85rem; color: var(--danger); cursor: pointer; display: flex; align-items: center; gap: 6px; transition: color var(--transition-fast); }
.clear-history-btn:hover { color: #ff6b6b; }

.feedback-list { display: flex; flex-direction: column; gap: 16px; }
.feedback-card {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 20px;
    transition: all var(--transition-fast);
}
.feedback-card:hover { border-color: var(--primary); }
.feedback-header-row { display: flex; align-items: center; gap: 10px; margin-bottom: 12px; }
.feedback-user-avatar { width: 36px; height: 36px; border-radius: 50%; overflow: hidden; flex-shrink: 0; }
.feedback-user-avatar img { width: 100%; height: 100%; object-fit: cover; }
.feedback-username { font-weight: 600; font-size: 0.9rem; }
.feedback-time { font-size: 0.8rem; color: var(--muted); }
.feedback-content-text { color: var(--text-secondary); line-height: 1.7; font-size: 0.95rem; white-space: pre-wrap; }
.feedback-footer-row { display: flex; align-items: center; gap: 16px; margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--border); }
.feedback-type-badge { padding: 3px 10px; background: var(--primary-light); color: var(--primary); border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600; }
.feedback-status { padding: 3px 10px; border-radius: var(--radius-full); font-size: 0.75rem; font-weight: 600; }
.feedback-status.status-open { background: rgba(245, 158, 11, 0.15); color: var(--warning); }
.feedback-status.status-resolved { background: rgba(34, 197, 94, 0.15); color: var(--success); }
.feedback-status.status-closed { background: rgba(122, 140, 158, 0.15); color: var(--muted); }

.settings-section {
    background: var(--surface); border: 1px solid var(--border);
    border-radius: var(--radius-lg); padding: 28px;
}
.settings-section-title {
    font-size: 1.1rem; font-weight: 700; margin-bottom: 20px;
    display: flex; align-items: center; gap: 10px;
}
.settings-section-title::before {
    content: ''; width: 4px; height: 24px;
    background: var(--primary); border-radius: 2px;
}
.settings-form { display: flex; flex-direction: column; gap: 20px; }
.settings-form .form-group { margin-bottom: 0; }
.settings-form-actions { display: flex; gap: 12px; justify-content: flex-end; }

.empty-state-box { text-align: center; padding: 60px 20px; }
.empty-icon { font-size: 4rem; color: var(--muted); margin-bottom: 16px; opacity: 0.5; }
.empty-state-box h3 { margin-bottom: 8px; }
.empty-state-box p { color: var(--muted); }

@media (max-width: 1024px) {
    .profile-layout { grid-template-columns: 1fr; }
    .profile-sidebar { position: static; }
    .favorites-grid { grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
}

@media (max-width: 768px) {
    .profile-page { padding: calc(var(--header-height) + 16px) 0 40px; }
    .profile-sidebar { padding: 20px; }
    .profile-avatar-display { width: 90px; height: 90px; }
    .profile-stat-row { grid-template-columns: repeat(3, 1fr); }
    .tab-btn { padding: 10px 12px; font-size: 0.8rem; }
    .tab-btn span:last-child { display: none; }
    .favorites-grid { grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .fav-info { padding: 10px; }
    .history-card { padding: 12px; gap: 12px; }
    .history-thumb { width: 60px; height: 90px; }
    .settings-section { padding: 20px; }
    .settings-form-actions { flex-direction: column; }
    .settings-form-actions .btn { width: 100%; }
}
</style>

<div class="container profile-page">
    <?php if ($message): ?>
    <div class="alert alert-<?php echo $message_type; ?>" id="profileAlert">
        <span class="alert-icon"><?php echo $message_type === 'success' ? '✓' : '⚠'; ?></span>
        <span><?php echo clean($message); ?></span>
        <span class="alert-close" onclick="document.getElementById('profileAlert').style.display='none'">✕</span>
    </div>
    <?php endif; ?>

    <div class="profile-layout">
        <aside class="profile-sidebar">
            <div class="profile-avatar-section">
                <div class="profile-avatar-display">
                    <img src="<?php echo clean($user['avatar']); ?>" alt="avatar" id="avatarPreview">
                </div>
                <div class="profile-name"><?php echo clean($user['username']); ?></div>
                <div class="profile-email"><?php echo clean($user['email']); ?></div>
                <div class="profile-join-date">
                    <span>📅</span> 加入于 <?php echo format_date($user['created_at'], 'Y年m月d日'); ?>
                </div>
            </div>

            <div class="profile-stat-row">
                <div class="profile-stat-block" onclick="switchTab('favorites')">
                    <div class="profile-stat-num"><?php echo $favorite_count; ?></div>
                    <div class="profile-stat-label">收藏</div>
                </div>
                <div class="profile-stat-block" onclick="switchTab('history')">
                    <div class="profile-stat-num"><?php echo $history_count; ?></div>
                    <div class="profile-stat-label">观看</div>
                </div>
                <div class="profile-stat-block" onclick="switchTab('feedback')">
                    <div class="profile-stat-num"><?php echo $feedback_count; ?></div>
                    <div class="profile-stat-label">反馈</div>
                </div>
            </div>

            <button class="avatar-customize-btn" id="avatarCustomizeBtn">
                <span>🎨</span> 自定义头像
            </button>

            <div class="avatar-customize-panel" id="avatarCustomizePanel">
                <form method="POST" enctype="multipart/form-data" id="avatarForm">
                    <input type="hidden" name="action" value="update_avatar">

                    <div class="avatar-panel-title">选择颜色</div>
                    <div class="color-presets" id="colorPresets">
                        <?php
                        $current_color = '01b4e4';
                        foreach ($preset_colors as $color) {
                            $selected = ($color === $current_color) ? ' selected' : '';
                            echo '<div class="color-preset' . $selected . '" data-color="' . clean($color) . '" style="background:#' . clean($color) . '"></div>';
                        }
                        ?>
                    </div>
                    <input type="hidden" name="avatar_color" id="selectedColor" value="<?php echo $current_color; ?>">
                    <input type="hidden" name="avatar_type" id="avatarType" value="preset">

                    <div class="avatar-panel-title" style="margin-top:16px;">或上传图片</div>
                    <label class="avatar-upload-area" id="avatarUploadArea">
                        <div class="avatar-upload-icon">📷</div>
                        <div class="avatar-upload-text">点击选择图片 (最大5MB)</div>
                        <input type="file" name="avatar_upload" id="avatarUpload" class="avatar-upload-input" accept="image/*">
                    </label>

                    <button type="submit" class="btn btn-primary btn-block" style="margin-top:16px;">保存头像</button>
                </form>
            </div>
        </aside>

        <main class="profile-main">
            <div class="tabs-nav">
                <button class="tab-btn <?php echo $tab === 'favorites' ? 'active' : ''; ?>" data-tab="favorites" onclick="switchTab('favorites')">
                    <span>⭐</span><span>我的收藏</span>
                </button>
                <button class="tab-btn <?php echo $tab === 'history' ? 'active' : ''; ?>" data-tab="history" onclick="switchTab('history')">
                    <span>📺</span><span>观看历史</span>
                </button>
                <button class="tab-btn <?php echo $tab === 'feedback' ? 'active' : ''; ?>" data-tab="feedback" onclick="switchTab('feedback')">
                    <span>💬</span><span>我的反馈</span>
                </button>
                <button class="tab-btn <?php echo $tab === 'settings' ? 'active' : ''; ?>" data-tab="settings" onclick="switchTab('settings')">
                    <span>⚙️</span><span>账号设置</span>
                </button>
            </div>

            <div class="tab-content <?php echo $tab === 'favorites' ? 'active' : ''; ?>" id="tab-favorites">
                <?php if (empty($favorites)): ?>
                <div class="empty-state-box">
                    <div class="empty-icon">⭐</div>
                    <h3>暂无收藏</h3>
                    <p>快去收藏喜欢的影片吧！</p>
                </div>
                <?php else: ?>
                <div class="favorites-grid">
                    <?php foreach ($favorites as $fav): ?>
                    <div class="fav-card">
                        <div class="fav-poster">
                            <?php if ($fav['poster']): ?>
                            <img src="<?php echo clean($fav['poster']); ?>" alt="<?php echo clean($fav['title']); ?>" loading="lazy">
                            <?php else: ?>
                            <div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;background:var(--card);color:var(--muted);font-size:3rem;">🎬</div>
                            <?php endif; ?>
                            <form method="POST" style="position:absolute;top:0;right:0;">
                                <input type="hidden" name="action" value="remove_favorite">
                                <input type="hidden" name="media_id" value="<?php echo clean($fav['media_id']); ?>">
                                <button type="submit" class="fav-remove" title="移除收藏">✕</button>
                            </form>
                            <a href="detail.php?id=<?php echo clean($fav['media_id']); ?>&type=<?php echo clean($fav['media_type']); ?>" class="fav-card-link" style="position:absolute;inset:0;z-index:1;"></a>
                        </div>
                        <div class="fav-info">
                            <div class="fav-title"><?php echo clean($fav['title']); ?></div>
                            <div class="fav-meta">
                                <span><?php echo $fav['media_type'] === 'movie' ? '电影' : '电视剧'; ?></span>
                                <span>·</span>
                                <span><?php echo format_date($fav['created_at'], 'Y-m-d'); ?></span>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="tab-content <?php echo $tab === 'history' ? 'active' : ''; ?>" id="tab-history">
                <?php if (empty($history)): ?>
                <div class="empty-state-box">
                    <div class="empty-icon">📺</div>
                    <h3>暂无观看记录</h3>
                    <p>开始观看影片，记录将自动保存在这里</p>
                </div>
                <?php else: ?>
                <div class="clear-history-bar">
                    <h3>观看记录 (<?php echo $history_count; ?>)</h3>
                    <form method="POST" onsubmit="return confirm('确定要清空全部观看历史吗？');">
                        <input type="hidden" name="action" value="clear_history">
                        <button type="submit" class="clear-history-btn">🗑️ 清空全部</button>
                    </form>
                </div>
                <div class="history-list">
                    <?php foreach ($history as $item): ?>
                    <div class="history-card">
                        <div class="history-thumb">
                            <?php if ($item['poster']): ?>
                            <img src="<?php echo clean($item['poster']); ?>" alt="<?php echo clean($item['title']); ?>" loading="lazy">
                            <?php else: ?>
                            <div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;background:var(--card);color:var(--muted);font-size:2rem;">🎬</div>
                            <?php endif; ?>
                        </div>
                        <div class="history-info">
                            <div>
                                <div class="history-title"><?php echo clean($item['title']); ?></div>
                                <div class="history-meta">
                                    <span><?php echo $item['media_type'] === 'movie' ? '电影' : '电视剧'; ?></span>
                                    <?php if ($item['episode'] > 0): ?>
                                    <span>第 <?php echo $item['episode']; ?> 集</span>
                                    <?php endif; ?>
                                    <span>·</span>
                                    <span><?php echo format_date($item['updated_at'], 'Y-m-d H:i'); ?></span>
                                </div>
                            </div>
                            <div class="progress-wrapper">
                                <div class="progress-bar">
                                    <div class="progress-fill" style="width: <?php echo min(100, intval($item['progress'])); ?>%;"></div>
                                </div>
                                <div class="progress-text">观看进度: <?php echo intval($item['progress']); ?>%</div>
                            </div>
                        </div>
                        <div class="history-actions">
                            <a href="player.php?id=<?php echo clean($item['media_id']); ?>&type=<?php echo clean($item['media_type']); ?><?php echo $item['episode'] > 0 ? '&episode=' . $item['episode'] : ''; ?>" class="btn btn-primary btn-sm">▶ 继续观看</a>
                            <form method="POST">
                                <input type="hidden" name="action" value="remove_history">
                                <input type="hidden" name="history_id" value="<?php echo intval($item['id']); ?>">
                                <button type="submit" class="history-delete-btn" title="删除">🗑️</button>
                            </form>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="tab-content <?php echo $tab === 'feedback' ? 'active' : ''; ?>" id="tab-feedback">
                <?php if (empty($my_feedback)): ?>
                <div class="empty-state-box">
                    <div class="empty-icon">💬</div>
                    <h3>暂无反馈</h3>
                    <p>有建议或问题？前往<a href="feedback.php" style="color:var(--primary);">反馈页面</a>告诉我们吧！</p>
                </div>
                <?php else: ?>
                <div class="feedback-list">
                    <?php foreach ($my_feedback as $fb): ?>
                    <div class="feedback-card">
                        <div class="feedback-header-row">
                            <span class="feedback-type-badge"><?php echo clean($fb['type']); ?></span>
                            <span class="feedback-time"><?php echo format_date($fb['created_at'], 'Y-m-d H:i'); ?></span>
                            <span class="feedback-status status-<?php echo clean($fb['status']); ?>">
                                <?php
                                $status_map = array('open' => '待处理', 'resolved' => '已解决', 'closed' => '已关闭');
                                echo isset($status_map[$fb['status']]) ? $status_map[$fb['status']] : $fb['status'];
                                ?>
                            </span>
                        </div>
                        <div class="feedback-content-text"><?php echo clean($fb['content']); ?></div>
                        <div class="feedback-footer-row">
                            <span style="font-size:0.85rem;color:var(--muted);">👍 <?php echo intval($fb['likes']); ?></span>
                            <a href="feedback.php" style="font-size:0.85rem;color:var(--primary);">查看详情 →</a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>

            <div class="tab-content <?php echo $tab === 'settings' ? 'active' : ''; ?>" id="tab-settings">
                <div class="settings-section" style="margin-bottom:24px;">
                    <div class="settings-section-title">修改用户名</div>
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="action" value="update_username">
                        <div class="form-group">
                            <label class="form-label">新用户名</label>
                            <input type="text" name="username" class="form-control" value="<?php echo clean($user['username']); ?>" required minlength="3" maxlength="20">
                            <div class="form-hint">3-20个字符，唯一标识</div>
                        </div>
                        <div class="settings-form-actions">
                            <button type="submit" class="btn btn-primary">保存修改</button>
                        </div>
                    </form>
                </div>

                <div class="settings-section">
                    <div class="settings-section-title">修改密码</div>
                    <form method="POST" class="settings-form">
                        <input type="hidden" name="action" value="update_password">
                        <div class="form-group">
                            <label class="form-label">当前密码</label>
                            <input type="password" name="current_password" class="form-control" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">新密码</label>
                            <input type="password" name="new_password" class="form-control" required minlength="6" id="newPwd">
                            <div class="form-hint">至少6位，建议包含字母和数字</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">确认新密码</label>
                            <input type="password" name="confirm_password" class="form-control" required minlength="6">
                        </div>
                        <div class="settings-form-actions">
                            <button type="submit" class="btn btn-primary">更新密码</button>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
(function() {
    var currentTab = '<?php echo $tab; ?>';

    window.switchTab = function(tabName) {
        currentTab = tabName;
        var tabs = document.querySelectorAll('.tab-btn');
        var contents = document.querySelectorAll('.tab-content');
        tabs.forEach(function(t) { t.classList.remove('active'); });
        contents.forEach(function(c) { c.classList.remove('active'); });
        var btn = document.querySelector('.tab-btn[data-tab="' + tabName + '"]');
        var content = document.getElementById('tab-' + tabName);
        if (btn) btn.classList.add('active');
        if (content) content.classList.add('active');
        if (history.replaceState) {
            history.replaceState(null, null, '?tab=' + tabName);
        }
        window.scrollTo({ top: document.querySelector('.profile-main').offsetTop - 80, behavior: 'smooth' });
    };

    var customizeBtn = document.getElementById('avatarCustomizeBtn');
    var customizePanel = document.getElementById('avatarCustomizePanel');
    if (customizeBtn && customizePanel) {
        customizeBtn.addEventListener('click', function() {
            customizePanel.classList.toggle('active');
        });
    }

    var colorPresets = document.querySelectorAll('.color-preset');
    var selectedColor = document.getElementById('selectedColor');
    var avatarType = document.getElementById('avatarType');
    var avatarUploadArea = document.getElementById('avatarUploadArea');
    var avatarUpload = document.getElementById('avatarUpload');

    colorPresets.forEach(function(preset) {
        preset.addEventListener('click', function() {
            colorPresets.forEach(function(p) { p.classList.remove('selected'); });
            preset.classList.add('selected');
            selectedColor.value = preset.dataset.color;
            avatarType.value = 'preset';
            avatarUploadArea.classList.remove('has-file');
            avatarUploadArea.querySelector('.avatar-upload-text').textContent = '点击选择图片 (最大5MB)';
            avatarUpload.value = '';
        });
    });

    if (avatarUpload) {
        avatarUpload.addEventListener('change', function() {
            if (avatarUpload.files && avatarUpload.files[0]) {
                var file = avatarUpload.files[0];
                if (file.size > 5 * 1024 * 1024) {
                    alert('图片大小不能超过5MB');
                    avatarUpload.value = '';
                    return;
                }
                avatarType.value = 'upload';
                avatarUploadArea.classList.add('has-file');
                avatarUploadArea.querySelector('.avatar-upload-text').textContent = '已选择: ' + file.name;
                var reader = new FileReader();
                reader.onload = function(e) {
                    var preview = document.getElementById('avatarPreview');
                    if (preview) preview.src = e.target.result;
                };
                reader.readAsDataURL(file);
            }
        });
    }

    var avatarForm = document.getElementById('avatarForm');
    if (avatarForm) {
        avatarForm.addEventListener('submit', function(e) {
            if (avatarType.value === 'preset') {
                e.preventDefault();
                var color = selectedColor.value;
                var username = '<?php echo addslashes($user['username']); ?>';
                var letter = username.charAt(0).toUpperCase();
                var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><circle cx="100" cy="100" r="100" fill="#' + color + '"/><text x="100" y="130" font-family="Arial" font-size="90" font-weight="700" fill="white" text-anchor="middle">' + letter + '</text></svg>';
                var avatarData = 'data:image/svg+xml;base64,' + btoa(unescape(encodeURIComponent(svg)));
                var preview = document.getElementById('avatarPreview');
                if (preview) preview.src = avatarData;

                var formData = new FormData();
                formData.append('action', 'update_avatar');
                formData.append('avatar_type', 'preset');
                formData.append('avatar_color', color);

                fetch('', {
                    method: 'POST',
                    body: formData
                }).then(function() {
                    location.reload();
                });
            }
        });
    }
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>