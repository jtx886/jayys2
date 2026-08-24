<?php
// 公共函数库

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Access Denied');
}

// 获取配置
function get_config() {
    return $GLOBALS['config'];
}

// 获取数据库实例
function get_db() {
    return $GLOBALS['db'];
}

// 防止XSS
function clean($str) {
    if (is_string($str)) {
        return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
    }
    return $str;
}

// 重定向
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

// 生成随机字符串
function random_string($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $str = '';
    for ($i = 0; $i < $length; $i++) {
        $str .= $chars[mt_rand(0, strlen($chars) - 1)];
    }
    return $str;
}

// 生成验证码
function generate_code($length = 6) {
    $code = '';
    for ($i = 0; $i < $length; $i++) {
        $code .= mt_rand(0, 9);
    }
    return $code;
}

// 密码哈希
function hash_password($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// 密码验证
function verify_password($password, $hash) {
    return password_verify($password, $hash);
}

// 发送JSON响应
function json_response($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// 检查是否登录
function is_logged_in() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['user_id']);
}

// 获取当前用户
function current_user() {
    if (!is_logged_in()) {
        return null;
    }
    $db = get_db();
    return $db->fetchOne("SELECT * FROM users WHERE id = ?", array($_SESSION['user_id']));
}

// 检查用户是否被封禁
function is_user_banned($user) {
    if (!$user) {
        return false;
    }
    if ($user['status'] !== 'banned') {
        return false;
    }
    if ($user['ban_until'] && strtotime($user['ban_until']) > time()) {
        return true;
    }
    return false;
}

// 获取主题设置
function get_theme() {
    $db = get_db();
    $theme = $db->fetchOne("SELECT * FROM themes ORDER BY id DESC LIMIT 1");
    if (!$theme) {
        $config = get_config();
        return $config['theme'];
    }
    return array(
        'primary' => $theme['primary_color'],
        'secondary' => $theme['secondary_color'],
        'accent' => $theme['accent_color'],
        'background' => $theme['background_color'],
        'text' => $theme['text_color'],
        'muted' => $theme['muted_color']
    );
}

// 获取当前播放源
function get_default_source() {
    $db = get_db();
    $source = $db->fetchOne("SELECT * FROM play_sources WHERE status = 1 ORDER BY is_default DESC, id ASC LIMIT 1");
    if (!$source) {
        $config = get_config();
        return array(
            'api_url' => $config['play_source_api'],
            'parser_url' => $config['parser_url']
        );
    }
    return $source;
}

// 获取公告
function get_active_announcements() {
    $db = get_db();
    return $db->fetchAll("SELECT * FROM announcements WHERE is_active = 1 ORDER BY id DESC");
}

// 检查公告是否已被用户关闭
function is_announcement_dismissed($announcementId, $userId = null) {
    if (!$userId) {
        return false;
    }
    $db = get_db();
    $result = $db->fetchOne(
        "SELECT * FROM announcement_dismissed WHERE announcement_id = ? AND user_id = ?",
        array($announcementId, $userId)
    );
    return !empty($result);
}

// 格式化日期
function format_date($date, $format = 'Y-m-d H:i:s') {
    if (!$date) {
        return '';
    }
    return date($format, strtotime($date));
}

// TMDB图片URL
function tmdb_image_url($path, $size = 'w500') {
    if (!$path) {
        return '';
    }
    $config = get_config();
    return $config['tmdb_image_url'] . '/' . $size . $path;
}

// 获取用户收藏
function get_user_favorites($userId) {
    $db = get_db();
    return $db->fetchAll(
        "SELECT * FROM favorites WHERE user_id = ? ORDER BY created_at DESC",
        array($userId)
    );
}

// 获取用户观看历史
function get_user_history($userId) {
    $db = get_db();
    return $db->fetchAll(
        "SELECT * FROM watch_history WHERE user_id = ? ORDER BY updated_at DESC",
        array($userId)
    );
}

// 添加收藏
function add_favorite($userId, $mediaId, $mediaType, $title, $poster) {
    $db = get_db();
    $existing = $db->fetchOne(
        "SELECT * FROM favorites WHERE user_id = ? AND media_id = ?",
        array($userId, $mediaId)
    );
    if ($existing) {
        return false;
    }
    return $db->insert('favorites', array(
        'user_id' => $userId,
        'media_id' => $mediaId,
        'media_type' => $mediaType,
        'title' => $title,
        'poster' => $poster
    ));
}

// 移除收藏
function remove_favorite($userId, $mediaId) {
    $db = get_db();
    return $db->delete('favorites', 'user_id = ? AND media_id = ?', array($userId, $mediaId));
}

// 检查是否已收藏
function is_favorited($userId, $mediaId) {
    $db = get_db();
    $result = $db->fetchOne(
        "SELECT id FROM favorites WHERE user_id = ? AND media_id = ?",
        array($userId, $mediaId)
    );
    return !empty($result);
}

// 更新观看历史
function update_watch_history($userId, $mediaId, $mediaType, $title, $poster, $episode = 0, $season = 0, $progress = 0) {
    $db = get_db();
    $existing = $db->fetchOne(
        "SELECT id FROM watch_history WHERE user_id = ? AND media_id = ? AND episode = ? AND season = ?",
        array($userId, $mediaId, $episode, $season)
    );
    if ($existing) {
        return $db->update('watch_history', array(
            'title' => $title,
            'poster' => $poster,
            'progress' => $progress,
            'updated_at' => date('Y-m-d H:i:s')
        ), 'id = ?', array($existing['id']));
    }
    return $db->insert('watch_history', array(
        'user_id' => $userId,
        'media_id' => $mediaId,
        'media_type' => $mediaType,
        'title' => $title,
        'poster' => $poster,
        'episode' => $episode,
        'season' => $season,
        'progress' => $progress,
        'updated_at' => date('Y-m-d H:i:s')
    ));
}

// 移除观看历史
function remove_watch_history($userId, $mediaId, $episode = 0) {
    $db = get_db();
    if ($episode > 0) {
        return $db->delete('watch_history', 'user_id = ? AND media_id = ? AND episode = ?', array($userId, $mediaId, $episode));
    }
    return $db->delete('watch_history', 'user_id = ? AND media_id = ?', array($userId, $mediaId));
}

// 删除全部观看历史
function clear_watch_history($userId) {
    $db = get_db();
    return $db->delete('watch_history', 'user_id = ?', array($userId));
}

// 发送邮件
function send_email($to, $subject, $body, $isHtml = true) {
    $mailer = new Mailer();
    return $mailer->send($to, $subject, $body, $isHtml);
}

// 获取反馈列表
function get_feedback_list($status = 'all') {
    $db = get_db();
    if ($status === 'all') {
        return $db->fetchAll("SELECT f.*, u.username, u.avatar FROM feedback f LEFT JOIN users u ON f.user_id = u.id ORDER BY f.created_at DESC");
    }
    return $db->fetchAll(
        "SELECT f.*, u.username, u.avatar FROM feedback f LEFT JOIN users u ON f.user_id = u.id WHERE f.status = ? ORDER BY f.created_at DESC",
        array($status)
    );
}

// 获取反馈回复
function get_feedback_replies($feedbackId) {
    $db = get_db();
    return $db->fetchAll(
        "SELECT fr.*, u.username, u.avatar, u.id as user_id FROM feedback_replies fr LEFT JOIN users u ON fr.user_id = u.id WHERE fr.feedback_id = ? ORDER BY CASE WHEN fr.is_admin = 1 THEN 0 ELSE 1 END, fr.created_at ASC",
        array($feedbackId)
    );
}

// 点赞反馈
function like_feedback($userId, $feedbackId) {
    $db = get_db();
    $existing = $db->fetchOne(
        "SELECT id FROM feedback_likes WHERE user_id = ? AND target_id = ? AND target_type = 'feedback'",
        array($userId, $feedbackId)
    );
    if ($existing) {
        return false;
    }
    $db->insert('feedback_likes', array(
        'user_id' => $userId,
        'target_id' => $feedbackId,
        'target_type' => 'feedback'
    ));
    $db->query("UPDATE feedback SET likes = likes + 1 WHERE id = ?", array($feedbackId));
    return true;
}

// 点赞回复
function like_reply($userId, $replyId) {
    $db = get_db();
    $existing = $db->fetchOne(
        "SELECT id FROM feedback_likes WHERE user_id = ? AND target_id = ? AND target_type = 'reply'",
        array($userId, $replyId)
    );
    if ($existing) {
        return false;
    }
    $db->insert('feedback_likes', array(
        'user_id' => $userId,
        'target_id' => $replyId,
        'target_type' => 'reply'
    ));
    $db->query("UPDATE feedback_replies SET likes = likes + 1 WHERE id = ?", array($replyId));
    return true;
}

// 获取仪表盘统计
function get_dashboard_stats() {
    $db = get_db();
    return array(
        'total_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users")['count'],
        'active_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'normal'")['count'],
        'banned_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE status = 'banned'")['count'],
        'total_favorites' => $db->fetchOne("SELECT COUNT(*) as count FROM favorites")['count'],
        'total_history' => $db->fetchOne("SELECT COUNT(*) as count FROM watch_history")['count'],
        'total_feedback' => $db->fetchOne("SELECT COUNT(*) as count FROM feedback")['count'],
        'new_users' => $db->fetchOne("SELECT COUNT(*) as count FROM users WHERE created_at > datetime('now', '-7 days')")['count']
    );
}
