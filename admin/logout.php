<?php
require_once '../config.php';

if (session_status() === PHP_SESSION_ACTIVE) {
    $_SESSION = [];
    session_destroy();
}

header('Location: index.php');
exit;
?>
