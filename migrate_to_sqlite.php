<?php
/**
 * 数据迁移脚本：从 JSON 文件迁移到 SQLite 数据库
 *
 * 使用方法：
 * 1. 将旧的 JSON/TXT 文件放到 data/backup/ 目录
 * 2. 运行：php migrate_to_sqlite.php
 *
 * 旧文件格式：
 * - data/backup/api_call_count.json  - 调用统计
 * - data/backup/user_config.json    - 用户配置
 * - data/backup/pc.txt             - PC端图片链接
 * - data/backup/pe.txt             - 移动端图片链接
 */

// 确保目录存在
$requiredDirs = ['data', 'admin/logs', 'data/cache'];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

define('DB_FILE', __DIR__ . '/data/app.db');
define('BACKUP_DIR', __DIR__ . '/data/backup');

echo "=== 数据迁移脚本 ===\n\n";

// 初始化数据库
function getDb() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_FILE);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }
    return $pdo;
}

function initDatabase() {
    $db = getDb();

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

    $db->exec("
        CREATE TABLE IF NOT EXISTS image_urls (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            url TEXT NOT NULL UNIQUE,
            type TEXT NOT NULL CHECK(type IN ('pc', 'pe')),
            created_at INTEGER DEFAULT (strftime('%s', 'now'))
        )
    ");

    $db->exec("CREATE INDEX IF NOT EXISTS idx_image_urls_type ON image_urls(type)");

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

    $db->exec("CREATE INDEX IF NOT EXISTS idx_call_stats_date ON call_stats(date)");

    $db->exec("
        CREATE TABLE IF NOT EXISTS admin_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            time TEXT NOT NULL,
            username TEXT NOT NULL,
            ip TEXT NOT NULL,
            action TEXT NOT NULL
        )
    ");

    $db->exec("
        CREATE TABLE IF NOT EXISTS rate_limits (
            id TEXT PRIMARY KEY,
            count INTEGER DEFAULT 0,
            timestamp INTEGER DEFAULT 0
        )
    ");
}

// 迁移用户配置
function migrateUserConfig() {
    $configFile = BACKUP_DIR . '/user_config.json';

    if (!file_exists($configFile)) {
        echo "⚠️  user_config.json 不存在，跳过用户配置迁移\n";
        return;
    }

    $content = file_get_contents($configFile);
    $config = json_decode($content, true);

    if (!$config) {
        echo "❌ user_config.json 解析失败\n";
        return;
    }

    $db = getDb();

    // 检查是否已有用户配置
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM user_config");
    $stmt->execute();
    $result = $stmt->fetch();

    if ($result['cnt'] > 0) {
        echo "⚠️  用户配置已存在，选择操作：\n";
        echo "   1. 覆盖现有配置\n";
        echo "   2. 保留现有配置（跳过）\n";
        echo "   默认选择: 保留现有配置\n";

        $choice = trim(fgets(STDIN));
        if ($choice !== '1') {
            echo "   → 跳过用户配置迁移\n";
            return;
        }
    }

    $username = $config['username'] ?? 'admin';
    $passwordHash = $config['password_hash'] ?? password_hash('123456', PASSWORD_DEFAULT);
    $loginAttempts = $config['login_attempts'] ?? 0;
    $lastAttempt = $config['last_attempt'] ?? 0;
    $lockedUntil = $config['locked_until'] ?? 0;

    $stmt = $db->prepare("
        INSERT OR REPLACE INTO user_config (id, username, password_hash, login_attempts, last_attempt, locked_until)
        VALUES (1, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$username, $passwordHash, $loginAttempts, $lastAttempt, $lockedUntil]);

    echo "✅ 用户配置迁移成功：$username\n";
}

// 迁移调用统计
function migrateCallStats() {
    $statsFile = BACKUP_DIR . '/api_call_count.json';

    if (!file_exists($statsFile)) {
        echo "⚠️  api_call_count.json 不存在，跳过统计迁移\n";
        return;
    }

    $content = file_get_contents($statsFile);
    $data = json_decode($content, true);

    if (!$data) {
        echo "❌ api_call_count.json 解析失败\n";
        return;
    }

    $db = getDb();
    $count = 0;

    if (isset($data['daily']) && is_array($data['daily'])) {
        $stmt = $db->prepare("
            INSERT OR REPLACE INTO call_stats (date, total, pc, pe, api_count, redirect_count, json_count, img_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");

        foreach ($data['daily'] as $date => $stats) {
            $total = $stats['total'] ?? 0;
            $pc = $stats['pc'] ?? 0;
            $pe = $stats['pe'] ?? 0;
            $api = $stats['api'] ?? round($total * 0.85);

            // 计算返回类型分布
            $redirect = $data['return_types']['redirect'] ?? round($total * 0.8);
            $json = $data['return_types']['json'] ?? round($total * 0.15);
            $img = $total - $redirect - $json;
            $img = max(0, $img);

            // 按日期比例分配
            if ($total > 0) {
                $redirect = round($total * ($redirect / ($total ?: 1)));
                $json = round($total * ($json / ($total ?: 1)));
                $img = $total - $redirect - $json;
            }

            try {
                $stmt->execute([$date, $total, $pc, $pe, $api, $redirect, $json, $img]);
                $count++;
            } catch (PDOException $e) {
                // 忽略重复日期
            }
        }
    }

    echo "✅ 调用统计迁移成功：$count 条记录\n";
}

// 迁移图片链接
function migrateImageUrls() {
    $pcFile = BACKUP_DIR . '/pc.txt';
    $peFile = BACKUP_DIR . '/pe.txt';

    $db = getDb();
    $pcCount = 0;
    $peCount = 0;

    $stmt = $db->prepare("INSERT OR IGNORE INTO image_urls (url, type) VALUES (?, ?)");

    // 迁移 PC 端图片
    if (file_exists($pcFile)) {
        $urls = file($pcFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($urls as $url) {
            $url = trim($url);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                try {
                    if ($stmt->execute([$url, 'pc'])) {
                        $pcCount++;
                    }
                } catch (PDOException $e) {
                    // 忽略重复
                }
            }
        }
        echo "✅ PC端图片链接迁移成功：$pcCount 条记录\n";
    } else {
        echo "⚠️  pc.txt 不存在，跳过\n";
    }

    // 迁移移动端图片
    if (file_exists($peFile)) {
        $urls = file($peFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($urls as $url) {
            $url = trim($url);
            if (filter_var($url, FILTER_VALIDATE_URL)) {
                try {
                    if ($stmt->execute([$url, 'pe'])) {
                        $peCount++;
                    }
                } catch (PDOException $e) {
                    // 忽略重复
                }
            }
        }
        echo "✅ 移动端图片链接迁移成功：$peCount 条记录\n";
    } else {
        echo "⚠️  pe.txt 不存在，跳过\n";
    }
}

// 主程序
echo "1. 初始化数据库...\n";
initDatabase();
echo "   完成\n\n";

echo "2. 迁移用户配置...\n";
migrateUserConfig();
echo "\n";

echo "3. 迁移调用统计...\n";
migrateCallStats();
echo "\n";

echo "4. 迁移图片链接...\n";
migrateImageUrls();
echo "\n";

echo "=== 迁移完成 ===\n\n";

// 显示迁移后的统计
$db = getDb();

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM image_urls WHERE type = 'pc'");
$stmt->execute();
$pcUrls = $stmt->fetch()['cnt'];

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM image_urls WHERE type = 'pe'");
$stmt->execute();
$peUrls = $stmt->fetch()['cnt'];

$stmt = $db->prepare("SELECT COUNT(*) as cnt FROM call_stats");
$stmt->execute();
$statsDays = $stmt->fetch()['cnt'];

$stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM call_stats");
$stmt->execute();
$totalCalls = $stmt->fetch()['total'];

echo "迁移后统计：\n";
echo "  - PC端图片：$pcUrls 条\n";
echo "  - 移动端图片：$peUrls 条\n";
echo "  - 统计天数：$statsDays 天\n";
echo "  - 总调用次数：" . number_format($totalCalls) . " 次\n";
?>
