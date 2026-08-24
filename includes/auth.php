<?php
// 认证系统

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Access Denied');
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 登录
function do_login($username, $password) {
    $db = get_db();
    $user = $db->fetchOne(
        "SELECT * FROM users WHERE username = ? OR email = ?",
        array($username, $username)
    );
    
    if (!$user) {
        return array('success' => false, 'message' => '用户不存在');
    }
    
    if (!verify_password($password, $user['password'])) {
        return array('success' => false, 'message' => '密码错误');
    }
    
    if ($user['status'] === 'banned') {
        if ($user['ban_until'] && strtotime($user['ban_until']) > time()) {
            return array(
                'success' => false,
                'message' => '您的账号已被封禁至 ' . $user['ban_until'] . '，原因：' . $user['ban_reason']
            );
        }
        $db->update('users', array('status' => 'normal', 'ban_until' => null, 'ban_reason' => ''), 'id = ?', array($user['id']));
    }
    
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['username'] = $user['username'];
    
    return array('success' => true, 'user' => $user);
}

// 注册
function do_register($email, $username, $password, $code) {
    $db = get_db();
    
    if (!is_valid_email($email)) {
        return array('success' => false, 'message' => '邮箱格式不正确');
    }
    
    if (strlen($username) < 3 || strlen($username) > 20) {
        return array('success' => false, 'message' => '用户名长度需在3-20位之间');
    }
    
    if (strlen($password) < 6) {
        return array('success' => false, 'message' => '密码至少6位');
    }
    
    $existing = $db->fetchOne("SELECT id FROM users WHERE email = ?", array($email));
    if ($existing) {
        return array('success' => false, 'message' => '该邮箱已注册');
    }
    
    $existing = $db->fetchOne("SELECT id FROM users WHERE username = ?", array($username));
    if ($existing) {
        return array('success' => false, 'message' => '该用户名已被使用');
    }
    
    $verCode = $db->fetchOne(
        "SELECT * FROM verification_codes WHERE email = ? AND code = ? AND type = 'register' AND expires_at > datetime('now')",
        array($email, $code)
    );
    
    if (!$verCode) {
        return array('success' => false, 'message' => '验证码错误或已过期');
    }
    
    $userId = $db->insert('users', array(
        'email' => $email,
        'username' => $username,
        'password' => hash_password($password),
        'avatar' => generate_default_avatar($username)
    ));
    
    $db->delete('verification_codes', 'id = ?', array($verCode['id']));
    
    return array('success' => true, 'user_id' => $userId);
}

// 发送注册验证码
function send_register_code($email) {
    $db = get_db();
    
    if (!is_valid_email($email)) {
        return array('success' => false, 'message' => '邮箱格式不正确');
    }
    
    $existing = $db->fetchOne("SELECT id FROM users WHERE email = ?", array($email));
    if ($existing) {
        return array('success' => false, 'message' => '该邮箱已注册');
    }
    
    $code = generate_code(6);
    
    $db->delete('verification_codes', 'email = ? AND type = ?', array($email, 'register'));
    
    $db->insert('verification_codes', array(
        'email' => $email,
        'code' => $code,
        'type' => 'register',
        'expires_at' => date('Y-m-d H:i:s', time() + 600)
    ));
    
    $subject = 'Jay影视 - 注册验证码';
    $mailer = new Mailer();
    $result = $mailer->send_template($email, $subject, 'verification', array('code' => $code));
    
    if (!$result) {
        return array('success' => false, 'message' => '邮件发送失败，请稍后重试');
    }
    
    return array('success' => true, 'message' => '验证码已发送，请查收邮件');
}

// 发送找回密码验证码
function send_reset_code($email) {
    $db = get_db();
    
    if (!is_valid_email($email)) {
        return array('success' => false, 'message' => '邮箱格式不正确');
    }
    
    $existing = $db->fetchOne("SELECT id FROM users WHERE email = ?", array($email));
    if (!$existing) {
        return array('success' => false, 'message' => '该邮箱未注册');
    }
    
    $code = generate_code(6);
    
    $db->delete('verification_codes', 'email = ? AND type = ?', array($email, 'reset'));
    
    $db->insert('verification_codes', array(
        'email' => $email,
        'code' => $code,
        'type' => 'reset',
        'expires_at' => date('Y-m-d H:i:s', time() + 600)
    ));
    
    $subject = 'Jay影视 - 找回密码验证码';
    $mailer = new Mailer();
    $result = $mailer->send_template($email, $subject, 'verification', array('code' => $code));
    
    if (!$result) {
        return array('success' => false, 'message' => '邮件发送失败，请稍后重试');
    }
    
    return array('success' => true, 'message' => '验证码已发送，请查收邮件');
}

// 重置密码
function do_reset_password($email, $code, $newPassword) {
    $db = get_db();
    
    $verCode = $db->fetchOne(
        "SELECT * FROM verification_codes WHERE email = ? AND code = ? AND type = 'reset' AND expires_at > datetime('now')",
        array($email, $code)
    );
    
    if (!$verCode) {
        return array('success' => false, 'message' => '验证码错误或已过期');
    }
    
    if (strlen($newPassword) < 6) {
        return array('success' => false, 'message' => '密码至少6位');
    }
    
    $db->update('users', array('password' => hash_password($newPassword)), 'email = ?', array($email));
    $db->delete('verification_codes', 'id = ?', array($verCode['id']));
    
    return array('success' => true, 'message' => '密码重置成功');
}

// 登出
function do_logout() {
    $_SESSION = array();
    session_destroy();
}

// 验证邮箱格式
function is_valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// 生成默认头像
function generate_default_avatar($username) {
    $colors = array('01b4e4', '1f80e0', 'e401b4', 'b4e401', 'e4b401', 'e40101', '01e4b4', '801fe4');
    $color = $colors[ord($username[0]) % count($colors)];
    $letter = strtoupper($username[0]);
    return 'data:image/svg+xml;base64,' . base64_encode(
        '<svg xmlns="http://www.w3.org/2000/svg" width="100" height="100"><circle cx="50" cy="50" r="50" fill="#' . $color . '"/><text x="50" y="65" font-family="Arial" font-size="40" fill="white" text-anchor="middle">' . $letter . '</text></svg>'
    );
}

// 管理员登录
function do_admin_login($username, $password) {
    $config = get_config();
    
    if ($username !== $config['admin_username']) {
        return array('success' => false, 'message' => '管理员账号错误');
    }
    
    if ($password !== $config['admin_password']) {
        return array('success' => false, 'message' => '管理员密码错误');
    }
    
    $_SESSION['is_admin'] = true;
    $_SESSION['admin_name'] = $username;
    
    return array('success' => true);
}

// 检查是否管理员
function is_admin() {
    return isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true;
}

// 要求登录
function require_login() {
    if (!is_logged_in()) {
        if (!isset($_SESSION['login_redirect'])) {
            $currentUrl = (isset($_SERVER['HTTPS']) ? 'https' : 'http') . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'];
            $_SESSION['login_redirect'] = $currentUrl;
        }
        redirect('login.php?required=1');
    }
    
    $user = current_user();
    if ($user && is_user_banned($user)) {
        do_logout();
        redirect('login.php?banned=1');
    }
}

// 要求管理员
function require_admin() {
    if (!is_admin()) {
        redirect('index.php');
    }
}
