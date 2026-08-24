<?php
// 共享底部
$config = require __DIR__ . '/../config.php';
$site_name = $config['site_name'];
?>
    </div>

    <!-- 回到顶部 -->
    <button class="back-to-top" id="backToTop" aria-label="回到顶部">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
            <path d="m18 15-6-6-6 6"/>
        </svg>
    </button>

    <footer class="site-footer">
        <div class="footer-content">
            <div class="footer-section">
                <div class="footer-logo">
                    <span class="logo-icon">🎬</span>
                    <span>Jay影视</span>
                </div>
                <p class="footer-desc">为您提供精彩的影视观看体验</p>
            </div>
            <div class="footer-section">
                <h4>分类</h4>
                <a href="category.php?type=movie">电影</a>
                <a href="category.php?type=tv">电视剧</a>
                <a href="category.php?type=variety">综艺</a>
                <a href="category.php?type=anime">动漫</a>
            </div>
            <div class="footer-section">
                <h4>帮助</h4>
                <a href="feedback.php">反馈建议</a>
                <a href="login.php">登录</a>
                <a href="register.php">注册</a>
            </div>
            <div class="footer-section">
                <h4>关于</h4>
                <p>本站所有资源均来源于网络，仅提供观看学习使用。</p>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?php echo date('Y'); ?> <?php echo $site_name; ?>. All rights reserved.</p>
        </div>
    </footer>

    <!-- 登录提示弹窗 -->
    <div class="login-required-modal" id="loginRequiredModal">
        <div class="login-required-content">
            <div class="login-required-icon">🔒</div>
            <h3>需要登录</h3>
            <p>需要登录才可以观看哦，如没有账号请注册！</p>
            <div class="login-required-actions">
                <a href="login.php" class="btn btn-primary">立即登录</a>
                <a href="register.php" class="btn btn-outline">注册账号</a>
                <button class="btn btn-text" id="loginRequiredClose">取消</button>
            </div>
        </div>
    </div>

<script src="assets/js/main.js"></script>
<?php if (isset($extra_js)): ?>
<?php echo $extra_js; ?>
<?php endif; ?>
</body>
</html>
