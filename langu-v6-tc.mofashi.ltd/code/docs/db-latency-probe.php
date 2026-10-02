<?php
// 只读诊断：测量 MySQL 层真实耗时（不启动 Laravel，不写 storage）
$envFile = '/www/wwwroot/tc.mofashi.ltd/.env';
$env = [];
foreach (file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
    if ($line === '' || $line[0] === '#' || strpos($line, '=') === false) continue;
    [$k, $v] = explode('=', $line, 2);
    $env[trim($k)] = trim(trim($v), '"\'');
}
$dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
    $env['DB_HOST'] ?? '127.0.0.1', $env['DB_PORT'] ?? '3306', $env['DB_DATABASE'] ?? 'tc');

$t = microtime(true);
$pdo = new PDO($dsn, $env['DB_USERNAME'], $env['DB_PASSWORD'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
printf("PDO 建连                     %7.2f ms\n", (microtime(true) - $t) * 1000);

function q(PDO $pdo, string $sql, bool $count = false): array {
    $t = microtime(true);
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    return [(microtime(true) - $t) * 1000, $rows];
}

[$t1, $r1] = q($pdo, 'SELECT COUNT(*) AS c FROM images');
printf("COUNT(*) images              %7.2f ms   行数=%s\n", $t1, array_values($r1[0])[0]);

[$t2, ] = q($pdo, 'SELECT * FROM groups WHERE is_guest = 1 LIMIT 1');
printf("groups WHERE is_guest        %7.2f ms\n", $t2);

[$t3, ] = q($pdo, 'SELECT * FROM images ORDER BY id DESC LIMIT 20');
printf("images 列表 20 行            %7.2f ms\n", $t3);

$t = microtime(true);
for ($i = 0; $i < 13; $i++) { $pdo->query('SELECT * FROM groups WHERE is_guest = 1 LIMIT 1')->fetchAll(); }
printf("groups x13 (模拟 composer)   %7.2f ms  → 均 %.2f ms/次\n", ($t = (microtime(true) - $t) * 1000), $t / 13);

$t = microtime(true);
for ($i = 0; $i < 13; $i++) { $pdo->query('SELECT COUNT(*) FROM images')->fetchAll(); }
printf("COUNT(*) x13                 %7.2f ms  → 均 %.2f ms/次\n", ($t = (microtime(true) - $t) * 1000), $t / 13);

[$t4, ] = q($pdo, 'SELECT COUNT(*) AS c FROM images');
printf("COUNT(*) 再来一次(缓冲热)     %7.2f ms\n", $t4);

echo "\n--- MySQL 状态 ---\n";
foreach ($pdo->query("SHOW GLOBAL STATUS WHERE Variable_name IN ('Innodb_buffer_pool_reads','Innodb_buffer_pool_read_requests','Uptime','Questions')")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    printf("%-38s %s\n", $r['Variable_name'], $r['Value']);
}
echo "\n--- images 表结构索引 ---\n";
foreach ($pdo->query('SHOW INDEX FROM images')->fetchAll(PDO::FETCH_ASSOC) as $r) {
    printf("索引 %-12s 列 %-16s 区分度 %s\n", $r['Key_name'], $r['Column_name'], $r['Cardinality']);
}
echo "\n--- 引擎与行数估计 ---\n";
foreach ($pdo->query("SHOW TABLE STATUS WHERE Name='images'")->fetchAll(PDO::FETCH_ASSOC) as $r) {
    printf("引擎=%s 行数估计=%s 数据=%.1fMB 索引=%.1fMB\n", $r['Engine'], $r['Rows'], $r['Data_length'] / 1048576, $r['Index_length'] / 1048576);
    echo "行格式=" . $r['Row_format'] . " 平均行长=" . $r['Avg_row_length'] . "\n";
}
