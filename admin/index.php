<?php
require_once dirname(__DIR__) . '/config.php';

if (IS_LOGGED_IN) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';
$csrfToken = generateCsrfToken();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $token = $_POST['csrf_token'] ?? '';
    
    if (isAccountLocked()) {
        $config = getUserConfig();
        $remainingTime = max(0, (($config['locked_until'] ?? 0) - time()) / 60);
        $error = "账户已临时锁定，请 " . number_format($remainingTime, 0) . " 分钟后再试";
    }
    elseif (!validateCsrfToken($token)) {
        $error = "安全验证失败，请刷新页面重试";
    }
    elseif (empty($username) || empty($password)) {
        $error = "用户名和密码不能为空";
        recordLoginAttempt(false);
    }
    elseif ($username !== getCurrentUsername()) {
        $error = "用户名或密码不正确";
        recordLoginAttempt(false);
    }
    elseif (!verifyPassword($password)) {
        $remaining = getRemainingAttempts();
        $error = "用户名或密码不正确";
        if ($remaining > 0) {
            $error .= "，还剩 $remaining 次尝试机会";
        }
        recordLoginAttempt(false);
    }
    else {
        recordLoginAttempt(true);
        // 防会话固定：登录成功后更换会话 ID，丢弃登录前的旧会话
        session_regenerate_id(true);
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_login_time'] = time();
        $_SESSION['admin_username'] = $username;
        
        logAdminAction("用户登录成功");
        header('Location: dashboard.php');
        exit;
    }
}

$lockMessage = '';
if (isAccountLocked()) {
    $config = getUserConfig();
    $remainingTime = max(0, (($config['locked_until'] ?? 0) - time()) / 60);
    $lockMessage = "账户已临时锁定，请 " . number_format($remainingTime, 0) . " 分钟后再试";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>魔法师API - 登录</title>
    <link rel="stylesheet" href="../public/css/all.min.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="../public/css/bootstrap.min.css?v=<?php echo APP_VERSION; ?>">
    <link rel="stylesheet" href="../public/css/admin.css?v=<?php echo APP_VERSION; ?>">
</head>
<body class="app-login">
<div class="app-login-box">
    <div class="app-login-brand">
        <i class="fas fa-magic"></i> 魔法师API
    </div>
    <p class="app-login-subtitle">管理员登录</p>

    <?php if ($lockMessage): ?>
    <div class="alert alert-warning py-2" role="alert">
        <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($lockMessage); ?>
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div class="alert alert-danger py-2" role="alert">
        <i class="fas fa-ban"></i> <?php echo htmlspecialchars($error); ?>
    </div>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
        <div class="mb-3">
            <label class="form-label" for="username">用户名</label>
            <input type="text" class="form-control" id="username" name="username" placeholder="请输入用户名" value="<?php echo htmlspecialchars($username, ENT_QUOTES); ?>" autocomplete="username" required <?php echo isAccountLocked() ? 'disabled' : ''; ?>>
        </div>
        <div class="mb-3">
            <label class="form-label" for="password">密码</label>
            <input type="password" class="form-control" id="password" name="password" placeholder="请输入密码" autocomplete="current-password" required <?php echo isAccountLocked() ? 'disabled' : ''; ?>>
        </div>
        <button type="submit" name="login" class="btn btn-primary w-100" <?php echo isAccountLocked() ? 'disabled' : ''; ?>>
            <i class="fas fa-sign-in-alt"></i> 登录
        </button>
    </form>

    <p class="text-muted text-center mb-0" style="font-size: .8rem; margin-top: 1.25rem;">
        首次使用请查阅项目说明文档配置管理员账号
    </p>
</div>

<script src="../public/js/jquery.min.js?v=<?php echo APP_VERSION; ?>"></script>
<script src="../public/js/bootstrap.bundle.min.js?v=<?php echo APP_VERSION; ?>"></script>
</body>
</html>