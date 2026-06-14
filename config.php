<?php
/**
 * 魔法师随机图片API - 配置文件
 * 使用 SQLite 数据库存储
 */

// 确保目录存在
$requiredDirs = ['data', 'admin/logs', 'data/cache'];
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

// ==================== 数据库初始化 ====================
$pdo = null;

function getDb() {
    global $pdo;
    
    if ($pdo === null) {
        try {
            $pdo = new PDO('sqlite:' . DB_FILE);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            initDatabase();
        } catch (PDOException $e) {
            die('数据库连接失败: ' . $e->getMessage());
        }
    }
    
    return $pdo;
}

function initDatabase() {
    $db = getDb();
    
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
$isAdminArea = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;

// 仅在管理区域启动会话
if ($isAdminArea) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', 1);
    }
    session_start();
    
    // 登录状态检查
    $isLoggedIn = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
    
    // 检查会话超时
    if ($isLoggedIn && time() - ($_SESSION['admin_login_time'] ?? time()) > SESSION_TIMEOUT) {
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

// ==================== CSRF 防护函数 ====================

function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
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
    $ip = md5($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $key = 'api_' . $ip;
    $now = time();
    $windowStart = $now - RATE_LIMIT_WINDOW;
    
    $db = getDb();
    
    // 清理过期记录
    $stmt = $db->prepare("DELETE FROM rate_limits WHERE timestamp < ?");
    $stmt->execute([$windowStart]);
    
    // 检查当前记录
    $stmt = $db->prepare("SELECT * FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();
    
    if ($record && $record['count'] >= RATE_LIMIT_MAX_API) {
        return false;
    }
    
    // 更新或插入记录
    if ($record) {
        $stmt = $db->prepare("UPDATE rate_limits SET count = count + 1 WHERE id = ?");
        $stmt->execute([$key]);
    } else {
        $stmt = $db->prepare("INSERT INTO rate_limits (id, count, timestamp) VALUES (?, 1, ?)");
        $stmt->execute([$key, $now]);
    }
    
    return true;
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
    
    // 检查当前记录
    $stmt = $db->prepare("SELECT * FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();
    
    if ($record && $record['count'] >= RATE_LIMIT_MAX_ADMIN) {
        return false;
    }
    
    // 更新或插入记录
    if ($record) {
        $stmt = $db->prepare("UPDATE rate_limits SET count = count + 1 WHERE id = ?");
        $stmt->execute([$key]);
    } else {
        $stmt = $db->prepare("INSERT INTO rate_limits (id, count, timestamp) VALUES (?, 1, ?)");
        $stmt->execute([$key, $now]);
    }
    
    return true;
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
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
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
    
    $ip = gethostbyname($host);
    
    $forbiddenPatterns = [
        '/^(10\.)/',
        '/^172\.(1[6-9]|2[0-9]|3[01])\./',
        '/^192\.168\./',
        '/^127\./',
        '/^169\.254\./',
        '/^0\./',
        '/^224\./',
        '/^240\./',
        '/^(::1|fe80:|fc00:|fd00:)/i',
    ];
    
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $ip)) {
            return false;
        }
    }
    
    $resolvedIp = gethostbyname($host);
    if ($resolvedIp === $host) {
        return false;
    }
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $resolvedIp)) {
            return false;
        }
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
    
    $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp', 'image/svg+xml'];
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$contentType) {
        $len = strlen($header);
        $header = trim($header);
        if (strpos(strtolower($header), 'content-type:') === 0) {
            $contentType = trim(substr($header, 13));
        }
        return $len;
    });
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    if ($data === false || !empty($error)) {
        return false;
    }
    
    if ($httpCode !== 200) {
        return false;
    }
    
    if (isset($contentType)) {
        $mimeType = trim(explode(';', $contentType)[0]);
        if (!in_array(strtolower($mimeType), $allowedTypes)) {
            return false;
        }
    }
    
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->buffer($data);
    if (!in_array($detectedMime, $allowedTypes)) {
        return false;
    }
    
    if (strlen($data) < 100 || strlen($data) > 50 * 1024 * 1024) {
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
    
    if ($cacheTime == 0) {
        $randomParam = 'rand=' . bin2hex(random_bytes(8));
        $imageUrl .= (strpos($imageUrl, '?') === false ? '?' : '&') . $randomParam;
    }
    
    if ($returnType === 'json') {
        $imageData = fetchRemoteImage($imageUrl);
        $width = 0;
        $height = 0;
        
        if ($imageData) {
            $imageInfo = @getimagesizefromstring($imageData);
            if ($imageInfo) {
                $width = $imageInfo[0];
                $height = $imageInfo[1];
            }
        }
        
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'url' => $imageUrl,
            'width' => $width,
            'height' => $height,
            'type' => $type,
            'timestamp' => time()
        ], JSON_UNESCAPED_UNICODE);
    } elseif ($returnType === 'img') {
        $imageData = fetchRemoteImage($imageUrl);
        if ($imageData) {
            $imageInfo = @getimagesizefromstring($imageData);
            if ($imageInfo) {
                header("Content-Type: {$imageInfo['mime']}");
            }
            echo $imageData;
        } else {
            http_response_code(404);
            echo '无法获取图片';
        }
    } else {
        header("Location: $imageUrl");
    }
    exit;
}

// 判断设备类型
function isMobileDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $mobileAgents = [
        'android', 'webos', 'iphone', 'ipad', 'ipod', 'blackberry', 
        'iemobile', 'opera mini', 'mobile', 'windows phone'
    ];
    
    foreach ($mobileAgents as $agent) {
        if (stripos($userAgent, $agent) !== false) {
            return true;
        }
    }
    return false;
}
?>
