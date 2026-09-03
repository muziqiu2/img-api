<?php
// 临时测试入口：仅用于本地浏览器自动化测试，测试后删除
require_once __DIR__ . '/config.php';
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0, 'path' => '/', 'domain' => '',
        'secure' => false, 'httponly' => true, 'samesite' => 'Lax'
    ]);
    session_start();
}
$_SESSION['admin_logged_in'] = true;
$_SESSION['admin_login_time'] = time();
$_SESSION['admin_username'] = 'admin';
header('Location: admin/dashboard.php');
exit;
