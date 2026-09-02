<?php
// 该模块：公共图片 API 处理。组装限流校验、访问模式、统计上报与
// 代理/302 跳转/JSON 输出等完整请求处理逻辑。

// 公共API处理函数
function handleImageApiRequest($type, $countType = null) {
    if (!checkApiRateLimit()) {
        header('HTTP/1.1 429 Too Many Requests');
        header('Content-Type: application/json; charset=utf-8');
        header('Retry-After: 60');
        echo json_encode([
            'success' => false,
            'error' => '请求过于频繁，请稍后再试',
            'retry_after' => 60
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 图片访问模式由后台配置决定（proxy=代理 / redirect=302跳转），
    // API 的 return 参数不再生效
    $mode = getImageAccessMode();
    // 统计沿用原语义：代理计入 img 列，跳转计入 redirect 列
    $returnType = ($mode === 'proxy') ? 'img' : 'redirect';

    // JSON 输出（?format=json）：返回图片地址 JSON，由后台「enable_json」开关控制
    $jsonRequested = isset($_GET['format']) ? strtolower(trim($_GET['format'])) === 'json' : false;
    if ($jsonRequested && !isJsonEnabled()) {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'error' => 'JSON 格式输出未开启，请先在后台「网站设置」中开启该功能',
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // cache 参数钳制上限（最大 30 天），防止超大值导致时间戳溢出
    $cacheTime = isset($_GET['cache']) ? max(0, min(2592000, intval($_GET['cache']))) : 0;
    $imageUrl = getRandomImageUrl($type);

    if ($countType === null) {
        $countType = $type;
    }
    // api 入口按实际设备类型同时计入 pc/pe 分布统计
    $deviceHint = ($countType === 'api') ? $type : null;
    updateCallCount($countType, $returnType, $deviceHint);

    if (!$imageUrl) {
        $errorMsg = ($type === 'pc') ? '没有找到可用的PC端图片' :
                    (($type === 'pe') ? '没有找到可用的移动端图片' : '没有找到可用的图片');
        http_response_code(404);
        if ($jsonRequested) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'type' => $type,
                'error' => $errorMsg,
            ], JSON_UNESCAPED_UNICODE);
        } else {
            echo $errorMsg;
        }
        exit;
    }

    header("Cache-Control: public, max-age=$cacheTime");
    header("Expires: " . gmdate('D, d M Y H:i:s', time() + $cacheTime) . ' GMT');

    // JSON 输出：返回图片地址（受后台开关控制，已在入口校验）。
    // 注意：无论访问模式是代理还是跳转，这里都返回真实图片 URL，
    // 因此在代理模式下启用 JSON 会暴露代理模式本应隐藏的图片链接。
    if ($jsonRequested) {
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => true,
            'type'   => $type,
            'mode'   => $mode,
            'cache'  => $cacheTime,
            'url'    => $imageUrl,
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }

    // 不再为 URL 追加 rand 随机参数：无论代理还是 302，追加都会破坏上游图片 CDN 的命中，
    // 导致缓存永不命中、强制回源给源站造成压力。是否缓存由调用方用 cache 参数显式控制。
    if ($mode === 'proxy') {
        // 代理模式：服务器下载图片并转发给用户，隐藏真实图片链接（仍有 SSRF 防护）
        // 服务器不支持 cURL、或下载失败时，降级为 302 跳转，保证接口始终能出图、不白屏
        if (!function_exists('curl_init') || !function_exists('curl_exec')) {
            header("Location: $imageUrl");
            exit;
        }
        $imageData = fetchRemoteImage($imageUrl);
        if ($imageData) {
            $imageInfo = @getimagesizefromstring($imageData);
            if ($imageInfo && !empty($imageInfo['mime'])) {
                header("Content-Type: {$imageInfo['mime']}");
            } else {
                header('Content-Type: application/octet-stream');
            }
            echo $imageData;
        } else {
            // 下载失败：降级为 302 跳转出图，避免返回 404/白屏
            header("Location: $imageUrl");
        }
    } else {
        // 302 跳转模式（默认）：直接重定向到真实图片 URL
        header("Location: $imageUrl");
    }
    exit;
}
