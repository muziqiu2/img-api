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

define('APP_VERSION', '3.2.2.1'); // 当前应用版本号（Semantic Versioning）
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

// ==================== 全局函数模块（按模块切割）====================
require_once __DIR__ . '/lib/db.php';
require_once __DIR__ . '/lib/cache.php';
require_once __DIR__ . '/lib/settings.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/ratelimit.php';
require_once __DIR__ . '/lib/images.php';
require_once __DIR__ . '/lib/network.php';
require_once __DIR__ . '/lib/api.php';
require_once __DIR__ . '/lib/stats.php';
require_once __DIR__ . '/lib/log.php';
require_once __DIR__ . '/lib/version.php';
require_once __DIR__ . '/lib/update.php';
require_once __DIR__ . '/lib/environment.php';
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

