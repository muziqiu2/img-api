<?php
// 确保目录存在
$requiredDirs = ['data', 'admin/logs', 'data/cache'];
foreach ($requiredDirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// 缓存相关常量
define('CACHE_DIR', __DIR__ . '/data/cache');
define('CACHE_TTL', 300); // 5分钟缓存

// 新增：用户配置文件路径
define('USER_CONFIG_FILE', __DIR__ . '/data/user_config.json');

define('PC_IMG_FILE', __DIR__ . '/data/pc.txt');
define('PE_IMG_FILE', __DIR__ . '/data/pe.txt');
define('COUNT_FILE', __DIR__ . '/data/api_call_count.json');
define('ADMIN_LOG_FILE', __DIR__ . '/admin/logs/admin_actions.log');
define('SESSION_TIMEOUT', 3600); // 会话超时时间(秒)

// 频率限制配置
define('RATE_LIMIT_FILE', __DIR__ . '/data/rate_limit.json');
define('RATE_LIMIT_WINDOW', 60); // 60秒窗口
define('RATE_LIMIT_MAX_API', 100); // API每分钟最大请求数
define('RATE_LIMIT_MAX_ADMIN', 10); // 管理后台每分钟最大请求数

// 定义是否在管理区域
$isAdminArea = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;

// 仅在管理区域启动会话
if ($isAdminArea) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_samesite', 'Lax');
    // 修正 HTTPS 检测
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

// 新增：重置用户配置为默认（安全考虑：此函数应在生产环境部署后谨慎使用）
function resetUserConfig() {
    $defaultConfig = [
        'username' => 'admin',
        'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
        'login_attempts' => 0,
        'last_attempt' => 0,
        'locked_until' => 0
    ];
    return saveUserConfig($defaultConfig);
}

// 新增：获取用户配置
function getUserConfig() {
    // 检查配置文件是否存在
    if (!file_exists(USER_CONFIG_FILE)) {
        // 创建默认配置
        return createDefaultUserConfig();
    }
    
    // 读取配置文件
    $content = file_get_contents(USER_CONFIG_FILE);
    $config = json_decode($content, true) ?? [];
    
    // 清理旧字段，只保留新格式
    $cleanConfig = [];
    if (isset($config['username'])) $cleanConfig['username'] = $config['username'];
    if (isset($config['password_hash'])) {
        $cleanConfig['password_hash'] = $config['password_hash'];
    } elseif (isset($config['password'])) {
        // 迁移旧密码（如果有）
        $cleanConfig['password_hash'] = $config['password'];
    }
    if (isset($config['login_attempts'])) {
        $cleanConfig['login_attempts'] = $config['login_attempts'];
    } elseif (isset($config['failed_attempts'])) {
        $cleanConfig['login_attempts'] = $config['failed_attempts'];
    }
    if (isset($config['last_attempt'])) $cleanConfig['last_attempt'] = $config['last_attempt'];
    if (isset($config['locked_until'])) {
        $cleanConfig['locked_until'] = $config['locked_until'];
    } elseif (isset($config['lockout_time'])) {
        $cleanConfig['locked_until'] = $config['lockout_time'];
    }
    
    // 确保配置完整
    if (!isset($cleanConfig['username'])) $cleanConfig['username'] = 'admin';
    if (!isset($cleanConfig['password_hash'])) $cleanConfig['password_hash'] = password_hash('123456', PASSWORD_DEFAULT);
    if (!isset($cleanConfig['login_attempts'])) $cleanConfig['login_attempts'] = 0;
    if (!isset($cleanConfig['last_attempt'])) $cleanConfig['last_attempt'] = 0;
    if (!isset($cleanConfig['locked_until'])) $cleanConfig['locked_until'] = 0;
    
    // 如果配置发生变化，保存清理后的配置
    if ($config !== $cleanConfig) {
        saveUserConfig($cleanConfig);
    }
    
    return $cleanConfig;
}

// 创建默认用户配置
function createDefaultUserConfig() {
    $defaultConfig = [
        'username' => 'admin',
        'password_hash' => password_hash('123456', PASSWORD_DEFAULT),
        'login_attempts' => 0,
        'last_attempt' => 0,
        'locked_until' => 0
    ];
    saveUserConfig($defaultConfig);
    return $defaultConfig;
}

// 新增：保存用户配置（带文件锁）
function saveUserConfig($config) {
    $file = fopen(USER_CONFIG_FILE, 'w');
    if ($file && flock($file, LOCK_EX)) {
        fwrite($file, json_encode($config, JSON_PRETTY_PRINT));
        flock($file, LOCK_UN);
        fclose($file);
        return true;
    }
    if ($file) fclose($file);
    return false;
}

// 新增：获取当前用户名
function getCurrentUsername() {
    $config = getUserConfig();
    return $config['username'] ?? 'admin';
}

// 新增：验证密码
function verifyPassword($password) {
    $config = getUserConfig();
    return password_verify($password, $config['password_hash'] ?? '');
}

// 新增：更新用户信息
function updateUserInfo($newUsername, $newPassword = '') {
    $config = getUserConfig();
    $config['username'] = $newUsername;
    
    // 如果提供了新密码，则更新密码
    if (!empty($newPassword)) {
        $config['password_hash'] = password_hash($newPassword, PASSWORD_DEFAULT);
    }
    
    return saveUserConfig($config);
}

// 新增：记录登录尝试
function recordLoginAttempt($success = false) {
    $config = getUserConfig();
    $config['last_attempt'] = time();
    
    if ($success) {
        // 登录成功，重置尝试次数
        $config['login_attempts'] = 0;
        $config['locked_until'] = 0;
    } else {
        // 登录失败，增加尝试次数
        $config['login_attempts']++;
        
        // 5次失败后锁定5分钟
        if ($config['login_attempts'] >= 5) {
            $config['locked_until'] = time() + 300; // 5分钟后解锁
        }
    }
    
    return saveUserConfig($config);
}

// 新增：检查账户是否被锁定
function isAccountLocked() {
    $config = getUserConfig();
    return time() < $config['locked_until'];
}

// 新增：获取剩余登录尝试次数
function getRemainingAttempts() {
    $config = getUserConfig();
    return max(0, 5 - $config['login_attempts']);
}

// ==================== 频率限制功能 ====================

/**
 * 检查API频率限制
 * @return bool true 表示通过，false 表示超出限制
 */
function checkApiRateLimit() {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = time();
    $windowStart = $now - RATE_LIMIT_WINDOW;
    
    $data = [];
    $file = fopen(RATE_LIMIT_FILE, 'c+');
    if (!$file) {
        return true; // 文件无法打开时放行
    }
    
    if (flock($file, LOCK_EX)) {
        $content = fread($file, filesize(RATE_LIMIT_FILE) ?: 1);
        $data = json_decode($content, true) ?? [];
        
        // 清理过期记录
        foreach ($data as $key => $record) {
            if ($record['timestamp'] < $windowStart) {
                unset($data[$key]);
            }
        }
        
        // 检查当前IP的请求数
        $ipKey = 'api_' . md5($ip);
        if (!isset($data[$ipKey])) {
            $data[$ipKey] = ['count' => 0, 'timestamp' => $now];
        }
        
        // 检查是否超限
        if ($data[$ipKey]['count'] >= RATE_LIMIT_MAX_API) {
            flock($file, LOCK_UN);
            fclose($file);
            return false;
        }
        
        // 增加计数
        $data[$ipKey]['count']++;
        $data[$ipKey]['timestamp'] = $now;
        
        // 保存
        ftruncate($file, 0);
        rewind($file);
        fwrite($file, json_encode($data));
        flock($file, LOCK_UN);
    }
    fclose($file);
    return true;
}

/**
 * 检查管理后台频率限制
 * @return bool true 表示通过，false 表示超出限制
 */
function checkAdminRateLimit() {
    if (!IS_LOGGED_IN) {
        return true; // 未登录不限制
    }
    
    $username = $_SESSION['admin_username'] ?? 'unknown';
    $now = time();
    $windowStart = $now - RATE_LIMIT_WINDOW;
    
    $data = [];
    $file = fopen(RATE_LIMIT_FILE, 'c+');
    if (!$file) {
        return true;
    }
    
    if (flock($file, LOCK_EX)) {
        $content = fread($file, filesize(RATE_LIMIT_FILE) ?: 1);
        $data = json_decode($content, true) ?? [];
        
        // 清理过期记录
        foreach ($data as $key => $record) {
            if ($record['timestamp'] < $windowStart) {
                unset($data[$key]);
            }
        }
        
        // 检查当前用户的请求数
        $userKey = 'admin_' . md5($username);
        if (!isset($data[$userKey])) {
            $data[$userKey] = ['count' => 0, 'timestamp' => $now];
        }
        
        // 检查是否超限
        if ($data[$userKey]['count'] >= RATE_LIMIT_MAX_ADMIN) {
            flock($file, LOCK_UN);
            fclose($file);
            return false;
        }
        
        // 增加计数
        $data[$userKey]['count']++;
        $data[$userKey]['timestamp'] = $now;
        
        // 保存
        ftruncate($file, 0);
        rewind($file);
        fwrite($file, json_encode($data));
        flock($file, LOCK_UN);
    }
    fclose($file);
    return true;
}

/**
 * 获取频率限制剩余请求数
 */
function getRateLimitRemaining($type = 'api') {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = $type . '_' . md5($ip);
    $max = $type === 'api' ? RATE_LIMIT_MAX_API : RATE_LIMIT_MAX_ADMIN;
    
    $file = fopen(RATE_LIMIT_FILE, 'r');
    if (!$file) {
        return $max;
    }
    
    $content = fread($file, filesize(RATE_LIMIT_FILE) ?: 1);
    $data = json_decode($content, true) ?? [];
    fclose($file);
    
    if (!isset($data[$key])) {
        return $max;
    }
    
    return max(0, $max - $data[$key]['count']);
}

/**
 * 生成 CSRF Token
 */
function generateCsrfToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * 验证 CSRF Token
 */
function validateCsrfToken($token) {
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * 获取图片总数
 */
function getImageCount($type = 'pc') {
    $file = $type === 'pe' ? PE_IMG_FILE : PC_IMG_FILE;
    
    if (!file_exists($file)) {
        file_put_contents($file, '');
        return 0;
    }
    
    $urls = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $validUrls = array_filter($urls, 'isValidImageUrl');
    
    return count($validUrls);
}

/**
 * 获取图片URL列表（支持分页）
 */
function getImageUrls($type = 'pc', $page = 1, $perPage = 20) {
    $file = $type === 'pe' ? PE_IMG_FILE : PC_IMG_FILE;
    if (!file_exists($file)) {
        file_put_contents($file, '');
        return ['urls' => [], 'total' => 0, 'pages' => 0, 'page' => $page];
    }
    
    // 读取并过滤URL
    $urls = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $urls = array_filter($urls, 'isValidImageUrl');
    $total = count($urls);
    
    // 分页处理
    $totalPages = $total > 0 ? ceil($total / $perPage) : 0;
    // 确保页码在有效范围内
    $page = max(1, min($page, max(1, $totalPages)));
    $offset = ($page - 1) * $perPage;
    $paginatedUrls = array_slice($urls, $offset, $perPage);
    
    return [
        'urls' => $paginatedUrls,
        'total' => $total,
        'pages' => $totalPages,
        'page' => $page
    ];
}

/**
 * 添加图片URL
 */
function addImageUrls($urls, $type = 'pc') {
    $file = $type === 'pe' ? PE_IMG_FILE : PC_IMG_FILE;
    
    // 读取现有URL
    $existingUrls = [];
    if (file_exists($file)) {
        $existingUrls = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $existingUrls = array_filter($existingUrls, 'isValidImageUrl');
    }
    
    // 使用 array_flip 将数组转为键值，查找效率从 O(n) 变为 O(1)
    $existingUrlsFlip = array_flip($existingUrls);
    
    // 处理新URL
    $newUrls = [];
    foreach ($urls as $url) {
        if (isValidImageUrl($url) && !isset($existingUrlsFlip[$url])) {
            $newUrls[] = $url;
            $existingUrlsFlip[$url] = true; // 防止批量添加时重复
        }
    }
    
    // 写入文件
    if (!empty($newUrls)) {
        $allUrls = array_merge($existingUrls, $newUrls);
        file_put_contents($file, implode("\n", $allUrls) . "\n");
    }
    
    // 清除缓存
    clearCachedImageUrls($type);
    
    return count($newUrls);
}

/**
 * 删除图片URL
 */
function deleteImageUrl($url, $type = 'pc') {
    $file = $type === 'pe' ? PE_IMG_FILE : PC_IMG_FILE;
    
    if (!file_exists($file)) {
        return false;
    }
    
    // 读取现有URL
    $existingUrls = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $originalCount = count($existingUrls);
    
    // 过滤掉要删除的URL
    $remainingUrls = array_filter($existingUrls, function($existingUrl) use ($url) {
        return trim($existingUrl) !== trim($url);
    });
    
    // 如果有变化则写入文件
    if (count($remainingUrls) !== $originalCount) {
        file_put_contents($file, implode("\n", $remainingUrls) . "\n");
        // 清除缓存
        clearCachedImageUrls($type);
        return true;
    }
    
    return false;
}

/**
 * 清空图片URL
 */
function clearImageUrls($type = 'pc') {
    $file = $type === 'pe' ? PE_IMG_FILE : PC_IMG_FILE;
    $result = file_put_contents($file, '') !== false;
    if ($result) {
        clearCachedImageUrls($type);
    }
    return $result;
}

/**
 * 更新调用统计（带文件锁和重试机制）
 */
function updateCallCount($type, $returnType = 'redirect') {
    $date = date('Y-m-d');
    $maxRetries = 3;
    $retryDelay = 100000; // 100ms
    
    for ($attempt = 0; $attempt < $maxRetries; $attempt++) {
        $file = fopen(COUNT_FILE, 'c+');
        if (!$file) {
            continue;
        }
        
        if (flock($file, LOCK_EX)) {
            $content = fread($file, filesize(COUNT_FILE) ?: 1);
            $data = json_decode($content, true) ?? [];
            ftruncate($file, 0);
            rewind($file);
            
            // 初始化数据结构
            if (!isset($data['total'])) $data['total'] = 0;
            if (!isset($data[$type])) $data[$type] = 0;
            if (!isset($data['daily'])) $data['daily'] = [];
            if (!isset($data['daily'][$date])) {
                $data['daily'][$date] = ['total' => 0, 'pc' => 0, 'pe' => 0, 'api' => 0];
            }
            if (!isset($data['return_types'])) $data['return_types'] = ['redirect' => 0, 'json' => 0, 'img' => 0];
            if (!isset($data['return_types'][$returnType])) $data['return_types'][$returnType] = 0;
            
            // 更新统计
            $data['total']++;
            $data[$type]++;
            $data['daily'][$date]['total']++;
            $data['daily'][$date][$type]++;
            $data['return_types'][$returnType]++;
            
            // 保存数据
            fwrite($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            flock($file, LOCK_UN);
            fclose($file);
            return $data;
        }
        
        fclose($file);
        
        // 锁获取失败，稍后重试
        if ($attempt < $maxRetries - 1) {
            usleep($retryDelay);
        }
    }
    
    // 所有重试都失败，返回空数据但不静默丢失
    return ['error' => 'failed_to_acquire_lock', 'attempts' => $maxRetries];
}

/**
 * 获取调用统计
 */
function getCallCount() {
    if (!file_exists(COUNT_FILE)) {
        return ['total' => 0, 'pc' => 0, 'pe' => 0, 'api' => 0, 'daily' => [], 'return_types' => []];
    }
    
    $content = file_get_contents(COUNT_FILE);
    $data = json_decode($content, true) ?? [];
    
    // 确保返回完整结构
    if (!isset($data['daily'])) $data['daily'] = [];
    if (!isset($data['return_types'])) $data['return_types'] = [];
    
    return $data;
}

/**
 * 获取总调用次数
 */
function getTotalCalls() {
    $data = getCallCount();
    return $data['total'] ?? 0;
}

/**
 * 记录管理员操作日志
 */
function logAdminAction($action) {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $time = date('Y-m-d H:i:s');
    $username = getCurrentUsername();
    $logEntry = "[$time] [$username] [$ip] $action\n";
    return file_put_contents(ADMIN_LOG_FILE, $logEntry, FILE_APPEND) !== false;
}

/**
 * 获取管理员操作日志
 */
function getAdminLogs($limit = 100) {
    if (!file_exists(ADMIN_LOG_FILE)) {
        return [];
    }
    
    $lines = file(ADMIN_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($lines); // 最新的在前
    $logs = [];
    
    foreach ($lines as $line) {
        if (preg_match('/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\] \[([^\]]+)\] \[([^\]]+)\] (.*)/', $line, $matches)) {
            $logs[] = [
                'time' => $matches[1],
                'user' => $matches[2],
                'ip' => $matches[3],
                'action' => $matches[4]
            ];
            
            if (count($logs) >= $limit) {
                break;
            }
        }
    }
    
    return $logs;
}

/**
 * 获取随机图片URL（带缓存）
 */
function getRandomImageUrl($type = 'pc') {
    $validUrls = getCachedImageUrls($type);
    
    if ($validUrls === null) {
        $file = $type === 'pe' ? PE_IMG_FILE : PC_IMG_FILE;
        
        if (!file_exists($file)) {
            return false;
        }
        
        // 读取并过滤有效的URL
        $urls = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $validUrls = array_filter($urls, 'isValidImageUrl');
        $validUrls = array_values($validUrls);
        
        setCachedImageUrls($type, $validUrls);
    }
    
    // 如果没有有效URL，返回false
    if (empty($validUrls)) {
        return false;
    }
    
    // 随机选择一个URL
    return $validUrls[array_rand($validUrls)];
}

// 验证图片URL是否有效
function isValidImageUrl($url) {
    $url = trim($url);
    return !empty($url) && filter_var($url, FILTER_VALIDATE_URL) !== false;
}

// 获取缓存的图片URL列表
function getCachedImageUrls($type) {
    $cacheFile = CACHE_DIR . "/{$type}_urls.cache";
    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < CACHE_TTL) {
        return json_decode(file_get_contents($cacheFile), true);
    }
    return null;
}

// 设置图片URL缓存
function setCachedImageUrls($type, $urls) {
    $cacheFile = CACHE_DIR . "/{$type}_urls.cache";
    file_put_contents($cacheFile, json_encode($urls));
}

// 清除图片URL缓存
function clearCachedImageUrls($type) {
    $cacheFile = CACHE_DIR . "/{$type}_urls.cache";
    if (file_exists($cacheFile)) {
        unlink($cacheFile);
    }
}

// SSRF防护：安全获取远程图片（增强版）
function fetchRemoteImage($url) {
    // 基本的URL验证
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    $parsed = parse_url($url);
    $scheme = strtolower($parsed['scheme'] ?? '');
    $host = $parsed['host'] ?? '';
    
    // 只允许 http 和 https 协议
    if (!in_array($scheme, ['http', 'https'])) {
        return false;
    }
    
    // DNS 解析获取 IP 地址
    $ip = gethostbyname($host);
    
    // 检查内网IP（更全面的检查）
    $forbiddenPatterns = [
        '/^(10\.)/',                           // 10.0.0.0/8
        '/^172\.(1[6-9]|2[0-9]|3[01])\./',    // 172.16.0.0/12
        '/^192\.168\./',                       // 192.168.0.0/16
        '/^127\./',                            // 127.0.0.0/8 (localhost)
        '/^169\.254\./',                       // 169.254.0.0/16 (link-local)
        '/^0\./',                              // 0.0.0.0/8
        '/^224\./',                            // 224.0.0.0/4 (multicast)
        '/^240\./',                            // 240.0.0.0/4 (reserved)
        '/^(::1|fe80:|fc00:|fd00:)/i',        // IPv6 本地地址
    ];
    
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $ip)) {
            return false;
        }
    }
    
    // 验证 resolve 后的 IP 不是内网
    $resolvedIp = gethostbyname($host);
    if ($resolvedIp === $host) {
        // DNS 解析失败
        return false;
    }
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $resolvedIp)) {
            return false;
        }
    }
    
    // 使用cURL获取，设置超时和安全选项
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ImageFetcher/1.0)');
    
    // 禁止 SSL 证书验证漏洞利用
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    
    // 设置期望的 MIME 类型
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
    
    // cURL 执行失败
    if ($data === false || !empty($error)) {
        return false;
    }
    
    // HTTP 状态码检查
    if ($httpCode !== 200) {
        return false;
    }
    
    // 验证 Content-Type
    if (isset($contentType)) {
        $mimeType = trim(explode(';', $contentType)[0]);
        if (!in_array(strtolower($mimeType), $allowedTypes)) {
            return false;
        }
    }
    
    // 验证图片数据是否为有效图片
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detectedMime = $finfo->buffer($data);
    if (!in_array($detectedMime, $allowedTypes)) {
        return false;
    }
    
    // 检查图片数据是否为空或过大（限制 50MB）
    if (strlen($data) < 100 || strlen($data) > 50 * 1024 * 1024) {
        return false;
    }
    
    return $data;
}

// 公共API处理函数
function handleImageApiRequest($type, $countType = null) {
    // 检查 API 频率限制
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
    
    // 验证并获取返回类型
    $validReturnTypes = ['redirect', 'json', 'img'];
    $returnType = isset($_GET['return']) ? $_GET['return'] : 'redirect';
    if (!in_array($returnType, $validReturnTypes)) {
        $returnType = 'redirect';
    }
    
    // 获取缓存时间
    $cacheTime = isset($_GET['cache']) ? max(0, intval($_GET['cache'])) : 0;
    
    // 获取随机图片
    $imageUrl = getRandomImageUrl($type);
    
    // 更新调用统计
    if ($countType === null) {
        $countType = $type;
    }
    updateCallCount($countType, $returnType);
    
    // 处理图片不存在的情况
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
    
    // 设置缓存控制
    header("Cache-Control: public, max-age=$cacheTime");
    header("Expires: " . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');
    
    // 添加随机参数防止URL缓存
    if ($cacheTime == 0) {
        $randomParam = 'rand=' . bin2hex(random_bytes(8));
        $imageUrl .= (strpos($imageUrl, '?') === false ? '?' : '&') . $randomParam;
    }
    
    // 根据返回类型处理响应
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
