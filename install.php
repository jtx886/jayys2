<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require __DIR__ . '/config.php';

$installFile = __DIR__ . '/data/installed.lock';
$installed = file_exists($installFile);

$phpVersion = phpversion();
$requiredVersion = '7.0.0';

$extensions = array(
    'pdo' => extension_loaded('pdo'),
    'pdo_sqlite' => extension_loaded('pdo_sqlite'),
    'json' => extension_loaded('json'),
    'mbstring' => extension_loaded('mbstring'),
    'curl' => extension_loaded('curl'),
    'fileinfo' => extension_loaded('fileinfo'),
);

$allExtensionsOk = !in_array(false, $extensions, true);
$phpVersionOk = version_compare($phpVersion, $requiredVersion, '>=');

$step = isset($_GET['step']) ? intval($_GET['step']) : 1;
$success = false;
$errorMessage = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $step === 2) {
    if (!$phpVersionOk || !$allExtensionsOk) {
        $errorMessage = '环境检查未通过，无法继续安装。';
    } else {
        try {
            $db = get_db();
            $conn = $db->getConnection();

            $tables = array(
                "CREATE TABLE IF NOT EXISTS users (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT UNIQUE NOT NULL,
                    username TEXT UNIQUE NOT NULL,
                    password TEXT NOT NULL,
                    avatar TEXT DEFAULT '',
                    status TEXT DEFAULT 'normal',
                    ban_until TEXT DEFAULT NULL,
                    ban_reason TEXT DEFAULT '',
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS verification_codes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT NOT NULL,
                    code TEXT NOT NULL,
                    type TEXT NOT NULL,
                    expires_at TEXT NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS favorites (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    media_id TEXT NOT NULL,
                    media_type TEXT NOT NULL,
                    title TEXT DEFAULT '',
                    poster TEXT DEFAULT '',
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )",
                "CREATE TABLE IF NOT EXISTS watch_history (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    media_id TEXT NOT NULL,
                    media_type TEXT NOT NULL,
                    title TEXT DEFAULT '',
                    poster TEXT DEFAULT '',
                    episode INTEGER DEFAULT 0,
                    season INTEGER DEFAULT 0,
                    progress REAL DEFAULT 0,
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )",
                "CREATE TABLE IF NOT EXISTS play_sources (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    name TEXT NOT NULL,
                    api_url TEXT NOT NULL,
                    parser_url TEXT DEFAULT '',
                    is_default INTEGER DEFAULT 0,
                    status INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS announcements (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    content TEXT NOT NULL,
                    is_active INTEGER DEFAULT 1,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS announcement_dismissed (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    announcement_id INTEGER NOT NULL,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
                )",
                "CREATE TABLE IF NOT EXISTS feedback (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    content TEXT NOT NULL,
                    status TEXT DEFAULT 'open',
                    likes INTEGER DEFAULT 0,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )",
                "CREATE TABLE IF NOT EXISTS feedback_replies (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    feedback_id INTEGER NOT NULL,
                    user_id INTEGER NOT NULL,
                    content TEXT NOT NULL,
                    is_admin INTEGER DEFAULT 0,
                    likes INTEGER DEFAULT 0,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                    FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )",
                "CREATE TABLE IF NOT EXISTS feedback_likes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    user_id INTEGER NOT NULL,
                    target_id INTEGER NOT NULL,
                    target_type TEXT NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
                )",
                "CREATE TABLE IF NOT EXISTS themes (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    primary_color TEXT DEFAULT '#01b4e4',
                    secondary_color TEXT DEFAULT '#0d253f',
                    accent_color TEXT DEFAULT '#1f80e0',
                    background_color TEXT DEFAULT '#04070d',
                    text_color TEXT DEFAULT '#ffffff',
                    muted_color TEXT DEFAULT '#9aa0a6',
                    updated_at TEXT DEFAULT CURRENT_TIMESTAMP
                )",
                "CREATE TABLE IF NOT EXISTS admin_notifications (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    type TEXT NOT NULL,
                    title TEXT NOT NULL,
                    content TEXT DEFAULT '',
                    is_read INTEGER DEFAULT 0,
                    created_at TEXT DEFAULT CURRENT_TIMESTAMP
                )"
            );

            foreach ($tables as $sql) {
                $conn->exec($sql);
            }

            $themeCount = $conn->query("SELECT COUNT(*) FROM themes")->fetchColumn();
            if ($themeCount == 0) {
                $conn->exec("INSERT INTO themes (primary_color, secondary_color, accent_color, background_color, text_color, muted_color) VALUES ('#01b4e4', '#0d253f', '#1f80e0', '#04070d', '#ffffff', '#9aa0a6')");
            }

            $sourceCount = $conn->query("SELECT COUNT(*) FROM play_sources")->fetchColumn();
            if ($sourceCount == 0) {
                $conn->exec("INSERT INTO play_sources (name, api_url, parser_url, is_default) VALUES ('默认播放源', 'https://api.yyzy-tv.vip/inc/apijson.php', 'https://svip.ffzyplay.com/?url=', 1)");
            }

            $adminCheck = $conn->query("SELECT COUNT(*) FROM users WHERE username = ?", array($config['admin_username']))->fetchColumn();
            if ($adminCheck == 0) {
                $adminPass = hash_password($config['admin_password']);
                $conn->exec("INSERT INTO users (email, username, password, status) VALUES (?, ?, ?, 'normal')", array(
                    'admin@jaymovie.local',
                    $config['admin_username'],
                    $adminPass
                ));
            }

            @file_put_contents($installFile, json_encode(array(
                'installed_at' => date('Y-m-d H:i:s'),
                'version' => '1.0.0'
            )));

            $success = true;
            $step = 3;

        } catch (Exception $e) {
            $errorMessage = '安装失败: ' . $e->getMessage();
        }
    }
}

if ($installed && $step < 3) {
    $step = 3;
    $success = true;
}

$page_title = '安装向导';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo clean($page_title); ?> - Jay影视</title>
    <link rel="stylesheet" href="assets/css/style.css">
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
            padding: 20px;
        }
        .install-container {
            width: 100%;
            max-width: 680px;
            background: var(--secondary);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 24px;
            overflow: hidden;
            box-shadow: 0 24px 64px rgba(0,0,0,0.5);
        }
        .install-header {
            background: linear-gradient(135deg, var(--primary), var(--accent));
            padding: 40px 36px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .install-header::before {
            content: "";
            position: absolute;
            inset: 0;
            background: radial-gradient(circle at 20% 50%, rgba(255,255,255,0.15) 0%, transparent 50%);
        }
        .install-logo {
            width: 64px;
            height: 64px;
            margin: 0 auto 16px;
            background: rgba(255,255,255,0.2);
            border-radius: 16px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            backdrop-filter: blur(8px);
        }
        .install-header h1 {
            color: white;
            font-size: 1.5rem;
            margin-bottom: 6px;
            position: relative;
            z-index: 1;
        }
        .install-header p {
            color: rgba(255,255,255,0.85);
            font-size: 0.95rem;
            position: relative;
            z-index: 1;
        }
        .install-body {
            padding: 36px;
        }
        .step-indicator {
            display: flex;
            justify-content: center;
            gap: 8px;
            margin-bottom: 32px;
        }
        .step-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.15);
            transition: all 0.3s ease;
        }
        .step-dot.active {
            background: var(--primary);
            transform: scale(1.3);
        }
        .step-dot.completed {
            background: var(--success);
        }
        .check-list {
            list-style: none;
            padding: 0;
            margin: 0 0 28px;
        }
        .check-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 14px 18px;
            background: rgba(4,7,13,0.5);
            border-radius: 10px;
            margin-bottom: 10px;
            border: 1px solid rgba(255,255,255,0.05);
        }
        .check-item-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .check-icon {
            width: 32px;
            height: 32px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1rem;
            flex-shrink: 0;
        }
        .check-icon.ok {
            background: rgba(34,197,94,0.15);
            color: #22c55e;
        }
        .check-icon.fail {
            background: rgba(239,68,68,0.15);
            color: #ef4444;
        }
        .check-label {
            font-weight: 600;
            font-size: 0.9rem;
        }
        .check-label .req {
            display: block;
            font-size: 0.75rem;
            color: var(--muted);
            font-weight: 400;
            margin-top: 2px;
        }
        .check-status {
            font-size: 0.85rem;
            font-weight: 600;
        }
        .check-status.ok {
            color: #22c55e;
        }
        .check-status.fail {
            color: #ef4444;
        }
        .install-actions {
            display: flex;
            gap: 12px;
            justify-content: center;
        }
        .success-icon {
            width: 72px;
            height: 72px;
            margin: 0 auto 20px;
            background: rgba(34,197,94,0.15);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2.5rem;
            color: #22c55e;
        }
        .success-title {
            text-align: center;
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 8px;
        }
        .success-desc {
            text-align: center;
            color: var(--muted);
            margin-bottom: 28px;
        }
        .success-links {
            display: flex;
            gap: 12px;
            justify-content: center;
            margin-bottom: 24px;
        }
        .admin-info {
            background: rgba(4,7,13,0.5);
            border: 1px solid rgba(255,255,255,0.1);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 24px;
        }
        .admin-info h3 {
            font-size: 0.95rem;
            margin-bottom: 12px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .admin-info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 0.85rem;
        }
        .admin-info-row:last-child {
            border-bottom: none;
        }
        .admin-info-row .label {
            color: var(--muted);
        }
        .admin-info-row .value {
            font-weight: 600;
            font-family: var(--font-mono);
        }
        .warning-banner {
            background: rgba(245,158,11,0.15);
            border: 1px solid rgba(245,158,11,0.3);
            color: #f59e0b;
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .error-banner {
            background: rgba(239,68,68,0.15);
            border: 1px solid rgba(239,68,68,0.3);
            color: #ef4444;
            padding: 12px 18px;
            border-radius: 10px;
            margin-bottom: 24px;
            font-size: 0.9rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .install-footer {
            padding: 16px 36px;
            border-top: 1px solid rgba(255,255,255,0.05);
            text-align: center;
            color: var(--muted);
            font-size: 0.8rem;
        }
        .hidden {
            display: none !important;
        }
    </style>
</head>
<body>
    <div class="install-container">
        <div class="install-header">
            <div class="install-logo">🎬</div>
            <h1>Jay影视 安装向导</h1>
            <p>欢迎使用 Jay影视，请按照步骤完成安装</p>
        </div>

        <div class="install-body">
            <?php if ($success || $installed): ?>
                <?php if ($installed && !$success): ?>
                <div class="warning-banner">
                    <span>⚠️</span>
                    <span>系统已安装，如需重新安装请删除 data/installed.lock 文件</span>
                </div>
                <?php endif; ?>

                <div class="success-icon">✅</div>
                <div class="success-title">安装完成！</div>
                <p class="success-desc">Jay影视已成功安装，您可以开始使用了。</p>

                <div class="admin-info">
                    <h3>🔐 管理员信息</h3>
                    <div class="admin-info-row">
                        <span class="label">账号</span>
                        <span class="value"><?php echo clean($config['admin_username']); ?></span>
                    </div>
                    <div class="admin-info-row">
                        <span class="label">密码</span>
                        <span class="value"><?php echo clean($config['admin_password']); ?></span>
                    </div>
                    <div class="admin-info-row">
                        <span class="label">前台地址</span>
                        <span class="value" style="font-family:var(--font-sans);">../index.php</span>
                    </div>
                    <div class="admin-info-row">
                        <span class="label">后台地址</span>
                        <span class="value" style="font-family:var(--font-sans);">admin/index.php</span>
                    </div>
                </div>

                <div class="success-links">
                    <a href="index.php" class="btn btn-primary btn-lg">🏠 访问首页</a>
                    <a href="admin/index.php" class="btn btn-secondary btn-lg">⚙️ 管理后台</a>
                </div>

                <div class="warning-banner">
                    <span>⚠️</span>
                    <span>安装完成后，建议删除 install.php 文件以确保安全</span>
                </div>

            <?php elseif ($errorMessage): ?>
                <div class="error-banner">
                    <span>❌</span>
                    <span><?php echo clean($errorMessage); ?></span>
                </div>

                <div class="install-actions">
                    <a href="install.php" class="btn btn-secondary">🔄 重新尝试</a>
                </div>

            <?php elseif ($step === 1): ?>
                <div class="step-indicator">
                    <div class="step-dot active"></div>
                    <div class="step-dot"></div>
                    <div class="step-dot"></div>
                </div>

                <h3 style="margin-bottom:16px;">环境检查</h3>

                <ul class="check-list">
                    <li class="check-item">
                        <div class="check-item-info">
                            <div class="check-icon <?php echo $phpVersionOk ? 'ok' : 'fail'; ?>">
                                <?php echo $phpVersionOk ? '✓' : '✗'; ?>
                            </div>
                            <div>
                                <div class="check-label">PHP 版本</div>
                                <div class="req">当前: <?php echo clean($phpVersion); ?> / 要求: >= <?php echo clean($requiredVersion); ?></div>
                            </div>
                        </div>
                        <span class="check-status <?php echo $phpVersionOk ? 'ok' : 'fail'; ?>">
                            <?php echo $phpVersionOk ? '通过' : '不通过'; ?>
                        </span>
                    </li>

                    <?php foreach ($extensions as $ext => $loaded): ?>
                    <li class="check-item">
                        <div class="check-item-info">
                            <div class="check-icon <?php echo $loaded ? 'ok' : 'fail'; ?>">
                                <?php echo $loaded ? '✓' : '✗'; ?>
                            </div>
                            <div>
                                <div class="check-label"><?php echo clean(strtoupper($ext)); ?> 扩展</div>
                                <div class="req"><?php echo $loaded ? '已加载' : '未加载'; ?></div>
                            </div>
                        </div>
                        <span class="check-status <?php echo $loaded ? 'ok' : 'fail'; ?>">
                            <?php echo $loaded ? '通过' : '不通过'; ?>
                        </span>
                    </li>
                    <?php endforeach; ?>

                    <?php
                    $dataWritable = is_writable(__DIR__ . '/data');
                    ?>
                    <li class="check-item">
                        <div class="check-item-info">
                            <div class="check-icon <?php echo $dataWritable ? 'ok' : 'fail'; ?>">
                                <?php echo $dataWritable ? '✓' : '✗'; ?>
                            </div>
                            <div>
                                <div class="check-label">data 目录可写</div>
                                <div class="req">用于存储数据库和缓存</div>
                            </div>
                        </div>
                        <span class="check-status <?php echo $dataWritable ? 'ok' : 'fail'; ?>">
                            <?php echo $dataWritable ? '通过' : '不通过'; ?>
                        </span>
                    </li>
                </ul>

                <?php if (!$phpVersionOk || !$allExtensionsOk || !$dataWritable): ?>
                <div class="error-banner">
                    <span>❌</span>
                    <span>请解决以上问题后刷新页面重试</span>
                </div>
                <?php else: ?>
                <div class="install-actions">
                    <a href="install.php?step=2" class="btn btn-primary btn-lg">✅ 环境检查通过，下一步</a>
                </div>
                <?php endif; ?>

            <?php elseif ($step === 2): ?>
                <div class="step-indicator">
                    <div class="step-dot completed"></div>
                    <div class="step-dot active"></div>
                    <div class="step-dot"></div>
                </div>

                <h3 style="margin-bottom:16px;">配置信息确认</h3>

                <div class="admin-info">
                    <h3>⚙️ 系统配置</h3>
                    <div class="admin-info-row">
                        <span class="label">站点名称</span>
                        <span class="value"><?php echo clean($config['site_name']); ?></span>
                    </div>
                    <div class="admin-info-row">
                        <span class="label">管理员账号</span>
                        <span class="value"><?php echo clean($config['admin_username']); ?></span>
                    </div>
                    <div class="admin-info-row">
                        <span class="label">管理员密码</span>
                        <span class="value"><?php echo clean($config['admin_password']); ?></span>
                    </div>
                    <div class="admin-info-row">
                        <span class="label">数据库类型</span>
                        <span class="value"><?php echo clean(strtoupper($config['db']['type'])); ?></span>
                    </div>
                    <div class="admin-info-row">
                        <span class="label">数据库路径</span>
                        <span class="value" style="font-family:var(--font-sans);"><?php echo clean($config['db']['path']); ?></span>
                    </div>
                </div>

                <div class="warning-banner">
                    <span>⚠️</span>
                    <span>点击下方按钮将创建数据表和默认数据，请确认以上配置。</span>
                </div>

                <form method="post" action="install.php?step=2">
                    <div class="install-actions">
                        <a href="install.php" class="btn btn-secondary">← 返回上一步</a>
                        <button type="submit" class="btn btn-primary btn-lg">🚀 开始安装</button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <div class="install-footer">
            Jay影视 v1.0.0 &copy; <?php echo date('Y'); ?>
        </div>
    </div>
</body>
</html>