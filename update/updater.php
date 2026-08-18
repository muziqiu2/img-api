<?php
/**
 * 自动更新核心类
 *
 * 提供完整的 GitHub Releases 更新流程：
 * - 检查最新版本
 * - 下载发行包
 * - 校验文件签名
 * - 备份当前版本
 * - 安全解压并替换
 * - 执行数据库迁移
 * - 失败自动回滚
 */

require_once dirname(__DIR__) . '/config.php';

class AppUpdater
{
    private $currentVersion;
    private $latestVersion = null;
    private $releaseData = null;
    private $downloadUrl = null;
    private $zipPath = null;
    private $extractDir = null;
    private $backupPath = null;
    private $errors = [];
    private $logs = [];
    private $warnings = [];
    private $stripTopDir = false; // 结构探测结果：是否剥离 zip 顶层目录

    public function __construct()
    {
        $this->currentVersion = APP_VERSION;
        $this->zipPath = UPDATE_CACHE_DIR . '/release_' . time() . '.zip';
        $this->extractDir = UPDATE_CACHE_DIR . '/extract_' . time();
    }

    // ============================================================
    // 1. 检查更新
    // ============================================================

    /**
     * 查询 GitHub Releases 获取最新版本信息
     * @param bool $force 是否强制忽略缓存重新查询
     * @return array
     */
    public function checkForUpdate($force = false)
    {
        $this->log('开始检查更新...');

        if (!$force) {
            $cached = getCachedUpdateCheck();
            if ($cached && !empty($cached['tag_name'])) {
                $this->latestVersion = $cached['tag_name'];
                $this->releaseData = $cached;
                $this->downloadUrl = $cached['zipball_url'] ?? $cached['html_url'] ?? null;
                $hasNew = compareVersions($cached['tag_name'], $this->currentVersion) > 0;
                $this->log('使用缓存的检查结果：' . ($hasNew ? '有新版本可用' : '当前已是最新'));
                return [
                    'success' => true,
                    'cached' => true,
                    'has_update' => $hasNew,
                    'current' => $this->currentVersion,
                    'latest' => $cached['tag_name'],
                    'release' => $this->formatReleaseData($cached),
                    'warnings' => $this->warnings,
                ];
            }
        }

        clearUpdateCheckCache();

        $url = GITHUB_API_BASE . '/releases/latest';
        $response = $this->httpGet($url);

        if ($response === false) {
            $this->errors[] = '无法连接到 GitHub API，请检查网络或稍后重试';
            return ['success' => false, 'errors' => $this->errors];
        }

        $data = json_decode($response, true);
        if (!is_array($data) || empty($data['tag_name'])) {
            $this->errors[] = 'GitHub API 返回数据格式异常';
            return ['success' => false, 'errors' => $this->errors];
        }

        $this->latestVersion = $data['tag_name'];
        $this->releaseData = $data;
        $this->downloadUrl = $data['zipball_url'] ?? null;

        setCachedUpdateCheck($data);

        $hasNew = compareVersions($this->latestVersion, $this->currentVersion) > 0;
        $this->log('最新版本: ' . $this->latestVersion . ' / 当前版本: ' . $this->currentVersion);

        return [
            'success' => true,
            'cached' => false,
            'has_update' => $hasNew,
            'current' => $this->currentVersion,
            'latest' => $this->latestVersion,
            'release' => $this->formatReleaseData($data),
            'warnings' => $this->warnings,
        ];
    }

    /**
     * 格式化 release 数据供 UI 使用
     */
    private function formatReleaseData($data)
    {
        return [
            'tag_name' => $data['tag_name'] ?? '',
            'name' => $data['name'] ?? '',
            'body' => $data['body'] ?? '',
            'published_at' => $data['published_at'] ?? '',
            'html_url' => $data['html_url'] ?? '',
            'zipball_url' => $data['zipball_url'] ?? '',
            'tarball_url' => $data['tarball_url'] ?? '',
            'draft' => !empty($data['draft']),
            'prerelease' => !empty($data['prerelease']),
        ];
    }

    // ============================================================
    // 2. 执行完整更新
    // ============================================================

    /**
     * 执行完整的更新流程
     * @return array
     */
    public function doUpdate()
    {
        $this->log('========== 开始更新流程 ==========');
        $startTime = microtime(true);

        try {
            // 2.1 环境预检查
            $env = checkUpdateEnvironment();
            if (!$env['ok']) {
                throw new Exception('环境检查失败: ' . implode('；', $env['errors']));
            }
            foreach ($env['warnings'] as $w) {
                $this->log('[警告] ' . $w);
            }

            // 2.2 确保有最新版本信息
            if ($this->latestVersion === null || $this->releaseData === null) {
                $check = $this->checkForUpdate(true);
                if (!$check['success']) {
                    throw new Exception(implode('；', $check['errors']));
                }
                if (!$check['has_update']) {
                    throw new Exception('当前已是最新版本，无需更新');
                }
            }

            // 2.3 下载更新包
            $this->log('步骤 1/6: 正在下载更新包...');
            if (!$this->downloadRelease()) {
                throw new Exception('下载失败: ' . implode('；', $this->errors));
            }

            // 2.4 校验 zip 文件
            $this->log('步骤 2/6: 校验文件完整性...');
            if (!$this->verifyZipArchive()) {
                throw new Exception('文件校验失败: ' . implode('；', $this->errors));
            }

            // 2.5 备份当前版本
            $this->log('步骤 3/6: 正在创建备份...');
            if (!$this->backupCurrentVersion()) {
                throw new Exception('备份失败: ' . implode('；', $this->errors));
            }

            // 2.6 解压并安全扫描
            $this->log('步骤 4/6: 正在解压更新包...');
            if (!$this->extractAndScan()) {
                $this->rollback();
                throw new Exception('解压/扫描失败: ' . implode('；', $this->errors));
            }

            // 2.7 文件替换
            $this->log('步骤 5/6: 正在更新文件...');
            if (!$this->replaceFiles()) {
                $this->rollback();
                throw new Exception('文件替换失败: ' . implode('；', $this->errors));
            }

            // 2.8 执行数据库迁移
            $this->log('步骤 6/6: 正在执行数据库迁移...');
            $migrateResult = $this->runMigrations();
            if (!$migrateResult['success']) {
                $this->rollback();
                throw new Exception('数据库迁移失败: ' . implode('；', $migrateResult['messages']));
            }

            // 2.9 完成
            $this->finalizeUpdate();
            $duration = round(microtime(true) - $startTime, 2);
            $this->log('========== 更新完成 (耗时 ' . $duration . 's) ==========');

            logUpdateAction($this->currentVersion, $this->latestVersion, 'success', '自动更新成功，耗时 ' . $duration . 's', $this->backupPath);

            return [
                'success' => true,
                'from_version' => $this->currentVersion,
                'to_version' => $this->latestVersion,
                'backup_path' => $this->backupPath,
                'logs' => $this->logs,
                'warnings' => $this->warnings,
                'duration' => $duration,
            ];

        } catch (Exception $e) {
            $this->errors[] = $e->getMessage();
            $this->log('[错误] ' . $e->getMessage());
            logUpdateAction($this->currentVersion, $this->latestVersion ?? $this->currentVersion, 'failed', $e->getMessage(), $this->backupPath ?? '');
            return [
                'success' => false,
                'errors' => $this->errors,
                'logs' => $this->logs,
                'warnings' => $this->warnings,
                'backup_path' => $this->backupPath,
            ];
        } finally {
            $this->cleanup();
        }
    }

    // ============================================================
    // 3. 下载更新包
    // ============================================================

    private function downloadRelease()
    {
        $url = $this->downloadUrl;
        if (empty($url)) {
            $this->errors[] = '下载地址为空';
            return false;
        }

        $zipDir = dirname($this->zipPath);
        if (!is_dir($zipDir)) {
            @mkdir($zipDir, 0755, true);
        }

        // 使用 curl 下载（支持大文件、超时控制）
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            if ($ch === false) {
                $this->errors[] = '无法初始化下载';
                return false;
            }
            $fp = @fopen($this->zipPath, 'w');
            if (!$fp) {
                curl_close($ch);
                $this->errors[] = '无法创建下载文件';
                return false;
            }
            curl_setopt($ch, CURLOPT_FILE, $fp);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 300);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
            curl_setopt($ch, CURLOPT_USERAGENT, 'AppUpdater/1.0 (+' . GITHUB_REPO_OWNER . '/' . GITHUB_REPO_NAME . ')');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);

            if (!empty(getGithubToken())) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: token ' . getGithubToken()]);
            }

            $success = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            fclose($fp);

            if (!$success || $httpCode !== 200) {
                @unlink($this->zipPath);
                $this->errors[] = '下载失败 (HTTP ' . $httpCode . ')' . ($error ? ': ' . $error : '');
                return false;
            }
        } else {
            // 备用：file_get_contents
            $context = stream_context_create([
                'http' => [
                    'timeout' => 300,
                    'user_agent' => 'AppUpdater/1.0',
                    'header' => !empty(getGithubToken()) ? "Authorization: token " . getGithubToken() . "\r\n" : '',
                ],
                'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
            ]);
            $data = @file_get_contents($url, false, $context);
            if ($data === false) {
                $this->errors[] = '下载失败（无法读取远程文件）';
                return false;
            }
            file_put_contents($this->zipPath, $data);
        }

        // 检查文件大小
        $fileSize = @filesize($this->zipPath);
        if ($fileSize === false || $fileSize < 1024) {
            @unlink($this->zipPath);
            $this->errors[] = '下载的文件过小，可能损坏';
            return false;
        }
        if ($fileSize > UPDATE_MAX_ZIP_SIZE) {
            @unlink($this->zipPath);
            $this->errors[] = '下载的文件过大，超过 ' . round(UPDATE_MAX_ZIP_SIZE / 1024 / 1024, 1) . 'MB 限制';
            return false;
        }

        $this->log('下载完成: ' . round($fileSize / 1024, 2) . ' KB');
        return true;
    }

    /**
     * 校验 zip 文件（使用 release body 中的 SHA256/SHA1 校验和，如果存在）
     */
    private function verifyZipArchive()
    {
        // 1) 基本结构校验：能否被 ZipArchive 打开
        if (!class_exists('ZipArchive')) {
            $this->errors[] = 'ZipArchive 扩展不可用';
            return false;
        }
        $zip = new ZipArchive();
        if ($zip->open($this->zipPath) !== true) {
            $this->errors[] = '无法打开 zip 文件，文件可能已损坏';
            return false;
        }
        $zip->close();

        // 2) 如果 release body 中包含 "SHA256:" 或 "SHA1:" 形式的校验和，则校验
        if (!empty($this->releaseData['body'])) {
            $body = $this->releaseData['body'];
            $matches = [];
            if (preg_match('/(?:sha256|sha-256)\s*[:：]\s*([a-f0-9]{64})/i', $body, $matches)) {
                $expectedHash = strtolower(trim($matches[1]));
                $actualHash = hash_file('sha256', $this->zipPath);
                if ($actualHash !== $expectedHash) {
                    $this->errors[] = 'SHA256 校验失败：文件可能被篡改';
                    return false;
                }
                $this->log('SHA256 校验通过');
            } elseif (preg_match('/(?:sha1|sha-1)\s*[:：]\s*([a-f0-9]{40})/i', $body, $matches)) {
                $expectedHash = strtolower(trim($matches[1]));
                $actualHash = hash_file('sha1', $this->zipPath);
                if ($actualHash !== $expectedHash) {
                    $this->errors[] = 'SHA1 校验失败：文件可能被篡改';
                    return false;
                }
                $this->log('SHA1 校验通过');
            } else {
                $this->log('未在 release notes 中找到显式校验和，跳过哈希校验');
                $this->warnings[] = '发布说明中未包含 SHA256/SHA1 校验和，本次已跳过哈希校验。建议发布者在 release notes 中补充，以确保更新包完整性。';
            }
        }

        return true;
    }

    // ============================================================
    // 4. 备份当前版本
    // ============================================================

    private function backupCurrentVersion()
    {
        if (!is_dir(UPDATE_BACKUP_DIR)) {
            @mkdir(UPDATE_BACKUP_DIR, 0755, true);
        }

        $this->backupPath = UPDATE_BACKUP_DIR . '/backup_v' . str_replace('/', '_', $this->currentVersion) . '_' . date('Ymd_His') . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($this->backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $this->errors[] = '无法创建备份文件';
            return false;
        }

        $rootPath = realpath(dirname(__DIR__));
        $protected = unserialize(UPDATE_PROTECTED_PATHS);

        // 收集要备份的文件
        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($rootPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $fileCount = 0;
        foreach ($files as $file) {
            if ($file->isDir()) continue;

            $realPath = $file->getRealPath();
            $relativePath = substr($realPath, strlen($rootPath) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);

            // 跳过受保护的目录/文件（避免备份巨大的缓存、数据文件）
            $skip = false;
            foreach ($protected as $pattern) {
                if (empty($pattern)) continue;
                if ($relativePath === rtrim($pattern, '/') ||
                    str_starts_with_custom($relativePath, $pattern)) {
                    $skip = true;
                    break;
                }
            }
            if ($skip) continue;

            // 不备份自己（更新包、临时文件）
            if (str_starts_with_custom($relativePath, 'update/') && basename($realPath) === 'release.zip') continue;

            $zip->addFile($realPath, $relativePath);
            $fileCount++;
        }

        // 同时备份数据库文件（单独记录）
        if (file_exists(DB_FILE)) {
            $zip->addFile(DB_FILE, 'data/app.db');
            $fileCount++;
        }

        $zip->close();

        if (!file_exists($this->backupPath) || filesize($this->backupPath) < 1024) {
            @unlink($this->backupPath);
            $this->errors[] = '备份文件生成失败或过小';
            return false;
        }

        $this->log('备份完成: ' . $fileCount . ' 个文件，大小 ' . round(filesize($this->backupPath) / 1024, 2) . ' KB');
        $this->pruneBackups(5);
        return true;
    }

    // 备份保留策略：仅保留最近 N 份备份，防止磁盘无限占用
    private function pruneBackups($keep = 5)
    {
        $files = glob(UPDATE_BACKUP_DIR . '/backup_*.zip');
        if ($files === false || count($files) <= $keep) {
            return;
        }
        rsort($files); // 最新的在前
        $toDelete = array_slice($files, $keep);
        foreach ($toDelete as $f) {
            if (@unlink($f)) {
                $this->log('已清理旧备份: ' . basename($f));
            }
        }
    }

    // ============================================================
    // 5. 解压 + 安全扫描（防止路径穿越和恶意文件）
    // ============================================================

    private function extractAndScan()
    {
        if (!is_dir($this->extractDir)) {
            @mkdir($this->extractDir, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($this->zipPath) !== true) {
            $this->errors[] = '无法打开更新包';
            return false;
        }

        $totalFiles = $zip->numFiles;
        $extractedCount = 0;
        $skippedCount = 0;

        // 结构探测：判断是否需要剥离 zip 顶层目录（支持任意命名的仓库根目录）
        $this->detectZipStructure($zip);

        // 先扫描所有文件，发现路径穿越则整体失败
        $suspiciousFiles = [];
        $disallowedFiles = [];
        $protectedFiles = [];

        for ($i = 0; $i < $totalFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) continue;

            $name = $stat['name'];
            // 1) 路径穿越检测
            if (strpos($name, '../') !== false || strpos($name, '..\\') !== false) {
                $suspiciousFiles[] = $name;
                continue;
            }

            // 2) 规范化路径（去掉 ZIP 根目录前缀，GitHub 源码包通常包含一个如 repo-xxxx/ 的根）
            $cleanName = $this->normalizeZipEntryPath($name);
            if ($cleanName === null) continue; // 仅为目录，跳过

            // 3) 受保护的路径不应覆盖
            if (isPathProtected($cleanName)) {
                $protectedFiles[] = $cleanName;
                continue;
            }

            // 4) 扩展名白名单
            if (!isExtensionAllowed($cleanName)) {
                $disallowedFiles[] = $cleanName;
                continue;
            }

            $extractedCount++;
        }

        if (!empty($suspiciousFiles)) {
            $zip->close();
            $this->errors[] = '发现路径穿越攻击迹象: ' . implode(', ', array_slice($suspiciousFiles, 0, 3));
            return false;
        }

        // 开始实际解压
        for ($i = 0; $i < $totalFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) continue;

            $name = $stat['name'];
            if (strpos($name, '../') !== false || strpos($name, '..\\') !== false) continue;

            $cleanName = $this->normalizeZipEntryPath($name);
            if ($cleanName === null) continue;
            if (isPathProtected($cleanName)) {
                $skippedCount++;
                continue;
            }
            if (!isExtensionAllowed($cleanName)) {
                $skippedCount++;
                continue;
            }

            $destPath = $this->extractDir . '/' . $cleanName;
            $destDir = dirname($destPath);
            if (!is_dir($destDir)) {
                @mkdir($destDir, 0755, true);
            }

            $content = $zip->getFromIndex($i);
            if ($content !== false) {
                file_put_contents($destPath, $content);
            }
        }

        $zip->close();
        $this->log('扫描/解压完成: ' . $extractedCount . ' 个文件，跳过 ' . $skippedCount . ' 个（受保护/不允许）');

        if ($extractedCount === 0) {
            $this->errors[] = '更新包中没有可应用的文件';
            return false;
        }

        return true;
    }

    /**
     * 结构探测：若 zip 顶层恰好只有一个目录且内含项目特征文件（如 config.php/README.md），
     * 则判定为 GitHub 源码包，统一剥离该顶层目录。支持任意命名的仓库根目录（v3.2.0/、release/ 等）。
     */
    private function detectZipStructure($zip)
    {
        $topDirs = [];
        $topFiles = [];

        for ($i = 0; $i < $zip->numFiles; $i++) {
            $stat = $zip->statIndex($i);
            if ($stat === false) continue;
            $name = str_replace('\\', '/', $stat['name']);
            $name = preg_replace('#^\./+#', '', $name);
            $name = ltrim($name, '/');
            if (empty($name)) continue;

            $slash = strpos($name, '/');
            if ($slash === false) {
                $topFiles[$name] = true; // 顶层文件
            } else {
                $topDirs[substr($name, 0, $slash)] = true;
            }
        }

        // 恰好一个顶层目录且没有顶层文件时，检查其内部是否包含项目特征文件
        if (count($topDirs) === 1 && empty($topFiles)) {
            $top = array_keys($topDirs)[0];
            $signatureFiles = ['config.php', 'api.php', 'index.php', 'pc.php', 'pe.php', 'README.md'];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $stat = $zip->statIndex($i);
                if ($stat === false) continue;
                $name = str_replace('\\', '/', $stat['name']);
                $name = preg_replace('#^\./+#', '', $name);
                $name = ltrim($name, '/');
                if (strpos($name, $top . '/') !== 0) continue;
                $rest = substr($name, strlen($top) + 1);
                if (in_array($rest, $signatureFiles)) {
                    $this->stripTopDir = true;
                    $this->log('检测到源码包根目录: ' . $top . '/，将统一剥离');
                    return;
                }
            }
        }
    }

    /**
     * 去除 GitHub 源码压缩包中的顶层目录并返回相对项目根的路径
     */
    private function normalizeZipEntryPath($entryName)
    {
        $entryName = str_replace('\\', '/', $entryName);
        // 仅去除开头的 ./,避免吞掉 .git 等点前缀路径
        $entryName = preg_replace('#^\./+#', '', $entryName);
        $entryName = ltrim($entryName, '/');

        if (empty($entryName) || substr($entryName, -1) === '/') {
            return null; // 纯目录
        }

        // 结构探测结果：统一剥离唯一顶层目录
        if ($this->stripTopDir) {
            $firstSlash = strpos($entryName, '/');
            return $firstSlash === false ? $entryName : substr($entryName, $firstSlash + 1);
        }

        // 兼容旧逻辑：仓库名前缀剥壳（如 img-api-abc123/）
        $firstSlash = strpos($entryName, '/');
        if ($firstSlash !== false) {
            $topDir = substr($entryName, 0, $firstSlash);
            // 仅当顶层目录以仓库名开头且带有后缀（如 img-api-abc123）时视为包根目录去除，
            // 避免误剥更新包中真实存在的顶层目录（如 assets/、v3.2.0/ 等）
            $repoLower = strtolower(GITHUB_REPO_NAME);
            if (strlen($topDir) > strlen(GITHUB_REPO_NAME) && str_starts_with_custom(strtolower($topDir), $repoLower)) {
                return substr($entryName, $firstSlash + 1);
            }
        }
        return $entryName;
    }

    // ============================================================
    // 6. 安全替换文件（先写入临时，再 rename 实现原子替换）
    // ============================================================

    private function replaceFiles()
    {
        $rootPath = realpath(dirname(__DIR__));
        $extractPath = realpath($this->extractDir);

        if ($extractPath === false) {
            $this->errors[] = '解压目录不存在';
            return false;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($extractPath, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $replaced = 0;
        $added = 0;
        $errors = [];

        foreach ($files as $file) {
            if ($file->isDir()) continue;

            $realPath = $file->getRealPath();
            $relativePath = substr($realPath, strlen($extractPath) + 1);
            $relativePath = str_replace('\\', '/', $relativePath);

            if (isPathProtected($relativePath)) {
                continue; // 双重检查
            }
            if (!isExtensionAllowed($relativePath)) {
                continue;
            }

            $targetPath = $rootPath . '/' . $relativePath;
            $targetDir = dirname($targetPath);

            if (!is_dir($targetDir)) {
                if (!@mkdir($targetDir, 0755, true)) {
                    $errors[] = '无法创建目录: ' . $relativePath;
                    continue;
                }
            }

            $isNew = !file_exists($targetPath);

            // 原子替换：先写入 .tmp 再 rename
            $tmpPath = $targetPath . '.tmp_' . uniqid();
            if (!@copy($realPath, $tmpPath)) {
                $errors[] = '写入失败: ' . $relativePath;
                continue;
            }

            if (!@rename($tmpPath, $targetPath)) {
                @unlink($tmpPath);
                $errors[] = '替换失败: ' . $relativePath;
                continue;
            }

            @chmod($targetPath, 0644);
            if ($isNew) $added++; else $replaced++;
        }

        if ($replaced === 0 && $added === 0) {
            $this->errors[] = '没有任何文件被更新';
            return false;
        }

        $this->log('文件更新完成: 替换 ' . $replaced . ' 个，新增 ' . $added . ' 个');
        if (!empty($errors)) {
            $this->log('[部分失败] ' . count($errors) . ' 个文件: ' . implode('；', array_slice($errors, 0, 3)));
        }

        return true;
    }

    // ============================================================
    // 7. 数据库迁移
    // ============================================================

    private function runMigrations()
    {
        $migrationFile = $this->extractDir . '/update/migrations.php';
        if (!file_exists($migrationFile)) {
            // 新版本不带迁移脚本，仅更新代码
            $this->log('无数据库迁移脚本');
            return ['success' => true, 'messages' => []];
        }

        // 加载迁移脚本并执行
        try {
            $result = include $migrationFile;
            if (is_array($result) && isset($result['success'])) {
                if (!empty($result['messages'])) {
                    foreach ($result['messages'] as $msg) {
                        $this->log('[迁移] ' . $msg);
                    }
                }
                return $result;
            }
            return ['success' => true, 'messages' => ['迁移脚本执行完成']];
        } catch (Exception $e) {
            return ['success' => false, 'messages' => [$e->getMessage()]];
        }
    }

    // ============================================================
    // 8. 更新完成
    // ============================================================

    private function finalizeUpdate()
    {
        // 更新版本文件
        setAppVersion($this->latestVersion);

        // 更新 config.php 中的 APP_VERSION（如果可行）
        $this->updateVersionInConfig($this->latestVersion);

        // 清理所有缓存
        $this->cleanup();
        clearUpdateCheckCache();
    }

    /**
     * 尝试更新 config.php 中的 APP_VERSION 常量
     */
    private function updateVersionInConfig($newVersion)
    {
        $configPath = dirname(__DIR__) . '/config.php';
        if (!is_writable($configPath)) {
            $this->log('config.php 不可写，版本号已写入 app_version.txt');
            return;
        }
        $content = file_get_contents($configPath);
        $newContent = preg_replace(
            "/define\s*\(\s*['\"]APP_VERSION['\"]\s*,\s*['\"][^'\"]+['\"]\s*\)/",
            "define('APP_VERSION', '" . $newVersion . "')",
            $content,
            1
        );
        if ($newContent !== null && $newContent !== $content) {
            file_put_contents($configPath, $newContent);
            $this->log('已更新 config.php 中的版本号为 ' . $newVersion);
        }
    }

    // ============================================================
    // 9. 回滚机制
    // ============================================================

    /**
     * 使用备份文件回滚到更新前状态
     */
    /**
     * 受控恢复备份 zip：逐条目校验路径、跳过受保护路径，默认不覆盖数据库
     * @param string $zipPath 备份文件路径
     * @param bool $restoreDatabase 是否同时恢复数据库（默认 false，避免丢失新数据）
     * @return int 成功恢复的文件数
     */
    private function extractZipSafely($zipPath, $restoreDatabase = false)
    {
        if (!file_exists($zipPath)) {
            return 0;
        }
        $rootPath = realpath(dirname(__DIR__));

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return 0;
        }

        $success = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $name = $zip->getNameIndex($i);
            if ($name === false || $name === '') continue;
            $name = str_replace('\\', '/', $name);
            if (substr($name, -1) === '/' || empty($name)) continue;

            // 路径穿越防御
            if (strpos($name, '../') !== false || strpos($name, '..\\') !== false) {
                continue;
            }
            // 仅去除开头的 ./,避免吞掉点前缀路径
            $clean = preg_replace('#^\./+#', '', $name);
            $clean = ltrim($clean, '/');
            if (empty($clean)) continue;

            // 受保护路径（data/、admin/logs/、缓存等）不参与回滚
            if (isPathProtected($clean)) {
                continue;
            }
            // 默认不恢复数据库，避免回滚后新增的数据丢失
            if (!$restoreDatabase && $clean === 'data/app.db') {
                continue;
            }

            $target = $rootPath . '/' . $clean;
            $dir = dirname($target);
            if (!is_dir($dir)) @mkdir($dir, 0755, true);
            if ($zip->extractTo($dir, [$name])) {
                $success++;
            }
        }
        $zip->close();
        return $success;
    }

    /**
     * 自动回滚（更新失败时调用）：恢复代码文件，跳过数据库与受保护路径
     */
    public function rollback()
    {
        $this->log('开始回滚...');
        if (empty($this->backupPath) || !file_exists($this->backupPath)) {
            $this->errors[] = '没有可用的备份文件，无法回滚';
            return false;
        }

        $restored = $this->extractZipSafely($this->backupPath, false);
        $this->log('回滚完成: 恢复 ' . $restored . ' 个文件（跳过数据库与受保护路径）');
        logUpdateAction($this->latestVersion ?? $this->currentVersion, $this->currentVersion, 'rollback', '更新失败，自动回滚', $this->backupPath);
        return $restored > 0;
    }

    /**
     * 手动回滚：通过指定备份文件路径（受控恢复，不覆盖数据库）
     */
    public static function rollbackFromBackup($backupPath)
    {
        if (!file_exists($backupPath)) {
            return false;
        }
        $updater = new AppUpdater();
        return $updater->extractZipSafely($backupPath, false) > 0;
    }

    // ============================================================
    // 10. 清理
    // ============================================================

    private function cleanup()
    {
        if (file_exists($this->zipPath)) @unlink($this->zipPath);
        if (is_dir($this->extractDir)) removeDirectory($this->extractDir);
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    // ============================================================
    // 工具方法
    // ============================================================

    public function getLogs()
    {
        return $this->logs;
    }

    public function getWarnings()
    {
        return $this->warnings;
    }

    public function getErrors()
    {
        return $this->errors;
    }

    public function getBackupPath()
    {
        return $this->backupPath;
    }

    private function log($msg)
    {
        $this->logs[] = '[' . date('H:i:s') . '] ' . $msg;
    }

    private function httpGet($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_USERAGENT, 'AppUpdater/1.0');
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
            if (!empty(getGithubToken())) {
                curl_setopt($ch, CURLOPT_HTTPHEADER, ['Authorization: token ' . getGithubToken()]);
            }
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);
            if ($httpCode !== 200) {
                $this->errors[] = 'GitHub API 请求失败 (HTTP ' . $httpCode . ')' . ($error ? ': ' . $error : '');
                return false;
            }
            return $response;
        }

        // file_get_contents 备用
        $context = stream_context_create([
            'http' => [
                'timeout' => 30,
                'user_agent' => 'AppUpdater/1.0',
                'header' => !empty(GITHUB_TOKEN) ? "Authorization: token " . GITHUB_TOKEN . "\r\n" : '',
            ],
            'ssl' => ['verify_peer' => true, 'verify_peer_name' => true],
        ]);
        $data = @file_get_contents($url, false, $context);
        if ($data === false) {
            $this->errors[] = '无法访问 GitHub API';
            return false;
        }
        return $data;
    }
}
