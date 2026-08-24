<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_admin();

$db = get_db();
$config = get_config();
$message = '';
$messageType = '';

$currentTheme = get_theme();
$defaults = $config['theme'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    if ($action === 'save') {
        $primary = trim($_POST['primary']);
        $secondary = trim($_POST['secondary']);
        $accent = trim($_POST['accent']);
        $background = trim($_POST['background']);
        $text = trim($_POST['text']);
        $muted = trim($_POST['muted']);

        $db->query("DELETE FROM themes");
        $db->insert('themes', array(
            'primary_color' => $primary,
            'secondary_color' => $secondary,
            'accent_color' => $accent,
            'background_color' => $background,
            'text_color' => $text,
            'muted_color' => $muted
        ));

        $message = '主题设置已保存';
        $messageType = 'success';
        $currentTheme = array(
            'primary' => $primary,
            'secondary' => $secondary,
            'accent' => $accent,
            'background' => $background,
            'text' => $text,
            'muted' => $muted
        );
    } elseif ($action === 'reset') {
        $db->query("DELETE FROM themes");
        $db->insert('themes', array(
            'primary_color' => $defaults['primary'],
            'secondary_color' => $defaults['secondary'],
            'accent_color' => $defaults['accent'],
            'background_color' => $defaults['background'],
            'text_color' => $defaults['text'],
            'muted_color' => $defaults['muted']
        ));
        $message = '已重置为默认主题';
        $messageType = 'success';
        $currentTheme = $defaults;
    }
}

$page_title = '主题定制';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($page_title); ?> - Jay影视管理</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <style>
        :root {
            --primary: <?php echo $currentTheme['primary']; ?>;
            --secondary: <?php echo $currentTheme['secondary']; ?>;
            --accent: <?php echo $currentTheme['accent']; ?>;
            --background: <?php echo $currentTheme['background']; ?>;
            --text: <?php echo $currentTheme['text']; ?>;
            --muted: <?php echo $currentTheme['muted']; ?>;
        }
        .admin-layout { display: grid; grid-template-columns: 260px 1fr; min-height: 100vh; }
        .admin-sidebar { background: var(--secondary); border-right: 1px solid rgba(255,255,255,0.1); padding: 24px 0; position: fixed; top: 0; left: 0; bottom: 0; width: 260px; overflow-y: auto; z-index: 50; }
        .admin-sidebar-header { padding: 0 24px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 16px; }
        .admin-sidebar-brand { display: flex; align-items: center; gap: 12px; font-size: 1.2rem; font-weight: 800; }
        .admin-sidebar-brand .brand-icon { width: 36px; height: 36px; background: linear-gradient(135deg, var(--primary), var(--accent)); border-radius: 10px; display: flex; align-items: center; justify-content: center; color: white; }
        .dev-tag-sm { display: inline-block; padding: 2px 8px; background: linear-gradient(135deg, #ef4444, #dc2626); color: white; font-size: 0.65rem; font-weight: 700; border-radius: 5px; margin-left: 4px; }
        .admin-sidebar-nav { padding: 0 16px; }
        .admin-nav-section { margin-bottom: 20px; }
        .admin-nav-label { padding: 8px 12px; font-size: 0.7rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; color: var(--muted); }
        .admin-nav-item { display: flex; align-items: center; gap: 12px; padding: 10px 16px; border-radius: 8px; color: #b3c4d4; font-size: 0.9rem; font-weight: 500; cursor: pointer; transition: all 0.15s ease; margin-bottom: 4px; text-decoration: none; }
        .admin-nav-item:hover { background: rgba(255,255,255,0.05); color: var(--text); }
        .admin-nav-item.active { background: rgba(1,180,228,0.15); color: var(--primary); }
        .admin-nav-item .nav-icon { width: 20px; text-align: center; }
        .admin-main { margin-left: 260px; padding: 24px; }
        .admin-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 32px; }
        .admin-header h1 { font-size: 1.5rem; }
        .admin-user { display: flex; align-items: center; gap: 10px; padding: 6px 14px 6px 6px; background: var(--secondary); border-radius: 20px; cursor: pointer; }
        .admin-user .avatar { width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, var(--primary), var(--accent)); display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 0.85rem; }
        .admin-user-name { font-size: 0.85rem; font-weight: 500; }
        .message-toast { padding: 12px 20px; border-radius: 10px; margin-bottom: 20px; font-weight: 500; display: flex; align-items: center; gap: 10px; }
        .message-toast.success { background: rgba(34,197,94,0.15); border: 1px solid rgba(34,197,94,0.3); color: #22c55e; }
        .message-toast.error { background: rgba(239,68,68,0.15); border: 1px solid rgba(239,68,68,0.3); color: #ef4444; }
        .content-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
        .panel { background: var(--secondary); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; overflow: hidden; }
        .panel-header { display: flex; align-items: center; justify-content: space-between; padding: 20px 24px; border-bottom: 1px solid rgba(255,255,255,0.1); }
        .panel-title { font-size: 1rem; font-weight: 600; }
        .panel-body { padding: 24px; }
        .color-row { display: flex; align-items: center; gap: 16px; padding: 16px; background: rgba(4,7,13,0.5); border-radius: 12px; margin-bottom: 12px; }
        .color-picker-wrapper { position: relative; width: 48px; height: 48px; border-radius: 12px; overflow: hidden; cursor: pointer; border: 2px solid rgba(255,255,255,0.15); flex-shrink: 0; }
        .color-picker-wrapper input[type="color"] { position: absolute; inset: 0; width: 100%; height: 100%; cursor: pointer; opacity: 0; }
        .color-preview { width: 100%; height: 100%; border-radius: 10px; }
        .color-info { flex: 1; }
        .color-label { font-weight: 600; font-size: 0.9rem; margin-bottom: 4px; }
        .color-desc { color: var(--muted); font-size: 0.8rem; }
        .color-hex { display: flex; align-items: center; gap: 8px; }
        .color-hex input { width: 110px; padding: 6px 10px; background: rgba(0,0,0,0.3); border: 1px solid rgba(255,255,255,0.1); border-radius: 6px; color: var(--text); font-family: monospace; font-size: 0.85rem; outline: none; }
        .color-hex input:focus { border-color: var(--primary); }
        .btn-primary { padding: 12px 24px; background: linear-gradient(135deg, var(--primary), var(--accent)); color: var(--background); border: none; border-radius: 10px; font-weight: 700; cursor: pointer; transition: all 0.15s ease; }
        .btn-primary:hover { transform: translateY(-2px); }
        .btn-secondary { padding: 12px 24px; background: rgba(255,255,255,0.1); color: var(--text); border: 1px solid rgba(255,255,255,0.1); border-radius: 10px; font-weight: 500; cursor: pointer; transition: all 0.15s ease; text-decoration: none; display: inline-flex; align-items: center; justify-content: center; }
        .btn-secondary:hover { background: rgba(255,255,255,0.15); }
        .btn-danger { padding: 12px 24px; background: rgba(239,68,68,0.15); color: #ef4444; border: 1px solid rgba(239,68,68,0.3); border-radius: 10px; font-weight: 500; cursor: pointer; transition: all 0.15s ease; }
        .btn-danger:hover { background: rgba(239,68,68,0.25); }
        .preview-card { background: var(--background); border: 1px solid rgba(255,255,255,0.1); border-radius: 14px; padding: 24px; }
        .preview-title { font-size: 1.2rem; font-weight: 700; margin-bottom: 12px; }
        .preview-subtitle { color: var(--muted); font-size: 0.9rem; margin-bottom: 20px; }
        .preview-buttons { display: flex; gap: 10px; margin-bottom: 20px; }
        .preview-btn { padding: 10px 20px; border-radius: 10px; font-weight: 600; font-size: 0.9rem; cursor: pointer; transition: all 0.15s ease; }
        .preview-btn.primary { background: var(--primary); color: var(--background); }
        .preview-btn.secondary { background: var(--surface); color: var(--text); border: 1px solid rgba(255,255,255,0.1); }
        .preview-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: 12px; }
        .preview-stat { background: var(--secondary); border: 1px solid rgba(255,255,255,0.08); border-radius: 12px; padding: 16px; text-align: center; }
        .preview-stat-value { font-size: 1.5rem; font-weight: 700; color: var(--primary); }
        .preview-stat-label { color: var(--muted); font-size: 0.8rem; }
        .preview-nav { display: flex; gap: 8px; margin-bottom: 20px; }
        .preview-nav-item { padding: 8px 16px; background: var(--secondary); border-radius: 8px; font-size: 0.85rem; color: var(--text-secondary); }
        .preview-nav-item.active { background: rgba(1,180,228,0.15); color: var(--primary); }
        @media (max-width: 768px) {
            .admin-layout { grid-template-columns: 1fr; }
            .admin-sidebar { position: static; width: 100%; }
            .admin-main { margin-left: 0; }
            .content-grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>
    <div class="admin-layout">
        <aside class="admin-sidebar">
            <div class="admin-sidebar-header">
                <div class="admin-sidebar-brand">
                    <div class="brand-icon">🎬</div>
                    <span>Jay影视<span class="dev-tag-sm">开发者</span></span>
                </div>
            </div>
            <nav class="admin-sidebar-nav">
                <div class="admin-nav-section">
                    <div class="admin-nav-label">概览</div>
                    <a href="dashboard.php" class="admin-nav-item"><span class="nav-icon">📊</span>仪表盘</a>
                </div>
                <div class="admin-nav-section">
                    <div class="admin-nav-label">管理</div>
                    <a href="users.php" class="admin-nav-item"><span class="nav-icon">👥</span>用户管理</a>
                    <a href="sources.php" class="admin-nav-item"><span class="nav-icon">📺</span>播放源管理</a>
                    <a href="announcements.php" class="admin-nav-item"><span class="nav-icon">📢</span>公告管理</a>
                    <a href="feedback.php" class="admin-nav-item"><span class="nav-icon">💬</span>反馈管理</a>
                </div>
                <div class="admin-nav-section">
                    <div class="admin-nav-label">工具</div>
                    <a href="emails.php" class="admin-nav-item"><span class="nav-icon">📧</span>邮件管理</a>
                    <a href="theme.php" class="admin-nav-item active"><span class="nav-icon">🎨</span>主题定制</a>
                </div>
                <div class="admin-nav-section">
                    <div class="admin-nav-label">系统</div>
                    <a href="../index.php" class="admin-nav-item"><span class="nav-icon">🏠</span>返回前台</a>
                    <a href="logout.php" class="admin-nav-item"><span class="nav-icon">🚪</span>退出登录</a>
                </div>
            </nav>
        </aside>

        <main class="admin-main">
            <div class="admin-header">
                <h1>主题定制</h1>
                <div class="admin-user">
                    <div class="avatar"><?php echo mb_substr($_SESSION['admin_name'], 0, 1); ?></div>
                    <span class="admin-user-name"><?php echo clean($_SESSION['admin_name']); ?></span>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="message-toast <?php echo $messageType; ?>"><?php echo $messageType === 'success' ? '✅' : '⚠️'; ?> <?php echo clean($message); ?></div>
            <?php endif; ?>

            <div class="content-grid">
                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">🎨 颜色配置</span>
                    </div>
                    <div class="panel-body">
                        <form method="post" id="themeForm">
                            <input type="hidden" name="action" value="save">

                            <div class="color-row">
                                <label class="color-picker-wrapper">
                                    <div class="color-preview" id="preview-primary" style="background: <?php echo $currentTheme['primary']; ?>;"></div>
                                    <input type="color" name="primary" value="<?php echo $currentTheme['primary']; ?>" onchange="updatePreview('primary', this.value)">
                                </label>
                                <div class="color-info">
                                    <div class="color-label">主题色</div>
                                    <div class="color-desc">主色调，用于按钮、链接等</div>
                                </div>
                                <div class="color-hex">
                                    <input type="text" value="<?php echo $currentTheme['primary']; ?>" oninput="syncColor('primary', this.value)">
                                </div>
                            </div>

                            <div class="color-row">
                                <label class="color-picker-wrapper">
                                    <div class="color-preview" id="preview-secondary" style="background: <?php echo $currentTheme['secondary']; ?>;"></div>
                                    <input type="color" name="secondary" value="<?php echo $currentTheme['secondary']; ?>" onchange="updatePreview('secondary', this.value)">
                                </label>
                                <div class="color-info">
                                    <div class="color-label">次要色</div>
                                    <div class="color-desc">侧边栏、卡片背景</div>
                                </div>
                                <div class="color-hex">
                                    <input type="text" value="<?php echo $currentTheme['secondary']; ?>" oninput="syncColor('secondary', this.value)">
                                </div>
                            </div>

                            <div class="color-row">
                                <label class="color-picker-wrapper">
                                    <div class="color-preview" id="preview-accent" style="background: <?php echo $currentTheme['accent']; ?>;"></div>
                                    <input type="color" name="accent" value="<?php echo $currentTheme['accent']; ?>" onchange="updatePreview('accent', this.value)">
                                </label>
                                <div class="color-info">
                                    <div class="color-label">强调色</div>
                                    <div class="color-desc">高亮、装饰元素</div>
                                </div>
                                <div class="color-hex">
                                    <input type="text" value="<?php echo $currentTheme['accent']; ?>" oninput="syncColor('accent', this.value)">
                                </div>
                            </div>

                            <div class="color-row">
                                <label class="color-picker-wrapper">
                                    <div class="color-preview" id="preview-background" style="background: <?php echo $currentTheme['background']; ?>;"></div>
                                    <input type="color" name="background" value="<?php echo $currentTheme['background']; ?>" onchange="updatePreview('background', this.value)">
                                </label>
                                <div class="color-info">
                                    <div class="color-label">背景色</div>
                                    <div class="color-desc">页面主背景</div>
                                </div>
                                <div class="color-hex">
                                    <input type="text" value="<?php echo $currentTheme['background']; ?>" oninput="syncColor('background', this.value)">
                                </div>
                            </div>

                            <div class="color-row">
                                <label class="color-picker-wrapper">
                                    <div class="color-preview" id="preview-text" style="background: <?php echo $currentTheme['text']; ?>;"></div>
                                    <input type="color" name="text" value="<?php echo $currentTheme['text']; ?>" onchange="updatePreview('text', this.value)">
                                </label>
                                <div class="color-info">
                                    <div class="color-label">文字色</div>
                                    <div class="color-desc">主要文字颜色</div>
                                </div>
                                <div class="color-hex">
                                    <input type="text" value="<?php echo $currentTheme['text']; ?>" oninput="syncColor('text', this.value)">
                                </div>
                            </div>

                            <div class="color-row">
                                <label class="color-picker-wrapper">
                                    <div class="color-preview" id="preview-muted" style="background: <?php echo $currentTheme['muted']; ?>;"></div>
                                    <input type="color" name="muted" value="<?php echo $currentTheme['muted']; ?>" onchange="updatePreview('muted', this.value)">
                                </label>
                                <div class="color-info">
                                    <div class="color-label">次要文字</div>
                                    <div class="color-desc">次要说明、时间等</div>
                                </div>
                                <div class="color-hex">
                                    <input type="text" value="<?php echo $currentTheme['muted']; ?>" oninput="syncColor('muted', this.value)">
                                </div>
                            </div>

                            <div style="display: flex; gap: 12px; margin-top: 20px;">
                                <button type="submit" class="btn-primary">保存主题</button>
                            </div>
                        </form>
                        <form method="post" style="margin-top: 12px;" onsubmit="return confirm('确定重置为默认主题？');">
                            <input type="hidden" name="action" value="reset">
                            <button type="submit" class="btn-secondary">重置为默认</button>
                        </form>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <span class="panel-title">👁️ 实时预览</span>
                    </div>
                    <div class="panel-body">
                        <div class="preview-card">
                            <div class="preview-nav">
                                <div class="preview-nav-item active">首页</div>
                                <div class="preview-nav-item">电影</div>
                                <div class="preview-nav-item">电视剧</div>
                            </div>
                            <div class="preview-title">精彩影视在线观看</div>
                            <div class="preview-subtitle">发现、观看、收藏您喜爱的影视节目</div>
                            <div class="preview-buttons">
                                <button class="preview-btn primary">▶ 立即播放</button>
                                <button class="preview-btn secondary">➕ 加入列表</button>
                            </div>
                            <div class="preview-cards">
                                <div class="preview-stat"><div class="preview-stat-value" id="stat-primary">7.8</div><div class="preview-stat-label">评分</div></div>
                                <div class="preview-stat"><div class="preview-stat-value" id="stat-secondary">1,234</div><div class="preview-stat-label">播放次数</div></div>
                                <div class="preview-stat"><div class="preview-stat-value" id="stat-accent">567</div><div class="preview-stat-label">收藏数</div></div>
                            </div>
                        </div>

                        <div style="margin-top: 20px; padding: 16px; background: rgba(4,7,13,0.5); border-radius: 12px;">
                            <h4 style="margin-bottom: 10px; font-size: 0.9rem;">📝 文字样式预览</h4>
                            <p style="color: var(--text); font-size: 1rem; margin-bottom: 6px;">主文字颜色 Text Color</p>
                            <p style="color: var(--muted); font-size: 0.85rem; margin-bottom: 6px;">次要文字 Muted Text</p>
                            <p style="color: var(--primary); font-size: 0.85rem; margin-bottom: 6px;">链接颜色 Link Color</p>
                            <span class="status-badge active">状态徽章</span>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script>
        function updatePreview(name, value) {
            document.getElementById('preview-' + name).style.background = value;
            document.documentElement.style.setProperty('--' + name, value);
            document.querySelectorAll('input[name="' + name + '"]').forEach(function(input) {
                if (input.type === 'text') input.value = value;
            });
        }
        function syncColor(name, value) {
            if (/^#[0-9A-Fa-f]{6}$/.test(value)) {
                updatePreview(name, value);
            }
        }
    </script>
</body>
</html>
