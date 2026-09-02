<?php
// 该模块：用户认证与安全。覆盖管理员配置读写、密码校验、账号锁定与登录尝试、
// 会话当前用户解析，以及 CSRF Token 的生成与校验。

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
