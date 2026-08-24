<?php
// 数据库层 - SQLite支持，兼容所有PHP版本

if (basename($_SERVER['PHP_SELF']) === basename(__FILE__)) {
    die('Access Denied');
}

$config = require __DIR__ . '/../config.php';

class Database {
    private $pdo;
    private $config;
    
    public function __construct($config) {
        $this->config = $config;
        $this->connect();
        $this->initialize();
    }
    
    private function connect() {
        try {
            $dbPath = $this->config['db']['path'];
            $dir = dirname($dbPath);
            
            if (!is_dir($dir)) {
                @mkdir($dir, 0755, true);
            }
            
            $this->pdo = new PDO('sqlite:' . $dbPath);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA journal_mode=WAL');
            $this->pdo->exec('PRAGMA foreign_keys=ON');
        } catch (Exception $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    private function initialize() {
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT UNIQUE NOT NULL,
                username TEXT UNIQUE NOT NULL,
                password TEXT NOT NULL,
                avatar TEXT DEFAULT '',
                status TEXT DEFAULT 'normal',
                ban_until TEXT DEFAULT NULL,
                ban_reason TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS verification_codes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL,
                code TEXT NOT NULL,
                type TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS favorites (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                media_id TEXT NOT NULL,
                media_type TEXT NOT NULL,
                title TEXT DEFAULT '',
                poster TEXT DEFAULT '',
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS watch_history (
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
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS play_sources (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                api_url TEXT NOT NULL,
                parser_url TEXT DEFAULT '',
                is_default INTEGER DEFAULT 0,
                status INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS announcements (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                title TEXT NOT NULL,
                content TEXT NOT NULL,
                is_active INTEGER DEFAULT 1,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS announcement_dismissed (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                announcement_id INTEGER NOT NULL,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                FOREIGN KEY (announcement_id) REFERENCES announcements(id) ON DELETE CASCADE
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS feedback (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                status TEXT DEFAULT 'open',
                likes INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS feedback_replies (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                feedback_id INTEGER NOT NULL,
                user_id INTEGER NOT NULL,
                content TEXT NOT NULL,
                is_admin INTEGER DEFAULT 0,
                likes INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (feedback_id) REFERENCES feedback(id) ON DELETE CASCADE,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS feedback_likes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER NOT NULL,
                target_id INTEGER NOT NULL,
                target_type TEXT NOT NULL,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS themes (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                primary_color TEXT DEFAULT '#01b4e4',
                secondary_color TEXT DEFAULT '#0d253f',
                accent_color TEXT DEFAULT '#1f80e0',
                background_color TEXT DEFAULT '#04070d',
                text_color TEXT DEFAULT '#ffffff',
                muted_color TEXT DEFAULT '#9aa0a6',
                updated_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        $this->pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_notifications (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                type TEXT NOT NULL,
                title TEXT NOT NULL,
                content TEXT DEFAULT '',
                is_read INTEGER DEFAULT 0,
                created_at TEXT DEFAULT CURRENT_TIMESTAMP
            )
        ");
        
        // 插入默认主题
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM themes");
        if ($stmt->fetchColumn() == 0) {
            $this->pdo->exec("INSERT INTO themes (primary_color, secondary_color, accent_color, background_color, text_color, muted_color) VALUES ('#01b4e4', '#0d253f', '#1f80e0', '#04070d', '#ffffff', '#9aa0a6')");
        }
        
        // 插入默认播放源
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM play_sources");
        if ($stmt->fetchColumn() == 0) {
            $this->pdo->exec("INSERT INTO play_sources (name, api_url, parser_url, is_default) VALUES ('默认播放源', 'https://api.yyzy-tv.vip/inc/apijson.php', 'https://svip.ffzyplay.com/?url=', 1)");
        }
        
        // 迁移：添加缺失的列
        $this->migrate();
    }
    
    private function migrate() {
        // feedback表添加title和type列
        $columns = $this->getTableColumns('feedback');
        if (!in_array('title', $columns)) {
            $this->pdo->exec("ALTER TABLE feedback ADD COLUMN title TEXT DEFAULT ''");
        }
        if (!in_array('type', $columns)) {
            $this->pdo->exec("ALTER TABLE feedback ADD COLUMN type TEXT DEFAULT '建议'");
        }
        if (!in_array('status', $columns)) {
            $this->pdo->exec("ALTER TABLE feedback ADD COLUMN status TEXT DEFAULT 'open'");
        }
        
        // announcements表添加type列
        $columns = $this->getTableColumns('announcements');
        if (!in_array('type', $columns)) {
            $this->pdo->exec("ALTER TABLE announcements ADD COLUMN type TEXT DEFAULT 'info'");
        }
        
        // feedback_likes表添加target_type列
        $columns = $this->getTableColumns('feedback_likes');
        if (!in_array('target_type', $columns)) {
            $this->pdo->exec("ALTER TABLE feedback_likes ADD COLUMN target_type TEXT DEFAULT 'feedback'");
        }
    }
    
    private function getTableColumns($table) {
        try {
            $stmt = $this->pdo->query("PRAGMA table_info($table)");
            $columns = array();
            while ($row = $stmt->fetch()) {
                $columns[] = $row['name'];
            }
            return $columns;
        } catch (Exception $e) {
            return array();
        }
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    public function query($sql, $params = array()) {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    public function fetchAll($sql, $params = array()) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    public function fetchOne($sql, $params = array()) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    public function insert($table, $data) {
        $fields = implode(', ', array_keys($data));
        $placeholders = implode(', ', array_fill(0, count($data), '?'));
        $sql = "INSERT INTO $table ($fields) VALUES ($placeholders)";
        $this->query($sql, array_values($data));
        return $this->pdo->lastInsertId();
    }
    
    public function update($table, $data, $where, $whereParams = array()) {
        $set = array();
        foreach (array_keys($data) as $key) {
            $set[] = "$key = ?";
        }
        $setStr = implode(', ', $set);
        $sql = "UPDATE $table SET $setStr WHERE $where";
        return $this->query($sql, array_merge(array_values($data), $whereParams));
    }
    
    public function delete($table, $where, $params = array()) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->query($sql, $params);
    }
}

// 全局数据库实例
$GLOBALS['db'] = new Database($config);
