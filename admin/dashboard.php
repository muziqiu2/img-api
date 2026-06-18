<?php
require_once dirname(__DIR__) . '/config.php';

// 检查登录状态
if (!IS_LOGGED_IN) {
    header('Location: index.php');
    exit;
}

// 检查管理后台频率限制
if (!checkAdminRateLimit()) {
    $message = "请求过于频繁，请稍后再试";
    $messageType = 'error';
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

// 处理删除请求 (支持 GET 方式)
if (isset($_GET['delete']) && isset($_GET['token'])) {
    $deleteUrl = $_GET['delete'];
    $token = $_GET['token'];
    if (validateCsrfToken($token)) {
        if (deleteImageUrl($deleteUrl, $currentType)) {
            $message = "图片链接已成功删除";
            $messageType = 'success';
            logAdminAction("删除了" . ($currentType === 'pc' ? 'PC端' : '移动端') . "图片链接: $deleteUrl");
        } else {
            $message = "删除失败，未找到该图片链接";
            $messageType = 'error';
        }
    }
    // 清理 URL 参数，避免重复执行
    header('Location: ?section=management&type=' . $currentType);
    exit;
}

// 处理添加URL
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentSection === 'management') {
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
}
elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && $currentSection === 'user') {
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
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>魔法师API - 管理后台</title>
    <!-- Google Font -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,400i,700&display=fallback">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="/public/css/all.min.css">
    <!-- Theme style -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/css/adminlte.min.css">
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
                        <a href="?section=update" class="nav-link <?php echo $currentSection === 'update' ? 'active' : ''; ?>">
                            <i class="nav-icon fas fa-sync-alt"></i>
                            <p>系统更新</p>
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
                            elseif ($currentSection === 'update') echo '系统更新';
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
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <?php if ($currentSection === 'management'): ?>
                <!-- 统计卡片 -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                                <p>总调用次数</p>
                            </div>
                            <div class="icon"><i class="fas fa-chart-line"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?php echo getImageCount('pc'); ?></h3>
                                <p>PC端图片数</p>
                            </div>
                            <div class="icon"><i class="fas fa-desktop"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3><?php echo getImageCount('pe'); ?></h3>
                                <p>移动端图片数</p>
                            </div>
                            <div class="icon"><i class="fas fa-mobile-alt"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?php echo $stats['daily'][date('Y-m-d')]['total'] ?? 0; ?></h3>
                                <p>今日调用</p>
                            </div>
                            <div class="icon"><i class="fas fa-calendar-day"></i></div>
                        </div>
                    </div>
                </div>

                <!-- 类型切换 -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">图片链接管理</h3>
                        <div class="card-tools">
                            <ul class="nav nav-pills ml-auto">
                                <li class="nav-item">
                                    <a href="?section=management&type=pc" class="nav-link <?php echo $currentType === 'pc' ? 'active' : ''; ?>">PC端</a>
                                </li>
                                <li class="nav-item">
                                    <a href="?section=management&type=pe" class="nav-link <?php echo $currentType === 'pe' ? 'active' : ''; ?>">移动端</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- 添加链接表单 -->
                        <form method="post" action="?section=management&type=<?php echo $currentType; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="form-group">
                                <label>添加图片链接（每行一个URL）</label>
                                <textarea name="urls" class="form-control" rows="3" placeholder="https://example.com/image1.jpg"></textarea>
                            </div>
                            <button type="submit" name="add_urls" class="btn btn-primary">
                                <i class="fas fa-plus"></i> 添加图片链接
                            </button>
                            <?php if (!empty($urls)): ?>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- 图片列表 -->
                <?php if (!empty($urls)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php echo $currentType === 'pc' ? 'PC端' : '移动端'; ?>图片链接列表 (共 <?php echo $imageData['total']; ?> 个)
                        </h3>
                        <button type="button" class="btn btn-danger btn-sm float-right" id="deleteSelectedBtn" onclick="deleteSelected(<?php echo htmlspecialchars(json_encode($currentType), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($csrfToken), ENT_QUOTES); ?>)" style="display:none;">
                            <i class="fas fa-trash"></i> 删除选中
                        </button>
                    </div>
                    <div class="card-body table-responsive p-0" style="max-height: 400px;">
                        <table class="table table-head-fixed text-nowrap">
                            <thead>
                                <tr>
                                    <th style="width: 5%"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                                    <th style="width: 85%">URL</th>
                                    <th style="width: 10%">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($urls as $url): ?>
                                <tr>
                                    <td><input type="checkbox" class="url-checkbox" value="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>" onchange="updateDeleteButton()"></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" title="<?php echo htmlspecialchars($url); ?>">
                                            <?php echo htmlspecialchars(strlen($url) > 80 ? substr($url, 0, 80) . '...' : $url); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm" onclick="showDeleteConfirm(<?php echo htmlspecialchars(json_encode($url), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($currentType), ENT_QUOTES); ?>, <?php echo htmlspecialchars(json_encode($csrfToken), ENT_QUOTES); ?>)">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer clearfix">
                        <ul class="pagination pagination-sm m-0 float-right">
                            <?php if ($currentPage > 1): ?>
                            <li class="page-item"><a class="page-link" href="?section=management&type=<?php echo $currentType; ?>&page=<?php echo $currentPage - 1; ?>">&laquo;</a></li>
                            <?php endif; ?>
                            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                            <li class="page-item <?php echo $i == $currentPage ? 'active' : ''; ?>">
                                <a class="page-link" href="?section=management&type=<?php echo $currentType; ?>&page=<?php echo $i; ?>"><?php echo $i; ?></a>
                            </li>
                            <?php endfor; ?>
                            <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item"><a class="page-link" href="?section=management&type=<?php echo $currentType; ?>&page=<?php echo $currentPage + 1; ?>">&raquo;</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="icon fas fa-info"></i> 没有找到图片链接，请添加新的图片链接
                </div>
                <?php endif; ?>

                <?php elseif ($currentSection === 'logs'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">操作日志</h3>
                    </div>
                    <div class="card-body table-responsive p-0" style="max-height: 500px;">
                        <?php if (empty($adminLogs)): ?>
                        <div class="alert alert-warning m-3">
                            <i class="icon fas fa-info"></i> 暂无操作日志
                        </div>
                        <?php else: ?>
                        <table class="table table-head-fixed text-nowrap">
                            <thead>
                                <tr>
                                    <th style="width: 18%">时间</th>
                                    <th style="width: 12%">用户</th>
                                    <th style="width: 50%">操作</th>
                                    <th style="width: 20%">IP地址</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminLogs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['time']); ?></td>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($log['username']); ?></span></td>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><code><?php echo htmlspecialchars($log['ip']); ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>

                <?php elseif ($currentSection === 'user'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">用户设置</h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="?section=user">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="form-group">
                                <label for="current_password">原密码</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" placeholder="请输入原密码" required>
                            </div>
                            <div class="form-group">
                                <label for="new_username">用户名</label>
                                <input type="text" class="form-control" id="new_username" name="new_username" value="<?php echo htmlspecialchars($currentUsername); ?>" required>
                            </div>
                            <div class="form-group">
                                <label for="new_password">新密码</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" placeholder="不修改请留空">
                                <small class="form-text text-muted">密码长度至少6位</small>
                            </div>
                            <div class="form-group">
                                <label for="confirm_password">确认新密码</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="再次输入新密码">
                            </div>
                            <button type="submit" name="update_user" class="btn btn-primary">
                                <i class="fas fa-save"></i> 保存设置
                            </button>
                        </form>
                    </div>
                </div>

                <?php elseif ($currentSection === 'update'): ?>
                <!-- 当前版本信息卡 -->
                <div class="row">
                    <div class="col-lg-6 col-12">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo htmlspecialchars(APP_VERSION); ?></h3>
                                <p>当前版本</p>
                            </div>
                            <div class="icon"><i class="fas fa-code-branch"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-12">
                        <div class="small-box bg-warning" id="latestVersionBox">
                            <div class="inner">
                                <h3 id="latestVersionText">检查中...</h3>
                                <p id="latestVersionLabel">最新版本</p>
                            </div>
                            <div class="icon"><i class="fas fa-cloud-download-alt"></i></div>
                        </div>
                    </div>
                </div>

                <!-- 版本检查与一键更新 -->
                <div class="card" id="updateCard">
                    <div class="card-header">
                        <h3 class="card-title">版本检查与更新</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-primary" onclick="checkUpdate(true)">
                                <i class="fas fa-redo"></i> 重新检查
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group" id="releaseInfoBox" style="display:none;">
                            <label>最新版本发布信息</label>
                            <div class="card bg-light p-3" id="releaseDetails">
                                <div class="mb-2">
                                    <strong id="releaseName"></strong>
                                    <small class="text-muted ml-2" id="releaseDate"></small>
                                </div>
                                <div class="mb-2">
                                    <a id="releaseUrl" href="#" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="fab fa-github"></i> 查看 GitHub Release
                                    </a>
                                </div>
                                <pre id="releaseBody" style="white-space:pre-wrap;background:#f8f9fa;padding:10px;border-radius:4px;"></pre>
                            </div>
                        </div>

                        <div id="envWarningBox"></div>

                        <div class="alert alert-info" id="updateStatus">
                            <i class="icon fas fa-info-circle"></i> 正在检查 GitHub 最新版本...
                        </div>

                        <div class="mt-3" id="updateActionBox" style="display:none;">
                            <button type="button" class="btn btn-success btn-lg" id="updateBtn" onclick="doUpdate()">
                                <i class="fas fa-download"></i> 立即更新到最新版本
                            </button>
                            <small class="form-text text-muted mt-2">
                                更新前将自动备份当前文件；如果更新失败将自动回滚。
                            </small>
                        </div>

                        <div class="progress mt-3" id="progressBar" style="display:none;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progressBarInner" style="width:100%"></div>
                        </div>

                        <div class="mt-3" id="updateLogBox" style="display:none;">
                            <label>更新日志</label>
                            <pre id="updateLog" class="bg-dark text-light p-3 rounded" style="max-height:300px;overflow:auto;font-size:13px;"></pre>
                        </div>
                    </div>
                </div>

                <!-- 备份管理 -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">备份管理</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">每次自动更新时会在更新前创建备份。您也可以在此处手动从任一备份恢复系统。</p>
                        <div id="backupList">
                            <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> 正在加载备份列表...</div>
                        </div>
                    </div>
                </div>

                <!-- 更新历史日志 -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">更新历史</h3>
                    </div>
                    <div class="card-body">
                        <div id="updateHistoryList">
                            <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> 正在加载更新历史...</div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>

    <!-- 自定义确认模态框 -->
<div class="modal fade" id="confirmModal" tabindex="-1" role="dialog">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">确认删除</h5>
                <button type="button" class="close" id="confirmModalClose" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p id="confirmMessage">确定要删除这个图片链接吗？</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="confirmModalCancel">取消</button>
                <button type="button" class="btn btn-danger" id="confirmModalYes">确定删除</button>
            </div>
        </div>
    </div>
</div>

    <footer class="main-footer">
        <div class="float-right d-none d-sm-inline">
            <strong>魔法师随机图片API</strong>
        </div>
    </footer>
</div>

<!-- jQuery -->
<script src="/public/js/jquery.min.js"></script>
<!-- Bootstrap -->
<script src="/public/js/bootstrap.bundle.min.js"></script>
<!-- AdminLTE App -->
<script src="https://cdn.jsdelivr.net/npm/admin-lte@3.2/dist/js/adminlte.min.js"></script>
<!-- 自定义确认模态框处理 -->
<script>
var pendingDeleteUrl = '';
var pendingDeleteType = '';
var pendingDeleteToken = '';
var selectedUrls = [];

function showDeleteConfirm(url, type, token) {
    pendingDeleteUrl = url;
    pendingDeleteType = type;
    pendingDeleteToken = token;
    $('#confirmMessage').text('确定要删除这个图片链接吗？');
    $('#confirmModal').modal('show');
}

function toggleSelectAll() {
    var selectAll = document.getElementById('selectAll');
    var checkboxes = document.querySelectorAll('.url-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = selectAll.checked;
    });
    updateDeleteButton();
}

function updateDeleteButton() {
    var checkboxes = document.querySelectorAll('.url-checkbox');
    selectedUrls = [];
    checkboxes.forEach(function(cb) {
        if (cb.checked) {
            selectedUrls.push(cb.value);
        }
    });
    var deleteBtn = document.getElementById('deleteSelectedBtn');
    if (selectedUrls.length > 0) {
        deleteBtn.style.display = 'inline-block';
    } else {
        deleteBtn.style.display = 'none';
    }
}

function deleteSelected(type, token) {
    if (selectedUrls.length === 0) return;
    pendingDeleteUrl = 'MULTI_DELETE';
    pendingDeleteType = type;
    pendingDeleteToken = token;
    $('#confirmMessage').text('确定要删除选中的 ' + selectedUrls.length + ' 个图片链接吗？');
    $('#confirmModal').modal('show');
}

function executeDelete() {
    if (pendingDeleteUrl === 'MULTI_DELETE') {
        var form = document.createElement('form');
        form.method = 'post';
        form.action = '?section=management&type=' + encodeURIComponent(pendingDeleteType);

        var csrfInput = document.createElement('input');
        csrfInput.type = 'hidden';
        csrfInput.name = 'csrf_token';
        csrfInput.value = pendingDeleteToken;
        form.appendChild(csrfInput);

        selectedUrls.forEach(function(url) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_urls[]';
            input.value = url;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    } else if (pendingDeleteUrl) {
        var redirectUrl = '?section=management&type=' + encodeURIComponent(pendingDeleteType) + '&delete=' + encodeURIComponent(pendingDeleteUrl) + '&token=' + encodeURIComponent(pendingDeleteToken);
        window.location.href = redirectUrl;
    }
    $('#confirmModal').modal('hide');
}

// ============================================
// 系统更新相关 JavaScript（全局函数定义）
// ============================================
var updateCsrfToken = '<?php echo $csrfToken; ?>';
var currentVersion = '<?php echo htmlspecialchars(APP_VERSION); ?>';

function setUpdateStatus(message, type) {
    var box = document.getElementById('updateStatus');
    if (!box) return;
    var iconClass = 'fas fa-info-circle';
    var alertClass = 'alert alert-info';
    if (type === 'success') { alertClass = 'alert alert-success'; iconClass = 'fas fa-check-circle'; }
    else if (type === 'error') { alertClass = 'alert alert-danger'; iconClass = 'fas fa-exclamation-triangle'; }
    else if (type === 'warning') { alertClass = 'alert alert-warning'; iconClass = 'fas fa-exclamation-circle'; }
    box.className = alertClass;
    box.innerHTML = '<i class="icon ' + iconClass + '"></i> ' + message;
}

function appendUpdateLog(line) {
    var logBox = document.getElementById('updateLog');
    if (logBox) {
        logBox.textContent += line + '\n';
        logBox.scrollTop = logBox.scrollHeight;
    }
}

// 前端版本检查缓存配置（5 分钟内不重复请求，避免频繁调用 GitHub API）
var UPDATE_CHECK_LOCAL_CACHE_TTL = 5 * 60 * 1000;
var UPDATE_CHECK_LOCAL_CACHE_KEY = 'app_update_check_cache_v1';

// 渲染版本检查结果到页面（被 checkUpdate 和本地缓存共用）
function renderUpdateResult(data, fromCache, cacheTime) {
    var latestText = document.getElementById('latestVersionText');
    if (!data.success) {
        latestText.textContent = '未知';
        setUpdateStatus('检查失败: ' + (data.error || (data.errors && data.errors.join('; ')) || '未知错误'), 'error');
        return;
    }
    var latest = data.latest;
    latestText.textContent = latest;

    // 环境警告
    if (data.env && !data.env.ok) {
        var html = '<div class="alert alert-danger">';
        html += '<i class="icon fas fa-exclamation-triangle"></i> 环境不满足更新要求:<ul class="mt-2">';
        (data.env.errors || []).forEach(function (m) { html += '<li>' + m + '</li>'; });
        html += '</ul></div>';
        document.getElementById('envWarningBox').innerHTML = html;
    } else if (data.env && data.env.warnings && data.env.warnings.length > 0) {
        var whtml = '<div class="alert alert-warning">';
        whtml += '<i class="icon fas fa-exclamation"></i> 警告:<ul class="mt-2">';
        (data.env.warnings || []).forEach(function (m) { whtml += '<li>' + m + '</li>'; });
        whtml += '</ul></div>';
        document.getElementById('envWarningBox').innerHTML = whtml;
    }

    if (data.has_update) {
        var cacheHint = fromCache && cacheTime ? '（数据更新于 ' + cacheTime + '，5 分钟内自动使用本地缓存，点击右上角按钮可强制重新检查）' : '';
        setUpdateStatus(
            '发现新版本 <strong>' + latest + '</strong>（当前版本 ' + data.current + '）。建议立即更新。' + cacheHint,
            'success'
        );
        document.getElementById('updateActionBox').style.display = 'block';
        if (data.release) {
            document.getElementById('releaseName').textContent = data.release.name || latest;
            document.getElementById('releaseDate').textContent = data.release.published_at ? '  (' + data.release.published_at + ')' : '';
            document.getElementById('releaseUrl').href = data.release.html_url || '#';
            document.getElementById('releaseBody').textContent = data.release.body || '无发布说明';
            document.getElementById('releaseInfoBox').style.display = 'block';
        }
    } else {
        var cacheHint = fromCache && cacheTime ? '（数据更新于 ' + cacheTime + '，5 分钟内自动使用本地缓存，点击右上角按钮可强制重新检查）' : '';
        setUpdateStatus('当前已是最新版本（' + data.current + '）' + cacheHint, 'info');
        document.getElementById('latestVersionText').textContent = '已是最新';
    }
}

function checkUpdate(force) {
    var latestText = document.getElementById('latestVersionText');
    if (latestText) latestText.textContent = '检查中...';
    setUpdateStatus('正在检查 GitHub 最新版本...', 'info');
    document.getElementById('updateActionBox').style.display = 'none';
    document.getElementById('releaseInfoBox').style.display = 'none';
    document.getElementById('envWarningBox').innerHTML = '';

    // 非强制模式下优先使用前端 localStorage 缓存（5 分钟内避免频繁请求）
    if (!force) {
        try {
            var rawCache = localStorage.getItem(UPDATE_CHECK_LOCAL_CACHE_KEY);
            if (rawCache) {
                var cached = JSON.parse(rawCache);
                var age = Date.now() - (cached.timestamp || 0);
                if (cached.data && cached.data.success && age < UPDATE_CHECK_LOCAL_CACHE_TTL) {
                    var cacheTimeStr = new Date(cached.timestamp).toLocaleString();
                    renderUpdateResult(cached.data, true, cacheTimeStr);
                    return;
                }
                // 缓存过期，清理
                localStorage.removeItem(UPDATE_CHECK_LOCAL_CACHE_KEY);
            }
        } catch (e) {
            // localStorage 不可用，走正常请求
        }
    }

    var url = 'update.php?action=check' + (force ? '&force=1' : '');
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                latestText.textContent = '未知';
                setUpdateStatus('检查失败: ' + (data.error || (data.errors && data.errors.join('; ')) || '未知错误'), 'error');
                return;
            }
            // 写入前端缓存（仅保存成功的响应，避免缓存错误）
            try {
                localStorage.setItem(UPDATE_CHECK_LOCAL_CACHE_KEY, JSON.stringify({
                    timestamp: Date.now(),
                    data: data,
                }));
            } catch (e) {}
            renderUpdateResult(data, false, null);
        })
        .catch(function(err) {
            document.getElementById('latestVersionText').textContent = '失败';
            setUpdateStatus('网络请求失败，请检查网络或稍后再试', 'error');
        });
}

function doUpdate() {
    if (!confirm('确定要执行自动更新吗？此操作将下载并覆盖项目文件。更新过程中请不要关闭页面。')) {
        return;
    }
    var btn = document.getElementById('updateBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 更新中...';
    document.getElementById('progressBar').style.display = 'block';
    document.getElementById('updateLogBox').style.display = 'block';
    document.getElementById('updateLog').textContent = '';
    setUpdateStatus('正在执行更新，这可能需要几分钟时间...', 'info');
    appendUpdateLog('[开始] 发起更新请求...');

    var formData = new FormData();
    formData.append('action', 'update');
    formData.append('csrf_token', updateCsrfToken);

    fetch('update.php?action=update', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.logs && Array.isArray(data.logs)) {
            data.logs.forEach(function(line) { appendUpdateLog(line); });
        }
        if (data.success) {
            // 更新成功，清理前端缓存，确保下次进入页面获取最新版本信息
            try { localStorage.removeItem(UPDATE_CHECK_LOCAL_CACHE_KEY); } catch (e) {}
            setUpdateStatus('更新成功！当前版本已升级到 ' + (data.to_version || '最新版本') + '。请刷新页面确认。', 'success');
            appendUpdateLog('[完成] 更新成功！');
            btn.innerHTML = '<i class="fas fa-check"></i> 更新成功';
            btn.className = 'btn btn-lg btn-success';
            // 3秒后自动刷新
            setTimeout(function() { location.reload(); }, 3000);
        } else {
            var msg = data.error || (data.errors && data.errors.join('；')) || '更新失败';
            setUpdateStatus('更新失败: ' + msg, 'error');
            appendUpdateLog('[失败] ' + msg);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download"></i> 重试更新';
            btn.className = 'btn btn-lg btn-success';
        }
        document.getElementById('progressBar').style.display = 'none';
        loadBackupList();
        loadUpdateHistory();
    })
    .catch(function(err) {
        setUpdateStatus('更新请求失败，请检查服务器日志', 'error');
        document.getElementById('progressBar').style.display = 'none';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-download"></i> 重新尝试';
    });
}

function loadBackupList() {
    fetch('update.php?action=backups', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var box = document.getElementById('backupList');
            if (!data.success || !data.backups || data.backups.length === 0) {
                box.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-inbox"></i> 暂无备份文件</div>';
                return;
            }
            var html = '<table class="table table-striped"><thead><tr><th>文件名</th><th>大小 (KB)</th><th>创建时间</th><th>操作</th></tr></thead><tbody>';
            data.backups.forEach(function(b) {
                html += '<tr>';
                html += '<td>' + b.filename + '</td>';
                html += '<td>' + b.size + '</td>';
                html += '<td>' + b.time + '</td>';
                html += '<td><button type="button" class="btn btn-sm btn-warning" onclick="doRollback(\'' + b.filename + '\')">';
                html += '<i class="fas fa-undo"></i> 从此备份恢复</button></td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            box.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('backupList').innerHTML = '<div class="text-danger">加载失败</div>';
        });
}

function loadUpdateHistory() {
    fetch('update.php?action=logs', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var box = document.getElementById('updateHistoryList');
            if (!data.success || !data.logs || data.logs.length === 0) {
                box.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-inbox"></i> 暂无更新记录</div>';
                return;
            }
            var html = '<table class="table table-striped"><thead><tr><th>时间</th><th>从版本</th><th>到版本</th><th>状态</th><th>操作人</th><th>说明</th></tr></thead><tbody>';
            data.logs.forEach(function(log) {
                var statusClass = 'badge-info';
                var statusText = log.status;
                if (log.status === 'success') { statusClass = 'badge-success'; statusText = '成功'; }
                else if (log.status === 'failed') { statusClass = 'badge-danger'; statusText = '失败'; }
                else if (log.status === 'rollback') { statusClass = 'badge-warning'; statusText = '回滚'; }
                html += '<tr>';
                html += '<td>' + (log.timestamp || '-') + '</td>';
                html += '<td>' + (log.from_version || '-') + '</td>';
                html += '<td>' + (log.to_version || '-') + '</td>';
                html += '<td><span class="badge ' + statusClass + '">' + statusText + '</span></td>';
                html += '<td>' + (log.username || '-') + '</td>';
                html += '<td>' + (log.message || '-') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table>';
            box.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('updateHistoryList').innerHTML = '<div class="text-danger">加载失败</div>';
        });
}

function doRollback(filename) {
    if (!confirm('确定要从备份文件恢复吗？这将覆盖当前所有文件。此操作不可撤销。')) return;
    var formData = new FormData();
    formData.append('action', 'rollback');
    formData.append('backup', filename);
    formData.append('csrf_token', updateCsrfToken);
    fetch('update.php?action=rollback', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                alert('回滚成功！即将刷新页面...');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                alert('回滚失败: ' + (data.error || '未知错误'));
            }
        })
        .catch(function(err) {
            alert('请求失败: ' + err);
        });
}

// 自动加载：进入更新页面后立即检查版本
document.addEventListener('DOMContentLoaded', function() {
    if ('<?php echo $currentSection; ?>' === 'update') {
        checkUpdate(false);
        loadBackupList();
        loadUpdateHistory();
    }
});

// ============================================
// jQuery 模态框事件绑定
// ============================================
$(document).ready(function() {
    $('#confirmModalYes').on('click', function() {
        executeDelete();
    });

    $('#confirmModalCancel').on('click', function() {
        $('#confirmModal').modal('hide');
    });

    $('#confirmModalClose').on('click', function() {
        $('#confirmModal').modal('hide');
    });

    $('#confirmModal').on('hidden.bs.modal', function() {
        pendingDeleteUrl = '';
        pendingDeleteType = '';
        pendingDeleteToken = '';
        selectedUrls = [];
        var deleteBtn = document.getElementById('deleteSelectedBtn');
        if (deleteBtn) {
            deleteBtn.style.display = 'none';
        }
        var checkboxes = document.querySelectorAll('.url-checkbox');
        checkboxes.forEach(function(cb) {
            cb.checked = false;
        });
        var selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.checked = false;
        }
    });
});
</script>
</body>
</html>