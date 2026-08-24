<?php
$page_title = '注册';
require_once __DIR__ . '/includes/header.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$success = '';
$emailValue = '';
$usernameValue = '';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = random_string(32);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'send_code') {
        header('Content-Type: application/json; charset=utf-8');
        $email = trim($_POST['email'] ?? '');
        if (!is_valid_email($email)) {
            echo json_encode(array('success' => false, 'message' => '邮箱格式不正确'));
            exit;
        }
        $existing = get_db()->fetchOne("SELECT id FROM users WHERE email = ?", array($email));
        if ($existing) {
            echo json_encode(array('success' => false, 'message' => '该邮箱已注册'));
            exit;
        }
        $rateKey = 'register_code_last_' . $email;
        if (isset($_SESSION[$rateKey]) && (time() - $_SESSION[$rateKey]) < 60) {
            echo json_encode(array('success' => false, 'message' => '请等待60秒后再试'));
            exit;
        }
        $result = send_register_code($email);
        if ($result['success']) {
            $_SESSION[$rateKey] = time();
        }
        echo json_encode($result);
        exit;
    }

    if ($action === 'register') {
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            $error = '请求无效，请刷新页面后重试。';
        } else {
            $email = trim($_POST['email'] ?? '');
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';
            $code = trim($_POST['code'] ?? '');
            $agree = isset($_POST['agree']) ? true : false;

            $emailValue = clean($email);
            $usernameValue = clean($username);

            if (!$agree) {
                $error = '请先阅读并同意服务条款';
            } elseif (!is_valid_email($email)) {
                $error = '邮箱格式不正确';
            } elseif (strlen($username) < 3 || strlen($username) > 20) {
                $error = '用户名长度需在3-20位之间';
            } elseif (strlen($password) < 6) {
                $error = '密码至少6位';
            } elseif ($password !== $confirmPassword) {
                $error = '两次输入的密码不一致';
            } else {
                $result = do_register($email, $username, $password, $code);
                if ($result['success']) {
                    $_SESSION['user_id'] = $result['user_id'];
                    $_SESSION['username'] = $username;
                    unset($_SESSION['csrf_token']);
                    header('Content-Type: application/json; charset=utf-8');
                    echo json_encode(array(
                        'success' => true,
                        'message' => '注册成功，正在跳转...',
                        'redirect' => 'index.php'
                    ));
                    exit;
                } else {
                    $error = $result['message'];
                }
            }

            if ($error) {
                header('Content-Type: application/json; charset=utf-8');
                echo json_encode(array('success' => false, 'message' => $error));
                exit;
            }
        }
    }
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
                <h1 class="auth-title">创建账号</h1>
                <p class="auth-subtitle">注册账号，开启您的影视之旅</p>
                <div class="auth-steps">
                    <div class="step active" data-step="1"><span>1</span>填写信息</div>
                    <div class="step-line"></div>
                    <div class="step" data-step="2"><span>2</span>完成注册</div>
                </div>
            </div>

            <div id="formError" class="auth-alert auth-alert-danger" <?php echo $error ? '' : 'style="display:none"'; ?>>
                <span class="alert-icon">⚠️</span>
                <span id="formErrorMsg"><?php echo clean($error); ?></span>
            </div>

            <div id="formSuccess" class="auth-alert auth-alert-success" style="display:none">
                <span class="alert-icon">✅</span>
                <span id="formSuccessMsg">注册成功，正在跳转...</span>
            </div>

            <form class="auth-form" id="registerForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo clean($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="register">

                <div class="form-group">
                    <label class="form-label" for="email">邮箱地址 <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <span class="input-icon">📧</span>
                        <input type="email" id="email" name="email" class="form-control"
                               placeholder="example@email.com" value="<?php echo $emailValue; ?>" required autocomplete="email">
                    </div>
                    <div class="form-hint">用于接收验证码与找回密码</div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="username">用户名 <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <span class="input-icon">👤</span>
                        <input type="text" id="username" name="username" class="form-control"
                               placeholder="3-20位字符" value="<?php echo $usernameValue; ?>" required autocomplete="username">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="code">邮箱验证码 <span class="required">*</span></label>
                    <div class="input-with-icon input-with-action">
                        <span class="input-icon">✉️</span>
                        <input type="text" id="code" name="code" class="form-control"
                               placeholder="请输入6位验证码" maxlength="6" required inputmode="numeric">
                        <button type="button" class="btn btn-outline btn-sm send-code-btn" id="sendCodeBtn">发送验证码</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">密码 <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="password" name="password" class="form-control"
                               placeholder="至少6位" required autocomplete="new-password">
                        <button type="button" class="toggle-password" data-target="password" aria-label="显示/隐藏密码">
                            <span class="toggle-icon">👁️</span>
                        </button>
                    </div>
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar"><span class="strength-fill"></span></div>
                        <span class="strength-label">请输入密码</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirm_password">确认密码 <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <span class="input-icon">🔑</span>
                        <input type="password" id="confirm_password" name="confirm_password" class="form-control"
                               placeholder="请再次输入密码" required autocomplete="new-password">
                        <button type="button" class="toggle-password" data-target="confirm_password" aria-label="显示/隐藏密码">
                            <span class="toggle-icon">👁️</span>
                        </button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="checkbox-wrapper">
                        <input type="checkbox" name="agree" id="agree">
                        <span class="checkbox-custom"></span>
                        <span class="checkbox-label">我已阅读并同意 <a href="terms.php" target="_blank">服务条款</a> 和 <a href="privacy.php" target="_blank">隐私政策</a></span>
                    </label>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg auth-submit" id="submitBtn">
                    <span class="btn-text">创建账号</span>
                    <span class="btn-loader" hidden></span>
                </button>
            </form>

            <div class="auth-divider"><span>或</span></div>

            <div class="auth-footer-links">
                <p>已有账号？ <a href="login.php" class="auth-link">立即登录</a></p>
            </div>
        </div>

        <div class="auth-hero">
            <div class="auth-hero-inner">
                <h2>加入 Jay影视</h2>
                <p>创建账号，享受专属观影体验与个性化服务。</p>
                <ul class="auth-features">
                    <li>🎬 无广告高清体验</li>
                    <li>📺 多设备同步观看</li>
                    <li>⭐ 收藏与历史记录</li>
                    <li>🔔 最新影视推送</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<script>
(function() {
    const form = document.getElementById('registerForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const confirmInput = document.getElementById('confirm_password');
    const agreeCheckbox = document.getElementById('agree');
    const submitBtn = document.getElementById('submitBtn');
    const sendCodeBtn = document.getElementById('sendCodeBtn');
    const errorBox = document.getElementById('formError');
    const errorMsg = document.getElementById('formErrorMsg');
    const successBox = document.getElementById('formSuccess');
    const successMsg = document.getElementById('formSuccessMsg');

    function showError(msg) {
        errorMsg.textContent = msg;
        errorBox.style.display = 'flex';
        successBox.style.display = 'none';
    }
    function showSuccess(msg) {
        successMsg.textContent = msg;
        successBox.style.display = 'flex';
        errorBox.style.display = 'none';
    }
    function hideAlerts() {
        errorBox.style.display = 'none';
        successBox.style.display = 'none';
    }

    function checkPasswordStrength(pwd) {
        let score = 0;
        if (pwd.length >= 6) score++;
        if (pwd.length >= 10) score++;
        if (/[A-Z]/.test(pwd) && /[a-z]/.test(pwd)) score++;
        if (/\d/.test(pwd)) score++;
        if (/[^A-Za-z0-9]/.test(pwd)) score++;
        return Math.min(score, 4);
    }

    const strengthBar = document.querySelector('#passwordStrength .strength-fill');
    const strengthLabel = document.querySelector('#passwordStrength .strength-label');
    const strengthColors = ['#ef4444', '#f59e0b', '#3b82f6', '#22c55e'];
    const strengthTexts = ['太弱', '一般', '良好', '强'];

    passwordInput.addEventListener('input', function() {
        const score = checkPasswordStrength(passwordInput.value);
        const percent = passwordInput.value ? (score / 4) * 100 : 0;
        strengthBar.style.width = percent + '%';
        strengthBar.style.background = passwordInput.value ? strengthColors[Math.max(0, score - 1)] : 'transparent';
        strengthLabel.textContent = passwordInput.value ? strengthTexts[Math.max(0, score - 1)] : '请输入密码';
        strengthLabel.style.color = passwordInput.value ? strengthColors[Math.max(0, score - 1)] : 'var(--muted)';
    });

    let countdownTimer = null;
    function startCountdown(seconds) {
        sendCodeBtn.disabled = true;
        sendCodeBtn.classList.add('disabled');
        const origText = '重新发送';
        let remain = seconds;
        sendCodeBtn.textContent = remain + 's';
        countdownTimer = setInterval(function() {
            remain--;
            if (remain <= 0) {
                clearInterval(countdownTimer);
                sendCodeBtn.disabled = false;
                sendCodeBtn.classList.remove('disabled');
                sendCodeBtn.textContent = origText;
            } else {
                sendCodeBtn.textContent = remain + 's';
            }
        }, 1000);
    }

    sendCodeBtn.addEventListener('click', function() {
        const email = emailInput.value.trim();
        hideAlerts();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError('请输入正确的邮箱地址');
            return;
        }
        sendCodeBtn.disabled = true;
        sendCodeBtn.textContent = '发送中...';
        const formData = new FormData();
        formData.append('action', 'send_code');
        formData.append('email', email);
        fetch('register.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showSuccess(data.message);
                startCountdown(60);
            } else {
                showError(data.message);
                sendCodeBtn.disabled = false;
                sendCodeBtn.textContent = '发送验证码';
            }
        })
        .catch(function() {
            showError('网络错误，请稍后重试');
            sendCodeBtn.disabled = false;
            sendCodeBtn.textContent = '发送验证码';
        });
    });

    document.querySelectorAll('.toggle-password').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const target = document.getElementById(btn.dataset.target);
            target.type = target.type === 'password' ? 'text' : 'password';
            btn.querySelector('.toggle-icon').textContent = target.type === 'password' ? '👁️' : '🙈';
        });
    });

    form.addEventListener('submit', function(e) {
        e.preventDefault();
        hideAlerts();

        const email = emailInput.value.trim();
        const username = document.getElementById('username').value.trim();
        const password = passwordInput.value;
        const confirmPassword = confirmInput.value;
        const code = document.getElementById('code').value.trim();

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError('请输入正确的邮箱地址');
            return;
        }
        if (username.length < 3 || username.length > 20) {
            showError('用户名长度需在3-20位之间');
            return;
        }
        if (password.length < 6) {
            showError('密码至少6位');
            return;
        }
        if (password !== confirmPassword) {
            showError('两次输入的密码不一致');
            return;
        }
        if (!code || code.length !== 6) {
            showError('请输入6位验证码');
            return;
        }
        if (!agreeCheckbox.checked) {
            showError('请先阅读并同意服务条款');
            return;
        }

        const btnText = submitBtn.querySelector('.btn-text');
        const btnLoader = submitBtn.querySelector('.btn-loader');
        btnText.hidden = true;
        btnLoader.hidden = false;
        submitBtn.disabled = true;

        const formData = new FormData(form);
        fetch('register.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showSuccess(data.message);
                setTimeout(function() {
                    window.location.href = data.redirect || 'index.php';
                }, 1200);
            } else {
                showError(data.message);
                btnText.hidden = false;
                btnLoader.hidden = true;
                submitBtn.disabled = false;
            }
        })
        .catch(function() {
            showError('网络错误，请稍后重试');
            btnText.hidden = false;
            btnLoader.hidden = true;
            submitBtn.disabled = false;
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
