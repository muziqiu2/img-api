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
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <?php if ($mustChangePassword): ?>
                <div class="alert alert-danger alert-dismissible">
                    <button type="button" class="close" data-dismiss="alert">&times;</button>
                    <i class="icon fas fa-exclamation-triangle"></i>
                    <strong>安全警告：</strong>当前仍在使用默认密码（123456），系统已禁止使用其他功能，请立即修改密码！
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
                        <button type="button" class="btn btn-danger btn-sm float-right" id="deleteSelectedBtn" onclick="deleteSelected(<?php echo json_encode($currentType, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, <?php echo json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)" style="display:none;">
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
                                        <button type="button" class="btn btn-danger btn-sm" onclick="showDeleteConfirm(<?php echo json_encode($url, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, <?php echo json_encode($currentType, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>, <?php echo json_encode($csrfToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>)">
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
                            <?php
                            // 窗口式分页：仅渲染首页、当前页附近与末页（含省略号），
                            // 避免图片量大时渲染数千个页码节点导致页面卡顿
                            $pageItems = [];
                            if ($totalPages <= 7) {
                                $pageItems = range(1, $totalPages);
                            } else {
                                $near = range(max(1, $currentPage - 2), min($totalPages, $currentPage + 2));
                                $windowPages = array_values(array_unique(array_merge([1], $near, [$totalPages])));
                                $prev = 0;
                                foreach ($windowPages as $p) {
                                    if ($prev && $p - $prev > 1) {
                                        $pageItems[] = '...';
                                    }
                                    $pageItems[] = $p;
                                    $prev = $p;
                                }
                            }
                            ?>
                            <?php foreach ($pageItems as $item): ?>
                                <?php if ($item === '...'): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                <?php else: ?>
                                <li class="page-item <?php echo $item == $currentPage ? 'active' : ''; ?>">
                                    <a class="page-link" href="?section=management&type=<?php echo $currentType; ?>&page=<?php echo $item; ?>"><?php echo $item; ?></a>
                                </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
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
                    <?php if ($mustChangePassword): ?>
                    <div class="alert alert-warning mb-0 rounded-0">
                        <i class="icon fas fa-exclamation-triangle"></i>
                        系统检测到您仍在使用默认密码，请立即修改密码后再使用其他功能。
                    </div>
                    <?php endif; ?>
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

                <?php elseif ($currentSection === 'site'): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">网站设置</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">配置前台首页展示的文字内容。默认值即界面当前展示的内容，留空字段将保持默认。</p>
                        <form id="siteSettingsForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="form-group">
                                <label for="site_title">网页标题</label>
                                <input type="text" class="form-control" id="site_title" name="site_title" placeholder="浏览器标签页显示的名称" maxlength="100">
                                <small class="form-text text-muted">显示在浏览器标签页的 title 名称</small>
                            </div>
                            <div class="form-group">
                                <label for="site_name">网站名称</label>
                                <input type="text" class="form-control" id="site_name" name="site_name" placeholder="首页顶部大标题" maxlength="100">
                                <small class="form-text text-muted">首页顶部大标题展示的网站名称</small>
                            </div>
                            <div class="form-group">
                                <label for="site_lead">副标题</label>
                                <input type="text" class="form-control" id="site_lead" name="site_lead" placeholder="首页顶部的描述文字" maxlength="200">
                                <small class="form-text text-muted">首页顶部大标题下方的描述文字</small>
                            </div>
                            <div class="form-group">
                                <label for="site_copyright">版权文字</label>
                                <input type="text" class="form-control" id="site_copyright" name="site_copyright" placeholder="底部版权文字（链接到本项目仓库）" maxlength="200">
                                <small class="form-text text-muted">底部版权文字，默认链接到本项目 GitHub 仓库</small>
                            </div>
                            <div class="form-group">
                                <label for="site_icp">ICP 备案号</label>
                                <input type="text" class="form-control" id="site_icp" name="site_icp" placeholder="如：粤ICP备xxxxxxxx号（可留空不展示）" maxlength="100">
                                <small class="form-text text-muted">底部展示的备案号，链接到工信部网站。留空则不展示备案信息</small>
                            </div>
                            <hr>
                            <h6 class="text-muted mb-3">图片访问模式</h6>
                            <div class="form-group">
                                <label for="image_mode">图片访问模式</label>
                                <select class="form-control" id="image_mode" name="image_mode">
                                    <option value="redirect">302 跳转模式（默认）</option>
                                    <option value="proxy">代理模式（隐藏真实图片链接）</option>
                                </select>
                                <small class="form-text text-muted">代理模式：所有 API（api.php/pc.php/pe.php）由服务器代为下载并转发图片，用户无法看到真实图片 URL，可隐藏图片链接；302 跳转模式：API 直接重定向到真实图片 URL。此设置对全部 API 生效，调用方传参不再影响返回方式。</small>
                            </div>
                            <div class="form-group">
                                <label for="enable_json">JSON 格式输出</label>
                                <select class="form-control" id="enable_json" name="enable_json">
                                    <option value="0">关闭（默认）</option>
                                    <option value="1">开启</option>
                                </select>
                                <small class="form-text text-warning">开启后，可在 api.php/pc.php/pe.php 后加 <code>?format=json</code> 返回图片地址的 JSON 数据。注意：当「图片访问模式」为代理模式时，JSON 会返回真实的图片 URL，从而暴露代理模式本应隐藏的图片链接，请仅在确认无泄露风险时开启。</small>
                            </div>
                            <hr>
                            <h6 class="text-muted mb-3">频率限制设置</h6>
                            <div class="form-group">
                                <label for="rate_limit_api">API 每分钟最大请求数</label>
                                <input type="number" class="form-control" id="rate_limit_api" name="rate_limit_api" min="1" max="10000" placeholder="默认 100">
                                <small class="form-text text-muted">每个 IP 每分钟最多可请求 API（api.php/pc.php/pe.php）的次数，超过返回 429。留空使用默认 100</small>
                            </div>
                            <div class="form-group">
                                <label for="rate_limit_admin">后台操作每分钟最大请求数</label>
                                <input type="number" class="form-control" id="rate_limit_admin" name="rate_limit_admin" min="1" max="10000" placeholder="默认 10">
                                <small class="form-text text-muted">后台敏感操作（增删图片、更新、回滚等）每分钟最大次数，防自动化脚本。留空使用默认 10</small>
                            </div>
                            <hr>
                            <h6 class="text-muted mb-3">统计设置</h6>
                            <div class="form-group">
                                <label for="stats_auto_flush_interval">统计自动落库间隔（秒）</label>
                                <input type="number" class="form-control" id="stats_auto_flush_interval" name="stats_auto_flush_interval" min="0" max="86400" step="1" placeholder="默认 60">
                                <small class="form-text text-muted">API 调用统计先写入缓冲、按此间隔自动合并进数据库。填 0 表示关闭自动落库（仅打开后台时才落库）；建议 10~3600。留空恢复默认 60 秒。</small>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 保存设置
                            </button>
                        </form>
                    </div>
                </div>

                <?php elseif ($currentSection === 'environment'): ?>
                <?php echo renderEnvironmentChecksHtml(); ?>
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

                <!-- GitHub Token 设置 -->
                <div class="card mt-3" id="tokenSettingsCard">
                    <div class="card-header">
                        <h3 class="card-title">GitHub Token 设置</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">
                            填写 GitHub Personal Access Token 可大幅提升 API 请求限制（从 60次/小时 提升至 5000次/小时）。<br>
                            不填写也可以正常使用，但可能遇到频率限制。如需 Token，请前往 GitHub Settings → Developer settings → Personal access tokens 生成。
                        </p>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <input type="password" class="form-control" id="githubTokenInput" placeholder="ghp_xxxxxxxxxxxxxxxxxx">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="toggleTokenBtn" onclick="toggleTokenVisibility()">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-primary" id="saveTokenBtn" onclick="saveGithubToken()">
                                            <i class="fas fa-save"></i> 保存
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" id="clearTokenBtn" onclick="clearGithubToken()" style="display:none;">
                                            <i class="fas fa-trash"></i> 清空
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted" id="tokenStatus"></small>
                            </div>
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

                <!-- 环境检测明细（复用环境检测页的渲染） -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">环境检测明细</h3>
                        <div class="card-tools">
                            <a href="?section=environment" class="btn btn-tool" title="前往完整环境检测页"><i class="fas fa-external-link-alt"></i></a>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php echo renderEnvironmentChecksHtml(); ?>
                    </div>
                </div>
                <?php endif; ?>

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
// ============================================
// 非阻塞 Toast 通知
// ============================================
function escapeHtml(s) {
    var div = document.createElement('div');
    div.textContent = (s == null) ? '' : String(s);
    return div.innerHTML;
}

function showToast(message, type) {
    type = type || 'success';
    var container = document.getElementById('appToasts');
    if (!container) return;
    var icons = { success: 'fa-check-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle', error: 'fa-exclamation-circle' };
    var t = document.createElement('div');
    t.className = 'app-toast app-toast-' + type;
    t.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '"></i><span>' + escapeHtml(message) + '</span>' +
                  '<button type="button" class="app-toast-close" aria-label="关闭">&times;</button>';
    container.appendChild(t);
    var hide = function() {
        if (!t.parentNode) return;
        t.classList.add('app-toast-hide');
        setTimeout(function() { if (t.parentNode) t.parentNode.removeChild(t); }, 300);
    };
    t.querySelector('.app-toast-close').addEventListener('click', hide);
    setTimeout(hide, 4000);
}

// ============================================
// 通用确认弹窗（Promise 化）
//   confirmDialog({title, message, confirmText, danger}).then(function(ok){ ... })
// ============================================
var confirmResolve = null;

function confirmDialog(options) {
    options = options || {};
    document.getElementById('confirmTitle').textContent = options.title || '确认操作';
    document.getElementById('confirmMessage').textContent = options.message || '确定要执行此操作吗？';
    var yesBtn = document.getElementById('confirmModalYes');
    yesBtn.textContent = options.confirmText || '确定';
    yesBtn.className = 'btn ' + (options.danger ? 'btn-danger' : 'btn-primary');
    return new Promise(function(resolve) {
        confirmResolve = resolve;
        $('#confirmModal').modal('show');
    });
}

function resolveConfirm(result) {
    var fn = confirmResolve;
    confirmResolve = null;
    $('#confirmModal').modal('hide');
    if (fn) fn(result);
}

// ============================================
// 图片删除：选中状态与通用确认
// ============================================
var selectedUrls = [];

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

function resetDeleteSelection() {
    selectedUrls = [];
    var deleteBtn = document.getElementById('deleteSelectedBtn');
    if (deleteBtn) deleteBtn.style.display = 'none';
    var checkboxes = document.querySelectorAll('.url-checkbox');
    checkboxes.forEach(function(cb) { cb.checked = false; });
    var selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
}

function submitDeleteForm(url, type, token) {
    var form = document.createElement('form');
    form.method = 'post';
    form.action = '?section=management&type=' + encodeURIComponent(type);

    var csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = token;
    form.appendChild(csrfInput);

    if (url === 'MULTI_DELETE') {
        selectedUrls.forEach(function(u) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_urls[]';
            input.value = u;
            form.appendChild(input);
        });
    } else {
        // 单条删除同样使用 POST 表单提交（避免 GET 副作用与 token 泄露）
        var urlInput = document.createElement('input');
        urlInput.type = 'hidden';
        urlInput.name = 'delete_url';
        urlInput.value = url;
        form.appendChild(urlInput);
    }

    document.body.appendChild(form);
    form.submit();
}

function showDeleteConfirm(url, type, token) {
    confirmDialog({
        title: '确认删除',
        message: '确定要删除这个图片链接吗？此操作不可撤销。',
        confirmText: '确定删除',
        danger: true
    }).then(function(ok) {
        if (ok) submitDeleteForm(url, type, token);
        else resetDeleteSelection();
    });
}

function deleteSelected(type, token) {
    if (selectedUrls.length === 0) return;
    confirmDialog({
        title: '批量删除',
        message: '确定要删除选中的 ' + selectedUrls.length + ' 个图片链接吗？此操作不可撤销。',
        confirmText: '确定删除',
        danger: true
    }).then(function(ok) {
        if (ok) submitDeleteForm('MULTI_DELETE', type, token);
        else resetDeleteSelection();
    });
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
    confirmDialog({
        title: '确认更新',
        message: '确定要执行自动更新吗？此操作将下载并覆盖项目文件。更新过程中请不要关闭页面。',
        confirmText: '立即更新',
        danger: false
    }).then(function(ok) { if (ok) startUpdate(); });
}

function startUpdate() {
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
            var html = '<div class="table-responsive"><table class="table table-striped table-wrap-text"><thead><tr><th>文件名</th><th style="width:80px;">大小 (KB)</th><th style="width:110px;">创建时间</th><th style="width:130px;">操作</th></tr></thead><tbody>';
            data.backups.forEach(function(b) {
                html += '<tr>';
                html += '<td>' + b.filename + '</td>';
                html += '<td>' + b.size + ' KB</td>';
                html += '<td>' + b.time + '</td>';
                html += '<td class="nowrap">';
                html += '<button type="button" class="btn btn-sm btn-warning mr-1" onclick="doRollback(\'' + b.filename + '\')">';
                html += '<i class="fas fa-undo"></i> 恢复</button>';
                html += '<button type="button" class="btn btn-sm btn-danger" onclick="deleteBackup(\'' + b.filename + '\')">';
                html += '<i class="fas fa-trash"></i> 删除</button>';
                html += '</td></tr>';
            });
            html += '</tbody></table></div>';
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
            var html = '<div class="table-responsive"><table class="table table-striped table-wrap-text"><thead><tr><th>时间</th><th>从版本</th><th>到版本</th><th>状态</th><th>操作人</th><th>说明</th></tr></thead><tbody>';
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
            html += '</tbody></table></div>';
            box.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('updateHistoryList').innerHTML = '<div class="text-danger">加载失败</div>';
        });
}

function doRollback(filename) {
    confirmDialog({
        title: '恢复备份',
        message: '确定要从备份文件恢复吗？这将覆盖当前所有文件。此操作不可撤销。',
        confirmText: '确认恢复',
        danger: true
    }).then(function(ok) { if (!ok) return; startRollback(filename); });
}

function startRollback(filename) {
    var formData = new FormData();
    formData.append('action', 'rollback');
    formData.append('backup', filename);
    formData.append('csrf_token', updateCsrfToken);
    fetch('update.php?action=rollback', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('回滚成功！即将刷新页面...', 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast('回滚失败: ' + (data.error || '未知错误'), 'error');
            }
        })
        .catch(function(err) {
            showToast('请求失败: ' + err, 'error');
        });
}

// 删除备份文件
function deleteBackup(filename) {
    confirmDialog({
        title: '删除备份',
        message: '确定要删除备份文件 "' + filename + '" 吗？此操作不可撤销。',
        confirmText: '确认删除',
        danger: true
    }).then(function(ok) {
        if (!ok) return;
        var formData = new FormData();
        formData.append('action', 'delete_backup');
        formData.append('backup', filename);
        formData.append('csrf_token', updateCsrfToken);

        fetch('update.php', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('备份文件已删除', 'success');
                loadBackupList();
            } else {
                showToast('删除失败: ' + (data.error || '未知错误'), 'error');
            }
        })
        .catch(function(err) {
            showToast('请求失败: ' + err, 'error');
        });
    });
}

// 加载 GitHub Token 状态
function loadGithubToken() {
    fetch('update.php?action=settings', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tokenInput = document.getElementById('githubTokenInput');
            var tokenStatus = document.getElementById('tokenStatus');
            var clearBtn = document.getElementById('clearTokenBtn');
            if (data.success) {
                if (data.has_token) {
                    tokenStatus.textContent = '当前已设置 Token: ' + data.github_token;
                    tokenStatus.className = 'form-text text-success';
                    clearBtn.style.display = 'inline-block';
                } else {
                    tokenStatus.textContent = '未设置 Token';
                    tokenStatus.className = 'form-text text-muted';
                    clearBtn.style.display = 'none';
                }
            }
        })
        .catch(function() {});
}

// 保存 GitHub Token
function saveGithubToken() {
    var token = document.getElementById('githubTokenInput').value.trim();
    var formData = new FormData();
    formData.append('action', 'save_token');
    formData.append('token', token);
    formData.append('csrf_token', updateCsrfToken);

    document.getElementById('saveTokenBtn').disabled = true;
    document.getElementById('saveTokenBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';

    fetch('update.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById('githubTokenInput').value = '';
            loadGithubToken();
            // 清除前端缓存，下次检查会获取最新版本信息
            try { localStorage.removeItem(UPDATE_CHECK_LOCAL_CACHE_KEY); } catch (e) {}
        } else {
            showToast('保存失败: ' + (data.error || '未知错误'), 'error');
        }
    })
    .catch(function(err) {
        showToast('请求失败: ' + err, 'error');
    })
    .finally(function() {
        document.getElementById('saveTokenBtn').disabled = false;
        document.getElementById('saveTokenBtn').innerHTML = '<i class="fas fa-save"></i> 保存';
    });
}

// 清空 GitHub Token
function clearGithubToken() {
    confirmDialog({
        title: '清空 Token',
        message: '确定要清空 GitHub Token 吗？清空后将使用匿名方式访问 GitHub API。',
        confirmText: '确认清空',
        danger: true
    }).then(function(ok) {
        if (!ok) return;
        document.getElementById('githubTokenInput').value = '';
        saveGithubToken();
    });
}

// 切换 Token 显示/隐藏
function toggleTokenVisibility() {
    var input = document.getElementById('githubTokenInput');
    var btn = document.getElementById('toggleTokenBtn');
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

// 自动加载：进入更新页面后立即检查版本
document.addEventListener('DOMContentLoaded', function() {
    if ('<?php echo $currentSection; ?>' === 'update') {
        checkUpdate(false);
        loadBackupList();
        loadUpdateHistory();
        loadGithubToken();
    } else if ('<?php echo $currentSection; ?>' === 'site') {
        loadSiteSettings();
    }
});

// ============================================
// 网站设置：加载与保存
// ============================================
function loadSiteSettings() {
    fetch('update.php?action=get_site_settings', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('site_title').value = data.site_title || '';
            document.getElementById('site_name').value = data.site_name || '';
            document.getElementById('site_lead').value = data.site_lead || '';
            document.getElementById('site_copyright').value = data.site_copyright || '';
            document.getElementById('site_icp').value = data.site_icp || '';
            document.getElementById('rate_limit_api').value = data.rate_limit_api || '';
            document.getElementById('rate_limit_admin').value = data.rate_limit_admin || '';
            document.getElementById('image_mode').value = data.image_mode || 'redirect';
            document.getElementById('enable_json').value = data.enable_json || '0';
            // 0 是合法值（表示禁用自动落库），不能用 || 兜底，否则会误显示为空
            var flushInterval = data.stats_auto_flush_interval;
            document.getElementById('stats_auto_flush_interval').value = (flushInterval === 0 || flushInterval === '0') ? '0' : (flushInterval || '');
        }
    })
    .catch(function() {});
}

var siteSettingsForm = document.getElementById('siteSettingsForm');
if (siteSettingsForm) {
    siteSettingsForm.addEventListener('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    formData.append('action', 'save_site_settings');
    formData.append('csrf_token', updateCsrfToken);

    var submitBtn = this.querySelector('button[type="submit"]');
    var originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';

    fetch('update.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast(data.message, 'success');
        } else {
            showToast('保存失败: ' + (data.error || '未知错误'), 'error');
        }
    })
    .catch(function(err) {
        showToast('请求失败: ' + err, 'error');
    })
    .finally(function() {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
    });
});
}

// ============================================
// jQuery 模态框事件绑定
// ============================================
$(document).ready(function() {
    $('#confirmModalYes').on('click', function() {
        resolveConfirm(true);
    });

    $('#confirmModalCancel').on('click', function() {
        resolveConfirm(false);
    });

    $('#confirmModalClose').on('click', function() {
        resolveConfirm(false);
    });

    $('#confirmModal').on('hidden.bs.modal', function() {
        // 通过 ESC / 点击遮罩关闭而未显式确认时，统一按“取消”处理
        if (confirmResolve) {
            var fn = confirmResolve;
            confirmResolve = null;
            fn(false);
        }
    });
});
</script>
</body>
</html>