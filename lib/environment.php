<?php
// 该模块：本地环境检测。收集运行环境与依赖/目录检测的结构化结果，
// 并渲染「环境检测」完整 HTML。

// 检测本地环境（运行环境 + 依赖扩展 + 关键目录），供后台「环境检测」页面展示。
// 返回结构化结果便于前端渲染：environment 为环境信息，checks 为逐项依赖检查清单。
function getLocalEnvironmentChecks() {
    $checks = [];

    // ---------- 必需扩展 / 运行时 ----------
    $checks[] = [
        'name' => 'php_version',
        'label' => 'PHP 版本',
        'required' => PHP_VERSION_ID >= 70400,
        'ok' => PHP_VERSION_ID >= 70400,
        'detail' => '当前 PHP ' . PHP_VERSION . '（要求 ≥ 7.4）',
        'group' => '必需',
    ];
    $checks[] = [
        'name' => 'pdo_sqlite',
        'label' => 'PDO SQLite',
        'required' => true,
        'ok' => extension_loaded('pdo_sqlite'),
        'detail' => extension_loaded('pdo_sqlite') ? '已启用：数据库存储依赖此扩展' : '未启用：数据无法存储，应用无法工作',
        'group' => '必需',
    ];
    $checks[] = [
        'name' => 'session',
        'label' => 'Session 会话',
        'required' => true,
        'ok' => function_exists('session_start'),
        'detail' => function_exists('session_start') ? '已启用：后台登录依赖' : '未启用：管理后台无法登录',
        'group' => '必需',
    ];
    $checks[] = [
        'name' => 'json',
        'label' => 'JSON 扩展',
        'required' => true,
        'ok' => function_exists('json_encode'),
        'detail' => function_exists('json_encode') ? '已启用' : '未启用：统计缓冲与 JSON 输出不可用',
        'group' => '必需',
    ];
    $checks[] = [
        'name' => 'hash',
        'label' => 'Hash 扩展',
        'required' => true,
        'ok' => function_exists('hash_equals'),
        'detail' => function_exists('hash_equals') ? '已启用：用于密码与 CSRF 校验' : '未启用：安全校验不可用',
        'group' => '必需',
    ];

    // ---------- 功能依赖（缺失时对应功能受限，但应用仍可运行） ----------
    $curlOk = function_exists('curl_init') && function_exists('curl_exec');
    $checks[] = [
        'name' => 'curl',
        'label' => 'cURL',
        'required' => false,
        'ok' => $curlOk,
        'detail' => $curlOk
            ? '已启用：支持代理出图与自动更新下载'
            : '未启用：代理模式将降级为 302 跳转，自动更新不可用（可考虑开启 allow_url_fopen）',
        'group' => '功能',
    ];
    $checks[] = [
        'name' => 'zip',
        'label' => 'ZIP 扩展',
        'required' => false,
        'ok' => extension_loaded('zip'),
        'detail' => extension_loaded('zip')
            ? '已启用：支持自动更新解压'
            : '未启用：自动更新功能不可用，仅影响更新',
        'group' => '功能',
    ];
    $checks[] = [
        'name' => 'apcu',
        'label' => 'APCu（可选）',
        'required' => false,
        'ok' => function_exists('apcu_fetch') && @apcu_enabled(),
        'detail' => (function_exists('apcu_fetch') && @apcu_enabled())
            ? '已启用：限流使用内存计数，降低 SQLite 写压力（仅单机有效）'
            : '未启用：限流回退为 SQLite 计数。装 APCu 可在高并发下提升性能',
        'group' => '推荐',
    ];

    // ---------- 关键目录可写 ----------
    $dirs = [
        __DIR__ . '/data' => '数据目录 (data/)',
        CACHE_DIR => '缓存目录 (data/cache/)',
        UPDATE_BACKUP_DIR => '备份目录 (data/backups/)',
        UPDATE_CACHE_DIR => '更新缓存 (data/update_cache/)',
        __DIR__ . '/admin/logs' => '日志目录 (admin/logs/)',
    ];
    foreach ($dirs as $dir => $label) {
        $writable = isDirReallyWritable($dir);
        $checks[] = [
            'name' => 'dir_' . $dir,
            'label' => $label,
            'required' => true,
            'ok' => $writable,
            'detail' => $writable
                ? '可写'
                : '不可写：需授予写入权限（避免使用 chmod 777，建议调整属主为 Web 运行账户）',
            'group' => '目录',
        ];
    }

    // ---------- 环境信息（只读展示） ----------
    $db = null;
    $sqliteVersion = '';
    try {
        $dbVersion = @getDb()->query('SELECT sqlite_version()');
        $sqliteVersion = $dbVersion ? (string)$dbVersion->fetchColumn() : '';
    } catch (Exception $e) {
        $sqliteVersion = '';
    }
    $freeSpace = @disk_free_space(__DIR__);
    $environment = [
        'php_version' => PHP_VERSION . '（' . PHP_SAPI . '）',
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? '未知',
        'sqlite_version' => $sqliteVersion !== '' ? $sqliteVersion : '不可用',
        'memory_limit' => ini_get('memory_limit'),
        'upload_max_filesize' => ini_get('upload_max_filesize'),
        'post_max_size' => ini_get('post_max_size'),
        'max_execution_time' => ini_get('max_execution_time') ? ini_get('max_execution_time') . 's' : '不限(0)',
        'timezone' => date_default_timezone_get(),
    ];

    return [
        'environment' => $environment,
        'checks' => $checks,
    ];
}

// 渲染「环境检测」完整 HTML（运行环境表 + 依赖与目录检测表）。
// 供后台「环境检测」页与「系统更新 → 环境明细」复用，避免重复渲染逻辑。
function renderEnvironmentChecksHtml() {
    $envData = getLocalEnvironmentChecks();
    $envLabels = [
        'php_version' => 'PHP 版本',
        'server_software' => '服务器软件',
        'sqlite_version' => 'SQLite 版本',
        'memory_limit' => '内存限制 (memory_limit)',
        'upload_max_filesize' => '上传大小限制',
        'post_max_size' => 'POST 请求限制',
        'max_execution_time' => '执行时间限制',
        'timezone' => '时区',
    ];

    ob_start();
    ?>
    <!-- 运行环境信息 -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">运行环境</h3>
        </div>
        <div class="card-body">
            <div class="table-responsive">
            <table class="table table-bordered table-striped">
                <tbody>
                <?php foreach ($envData['environment'] as $key => $value): ?>
                    <tr>
                        <th style="width:220px;"><?php echo htmlspecialchars($envLabels[$key] ?? $key, ENT_QUOTES); ?></th>
                        <td><?php echo htmlspecialchars((string)$value, ENT_QUOTES); ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>
        </div>
    </div>

    <!-- 依赖与目录检测 -->
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">依赖与目录检测</h3>
        </div>
        <div class="card-body">
            <?php
            $failed = array_filter($envData['checks'], function ($c) { return !$c['ok']; });
            $grouped = [];
            foreach ($envData['checks'] as $c) {
                $grouped[$c['group']][] = $c;
            }
            ?>
            <?php if (empty($failed)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> 所有必需项均满足，环境正常。
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> 有 <?php echo count($failed); ?> 项未通过，请参考下表逐项处理。
                </div>
            <?php endif; ?>

            <?php foreach ($grouped as $group => $items): ?>
                <h6 class="text-muted mb-3"><?php echo htmlspecialchars($group, ENT_QUOTES); ?></h6>
                <div class="table-responsive">
                <table class="table table-bordered table-striped mb-4">
                    <thead>
                    <tr>
                        <th style="width:40px;"><i class="fas fa-exchange-alt"></i></th>
                        <th style="width:220px;">项目</th>
                        <th>说明</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $c): ?>
                        <tr>
                            <td class="text-center">
                                <?php if ($c['ok']): ?>
                                    <i class="fas fa-check-circle text-success" title="通过"></i>
                                <?php else: ?>
                                    <i class="fas fa-times-circle text-danger" title="未通过"></i>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php echo htmlspecialchars($c['label'], ENT_QUOTES); ?>
                                <?php if ($c['required']): ?>
                                    <span class="badge text-bg-danger">必需</span>
                                <?php else: ?>
                                    <span class="badge text-bg-secondary">可选</span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo htmlspecialchars($c['detail'], ENT_QUOTES); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
