<?php
/**
 * PHP 内置服务器路由文件
 * 将静态文件请求转发到 public 目录
 */

// 静态文件扩展名
$staticExtensions = [
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
    'otf' => 'font/otf',
    'eot' => 'application/vnd.ms-fontobject',
    'map' => 'application/json',
];

// 获取请求 URI
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$path = parse_url($requestUri, PHP_URL_PATH);
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

if (!empty($ext) && isset($staticExtensions[$ext])) {
    // 尝试两种路径：带 public 前缀的和不带的
    $candidates = [
        __DIR__ . $path,                 // /public/js/jquery.min.js -> workdir/public/js/jquery.min.js
        __DIR__ . '/public' . $path,     // /js/jquery.min.js -> workdir/public/js/jquery.min.js
    ];

    // 如果 URL 以 /public/ 开头，也尝试去掉前缀
    if (strpos($path, '/public/') === 0) {
        $candidates[] = __DIR__ . $path;
        $candidates[] = __DIR__ . substr($path, 7);
    }

    foreach ($candidates as $file) {
        if (is_file($file)) {
            header('Content-Type: ' . $staticExtensions[$ext]);
            $fileSize = filesize($file);
            if ($fileSize !== false) {
                header('Content-Length: ' . $fileSize);
            }
            readfile($file);
            return true;
        }
    }

    http_response_code(404);
    echo 'Not Found';
    return true;
}

// 其他请求交给 PHP 脚本处理
return false;
