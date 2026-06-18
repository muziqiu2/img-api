<?php
/**
 * 魔法师随机图片API - 配置文件
 * 使用 SQLite 数据库存储
 */

// 确保目录存在
$requiredDirs = ['data', 'admin/logs', 'data/cache', 'data/backups', 'data/update_cache'];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 数据库配置
define('DB_FILE', __DIR__ . '/data/app.db');

// 缓存配置
define('CACHE_DIR', __DIR__ . '/data/cache');
define('CACHE_TTL', 300); // 5分钟缓存

// 会话配置
define('SESSION_TIMEOUT', 3600); // 会话超时时间(秒)

// 频率限制配置
define('RATE_LIMIT_WINDOW', 60); // 60秒窗口
define('RATE_LIMIT_MAX_API', 100); // API每分钟最大请求数
define('RATE_LIMIT_MAX_ADMIN', 10); // 管理后台每分钟最大请求数

// 代理配置（仅在确定服务器前方有可信代理时启用）
define('TRUST_PROXY_HEADERS', false); // 是否信任代理头（如 X-Forwarded-For）

// ==================== 版本与自动更新配置 ====================

define('APP_VERSION', '3.1.0'); // 当前应用版本号（Semantic Versioning）
define('APP_VERSION_FILE', __DIR__ . '/data/app_version.txt'); // 存储在数据库外的版本文件（备份）

// GitHub 仓库配置
define('GITHUB_REPO_OWNER', 'muziqiu2'); // 仓库所有者
define('GITHUB_REPO_NAME', 'img-api');   // 仓库名称
define('GITHUB_API_BASE', 'https://api.github.com/repos/' . GITHUB_REPO_OWNER . '/' . GITHUB_REPO_NAME);
define('GITHUB_TOKEN', ''); // 可选：个人访问令牌（提升API速率限制，私有仓库必需）

// 更新相关目录
define('UPDATE_BACKUP_DIR', __DIR__ . '/data/backups');       // 更新备份目录
define('UPDATE_CACHE_DIR', __DIR__ . '/data/update_cache');   // 临时下载/解压目录
define('UPDATE_CHECK_CACHE_TTL', 3600);                       // 版本检查缓存（1小时）
define('UPDATE_CHECK_CACHE_FILE', CACHE_DIR . '/update_check.json');

// 更新安全配置
define('UPDATE_MAX_ZIP_SIZE', 50 * 1024 * 1024);              // 允许的最大更新包（50MB）
define('UPDATE_TIMEOUT', 300);                                // 更新执行超时时间（5分钟）
define('UPDATE_MIN_FREE_SPACE', 100 * 1024 * 1024);           // 最少需要 100MB 空闲空间

// 更新时被保护、不会被覆盖的目录/文件（相对项目根目录）
define('UPDATE_PROTECTED_PATHS', serialize([
    'data/',
    'admin/logs/',
    'data/cache/',
    'data/backups/',
    'data/update_cache/',
    '.git/',
    '.htaccess',
    '.router.php',
]));

// 更新时允许被替换的文件扩展名白名单（空数组表示不限制扩展名，仅受目录保护）
define('UPDATE_ALLOWED_EXTENSIONS', serialize([
    'php', 'txt', 'md', 'html', 'htm', 'css', 'js', 'json',
    'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico',
    'woff', 'woff2', 'ttf', 'eot', 'map',
]));

// ==================== 客户端IP获取函数 ====================

function getClientIp() {
    $ip = null;

    // 如果配置了信任代理头，则检查代理相关头部
    if (TRUST_PROXY_HEADERS) {
        // X-Forwarded-For: client, proxy1, proxy2
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (!empty($xff)) {
            // 取最后一个IP（最靠近服务器的才是真实的出口IP）
            $ips = array_map('trim', explode(',', $xff));
            $ip = end($ips);
        }

        // X-Real-IP
        if (empty($ip)) {
            $xri = $_SERVER['HTTP_X_REAL_IP'] ?? '';
            if (!empty($xri) && filter_var(trim($xri), FILTER_VALIDATE_IP)) {
                $ip = trim($xri);
            }
        }
    }

    // 默认使用 REMOTE_ADDR（最可靠，但可能在代理后不准确）
    if (empty($ip)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    // 验证IP格式
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = 'invalid';
    }

    return $ip;
}

// ==================== 数据库初始化 ====================
$pdo = null;
$dbInitialized = false;

function getDb() {
    global $pdo, $dbInitialized;

    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . DB_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
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
}

// 定义是否在管理区域
$isAdminArea = strpos($_SERVER['SCRIPT_NAME'] ?? '', '/admin/') !== false;

// 仅在管理区域启动会话
if ($isAdminArea) {
    // 如果会话尚未启动，设置cookie参数并启动
    if (session_status() === PHP_SESSION_NONE) {
        // 设置 session cookie 路径为根路径，确保全站共享
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
        session_start();
    }

    // 检查会话超时（未设置登录时间视为首次登录，不超时）
    $isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    if ($isLoggedIn && !empty($_SESSION['admin_login_time']) && (time() - $_SESSION['admin_login_time'] > SESSION_TIMEOUT)) {
        $_SESSION = [];
        session_destroy();
        $isLoggedIn = false;
    }
    define('IS_LOGGED_IN', $isLoggedIn);
} else {
    define('IS_LOGGED_IN', false);
}

// ==================== 用户认证函数 ====================

function getUserConfig() {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM user_config LIMIT 1");
    $stmt->execute();
    $result = $stmt->fetch();
    
    if (!$result) {
        // 创建默认配置
        $stmt = $db->prepare("
            INSERT INTO user_config (username, password_hash, login_attempts, last_attempt, locked_until)
            VALUES ('admin', ?, 0, 0, 0)
        ");
        $stmt->execute([password_hash('123456', PASSWORD_DEFAULT)]);
        return [
            'username' => 'admin',
            'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
            'login_attempts' => 0,
            'last_attempt' => 0,
            'locked_until' => 0
        ];
    }
    
    return $result;
}

function saveUserConfig($config) {
    $db = getDb();
    $stmt = $db->prepare("
        UPDATE user_config SET
            username = ?,
            password_hash = ?,
            login_attempts = ?,
            last_attempt = ?,
            locked_until = ?
        WHERE id = 1
    ");
    return $stmt->execute([
        $config['username'],
        $config['password_hash'],
        $config['login_attempts'],
        $config['last_attempt'],
        $config['locked_until']
    ]);
}

function getCurrentUsername() {
    $config = getUserConfig();
    return $config['username'] ?? 'admin';
}

function verifyPassword($password) {
    $config = getUserConfig();
    return password_verify($password, $config['password_hash'] ?? '');
}

function updateUserInfo($newUsername, $newPassword = '') {
    $db = getDb();
    
    if (!empty($newPassword)) {
        $stmt = $db->prepare("
            UPDATE user_config SET username = ?, password_hash = ? WHERE id = 1
        ");
        return $stmt->execute([$newUsername, password_hash($newPassword, PASSWORD_DEFAULT)]);
    } else {
        $stmt = $db->prepare("UPDATE user_config SET username = ? WHERE id = 1");
        return $stmt->execute([$newUsername]);
    }
}

function recordLoginAttempt($success = false) {
    $db = getDb();
    
    if ($success) {
        $stmt = $db->prepare("
            UPDATE user_config SET login_attempts = 0, locked_until = 0, last_attempt = ? WHERE id = 1
        ");
        return $stmt->execute([time()]);
    } else {
        $stmt = $db->prepare("SELECT login_attempts FROM user_config WHERE id = 1");
        $stmt->execute();
        $result = $stmt->fetch();
        $attempts = ($result['login_attempts'] ?? 0) + 1;
        $lockedUntil = ($attempts >= 5) ? time() + 300 : 0;
        
        $stmt = $db->prepare("
            UPDATE user_config SET login_attempts = ?, locked_until = ?, last_attempt = ? WHERE id = 1
        ");
        return $stmt->execute([$attempts, $lockedUntil, time()]);
    }
}

function isAccountLocked() {
    $config = getUserConfig();
    return time() < ($config['locked_until'] ?? 0);
}

function getRemainingAttempts() {
    $config = getUserConfig();
    return max(0, 5 - ($config['login_attempts'] ?? 0));
}

// ==================== 应用设置函数 ====================

function getAppSetting($key, $default = '') {
    $db = getDb();
    $stmt = $db->prepare("SELECT value FROM app_settings WHERE key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? ($result['value'] ?? $default) : $default;
}

function setAppSetting($key, $value) {
    $db = getDb();
    $stmt = $db->prepare("INSERT OR REPLACE INTO app_settings (key, value) VALUES (?, ?)");
    return $stmt->execute([$key, $value]);
}

function deleteAppSetting($key) {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM app_settings WHERE key = ?");
    return $stmt->execute([$key]);
}

// 获取 GitHub Token（优先从数据库获取，否则使用配置文件）
function getGithubToken() {
    $token = getAppSetting('github_token', '');
    if (empty($token)) {
        $token = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '';
    }
    return $token;
}

// ==================== CSRF 防护函数 ====================

function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        try {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        } catch (Exception $e) {
            $_SESSION['csrf_token'] = bin2hex(openssl_random_pseudo_bytes(32));
        }
    }
    return $_SESSION['csrf_token'];
}

function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

// ==================== 频率限制函数 ====================

function checkApiRateLimit() {
    $ip = md5(getClientIp());
    $key = 'api_' . $ip;
    $now = time();
    $windowStart = $now - RATE_LIMIT_WINDOW;

    $db = getDb();

    // 清理过期记录
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE timestamp < ?");
    $stmt->execute([$windowStart]);

    // 先检查当前计数是否已超过限制
    $stmt = $db->prepare("SELECT count FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();

    // 如果已超过限制，直接拒绝
    if ($record && $record['count'] >= RATE_LIMIT_MAX_API) {
        return false;
    }

    // 尝试原子地增加计数（使用 UPDATE ... WHERE 确保原子性）
    // 如果记录不存在或已过期，插入新记录；否则在 count < 限制时递增
    $stmt = $db->prepare("
        INSERT INTO rate_limits (id, count, timestamp)
        VALUES (?, 1, ?)
        ON CONFLICT(id) DO UPDATE
        SET count = CASE WHEN timestamp < ? THEN 1 ELSE count + 1 END,
            timestamp = CASE WHEN timestamp < ? THEN ? ELSE timestamp END
    ");
    $stmt->execute([$key, $now, $windowStart, $windowStart, $now]);

    // 再次检查：获取最新计数，如果超过限制则表示刚才的递增导致了超限，需要回滚
    // 但 SQLite 不支持读后写的锁（SELECT 后 UPDATE 不是原子的）
    // 所以我们接受在极端竞态下可能多允许 1 个请求的情况
    $stmt = $db->prepare("SELECT count FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();

    // 如果超过限制，返回 false（这次请求被拒绝）
    return !$record || $record['count'] <= RATE_LIMIT_MAX_API;
}

function checkAdminRateLimit() {
    if (!IS_LOGGED_IN) {
        return true;
    }

    $username = md5($_SESSION['admin_username'] ?? 'unknown');
    $key = 'admin_' . $username;
    $now = time();
    $windowStart = $now - RATE_LIMIT_WINDOW;

    $db = getDb();

    // 清理过期记录
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE timestamp < ?");
    $stmt->execute([$windowStart]);

    // 先检查当前计数是否已超过限制
    $stmt = $db->prepare("SELECT count FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();

    // 如果已超过限制，直接拒绝
    if ($record && $record['count'] >= RATE_LIMIT_MAX_ADMIN) {
        return false;
    }

    // 尝试原子地增加计数
    $stmt = $db->prepare("
        INSERT INTO rate_limits (id, count, timestamp)
        VALUES (?, 1, ?)
        ON CONFLICT(id) DO UPDATE
        SET count = CASE WHEN timestamp < ? THEN 1 ELSE count + 1 END,
            timestamp = CASE WHEN timestamp < ? THEN ? ELSE timestamp END
    ");
    $stmt->execute([$key, $now, $windowStart, $windowStart, $now]);

    // 再次检查
    $stmt = $db->prepare("SELECT count FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();

    return !$record || $record['count'] <= RATE_LIMIT_MAX_ADMIN;
}

// 通用管理后台频率限制函数（可自定义最大请求数）
function checkAdminRateLimitGeneric($maxRequests = 30, $windowSeconds = 60) {
    if (!IS_LOGGED_IN) {
        return true;
    }

    $username = md5($_SESSION['admin_username'] ?? 'unknown');
    $key = 'admin_generic_' . $username . '_' . $maxRequests;
    $now = time();
    $windowStart = $now - $windowSeconds;

    $db = getDb();

    // 清理过期记录
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE timestamp < ?");
    $stmt->execute([$windowStart]);

    // 先检查当前计数是否已超过限制
    $stmt = $db->prepare("SELECT count FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();

    // 如果已超过限制，直接拒绝
    if ($record && $record['count'] >= $maxRequests) {
        return false;
    }

    // 尝试原子地增加计数
    $stmt = $db->prepare("
        INSERT INTO rate_limits (id, count, timestamp)
        VALUES (?, 1, ?)
        ON CONFLICT(id) DO UPDATE
        SET count = CASE WHEN timestamp < ? THEN 1 ELSE count + 1 END,
            timestamp = CASE WHEN timestamp < ? THEN ? ELSE timestamp END
    ");
    $stmt->execute([$key, $now, $windowStart, $windowStart, $now]);

    // 再次检查
    $stmt = $db->prepare("SELECT count FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();

    return !$record || $record['count'] <= $maxRequests;
}

// ==================== 图片管理函数 ====================

function getImageCount($type = 'pc') {
    $db = getDb();
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM image_urls WHERE type = ?");
    $stmt->execute([$type]);
    $result = $stmt->fetch();
    return $result['cnt'] ?? 0;
}

function getImageUrls($type = 'pc', $page = 1, $perPage = 20) {
    $db = getDb();
    
    // 获取总数
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM image_urls WHERE type = ?");
    $stmt->execute([$type]);
    $total = $stmt->fetch()['cnt'] ?? 0;
    
    $totalPages = $total > 0 ? ceil($total / $perPage) : 0;
    $page = max(1, min($page, max(1, $totalPages)));
    $offset = ($page - 1) * $perPage;
    
    // 获取分页数据
    $stmt = $db->prepare("
        SELECT url FROM image_urls 
        WHERE type = ? 
        ORDER BY id DESC 
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$type, $perPage, $offset]);
    $urls = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return [
        'urls' => $urls,
        'total' => $total,
        'pages' => $totalPages,
        'page' => $page
    ];
}

function addImageUrls($urls, $type = 'pc') {
    $db = getDb();
    $added = 0;
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO image_urls (url, type) VALUES (?, ?)");
    
    foreach ($urls as $url) {
        $url = trim($url);
        if (isValidImageUrl($url)) {
            if ($stmt->execute([$url, $type])) {
                if ($stmt->rowCount() > 0) {
                    $added++;
                }
            }
        }
    }
    
    // 清除缓存
    clearCachedImageUrls($type);
    
    return $added;
}

function deleteImageUrl($url, $type = 'pc') {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM image_urls WHERE url = ? AND type = ?");
    $stmt->execute([trim($url), $type]);
    
    if ($stmt->rowCount() > 0) {
        clearCachedImageUrls($type);
        return true;
    }
    return false;
}

function clearImageUrls($type = 'pc') {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM image_urls WHERE type = ?");
    $result = $stmt->execute([$type]);
    
    if ($result) {
        clearCachedImageUrls($type);
    }
    return $result;
}

// 验证图片URL
function isValidImageUrl($url) {
    $url = trim($url);
    return !empty($url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// ==================== 缓存函数 ====================

function getCachedImageUrls($type) {
    $cacheFile = CACHE_DIR . "/{$type}_urls.cache";
    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < CACHE_TTL) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    return null;
}

function setCachedImageUrls($type, $urls) {
    $cacheFile = CACHE_DIR . "/{$type}_urls.cache";
    file_put_contents($cacheFile, json_encode($urls));
}

function clearCachedImageUrls($type) {
    $cacheFile = CACHE_DIR . "/{$type}_urls.cache";
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
}

// ==================== 统计函数 ====================

function updateCallCount($type, $returnType = 'redirect') {
    $date = date('Y-m-d');
    $db = getDb();
    
    // 检查今天的记录是否存在
    $stmt = $db->prepare("SELECT * FROM call_stats WHERE date = ?");
    $stmt->execute([$date]);
    $record = $stmt->fetch();
    
    if ($record) {
        // 更新现有记录
        $sql = "UPDATE call_stats SET 
            total = total + 1,
            pc = pc + ?,
            pe = pe + ?,
            api_count = api_count + ?,
            redirect_count = redirect_count + ?,
            json_count = json_count + ?,
            img_count = img_count + ?
            WHERE date = ?";
        $stmt = $db->prepare($sql);
        
        $isApi = ($type === 'api') ? 1 : 0;
        $isPc = ($type === 'pc') ? 1 : 0;
        $isPe = ($type === 'pe') ? 1 : 0;
        $isRedirect = ($returnType === 'redirect') ? 1 : 0;
        $isJson = ($returnType === 'json') ? 1 : 0;
        $isImg = ($returnType === 'img') ? 1 : 0;
        
        $stmt->execute([$isPc, $isPe, $isApi, $isRedirect, $isJson, $isImg, $date]);
    } else {
        // 插入新记录
        $sql = "INSERT INTO call_stats (date, total, pc, pe, api_count, redirect_count, json_count, img_count)
            VALUES (?, 1, ?, ?, ?, ?, ?, ?)";
        $stmt = $db->prepare($sql);
        
        $isApi = ($type === 'api') ? 1 : 0;
        $isPc = ($type === 'pc') ? 1 : 0;
        $isPe = ($type === 'pe') ? 1 : 0;
        $isRedirect = ($returnType === 'redirect') ? 1 : 0;
        $isJson = ($returnType === 'json') ? 1 : 0;
        $isImg = ($returnType === 'img') ? 1 : 0;
        
        $stmt->execute([$date, $isPc, $isPe, $isApi, $isRedirect, $isJson, $isImg]);
    }
    
    return getCallCount();
}

function getCallCount() {
    $db = getDb();
    
    // 获取总调用
    $stmt = $db->prepare("SELECT 
        COALESCE(SUM(total), 0) as total,
        COALESCE(SUM(pc), 0) as pc,
        COALESCE(SUM(pe), 0) as pe,
        COALESCE(SUM(api_count), 0) as api_count,
        COALESCE(SUM(redirect_count), 0) as redirect_count,
        COALESCE(SUM(json_count), 0) as json_count,
        COALESCE(SUM(img_count), 0) as img_count
        FROM call_stats");
    $stmt->execute();
    $totals = $stmt->fetch();
    
    // 获取每日数据
    $stmt = $db->prepare("SELECT date, total, pc, pe, api_count FROM call_stats ORDER BY date DESC LIMIT 365");
    $stmt->execute();
    $daily = [];
    while ($row = $stmt->fetch()) {
        $daily[$row['date']] = [
            'total' => (int)$row['total'],
            'pc' => (int)$row['pc'],
            'pe' => (int)$row['pe']
        ];
    }
    
    return [
        'total' => (int)$totals['total'],
        'pc' => (int)$totals['pc'],
        'pe' => (int)$totals['pe'],
        'api' => (int)$totals['api_count'],
        'daily' => $daily,
        'return_types' => [
            'redirect' => (int)$totals['redirect_count'],
            'json' => (int)$totals['json_count'],
            'img' => (int)$totals['img_count']
        ]
    ];
}

function getTotalCalls() {
    $db = getDb();
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM call_stats");
    $stmt->execute();
    return (int)$stmt->fetch()['total'];
}

// ==================== 日志函数 ====================

function logAdminAction($action) {
    $db = getDb();
    $ip = getClientIp();
    $time = date('Y-m-d H:i:s');
    $username = getCurrentUsername();

    $stmt = $db->prepare("INSERT INTO admin_logs (time, username, ip, action) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$time, $username, $ip, $action]);
}

function getAdminLogs($limit = 100) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM admin_logs ORDER BY id DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// ==================== 图片API函数 ====================

function getRandomImageUrl($type = 'pc') {
    $validUrls = getCachedImageUrls($type);
    
    if ($validUrls === null) {
        $db = getDb();
        $stmt = $db->prepare("SELECT url FROM image_urls WHERE type = ?");
        $stmt->execute([$type]);
        $validUrls = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($validUrls)) {
            return false;
        }
        
        setCachedImageUrls($type, $validUrls);
    }
    
    if (empty($validUrls)) {
        return false;
    }
    
    return $validUrls[array_rand($validUrls)];
}

// SSRF防护：安全获取远程图片
function fetchRemoteImage($url) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    $parsed = parse_url($url);
    $scheme = strtolower($parsed['scheme'] ?? '');
    $host = $parsed['host'] ?? '';

    if (!in_array($scheme, ['http', 'https'])) {
        return false;
    }

    if (empty($host)) {
        return false;
    }

    // 禁止访问本地服务
    $lowerHost = strtolower($host);
    $localHostnames = ['localhost', 'localhost.localdomain', 'local', '127.0.0.1', '0.0.0.0'];
    foreach ($localHostnames as $lh) {
        if ($lowerHost === $lh) {
            return false;
        }
    }

    // 解析IP并验证
    $ip = gethostbyname($host);
    if ($ip === $host || empty($ip)) {
        return false;
    }

    $forbiddenPatterns = [
        '/^(10\.)/',
        '/^172\.(1[6-9]|2[0-9]|3[01])\./',
        '/^192\.168\./',
        '/^127\./',
        '/^169\.254\./',
        '/^0\./',
        '/^224\./',
        '/^240\./',
        '/^255\.255\.255\.255$/',
        '/^(fe80|fc00|fd00|::1|fe80::)/i',
        '/^\[/', // IPv6 raw
    ];

    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $ip)) {
            return false;
        }
    }

    $port = $parsed['port'] ?? null;
    if ($port !== null && !in_array((int)$port, [80, 443, 8080, 8443])) {
        return false;
    }

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ImageFetcher/1.0)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    // 强制使用已验证的IP，防止DNS重绑定攻击
    $resolvePort = ($port !== null) ? (int)$port : ($scheme === 'https' ? 443 : 80);
    curl_setopt($ch, CURLOPT_RESOLVE, [$host . ':' . $resolvePort . ':' . $ip]);

    // 限制下载大小（5MB）
    $maxSize = 5 * 1024 * 1024;
    $data = '';
    $totalSize = 0;
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$data, $maxSize, &$totalSize) {
        $chunkLen = strlen($chunk);
        $totalSize += $chunkLen;
        if ($totalSize > $maxSize) {
            return 0; // 返回0中止传输
        }
        $data .= $chunk;
        return $chunkLen;
    });

    // Content-Type 验证
    $allowedTypes = [
        'image/jpeg' => true, 'image/jpg' => true,
        'image/png' => true, 'image/gif' => true,
        'image/webp' => true, 'image/bmp' => true,
        'image/svg+xml' => true, 'image/x-icon' => true,
    ];
    $contentTypeOk = true;
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$contentTypeOk, $allowedTypes) {
        $len = strlen($header);
        $header = trim($header);
        if (stripos($header, 'Content-Type:') === 0) {
            $type = trim(substr($header, 13));
            $type = strtolower(explode(';', $type)[0]);
            if (!isset($allowedTypes[$type])) {
                $contentTypeOk = false;
            }
        }
        return $len;
    });

    $success = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($success === false || !empty($error) || $httpCode !== 200) {
        return false;
    }

    if (!$contentTypeOk) {
        return false;
    }

    // 内容验证：检测文件签名（魔数）
    $allowedSignatures = [
        'ffd8ff' => 'jpg', // JPEG
        '89504e47' => 'png', // PNG
        '47494638' => 'gif', // GIF
        '52494646' => 'webp_check', // WEBP (starts with RIFF, need more check)
        '424d' => 'bmp', // BMP
    ];
    if (strlen($data) >= 4) {
        $signature = bin2hex(substr($data, 0, 4));
        $first2 = substr($signature, 0, 4);
        $isValidImage = false;
        foreach ($allowedSignatures as $sig => $type) {
            if (strpos($signature, $sig) === 0 || strpos($first2, $sig) === 0) {
                $isValidImage = true;
                break;
            }
        }
        if (!$isValidImage && strlen($data) >= 200) {
            // 备用：使用 finfo 检测
            $finfo = new finfo(FILEINFO_MIME_TYPE);
            $detectedMime = $finfo->buffer($data);
            if (!isset($allowedTypes[$detectedMime])) {
                return false;
            }
        } elseif (!$isValidImage) {
            return false;
        }
    }

    if (strlen($data) < 100) {
        return false;
    }

    return $data;
}

// 公共API处理函数
function handleImageApiRequest($type, $countType = null) {
    if (!checkApiRateLimit()) {
        header('HTTP/1.1 429 Too Many Requests');
        header('Content-Type: application/json; charset=utf-8');
        header('Retry-After: 60');
        echo json_encode([
            'success' => false,
            'error' => '请求过于频繁，请稍后再试',
            'retry_after' => 60
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    $validReturnTypes = ['redirect', 'json', 'img'];
    $returnType = isset($_GET['return']) ? $_GET['return'] : 'redirect';
    if (!in_array($returnType, $validReturnTypes)) {
        $returnType = 'redirect';
    }

    $cacheTime = isset($_GET['cache']) ? max(0, intval($_GET['cache'])) : 0;
    $imageUrl = getRandomImageUrl($type);

    if ($countType === null) {
        $countType = $type;
    }
    updateCallCount($countType, $returnType);

    if (!$imageUrl) {
        $errorMsg = ($type === 'pc') ? '没有找到可用的PC端图片' :
                    (($type === 'pe') ? '没有找到可用的移动端图片' : '没有找到可用的图片');

        if ($returnType === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => $errorMsg,
                'type' => $type,
                'timestamp' => time()
            ], JSON_UNESCAPED_UNICODE);
        } else {
            http_response_code(404);
            echo $errorMsg;
        }
        exit;
    }

    header("Cache-Control: public, max-age=$cacheTime");
    header("Expires: " . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');

    // 无缓存模式时添加随机参数避免CDN缓存
    if ($cacheTime == 0) {
        try {
            $randomParam = 'rand=' . bin2hex(random_bytes(8));
        } catch (Exception $e) {
            $randomParam = 'rand=' . substr(md5(uniqid((string)mt_rand(), true)), 0, 16);
        }
        $imageUrl .= (strpos($imageUrl, '?') === false ? '?' : '&') . $randomParam;
    }

    if ($returnType === 'json') {
        // JSON模式：不下载图片，直接返回URL信息，避免服务器带宽占用
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'url' => $imageUrl,
            'type' => $type,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($returnType === 'img') {
        // IMG模式：下载图片并代理返回（仍有SSRF保护）
        $imageData = fetchRemoteImage($imageUrl);
        if ($imageData) {
            $imageInfo = @getimagesizefromstring($imageData);
            if ($imageInfo && !empty($imageInfo['mime'])) {
                header("Content-Type: {$imageInfo['mime']}");
            } else {
                header('Content-Type: application/octet-stream');
            }
            echo $imageData;
        } else {
            http_response_code(404);
            echo '无法获取图片';
        }
    } else {
        // 默认：重定向
        header("Location: $imageUrl");
    }
    exit;
}

// 判断设备类型
function isMobileDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (empty($userAgent)) {
        return false;
    }

    $mobileAgents = [
        'android', 'webos', 'iphone', 'ipad', 'ipod', 'blackberry',
        'iemobile', 'opera mini', 'mobile', 'windows phone',
        'kindle', 'silk/', 'symbian', 'maemo', 'samsung', 'htc',
        'nokia', 'sony', 'lg-', 'lg /', 'lge ', 'bada', 'meego',
        'j2me', 'midp', 'wap', 'phone', 'pocket', 'pda',
    ];

    $lowerAgent = strtolower($userAgent);

    foreach ($mobileAgents as $agent) {
        if (strpos($lowerAgent, $agent) !== false) {
            return true;
        }
    }
    return false;
}

// ==================== 更新系统辅助函数 ====================

// 获取当前应用版本号（优先从数据库/版本文件）
function getAppVersion() {
    if (file_exists(APP_VERSION_FILE)) {
        $v = trim(file_get_contents(APP_VERSION_FILE));
        if (!empty($v)) return APP_VERSION;
        return $v;
    }
    return APP_VERSION;
}

// 写入当前版本号文件（数据备份，用于回滚识别
function setAppVersion($version) {
    return file_put_contents(APP_VERSION_FILE, $version) !== false;
}

// 获取更新日志
function getUpdateLogs($limit = 20) {
    try {
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM update_logs ORDER BY id DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// 写入更新日志
function logUpdateAction($fromVersion, $toVersion, $status, $message = '', $backupPath = '') {
    try {
        $db = getDb();
        $time = date('Y-m-d H:i:s');
        $username = function_exists('getCurrentUsername') ? getCurrentUsername() : 'system';
        $ip = function_exists('getClientIp') ? getClientIp() : 'unknown';
        $stmt = $db->prepare("INSERT INTO update_logs (from_version, to_version, status, message, backup_path, username, ip, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$fromVersion, $toVersion, $status, $message, $backupPath, $username, $ip, $time]);
    } catch (Exception $e) {
        return false;
    }
}

// 比较两个语义化版本号（返回 1: a>b, -1: a<b, 0: a==b）
function compareVersions($a, $b) {
    $a = preg_replace('/^v/', '', trim($a));
    $b = preg_replace('/^v/', '', trim($b));
    return version_compare($a, $b);
}

// 清理更新缓存文件
function cleanupUpdateCache() {
    $dirs = [UPDATE_CACHE_DIR];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        $items = glob(rtrim($dir, '/') . '/*');
        if ($items === false) continue;
        foreach ($items as $item) {
            if (is_file($item)) {
                @unlink($item);
            } elseif (is_dir($item)) {
                removeDirectory($item);
            }
        }
    }
}

// 递归删除目录
function removeDirectory($dir) {
    if (!is_dir($dir)) return;
    $items = glob(rtrim($dir, '/') . '/*');
    if ($items === false) return;
    foreach ($items as $item) {
        if (is_file($item)) {
            @unlink($item);
        } elseif (is_dir($item)) {
            removeDirectory($item);
        }
    }
    @rmdir($dir);
}

// 检查是否有更新检查结果缓存（避免频繁请求GitHub API）
function getCachedUpdateCheck() {
    if (!file_exists(UPDATE_CHECK_CACHE_FILE)) return null;
    if (time() - filemtime(UPDATE_CHECK_CACHE_FILE) > UPDATE_CHECK_CACHE_TTL) return null;
    $data = @json_decode(file_get_contents(UPDATE_CHECK_CACHE_FILE), true);
    return is_array($data) && !empty($data) ? $data : null;
}

// 写入更新检查缓存
function setCachedUpdateCheck($data) {
    @file_put_contents(UPDATE_CHECK_CACHE_FILE, json_encode($data, JSON_UNESCAPED_UNICODE));
}

// 清除更新检查缓存
function clearUpdateCheckCache() {
    if (file_exists(UPDATE_CHECK_CACHE_FILE)) {
        @unlink(UPDATE_CHECK_CACHE_FILE);
    }
}

// 检查目录是否可写（通过实际写入测试文件，避免 is_writable 误报）
function isDirReallyWritable($dir) {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            return false;
        }
    }
    $testFile = rtrim($dir, '/\\') . '/.write_test_' . mt_rand() . '.tmp';
    $result = @file_put_contents($testFile, 'test');
    if ($result !== false) {
        @unlink($testFile);
        return true;
    }
    return false;
}

// 检查服务器环境是否满足更新要求
function checkUpdateEnvironment() {
    $errors = [];
    $warnings = [];

    // 检查PHP扩展
    if (!extension_loaded('zip')) {
        $errors[] = '缺少 zip 扩展（用于解压更新包）';
    }
    if (!extension_loaded('curl') && !ini_get('allow_url_fopen')) {
        $errors[] = '需要 curl 扩展或 allow_url_fopen 开启（用于下载更新包）';
    }

    // 检查目录可写（只检查真正需要写入的目录，并通过实际写入测试）
    $writableDirs = [
        UPDATE_BACKUP_DIR => '备份目录',
        UPDATE_CACHE_DIR => '更新缓存目录',
        CACHE_DIR => '缓存目录',
    ];
    foreach ($writableDirs as $dir => $label) {
        if (!isDirReallyWritable($dir)) {
            $errors[] = '目录不可写: ' . $label . '（' . basename($dir) . '，更新需要写权限）';
        }
    }

    // 检查磁盘空间
    $freeSpace = @disk_free_space(__DIR__);
    if ($freeSpace !== false && $freeSpace < UPDATE_MIN_FREE_SPACE) {
        $errors[] = '磁盘空间不足（需要至少 ' . round(UPDATE_MIN_FREE_SPACE / 1024 / 1024, 1) . 'MB 剩余空间）';
    }

    // 检查执行时限
    if (ini_get('max_execution_time') > 0 && ini_get('max_execution_time') < UPDATE_TIMEOUT) {
        $warnings[] = 'PHP max_execution_time=' . ini_get('max_execution_time') . 's 可能不足，建议设置为 ' . UPDATE_TIMEOUT . 's 或以上';
    }

    return [
        'ok' => empty($errors),
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

// 判断路径是否受保护（不会被更新覆盖
function isPathProtected($relativePath) {
    $protected = unserialize(UPDATE_PROTECTED_PATHS);
    $relativePath = str_replace('\\', '/', $relativePath);
    $normalized = ltrim($relativePath, './');
    foreach ($protected as $pattern) {
        if (empty($pattern)) continue;
        if ($normalized === rtrim($pattern, '/') ||
            str_starts_with_custom($normalized, $pattern)) {
            return true;
        }
    }
    return false;
}

// 兼容低版本PHP的路径前缀检查
function str_starts_with_custom($haystack, $needle) {
    if (function_exists('str_starts_with')) {
        return str_starts_with($haystack, $needle);
    }
    return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
}

// 检查文件扩展名是否在白名单内
function isExtensionAllowed($filename) {
    $allowed = unserialize(UPDATE_ALLOWED_EXTENSIONS);
    if (empty($allowed)) return true;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed);
}
?>
