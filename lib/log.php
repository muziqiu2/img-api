<?php
// 该模块：管理操作日志。记录管理员操作并查询操作日志列表。

function logAdminAction($action) {
    $db = getDb();
    $ip = getClientIp();
    $time = date('Y-m-d H:i:s');
    $username = getCurrentUsername();

    $stmt = $db->prepare("INSERT INTO admin_logs (time, username, ip, action) VALUES (?, ?, ?, ?)");
    $result = $stmt->execute([$time, $username, $ip, $action]);

    // 保留策略：操作日志最多保留 1000 条，防止无限增长
    $stmt = $db->prepare("DELETE FROM admin_logs WHERE id NOT IN (SELECT id FROM admin_logs ORDER BY id DESC LIMIT 1000)");
    $stmt->execute();

    return $result;
}

function getAdminLogs($limit = 100) {
    $db = getDb();
    $stmt = $db->prepare("SELECT * FROM admin_logs ORDER BY id DESC LIMIT ?");
    $stmt->execute([$limit]);
    return $stmt->fetchAll();
}
