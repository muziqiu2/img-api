<?php
require_once '../config.php';

// 检查登录状态
if (!IS_LOGGED_IN) {
    header('Location: index.php');
    exit;
}

// 获取当前分区
$currentSection = isset($_GET['section']) ? $_GET['section'] : 'management';

// 处理表单提交
$message = '';
$messageType = '';
$csrfToken = generateCsrfToken();
$currentType = isset($_GET['type']) && $_GET['type'] === 'pe' ? 'pe' : 'pc';
$currentPage = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 20;

// 处理添加URL
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证 CSRF Token
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $message = "安全验证失败，请刷新页面重试";
        $messageType = 'error';
    } else {
        // 处理图片链接管理
        if ($currentSection === 'management') {
            if (isset($_POST['add_urls'])) {
            $urls = isset($_POST['urls']) ? explode("\n", $_POST['urls']) : [];
            $added = addImageUrls($urls, $currentType);
            
            if ($added > 0) {
                $message = "成功添加 $added 个图片链接";
                $messageType = 'success';
                logAdminAction("添加了 $added 个" . ($currentType === 'pc' ? 'PC端' : '移动端') . "图片链接");
            } else {
                $message = "没有添加新的图片链接，可能是重复或无效链接";
                $messageType = 'warning';
            }
        }
        // 处理删除URL
        elseif (isset($_POST['delete_url'])) {
            $url = isset($_POST['url']) ? $_POST['url'] : '';
            if (deleteImageUrl($url, $currentType)) {
                $message = "图片链接已成功删除";
                $messageType = 'success';
                logAdminAction("删除了" . ($currentType === 'pc' ? 'PC端' : '移动端') . "图片链接: $url");
            } else {
                $message = "删除失败，未找到该图片链接";
                $messageType = 'error';
            }
        }
        // 处理清空操作
        elseif (isset($_POST['clear_all'])) {
            if (clearImageUrls($currentType)) {
                $message = "所有图片链接已清空";
                $messageType = 'success';
                logAdminAction("清空了所有" . ($currentType === 'pc' ? 'PC端' : '移动端') . "图片链接");
            } else {
                $message = "清空失败，请重试";
                $messageType = 'error';
            }
        }
    }
    // 处理用户信息更新
    elseif ($currentSection === 'user') {
        if (isset($_POST['update_user'])) {
            $newUsername = trim($_POST['new_username']);
            $newPassword = trim($_POST['new_password']);
            $confirmPassword = trim($_POST['confirm_password']);
            
            // 验证用户名
            if (empty($newUsername)) {
                $message = "用户名不能为空";
                $messageType = 'error';
            } elseif (strlen($newUsername) < 3 || strlen($newUsername) > 20) {
                $message = "用户名长度应在3-20个字符之间";
                $messageType = 'error';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
                $message = "用户名只能包含字母、数字和下划线";
                $messageType = 'error';
            }
            // 验证密码（如果提供了新密码）
            elseif (!empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    $message = "密码长度至少为6位";
                    $messageType = 'error';
                } elseif ($newPassword !== $confirmPassword) {
                    $message = "两次输入的密码不一致";
                    $messageType = 'error';
                } else {
                    // 更新用户信息
                    if (updateUserInfo($newUsername, $newPassword)) {
                        $_SESSION['admin_username'] = $newUsername;
                        $message = "用户信息更新成功";
                        $messageType = 'success';
                        logAdminAction("更新了用户信息");
                    } else {
                        $message = "用户信息更新失败，请重试";
                        $messageType = 'error';
                    }
                }
            } else {
                // 只更新用户名
                if (updateUserInfo($newUsername)) {
                    $_SESSION['admin_username'] = $newUsername;
                    $message = "用户信息更新成功";
                    $messageType = 'success';
                    logAdminAction("更新了用户信息");
                } else {
                    $message = "用户信息更新失败，请重试";
                    $messageType = 'error';
                }
            }
        }
    }
    }
}

// 获取数据
if ($currentSection === 'management') {
    // 获取图片URL列表
    $imageData = getImageUrls($currentType, $currentPage, $perPage);
    $urls = $imageData['urls'];
    $totalPages = $imageData['pages'];
}

// 获取统计数据
$stats = getCallCount();
$adminLogs = getAdminLogs(10);
$currentUsername = getCurrentUsername();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>魔法师API - 后台管理</title>
    <!-- 国内CDN资源 -->
    <link href="https://cdn.staticfile.org/twitter-bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.staticfile.org/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <style>
        :root {
            --primary: #6366f1;
            --primary-light: #818cf8;
            --danger: #ef4444;
            --success: #10b981;
            --warning: #f59e0b;
            --light-bg: #f8fafc;
            --card-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
        }
        
        body {
            font-family: system-ui, -apple-system, sans-serif;
            background-color: var(--light-bg);
            color: #334155;
            min-height: 100vh;
            margin: 0;
        }
        
        .sidebar {
            background-color: white;
            border-right: 1px solid #e2e8f0;
            padding: 1.5rem 0;
            height: 100vh;
            position: sticky;
            top: 0;
        }
        
        .sidebar-header {
            padding: 0 1.5rem 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            margin-bottom: 1.5rem;
        }
        
        .sidebar-header h1 {
            font-size: 1.5rem;
            margin: 0;
            color: var(--primary);
            display: flex;
            align-items: center;
        }
        
        .sidebar-header h1 i {
            margin-right: 0.5rem;
        }
        
        .nav-link {
            color: #64748b;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            text-decoration: none;
        }
        
        .nav-link i {
            margin-right: 0.75rem;
            width: 20px;
        }
        
        .nav-link:hover, .nav-link.active {
            color: var(--primary);
            background-color: rgba(99, 102, 241, 0.05);
        }
        
        .main-content {
            padding: 1.5rem;
        }
        
        .header-actions {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        
        .card {
            background: white;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
            border: none;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid #e2e8f0;
            padding: 1rem 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h2 {
            margin: 0;
            font-size: 1.25rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .btn {
            padding: 0.5rem 1rem;
            border-radius: 6px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
        }
        
        .btn-primary {
            background-color: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background-color: var(--primary-light);
        }
        
        .btn-danger {
            background-color: var(--danger);
            color: white;
        }
        
        .btn-danger:hover {
            background-color: #dc2626;
        }
        
        .btn-outline {
            background-color: transparent;
            border: 1px solid #e2e8f0;
            color: #64748b;
        }
        
        .btn-outline:hover {
            background-color: #f1f5f9;
        }
        
        .form-control {
            width: 100%;
            padding: 0.6rem 0.75rem;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
        }
        
        .alert {
            padding: 1rem 1.25rem;
            border-radius: 6px;
            margin-bottom: 1.5rem;
            border: none;
            display: flex;
            align-items: center;
        }
        
        .alert i {
            margin-right: 0.5rem;
        }
        
        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
        
        .alert-warning {
            background-color: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .alert-error {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .url-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        
        .url-item {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            transition: background-color 0.2s ease;
        }
        
        .url-item:last-child {
            border-bottom: none;
        }
        
        .url-item:hover {
            background-color: #f8fafc;
        }
        
        .url-link {
            color: #3b82f6;
            text-decoration: none;
            max-width: 80%;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .url-link:hover {
            text-decoration: underline;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            padding: 0;
            margin: 1.5rem 0 0;
            list-style: none;
        }
        
        .pagination li {
            margin: 0 0.25rem;
        }
        
        .pagination a {
            display: block;
            padding: 0.5rem 1rem;
            border-radius: 6px;
            color: #64748b;
            text-decoration: none;
            transition: all 0.2s ease;
        }
        
        .pagination a:hover, .pagination a.active {
            background-color: var(--primary);
            color: white;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }
        
        .stat-card {
            padding: 1rem;
            background-color: white;
            border-radius: 8px;
            box-shadow: var(--card-shadow);
        }
        
        .stat-label {
            color: #64748b;
            font-size: 0.9rem;
            margin-bottom: 0.25rem;
        }
        
        .stat-value {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
        }
        
        .log-item {
            padding: 0.75rem 0;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.9rem;
        }
        
        .log-item:last-child {
            border-bottom: none;
        }
        
        .log-time {
            color: #94a3b8;
            margin-right: 0.5rem;
        }
        
        .log-user {
            color: var(--primary);
            margin-right: 0.5rem;
        }
        
        .log-ip {
            color: #94a3b8;
            margin-left: 0.5rem;
            font-size: 0.8rem;
        }
        
        .tab-buttons {
            display: flex;
            margin-bottom: 1rem;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .tab-button {
            padding: 0.75rem 1.5rem;
            background: none;
            border: none;
            cursor: pointer;
            font-weight: 500;
            color: #64748b;
            position: relative;
        }
        
        .tab-button.active {
            color: var(--primary);
        }
        
        .tab-button.active::after {
            content: "";
            position: absolute;
            bottom: -1px;
            left: 0;
            width: 100%;
            height: 2px;
            background-color: var(--primary);
        }
        
        .section {
            display: none;
        }
        
        .section.active {
            display: block;
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
        
        .help-text {
            color: #94a3b8;
            font-size: 0.85rem;
            margin-top: 0.25rem;
        }
        
        @media (max-width: 768px) {
            .sidebar {
                height: auto;
                position: relative;
            }
            
            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }
            
            .url-item {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }
            
            .header-actions {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- 侧边栏 -->
            <div class="col-md-3 col-lg-2 d-md-block sidebar collapse">
                <div class="sidebar-header">
                    <h1><i class="fas fa-magic"></i>魔法师API</h1>
                </div>
                <div class="nav flex-column">
                    <a class="nav-link <?php echo $currentSection === 'management' ? 'active' : ''; ?>" href="dashboard.php?section=management">
                        <i class="fas fa-tachometer-alt"></i> 图片管理
                    </a>
                    <a class="nav-link <?php echo $currentSection === 'logs' ? 'active' : ''; ?>" href="dashboard.php?section=logs">
                        <i class="fas fa-history"></i> 操作日志
                    </a>
                    <a class="nav-link <?php echo $currentSection === 'user' ? 'active' : ''; ?>" href="dashboard.php?section=user">
                        <i class="fas fa-user-cog"></i> 用户设置
                    </a>
                    <a class="nav-link" href="logout.php">
                        <i class="fas fa-sign-out-alt"></i> 退出登录
                    </a>
                </div>
            </div>

            <!-- 主内容区 -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <div class="header-actions">
                    <h2>
                        <?php 
                        if ($currentSection === 'management') echo '图片管理';
                        elseif ($currentSection === 'logs') echo '操作日志';
                        elseif ($currentSection === 'user') echo '用户设置';
                        ?>
                    </h2>
                    <div>
                        <span class="me-3">登录用户: <?php echo htmlspecialchars($currentUsername); ?></span>
                        <span class="me-3">登录时间: <?php echo date('Y-m-d H:i', $_SESSION['admin_login_time'] ?? time()); ?></span>
                        <a href="logout.php" class="btn btn-outline">
                            <i class="fas fa-sign-out-alt me-1"></i> 退出
                        </a>
                    </div>
                </div>
                
                <!-- 消息提示 -->
                <?php if ($message): ?>
                    <div class="alert alert-<?php echo $messageType; ?>">
                        <?php 
                        if ($messageType === 'success') echo '<i class="fas fa-check-circle"></i>';
                        elseif ($messageType === 'warning') echo '<i class="fas fa-exclamation-triangle"></i>';
                        elseif ($messageType === 'error') echo '<i class="fas fa-times-circle"></i>';
                        echo $message;
                        ?>
                    </div>
                <?php endif; ?>
                
                <!-- 图片管理区域 -->
                <div class="section <?php echo $currentSection === 'management' ? 'active' : ''; ?>">
                    <!-- 统计卡片 -->
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-label">总调用次数</div>
                            <div class="stat-value"><?php echo number_format($stats['total'] ?? 0); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">PC端图片数</div>
                            <div class="stat-value"><?php echo getImageCount('pc'); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">移动端图片数</div>
                            <div class="stat-value"><?php echo getImageCount('pe'); ?></div>
                        </div>
                        <div class="stat-card">
                            <div class="stat-label">今日调用</div>
                            <div class="stat-value"><?php echo $stats['daily'][date('Y-m-d')]['total'] ?? 0; ?></div>
                        </div>
                    </div>
                    
                    <!-- 图片链接管理 -->
                    <div class="card">
                        <div class="card-header">
                            <h2>图片链接管理</h2>
                        </div>
                        <div class="card-body">
                            <!-- 类型切换标签 -->
                            <div class="tab-buttons">
                                <button class="tab-button <?php echo $currentType === 'pc' ? 'active' : ''; ?>" onclick="window.location='dashboard.php?section=management&type=pc'">
                                    PC端图片
                                </button>
                                <button class="tab-button <?php echo $currentType === 'pe' ? 'active' : ''; ?>" onclick="window.location='dashboard.php?section=management&type=pe'">
                                    移动端图片
                                </button>
                            </div>
                            
                            <!-- 添加图片链接表单 -->
                            <form method="post" class="mb-5">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <div class="mb-3">
                                    <label for="urls" class="form-label">添加图片链接（每行一个URL）</label>
                                    <textarea id="urls" name="urls" class="form-control" rows="4" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg"></textarea>
                                </div>
                                <button type="submit" name="add_urls" class="btn btn-primary">
                                    <i class="fas fa-plus me-1"></i> 添加图片链接
                                </button>
                            </form>
                            
                            <!-- 图片链接列表 -->
                            <div>
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h3><?php echo $currentType === 'pc' ? 'PC端' : '移动端'; ?>图片链接列表 (共 <?php echo $imageData['total'] ?? 0; ?> 个)</h3>
                                    <form method="post" onsubmit="return confirm('确定要清空所有<?php echo $currentType === 'pc' ? 'PC端' : '移动端'; ?>图片链接吗？此操作不可恢复！');">
                                        <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                        <button type="submit" name="clear_all" class="btn btn-danger">
                                            <i class="fas fa-trash me-1"></i> 清空所有
                                        </button>
                                    </form>
                                </div>
                                
                                <?php if (empty($urls)): ?>
                                    <div class="alert alert-warning">
                                        <i class="fas fa-info-circle"></i> 没有找到图片链接，请添加新的图片链接
                                    </div>
                                <?php else: ?>
                                    <ul class="url-list">
                                        <?php foreach ($urls as $url): ?>
                                            <li class="url-item">
                                                <a href="<?php echo $url; ?>" target="_blank" class="url-link" title="<?php echo $url; ?>">
                                                    <?php echo $url; ?>
                                                </a>
                                                <form method="post" onsubmit="return confirm('确定要删除这个图片链接吗？');" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                                    <input type="hidden" name="url" value="<?php echo htmlspecialchars($url); ?>">
                                                    <button type="submit" name="delete_url" class="btn btn-outline">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                    
                                    <!-- 分页 -->
                                    <?php if ($totalPages > 1): ?>
                                        <ul class="pagination">
                                            <?php if ($currentPage > 1): ?>
                                                <li><a href="dashboard.php?section=management&type=<?php echo $currentType; ?>&page=<?php echo $currentPage - 1; ?>">上一页</a></li>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                                <li><a href="dashboard.php?section=management&type=<?php echo $currentType; ?>&page=<?php echo $i; ?>" class="<?php echo $i == $currentPage ? 'active' : ''; ?>"><?php echo $i; ?></a></li>
                                            <?php endfor; ?>
                                            
                                            <?php if ($currentPage < $totalPages): ?>
                                                <li><a href="dashboard.php?section=management&type=<?php echo $currentType; ?>&page=<?php echo $currentPage + 1; ?>">下一页</a></li>
                                            <?php endif; ?>
                                        </ul>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- 操作日志区域 -->
                <div class="section <?php echo $currentSection === 'logs' ? 'active' : ''; ?>">
                    <div class="card">
                        <div class="card-header">
                            <h2>操作日志</h2>
                        </div>
                        <div class="card-body">
                            <?php 
                            $logs = getAdminLogs(100);
                            if (empty($logs)): 
                            ?>
                                <div class="alert alert-warning">
                                    <i class="fas fa-info-circle"></i> 暂无操作日志
                                </div>
                            <?php else: ?>
                                <div class="log-list">
                                    <?php foreach ($logs as $log): ?>
                                        <div class="log-item">
                                            <span class="log-time"><?php echo $log['time']; ?></span>
                                            <span class="log-user">[<?php echo $log['user']; ?>]</span>
                                            <span><?php echo $log['action']; ?></span>
                                            <span class="log-ip">IP: <?php echo $log['ip']; ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- 用户设置区域 -->
                <div class="section <?php echo $currentSection === 'user' ? 'active' : ''; ?>">
                    <div class="card">
                        <div class="card-header">
                            <h2>用户设置</h2>
                        </div>
                        <div class="card-body">
                            <form method="post">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                <div class="form-group">
                                    <label for="new_username" class="form-label">用户名</label>
                                    <input type="text" id="new_username" name="new_username" class="form-control" 
                                           value="<?php echo htmlspecialchars($currentUsername); ?>" required>
                                    <p class="help-text">修改管理员登录用户名</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="new_password" class="form-label">新密码</label>
                                    <input type="password" id="new_password" name="new_password" class="form-control" 
                                           placeholder="不修改请留空">
                                    <p class="help-text">密码长度至少6位，建议包含字母和数字</p>
                                </div>
                                
                                <div class="form-group">
                                    <label for="confirm_password" class="form-label">确认新密码</label>
                                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" 
                                           placeholder="再次输入新密码">
                                </div>
                                
                                <button type="submit" name="update_user" class="btn btn-primary">
                                    <i class="fas fa-save me-1"></i> 保存设置
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.staticfile.org/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.staticfile.org/twitter-bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        // 自动调整textarea高度
        document.getElementById('urls')?.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });
        
        // 初始化textarea高度
        window.addEventListener('load', function() {
            const textarea = document.getElementById('urls');
            if (textarea) {
                textarea.style.height = (textarea.scrollHeight) + 'px';
            }
        });
    </script>
</body>
</html>
