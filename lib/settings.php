<?php
// 该模块：应用设置。封装 app_settings 表的读写（带短 TTL APCu 缓存），
// 并提供网站展示设置、GitHub Token、图片访问模式、JSON 开关、
// 统计自动落库间隔与可调限流值等业务读取函数。

// app_settings 的 APCu 缓存 key。设置项多在高频路径读取（image_mode/rate_limit/enable_json…），
// 用短 TTL 缓存消除每请求的 SQLite 查询；后台写入时调 clearAppSettingCache 及时失效。
function appSettingCacheKey($key) {
    return 'appset:' . sha1($key);
}

function clearAppSettingCache($key) {
    if (function_exists('apcu_delete') && @apcu_enabled()) {
        @apcu_delete(appSettingCacheKey($key));
    }
}

function getAppSetting($key, $default = '') {
    if (function_exists('apcu_fetch') && @apcu_enabled()) {
        $cacheKey = appSettingCacheKey($key);
        if (apcu_exists($cacheKey)) {
            return apcu_fetch($cacheKey);
        }
        $db = getDb();
        $stmt = $db->prepare("SELECT value FROM app_settings WHERE key = ?");
        $stmt->execute([$key]);
        $result = $stmt->fetch();
        $value = $result ? ($result['value'] ?? $default) : $default;
        // 短暂缓存（30 秒），兼顾读性能与后台改动的及时生效
        @apcu_store($cacheKey, $value, 30);
        return $value;
    }
    $db = getDb();
    $stmt = $db->prepare("SELECT value FROM app_settings WHERE key = ?");
    $stmt->execute([$key]);
    $result = $stmt->fetch();
    return $result ? ($result['value'] ?? $default) : $default;
}

function setAppSetting($key, $value) {
    clearAppSettingCache($key); // 写入前失效旧缓存，避免读到过期值
    $db = getDb();
    $stmt = $db->prepare("INSERT OR REPLACE INTO app_settings (key, value) VALUES (?, ?)");
    return $stmt->execute([$key, $value]);
}

function deleteAppSetting($key) {
    clearAppSettingCache($key);
    $db = getDb();
    $stmt = $db->prepare("DELETE FROM app_settings WHERE key = ?");
    return $stmt->execute([$key]);
}

// 获取网站展示设置（前台首页用），未设置时返回默认值
function getSiteSettings() {
    $defaults = [
        'site_title'     => '魔法师随机图片API',
        'site_name'      => '魔法师随机图片API',
        'site_lead'      => '免费提供高质量随机二次元图片API服务',
        'site_copyright' => '魔法师随机图片API',
        'site_icp'       => '',
    ];
    $settings = [];
    foreach ($defaults as $key => $default) {
        $settings[$key] = getAppSetting($key, $default);
    }
    return $settings;
}

// 获取 GitHub Token（优先从数据库获取，否则使用配置文件）
function getGithubToken() {
    $token = getAppSetting('github_token', '');
    if (empty($token)) {
        $token = defined('GITHUB_TOKEN') ? GITHUB_TOKEN : '';
    }
    return $token;
}
// 可调限流值（后台可配置，存 app_settings 表，未设置时回退到常量默认值）
// 范围钳制在 1 ~ 10000，防止配置异常导致误封或绕过限制
function getApiRateLimitMax() {
    $v = intval(getAppSetting('rate_limit_api', ''));
    return ($v >= 1 && $v <= 10000) ? $v : RATE_LIMIT_MAX_API;
}

function getAdminRateLimitMax() {
    $v = intval(getAppSetting('rate_limit_admin', ''));
    return ($v >= 1 && $v <= 10000) ? $v : RATE_LIMIT_MAX_ADMIN;
}
// 图片访问模式（后台可配置，存 app_settings 表，未设置时默认 302 跳转）
// proxy    —— 代理模式：服务器下载图片并转发给用户，隐藏真实图片链接
// redirect —— 302 跳转模式：直接重定向到真实图片 URL（默认，兼容原行为）
// 模式由后台完全决定，API 的 return 参数不再生效
function getImageAccessMode() {
    $mode = getAppSetting('image_mode', '');
    return ($mode === 'proxy') ? 'proxy' : 'redirect';
}

// JSON 格式输出开关（后台可配置，存 app_settings 表，未设置时默认关闭）
// 开启后可通过 ?format=json 获取图片地址 JSON。
// 注意：当图片访问模式为「代理模式」时，JSON 会返回真实图片 URL，
// 从而暴露代理模式本应隐藏的图片链接，仅建议在确认无泄露风险时开启。
function isJsonEnabled() {
    return getAppSetting('enable_json', '0') === '1';
}
// 统计自动落库间隔（后台可配置，存 app_settings 表，未设置时回退到常量默认值）
// 范围：0 表示禁用自动落库；10 ~ 86400 秒（1天）为有效值，过小会退化为高频写库、过大则近似禁用。
// 非法值均回退到 STATS_AUTO_FLUSH_INTERVAL 默认值。
function getStatsAutoFlushInterval() {
    $raw = getAppSetting('stats_auto_flush_interval', '');
    // 未配置（空字符串）时回退默认；需区分「未设置」与「显式设为 0 禁用」
    if ($raw === '') {
        return STATS_AUTO_FLUSH_INTERVAL;
    }
    $v = intval($raw);
    if ($v === 0) {
        return 0; // 显式禁用
    }
    return ($v >= 10 && $v <= 86400) ? $v : STATS_AUTO_FLUSH_INTERVAL;
}
