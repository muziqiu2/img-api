<?php
// 该模块：调用统计。处理统计缓冲文件、APCu 内存计数与 SQLite 三者的写入/合并/归档，
// 以及只读查询与自动落库。

// 当日统计缓冲文件路径
function statsBufferFile($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    return CACHE_DIR . '/call_stats_' . $date . '.json';
}

// 读取当日统计缓冲（无缓冲或损坏时返回全零）
function readStatsBuffer($date = null) {
    $file = statsBufferFile($date);
    if (!file_exists($file)) {
        return ['total' => 0, 'pc' => 0, 'pe' => 0, 'api' => 0, 'redirect' => 0, 'json' => 0, 'img' => 0];
    }
    $data = @json_decode(file_get_contents($file), true);
    return is_array($data) ? array_merge(
        ['total' => 0, 'pc' => 0, 'pe' => 0, 'api' => 0, 'redirect' => 0, 'json' => 0, 'img' => 0],
        $data
    ) : ['total' => 0, 'pc' => 0, 'pe' => 0, 'api' => 0, 'redirect' => 0, 'json' => 0, 'img' => 0];
}

// 原子写入统计缓冲（先写临时文件再 rename）
function writeStatsBuffer($data, $date = null) {
    $file = statsBufferFile($date);
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    $tmp = $file . '.tmp.' . getmypid() . '.' . uniqid();
    if (@file_put_contents($tmp, json_encode($data)) === false) {
        return false;
    }
    return @rename($tmp, $file);
}

// 直接把增量写入 SQLite（降级路径 / 缓冲合并使用）
function writeCallStatsDirect($date, $isApi, $isPc, $isPe, $isRedirect, $isJson, $isImg, $totalInc = 1) {
    $db = getDb();

    // 检查今天的记录是否存在
    $stmt = $db->prepare("SELECT * FROM call_stats WHERE date = ?");
    $stmt->execute([$date]);
    $record = $stmt->fetch();

    if ($record) {
        // 更新现有记录
        $stmt = $db->prepare("UPDATE call_stats SET 
            total = total + ?,
            pc = pc + ?,
            pe = pe + ?,
            api_count = api_count + ?,
            redirect_count = redirect_count + ?,
            json_count = json_count + ?,
            img_count = img_count + ?
            WHERE date = ?");
        $stmt->execute([$totalInc, $isPc, $isPe, $isApi, $isRedirect, $isJson, $isImg, $date]);
    } else {
        // 插入新记录
        $stmt = $db->prepare("INSERT INTO call_stats (date, total, pc, pe, api_count, redirect_count, json_count, img_count)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$date, $totalInc, $isPc, $isPe, $isApi, $isRedirect, $isJson, $isImg]);
    }
    return true;
}
// 全部统计列字段名（file/APCu/SQLite 三处保持一致）
function statsFields() {
    return ['total', 'pc', 'pe', 'api', 'redirect', 'json', 'img'];
}

// APCu 统计计数 key（按日期分桶，跨日互不干扰）
function statsApcuKey($field, $date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    return 'stats:' . $date . ':' . $field;
}

// 统计是否可用 APCu 内存计数（单机、启用 apcu 扩展时）
function statsCanUseApcu() {
    return function_exists('apcu_fetch') && is_callable('apcu_inc') && @apcu_enabled();
}

// 读取并清空指定日期的 APCu 统计计数；仅清存量>0 的计数器，避免清掉并发新加的数据
function takeStatsApcu($date) {
    $delta = ['total' => 0, 'pc' => 0, 'pe' => 0, 'api' => 0, 'redirect' => 0, 'json' => 0, 'img' => 0];
    foreach ($delta as $f => $v) {
        $c = (int)@apcu_fetch(statsApcuKey($f, $date));
        if ($c > 0) {
            $delta[$f] = $c;
            @apcu_delete(statsApcuKey($f, $date));
        }
    }
    return $delta;
}

// 把 APCu 内存计数合并进 SQLite 并清空（今日+昨日，覆盖滚动日界可能残留的旧日期 key）
function flushStatsApcu() {
    if (!statsCanUseApcu()) {
        return;
    }
    foreach ([date('Y-m-d'), date('Y-m-d', strtotime('-1 day'))] as $date) {
        $d = takeStatsApcu($date);
        if (($d['total'] ?? 0) <= 0) {
            continue;
        }
        writeCallStatsDirect($date, $d['api'], $d['pc'], $d['pe'], $d['redirect'], $d['json'], $d['img'], $d['total']);
    }
}

// 把当日统计缓冲合并进 SQLite 并清空缓冲（在读取统计或自动落库时调用）
function flushStatsBuffer($date = null) {
    if ($date === null) {
        $date = date('Y-m-d');
    }
    $file = statsBufferFile($date);
    if (!file_exists($file)) {
        return;
    }

    // 与 updateCallCount 使用同一把文件锁，避免「并发写缓冲」与「合并清空缓冲」交错
    // 导致刚写入的计数被 unlink 丢失。拿不到锁则跳过，留待下次合并。
    $lockFile = $file . '.lock';
    $fp = @fopen($lockFile, 'c');
    if ($fp === false || !flock($fp, LOCK_EX)) {
        if (is_resource($fp)) fclose($fp);
        return;
    }

    $buf = readStatsBuffer($date);
    if (($buf['total'] ?? 0) <= 0) {
        @unlink($file);
        flock($fp, LOCK_UN);
        fclose($fp);
        return;
    }
    writeCallStatsDirect(
        $date,
        (int)($buf['api'] ?? 0),
        (int)($buf['pc'] ?? 0),
        (int)($buf['pe'] ?? 0),
        (int)($buf['redirect'] ?? 0),
        (int)($buf['json'] ?? 0),
        (int)($buf['img'] ?? 0),
        (int)($buf['total'] ?? 0)
    );
    @unlink($file);

    flock($fp, LOCK_UN);
    fclose($fp);
}
// 读取统计前合并所有残留的统计缓冲（含历史日期）。
// 若某天有调用但之后一直无人触发统计读取，缓冲文件会残留，此处兜底合并，避免数据永久丢失。
function flushAllStatsBuffers() {
    flushStatsApcu(); // APCu 内存计数（若有）
    $files = glob(CACHE_DIR . '/call_stats_*.json');
    if ($files === false) return;
    foreach ($files as $file) {
        $date = str_replace('call_stats_', '', basename($file, '.json'));
        if ($date === '' || $date === '__history__') continue;
        flushStatsBuffer($date);
    }
}

// 归档过期统计：每日明细保留 365 天，总量累加到 __history__ 行永久保留
function archiveOldCallStats() {
    // 每天只归档一次
    $markerFile = CACHE_DIR . '/stats_archive_marker';
    if (file_exists($markerFile) && date('Y-m-d', filemtime($markerFile)) === date('Y-m-d')) {
        return;
    }
    @touch($markerFile);

    $db = getDb();
    $cutoff = date('Y-m-d', strtotime('-365 days'));

    // 汇总过期明细各列总量
    $stmt = $db->prepare("SELECT 
        COALESCE(SUM(total),0) as t, COALESCE(SUM(pc),0) as pc, COALESCE(SUM(pe),0) as pe,
        COALESCE(SUM(api_count),0) as api, COALESCE(SUM(redirect_count),0) as rd,
        COALESCE(SUM(json_count),0) as js, COALESCE(SUM(img_count),0) as im
        FROM call_stats WHERE date < ? AND date != '__history__'");
    $stmt->execute([$cutoff]);
    $row = $stmt->fetch();

    if ($row && (int)$row['t'] > 0) {
        // 累加到历史归档行（保证总调用次数永久保留）
        $stmt = $db->prepare("INSERT INTO call_stats (date, total, pc, pe, api_count, redirect_count, json_count, img_count)
            VALUES ('__history__', ?, ?, ?, ?, ?, ?, ?)
            ON CONFLICT(date) DO UPDATE SET
                total = total + excluded.total,
                pc = pc + excluded.pc,
                pe = pe + excluded.pe,
                api_count = api_count + excluded.api_count,
                redirect_count = redirect_count + excluded.redirect_count,
                json_count = json_count + excluded.json_count,
                img_count = img_count + excluded.img_count");
        $stmt->execute([(int)$row['t'], (int)$row['pc'], (int)$row['pe'], (int)$row['api'], (int)$row['rd'], (int)$row['js'], (int)$row['im']]);
    }

    // 删除过期明细
    $stmt = $db->prepare("DELETE FROM call_stats WHERE date < ? AND date != '__history__'");
    $stmt->execute([$cutoff]);
}

// 按固定间隔自动落库：把统计缓冲合并进 SQLite，避免「长期不打开后台」导致计数滞留缓存而丢失。
// 使用独立落库锁 + 时间戳标记文件做节流与并发互斥；落库是后台性维护动作，不阻塞 API 响应。
function autoFlushStatsIfDue() {
    $interval = getStatsAutoFlushInterval();
    if ($interval <= 0) {
        return; // 已在后台禁用自动落库
    }

    $markerFile = CACHE_DIR . '/stats_flush_marker';
    $now = time();

    // 快路径：距上次落库未到间隔，直接跳过（无锁，开销极低）
    if (file_exists($markerFile)) {
        $last = (int)@file_get_contents($markerFile);
        if ($last > 0 && ($now - $last) < $interval) {
            return;
        }
    }

    // 慢路径：需要落库，用独立锁防止多个并发请求重复落库
    $lockFile = CACHE_DIR . '/stats_flush.lock';
    $fp = @fopen($lockFile, 'c');
    if ($fp === false || !flock($fp, LOCK_EX | LOCK_NB)) {
        if (is_resource($fp)) fclose($fp);
        return; // 已有其他请求在落库，本次跳过
    }

    // 双重检查：拿到锁后再次校验时间戳，避免阻塞等待期间已被其他请求落库
    if (file_exists($markerFile)) {
        $last = (int)@file_get_contents($markerFile);
        if ($last > 0 && ($now - $last) < $interval) {
            flock($fp, LOCK_UN);
            fclose($fp);
            return;
        }
    }

    flushAllStatsBuffers();
    archiveOldCallStats();

    // 记录本次落库时间
    @file_put_contents($markerFile, (string)$now);

    flock($fp, LOCK_UN);
    fclose($fp);
}

// 统计写入：先更新文件缓冲（低开销，不触碰 SQLite 写锁），读取统计时再合并入库
function updateCallCount($type, $returnType = 'redirect', $deviceType = null) {
    $date = date('Y-m-d');

    // 计算各列增量；api 入口按实际设备类型（$deviceType）同时计入 pc/pe 分布
    $isApi = ($type === 'api') ? 1 : 0;
    $isPc = (($type === 'pc') || ($type === 'api' && $deviceType === 'pc')) ? 1 : 0;
    $isPe = (($type === 'pe') || ($type === 'api' && $deviceType === 'pe')) ? 1 : 0;
    $isRedirect = ($returnType === 'redirect') ? 1 : 0;
    $isJson = ($returnType === 'json') ? 1 : 0;
    $isImg = ($returnType === 'img') ? 1 : 0;

    // APCu 内存计数（首选）：apcu_inc 原子递增，热路径无文件锁、无整文件重写、无 SQLite 写锁。
    // 计数属于「可延迟」数据，由 autoFlushStatsIfDue 按固定间隔落库持久化，重启丢失范围极小。
    if (statsCanUseApcu()) {
        // TTL 覆盖 2 天窗口：即使跨日滚动、偶发数天无人触发落库，计数也不至于提前过期
        $ttl = 86400 * 2 + 3600;
        apcu_inc(statsApcuKey('total', $date), 1, $ok1, $ttl);
        if ($isPc) apcu_inc(statsApcuKey('pc', $date), 1, $ok2, $ttl);
        if ($isPe) apcu_inc(statsApcuKey('pe', $date), 1, $ok3, $ttl);
        if ($isApi) apcu_inc(statsApcuKey('api', $date), 1, $ok4, $ttl);
        if ($isRedirect) apcu_inc(statsApcuKey('redirect', $date), 1, $ok5, $ttl);
        if ($isJson) apcu_inc(statsApcuKey('json', $date), 1, $ok6, $ttl);
        if ($isImg) apcu_inc(statsApcuKey('img', $date), 1, $ok7, $ttl);
        // 按间隔自动落库：让计数即使不打开后台也能及时持久化到 SQLite
        autoFlushStatsIfDue();
        return true;
    }

    // 无 APCu 回退：文件缓冲（flock 锁文件保护临界区，数据用 tmp+rename 原子写入）
    $lockFile = statsBufferFile($date) . '.lock';
    $fp = @fopen($lockFile, 'c');
    if ($fp === false || !flock($fp, LOCK_EX)) {
        if (is_resource($fp)) fclose($fp);
        // 降级：直接写库
        return writeCallStatsDirect($date, $isApi, $isPc, $isPe, $isRedirect, $isJson, $isImg);
    }

    $buf = readStatsBuffer($date);
    $buf['total'] = (int)($buf['total'] ?? 0) + 1;
    $buf['pc'] = (int)($buf['pc'] ?? 0) + $isPc;
    $buf['pe'] = (int)($buf['pe'] ?? 0) + $isPe;
    $buf['api'] = (int)($buf['api'] ?? 0) + $isApi;
    $buf['redirect'] = (int)($buf['redirect'] ?? 0) + $isRedirect;
    $buf['json'] = (int)($buf['json'] ?? 0) + $isJson;
    $buf['img'] = (int)($buf['img'] ?? 0) + $isImg;
    writeStatsBuffer($buf, $date);

    flock($fp, LOCK_UN);
    fclose($fp);

    // 按间隔自动落库：让计数即使不打开后台也能及时持久化到 SQLite
    autoFlushStatsIfDue();

    return true;
}

// 纯查询统计数据（不合并缓冲、不归档，只读操作）。
// 供公开页面（首页）使用，避免高频访问触发 SQLite 写锁。
function queryCallStatsData() {
    $db = getDb();

    // 获取总调用（SUM 包含 __history__ 归档行，总调用次数永久保留）
    $stmt = $db->prepare("SELECT 
        COALESCE(SUM(total), 0) as total,
        COALESCE(SUM(pc), 0) as pc,
        COALESCE(SUM(pe), 0) as pe,
        COALESCE(SUM(api_count), 0) as api_count,
        COALESCE(SUM(redirect_count), 0) as redirect_count,
        COALESCE(SUM(json_count), 0) as json_count,
        COALESCE(SUM(img_count), 0) as img_count
        FROM call_stats");
    $stmt->execute();
    $totals = $stmt->fetch();

    // 获取每日数据（排除历史归档行，仅保留 365 天明细）
    $stmt = $db->prepare("SELECT date, total, pc, pe, api_count FROM call_stats WHERE date != '__history__' ORDER BY date DESC LIMIT 365");
    $stmt->execute();
    $daily = [];
    while ($row = $stmt->fetch()) {
        $daily[$row['date']] = [
            'total' => (int)$row['total'],
            'pc' => (int)$row['pc'],
            'pe' => (int)$row['pe']
        ];
    }

    return [
        'total' => (int)$totals['total'],
        'pc' => (int)$totals['pc'],
        'pe' => (int)$totals['pe'],
        'api' => (int)$totals['api_count'],
        'daily' => $daily,
        'return_types' => [
            'redirect' => (int)$totals['redirect_count'],
            'json' => (int)$totals['json_count'],
            'img' => (int)$totals['img_count']
        ]
    ];
}

// 只读统计：用于公开页面。不合并缓冲、不归档，避免写库。
// 与 getCallCount 的差异仅在于是否落盘合并缓冲；数据可能滞后于最近几次未合并的缓冲区，
// 但首页仅用于展示概览，可接受，且后台打开时会触发 getCallCount 完成真正的合并入库。
function getCallCountReadOnly() {
    return queryCallStatsData();
}

function getCallCount() {
    // 读取前先合并当日及所有残留缓冲并归档过期明细
    flushAllStatsBuffers();
    archiveOldCallStats();

    return queryCallStatsData();
}

function getTotalCalls() {
    // 读取前先合并当日及所有残留缓冲
    flushAllStatsBuffers();
    archiveOldCallStats();

    $db = getDb();
    $stmt = $db->prepare("SELECT COALESCE(SUM(total), 0) as total FROM call_stats");
    $stmt->execute();
    return (int)$stmt->fetch()['total'];
}
