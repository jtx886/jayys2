<?php
$page_title = '找回密码';
require_once __DIR__ . '/includes/header.php';

if (is_logged_in()) {
    redirect('index.php');
}

$error = '';
$success = '';

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
        if (!$existing) {
            echo json_encode(array('success' => false, 'message' => '该邮箱未注册'));
            exit;
        }
        $rateKey = 'reset_code_last_' . $email;
        if (isset($_SESSION[$rateKey]) && (time() - $_SESSION[$rateKey]) < 60) {
            echo json_encode(array('success' => false, 'message' => '请等待60秒后再试'));
            exit;
        }
        $result = send_reset_code($email);
        if ($result['success']) {
            $_SESSION[$rateKey] = time();
            $_SESSION['reset_email'] = $email;
        }
        echo json_encode($result);
        exit;
    }

    if ($action === 'reset_password') {
        header('Content-Type: application/json; charset=utf-8');
        $email = trim($_POST['email'] ?? '');
        $code = trim($_POST['code'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if (!is_valid_email($email)) {
            echo json_encode(array('success' => false, 'message' => '邮箱格式不正确'));
            exit;
        }
        if (strlen($password) < 6) {
            echo json_encode(array('success' => false, 'message' => '密码至少6位'));
            exit;
        }
        if ($password !== $confirmPassword) {
            echo json_encode(array('success' => false, 'message' => '两次输入的密码不一致'));
            exit;
        }
        if (empty($code) || strlen($code) !== 6) {
            echo json_encode(array('success' => false, 'message' => '请输入6位验证码'));
            exit;
        }

        $result = do_reset_password($email, $code, $password);
        if ($result['success']) {
            unset($_SESSION['reset_email']);
            echo json_encode(array(
                'success' => true,
                'message' => '密码重置成功，正在跳转至登录页...',
                'redirect' => 'login.php'
            ));
            exit;
        } else {
            echo json_encode(array('success' => false, 'message' => $result['message']));
            exit;
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
                <h1 class="auth-title">找回密码</h1>
                <p class="auth-subtitle">通过邮箱验证码重置您的账号密码</p>
                <div class="auth-steps">
                    <div class="step active" data-step="1"><span>1</span>发送验证码</div>
                    <div class="step-line"></div>
                    <div class="step" data-step="2"><span>2</span>重置密码</div>
                </div>
            </div>

            <div id="formError" class="auth-alert auth-alert-danger" style="display:none">
                <span class="alert-icon">⚠️</span>
                <span id="formErrorMsg"></span>
            </div>

            <div id="formSuccess" class="auth-alert auth-alert-success" style="display:none">
                <span class="alert-icon">✅</span>
                <span id="formSuccessMsg"></span>
            </div>

            <form class="auth-form" id="requestForm" novalidate>
                <input type="hidden" name="csrf_token" value="<?php echo clean($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="send_code">

                <div class="form-group">
                    <label class="form-label" for="reqEmail">注册时的邮箱 <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <span class="input-icon">📧</span>
                        <input type="email" id="reqEmail" name="email" class="form-control"
                               placeholder="example@email.com" required autocomplete="email">
                    </div>
                    <div class="form-hint">请输入您注册时使用的邮箱，我们将发送验证码到该邮箱</div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg auth-submit" id="sendCodeSubmit">
                    <span class="btn-text">发送验证码</span>
                    <span class="btn-loader" hidden></span>
                </button>
            </form>

            <form class="auth-form" id="resetForm" novalidate style="display:none">
                <input type="hidden" name="csrf_token" value="<?php echo clean($_SESSION['csrf_token']); ?>">
                <input type="hidden" name="action" value="reset_password">

                <div class="form-group">
                    <label class="form-label">邮箱地址</label>
                    <div class="input-with-icon">
                        <span class="input-icon">📧</span>
                        <input type="email" id="resetEmail" name="email" class="form-control"
                               placeholder="example@email.com" required autocomplete="email" readonly>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="code">邮箱验证码 <span class="required">*</span></label>
                    <div class="input-with-icon input-with-action">
                        <span class="input-icon">✉️</span>
                        <input type="text" id="code" name="code" class="form-control"
                               placeholder="请输入6位验证码" maxlength="6" required inputmode="numeric">
                        <button type="button" class="btn btn-outline btn-sm send-code-btn" id="resendCodeBtn">重新发送</button>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="newPassword">新密码 <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <span class="input-icon">🔒</span>
                        <input type="password" id="newPassword" name="password" class="form-control"
                               placeholder="至少6位" required autocomplete="new-password">
                        <button type="button" class="toggle-password" data-target="newPassword" aria-label="显示/隐藏密码">
                            <span class="toggle-icon">👁️</span>
                        </button>
                    </div>
                    <div class="password-strength" id="passwordStrength">
                        <div class="strength-bar"><span class="strength-fill"></span></div>
                        <span class="strength-label">请输入密码</span>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="confirmPassword">确认新密码 <span class="required">*</span></label>
                    <div class="input-with-icon">
                        <span class="input-icon">🔑</span>
                        <input type="password" id="confirmPassword" name="confirm_password" class="form-control"
                               placeholder="请再次输入新密码" required autocomplete="new-password">
                        <button type="button" class="toggle-password" data-target="confirmPassword" aria-label="显示/隐藏密码">
                            <span class="toggle-icon">👁️</span>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg auth-submit" id="resetSubmitBtn">
                    <span class="btn-text">重置密码</span>
                    <span class="btn-loader" hidden></span>
                </button>
            </form>

            <div class="auth-divider"><span>或</span></div>

            <div class="auth-footer-links">
                <p><a href="login.php" class="auth-link">← 返回登录</a></p>
                <p style="margin-top: 8px;">还没有账号？ <a href="register.php" class="auth-link">立即注册</a></p>
            </div>
        </div>

        <div class="auth-hero">
            <div class="auth-hero-inner">
                <h2>账号安全</h2>
                <p>通过邮箱验证重置密码，保障您的账号安全。</p>
                <ul class="auth-features">
                    <li>🔒 双重邮箱验证</li>
                    <li>⏱️ 验证码10分钟内有效</li>
                    <li>🛡️ 全程加密保护</li>
                    <li>📧 一键找回密码</li>
                </ul>
            </div>
        </div>
    </div>
</section>

<script>
(function() {
    const requestForm = document.getElementById('requestForm');
    const resetForm = document.getElementById('resetForm');
    const reqEmail = document.getElementById('reqEmail');
    const resetEmail = document.getElementById('resetEmail');
    const codeInput = document.getElementById('code');
    const newPassword = document.getElementById('newPassword');
    const confirmPassword = document.getElementById('confirmPassword');
    const sendCodeSubmit = document.getElementById('sendCodeSubmit');
    const resetSubmitBtn = document.getElementById('resetSubmitBtn');
    const resendCodeBtn = document.getElementById('resendCodeBtn');
    const errorBox = document.getElementById('formError');
    const errorMsg = document.getElementById('formErrorMsg');
    const successBox = document.getElementById('formSuccess');
    const successMsg = document.getElementById('formSuccessMsg');
    const step1 = document.querySelector('.step[data-step="1"]');
    const step2 = document.querySelector('.step[data-step="2"]');

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

    newPassword.addEventListener('input', function() {
        const score = checkPasswordStrength(newPassword.value);
        const percent = newPassword.value ? (score / 4) * 100 : 0;
        strengthBar.style.width = percent + '%';
        strengthBar.style.background = newPassword.value ? strengthColors[Math.max(0, score - 1)] : 'transparent';
        strengthLabel.textContent = newPassword.value ? strengthTexts[Math.max(0, score - 1)] : '请输入密码';
        strengthLabel.style.color = newPassword.value ? strengthColors[Math.max(0, score - 1)] : 'var(--muted)';
    });

    let countdownTimer = null;
    function startCountdown(seconds) {
        resendCodeBtn.disabled = true;
        resendCodeBtn.classList.add('disabled');
        const origText = '重新发送';
        let remain = seconds;
        resendCodeBtn.textContent = remain + 's';
        countdownTimer = setInterval(function() {
            remain--;
            if (remain <= 0) {
                clearInterval(countdownTimer);
                resendCodeBtn.disabled = false;
                resendCodeBtn.classList.remove('disabled');
                resendCodeBtn.textContent = origText;
            } else {
                resendCodeBtn.textContent = remain + 's';
            }
        }, 1000);
    }

    function sendCodeRequest(email) {
        hideAlerts();
        const formData = new FormData();
        formData.append('email', email);
        formData.append('type', 'reset');
        return fetch('api.php?action=send-code', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        }).then(function(r) { return r.json().catch(function() { return { success: false, message: '服务器响应异常' }; }); });
    }

    function transitionToReset(email) {
        resetEmail.value = email;
        requestForm.style.display = 'none';
        resetForm.style.display = 'block';
        step1.classList.remove('active');
        step1.classList.add('done');
        step2.classList.add('active');
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    reqEmail.addEventListener('keydown', function() {
        hideAlerts();
    });

    requestForm.addEventListener('submit', function(e) {
        e.preventDefault();
        hideAlerts();
        const email = reqEmail.value.trim();
        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError('请输入正确的邮箱地址');
            return;
        }
        const btnText = sendCodeSubmit.querySelector('.btn-text');
        const btnLoader = sendCodeSubmit.querySelector('.btn-loader');
        btnText.hidden = true;
        btnLoader.hidden = false;
        sendCodeSubmit.disabled = true;

        sendCodeRequest(email)
            .then(function(data) {
                if (data.success) {
                    showSuccess(data.message);
                    startCountdown(60);
                    setTimeout(function() {
                        transitionToReset(email);
                    }, 800);
                } else {
                    showError(data.message);
                    btnText.hidden = false;
                    btnLoader.hidden = true;
                    sendCodeSubmit.disabled = false;
                }
            })
            .catch(function() {
                showError('网络错误，请稍后重试');
                btnText.hidden = false;
                btnLoader.hidden = true;
                sendCodeSubmit.disabled = false;
            });
    });

    resendCodeBtn.addEventListener('click', function() {
        const email = resetEmail.value.trim();
        if (!email) {
            showError('请输入邮箱地址');
            return;
        }
        hideAlerts();
        resendCodeBtn.disabled = true;
        resendCodeBtn.classList.add('disabled');
        resendCodeBtn.textContent = '发送中...';
        sendCodeRequest(email)
            .then(function(data) {
                if (data.success) {
                    showSuccess(data.message);
                    startCountdown(60);
                } else {
                    showError(data.message);
                    resendCodeBtn.disabled = false;
                    resendCodeBtn.classList.remove('disabled');
                    resendCodeBtn.textContent = '重新发送';
                }
            })
            .catch(function() {
                showError('网络错误，请稍后重试');
                resendCodeBtn.disabled = false;
                resendCodeBtn.classList.remove('disabled');
                resendCodeBtn.textContent = '重新发送';
            });
    });

    document.querySelectorAll('.toggle-password').forEach(function(btn) {
        btn.addEventListener('click', function() {
            const target = document.getElementById(btn.dataset.target);
            target.type = target.type === 'password' ? 'text' : 'password';
            btn.querySelector('.toggle-icon').textContent = target.type === 'password' ? '👁️' : '🙈';
        });
    });

    resetForm.addEventListener('submit', function(e) {
        e.preventDefault();
        hideAlerts();

        const email = resetEmail.value.trim();
        const pwd = newPassword.value;
        const confirm = confirmPassword.value;
        const code = codeInput.value.trim();

        if (!email || !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) {
            showError('邮箱格式不正确');
            return;
        }
        if (!code || code.length !== 6) {
            showError('请输入6位验证码');
            return;
        }
        if (pwd.length < 6) {
            showError('密码至少6位');
            return;
        }
        if (pwd !== confirm) {
            showError('两次输入的密码不一致');
            return;
        }

        const btnText = resetSubmitBtn.querySelector('.btn-text');
        const btnLoader = resetSubmitBtn.querySelector('.btn-loader');
        btnText.hidden = true;
        btnLoader.hidden = false;
        resetSubmitBtn.disabled = true;

        const formData = new FormData(resetForm);
        fetch('forgot-password.php', {
            method: 'POST',
            body: formData,
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json().catch(function() { return { success: false, message: '服务器响应异常' }; }); })
        .then(function(data) {
            if (data.success) {
                showSuccess(data.message);
                setTimeout(function() {
                    window.location.href = data.redirect || 'login.php';
                }, 1500);
            } else {
                showError(data.message);
                btnText.hidden = false;
                btnLoader.hidden = true;
                resetSubmitBtn.disabled = false;
            }
        })
        .catch(function() {
            showError('网络错误，请稍后重试');
            btnText.hidden = false;
            btnLoader.hidden = true;
            resetSubmitBtn.disabled = false;
        });
    });
})();
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
