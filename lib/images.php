<?php
// 该模块：图片数据管理。图片数量/分页查询、增删清空、以及随机取图
// （id 列表缓存 + array_rand 均匀随机）相关的数据读取函数。

function getImageCount($type = 'pc') {
    // 复用数量缓存（避免每页/每请求 COUNT(*)，图片数低频变化且增删路径均已清缓存）
    $cached = getCachedImageCount($type);
    if ($cached !== null) {
        return $cached;
    }

    $db = getDb();
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM image_urls WHERE type = ?");
    $stmt->execute([$type]);
    $result = $stmt->fetch();
    $count = $result['cnt'] ?? 0;

    setCachedImageCount($type, $count);
    return $count;
}

function getImageUrls($type = 'pc', $page = 1, $perPage = 20) {
    $db = getDb();
    
    // 获取总数
    $stmt = $db->prepare("SELECT COUNT(*) as cnt FROM image_urls WHERE type = ?");
    $stmt->execute([$type]);
    $total = $stmt->fetch()['cnt'] ?? 0;
    
    $totalPages = $total > 0 ? ceil($total / $perPage) : 0;
    $page = max(1, min($page, max(1, $totalPages)));
    $offset = ($page - 1) * $perPage;
    
    // 获取分页数据
    $stmt = $db->prepare("
        SELECT url FROM image_urls 
        WHERE type = ? 
        ORDER BY id DESC 
        LIMIT ? OFFSET ?
    ");
    // LIMIT/OFFSET 必须显式绑定为整型，避免部分 SQLite 驱动将字符串参数误判
    $stmt->bindValue(1, $type, PDO::PARAM_STR);
    $stmt->bindValue(2, $perPage, PDO::PARAM_INT);
    $stmt->bindValue(3, $offset, PDO::PARAM_INT);
    $stmt->execute();
    $urls = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    return [
        'urls' => $urls,
        'total' => $total,
        'pages' => $totalPages,
        'page' => $page
    ];
}

function addImageUrls($urls, $type = 'pc') {
    $db = getDb();
    $added = 0;
    
    $stmt = $db->prepare("INSERT OR IGNORE INTO image_urls (url, type) VALUES (?, ?)");
    
    foreach ($urls as $url) {
        $url = trim($url);
        if (isValidImageUrl($url)) {
            if ($stmt->execute([$url, $type])) {
                if ($stmt->rowCount() > 0) {
                    $added++;
                }
            }
        }
    }
    
    // 清除缓存
    clearCachedImageUrls($type);
    
    return $added;
}

function deleteImageUrl($url, $type = 'pc') {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM image_urls WHERE url = ? AND type = ?");
    $stmt->execute([trim($url), $type]);
    
    if ($stmt->rowCount() > 0) {
        clearCachedImageUrls($type);
        return true;
    }
    return false;
}

function clearImageUrls($type = 'pc') {
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM image_urls WHERE type = ?");
    $result = $stmt->execute([$type]);
    
    if ($result) {
        clearCachedImageUrls($type);
    }
    return $result;
}
function getRandomImageUrl($type = 'pc') {
    $db = getDb();

    // 均匀随机：缓存该类型全部图片 id，array_rand 随机取一个。
    // 对比 rowid 范围随机（id 空洞时分布不均、反复命中同一张）无空洞偏差、各图等概率；
    // id 仅为 int 数组，即使几千条也只需几 KB~几十 KB，载入/解析开销可忽略。
    $ids = getCachedImageIds($type);
    if ($ids === null) {
        $ids = loadImageIdList($db, $type);
    }
    if (empty($ids)) {
        return false;
    }

    // 抽中已删除的陈旧 id 时（删除图片与随机请求并发、或缓存未及时失效），
    // 重建 id 列表后重试一次，避免单次 404（与旧版「按实际计数重试一次并刷新计数缓存」等价）。
    $id = $ids[array_rand($ids)];
    $url = fetchImageUrlById($db, $id, $type);
    if ($url !== false) {
        return $url;
    }

    $ids = loadImageIdList($db, $type);
    if (empty($ids)) {
        return false;
    }
    $id = $ids[array_rand($ids)];
    return fetchImageUrlById($db, $id, $type);
}

// 从数据库载入某类型的全部图片 id 并写入缓存（未命中时重新构建）
function loadImageIdList($db, $type) {
    // 使用占位符而非 quote() 拼接，与全项目预处理风格统一，杜绝 SQL 注入面
    $stmt = $db->prepare("SELECT id FROM image_urls WHERE type = ?");
    $stmt->execute([$type]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $ids = array_values(array_map('intval', array_column($rows, 'id')));
    setCachedImageIds($type, $ids);
    return $ids;
}

// 按 id 与类型取图片 URL（不存在返回 false）
function fetchImageUrlById($db, $id, $type) {
    $stmt = $db->prepare("SELECT url FROM image_urls WHERE id = ? AND type = ?");
    $stmt->execute([$id, $type]);
    $url = $stmt->fetchColumn();
    return $url !== false ? $url : false;
}
