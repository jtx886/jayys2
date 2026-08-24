<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (is_admin()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? $_POST['password'] : '';

    if (empty($username) || empty($password)) {
        $error = '请填写完整的登录信息';
    } else {
        $result = do_admin_login($username, $password);
        if ($result['success']) {
            redirect('dashboard.php');
        } else {
            $error = $result['message'];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>管理员登录 - Jay影视</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary: #01b4e4;
            --secondary: #0d253f;
            --accent: #1f80e0;
            --background: #04070d;
            --text: #ffffff;
            --muted: #9aa0a6;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--background);
            position: relative;
            overflow: hidden;
        }

        body::before {
            content: "";
            position: absolute;
            inset: 0;
            background:
                radial-gradient(ellipse at 20% 50%, rgba(1, 180, 228, 0.15) 0%, transparent 50%),
                radial-gradient(ellipse at 80% 20%, rgba(31, 128, 224, 0.1) 0%, transparent 40%),
                radial-gradient(ellipse at 50% 80%, rgba(13, 37, 63, 0.8) 0%, transparent 60%);
            pointer-events: none;
        }

        .bg-grid {
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(1, 180, 228, 0.03) 1px, transparent 1px),
                linear-gradient(90deg, rgba(1, 180, 228, 0.03) 1px, transparent 1px);
            background-size: 40px 40px;
            pointer-events: none;
        }

        .login-container {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 440px;
            padding: 20px;
        }

        .login-card {
            background: rgba(13, 37, 63, 0.8);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, 0.5);
        }

        .login-brand {
            text-align: center;
            margin-bottom: 36px;
        }

        .login-logo {
            width: 72px;
            height: 72px;
            margin: 0 auto 16px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            box-shadow: 0 12px 32px rgba(1, 180, 228, 0.4);
            position: relative;
        }

        .login-logo::after {
            content: "";
            position: absolute;
            inset: -4px;
            border-radius: 22px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            filter: blur(16px);
            opacity: 0.3;
            z-index: -1;
        }

        .login-title {
            font-size: 1.5rem;
            font-weight: 800;
            color: var(--text);
            margin-bottom: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .dev-tag {
            display: inline-block;
            padding: 3px 10px;
            background: linear-gradient(135deg, #ef4444, #dc2626);
            color: white;
            font-size: 0.75rem;
            font-weight: 700;
            border-radius: 6px;
            letter-spacing: 0.5px;
        }

        .login-subtitle {
            font-size: 0.9rem;
            color: var(--muted);
        }

        .login-form .form-group {
            margin-bottom: 20px;
        }

        .login-form .form-label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 0.85rem;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .login-form .form-control {
            width: 100%;
            padding: 14px 18px;
            background: rgba(4, 7, 13, 0.6);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: var(--text);
            font-size: 0.95rem;
            transition: all 0.2s ease;
            outline: none;
        }

        .login-form .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(1, 180, 228, 0.15);
            background: rgba(4, 7, 13, 0.9);
        }

        .login-form .form-control::placeholder {
            color: rgba(154, 160, 166, 0.5);
        }

        .login-btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: var(--background);
            border: none;
            border-radius: 12px;
            font-size: 1rem;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s ease;
            margin-top: 8px;
            box-shadow: 0 8px 24px rgba(1, 180, 228, 0.3);
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 32px rgba(1, 180, 228, 0.4);
        }

        .login-btn:active {
            transform: translateY(0);
        }

        .login-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #ef4444;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 0.85rem;
            color: var(--muted);
        }

        .login-footer a {
            color: var(--primary);
            text-decoration: none;
        }

        .login-footer a:hover {
            text-decoration: underline;
        }

        .floating-shape {
            position: absolute;
            border-radius: 50%;
            filter: blur(60px);
            opacity: 0.4;
            pointer-events: none;
        }

        .floating-shape.one {
            width: 300px;
            height: 300px;
            background: var(--primary);
            top: -100px;
            right: -100px;
            animation: float 8s ease-in-out infinite;
        }

        .floating-shape.two {
            width: 250px;
            height: 250px;
            background: var(--accent);
            bottom: -80px;
            left: -80px;
            animation: float 10s ease-in-out infinite reverse;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-30px); }
        }

        @media (max-width: 480px) {
            .login-card {
                padding: 36px 24px;
                border-radius: 20px;
            }
        }
    </style>
</head>
<body>
    <div class="floating-shape one"></div>
    <div class="floating-shape two"></div>
    <div class="bg-grid"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-brand">
                <div class="login-logo">🎬</div>
                <h1 class="login-title">Jay影视管理<span class="dev-tag">开发者</span></h1>
                <p class="login-subtitle">请登录以访问管理后台</p>
            </div>

            <?php if ($error): ?>
            <div class="login-error">
                ⚠️ <?php echo clean($error); ?>
            </div>
            <?php endif; ?>

            <form method="POST" class="login-form">
                <div class="form-group">
                    <label class="form-label" for="username">管理员账号</label>
                    <input type="text" id="username" name="username" class="form-control" placeholder="请输入管理员账号" required autocomplete="username">
                </div>
                <div class="form-group">
                    <label class="form-label" for="password">密码</label>
                    <input type="password" id="password" name="password" class="form-control" placeholder="请输入密码" required autocomplete="current-password">
                </div>
                <button type="submit" class="login-btn">登 录</button>
            </form>

            <div class="login-footer">
                <a href="../index.php">← 返回前台</a>
            </div>
        </div>
    </div>
</body>
</html>
