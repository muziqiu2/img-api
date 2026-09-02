<?php
// 该模块：图片访问缓存。管理各类型图片的数量缓存文件、全量 url 列表缓存
// 与 id 列表缓存（含 APCu 内存缓存），并在增删图片后按需清除缓存。

// 图片数量缓存文件路径
// （取代旧版"全量 URL 列表缓存"：列表过大时每请求 json_decode 全量数组，
//  内存 O(n) 且解析耗时，是高并发下的内存压力来源；数量缓存为 O(1)）
function imageCountCacheFile($type) {
    return CACHE_DIR . "/{$type}_count.cache";
}

function getCachedImageCount($type) {
    $cacheFile = imageCountCacheFile($type);
    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < CACHE_TTL) {
        $count = @file_get_contents($cacheFile);
        return is_numeric($count) ? (int)$count : null;
    }
    return null;
}

function setCachedImageCount($type, $count) {
    @file_put_contents(imageCountCacheFile($type), (string)(int)$count);
}

function clearCachedImageUrls($type) {
    // 失效 APCu 内存 id 缓存
    if (function_exists('apcu_delete') && @apcu_enabled()) {
        @apcu_delete('imgids:' . $type);
    }
    // 同时清理旧版全量列表缓存、数量缓存、旧 maxid 缓存与新的 id 列表缓存，避免增删图后残留
    foreach ([CACHE_DIR . "/{$type}_urls.cache", CACHE_DIR . "/{$type}_maxid.cache", imageCountCacheFile($type), imageIdListCacheFile($type)] as $cacheFile) {
        if (file_exists($cacheFile)) {
            @unlink($cacheFile);
        }
    }
}

// 图片 id 列表缓存：随机取图时一次性载入该类型的全部 id，用 array_rand 均匀随机选取。
// 相比 rowid 范围随机（对 id 空洞敏感、分布不均，会反复命中同一张）更均匀；
// 相比缓存全量 url 列表（每请求 json_decode 大数组、内存 O(n)）更轻量——id 为 int，
// 数千条也仅几 KB~几十 KB，解析开销可忽略。
function imageIdListCacheFile($type) {
    return CACHE_DIR . "/{$type}_ids.cache";
}

function getCachedImageIds($type) {
    // APCu 内存缓存优先：O(1) 无文件 IO（与限流一致，仅 APCu 可用时启用），
    // 避免每请求 json_decode 缓存文件的耗时
    $apcuKey = 'imgids:' . $type;
    if (function_exists('apcu_fetch') && is_callable('apcu_inc') && @apcu_enabled()) {
        $ids = apcu_fetch($apcuKey);
        if (is_array($ids) && $ids !== []) {
            return $ids;
        }
    }
    // 无 APCu 时降级文件缓存
    $cacheFile = imageIdListCacheFile($type);
    if (file_exists($cacheFile) && time() - filemtime($cacheFile) < CACHE_TTL) {
        $ids = @json_decode(@file_get_contents($cacheFile), true);
        if (is_array($ids) && $ids !== []) {
            return $ids;
        }
    }
    return null;
}

function setCachedImageIds($type, $ids, $ttl = CACHE_TTL) {
    $ids = array_values(array_map('intval', $ids));
    if (function_exists('apcu_store') && @apcu_enabled()) {
        @apcu_store('imgids:' . $type, $ids, $ttl);
    }
    @file_put_contents(imageIdListCacheFile($type), json_encode($ids));
}
