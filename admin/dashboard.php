<?php
require_once dirname(__DIR__) . '/config.php';

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
$perPage = 10;

// 检查管理后台频率限制：仅对 POST 写操作生效（防止自动化脚本批量操作），
// GET 页面浏览不受限，避免管理员正常快速点页面被误判为 429
$rateLimited = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !checkAdminRateLimit()) {
    $rateLimited = true;
    $message = "请求过于频繁，请稍后再试";
    $messageType = 'error';
}

// 处理表单提交（限频时跳过所有写操作）
if (!$rateLimited && $_SERVER['REQUEST_METHOD'] === 'POST' && $currentSection === 'management') {
    if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
        $message = "安全验证失败，请刷新页面重试";
        $messageType = 'error';
    } else {
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
        elseif (isset($_POST['delete_url'])) {
            $url = trim($_POST['delete_url'] ?? '');
            if (deleteImageUrl($url, $currentType)) {
                $message = "图片链接已成功删除";
                $messageType = 'success';
                logAdminAction("删除了" . ($currentType === 'pc' ? 'PC端' : '移动端') . "图片链接: $url");
            } else {
                $message = "删除失败，未找到该图片链接";
                $messageType = 'error';
            }
        }
        elseif (isset($_POST['delete_urls']) && is_array($_POST['delete_urls'])) {
            $deletedCount = 0;
            foreach ($_POST['delete_urls'] as $url) {
                if (deleteImageUrl($url, $currentType)) {
                    $deletedCount++;
                }
            }
            if ($deletedCount > 0) {
                $message = "已成功删除 $deletedCount 个图片链接";
                $messageType = 'success';
                logAdminAction("批量删除了 $deletedCount 个" . ($currentType === 'pc' ? 'PC端' : '移动端') . "图片链接");
            } else {
                $message = "删除失败，未找到选中的图片链接";
                $messageType = 'error';
            }
        }
    }
}
elseif (!$rateLimited && $_SERVER['REQUEST_METHOD'] === 'POST' && $currentSection === 'user') {
    if (isset($_POST['update_user'])) {
        if (!isset($_POST['csrf_token']) || !validateCsrfToken($_POST['csrf_token'])) {
            $message = "安全验证失败，请刷新页面重试";
            $messageType = 'error';
        } else {
            $currentPassword = trim($_POST['current_password'] ?? '');
            $newUsername = trim($_POST['new_username']);
            $newPassword = trim($_POST['new_password']);
            $confirmPassword = trim($_POST['confirm_password']);

            // 验证原密码
            if (empty($currentPassword)) {
                $message = "请输入原密码";
                $messageType = 'error';
            } elseif (!verifyPassword($currentPassword)) {
                $message = "原密码不正确";
                $messageType = 'error';
            } elseif (empty($newUsername)) {
                $message = "用户名不能为空";
                $messageType = 'error';
            } elseif (strlen($newUsername) < 3 || strlen($newUsername) > 20) {
                $message = "用户名长度应在3-20个字符之间";
                $messageType = 'error';
            } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $newUsername)) {
                $message = "用户名只能包含字母、数字和下划线";
                $messageType = 'error';
            }
            elseif (!empty($newPassword)) {
                if (strlen($newPassword) < 6) {
                    $message = "密码长度至少为6位";
                    $messageType = 'error';
                } elseif ($newPassword !== $confirmPassword) {
                    $message = "两次输入的密码不一致";
                    $messageType = 'error';
                } else {
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

// 获取数据
if ($currentSection === 'management') {
    $imageData = getImageUrls($currentType, $currentPage, $perPage);
    $urls = $imageData['urls'];
    $totalPages = $imageData['pages'];
}

$stats = getCallCount();
$adminLogs = getAdminLogs(50);
$currentUsername = getCurrentUsername();

// 强制修改默认密码：仍在使用默认密码时，除用户设置页外一律强制跳转过去
$mustChangePassword = isDefaultPassword();
if ($mustChangePassword && $currentSection !== 'user') {
    header('Location: dashboard.php?section=user&force_change=1');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>魔法师API - 管理后台</title>
    <!-- Font Awesome -->
    <link rel="stylesheet" href="../public/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="../public/css/adminlte.min.css">
</head>
<body class="hold-transition sidebar-mini layout-fixed">
<div class="wrapper">

    <!-- Navbar -->
    <nav class="main-header navbar navbar-expand navbar-white navbar-light">
        <ul class="navbar-nav">
            <li class="nav-item">
                <a class="nav-link" data-widget="pushmenu" href="#" role="button"><i class="fas fa-bars"></i></a>
            </li>
        </ul>
        <ul class="navbar-nav ml-auto">
            <li class="nav-item d-none d-sm-inline-block">
                <span class="nav-link">登录用户: <?php echo htmlspecialchars($currentUsername); ?></span>
            </li>
            <li class="nav-item d-none d-sm-inline-block">
                <a href="logout.php" class="nav-link">退出</a>
            </li>
        </ul>
    </nav>

    <!-- Main Sidebar Container -->
    <aside class="main-sidebar sidebar-dark-primary elevation-4">
        <a href="../" class="brand-link">
            <i class="fas fa-magic nav-icon ml-3 mr-2"></i>
            <span class="brand-text font-weight-light">魔法师API</span>
        </a>

        <div class="sidebar">
            <nav class="mt-2">
                <ul class="nav nav-pills nav-sidebar flex-column" data-widget="treeview" role="menu" data-accordion="false">
                    <li class="nav-item">
                        <a href="?section=management" class="nav-link <?php echo $currentSection === 'management' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-image"></i>
                            <p>图片管理</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?section=logs" class="nav-link <?php echo $currentSection === 'logs' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-history"></i>
                            <p>操作日志</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?section=user" class="nav-link <?php echo $currentSection === 'user' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-user-cog"></i>
                            <p>用户设置</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?section=site" class="nav-link <?php echo $currentSection === 'site' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-globe"></i>
                            <p>网站设置</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?section=update" class="nav-link <?php echo $currentSection === 'update' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-sync-alt"></i>
                            <p>系统更新</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="?section=environment" class="nav-link <?php echo $currentSection === 'environment' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-stethoscope"></i>
                            <p>环境检测</p>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="logout.php" class="nav-link">
                            <i class="nav-icon fas fa-sign-out-alt"></i>
                            <p>退出登录</p>
                        </a>
                    </li>
                </ul>
            </nav>
        </div>
    </aside>

    <!-- Content Wrapper -->
    <div class="content-wrapper">
        <div class="content-header">
            <div class="container-fluid">
                <div class="row mb-2">
                    <div class="col-sm-6">
                        <h1 class="m-0">
                            <?php
                            if ($currentSection === 'management') echo '图片管理';
                            elseif ($currentSection === 'logs') echo '操作日志';
                            elseif ($currentSection === 'user') echo '用户设置';
                            elseif ($currentSection === 'site') echo '网站设置';
                            elseif ($currentSection === 'update') echo '系统更新';
                            elseif ($currentSection === 'environment') echo '环境检测';
                            ?>
                        </h1>
                    </div>
                </div>
            </div>
        </div>

        <div class="content">
            <div class="container-fluid">
                <?php if ($message): ?>
                <div class="alert alert-<?php echo $messageType === 'error' ? 'danger' : ($messageType === 'warning' ? 'warning' : 'success'); ?> alert-dismissible">
                    <button type="button" class="close" data-bs-dismiss="alert">&times;</button>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <?php if ($mustChangePassword): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-bs-dismiss="alert">&times;</button>
                    <i class="icon fas fa-exclamation-triangle"></i>
                    <strong>安全警告：</strong>当前仍在使用默认密码（123456），系统已禁止使用其他功能，请立即修改密码！
                </div>
                <?php endif; ?>

                <?php
                // 各 section 内容拆分为独立视图，避免巨石文件。
                // include 在 dashboard.php 作用域内，可直接使用上方加载的 $stats/$urls/$csrfToken 等变量。
                switch ($currentSection) {
                    case 'management':
                        include __DIR__ . '/views/management.php';
                        break;
                    case 'logs':
                        include __DIR__ . '/views/logs.php';
                        break;
                    case 'user':
                        include __DIR__ . '/views/user.php';
                        break;
                    case 'site':
                        include __DIR__ . '/views/site.php';
                        break;
                    case 'environment':
                        include __DIR__ . '/views/environment.php';
                        break;
                    case 'update':
                    default:
                        include __DIR__ . '/views/update.php';
                        break;
                }
                ?>
            </div>
        </div>
    </div>

    <!-- 通用确认弹窗 -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="confirmTitle">确认操作</h5>
                <button type="button" class="close" id="confirmModalClose" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage" class="mb-0">确定要执行此操作吗？</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="confirmModalCancel">取消</button>
                <button type="button" class="btn btn-primary" id="confirmModalYes">确定</button>
            </div>
        </div>
    </div>
</div>

<!-- 非阻塞 Toast 通知容器 -->
<div class="app-toast-container" id="appToasts"></div>

<style>
.app-toast-container {
    position: fixed;
    top: 16px;
    right: 16px;
    z-index: 1080;
    display: flex;
    flex-direction: column;
    gap: 8px;
    max-width: 380px;
}
.app-toast {
    display: flex;
    align-items: center;
    padding: 10px 14px;
    border-radius: 6px;
    color: #fff;
    box-shadow: 0 4px 14px rgba(0,0,0,0.18);
    font-size: 14px;
    opacity: 1;
    transform: translateY(0);
    transition: opacity .3s ease, transform .3s ease;
    word-break: break-word;
}
.app-toast i { margin-right: 8px; flex-shrink: 0; }
.app-toast span { flex: 1; }
/* 移动端表格长文本处理：允许折行，避免长文件名/说明超出卡片撑破界面 */
.table-responsive { -webkit-overflow-scrolling: touch; }
.table-wrap-text th,
.table-wrap-text td {
    overflow-wrap: anywhere;
    word-break: break-word;
}
.table-wrap-text code {
    word-break: break-all;
    white-space: normal;
}
/* 操作列等不希望折行的单元格（仅在本表中豁免） */
.table-wrap-text .nowrap { white-space: nowrap; }
.app-toast-close {
    margin-left: 10px;
    padding: 0 2px;
    background: transparent;
    border: none;
    color: inherit;
    font-size: 18px;
    line-height: 1;
    cursor: pointer;
    opacity: .7;
}
.app-toast-close:hover { opacity: 1; }
.app-toast-success { background: #28a745; }
.app-toast-info    { background: #17a2b8; }
.app-toast-warning { background: #ffc107; color: #343a40; }
.app-toast-error   { background: #dc3545; }
.app-toast-hide { opacity: 0; transform: translateY(-8px); }
</style>

    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline">
            <strong>魔法师随机图片API</strong>
        </div>
    </footer>
</div>

<!-- jQuery -->
<script src="../public/js/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="../public/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="../public/js/adminlte.min.js"></script>
<!-- 自定义确认模态框处理 -->
<script>
    // APP 配置由 PHP 注入，供外置 public/js/admin.js 读取（csrf/version/section）
    var APP = {
        csrf: <?php echo json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        version: <?php echo json_encode(APP_VERSION, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>,
        section: <?php echo json_encode($currentSection, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>
    };
</script>
<script src="../public/js/admin.js"></script>
</body>
</html>