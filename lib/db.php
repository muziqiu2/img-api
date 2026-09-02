<?php
// 该模块：数据库连接与初始化。维护全局 PDO 连接（$pdo/$dbInitialized），
// 并在 getDb() 首连时惰性执行 initDatabase() 建表（DDL）。

$pdo = null;
$dbInitialized = false;
function getDb() {
    global $pdo, $dbInitialized;

    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . DB_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

            // 并发稳健性配置（仅记录日志，失败不影响连接本身）
            try {
                // 写锁等待最多 5 秒，避免高并发写入时立刻抛 "database is locked"
                $pdo->exec('PRAGMA busy_timeout = 5000');
                // WAL 日志模式：读写不互斥，显著降低写锁争用。
                // journal_mode 是持久化设置，首次生效后对后续所有连接均有效。
                $walMode = $pdo->query('PRAGMA journal_mode = WAL')->fetchColumn();
                if (strcasecmp((string)$walMode, 'wal') === 0) {
                    // 仅 WAL 生效时才开启 NORMAL 同步（WAL 下 NORMAL 足以防库损坏且写吞吐更高）
                    $pdo->exec('PRAGMA synchronous = NORMAL');
                }
            } catch (Exception $e) {
                @error_log('[img-api] 数据库 PRAGMA 配置失败: ' . $e->getMessage());
            }
        } catch (PDOException $e) {
            @error_log('[img-api] 数据库连接失败: ' . $e->getMessage());
            die('数据库连接失败，请检查服务器配置或查看服务器错误日志');
        }
    }

    if (!$dbInitialized) {
        $dbInitialized = true;
        initDatabase();
    }

    return $pdo;
}
function initDatabase() {
    global $pdo;
    $db = $pdo;

    // 已初始化标记：跳过重复 DDL，消除每个请求的固定开销（CREATE TABLE 编译与 schema 读锁）。
    // 数据库文件缺失或被清空时仍会重新初始化，确保重置场景可自愈；
    // 未来新表结构变更走 update/migrations.php（更新时执行），不受此标记影响。
    $schemaMarker = DB_FILE . '.schema_ok';
    if (file_exists($schemaMarker) && file_exists(DB_FILE) && @filesize(DB_FILE) > 0) {
        return;
    }

    // 用户配置表
    $db->exec("
        CREATE TABLE IF NOT EXISTS user_config (
            id INTEGER PRIMARY KEY,
            username TEXT NOT NULL DEFAULT 'admin',
            password_hash TEXT NOT NULL,
            login_attempts INTEGER DEFAULT 0,
            last_attempt INTEGER DEFAULT 0,
            locked_until INTEGER DEFAULT 0
        )
    ");

    // 应用设置表（存储 GitHub Token 等配置）
    $db->exec("
        CREATE TABLE IF NOT EXISTS app_settings (
            key TEXT PRIMARY KEY,
            value TEXT
        )
    ");

    // 图片URL表
    $db->exec("
        CREATE TABLE IF NOT EXISTS image_urls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL UNIQUE,
            type TEXT NOT NULL CHECK(type IN ('pc', 'pe')),
            created_at INTEGER DEFAULT (strftime('%s', 'now'))
        )
    ");

    // 创建索引
    $db->exec("CREATE INDEX IF NOT EXISTS idx_image_urls_type ON image_urls(type)");

    // 调用统计表
    $db->exec("
        CREATE TABLE IF NOT EXISTS call_stats (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            date TEXT NOT NULL UNIQUE,
            total INTEGER DEFAULT 0,
            pc INTEGER DEFAULT 0,
            pe INTEGER DEFAULT 0,
            api_count INTEGER DEFAULT 0,
            redirect_count INTEGER DEFAULT 0,
            json_count INTEGER DEFAULT 0,
            img_count INTEGER DEFAULT 0
        )
    ");

    // 创建索引
    $db->exec("CREATE INDEX IF NOT EXISTS idx_call_stats_date ON call_stats(date)");

    // 操作日志表
    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            time TEXT NOT NULL,
            username TEXT NOT NULL,
            ip TEXT NOT NULL,
            action TEXT NOT NULL
        )
    ");

    // 频率限制表
    $db->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id TEXT PRIMARY KEY,
            count INTEGER DEFAULT 0,
            timestamp INTEGER DEFAULT 0
        )
    ");

    // 更新日志表
    $db->exec("
        CREATE TABLE IF NOT EXISTS update_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            from_version TEXT NOT NULL,
            to_version TEXT NOT NULL,
            status TEXT NOT NULL,
            message TEXT,
            backup_path TEXT,
            username TEXT,
            ip TEXT,
            timestamp TEXT NOT NULL
        )
    ");

    // 确保默认用户存在
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM user_config");
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result['cnt'] == 0) {
        $stmt = $db->prepare("
            INSERT INTO user_config (username, password_hash, login_attempts, last_attempt, locked_until)
            VALUES ('admin', ?, 0, 0, 0)
        ");
        $stmt->execute([password_hash('123456', PASSWORD_DEFAULT)]);
    }

    // 全部表结构与默认数据就绪后写入标记，后续请求跳过 DDL
    @file_put_contents($schemaMarker, date('Y-m-d H:i:s'));
}
