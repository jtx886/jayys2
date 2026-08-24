<?php
$page_title = '登录';
require_once __DIR__ . '/includes/header.php';

if (is_logged_in()) {
    $user = current_user();
    if ($user && is_user_banned($user)) {
        do_logout();
        redirect('login.php?banned=1');
    }
    $redirectUrl = isset($_SESSION['login_redirect']) ? $_SESSION['login_redirect'] : 'index.php';
    unset($_SESSION['login_redirect']);
    redirect($redirectUrl);
}

$error = '';
$success = '';
$bannedNotice = '';
$requiredNotice = '';
$usernameValue = '';

if (isset($_GET['banned']) && $_GET['banned'] == '1') {
    $bannedNotice = '您的账号已被封禁，请联系管理员了解详情。';
}
if (isset($_GET['required']) && $_GET['required'] == '1') {
    $requiredNotice = '请先登录以访问此页面。';
}
if (isset($_GET['logout']) && $_GET['logout'] == '1') {
    $success = '您已成功退出登录。';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error = '请求无效，请刷新页面后重试。';
    } else {
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']) ? true : false;

        if (empty($username) || empty($password)) {
            $error = '请填写用户名/邮箱和密码。';
            $usernameValue = clean($username);
        } else {
            $result = do_login($username, $password);
            if ($result['success']) {
                if ($remember) {
                    ini_set('session.gc_maxlifetime', 30 * 24 * 3600);
                    session_set_cookie_params(30 * 24 * 3600);
                }
                $redirectUrl = isset($_SESSION['login_redirect']) ? $_SESSION['login_redirect'] : 'index.php';
                unset($_SESSION['login_redirect']);
                redirect($redirectUrl);
            } else {
                $error = $result['message'];
                $usernameValue = clean($username);
            }
        }
    }
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = random_string(32);
}
?>

<link rel="stylesheet" href="assets/css/auth.css">

<section class="auth-section">
    <div class="auth-bg-overlay"></div>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-card-header">
                <div class="auth-logo">
                    <span class="logo-icon">🎬</span>
                    <span>Jay影视</span>
                </div>
                <h1 class="auth-title">欢迎回来</h1>
                <p class="auth-subtitle">登录您的账号，继续精彩旅程</p>
            </div>

            <?php if ($bannedNotice): ?>
                <div class="auth-alert auth-alert-danger">
                    <span class="alert-icon">⛔</span>
                    <span><?php echo clean($bannedNotice); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($requiredNotice): ?>
                <div class="auth-alert auth-alert-warning">
                    <span class="alert-icon">🔐</span>
                    <span><?php echo clean($requiredNotice); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="auth-alert auth-alert-success">
                    <span class="alert-icon">✅</span>
                    <span><?php echo clean($success); ?></span>
                </div>
            <?php endif; ?>

            <?php if ($error): ?>
                <div class="auth-alert auth-alert-danger">
                    <span class="alert-icon">⚠️</span>
                    <span><?php echo clean($error); ?></span>
                </div>
            <?php endif; ?>

            <form class="auth-form" method="post" action="login.php" id="loginForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo clean($_SESSION['csrf_token']); ?>">

                <div class="form-group">
                    <label class="form-label" for="username">用户名 / 邮箱</label>
                    <div class="input-with-icon">
                        <span class="input-icon">👤</span>
                        <input type="text" id="username" name="username" class="form-control"
                               placeholder="请输入用户名或邮箱" value="<?php echo $usernameValue; ?>" required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">密码</label>
                    <div class="input-with-icon">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="请输入密码" required autocomplete="current-password">
                        <button type="button" class="toggle-password" data-target="password" aria-label="显示/隐藏密码">
                            <span class="toggle-icon">👁️</span>
                        </button>
                    </div>
                </div>

                <div class="form-group auth-options">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="remember" id="remember">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-label">记住我 (30天)</span>
                    </label>
                    <a href="forgot-password.php" class="auth-link-muted">忘记密码？</a>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg auth-submit">
                    <span class="btn-text">登录</span>
                    <span class="btn-loader" hidden></span>
                </button>
            </form>

            <div class="auth-divider"><span>或</span></div>

            <div class="auth-footer-links">
                <p>还没有账号？ <a href="register.php" class="auth-link">立即注册</a></p>
            </div>
        </div>

        <div class="auth-hero">
            <div class="auth-hero-inner">
                <h2>发现精彩影视</h2>
                <p>登录后即可享受个性化推荐、观看历史、收藏夹等专属功能。</p>
                <ul class="auth-features">
                    <li>🎬 海量高清影片</li>
                    <li>📺 追剧历史记录</li>
                    <li>⭐ 一键收藏</li>
                    <li>🔔 更新实时提醒</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<script>
(function() {
    const form = document.getElementById('loginForm');
    const submitBtn = form.querySelector('.auth-submit');
    const btnText = submitBtn.querySelector('.btn-text');
    const btnLoader = submitBtn.querySelector('.btn-loader');

    form.addEventListener('submit', function(e) {
        if (!form.checkValidity()) {
            e.preventDefault();
            form.reportValidity();
            return;
        }
        btnText.hidden = true;
        btnLoader.hidden = false;
        submitBtn.disabled = true;
    });

    document.querySelectorAll('.toggle-password').forEach(btn => {
        btn.addEventListener('click', function() {
            const target = document.getElementById(btn.dataset.target);
            target.type = target.type === 'password' ? 'text' : 'password';
            btn.querySelector('.toggle-icon').textContent = target.type === 'password' ? '👁️' : '🙈';
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
