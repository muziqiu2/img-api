<?php
/**
 * 数据库迁移脚本 — 版本化的数据库变更入口
 *
 * 发布新版本时，如果需要变更数据库（新增表、字段等，请在此文件中按版本号编写迁移函数
 *
 * 返回格式: ['success' => bool, 'messages' => string[]]
 */

if (!defined('DB_FILE')) {
    require_once dirname(__DIR__) . '/config.php';
}

$db = getDb();
$messages = [];
$success = true;

try {
    // ========================================
    // v3.0.0 -> v3.1.0 迁移：以下为示例
    // ========================================
    // 1) 确保 update_logs 表存在（如果从旧版本升级上来）
    $db->exec("
        CREATE TABLE IF NOT EXISTS update_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            from_version TEXT NOT NULL,
            to_version TEXT NOT NULL,
            status TEXT NOT NULL,
            message TEXT,
            backup_path TEXT,
            username TEXT,
            ip TEXT,
            timestamp TEXT NOT NULL
        )
    ");
    $messages[] = '已确保 update_logs 表存在';

    // ========================================
    // v3.1.0 -> v3.2.0 迁移：示例 — 如果有需要的代码变更
    // 在此处添加新的迁移（通过检查版本号或表是否存在来决定是否执行）
    // ========================================
    //
    // 例如:
    // $stmt = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='your_new_table'");
    // if (!$stmt->fetch()) {
    //     $db->exec("CREATE TABLE your_new_table (...)");
    //     $messages[] = '创建表 your_new_table';
    // }

} catch (Exception $e) {
    $success = false;
    $messages[] = '迁移失败: ' . $e->getMessage();
}

return ['success' => $success, 'messages' => $messages];
