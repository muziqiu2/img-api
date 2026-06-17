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
        $remainingTime = max(0, ($config['locked_until'] - time()) / 60);
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
    $remainingTime = max(0, ($config['locked_until'] - time()) / 60);
    $lockMessage = "账户已临时锁定，请 " . number_format($remainingTime, 0) . " 分钟后再试";
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>魔法师API - 登录</title>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <link rel="stylesheet" href="/public/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="login-logo">
        <a href="/"><i class="fas fa-magic"></i> <b>魔法师</b>API</a>
    </div>
    <div class="card">
        <div class="card-body login-card-body">
            <p class="login-box-msg">管理员登录</p>
            
            <?php if ($lockMessage): ?>
            <div class="alert alert-warning">
                <i class="icon fas fa-exclamation-triangle"></i> <?php echo $lockMessage; ?>
            </div>
            <?php endif; ?>
            
            <?php if ($error): ?>
            <div class="alert alert-danger">
                <i class="icon fas fa-ban"></i> <?php echo htmlspecialchars($error); ?>
            </div>
            <?php endif; ?>
            
            <form method="post">
                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" name="username" placeholder="用户名" value="<?php echo htmlspecialchars($username); ?>" required <?php echo isAccountLocked() ? 'disabled' : ''; ?>>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-user"></span>
                        </div>
                    </div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" class="form-control" name="password" placeholder="密码" required <?php echo isAccountLocked() ? 'disabled' : ''; ?>>
                    <div class="input-group-append">
                        <div class="input-group-text">
                            <span class="fas fa-lock"></span>
                        </div>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" name="login" class="btn btn-primary btn-block" <?php echo isAccountLocked() ? 'disabled' : ''; ?>>
                            <i class="fas fa-sign-in-alt mr-1"></i> 登录
                        </button>
                    </div>
                </div>
            </form>
            
            <div class="mt-3 text-center">
                <p class="text-muted" style="font-size: 0.85rem;">
                    默认账号: admin | 默认密码: 123456<br>
                    登录后请及时修改密码
                </p>
            </div>
        </div>
    </div>
</div>

<script src="/public/js/jquery.min.js"></script>
<script src="/public/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
</body>
</html>