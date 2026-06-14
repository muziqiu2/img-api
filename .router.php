<?php
/**
 * PHP 内置服务器路由文件
 * 将静态文件请求转发到 public 目录
 */

// 静态文件扩展名
$staticExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'ico', 'svg', 'woff', 'woff2', 'ttf', 'eot', 'map'];

// 获取请求 URI
$requestUri = $_SERVER['REQUEST_URI'];
$path = parse_url($requestUri, PHP_URL_PATH);

// 静态文件请求
$ext = pathinfo($path, PATHINFO_EXTENSION);
if (in_array($ext, $staticExtensions) && file_exists(__DIR__ . '/public' . $path)) {
    // 提供静态文件
    $file = __DIR__ . '/public' . $path;
    $mimeTypes = [
        'css' => 'text/css',
        'js' => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'gif' => 'image/gif',
        'ico' => 'image/x-icon',
        'svg' => 'image/svg+xml',
        'woff' => 'font/woff',
        'woff2' => 'font/woff2',
        'ttf' => 'font/ttf',
        'eot' => 'application/vnd.ms-fontobject',
        'map' => 'application/json',
    ];
    $mime = $mimeTypes[$ext] ?? 'application/octet-stream';
    header('Content-Type: ' . $mime);
    readfile($file);
    return true;
}

// 其他请求交给 PHP 处理
return false;
