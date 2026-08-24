<?php
// 用户类
class User {
    private $db;
    private $currentUser = null;

    public function __construct() {
        $this->db = Database::getInstance();
        if (isset($_SESSION['user_id'])) {
            $this->loadCurrentUser($_SESSION['user_id']);
        }
    }

    public function loadCurrentUser($userId) {
        $sql = "SELECT * FROM users WHERE id = ?";
        $user = $this->db->fetchOne($sql, array($userId), 'i');
        if ($user) {
            $this->currentUser = $user;
            return true;
        }
        return false;
    }

    public function isLoggedIn() {
        return $this->currentUser !== null && $this->currentUser['status'] == 1;
    }

    public function isAdmin() {
        return $this->isLoggedIn() && $this->currentUser['is_admin'] == 1;
    }

    public function isBanned() {
        if ($this->currentUser === null) return false;
        if ($this->currentUser['status'] == 0) {
            // 检查封禁是否到期
            if ($this->currentUser['ban_until'] && strtotime($this->currentUser['ban_until']) < time()) {
                // 自动解封
                $this->db->update('users', array('status' => 1, 'ban_time' => null, 'ban_until' => null, 'ban_reason' => null), 'id = ?', array($this->currentUser['id']), 'i');
                $this->currentUser['status'] = 1;
                return false;
            }
            return true;
        }
        return false;
    }

    public function getCurrentUser() {
        return $this->currentUser;
    }

    public function getById($id) {
        $sql = "SELECT * FROM users WHERE id = ?";
        return $this->db->fetchOne($sql, array($id), 'i');
    }

    public function getByEmail($email) {
        $sql = "SELECT * FROM users WHERE email = ?";
        return $this->db->fetchOne($sql, array($email));
    }

    public function getByUsername($username) {
        $sql = "SELECT * FROM users WHERE username = ?";
        return $this->db->fetchOne($sql, array($username));
    }

    public function register($username, $email, $password, $code) {
        // 验证验证码
        $sql = "SELECT * FROM email_verifications WHERE email = ? AND code = ? AND type = 'register' AND used = 0 AND expires_at > NOW() ORDER BY id DESC LIMIT 1";
        $verification = $this->db->fetchOne($sql, array($email, $code));
        
        if (!$verification) {
            return array('success' => false, 'message' => '验证码错误或已过期');
        }

        // 检查用户名是否存在
        if ($this->getByUsername($username)) {
            return array('success' => false, 'message' => '用户名已被使用');
        }

        // 检查邮箱是否存在
        if ($this->getByEmail($email)) {
            return array('success' => false, 'message' => '邮箱已被注册');
        }

        // 创建用户
        $hashedPassword = $this->hashPassword($password);
        $userId = $this->db->insert('users', array(
            'username' => $username,
            'email' => $email,
            'password' => $hashedPassword,
            'status' => 1
        ));

        if (!$userId) {
            return array('success' => false, 'message' => '注册失败，请稍后重试');
        }

        // 标记验证码已使用
        $this->db->update('email_verifications', array('used' => 1), 'id = ?', array($verification['id']), 'i');

        // 自动登录
        $_SESSION['user_id'] = $userId;
        $this->loadCurrentUser($userId);

        return array('success' => true, 'message' => '注册成功', 'user_id' => $userId);
    }

    public function login($username, $password) {
        // 支持用户名或邮箱登录
        if (strpos($username, '@') !== false) {
            $user = $this->getByEmail($username);
        } else {
            $user = $this->getByUsername($username);
        }

        if (!$user) {
            return array('success' => false, 'message' => '用户不存在');
        }

        if (!$this->verifyPassword($password, $user['password'])) {
            return array('success' => false, 'message' => '密码错误');
        }

        if ($user['status'] == 0) {
            if ($user['ban_until'] && strtotime($user['ban_until']) < time()) {
                // 自动解封
                $this->db->update('users', array('status' => 1, 'ban_time' => null, 'ban_until' => null, 'ban_reason' => null), 'id = ?', array($user['id']), 'i');
            } else {
                $banMsg = $user['ban_until'] ? ('，解禁时间：' . date('Y-m-d H:i:s', strtotime($user['ban_until']))) : '（永久）';
                return array('success' => false, 'message' => '账号已被封禁' . $banMsg);
            }
        }

        $_SESSION['user_id'] = $user['id'];
        $this->loadCurrentUser($user['id']);

        return array('success' => true, 'message' => '登录成功');
    }

    public function logout() {
        unset($_SESSION['user_id']);
        $this->currentUser = null;
        return true;
    }

    public function generateVerificationCode($email, $type = 'register') {
        // 检查是否已注册
        if ($type == 'register' && $this->getByEmail($email)) {
            return array('success' => false, 'message' => '该邮箱已被注册');
        }
        if ($type == 'reset' && !$this->getByEmail($email)) {
            return array('success' => false, 'message' => '该邮箱未注册');
        }

        // 生成6位验证码
        $code = str_pad(mt_rand(0, 999999), 6, '0', STR_PAD_LEFT);
        $expires = date('Y-m-d H:i:s', time() + 600); // 10分钟有效期

        $this->db->insert('email_verifications', array(
            'email' => $email,
            'code' => $code,
            'type' => $type,
            'expires_at' => $expires
        ));

        // 发送邮件
        $mailer = new Mailer();
        if (!$mailer->sendVerificationCode($email, $code, $type)) {
            return array('success' => false, 'message' => '验证码发送失败，请稍后重试');
        }

        return array('success' => true, 'message' => '验证码已发送，请查收邮箱', 'code' => $code);
    }

    public function banUser($userId, $duration, $reason = '') {
        $user = $this->getById($userId);
        if (!$user || $user['is_admin'] == 1) {
            return array('success' => false, 'message' => '无法封禁该用户');
        }

        $banTime = date('Y-m-d H:i:s');
        if ($duration === 'forever') {
            $banUntil = null;
        } else {
            $banUntil = date('Y-m-d H:i:s', time() + $duration);
        }

        $result = $this->db->update('users', array(
            'status' => 0,
            'ban_time' => $banTime,
            'ban_until' => $banUntil,
            'ban_reason' => $reason
        ), 'id = ?', array($userId), 'i');

        if ($result === false) {
            return array('success' => false, 'message' => '封禁失败');
        }

        // 发送邮件通知
        $mailer = new Mailer();
        $mailer->sendBanNotice($user['email'], $user['username'], $banTime, $banUntil, $reason);

        return array('success' => true, 'message' => '封禁成功');
    }

    public function unbanUser($userId) {
        return $this->db->update('users', array(
            'status' => 1,
            'ban_time' => null,
            'ban_until' => null,
            'ban_reason' => null
        ), 'id = ?', array($userId), 'i') !== false;
    }

    public function getAllUsers($page = 1, $perPage = 20, $search = '') {
        $offset = ($page - 1) * $perPage;
        $where = '';
        $params = array();
        $types = '';

        if ($search) {
            $where = "WHERE username LIKE ? OR email LIKE ?";
            $params[] = "%$search%";
            $params[] = "%$search%";
            $types = 'ss';
        }

        $sql = "SELECT * FROM users $where ORDER BY created_at DESC LIMIT ? OFFSET ?";
        $params[] = $perPage;
        $params[] = $offset;
        $types .= 'ii';

        $users = $this->db->fetchAll($sql, $params, $types);

        $countSql = "SELECT COUNT(*) as total FROM users $where";
        $countParams = array_slice($params, 0, count($params) - 2);
        $countTypes = substr($types, 0, -2);
        $countRow = $this->db->fetchOne($countSql, $countParams, $countTypes);

        return array(
            'users' => $users,
            'total' => $countRow ? (int)$countRow['total'] : 0,
            'perPage' => $perPage,
            'page' => $page
        );
    }

    public function updateAvatar($userId, $avatarPath) {
        return $this->db->update('users', array('avatar' => $avatarPath), 'id = ?', array($userId), 'i') !== false;
    }

    public function updateProfile($userId, $data) {
        $allowed = array('password');
        $updateData = array();
        foreach ($allowed as $key) {
            if (isset($data[$key])) {
                if ($key == 'password') {
                    $updateData[$key] = $this->hashPassword($data[$key]);
                } else {
                    $updateData[$key] = $data[$key];
                }
            }
        }
        if (empty($updateData)) return true;
        return $this->db->update('users', $updateData, 'id = ?', array($userId), 'i') !== false;
    }

    private function hashPassword($password) {
        if (function_exists('password_hash')) {
            return password_hash($password, PASSWORD_BCRYPT);
        }
        return crypt($password, '$2y$10$' . substr(md5(uniqid()), 0, 22));
    }

    private function verifyPassword($password, $hash) {
        if (function_exists('password_verify')) {
            return password_verify($password, $hash);
        }
        // 兼容旧版本PHP
        if (substr($hash, 0, 4) == '$2y$') {
            $hash2a = '$2a$' . substr($hash, 4);
            return crypt($password, $hash2a) === $hash2a || crypt($password, $hash) === $hash;
        }
        return crypt($password, $hash) === $hash;
    }
}
?>
