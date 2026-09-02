<?php
// 该模块：版本与更新日志。提供当前版本读取/写入、更新日志读写与语义化版本比较。

// 获取当前应用版本号（优先从数据库/版本文件）
function getAppVersion() {
    if (file_exists(APP_VERSION_FILE)) {
        $v = trim(file_get_contents(APP_VERSION_FILE));
        if (!empty($v)) return $v;
    }
    return APP_VERSION;
}

// 写入当前版本号文件（数据备份，用于回滚识别
function setAppVersion($version) {
    return file_put_contents(APP_VERSION_FILE, $version) !== false;
}

// 获取更新日志
function getUpdateLogs($limit = 20) {
    try {
        $db = getDb();
        $stmt = $db->prepare("SELECT * FROM update_logs ORDER BY id DESC LIMIT ?");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (Exception $e) {
        return [];
    }
}

// 写入更新日志
function logUpdateAction($fromVersion, $toVersion, $status, $message = '', $backupPath = '') {
    try {
        $db = getDb();
        $time = date('Y-m-d H:i:s');
        $username = function_exists('getCurrentUsername') ? getCurrentUsername() : 'system';
        $ip = function_exists('getClientIp') ? getClientIp() : 'unknown';
        $stmt = $db->prepare("INSERT INTO update_logs (from_version, to_version, status, message, backup_path, username, ip, timestamp) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        return $stmt->execute([$fromVersion, $toVersion, $status, $message, $backupPath, $username, $ip, $time]);
    } catch (Exception $e) {
        return false;
    }
}

// 比较两个语义化版本号（返回 1: a>b, -1: a<b, 0: a==b）
function compareVersions($a, $b) {
    $a = preg_replace('/^v/', '', trim($a));
    $b = preg_replace('/^v/', '', trim($b));
    return version_compare($a, $b);
}
