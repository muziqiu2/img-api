<?php
/**
 * 魔法师随机图片API - 配置文件
 * 使用 SQLite 数据库存储
 */

// 统一应用时区：统计按自然日归档、日志时间戳等均依赖时区，
// 未设置时 PHP 默认随服务器时区，导致跨机器部署时「按日」口径不一致。
// 如需自定义，修改下方或改为从网站设置读取。
date_default_timezone_set('Asia/Shanghai');

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

// 统计自动落库默认间隔（秒）：作为后台未配置（或配置非法）时的回退默认值。
// API 写入统计缓冲后，每隔该间隔把缓冲合并进 SQLite，
// 避免「长期不打开后台」导致首页计数停滞、数据悬在易丢失的缓存文件中。
// 实际生效值由 getStatsAutoFlushInterval() 读取后台「网站设置」配置，0 表示禁用自动落库。
define('STATS_AUTO_FLUSH_INTERVAL', 60);

// 会话配置
define('SESSION_TIMEOUT', 3600); // 会话超时时间(秒)

// 频率限制配置
define('RATE_LIMIT_WINDOW', 60); // 60秒窗口
define('RATE_LIMIT_MAX_API', 100); // API每分钟最大请求数
define('RATE_LIMIT_MAX_ADMIN', 10); // 管理后台每分钟最大请求数

// 代理配置（仅在确定服务器前方有可信代理时启用）
define('TRUST_PROXY_HEADERS', false); // 是否信任代理头（如 X-Forwarded-For）

// ==================== 版本与自动更新配置 ====================

define('APP_VERSION', '3.2.1.4'); // 当前应用版本号（Semantic Versioning）
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
// PHP 7.4+ 支持 define() 直接定义数组，无需 serialize 序列化存储。
define('UPDATE_PROTECTED_PATHS', [
    'data/',
    'admin/logs/',
    'data/cache/',
    'data/backups/',
    'data/update_cache/',
    '.git/',
    '.htaccess',
    '.router.php',
]);

// 更新时允许被替换的文件扩展名白名单（空数组表示不限制扩展名，仅受目录保护）
define('UPDATE_ALLOWED_EXTENSIONS', [
    'php', 'txt', 'md', 'html', 'htm', 'css', 'js', 'json',
    'svg', 'png', 'jpg', 'jpeg', 'gif', 'webp', 'ico',
    'woff', 'woff2', 'ttf', 'eot', 'map',
]);

// ==================== 客户端IP获取函数 ====================

function getClientIp() {
    $ip = null;

    // 如果配置了信任代理头，则检查代理相关头部
    if (TRUST_PROXY_HEADERS) {
        // X-Forwarded-For: client, proxy1, proxy2
        // ⚠ 注意：XFF 最右侧地址可被客户端/上游直接伪造，仅应信任「由可信代理追加、
        //   且为公网（非私有/非保留段）」的最右 IP。故从右往左跳过私有/保留段取第一个公网 IP。
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (!empty($xff)) {
            $ips = array_reverse(array_map('trim', explode(',', $xff)));
            foreach ($ips as $candidate) {
                // 跳过内网/保留段（这些多为代理内网地址，不可作为真实客户端 IP）
                if (filter_var($candidate, FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $ip = $candidate;
                    break;
                }
            }
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
        // 记录异常：用户配置缺失时不再静默重建默认账号（避免密码被静默重置回默认值）
        @error_log('[img-api] user_config 记录缺失，请检查数据库完整性 (data/app.db)');
        return [
            'username' => '',
            'password_hash' => '',
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

// 检测是否仍在使用默认密码（用于强制修改提示）
function isDefaultPassword() {
    $config = getUserConfig();
    if (empty($config['password_hash'])) {
        return false; // 配置异常时不做判断，避免误锁
    }
    return password_verify('123456', $config['password_hash']);
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

// app_settings 的 APCu 缓存 key。设置项多在高频路径读取（image_mode/rate_limit/enable_json…），
// 用短 TTL 缓存消除每请求的 SQLite 查询；后台写入时调 clearAppSettingCache 及时失效。
function appSettingCacheKey($key) {
    return 'appset:' . sha1($key);
}

function clearAppSettingCache($key) {
    if (function_exists('apcu_delete') && @apcu_enabled()) {
        @apcu_delete(appSettingCacheKey($key));
    }
}

function getAppSetting($key, $default = '') {
    if (function_exists('apcu_fetch') && @apcu_enabled()) {
        $cacheKey = appSettingCacheKey($key);
        if (apcu_exists($cacheKey)) {
            return apcu_fetch($cacheKey);
        }
        $db = getDb();
        $stmt = $db->prepare("SELECT value FROM app_settings WHERE key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        $value = $result ? ($result['value'] ?? $default) : $default;
        // 短暂缓存（30 秒），兼顾读性能与后台改动的及时生效
        @apcu_store($cacheKey, $value, 30);
        return $value;
    }
    $db = getDb();
    $stmt = $db->prepare("SELECT value FROM app_settings WHERE key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? ($result['value'] ?? $default) : $default;
}

function setAppSetting($key, $value) {
    clearAppSettingCache($key); // 写入前失效旧缓存，避免读到过期值
    $db = getDb();
    $stmt = $db->prepare("INSERT OR REPLACE INTO app_settings (key, value) VALUES (?, ?)");
    return $stmt->execute([$key, $value]);
}

function deleteAppSetting($key) {
    clearAppSettingCache($key);
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM app_settings WHERE key = ?");
    return $stmt->execute([$key]);
}

// 获取网站展示设置（前台首页用），未设置时返回默认值
function getSiteSettings() {
    $defaults = [
        'site_title'     => '魔法师随机图片API',
        'site_name'      => '魔法师随机图片API',
        'site_lead'      => '免费提供高质量随机二次元图片API服务',
        'site_copyright' => '魔法师随机图片API',
        'site_icp'       => '',
    ];
    $settings = [];
    foreach ($defaults as $key => $default) {
        $settings[$key] = getAppSetting($key, $default);
    }
    return $settings;
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

// 限流计数是否为内存级（APCu）：单机部署时可用，消除每请求的 SQLite 写锁竞争。
// ⚠️ 仅限单机：APCu 为共享内存、进程间可见但不跨主机，负载均衡多机时各节点各自计数，
//    限流会被放大 N 倍，故仅在单机时启用，否则回退到 SQLite 计数。
function rateLimitCanUseApcu() {
    return function_exists('apcu_fetch') && is_callable('apcu_inc') && @apcu_enabled();
}

// 限流入口：优先 APCu 内存计数（内存级、无 SQLite 写），无 APCu 时降级回 SQLite 计数。
// 限流计数属可丢弃数据（重启/清缓存仅使窗口重新计数），正是内存计数的适用场景。
function applyRateLimit($key, $maxRequests, $windowSeconds) {
    if (rateLimitCanUseApcu()) {
        return applyRateLimitApcu($key, $maxRequests, $windowSeconds);
    }
    return applyRateLimitDb($key, $maxRequests, $windowSeconds);
}

// APCu 固定窗口计数：按时间窗口分桶，窗口过期由 TTL 自动清理，无需手动 DELETE。
// apcu_inc 为原子操作（跨 PHP-FPM worker），效果等价于锁，且无任何 DB 写。
function applyRateLimitApcu($key, $maxRequests, $windowSeconds) {
    // 窗口分桶号，保证同窗口内同一 key 落在同一 bucket
    $bucket = (int)floor(time() / $windowSeconds);
    $apcuKey = 'rl:' . $bucket . ':' . md5($key);
    // apcu_inc(key, step, &success, ttl)：key 首次创建时按 step 计为 1；TTL 留 30s 余量避免窗口抖动误清
    $count = apcu_inc($apcuKey, 1, $success, $windowSeconds + 30);
    if ($success) {
        return $count <= $maxRequests;
    }
    // APCu 内部异常：保守放行，避免误封（DB 降级路径会兜底）
    return true;
}

// 限流降级路径：SQLite 滑动窗口计数（无 APCu 时使用，保持原有行为）
function applyRateLimitDb($key, $maxRequests, $windowSeconds) {
    $now = time();
    $windowStart = $now - $windowSeconds;

    $db = getDb();

    // 概率化清理过期记录（避免每次请求都执行全表 DELETE，降低 SQLite 写负载）
    if (mt_rand(1, 50) === 1) {
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE timestamp < ?");
        $stmt->execute([$windowStart]);
    }

    // 先检查当前计数是否已超过限制
    $stmt = $db->prepare("SELECT count FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();

    // 如果已超过限制，直接拒绝
    if ($record && (int)$record['count'] >= $maxRequests) {
        return false;
    }

    // 原子地增加计数（UPSERT：窗口过期则重置为 1，否则 +1）
    $stmt = $db->prepare("
        INSERT INTO rate_limits (id, count, timestamp)
        VALUES (?, 1, ?)
        ON CONFLICT(id) DO UPDATE
        SET count = CASE WHEN timestamp < ? THEN 1 ELSE count + 1 END,
            timestamp = CASE WHEN timestamp < ? THEN ? ELSE timestamp END
    ");
    $stmt->execute([$key, $now, $windowStart, $windowStart, $now]);

    // 复查：若本次递增导致超限，补偿回退（近似回滚，最多瞬时超限 1 个请求）
    $stmt = $db->prepare("SELECT count FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();
    if ($record && (int)$record['count'] > $maxRequests) {
        $stmt = $db->prepare("UPDATE rate_limits SET count = count - 1 WHERE id = ?");
        $stmt->execute([$key]);
        return false;
    }

    return true;
}

// 可调限流值（后台可配置，存 app_settings 表，未设置时回退到常量默认值）
// 范围钳制在 1 ~ 10000，防止配置异常导致误封或绕过限制
function getApiRateLimitMax() {
    $v = intval(getAppSetting('rate_limit_api', ''));
    return ($v >= 1 && $v <= 10000) ? $v : RATE_LIMIT_MAX_API;
}

function getAdminRateLimitMax() {
    $v = intval(getAppSetting('rate_limit_admin', ''));
    return ($v >= 1 && $v <= 10000) ? $v : RATE_LIMIT_MAX_ADMIN;
}

// 图片访问模式（后台可配置，存 app_settings 表，未设置时默认 302 跳转）
// proxy    —— 代理模式：服务器下载图片并转发给用户，隐藏真实图片链接
// redirect —— 302 跳转模式：直接重定向到真实图片 URL（默认，兼容原行为）
// 模式由后台完全决定，API 的 return 参数不再生效
function getImageAccessMode() {
    $mode = getAppSetting('image_mode', '');
    return ($mode === 'proxy') ? 'proxy' : 'redirect';
}

// JSON 格式输出开关（后台可配置，存 app_settings 表，未设置时默认关闭）
// 开启后可通过 ?format=json 获取图片地址 JSON。
// 注意：当图片访问模式为「代理模式」时，JSON 会返回真实图片 URL，
// 从而暴露代理模式本应隐藏的图片链接，仅建议在确认无泄露风险时开启。
function isJsonEnabled() {
    return getAppSetting('enable_json', '0') === '1';
}

// 统计自动落库间隔（后台可配置，存 app_settings 表，未设置时回退到常量默认值）
// 范围：0 表示禁用自动落库；10 ~ 86400 秒（1天）为有效值，过小会退化为高频写库、过大则近似禁用。
// 非法值均回退到 STATS_AUTO_FLUSH_INTERVAL 默认值。
function getStatsAutoFlushInterval() {
    $raw = getAppSetting('stats_auto_flush_interval', '');
    // 未配置（空字符串）时回退默认；需区分「未设置」与「显式设为 0 禁用」
    if ($raw === '') {
        return STATS_AUTO_FLUSH_INTERVAL;
    }
    $v = intval($raw);
    if ($v === 0) {
        return 0; // 显式禁用
    }
    return ($v >= 10 && $v <= 86400) ? $v : STATS_AUTO_FLUSH_INTERVAL;
}

function checkApiRateLimit() {
    $ip = md5(getClientIp());
    return applyRateLimit('api_' . $ip, getApiRateLimitMax(), RATE_LIMIT_WINDOW);
}

function checkAdminRateLimit() {
    if (!IS_LOGGED_IN) {
        return true;
    }

    $username = md5($_SESSION['admin_username'] ?? 'unknown');
    return applyRateLimit('admin_' . $username, getAdminRateLimitMax(), RATE_LIMIT_WINDOW);
}

// 通用管理后台频率限制函数（可自定义最大请求数）
function checkAdminRateLimitGeneric($maxRequests = 30, $windowSeconds = 60) {
    if (!IS_LOGGED_IN) {
        return true;
    }

    $username = md5($_SESSION['admin_username'] ?? 'unknown');
    return applyRateLimit('admin_generic_' . $username . '_' . $maxRequests, $maxRequests, $windowSeconds);
}

// ==================== 图片管理函数 ====================

function getImageCount($type = 'pc') {
    // 复用数量缓存（避免每页/每请求 COUNT(*)，图片数低频变化且增删路径均已清缓存）
    $cached = getCachedImageCount($type);
    if ($cached !== null) {
        return $cached;
    }

    $db = getDb();
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM image_urls WHERE type = ?");
    $stmt->execute([$type]);
    $result = $stmt->fetch();
    $count = $result['cnt'] ?? 0;

    setCachedImageCount($type, $count);
    return $count;
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
    // LIMIT/OFFSET 必须显式绑定为整型，避免部分 SQLite 驱动将字符串参数误判
    $stmt->bindValue(1, $type, PDO::PARAM_STR);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
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

// 安全 URL 校验（含 SSRF 防护：仅允许公网 http/https 地址）
// 校验通过时通过引用返回解析结果（host/ip/port），供 fetchRemoteImage 固定 IP 使用
function isSafeRemoteUrl($url, &$resolved = null) {
    $resolved = null;
    $url = trim($url);

    if (empty($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
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

    // 禁止本地主机名
    $lowerHost = strtolower($host);
    $localHostnames = ['localhost', 'localhost.localdomain', 'local', '127.0.0.1', '0.0.0.0', '[::1]'];
    if (in_array($lowerHost, $localHostnames)) {
        return false;
    }

    // 解析 IP 并禁止内网/保留地址
    // 若 host 本身就是 IP 字面量，直接用它校验；否则通过 DNS 解析域名
    $rawIp = filter_var($host, FILTER_VALIDATE_IP);
    if ($rawIp !== false) {
        $ip = $rawIp;
    } else {
        $ip = gethostbyname($host);
        // gethostbyname 解析失败（含域名无法解析、仅 IPv6 地址等情况）时返回原 host
        if ($ip === $host || empty($ip)) {
            return false;
        }
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
        // CGNAT 共享地址段 100.64.0.0/10
        '/^100\.(6[4-9]|[7-9][0-9]|1[0-1][0-9]|12[0-7])\./',
        // 基准测试网段 198.18.0.0/15
        '/^198\.(1[89])\./',
        // 文档/测试专用网段 TEST-NET-1/2/3
        '/^192\.0\.2\./',
        '/^198\.51\.100\./',
        '/^203\.0\.113\./',
        '/^(fe80|fc00|fd00|::1|fe80::)/i',
        '/^\[/', // IPv6 raw
    ];
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $ip)) {
            return false;
        }
    }

    // 双保险：用 PHP 官方过滤器覆盖其自带的私有/保留网段（10/8、172.16/12、192.168/16、
    // 0/8、127/8、169.254/16、组播与 240/4 等）。
    // 注意：filter_var 的 FILTER_FLAG_NO_RES_RANGE 并不覆盖 CGNAT(100.64/10)、测试网段与
    // 198.18/15 基准段——这些由上方正则补足。二者叠加后才是完整兜底，避免单一路径漏判。
    if (!filter_var($ip, FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
    }

    // 端口白名单（仅允许常见 Web 端口，防止探测内网非 Web 服务）
    $port = $parsed['port'] ?? null;
    if ($port !== null && !in_array((int)$port, [80, 443, 8080, 8443])) {
        return false;
    }

    $resolved = [
        'host'   => $host,
        'ip'     => $ip,
        'port'   => $port !== null ? (int)$port : ($scheme === 'https' ? 443 : 80),
        'scheme' => $scheme,
        'url'    => $url,
    ];
    return true;
}

// 验证图片URL（兼容旧接口，实际委托给 isSafeRemoteUrl）
function isValidImageUrl($url) {
    return isSafeRemoteUrl($url);
}

// 将（可能是相对的）重定向地址解析为绝对地址
function resolveRelativeUrl($baseUrl, $redirectUrl) {
    $redirectUrl = trim($redirectUrl);
    if (filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
        return $redirectUrl;
    }

    $parsed = parse_url($baseUrl);
    $scheme = $parsed['scheme'] ?? 'http';
    $host = $parsed['host'] ?? '';
    if (empty($host)) {
        return $redirectUrl;
    }
    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
    $origin = $scheme . '://' . $host . $port;

    if (strpos($redirectUrl, '//') === 0) {
        return $scheme . ':' . $redirectUrl;
    }
    if (strpos($redirectUrl, '/') === 0) {
        return $origin . $redirectUrl;
    }
    // 相对路径：基于当前路径的目录部分拼接
    $path = $parsed['path'] ?? '/';
    $baseDir = preg_replace('#/[^/]*$#', '/', $path);
    return $origin . $baseDir . $redirectUrl;
}

// ==================== 缓存函数 ====================

// 图片数量缓存文件路径
// （取代旧版"全量 URL 列表缓存"：列表过大时每请求 json_decode 全量数组，
//  内存 O(n) 且解析耗时，是高并发下的内存压力来源；数量缓存为 O(1)）
function imageCountCacheFile($type) {
    return CACHE_DIR . "/{$type}_count.cache";
}

function getCachedImageCount($type) {
    $cacheFile = imageCountCacheFile($type);
    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < CACHE_TTL) {
        $count = @file_get_contents($cacheFile);
        return is_numeric($count) ? (int)$count : null;
    }
    return null;
}

function setCachedImageCount($type, $count) {
    @file_put_contents(imageCountCacheFile($type), (string)(int)$count);
}

function clearCachedImageUrls($type) {
    // 失效 APCu 内存 id 缓存
    if (function_exists('apcu_delete') && @apcu_enabled()) {
        @apcu_delete('imgids:' . $type);
    }
    // 同时清理旧版全量列表缓存、数量缓存、旧 maxid 缓存与新的 id 列表缓存，避免增删图后残留
    foreach ([CACHE_DIR . "/{$type}_urls.cache", CACHE_DIR . "/{$type}_maxid.cache", imageCountCacheFile($type), imageIdListCacheFile($type)] as $cacheFile) {
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }
}

// 图片 id 列表缓存：随机取图时一次性载入该类型的全部 id，用 array_rand 均匀随机选取。
// 相比 rowid 范围随机（对 id 空洞敏感、分布不均，会反复命中同一张）更均匀；
// 相比缓存全量 url 列表（每请求 json_decode 大数组、内存 O(n)）更轻量——id 为 int，
// 数千条也仅几 KB~几十 KB，解析开销可忽略。
function imageIdListCacheFile($type) {
    return CACHE_DIR . "/{$type}_ids.cache";
}

function getCachedImageIds($type) {
    // APCu 内存缓存优先：O(1) 无文件 IO（与限流一致，仅 APCu 可用时启用），
    // 避免每请求 json_decode 缓存文件的耗时
    $apcuKey = 'imgids:' . $type;
    if (function_exists('apcu_fetch') && is_callable('apcu_inc') && @apcu_enabled()) {
        $ids = apcu_fetch($apcuKey);
        if (is_array($ids) && $ids !== []) {
            return $ids;
        }
    }
    // 无 APCu 时降级文件缓存
    $cacheFile = imageIdListCacheFile($type);
    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < CACHE_TTL) {
        $ids = @json_decode(@file_get_contents($cacheFile), true);
        if (is_array($ids) && $ids !== []) {
            return $ids;
        }
    }
    return null;
}

function setCachedImageIds($type, $ids, $ttl = CACHE_TTL) {
    $ids = array_values(array_map('intval', $ids));
    if (function_exists('apcu_store') && @apcu_enabled()) {
        @apcu_store('imgids:' . $type, $ids, $ttl);
    }
    @file_put_contents(imageIdListCacheFile($type), json_encode($ids));
}

// ==================== 统计函数 ====================

// 当日统计缓冲文件路径
function statsBufferFile($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    return CACHE_DIR . '/call_stats_' . $date . '.json';
}

// 读取当日统计缓冲（无缓冲或损坏时返回全零）
function readStatsBuffer($date = null) {
    $file = statsBufferFile($date);
    if (!file_exists($file)) {
        return ['total' => 0, 'pc' => 0, 'pe' => 0, 'api' => 0, 'redirect' => 0, 'json' => 0, 'img' => 0];
    }
    $data = @json_decode(file_get_contents($file), true);
    return is_array($data) ? array_merge(
        ['total' => 0, 'pc' => 0, 'pe' => 0, 'api' => 0, 'redirect' => 0, 'json' => 0, 'img' => 0],
        $data
    ) : ['total' => 0, 'pc' => 0, 'pe' => 0, 'api' => 0, 'redirect' => 0, 'json' => 0, 'img' => 0];
}

// 原子写入统计缓冲（先写临时文件再 rename）
function writeStatsBuffer($data, $date = null) {
    $file = statsBufferFile($date);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $tmp = $file . '.tmp.' . getmypid() . '.' . uniqid();
    if (@file_put_contents($tmp, json_encode($data)) === false) {
        return false;
    }
    return @rename($tmp, $file);
}

// 直接把增量写入 SQLite（降级路径 / 缓冲合并使用）
function writeCallStatsDirect($date, $isApi, $isPc, $isPe, $isRedirect, $isJson, $isImg, $totalInc = 1) {
    $db = getDb();

    // 检查今天的记录是否存在
    $stmt = $db->prepare("SELECT * FROM call_stats WHERE date = ?");
    $stmt->execute([$date]);
    $record = $stmt->fetch();

    if ($record) {
        // 更新现有记录
        $stmt = $db->prepare("UPDATE call_stats SET 
            total = total + ?,
            pc = pc + ?,
            pe = pe + ?,
            api_count = api_count + ?,
            redirect_count = redirect_count + ?,
            json_count = json_count + ?,
            img_count = img_count + ?
            WHERE date = ?");
        $stmt->execute([$totalInc, $isPc, $isPe, $isApi, $isRedirect, $isJson, $isImg, $date]);
    } else {
        // 插入新记录
        $stmt = $db->prepare("INSERT INTO call_stats (date, total, pc, pe, api_count, redirect_count, json_count, img_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$date, $totalInc, $isPc, $isPe, $isApi, $isRedirect, $isJson, $isImg]);
    }
    return true;
}

// ============ 统计：APCu 内存计数（单机可选，消除每请求文件锁+整文件重写 I/O） ============

// 全部统计列字段名（file/APCu/SQLite 三处保持一致）
function statsFields() {
    return ['total', 'pc', 'pe', 'api', 'redirect', 'json', 'img'];
}

// APCu 统计计数 key（按日期分桶，跨日互不干扰）
function statsApcuKey($field, $date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    return 'stats:' . $date . ':' . $field;
}

// 统计是否可用 APCu 内存计数（单机、启用 apcu 扩展时）
function statsCanUseApcu() {
    return function_exists('apcu_fetch') && is_callable('apcu_inc') && @apcu_enabled();
}

// 读取并清空指定日期的 APCu 统计计数；仅清存量>0 的计数器，避免清掉并发新加的数据
function takeStatsApcu($date) {
    $delta = ['total' => 0, 'pc' => 0, 'pe' => 0, 'api' => 0, 'redirect' => 0, 'json' => 0, 'img' => 0];
    foreach ($delta as $f => $v) {
        $c = (int)@apcu_fetch(statsApcuKey($f, $date));
        if ($c > 0) {
            $delta[$f] = $c;
            @apcu_delete(statsApcuKey($f, $date));
        }
    }
    return $delta;
}

// 把 APCu 内存计数合并进 SQLite 并清空（今日+昨日，覆盖滚动日界可能残留的旧日期 key）
function flushStatsApcu() {
    if (!statsCanUseApcu()) {
        return;
    }
    foreach ([date('Y-m-d'), date('Y-m-d', strtotime('-1 day'))] as $date) {
        $d = takeStatsApcu($date);
        if (($d['total'] ?? 0) <= 0) {
            continue;
        }
        writeCallStatsDirect($date, $d['api'], $d['pc'], $d['pe'], $d['redirect'], $d['json'], $d['img'], $d['total']);
    }
}

// 把当日统计缓冲合并进 SQLite 并清空缓冲（在读取统计或自动落库时调用）
function flushStatsBuffer($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    $file = statsBufferFile($date);
    if (!file_exists($file)) {
        return;
    }

    // 与 updateCallCount 使用同一把文件锁，避免「并发写缓冲」与「合并清空缓冲」交错
    // 导致刚写入的计数被 unlink 丢失。拿不到锁则跳过，留待下次合并。
    $lockFile = $file . '.lock';
    $fp = @fopen($lockFile, 'c');
    if ($fp === false || !flock($fp, LOCK_EX)) {
        if (is_resource($fp)) fclose($fp);
        return;
    }

    $buf = readStatsBuffer($date);
    if (($buf['total'] ?? 0) <= 0) {
        @unlink($file);
        flock($fp, LOCK_UN);
        fclose($fp);
        return;
    }
    writeCallStatsDirect(
        $date,
        (int)($buf['api'] ?? 0),
        (int)($buf['pc'] ?? 0),
        (int)($buf['pe'] ?? 0),
        (int)($buf['redirect'] ?? 0),
        (int)($buf['json'] ?? 0),
        (int)($buf['img'] ?? 0),
        (int)($buf['total'] ?? 0)
    );
    @unlink($file);

    flock($fp, LOCK_UN);
    fclose($fp);
}

// 读取统计前合并所有残留的统计缓冲（含历史日期）。
// 若某天有调用但之后一直无人触发统计读取，缓冲文件会残留，此处兜底合并，避免数据永久丢失。
function flushAllStatsBuffers() {
    flushStatsApcu(); // APCu 内存计数（若有）
    $files = glob(CACHE_DIR . '/call_stats_*.json');
    if ($files === false) return;
    foreach ($files as $file) {
        $date = str_replace('call_stats_', '', basename($file, '.json'));
        if ($date === '' || $date === '__history__') continue;
        flushStatsBuffer($date);
    }
}

// 归档过期统计：每日明细保留 365 天，总量累加到 __history__ 行永久保留
function archiveOldCallStats() {
    // 每天只归档一次
    $markerFile = CACHE_DIR . '/stats_archive_marker';
    if (file_exists($markerFile) && date('Y-m-d', filemtime($markerFile)) === date('Y-m-d')) {
        return;
    }
    @touch($markerFile);

    $db = getDb();
    $cutoff = date('Y-m-d', strtotime('-365 days'));

    // 汇总过期明细各列总量
    $stmt = $db->prepare("SELECT 
        COALESCE(SUM(total),0) as t, COALESCE(SUM(pc),0) as pc, COALESCE(SUM(pe),0) as pe,
        COALESCE(SUM(api_count),0) as api, COALESCE(SUM(redirect_count),0) as rd,
        COALESCE(SUM(json_count),0) as js, COALESCE(SUM(img_count),0) as im
        FROM call_stats WHERE date < ? AND date != '__history__'");
    $stmt->execute([$cutoff]);
    $row = $stmt->fetch();

    if ($row && (int)$row['t'] > 0) {
        // 累加到历史归档行（保证总调用次数永久保留）
        $stmt = $db->prepare("INSERT INTO call_stats (date, total, pc, pe, api_count, redirect_count, json_count, img_count)
            VALUES ('__history__', ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(date) DO UPDATE SET
                total = total + excluded.total,
                pc = pc + excluded.pc,
                pe = pe + excluded.pe,
                api_count = api_count + excluded.api_count,
                redirect_count = redirect_count + excluded.redirect_count,
                json_count = json_count + excluded.json_count,
                img_count = img_count + excluded.img_count");
        $stmt->execute([(int)$row['t'], (int)$row['pc'], (int)$row['pe'], (int)$row['api'], (int)$row['rd'], (int)$row['js'], (int)$row['im']]);
    }

    // 删除过期明细
    $stmt = $db->prepare("DELETE FROM call_stats WHERE date < ? AND date != '__history__'");
    $stmt->execute([$cutoff]);
}

// 按固定间隔自动落库：把统计缓冲合并进 SQLite，避免「长期不打开后台」导致计数滞留缓存而丢失。
// 使用独立落库锁 + 时间戳标记文件做节流与并发互斥；落库是后台性维护动作，不阻塞 API 响应。
function autoFlushStatsIfDue() {
    $interval = getStatsAutoFlushInterval();
    if ($interval <= 0) {
        return; // 已在后台禁用自动落库
    }

    $markerFile = CACHE_DIR . '/stats_flush_marker';
    $now = time();

    // 快路径：距上次落库未到间隔，直接跳过（无锁，开销极低）
    if (file_exists($markerFile)) {
        $last = (int)@file_get_contents($markerFile);
        if ($last > 0 && ($now - $last) < $interval) {
            return;
        }
    }

    // 慢路径：需要落库，用独立锁防止多个并发请求重复落库
    $lockFile = CACHE_DIR . '/stats_flush.lock';
    $fp = @fopen($lockFile, 'c');
    if ($fp === false || !flock($fp, LOCK_EX | LOCK_NB)) {
        if (is_resource($fp)) fclose($fp);
        return; // 已有其他请求在落库，本次跳过
    }

    // 双重检查：拿到锁后再次校验时间戳，避免阻塞等待期间已被其他请求落库
    if (file_exists($markerFile)) {
        $last = (int)@file_get_contents($markerFile);
        if ($last > 0 && ($now - $last) < $interval) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return;
        }
    }

    flushAllStatsBuffers();
    archiveOldCallStats();

    // 记录本次落库时间
    @file_put_contents($markerFile, (string)$now);

    flock($fp, LOCK_UN);
    fclose($fp);
}

// 统计写入：先更新文件缓冲（低开销，不触碰 SQLite 写锁），读取统计时再合并入库
function updateCallCount($type, $returnType = 'redirect', $deviceType = null) {
    $date = date('Y-m-d');

    // 计算各列增量；api 入口按实际设备类型（$deviceType）同时计入 pc/pe 分布
    $isApi = ($type === 'api') ? 1 : 0;
    $isPc = (($type === 'pc') || ($type === 'api' && $deviceType === 'pc')) ? 1 : 0;
    $isPe = (($type === 'pe') || ($type === 'api' && $deviceType === 'pe')) ? 1 : 0;
    $isRedirect = ($returnType === 'redirect') ? 1 : 0;
    $isJson = ($returnType === 'json') ? 1 : 0;
    $isImg = ($returnType === 'img') ? 1 : 0;

    // APCu 内存计数（首选）：apcu_inc 原子递增，热路径无文件锁、无整文件重写、无 SQLite 写锁。
    // 计数属于「可延迟」数据，由 autoFlushStatsIfDue 按固定间隔落库持久化，重启丢失范围极小。
    if (statsCanUseApcu()) {
        // TTL 覆盖 2 天窗口：即使跨日滚动、偶发数天无人触发落库，计数也不至于提前过期
        $ttl = 86400 * 2 + 3600;
        apcu_inc(statsApcuKey('total', $date), 1, $ok1, $ttl);
        if ($isPc) apcu_inc(statsApcuKey('pc', $date), 1, $ok2, $ttl);
        if ($isPe) apcu_inc(statsApcuKey('pe', $date), 1, $ok3, $ttl);
        if ($isApi) apcu_inc(statsApcuKey('api', $date), 1, $ok4, $ttl);
        if ($isRedirect) apcu_inc(statsApcuKey('redirect', $date), 1, $ok5, $ttl);
        if ($isJson) apcu_inc(statsApcuKey('json', $date), 1, $ok6, $ttl);
        if ($isImg) apcu_inc(statsApcuKey('img', $date), 1, $ok7, $ttl);
        // 按间隔自动落库：让计数即使不打开后台也能及时持久化到 SQLite
        autoFlushStatsIfDue();
        return true;
    }

    // 无 APCu 回退：文件缓冲（flock 锁文件保护临界区，数据用 tmp+rename 原子写入）
    $lockFile = statsBufferFile($date) . '.lock';
    $fp = @fopen($lockFile, 'c');
    if ($fp === false || !flock($fp, LOCK_EX)) {
        if (is_resource($fp)) fclose($fp);
        // 降级：直接写库
        return writeCallStatsDirect($date, $isApi, $isPc, $isPe, $isRedirect, $isJson, $isImg);
    }

    $buf = readStatsBuffer($date);
    $buf['total'] = (int)($buf['total'] ?? 0) + 1;
    $buf['pc'] = (int)($buf['pc'] ?? 0) + $isPc;
    $buf['pe'] = (int)($buf['pe'] ?? 0) + $isPe;
    $buf['api'] = (int)($buf['api'] ?? 0) + $isApi;
    $buf['redirect'] = (int)($buf['redirect'] ?? 0) + $isRedirect;
    $buf['json'] = (int)($buf['json'] ?? 0) + $isJson;
    $buf['img'] = (int)($buf['img'] ?? 0) + $isImg;
    writeStatsBuffer($buf, $date);

    flock($fp, LOCK_UN);
    fclose($fp);

    // 按间隔自动落库：让计数即使不打开后台也能及时持久化到 SQLite
    autoFlushStatsIfDue();

    return true;
}

// 纯查询统计数据（不合并缓冲、不归档，只读操作）。
// 供公开页面（首页）使用，避免高频访问触发 SQLite 写锁。
function queryCallStatsData() {
    $db = getDb();

    // 获取总调用（SUM 包含 __history__ 归档行，总调用次数永久保留）
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

    // 获取每日数据（排除历史归档行，仅保留 365 天明细）
    $stmt = $db->prepare("SELECT date, total, pc, pe, api_count FROM call_stats WHERE date != '__history__' ORDER BY date DESC LIMIT 365");
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

// 只读统计：用于公开页面。不合并缓冲、不归档，避免写库。
// 与 getCallCount 的差异仅在于是否落盘合并缓冲；数据可能滞后于最近几次未合并的缓冲区，
// 但首页仅用于展示概览，可接受，且后台打开时会触发 getCallCount 完成真正的合并入库。
function getCallCountReadOnly() {
    return queryCallStatsData();
}

function getCallCount() {
    // 读取前先合并当日及所有残留缓冲并归档过期明细
    flushAllStatsBuffers();
    archiveOldCallStats();

    return queryCallStatsData();
}

function getTotalCalls() {
    // 读取前先合并当日及所有残留缓冲
    flushAllStatsBuffers();
    archiveOldCallStats();

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
    $result = $stmt->execute([$time, $username, $ip, $action]);

    // 保留策略：操作日志最多保留 1000 条，防止无限增长
    $stmt = $db->prepare("DELETE FROM admin_logs WHERE id NOT IN (SELECT id FROM admin_logs ORDER BY id DESC LIMIT 1000)");
    $stmt->execute();

    return $result;
}

function getAdminLogs($limit = 100) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM admin_logs ORDER BY id DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}

// ==================== 图片API函数 ====================

function getRandomImageUrl($type = 'pc') {
    $db = getDb();

    // 均匀随机：缓存该类型全部图片 id，array_rand 随机取一个。
    // 对比 rowid 范围随机（id 空洞时分布不均、反复命中同一张）无空洞偏差、各图等概率；
    // id 仅为 int 数组，即使几千条也只需几 KB~几十 KB，载入/解析开销可忽略。
    $ids = getCachedImageIds($type);
    if ($ids === null) {
        $ids = loadImageIdList($db, $type);
    }
    if (empty($ids)) {
        return false;
    }

    // 抽中已删除的陈旧 id 时（删除图片与随机请求并发、或缓存未及时失效），
    // 重建 id 列表后重试一次，避免单次 404（与旧版「按实际计数重试一次并刷新计数缓存」等价）。
    $id = $ids[array_rand($ids)];
    $url = fetchImageUrlById($db, $id, $type);
    if ($url !== false) {
        return $url;
    }

    $ids = loadImageIdList($db, $type);
    if (empty($ids)) {
        return false;
    }
    $id = $ids[array_rand($ids)];
    return fetchImageUrlById($db, $id, $type);
}

// 从数据库载入某类型的全部图片 id 并写入缓存（未命中时重新构建）
function loadImageIdList($db, $type) {
    // 使用占位符而非 quote() 拼接，与全项目预处理风格统一，杜绝 SQL 注入面
    $stmt = $db->prepare("SELECT id FROM image_urls WHERE type = ?");
    $stmt->execute([$type]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_values(array_map('intval', array_column($rows, 'id')));
    setCachedImageIds($type, $ids);
    return $ids;
}

// 按 id 与类型取图片 URL（不存在返回 false）
function fetchImageUrlById($db, $id, $type) {
    $stmt = $db->prepare("SELECT url FROM image_urls WHERE id = ? AND type = ?");
    $stmt->execute([$id, $type]);
    $url = $stmt->fetchColumn();
    return $url !== false ? $url : false;
}

// SSRF防护：安全获取远程图片（每一跳重定向均重新校验，防止重定向绕过防护）
function fetchRemoteImage($url) {
    // 初始 URL 校验并取得固定解析结果
    if (!isSafeRemoteUrl($url, $resolved)) {
        return false;
    }

    // Content-Type 白名单
    $allowedTypes = [
        'image/jpeg' => true, 'image/jpg' => true,
        'image/png' => true, 'image/gif' => true,
        'image/webp' => true, 'image/bmp' => true,
        'image/svg+xml' => true, 'image/x-icon' => true,
    ];

    // 限制下载大小（5MB）
    $maxSize = 5 * 1024 * 1024;
    $data = '';
    $totalSize = 0;
    $contentTypeOk = true;
    $responseStatus = 0;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    // 关闭自动跟随重定向：每一跳都在本函数内重新校验后再请求，防止 SSRF 绕过
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ImageFetcher/1.0)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$data, $maxSize, &$totalSize) {
        $chunkLen = strlen($chunk);
        $totalSize += $chunkLen;
        if ($totalSize > $maxSize) {
            return 0; // 返回0中止传输
        }
        $data .= $chunk;
        return $chunkLen;
    });

    // 仅校验最终 200 响应的 Content-Type（重定向响应不参与校验）
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$contentTypeOk, &$responseStatus, $allowedTypes) {
        $len = strlen($header);
        $trimmed = trim($header);
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $trimmed, $m)) {
            $responseStatus = (int)$m[1];
            $contentTypeOk = ($responseStatus === 200);
            return $len;
        }
        if ($responseStatus === 200 && stripos($trimmed, 'Content-Type:') === 0) {
            $type = trim(substr($trimmed, 13));
            $type = strtolower(explode(';', $type)[0]);
            if (!isset($allowedTypes[$type])) {
                $contentTypeOk = false;
            }
        }
        return $len;
    });

    // 手动处理重定向：每跳重新校验目标地址并固定 IP（防止 DNS 重绑定与内网跳转）
    $currentUrl = $url;
    $currentResolved = $resolved;
    $maxRedirects = 3;
    $httpCode = 0;
    $error = '';
    $success = false;

    for ($hop = 0; $hop <= $maxRedirects; $hop++) {
        $data = '';
        $totalSize = 0;
        $contentTypeOk = true;
        $responseStatus = 0;

        curl_setopt($ch, CURLOPT_URL, $currentUrl);
        curl_setopt($ch, CURLOPT_RESOLVE, [
            $currentResolved['host'] . ':' . $currentResolved['port'] . ':' . $currentResolved['ip']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($response === false || !empty($error)) {
            $success = false;
            break;
        }

        if ($httpCode >= 300 && $httpCode < 400) {
            // 重定向：解析目标地址并重新校验
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            if (empty($redirectUrl)) {
                $success = false;
                $error = '重定向地址为空';
                break;
            }
            $redirectUrl = resolveRelativeUrl($currentUrl, $redirectUrl);
            if (!isSafeRemoteUrl($redirectUrl, $currentResolved)) {
                $success = false;
                $error = '重定向目标地址不合法';
                break;
            }
            $currentUrl = $redirectUrl;
            continue; // 进入下一跳
        }

        if ($httpCode === 200) {
            $success = true;
            break;
        }

        $success = false;
        $error = 'HTTP ' . $httpCode;
        break;
    }

    curl_close($ch);

    if (!$success || !empty($error) || $httpCode !== 200) {
        return false;
    }

    if (!$contentTypeOk) {
        return false;
    }

    // 内容验证：检测文件签名（魔数）
    // 注意：签名必须存为数组"值"（而非键）——'47494638'/'52494646' 这类纯数字字符串
    // 若作数组键会被 PHP 自动转成整数，strpos/strncmp 收到 int needle 会触发 Deprecated
    // 且匹配失效（int 被当作字符字节值），导致 GIF/WEBP 图片校验失败
    $allowedSignatures = [
        'ffd8ff',   // JPEG
        '89504e47', // PNG
        '47494638', // GIF
        '52494646', // WEBP (RIFF)
        '424d',     // BMP
    ];
    if (strlen($data) >= 4) {
        $signature = bin2hex(substr($data, 0, 4));
        $isValidImage = false;
        foreach ($allowedSignatures as $sig) {
            if (strncmp($signature, $sig, strlen($sig)) === 0) {
                if ($sig === '52494646') {
                    // RIFF 头不足以确认 WEBP（WAV/AVI 同为 RIFF 容器），必须校验第 8-11 字节
                    $isValidImage = (strlen($data) >= 12) && (substr($data, 8, 4) === 'WEBP');
                } else {
                    $isValidImage = true;
                }
                break;
            }
        }
        if (!$isValidImage && strlen($data) >= 200) {
            // 备用：使用 finfo 检测
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->buffer($data);
                if (!isset($allowedTypes[$detectedMime])) {
                    return false;
                }
            } else {
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

    // 图片访问模式由后台配置决定（proxy=代理 / redirect=302跳转），
    // API 的 return 参数不再生效
    $mode = getImageAccessMode();
    // 统计沿用原语义：代理计入 img 列，跳转计入 redirect 列
    $returnType = ($mode === 'proxy') ? 'img' : 'redirect';

    // JSON 输出（?format=json）：返回图片地址 JSON，由后台「enable_json」开关控制
    $jsonRequested = isset($_GET['format']) ? strtolower(trim($_GET['format'])) === 'json' : false;
    if ($jsonRequested && !isJsonEnabled()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'JSON 格式输出未开启，请先在后台「网站设置」中开启该功能',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // cache 参数钳制上限（最大 30 天），防止超大值导致时间戳溢出
    $cacheTime = isset($_GET['cache']) ? max(0, min(2592000, intval($_GET['cache']))) : 0;
    $imageUrl = getRandomImageUrl($type);

    if ($countType === null) {
        $countType = $type;
    }
    // api 入口按实际设备类型同时计入 pc/pe 分布统计
    $deviceHint = ($countType === 'api') ? $type : null;
    updateCallCount($countType, $returnType, $deviceHint);

    if (!$imageUrl) {
        $errorMsg = ($type === 'pc') ? '没有找到可用的PC端图片' :
                    (($type === 'pe') ? '没有找到可用的移动端图片' : '没有找到可用的图片');
        http_response_code(404);
        if ($jsonRequested) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'type' => $type,
                'error' => $errorMsg,
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo $errorMsg;
        }
        exit;
    }

    header("Cache-Control: public, max-age=$cacheTime");
    header("Expires: " . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');

    // JSON 输出：返回图片地址（受后台开关控制，已在入口校验）。
    // 注意：无论访问模式是代理还是跳转，这里都返回真实图片 URL，
    // 因此在代理模式下启用 JSON 会暴露代理模式本应隐藏的图片链接。
    if ($jsonRequested) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'type'   => $type,
            'mode'   => $mode,
            'cache'  => $cacheTime,
            'url'    => $imageUrl,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 不再为 URL 追加 rand 随机参数：无论代理还是 302，追加都会破坏上游图片 CDN 的命中，
    // 导致缓存永不命中、强制回源给源站造成压力。是否缓存由调用方用 cache 参数显式控制。
    if ($mode === 'proxy') {
        // 代理模式：服务器下载图片并转发给用户，隐藏真实图片链接（仍有 SSRF 防护）
        // 服务器不支持 cURL、或下载失败时，降级为 302 跳转，保证接口始终能出图、不白屏
        if (!function_exists('curl_init') || !function_exists('curl_exec')) {
            header("Location: $imageUrl");
            exit;
        }
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
            // 下载失败：降级为 302 跳转出图，避免返回 404/白屏
            header("Location: $imageUrl");
        }
    } else {
        // 302 跳转模式（默认）：直接重定向到真实图片 URL
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
        if (!empty($v)) return $v;
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

    // 提示目录防护（.htaccess 仅对 Apache 生效；Nginx 需按 nginx.conf.example 配置）
    $serverSoftware = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
    if (strpos($serverSoftware, 'nginx') !== false) {
        $warnings[] = '检测到 Nginx 服务器：请务必按根目录 nginx.conf.example 配置 data/ 与 admin/logs/ 目录拒绝访问，否则敏感文件可被公网下载';
    } else {
        $warnings[] = '请确认 data/ 与 admin/logs/ 目录无法通过 Web 访问（Apache 使用自带 .htaccess；Nginx 请参考 nginx.conf.example）';
    }

    return [
        'ok' => empty($errors),
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}

// 检测本地环境（运行环境 + 依赖扩展 + 关键目录），供后台「环境检测」页面展示。
// 返回结构化结果便于前端渲染：environment 为环境信息，checks 为逐项依赖检查清单。
function getLocalEnvironmentChecks() {
    $checks = [];

    // ---------- 必需扩展 / 运行时 ----------
    $checks[] = [
        'name' => 'php_version',
        'label' => 'PHP 版本',
        'required' => PHP_VERSION_ID >= 70400,
        'ok' => PHP_VERSION_ID >= 70400,
        'detail' => '当前 PHP ' . PHP_VERSION . '（要求 ≥ 7.4）',
        'group' => '必需',
    ];
    $checks[] = [
        'name' => 'pdo_sqlite',
        'label' => 'PDO SQLite',
        'required' => true,
        'ok' => extension_loaded('pdo_sqlite'),
        'detail' => extension_loaded('pdo_sqlite') ? '已启用：数据库存储依赖此扩展' : '未启用：数据无法存储，应用无法工作',
        'group' => '必需',
    ];
    $checks[] = [
        'name' => 'session',
        'label' => 'Session 会话',
        'required' => true,
        'ok' => function_exists('session_start'),
        'detail' => function_exists('session_start') ? '已启用：后台登录依赖' : '未启用：管理后台无法登录',
        'group' => '必需',
    ];
    $checks[] = [
        'name' => 'json',
        'label' => 'JSON 扩展',
        'required' => true,
        'ok' => function_exists('json_encode'),
        'detail' => function_exists('json_encode') ? '已启用' : '未启用：统计缓冲与 JSON 输出不可用',
        'group' => '必需',
    ];
    $checks[] = [
        'name' => 'hash',
        'label' => 'Hash 扩展',
        'required' => true,
        'ok' => function_exists('hash_equals'),
        'detail' => function_exists('hash_equals') ? '已启用：用于密码与 CSRF 校验' : '未启用：安全校验不可用',
        'group' => '必需',
    ];

    // ---------- 功能依赖（缺失时对应功能受限，但应用仍可运行） ----------
    $curlOk = function_exists('curl_init') && function_exists('curl_exec');
    $checks[] = [
        'name' => 'curl',
        'label' => 'cURL',
        'required' => false,
        'ok' => $curlOk,
        'detail' => $curlOk
            ? '已启用：支持代理出图与自动更新下载'
            : '未启用：代理模式将降级为 302 跳转，自动更新不可用（可考虑开启 allow_url_fopen）',
        'group' => '功能',
    ];
    $checks[] = [
        'name' => 'zip',
        'label' => 'ZIP 扩展',
        'required' => false,
        'ok' => extension_loaded('zip'),
        'detail' => extension_loaded('zip')
            ? '已启用：支持自动更新解压'
            : '未启用：自动更新功能不可用，仅影响更新',
        'group' => '功能',
    ];
    $checks[] = [
        'name' => 'apcu',
        'label' => 'APCu（可选）',
        'required' => false,
        'ok' => function_exists('apcu_fetch') && @apcu_enabled(),
        'detail' => (function_exists('apcu_fetch') && @apcu_enabled())
            ? '已启用：限流使用内存计数，降低 SQLite 写压力（仅单机有效）'
            : '未启用：限流回退为 SQLite 计数。装 APCu 可在高并发下提升性能',
        'group' => '推荐',
    ];

    // ---------- 关键目录可写 ----------
    $dirs = [
        __DIR__ . '/data' => '数据目录 (data/)',
        CACHE_DIR => '缓存目录 (data/cache/)',
        UPDATE_BACKUP_DIR => '备份目录 (data/backups/)',
        UPDATE_CACHE_DIR => '更新缓存 (data/update_cache/)',
        __DIR__ . '/admin/logs' => '日志目录 (admin/logs/)',
    ];
    foreach ($dirs as $dir => $label) {
        $writable = isDirReallyWritable($dir);
        $checks[] = [
            'name' => 'dir_' . $dir,
            'label' => $label,
            'required' => true,
            'ok' => $writable,
            'detail' => $writable
                ? '可写'
                : '不可写：需授予写入权限（避免使用 chmod 777，建议调整属主为 Web 运行账户）',
            'group' => '目录',
        ];
    }

    // ---------- 环境信息（只读展示） ----------
    $db = null;
    $sqliteVersion = '';
    try {
        $dbVersion = @getDb()->query('SELECT sqlite_version()');
        $sqliteVersion = $dbVersion ? (string)$dbVersion->fetchColumn() : '';
    } catch (Exception $e) {
        $sqliteVersion = '';
    }
    $freeSpace = @disk_free_space(__DIR__);
    $environment = [
        'php_version' => PHP_VERSION . '（' . PHP_SAPI . '）',
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '未知',
        'sqlite_version' => $sqliteVersion !== '' ? $sqliteVersion : '不可用',
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time') ? ini_get('max_execution_time') . 's' : '不限(0)',
        'timezone' => date_default_timezone_get(),
    ];

    return [
        'environment' => $environment,
        'checks' => $checks,
    ];
}

// 渲染「环境检测」完整 HTML（运行环境表 + 依赖与目录检测表）。
// 供后台「环境检测」页与「系统更新 → 环境明细」复用，避免重复渲染逻辑。
function renderEnvironmentChecksHtml() {
    $envData = getLocalEnvironmentChecks();
    $envLabels = [
        'php_version' => 'PHP 版本',
        'server_software' => '服务器软件',
        'sqlite_version' => 'SQLite 版本',
        'memory_limit' => '内存限制 (memory_limit)',
        'upload_max_filesize' => '上传大小限制',
        'post_max_size' => 'POST 请求限制',
        'max_execution_time' => '执行时间限制',
        'timezone' => '时区',
    ];

    ob_start();
    ?>
    <!-- 运行环境信息 -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">运行环境</h3>
        </div>
        <div class="card-body">
            <table class="table table-bordered table-striped">
                <tbody>
                <?php foreach ($envData['environment'] as $key => $value): ?>
                    <tr>
                        <th style="width:220px;"><?php echo htmlspecialchars($envLabels[$key] ?? $key, ENT_QUOTES); ?></th>
                        <td><?php echo htmlspecialchars((string)$value, ENT_QUOTES); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- 依赖与目录检测 -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">依赖与目录检测</h3>
        </div>
        <div class="card-body">
            <?php
            $failed = array_filter($envData['checks'], function ($c) { return !$c['ok']; });
            $grouped = [];
            foreach ($envData['checks'] as $c) {
                $grouped[$c['group']][] = $c;
            }
            ?>
            <?php if (empty($failed)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 所有必需项均满足，环境正常。
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> 有 <?php echo count($failed); ?> 项未通过，请参考下表逐项处理。
                </div>
            <?php endif; ?>

            <?php foreach ($grouped as $group => $items): ?>
                <h6 class="text-muted mb-3"><?php echo htmlspecialchars($group, ENT_QUOTES); ?></h6>
                <table class="table table-bordered table-striped mb-4">
                    <thead>
                    <tr>
                        <th style="width:40px;"><i class="fas fa-exchange-alt"></i></th>
                        <th style="width:220px;">项目</th>
                        <th>说明</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $c): ?>
                        <tr>
                            <td class="text-center">
                                <?php if ($c['ok']): ?>
                                    <i class="fas fa-check-circle text-success" title="通过"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger" title="未通过"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($c['label'], ENT_QUOTES); ?>
                                <?php if ($c['required']): ?>
                                    <span class="badge badge-danger">必需</span>
                                <?php else: ?>
                                    <span class="badge badge-secondary">可选</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($c['detail'], ENT_QUOTES); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// 判断路径是否受保护（不会被更新覆盖
function isPathProtected($relativePath) {
    $protected = UPDATE_PROTECTED_PATHS;
    $relativePath = str_replace('\\', '/', $relativePath);
    // 仅去除开头的 ./,避免吞掉 .git/.htaccess 等点前缀路径
    $normalized = preg_replace('#^\./+#', '', $relativePath);
    $normalized = ltrim($normalized, '/');
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
    $allowed = UPDATE_ALLOWED_EXTENSIONS;
    if (empty($allowed)) return true;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed);
}
?>
