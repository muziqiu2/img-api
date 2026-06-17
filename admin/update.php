<?php
/**
 * 管理后台 —— 一键更新 AJAX 接口
 *
 * 支持的 action 参数:
 *   check    —— 检查 GitHub 最新版本
 *   update   —— 执行完整更新（耗时较长）
 *   rollback —— 从指定备份回滚
 *   backups  —— 获取备份列表
 *   logs     —— 获取更新历史日志
 *   env      —— 检查当前环境是否满足更新要求
 */

require_once dirname(__DIR__) . '/config.php';
require_once dirname(__DIR__) . '/update/updater.php';

header('Content-Type: application/json; charset=utf-8');

// 仅允许已登录的管理员访问
if (!IS_LOGGED_IN) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => '未登录或登录已过期'], JSON_UNESCAPED_UNICODE);
    exit;
}

// CSRF 验证（所有非 GET 请求必须验证）
$csrfToken = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !validateCsrfToken($csrfToken)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'CSRF token 验证失败'], JSON_UNESCAPED_UNICODE);
    exit;
}

// 频率限制
if (!checkAdminRateLimit()) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => '请求过于频繁，请稍后再试'], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = isset($_GET['action']) ? $_GET['action'] : ($_POST['action'] ?? '');

try {
    switch ($action) {

        // ============================================
        // 1) 检查更新
        // ============================================
        case 'check':
            $force = isset($_GET['force']) && $_GET['force'] === '1';
            $updater = new AppUpdater();
            $result = $updater->checkForUpdate($force);
            if (!$result['success']) {
                http_response_code(500);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
                exit;
            }
            $result['env'] = checkUpdateEnvironment();
            echo json_encode($result, JSON_UNESCAPED_UNICODE);
            break;

        // ============================================
        // 2) 执行更新
        // ============================================
        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => '仅允许 POST 请求'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            // 延长 PHP 超时
            @set_time_limit(300);
            @ini_set('max_execution_time', 300);

            $updater = new AppUpdater();
            $checkResult = $updater->checkForUpdate(true);
            if (!$checkResult['success']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => '无法获取更新信息'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            if (!$checkResult['has_update']) {
                echo json_encode(['success' => false, 'error' => '当前已是最新版本'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $result = $updater->doUpdate();

            // 记录操作到管理员日志
            logAdminAction(
                ($result['success'] ? '成功' : '尝试')
                . '从版本 '
                . ($result['from_version'] ?? '未知')
                . ' 更新到 '
                . ($result['to_version'] ?? '未知')
            );

            if ($result['success']) {
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(500);
                echo json_encode($result, JSON_UNESCAPED_UNICODE);
            }
            break;

        // ============================================
        // 3) 从备份回滚
        // ============================================
        case 'rollback':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => '仅允许 POST 请求'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            $backupFile = isset($_POST['backup']) ? basename($_POST['backup']) : '';
            if (empty($backupFile) || substr($backupFile, -4) !== '.zip') {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => '无效的备份文件'], JSON_UNESCAPED_UNICODE);
                exit;
            }
            $fullPath = UPDATE_BACKUP_DIR . '/' . $backupFile;
            if (!file_exists($fullPath)) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => '备份文件不存在'], JSON_UNESCAPED_UNICODE);
                exit;
            }

            @set_time_limit(300);
            $result = AppUpdater::rollbackFromBackup($fullPath);

            logAdminAction('从备份 ' . $backupFile . ' 进行回滚' . ($result ? '成功' : '失败'));

            if ($result) {
                logUpdateAction('当前', APP_VERSION, 'rollback', '管理员手动回滚，使用文件: ' . $backupFile, $fullPath);
                echo json_encode(['success' => true, 'message' => '回滚成功'], JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(500);
                echo json_encode(['success' => false, 'error' => '回滚失败'], JSON_UNESCAPED_UNICODE);
            }
            break;

        // ============================================
        // 4) 列出备份文件
        // ============================================
        case 'backups':
            $files = [];
            if (is_dir(UPDATE_BACKUP_DIR)) {
                $items = glob(UPDATE_BACKUP_DIR . '/backup_*.zip');
                if ($items !== false) {
                    rsort($items); // 最新的在前
                    foreach ($items as $f) {
                        $files[] = [
                            'filename' => basename($f),
                            'size' => round(filesize($f) / 1024, 2),
                            'time' => date('Y-m-d H:i:s', filemtime($f)),
                        ];
                    }
                }
            }
            echo json_encode(['success' => true, 'backups' => $files], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================
        // 5) 获取更新历史日志
        // ============================================
        case 'logs':
            $logs = getUpdateLogs(50);
            echo json_encode(['success' => true, 'logs' => $logs], JSON_UNESCAPED_UNICODE);
            break;

        // ============================================
        // 6) 环境检查
        // ============================================
        case 'env':
            echo json_encode(checkUpdateEnvironment(), JSON_UNESCAPED_UNICODE);
            break;

        // ============================================
        // 未知 action
        // ============================================
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => '无效的 action 参数'], JSON_UNESCAPED_UNICODE);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => '服务器错误: ' . $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
