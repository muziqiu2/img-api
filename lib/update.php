<?php
// 该模块：自动更新环境与辅助。更新缓存清理、递归删除、检查缓存读写、
// 环境可写性/可用性检测、受保护路径与扩展名白名单判断等。

// 清理更新缓存文件
function cleanupUpdateCache() {
    $dirs = [UPDATE_CACHE_DIR];
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) continue;
        $items = glob(rtrim($dir, '/') . '/*');
        if ($items === false) continue;
        foreach ($items as $item) {
            if (is_file($item)) {
                @unlink($item);
            } elseif (is_dir($item)) {
                removeDirectory($item);
            }
        }
    }
}

// 递归删除目录
function removeDirectory($dir) {
    if (!is_dir($dir)) return;
    $items = glob(rtrim($dir, '/') . '/*');
    if ($items === false) return;
    foreach ($items as $item) {
        if (is_file($item)) {
            @unlink($item);
        } elseif (is_dir($item)) {
            removeDirectory($item);
        }
    }
    @rmdir($dir);
}

// 检查是否有更新检查结果缓存（避免频繁请求GitHub API）
function getCachedUpdateCheck() {
    if (!file_exists(UPDATE_CHECK_CACHE_FILE)) return null;
    if (time() - filemtime(UPDATE_CHECK_CACHE_FILE) > UPDATE_CHECK_CACHE_TTL) return null;
    $data = @json_decode(file_get_contents(UPDATE_CHECK_CACHE_FILE), true);
    return is_array($data) && !empty($data) ? $data : null;
}

// 写入更新检查缓存
function setCachedUpdateCheck($data) {
    @file_put_contents(UPDATE_CHECK_CACHE_FILE, json_encode($data, JSON_UNESCAPED_UNICODE));
}

// 清除更新检查缓存
function clearUpdateCheckCache() {
    if (file_exists(UPDATE_CHECK_CACHE_FILE)) {
        @unlink(UPDATE_CHECK_CACHE_FILE);
    }
}

// 检查目录是否可写（通过实际写入测试文件，避免 is_writable 误报）
function isDirReallyWritable($dir) {
    if (!is_dir($dir)) {
        if (!@mkdir($dir, 0755, true)) {
            return false;
        }
    }
    $testFile = rtrim($dir, '/\\') . '/.write_test_' . mt_rand() . '.tmp';
    $result = @file_put_contents($testFile, 'test');
    if ($result !== false) {
        @unlink($testFile);
        return true;
    }
    return false;
}

// 检查服务器环境是否满足更新要求
function checkUpdateEnvironment() {
    $errors = [];
    $warnings = [];

    // 检查PHP扩展
    if (!extension_loaded('zip')) {
        $errors[] = '缺少 zip 扩展（用于解压更新包）';
    }
    if (!extension_loaded('curl') && !ini_get('allow_url_fopen')) {
        $errors[] = '需要 curl 扩展或 allow_url_fopen 开启（用于下载更新包）';
    }

    // 检查目录可写（只检查真正需要写入的目录，并通过实际写入测试）
    $writableDirs = [
        UPDATE_BACKUP_DIR => '备份目录',
        UPDATE_CACHE_DIR => '更新缓存目录',
        CACHE_DIR => '缓存目录',
    ];
    foreach ($writableDirs as $dir => $label) {
        if (!isDirReallyWritable($dir)) {
            $errors[] = '目录不可写: ' . $label . '（' . basename($dir) . '，更新需要写权限）';
        }
    }

    // 检查磁盘空间
    $freeSpace = @disk_free_space(__DIR__);
    if ($freeSpace !== false && $freeSpace < UPDATE_MIN_FREE_SPACE) {
        $errors[] = '磁盘空间不足（需要至少 ' . round(UPDATE_MIN_FREE_SPACE / 1024 / 1024, 1) . 'MB 剩余空间）';
    }

    // 检查执行时限
    if (ini_get('max_execution_time') > 0 && ini_get('max_execution_time') < UPDATE_TIMEOUT) {
        $warnings[] = 'PHP max_execution_time=' . ini_get('max_execution_time') . 's 可能不足，建议设置为 ' . UPDATE_TIMEOUT . 's 或以上';
    }

    // 提示目录防护（.htaccess 仅对 Apache 生效；Nginx 需按 nginx.conf.example 配置）
    $serverSoftware = strtolower($_SERVER['SERVER_SOFTWARE'] ?? '');
    if (strpos($serverSoftware, 'nginx') !== false) {
        $warnings[] = '检测到 Nginx 服务器：请务必按根目录 nginx.conf.example 配置 data/ 与 admin/logs/ 目录拒绝访问，否则敏感文件可被公网下载';
    } else {
        $warnings[] = '请确认 data/ 与 admin/logs/ 目录无法通过 Web 访问（Apache 使用自带 .htaccess；Nginx 请参考 nginx.conf.example）';
    }

    return [
        'ok' => empty($errors),
        'errors' => $errors,
        'warnings' => $warnings,
    ];
}
// 判断路径是否受保护（不会被更新覆盖
function isPathProtected($relativePath) {
    $protected = UPDATE_PROTECTED_PATHS;
    $relativePath = str_replace('\\', '/', $relativePath);
    // 仅去除开头的 ./,避免吞掉 .git/.htaccess 等点前缀路径
    $normalized = preg_replace('#^\./+#', '', $relativePath);
    $normalized = ltrim($normalized, '/');
    foreach ($protected as $pattern) {
        if (empty($pattern)) continue;
        if ($normalized === rtrim($pattern, '/') ||
            str_starts_with_custom($normalized, $pattern)) {
            return true;
        }
    }
    return false;
}

// 兼容低版本PHP的路径前缀检查
function str_starts_with_custom($haystack, $needle) {
    if (function_exists('str_starts_with')) {
        return str_starts_with($haystack, $needle);
    }
    return $needle !== '' && strncmp($haystack, $needle, strlen($needle)) === 0;
}

// 检查文件扩展名是否在白名单内
function isExtensionAllowed($filename) {
    $allowed = UPDATE_ALLOWED_EXTENSIONS;
    if (empty($allowed)) return true;
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    return in_array($ext, $allowed);
}
