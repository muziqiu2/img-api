<?php
// 该模块：网络相关。客户端 IP 获取、安全 URL 校验（SSRF 防护）、
// 重定向地址解析、远程图片安全抓取，以及移动设备判断。

function getClientIp() {
    $ip = null;

    // 如果配置了信任代理头，则检查代理相关头部
    if (TRUST_PROXY_HEADERS) {
        // X-Forwarded-For: client, proxy1, proxy2
        // ⚠ 注意：XFF 最右侧地址可被客户端/上游直接伪造，仅应信任「由可信代理追加、
        //   且为公网（非私有/非保留段）」的最右 IP。故从右往左跳过私有/保留段取第一个公网 IP。
        $xff = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';
        if (!empty($xff)) {
            $ips = array_reverse(array_map('trim', explode(',', $xff)));
            foreach ($ips as $candidate) {
                // 跳过内网/保留段（这些多为代理内网地址，不可作为真实客户端 IP）
                if (filter_var($candidate, FILTER_VALIDATE_IP,
                    FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    $ip = $candidate;
                    break;
                }
            }
        }

        // X-Real-IP
        if (empty($ip)) {
            $xri = $_SERVER['HTTP_X_REAL_IP'] ?? '';
            if (!empty($xri) && filter_var(trim($xri), FILTER_VALIDATE_IP)) {
                $ip = trim($xri);
            }
        }
    }

    // 默认使用 REMOTE_ADDR（最可靠，但可能在代理后不准确）
    if (empty($ip)) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }

    // 验证IP格式
    if (!filter_var($ip, FILTER_VALIDATE_IP)) {
        $ip = 'invalid';
    }

    return $ip;
}
// 安全 URL 校验（含 SSRF 防护：仅允许公网 http/https 地址）
// 校验通过时通过引用返回解析结果（host/ip/port），供 fetchRemoteImage 固定 IP 使用
function isSafeRemoteUrl($url, &$resolved = null) {
    $resolved = null;
    $url = trim($url);

    if (empty($url) || filter_var($url, FILTER_VALIDATE_URL) === false) {
        return false;
    }

    $parsed = parse_url($url);
    $scheme = strtolower($parsed['scheme'] ?? '');
    $host = $parsed['host'] ?? '';

    if (!in_array($scheme, ['http', 'https'])) {
        return false;
    }
    if (empty($host)) {
        return false;
    }

    // 禁止本地主机名
    $lowerHost = strtolower($host);
    $localHostnames = ['localhost', 'localhost.localdomain', 'local', '127.0.0.1', '0.0.0.0', '[::1]'];
    if (in_array($lowerHost, $localHostnames)) {
        return false;
    }

    // 解析 IP 并禁止内网/保留地址
    // 若 host 本身就是 IP 字面量，直接用它校验；否则通过 DNS 解析域名
    $rawIp = filter_var($host, FILTER_VALIDATE_IP);
    if ($rawIp !== false) {
        $ip = $rawIp;
    } else {
        $ip = gethostbyname($host);
        // gethostbyname 解析失败（含域名无法解析、仅 IPv6 地址等情况）时返回原 host
        if ($ip === $host || empty($ip)) {
            return false;
        }
    }

    $forbiddenPatterns = [
        '/^(10\.)/',
        '/^172\.(1[6-9]|2[0-9]|3[01])\./',
        '/^192\.168\./',
        '/^127\./',
        '/^169\.254\./',
        '/^0\./',
        '/^224\./',
        '/^240\./',
        '/^255\.255\.255\.255$/',
        // CGNAT 共享地址段 100.64.0.0/10
        '/^100\.(6[4-9]|[7-9][0-9]|1[0-1][0-9]|12[0-7])\./',
        // 基准测试网段 198.18.0.0/15
        '/^198\.(1[89])\./',
        // 文档/测试专用网段 TEST-NET-1/2/3
        '/^192\.0\.2\./',
        '/^198\.51\.100\./',
        '/^203\.0\.113\./',
        '/^(fe80|fc00|fd00|::1|fe80::)/i',
        '/^\[/', // IPv6 raw
    ];
    foreach ($forbiddenPatterns as $pattern) {
        if (preg_match($pattern, $ip)) {
            return false;
        }
    }

    // 双保险：用 PHP 官方过滤器覆盖其自带的私有/保留网段（10/8、172.16/12、192.168/16、
    // 0/8、127/8、169.254/16、组播与 240/4 等）。
    // 注意：filter_var 的 FILTER_FLAG_NO_RES_RANGE 并不覆盖 CGNAT(100.64/10)、测试网段与
    // 198.18/15 基准段——这些由上方正则补足。二者叠加后才是完整兜底，避免单一路径漏判。
    if (!filter_var($ip, FILTER_VALIDATE_IP,
        FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
        return false;
    }

    // 端口白名单（仅允许常见 Web 端口，防止探测内网非 Web 服务）
    $port = $parsed['port'] ?? null;
    if ($port !== null && !in_array((int)$port, [80, 443, 8080, 8443])) {
        return false;
    }

    $resolved = [
        'host'   => $host,
        'ip'     => $ip,
        'port'   => $port !== null ? (int)$port : ($scheme === 'https' ? 443 : 80),
        'scheme' => $scheme,
        'url'    => $url,
    ];
    return true;
}
// 验证图片URL（兼容旧接口，实际委托给 isSafeRemoteUrl）
function isValidImageUrl($url) {
    return isSafeRemoteUrl($url);
}
// 将（可能是相对的）重定向地址解析为绝对地址
function resolveRelativeUrl($baseUrl, $redirectUrl) {
    $redirectUrl = trim($redirectUrl);
    if (filter_var($redirectUrl, FILTER_VALIDATE_URL)) {
        return $redirectUrl;
    }

    $parsed = parse_url($baseUrl);
    $scheme = $parsed['scheme'] ?? 'http';
    $host = $parsed['host'] ?? '';
    if (empty($host)) {
        return $redirectUrl;
    }
    $port = isset($parsed['port']) ? ':' . $parsed['port'] : '';
    $origin = $scheme . '://' . $host . $port;

    if (strpos($redirectUrl, '//') === 0) {
        return $scheme . ':' . $redirectUrl;
    }
    if (strpos($redirectUrl, '/') === 0) {
        return $origin . $redirectUrl;
    }
    // 相对路径：基于当前路径的目录部分拼接
    $path = $parsed['path'] ?? '/';
    $baseDir = preg_replace('#/[^/]*$#', '/', $path);
    return $origin . $baseDir . $redirectUrl;
}
// SSRF防护：安全获取远程图片（每一跳重定向均重新校验，防止重定向绕过防护）
function fetchRemoteImage($url) {
    // 初始 URL 校验并取得固定解析结果
    if (!isSafeRemoteUrl($url, $resolved)) {
        return false;
    }

    // Content-Type 白名单
    $allowedTypes = [
        'image/jpeg' => true, 'image/jpg' => true,
        'image/png' => true, 'image/gif' => true,
        'image/webp' => true, 'image/bmp' => true,
        'image/svg+xml' => true, 'image/x-icon' => true,
    ];

    // 限制下载大小（5MB）
    $maxSize = 5 * 1024 * 1024;
    $data = '';
    $totalSize = 0;
    $contentTypeOk = true;
    $responseStatus = 0;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
    // 关闭自动跟随重定向：每一跳都在本函数内重新校验后再请求，防止 SSRF 绕过
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ImageFetcher/1.0)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $chunk) use (&$data, $maxSize, &$totalSize) {
        $chunkLen = strlen($chunk);
        $totalSize += $chunkLen;
        if ($totalSize > $maxSize) {
            return 0; // 返回0中止传输
        }
        $data .= $chunk;
        return $chunkLen;
    });

    // 仅校验最终 200 响应的 Content-Type（重定向响应不参与校验）
    curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($ch, $header) use (&$contentTypeOk, &$responseStatus, $allowedTypes) {
        $len = strlen($header);
        $trimmed = trim($header);
        if (preg_match('#^HTTP/\S+\s+(\d{3})#i', $trimmed, $m)) {
            $responseStatus = (int)$m[1];
            $contentTypeOk = ($responseStatus === 200);
            return $len;
        }
        if ($responseStatus === 200 && stripos($trimmed, 'Content-Type:') === 0) {
            $type = trim(substr($trimmed, 13));
            $type = strtolower(explode(';', $type)[0]);
            if (!isset($allowedTypes[$type])) {
                $contentTypeOk = false;
            }
        }
        return $len;
    });

    // 手动处理重定向：每跳重新校验目标地址并固定 IP（防止 DNS 重绑定与内网跳转）
    $currentUrl = $url;
    $currentResolved = $resolved;
    $maxRedirects = 3;
    $httpCode = 0;
    $error = '';
    $success = false;

    for ($hop = 0; $hop <= $maxRedirects; $hop++) {
        $data = '';
        $totalSize = 0;
        $contentTypeOk = true;
        $responseStatus = 0;

        curl_setopt($ch, CURLOPT_URL, $currentUrl);
        curl_setopt($ch, CURLOPT_RESOLVE, [
            $currentResolved['host'] . ':' . $currentResolved['port'] . ':' . $currentResolved['ip']
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = curl_error($ch);

        if ($response === false || !empty($error)) {
            $success = false;
            break;
        }

        if ($httpCode >= 300 && $httpCode < 400) {
            // 重定向：解析目标地址并重新校验
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            if (empty($redirectUrl)) {
                $success = false;
                $error = '重定向地址为空';
                break;
            }
            $redirectUrl = resolveRelativeUrl($currentUrl, $redirectUrl);
            if (!isSafeRemoteUrl($redirectUrl, $currentResolved)) {
                $success = false;
                $error = '重定向目标地址不合法';
                break;
            }
            $currentUrl = $redirectUrl;
            continue; // 进入下一跳
        }

        if ($httpCode === 200) {
            $success = true;
            break;
        }

        $success = false;
        $error = 'HTTP ' . $httpCode;
        break;
    }

    curl_close($ch);

    if (!$success || !empty($error) || $httpCode !== 200) {
        return false;
    }

    if (!$contentTypeOk) {
        return false;
    }

    // 内容验证：检测文件签名（魔数）
    // 注意：签名必须存为数组"值"（而非键）——'47494638'/'52494646' 这类纯数字字符串
    // 若作数组键会被 PHP 自动转成整数，strpos/strncmp 收到 int needle 会触发 Deprecated
    // 且匹配失效（int 被当作字符字节值），导致 GIF/WEBP 图片校验失败
    $allowedSignatures = [
        'ffd8ff',   // JPEG
        '89504e47', // PNG
        '47494638', // GIF
        '52494646', // WEBP (RIFF)
        '424d',     // BMP
    ];
    if (strlen($data) >= 4) {
        $signature = bin2hex(substr($data, 0, 4));
        $isValidImage = false;
        foreach ($allowedSignatures as $sig) {
            if (strncmp($signature, $sig, strlen($sig)) === 0) {
                if ($sig === '52494646') {
                    // RIFF 头不足以确认 WEBP（WAV/AVI 同为 RIFF 容器），必须校验第 8-11 字节
                    $isValidImage = (strlen($data) >= 12) && (substr($data, 8, 4) === 'WEBP');
                } else {
                    $isValidImage = true;
                }
                break;
            }
        }
        if (!$isValidImage && strlen($data) >= 200) {
            // 备用：使用 finfo 检测
            if (class_exists('finfo')) {
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $detectedMime = $finfo->buffer($data);
                if (!isset($allowedTypes[$detectedMime])) {
                    return false;
                }
            } else {
                return false;
            }
        } elseif (!$isValidImage) {
            return false;
        }
    }

    if (strlen($data) < 100) {
        return false;
    }

    return $data;
}
// 判断设备类型
function isMobileDevice() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';

    if (empty($userAgent)) {
        return false;
    }

    $mobileAgents = [
        'android', 'webos', 'iphone', 'ipad', 'ipod', 'blackberry',
        'iemobile', 'opera mini', 'mobile', 'windows phone',
        'kindle', 'silk/', 'symbian', 'maemo', 'samsung', 'htc',
        'nokia', 'sony', 'lg-', 'lg /', 'lge ', 'bada', 'meego',
        'j2me', 'midp', 'wap', 'phone', 'pocket', 'pda',
    ];

    $lowerAgent = strtolower($userAgent);

    foreach ($mobileAgents as $agent) {
        if (strpos($lowerAgent, $agent) !== false) {
            return true;
        }
    }
    return false;
}
