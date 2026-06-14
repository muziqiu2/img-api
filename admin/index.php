<?php
require_once dirname(__DIR__) . '/config.php';

// 已登录用户跳转到管理面板
if (IS_LOGGED_IN) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';
$csrfToken = generateCsrfToken();

// 处理登录请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $token = $_POST['csrf_token'] ?? '';
    
    // 先检查账户是否被锁定（防止绕过）
    if (isAccountLocked()) {
        $config = getUserConfig();
        $remainingTime = max(0, ($config['locked_until'] - time()) / 60);
        $error = "账户已临时锁定，请 " . number_format($remainingTime, 0) . " 分钟后再试";
    }
    // 验证 CSRF Token
    elseif (!validateCsrfToken($token)) {
        $error = "安全验证失败，请刷新页面重试";
    }
    // 验证用户名和密码
    elseif (empty($username) || empty($password)) {
        $error = "用户名和密码不能为空";
        recordLoginAttempt(false);
    }
    // 验证用户名是否正确
    elseif ($username !== getCurrentUsername()) {
        $error = "用户名或密码不正确";
        recordLoginAttempt(false);
    }
    // 验证密码是否正确
    elseif (!verifyPassword($password)) {
        $remaining = getRemainingAttempts();
        $error = "用户名或密码不正确";
        if ($remaining > 0) {
            $error .= "，还剩 $remaining 次尝试机会";
        }
        recordLoginAttempt(false);
    }
    // 登录成功
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

// 检查是否有锁定信息需要显示
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>魔法师API - 登录</title>
    <link href="https://cdn.staticfile.org/twitter-bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.staticfile.org/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --danger: #ef4444;
        }
        
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            margin: 0;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 2rem;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.05);
        }
        
        .login-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        
        .login-header h1 {
            font-size: 1.8rem;
            color: var(--primary);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .login-header h1 i {
            margin-right: 0.5rem;
        }
        
        .login-header p {
            color: #64748b;
            margin: 0;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 500;
            color: #475569;
        }
        
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .btn-primary {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--primary);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-light);
        }
        
        .alert-error {
            padding: 1rem;
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
        }
        
        .alert-error i {
            margin-right: 0.5rem;
        }
        
        .lock-message {
            padding: 1rem;
            background-color: rgba(245, 158, 11, 0.1);
            color: #d97706;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            text-align: center;
        }
        
        .form-footer {
            text-align: center;
            margin-top: 1.5rem;
            color: #64748b;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-header">
            <h1><i class="fas fa-magic"></i>魔法师API</h1>
            <p>管理员登录</p>
        </div>
        
        <?php if ($lockMessage): ?>
            <div class="lock-message">
                <?php echo $lockMessage; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-circle"></i>
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
            <div class="form-group">
                <label for="username" class="form-label">用户名</label>
                <input type="text" id="username" name="username" class="form-control" 
                       value="<?php echo htmlspecialchars($username); ?>" required 
                       <?php echo isAccountLocked() ? 'disabled' : ''; ?>>
            </div>
            
            <div class="form-group">
                <label for="password" class="form-label">密码</label>
                <input type="password" id="password" name="password" class="form-control" required
                       <?php echo isAccountLocked() ? 'disabled' : ''; ?>>
            </div>
            
            <button type="submit" name="login" class="btn-primary" 
                    <?php echo isAccountLocked() ? 'disabled' : ''; ?>>
                <i class="fas fa-sign-in-alt me-1"></i> 登录
            </button>
        </form>
        
        <div class="form-footer">
            <p>默认账号: admin | 默认密码: 123456</p>
            <p>登录后请及时修改密码</p>
        </div>
    </div>
    
    <script src="https://cdn.staticfile.org/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.staticfile.org/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
</body>
</html>
