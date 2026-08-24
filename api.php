<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/tmdb.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : '';
$method = $_SERVER['REQUEST_METHOD'];

function api_error($message, $code = 400) {
    json_response(array('success' => false, 'message' => $message), $code);
}

function api_success($data = array(), $message = '操作成功') {
    json_response(array('success' => true, 'message' => $message, 'data' => $data));
}

try {
    switch ($action) {
        case 'favorite':
            $user = current_user();
            if (!$user) {
                api_error('请先登录', 401);
            }

            $db = get_db();

            if ($method === 'POST') {
                $mediaId = isset($_POST['media_id']) ? trim($_POST['media_id']) : '';
                $mediaType = isset($_POST['media_type']) ? trim($_POST['media_type']) : '';
                $title = isset($_POST['title']) ? trim($_POST['title']) : '';
                $poster = isset($_POST['poster']) ? trim($_POST['poster']) : '';

                if (empty($mediaId) || empty($mediaType)) {
                    api_error('参数不完整');
                }

                $existing = $db->fetchOne(
                    "SELECT id FROM favorites WHERE user_id = ? AND media_id = ?",
                    array($user['id'], $mediaId)
                );
                if ($existing) {
                    api_error('已收藏该影片');
                }

                $id = $db->insert('favorites', array(
                    'user_id' => $user['id'],
                    'media_id' => $mediaId,
                    'media_type' => $mediaType,
                    'title' => $title,
                    'poster' => $poster
                ));

                api_success(array('favorite_id' => $id), '收藏成功');

            } elseif ($method === 'DELETE') {
                $input = json_decode(file_get_contents('php://input'), true);
                $mediaId = isset($input['media_id']) ? trim($input['media_id']) : '';

                if (empty($mediaId)) {
                    $mediaId = isset($_GET['media_id']) ? trim($_GET['media_id']) : '';
                }

                if (empty($mediaId)) {
                    api_error('参数不完整');
                }

                $db->delete('favorites', 'user_id = ? AND media_id = ?', array($user['id'], $mediaId));
                api_success(array(), '已取消收藏');

            } else {
                api_error('请求方法不被允许', 405);
            }
            break;

        case 'watch-progress':
            $user = current_user();
            if (!$user) {
                api_error('请先登录', 401);
            }

            if ($method !== 'POST') {
                api_error('请求方法不被允许', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            $mediaId = isset($input['media_id']) ? trim($input['media_id']) : '';
            $mediaType = isset($input['media_type']) ? trim($input['media_type']) : '';
            $title = isset($input['title']) ? trim($input['title']) : '';
            $poster = isset($input['poster']) ? trim($input['poster']) : '';
            $episode = isset($input['episode']) ? intval($input['episode']) : 0;
            $season = isset($input['season']) ? intval($input['season']) : 0;
            $progress = isset($input['progress']) ? floatval($input['progress']) : 0;

            if (empty($mediaId)) {
                api_error('参数不完整');
            }

            update_watch_history($user['id'], $mediaId, $mediaType, $title, $poster, $episode, $season, $progress);
            api_success(array(), '观看进度已保存');
            break;

        case 'search':
            if ($method !== 'GET') {
                api_error('请求方法不被允许', 405);
            }

            $query = isset($_GET['q']) ? trim($_GET['q']) : '';
            $type = isset($_GET['type']) ? trim($_GET['type']) : 'multi';
            $page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;

            if (empty($query)) {
                api_error('搜索关键词不能为空');
            }

            $tmdb = new TMDB();
            $results = $tmdb->search($query, $type, $page);

            api_success(array(
                'results' => isset($results['results']) ? $results['results'] : array(),
                'total_results' => isset($results['total_results']) ? $results['total_results'] : 0,
                'total_pages' => isset($results['total_pages']) ? $results['total_pages'] : 1,
                'page' => $page
            ));
            break;

        case 'send-code':
            if ($method !== 'POST') {
                api_error('请求方法不被允许', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            $email = isset($input['email']) ? trim($input['email']) : '';
            $type = isset($input['type']) ? trim($input['type']) : 'register';

            if (empty($email)) {
                api_error('请填写邮箱地址');
            }

            if (!is_valid_email($email)) {
                api_error('邮箱格式不正确');
            }

            if ($type === 'register') {
                $result = send_register_code($email);
            } elseif ($type === 'reset') {
                $result = send_reset_code($email);
            } else {
                api_error('无效的验证码类型');
            }

            if ($result['success']) {
                api_success(array(), $result['message']);
            } else {
                api_error($result['message']);
            }
            break;

        case 'announcement-dismiss':
            $user = current_user();
            if (!$user) {
                api_error('请先登录', 401);
            }

            if ($method !== 'POST') {
                api_error('请求方法不被允许', 405);
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) {
                $input = $_POST;
            }

            $announcementId = isset($input['announcement_id']) ? intval($input['announcement_id']) : 0;

            if ($announcementId <= 0) {
                api_error('参数不完整');
            }

            $db = get_db();
            $existing = $db->fetchOne(
                "SELECT id FROM announcement_dismissed WHERE announcement_id = ? AND user_id = ?",
                array($announcementId, $user['id'])
            );

            if (!$existing) {
                $db->insert('announcement_dismissed', array(
                    'user_id' => $user['id'],
                    'announcement_id' => $announcementId
                ));
            }

            api_success(array(), '已关闭公告');
            break;

        case 'feedback':
            if ($method !== 'POST') {
                $list = get_feedback_list();
                api_success($list);
            }
            $user = current_user();
            if (!$user) {
                api_error('请先登录', 401);
            }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { $input = $_POST; }
            $title = isset($input['title']) ? trim($input['title']) : '';
            $content = isset($input['content']) ? trim($input['content']) : '';
            $type = isset($input['type']) ? trim($input['type']) : '建议';
            if (empty($content)) {
                api_error('反馈内容不能为空');
            }
            $id = $db->insert('feedback', array(
                'user_id' => $user['id'],
                'title' => $title,
                'content' => $content,
                'status' => 'open',
                'type' => $type
            ));
            api_success(array('id' => $id), '反馈提交成功');
            break;

        case 'feedback-reply':
            $user = current_user();
            if (!$user) { api_error('请先登录', 401); }
            if ($method !== 'POST') { api_error('请求方法不被允许', 405); }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { $input = $_POST; }
            $feedbackId = isset($input['feedback_id']) ? intval($input['feedback_id']) : 0;
            $content = isset($input['content']) ? trim($input['content']) : '';
            if ($feedbackId <= 0 || empty($content)) { api_error('参数不完整'); }
            $feedback = $db->fetchOne("SELECT * FROM feedback WHERE id = ?", array($feedbackId));
            if (!$feedback) { api_error('反馈不存在'); }
            $adminReply = is_admin() ? 1 : 0;
            $id = $db->insert('feedback_replies', array(
                'feedback_id' => $feedbackId,
                'user_id' => $user['id'],
                'content' => $content,
                'is_admin' => $adminReply
            ));
            if ($adminReply) {
                $owner = $db->fetchOne("SELECT email FROM users WHERE id = ?", array($feedback['user_id']));
                if ($owner && $owner['email']) {
                    $mailer = new Mailer();
                    $mailer->send_template($owner['email'], '管理员回复了您的反馈', 'feedback_reply', array(
                        'content' => $content
                    ));
                }
            }
            api_success(array('id' => $id), '回复成功');
            break;

        case 'feedback-like':
            $user = current_user();
            if (!$user) { api_error('请先登录', 401); }
            if ($method !== 'POST') { api_error('请求方法不被允许', 405); }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { $input = $_POST; }
            $targetId = isset($input['target_id']) ? intval($input['target_id']) : 0;
            $targetType = isset($input['target_type']) ? trim($input['target_type']) : 'feedback';
            if ($targetId <= 0) { api_error('参数不完整'); }
            if ($targetType === 'feedback') {
                like_feedback($user['id'], $targetId);
                $row = $db->fetchOne("SELECT likes FROM feedback WHERE id = ?", array($targetId));
                api_success(array('likes' => $row ? intval($row['likes']) : 0));
            } else {
                like_reply($user['id'], $targetId);
                $row = $db->fetchOne("SELECT likes FROM feedback_replies WHERE id = ?", array($targetId));
                api_success(array('likes' => $row ? intval($row['likes']) : 0));
            }
            break;

        case 'user-ban':
            $admin = is_admin();
            if (!$admin) { api_error('无权限', 403); }
            if ($method !== 'POST') { api_error('请求方法不被允许', 405); }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { $input = $_POST; }
            $userId = isset($input['user_id']) ? intval($input['user_id']) : 0;
            $hours = isset($input['duration_hours']) ? $input['duration_hours'] : null;
            $reason = isset($input['reason']) ? trim($input['reason']) : '违反社区规定';
            if ($userId <= 0) { api_error('参数不完整'); }
            $banUntil = null;
            if ($hours !== null && is_numeric($hours)) {
                $banUntil = date('Y-m-d H:i:s', time() + (int)$hours * 3600);
            }
            $db->update('users', array(
                'status' => 'banned',
                'ban_until' => $banUntil,
                'ban_reason' => $reason
            ), 'id = ?', array($userId));
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", array($userId));
            if ($user && $user['email']) {
                $mailer = new Mailer();
                $mailer->send_template($user['email'], '账号封禁通知', 'banned', array(
                    'username' => $user['username'],
                    'reason' => $reason,
                    'until' => $banUntil ? $banUntil : '永久'
                ));
            }
            api_success(array(), '封禁成功');
            break;

        case 'user-unban':
            if (!is_admin()) { api_error('无权限', 403); }
            if ($method !== 'POST') { api_error('请求方法不被允许', 405); }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { $input = $_POST; }
            $userId = isset($input['user_id']) ? intval($input['user_id']) : 0;
            if ($userId <= 0) { api_error('参数不完整'); }
            $db->update('users', array(
                'status' => 'normal',
                'ban_until' => null,
                'ban_reason' => ''
            ), 'id = ?', array($userId));
            $user = $db->fetchOne("SELECT * FROM users WHERE id = ?", array($userId));
            if ($user && $user['email']) {
                $mailer = new Mailer();
                $mailer->send_template($user['email'], '账号解封通知', 'unbanned', array(
                    'username' => $user['username']
                ));
            }
            api_success(array(), '解封成功');
            break;

        case 'source':
            if ($method === 'GET') {
                $sources = $db->fetchAll("SELECT * FROM play_sources WHERE status = 1 ORDER BY is_default DESC, id ASC");
                api_success($sources);
            } elseif ($method === 'POST') {
                if (!is_admin()) { api_error('无权限', 403); }
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) { $input = $_POST; }
                $name = isset($input['name']) ? trim($input['name']) : '';
                $apiUrl = isset($input['api_url']) ? trim($input['api_url']) : '';
                $parserUrl = isset($input['parser_url']) ? trim($input['parser_url']) : '';
                if (empty($name) || empty($apiUrl)) { api_error('参数不完整'); }
                $id = $db->insert('play_sources', array(
                    'name' => $name,
                    'api_url' => $apiUrl,
                    'parser_url' => $parserUrl,
                    'is_default' => 0,
                    'status' => 1
                ));
                api_success(array('id' => $id), '添加成功');
            } else {
                api_error('请求方法不被允许', 405);
            }
            break;

        case 'source-edit':
            if (!is_admin()) { api_error('无权限', 403); }
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id <= 0) { api_error('参数不完整'); }
            if ($method === 'GET') {
                $source = $db->fetchOne("SELECT * FROM play_sources WHERE id = ?", array($id));
                if (!$source) { api_error('资源源不存在'); }
                api_success($source);
            } elseif ($method === 'PUT' || $method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) { $input = $_POST; }
                $db->update('play_sources', array(
                    'name' => isset($input['name']) ? trim($input['name']) : '',
                    'api_url' => isset($input['api_url']) ? trim($input['api_url']) : '',
                    'parser_url' => isset($input['parser_url']) ? trim($input['parser_url']) : ''
                ), 'id = ?', array($id));
                api_success(array(), '更新成功');
            }
            break;

        case 'source-delete':
            if (!is_admin()) { api_error('无权限', 403); }
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id <= 0) { api_error('参数不完整'); }
            $db->delete('play_sources', 'id = ?', array($id));
            api_success(array(), '删除成功');
            break;

        case 'announcement':
            if ($method === 'GET') {
                $list = $db->fetchAll("SELECT * FROM announcements ORDER BY id DESC");
                api_success($list);
            } elseif ($method === 'POST') {
                if (!is_admin()) { api_error('无权限', 403); }
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) { $input = $_POST; }
                $title = isset($input['title']) ? trim($input['title']) : '';
                $content = isset($input['content']) ? trim($input['content']) : '';
                if (empty($title) || empty($content)) { api_error('参数不完整'); }
                $id = $db->insert('announcements', array(
                    'title' => $title,
                    'content' => $content,
                    'is_active' => 1
                ));
                api_success(array('id' => $id), '发布成功');
            } else {
                api_error('请求方法不被允许', 405);
            }
            break;

        case 'announcement-edit':
            if (!is_admin()) { api_error('无权限', 403); }
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id <= 0) { api_error('参数不完整'); }
            if ($method === 'GET') {
                $ann = $db->fetchOne("SELECT * FROM announcements WHERE id = ?", array($id));
                if (!$ann) { api_error('公告不存在'); }
                api_success($ann);
            } elseif ($method === 'PUT' || $method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) { $input = $_POST; }
                $db->update('announcements', array(
                    'title' => isset($input['title']) ? trim($input['title']) : '',
                    'content' => isset($input['content']) ? trim($input['content']) : ''
                ), 'id = ?', array($id));
                api_success(array(), '更新成功');
            }
            break;

        case 'announcement-delete':
            if (!is_admin()) { api_error('无权限', 403); }
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id <= 0) { api_error('参数不完整'); }
            $db->delete('announcements', 'id = ?', array($id));
            api_success(array(), '删除成功');
            break;

        case 'announcement-toggle':
            if (!is_admin()) { api_error('无权限', 403); }
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id <= 0) { api_error('参数不完整'); }
            $ann = $db->fetchOne("SELECT is_active FROM announcements WHERE id = ?", array($id));
            $newStatus = $ann && $ann['is_active'] ? 0 : 1;
            $db->update('announcements', array('is_active' => $newStatus), 'id = ?', array($id));
            api_success(array(), '状态已更新');
            break;

        case 'send-email':
            if (!is_admin()) { api_error('无权限', 403); }
            if ($method !== 'POST') { api_error('请求方法不被允许', 405); }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { $input = $_POST; }
            $to = isset($input['to']) ? trim($input['to']) : '';
            $subject = isset($input['subject']) ? trim($input['subject']) : '';
            $content = isset($input['content']) ? trim($input['content']) : '';
            $template = isset($input['template']) ? trim($input['template']) : 'custom';
            if (empty($to) || empty($subject)) { api_error('参数不完整'); }
            $mailer = new Mailer();
            if ($template === 'custom') {
                $result = $mailer->send($to, $subject, $content, true);
            } else {
                $result = $mailer->send_template($to, $subject, $template, array('content' => $content));
            }
            if (!$result) { api_error('邮件发送失败'); }
            api_success(array(), '邮件发送成功');
            break;

        case 'theme':
            if (!is_admin()) { api_error('无权限', 403); }
            if ($method === 'GET') {
                $theme = $db->fetchOne("SELECT * FROM themes ORDER BY id DESC LIMIT 1");
                api_success($theme ?: array());
            } elseif ($method === 'POST') {
                $input = json_decode(file_get_contents('php://input'), true);
                if (!$input) { $input = $_POST; }
                $db->query("DELETE FROM themes");
                $db->insert('themes', array(
                    'primary_color' => isset($input['primary']) ? $input['primary'] : '#01b4e4',
                    'secondary_color' => isset($input['secondary']) ? $input['secondary'] : '#0d253f',
                    'accent_color' => isset($input['accent']) ? $input['accent'] : '#1f80e0',
                    'background_color' => isset($input['background']) ? $input['background'] : '#04070d',
                    'text_color' => isset($input['text']) ? $input['text'] : '#ffffff',
                    'muted_color' => isset($input['muted']) ? $input['muted'] : '#9aa0a6',
                    'updated_at' => date('Y-m-d H:i:s')
                ));
                api_success(array(), '主题已保存');
            }
            break;

        case 'feedback-resolve':
            if (!is_admin()) { api_error('无权限', 403); }
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            $action = isset($_GET['action']) ? trim($_GET['action']) : '';
            if ($id <= 0) { api_error('参数不完整'); }
            if ($action === 'resolve') {
                $db->update('feedback', array('status' => 'resolved'), 'id = ?', array($id));
                api_success(array(), '已解决');
            } elseif ($action === 'close') {
                $db->update('feedback', array('status' => 'closed'), 'id = ?', array($id));
                api_success(array(), '已关闭');
            } elseif ($action === 'delete') {
                $db->delete('feedback', 'id = ?', array($id));
                api_success(array(), '已删除');
            }
            api_error('无效的操作');
            break;

        case 'feedback-list':
            $status = isset($_GET['status']) ? trim($_GET['status']) : 'all';
            $list = get_feedback_list($status);
            api_success($list);
            break;

        case 'feedback-delete':
            if (!is_admin()) { api_error('无权限', 403); }
            $id = isset($_GET['id']) ? intval($_GET['id']) : 0;
            if ($id <= 0) { api_error('参数不完整'); }
            $db->delete('feedback', 'id = ?', array($id));
            $db->delete('feedback_replies', 'feedback_id = ?', array($id));
            api_success(array(), '删除成功');
            break;

        case 'source-add':
            if (!is_admin()) { api_error('无权限', 403); }
            if ($method !== 'POST') { api_error('请求方法不被允许', 405); }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { $input = $_POST; }
            $name = isset($input['name']) ? trim($input['name']) : '';
            $apiUrl = isset($input['api_url']) ? trim($input['api_url']) : '';
            $parserUrl = isset($input['parser_url']) ? trim($input['parser_url']) : '';
            if (empty($name) || empty($apiUrl)) { api_error('参数不完整'); }
            $id = $db->insert('play_sources', array(
                'name' => $name,
                'api_url' => $apiUrl,
                'parser_url' => $parserUrl,
                'is_default' => 0,
                'status' => 1
            ));
            api_success(array('id' => $id), '添加成功');
            break;

        case 'announcement-add':
            if (!is_admin()) { api_error('无权限', 403); }
            if ($method !== 'POST') { api_error('请求方法不被允许', 405); }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { $input = $_POST; }
            $title = isset($input['title']) ? trim($input['title']) : '';
            $content = isset($input['content']) ? trim($input['content']) : '';
            $type = isset($input['type']) ? trim($input['type']) : 'info';
            $isActive = isset($input['is_active']) ? intval($input['is_active']) : 1;
            if (empty($title) || empty($content)) { api_error('参数不完整'); }
            $id = $db->insert('announcements', array(
                'title' => $title,
                'content' => $content,
                'type' => $type,
                'is_active' => $isActive
            ));
            api_success(array('id' => $id), '添加成功');
            break;

        case 'theme-save':
            if (!is_admin()) { api_error('无权限', 403); }
            if ($method !== 'POST') { api_error('请求方法不被允许', 405); }
            $input = json_decode(file_get_contents('php://input'), true);
            if (!$input) { $input = $_POST; }
            $db->query("DELETE FROM themes");
            $db->insert('themes', array(
                'primary_color' => isset($input['primary']) ? $input['primary'] : '#01b4e4',
                'secondary_color' => isset($input['secondary']) ? $input['secondary'] : '#0d253f',
                'accent_color' => isset($input['accent']) ? $input['accent'] : '#1f80e0',
                'background_color' => isset($input['background']) ? $input['background'] : '#04070d',
                'text_color' => isset($input['text']) ? $input['text'] : '#ffffff',
                'muted_color' => isset($input['muted']) ? $input['muted'] : '#9aa0a6',
                'updated_at' => date('Y-m-d H:i:s')
            ));
            api_success(array(), '主题已保存');
            break;

        default:
            api_error('未知的API端点', 404);
    }

} catch (Exception $e) {
    api_error('服务器内部错误: ' . $e->getMessage(), 500);
}