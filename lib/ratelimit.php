<?php
// 该模块：频率限制（Rate Limit）。优先使用 APCu 内存计数，无 APCu 时降级到
// SQLite 滑动窗口计数；提供 API 与管理后台的限流入口及可调限流参数读取。

// 限流计数是否为内存级（APCu）：单机部署时可用，消除每请求的 SQLite 写锁竞争。
// ⚠️ 仅限单机：APCu 为共享内存、进程间可见但不跨主机，负载均衡多机时各节点各自计数，
//    限流会被放大 N 倍，故仅在单机时启用，否则回退到 SQLite 计数。
function rateLimitCanUseApcu() {
    return function_exists('apcu_fetch') && is_callable('apcu_inc') && @apcu_enabled();
}

// 限流入口：优先 APCu 内存计数（内存级、无 SQLite 写），无 APCu 时降级回 SQLite 计数。
// 限流计数属可丢弃数据（重启/清缓存仅使窗口重新计数），正是内存计数的适用场景。
function applyRateLimit($key, $maxRequests, $windowSeconds) {
    if (rateLimitCanUseApcu()) {
        return applyRateLimitApcu($key, $maxRequests, $windowSeconds);
    }
    try {
        return applyRateLimitDb($key, $maxRequests, $windowSeconds);
    } catch (Throwable $e) {
        // SQLite 版本过旧（UPSERT 语法需 3.24+）或其它数据库异常：保守放行并记录，
        // 避免限流系统自身故障导致整个 API 不可用（环境检测页已提供版本检测项）
        @error_log('[img-api] 限流计数异常（已放行）: ' . $e->getMessage());
        return true;
    }
}

// APCu 固定窗口计数：按时间窗口分桶，窗口过期由 TTL 自动清理，无需手动 DELETE。
// apcu_inc 为原子操作（跨 PHP-FPM worker），效果等价于锁，且无任何 DB 写。
function applyRateLimitApcu($key, $maxRequests, $windowSeconds) {
    // 窗口分桶号，保证同窗口内同一 key 落在同一 bucket
    $bucket = (int)floor(time() / $windowSeconds);
    $apcuKey = 'rl:' . $bucket . ':' . md5($key);
    // apcu_inc(key, step, &success, ttl)：key 首次创建时按 step 计为 1；TTL 留 30s 余量避免窗口抖动误清
    $count = apcu_inc($apcuKey, 1, $success, $windowSeconds + 30);
    if ($success) {
        return $count <= $maxRequests;
    }
    // APCu 内部异常：保守放行，避免误封（DB 降级路径会兜底）
    return true;
}

// 限流降级路径：SQLite 滑动窗口计数（无 APCu 时使用，保持原有行为）
function applyRateLimitDb($key, $maxRequests, $windowSeconds) {
    $now = time();
    $windowStart = $now - $windowSeconds;

    $db = getDb();

    // 概率化清理过期记录（避免每次请求都执行全表 DELETE，降低 SQLite 写负载）
    if (mt_rand(1, 50) === 1) {
        $stmt = $db->prepare("DELETE FROM rate_limits WHERE timestamp < ?");
        $stmt->execute([$windowStart]);
    }

    // 先检查当前计数是否已超过限制
    $stmt = $db->prepare("SELECT count FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();

    // 如果已超过限制，直接拒绝
    if ($record && (int)$record['count'] >= $maxRequests) {
        return false;
    }

    // 原子地增加计数（UPSERT：窗口过期则重置为 1，否则 +1）
    $stmt = $db->prepare("
        INSERT INTO rate_limits (id, count, timestamp)
        VALUES (?, 1, ?)
        ON CONFLICT(id) DO UPDATE
        SET count = CASE WHEN timestamp < ? THEN 1 ELSE count + 1 END,
            timestamp = CASE WHEN timestamp < ? THEN ? ELSE timestamp END
    ");
    $stmt->execute([$key, $now, $windowStart, $windowStart, $now]);

    // 复查：若本次递增导致超限，补偿回退（近似回滚，最多瞬时超限 1 个请求）
    $stmt = $db->prepare("SELECT count FROM rate_limits WHERE id = ?");
    $stmt->execute([$key]);
    $record = $stmt->fetch();
    if ($record && (int)$record['count'] > $maxRequests) {
        $stmt = $db->prepare("UPDATE rate_limits SET count = count - 1 WHERE id = ?");
        $stmt->execute([$key]);
        return false;
    }

    return true;
}
function checkApiRateLimit() {
    $ip = md5(getClientIp());
    return applyRateLimit('api_' . $ip, getApiRateLimitMax(), RATE_LIMIT_WINDOW);
}

function checkAdminRateLimit() {
    if (!IS_LOGGED_IN) {
        return true;
    }

    $username = md5($_SESSION['admin_username'] ?? 'unknown');
    return applyRateLimit('admin_' . $username, getAdminRateLimitMax(), RATE_LIMIT_WINDOW);
}

// 通用管理后台频率限制函数（可自定义最大请求数）
function checkAdminRateLimitGeneric($maxRequests = 30, $windowSeconds = 60) {
    if (!IS_LOGGED_IN) {
        return true;
    }

    $username = md5($_SESSION['admin_username'] ?? 'unknown');
    return applyRateLimit('admin_generic_' . $username . '_' . $maxRequests, $maxRequests, $windowSeconds);
}
