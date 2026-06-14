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

// 定义是否在管理区域
$isAdminArea = strpos($_SERVER['SCRIPT_NAME'], '/admin/') !== false;

// 仅在管理区域启动会话
if ($isAdminArea) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.cookie_secure', $_SERVER['HTTPS'] ?? false);
    ini_set('session.cookie_samesite', 'Lax');
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

// 新增：获取用户配置
function getUserConfig() {
    // 检查配置文件是否存在
    if (!file_exists(USER_CONFIG_FILE)) {
        // 创建默认配置
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

/**
 * 验证管理员密码
 */
function validateAdminPassword($password) {
    return verifyPassword($password);
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
        return ['urls' => [], 'total' => 0, 'pages' => 0];
    }
    
    // 读取并过滤URL
    $urls = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $urls = array_filter($urls, 'isValidImageUrl');
    $total = count($urls);
    
    // 分页处理
    $offset = ($page - 1) * $perPage;
    $paginatedUrls = array_slice($urls, $offset, $perPage);
    $totalPages = max(1, ceil($total / $perPage));
    
    return [
        'urls' => $paginatedUrls,
        'total' => $total,
        'pages' => $totalPages
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
    
    // 处理新URL
    $newUrls = [];
    foreach ($urls as $url) {
        if (isValidImageUrl($url) && !in_array($url, $existingUrls)) {
            $newUrls[] = $url;
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
 * 更新调用统计（带文件锁）
 */
function updateCallCount($type, $returnType = 'redirect') {
    $date = date('Y-m-d');
    $data = [];
    
    // 读取现有数据（带锁）
    $file = fopen(COUNT_FILE, 'c+');
    if ($file && flock($file, LOCK_EX)) {
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
    if ($file) fclose($file);
    return $data;
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

// SSRF防护：安全获取远程图片
function fetchRemoteImage($url) {
    // 基本的URL验证
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    $parsed = parse_url($url);
    $host = $parsed['host'] ?? '';
    
    // 禁止访问内网IP
    $ip = gethostbyname($host);
    if (preg_match('/^(10\.|172\.(1[6-9]|2[0-9]|3[01])\.|192\.168\.|127\.|::1)/', $ip)) {
        return false;
    }
    
    // 使用cURL获取，设置超时
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 3);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ImageFetcher/1.0)');
    
    $data = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 200 && $data !== false) {
        return $data;
    }
    return false;
}

// 公共API处理函数
function handleImageApiRequest($type, $countType = null) {
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
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $errorMsg,
                'type' => $type,
                'timestamp' => time()
            ]);
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
        $randomParam = 'rand=' . uniqid();
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
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'url' => $imageUrl,
            'width' => $width,
            'height' => $height,
            'type' => $type,
            'timestamp' => time()
        ]);
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
