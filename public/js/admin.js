// ============================================
// 非阻塞 Toast 通知
// ============================================
function escapeHtml(s) {
    var div = document.createElement('div');
    div.textContent = (s == null) ? '' : String(s);
    return div.innerHTML;
}

function showToast(message, type) {
    type = type || 'success';
    var container = document.getElementById('appToasts');
    if (!container) return;
    var icons = { success: 'fa-check-circle', info: 'fa-info-circle', warning: 'fa-exclamation-triangle', error: 'fa-exclamation-circle' };
    var t = document.createElement('div');
    t.className = 'app-toast app-toast-' + type;
    t.innerHTML = '<i class="fas ' + (icons[type] || icons.info) + '"></i><span>' + escapeHtml(message) + '</span>' +
                  '<button type="button" class="app-toast-close" aria-label="关闭">&times;</button>';
    container.appendChild(t);
    var hide = function() {
        if (!t.parentNode) return;
        t.classList.add('app-toast-hide');
        setTimeout(function() { if (t.parentNode) t.parentNode.removeChild(t); }, 300);
    };
    t.querySelector('.app-toast-close').addEventListener('click', hide);
    setTimeout(hide, 4000);
}

// ============================================
// 通用确认弹窗（Promise 化）
//   confirmDialog({title, message, confirmText, danger}).then(function(ok){ ... })
// ============================================
var confirmResolve = null;

function confirmDialog(options) {
    options = options || {};
    document.getElementById('confirmTitle').textContent = options.title || '确认操作';
    document.getElementById('confirmMessage').textContent = options.message || '确定要执行此操作吗？';
    var yesBtn = document.getElementById('confirmModalYes');
    yesBtn.textContent = options.confirmText || '确定';
    yesBtn.className = 'btn ' + (options.danger ? 'btn-danger' : 'btn-primary');
    return new Promise(function(resolve) {
        confirmResolve = resolve;
        $('#confirmModal').modal('show');
    });
}

function resolveConfirm(result) {
    var fn = confirmResolve;
    confirmResolve = null;
    $('#confirmModal').modal('hide');
    if (fn) fn(result);
}

// ============================================
// 图片删除：选中状态与通用确认
// ============================================
var selectedUrls = [];

function toggleSelectAll() {
    var selectAll = document.getElementById('selectAll');
    var checkboxes = document.querySelectorAll('.url-checkbox');
    checkboxes.forEach(function(cb) {
        cb.checked = selectAll.checked;
    });
    updateDeleteButton();
}

function updateDeleteButton() {
    var checkboxes = document.querySelectorAll('.url-checkbox');
    selectedUrls = [];
    checkboxes.forEach(function(cb) {
        if (cb.checked) {
            selectedUrls.push(cb.value);
        }
    });
    var deleteBtn = document.getElementById('deleteSelectedBtn');
    if (selectedUrls.length > 0) {
        deleteBtn.style.display = 'inline-block';
    } else {
        deleteBtn.style.display = 'none';
    }
}

function resetDeleteSelection() {
    selectedUrls = [];
    var deleteBtn = document.getElementById('deleteSelectedBtn');
    if (deleteBtn) deleteBtn.style.display = 'none';
    var checkboxes = document.querySelectorAll('.url-checkbox');
    checkboxes.forEach(function(cb) { cb.checked = false; });
    var selectAll = document.getElementById('selectAll');
    if (selectAll) selectAll.checked = false;
}

function submitDeleteForm(url, type, token) {
    var form = document.createElement('form');
    form.method = 'post';
    form.action = '?section=management&type=' + encodeURIComponent(type);

    var csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = 'csrf_token';
    csrfInput.value = token;
    form.appendChild(csrfInput);

    if (url === 'MULTI_DELETE') {
        selectedUrls.forEach(function(u) {
            var input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'delete_urls[]';
            input.value = u;
            form.appendChild(input);
        });
    } else {
        // 单条删除同样使用 POST 表单提交（避免 GET 副作用与 token 泄露）
        var urlInput = document.createElement('input');
        urlInput.type = 'hidden';
        urlInput.name = 'delete_url';
        urlInput.value = url;
        form.appendChild(urlInput);
    }

    document.body.appendChild(form);
    form.submit();
}

function showDeleteConfirm(url, type, token) {
    confirmDialog({
        title: '确认删除',
        message: '确定要删除这个图片链接吗？此操作不可撤销。',
        confirmText: '确定删除',
        danger: true
    }).then(function(ok) {
        if (ok) submitDeleteForm(url, type, token);
        else resetDeleteSelection();
    });
}

function deleteSelected(type, token) {
    if (selectedUrls.length === 0) return;
    confirmDialog({
        title: '批量删除',
        message: '确定要删除选中的 ' + selectedUrls.length + ' 个图片链接吗？此操作不可撤销。',
        confirmText: '确定删除',
        danger: true
    }).then(function(ok) {
        if (ok) submitDeleteForm('MULTI_DELETE', type, token);
        else resetDeleteSelection();
    });
}

// ============================================
// 系统更新相关 JavaScript（全局函数定义）
// ============================================
// csrf/version/section 由 dashboard.php 以 APP 对象注入
var updateCsrfToken = APP.csrf;
var currentVersion = APP.version;

function setUpdateStatus(message, type) {
    var box = document.getElementById('updateStatus');
    if (!box) return;
    var iconClass = 'fas fa-info-circle';
    var alertClass = 'alert alert-info';
    if (type === 'success') { alertClass = 'alert alert-success'; iconClass = 'fas fa-check-circle'; }
    else if (type === 'error') { alertClass = 'alert alert-danger'; iconClass = 'fas fa-exclamation-triangle'; }
    else if (type === 'warning') { alertClass = 'alert alert-warning'; iconClass = 'fas fa-exclamation-circle'; }
    box.className = alertClass;
    // message 可能来自 GitHub API（如 tag_name）、错误信息等，统一转义防 XSS
    box.innerHTML = '<i class="icon ' + iconClass + '"></i> ' + escapeHtml(message);
}

function appendUpdateLog(line) {
    var logBox = document.getElementById('updateLog');
    if (logBox) {
        logBox.textContent += line + '\n';
        logBox.scrollTop = logBox.scrollHeight;
    }
}

// 前端版本检查缓存配置（5 分钟内不重复请求，避免频繁调用 GitHub API）
var UPDATE_CHECK_LOCAL_CACHE_TTL = 5 * 60 * 1000;
var UPDATE_CHECK_LOCAL_CACHE_KEY = 'app_update_check_cache_v1';

// 渲染版本检查结果到页面（被 checkUpdate 和本地缓存共用）
function renderUpdateResult(data, fromCache, cacheTime) {
    var latestText = document.getElementById('latestVersionText');
    if (!data.success) {
        latestText.textContent = '未知';
        setUpdateStatus('检查失败: ' + (data.error || (data.errors && data.errors.join('; ')) || '未知错误'), 'error');
        return;
    }
    var latest = data.latest;
    latestText.textContent = latest;

    // 环境警告
    if (data.env && !data.env.ok) {
        var html = '<div class="alert alert-danger">';
        html += '<i class="icon fas fa-exclamation-triangle"></i> 环境不满足更新要求:<ul class="mt-2">';
        (data.env.errors || []).forEach(function (m) { html += '<li>' + m + '</li>'; });
        html += '</ul></div>';
        document.getElementById('envWarningBox').innerHTML = html;
    } else if (data.env && data.env.warnings && data.env.warnings.length > 0) {
        var whtml = '<div class="alert alert-warning">';
        whtml += '<i class="icon fas fa-exclamation"></i> 警告:<ul class="mt-2">';
        (data.env.warnings || []).forEach(function (m) { whtml += '<li>' + m + '</li>'; });
        whtml += '</ul></div>';
        document.getElementById('envWarningBox').innerHTML = whtml;
    }

    if (data.has_update) {
        var cacheHint = fromCache && cacheTime ? '（数据更新于 ' + cacheTime + '，5 分钟内自动使用本地缓存，点击右上角按钮可强制重新检查）' : '';
        setUpdateStatus(
            '发现新版本 <strong>' + latest + '</strong>（当前版本 ' + data.current + '）。建议立即更新。' + cacheHint,
            'success'
        );
        document.getElementById('updateActionBox').style.display = 'block';
        if (data.release) {
            document.getElementById('releaseName').textContent = data.release.name || latest;
            document.getElementById('releaseDate').textContent = data.release.published_at ? '  (' + data.release.published_at + ')' : '';
            document.getElementById('releaseUrl').href = data.release.html_url || '#';
            document.getElementById('releaseBody').textContent = data.release.body || '无发布说明';
            document.getElementById('releaseInfoBox').style.display = 'block';
        }
    } else {
        var cacheHint = fromCache && cacheTime ? '（数据更新于 ' + cacheTime + '，5 分钟内自动使用本地缓存，点击右上角按钮可强制重新检查）' : '';
        setUpdateStatus('当前已是最新版本（' + data.current + '）' + cacheHint, 'info');
        document.getElementById('latestVersionText').textContent = '已是最新';
    }
}

function checkUpdate(force) {
    var latestText = document.getElementById('latestVersionText');
    if (latestText) latestText.textContent = '检查中...';
    setUpdateStatus('正在检查 GitHub 最新版本...', 'info');
    document.getElementById('updateActionBox').style.display = 'none';
    document.getElementById('releaseInfoBox').style.display = 'none';
    document.getElementById('envWarningBox').innerHTML = '';

    // 非强制模式下优先使用前端 localStorage 缓存（5 分钟内避免频繁请求）
    if (!force) {
        try {
            var rawCache = localStorage.getItem(UPDATE_CHECK_LOCAL_CACHE_KEY);
            if (rawCache) {
                var cached = JSON.parse(rawCache);
                var age = Date.now() - (cached.timestamp || 0);
                if (cached.data && cached.data.success && age < UPDATE_CHECK_LOCAL_CACHE_TTL) {
                    var cacheTimeStr = new Date(cached.timestamp).toLocaleString();
                    renderUpdateResult(cached.data, true, cacheTimeStr);
                    return;
                }
                // 缓存过期，清理
                localStorage.removeItem(UPDATE_CHECK_LOCAL_CACHE_KEY);
            }
        } catch (e) {
            // localStorage 不可用，走正常请求
        }
    }

    var url = 'update.php?action=check' + (force ? '&force=1' : '');
    fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.success) {
                latestText.textContent = '未知';
                setUpdateStatus('检查失败: ' + (data.error || (data.errors && data.errors.join('; ')) || '未知错误'), 'error');
                return;
            }
            // 写入前端缓存（仅保存成功的响应，避免缓存错误）
            try {
                localStorage.setItem(UPDATE_CHECK_LOCAL_CACHE_KEY, JSON.stringify({
                    timestamp: Date.now(),
                    data: data,
                }));
            } catch (e) {}
            renderUpdateResult(data, false, null);
        })
        .catch(function(err) {
            document.getElementById('latestVersionText').textContent = '失败';
            setUpdateStatus('网络请求失败，请检查网络或稍后再试', 'error');
        });
}

function doUpdate() {
    confirmDialog({
        title: '确认更新',
        message: '确定要执行自动更新吗？此操作将下载并覆盖项目文件。更新过程中请不要关闭页面。',
        confirmText: '立即更新',
        danger: false
    }).then(function(ok) { if (ok) startUpdate(); });
}

function startUpdate() {
    var btn = document.getElementById('updateBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 更新中...';
    document.getElementById('progressBar').style.display = 'block';
    document.getElementById('updateLogBox').style.display = 'block';
    document.getElementById('updateLog').textContent = '';
    setUpdateStatus('正在执行更新，这可能需要几分钟时间...', 'info');
    appendUpdateLog('[开始] 发起更新请求...');

    var formData = new FormData();
    formData.append('action', 'update');
    formData.append('csrf_token', updateCsrfToken);

    fetch('update.php?action=update', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.logs && Array.isArray(data.logs)) {
            data.logs.forEach(function(line) { appendUpdateLog(line); });
        }
        if (data.success) {
            // 更新成功，清理前端缓存，确保下次进入页面获取最新版本信息
            try { localStorage.removeItem(UPDATE_CHECK_LOCAL_CACHE_KEY); } catch (e) {}
            setUpdateStatus('更新成功！当前版本已升级到 ' + (data.to_version || '最新版本') + '。请刷新页面确认。', 'success');
            appendUpdateLog('[完成] 更新成功！');
            btn.innerHTML = '<i class="fas fa-check"></i> 更新成功';
            btn.className = 'btn btn-lg btn-success';
            // 3秒后自动刷新
            setTimeout(function() { location.reload(); }, 3000);
        } else {
            var msg = data.error || (data.errors && data.errors.join('；')) || '更新失败';
            setUpdateStatus('更新失败: ' + msg, 'error');
            appendUpdateLog('[失败] ' + msg);
            btn.disabled = false;
            btn.innerHTML = '<i class="fas fa-download"></i> 重试更新';
            btn.className = 'btn btn-lg btn-success';
        }
        document.getElementById('progressBar').style.display = 'none';
        loadBackupList();
        loadUpdateHistory();
    })
    .catch(function(err) {
        setUpdateStatus('更新请求失败，请检查服务器日志', 'error');
        document.getElementById('progressBar').style.display = 'none';
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-download"></i> 重新尝试';
    });
}

function loadBackupList() {
    fetch('update.php?action=backups', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var box = document.getElementById('backupList');
            if (!data.success || !data.backups || data.backups.length === 0) {
                box.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-inbox"></i> 暂无备份文件</div>';
                return;
            }
            var html = '<div class="table-responsive"><table class="table table-striped table-wrap-text"><thead><tr><th>文件名</th><th style="width:80px;">大小 (KB)</th><th style="width:110px;">创建时间</th><th style="width:130px;">操作</th></tr></thead><tbody>';
            data.backups.forEach(function(b) {
                html += '<tr>';
                // filename/size/time 均来自服务端备份文件名，统一转义；按钮通过 data-* + 事件委托传参，
                // 避免内联 onclick 字符串拼接造成的 XSS 与 JS 语法破坏
                html += '<td>' + escapeHtml(b.filename) + '</td>';
                html += '<td>' + escapeHtml(b.size) + ' KB</td>';
                html += '<td>' + escapeHtml(b.time) + '</td>';
                html += '<td class="nowrap">';
                html += '<button type="button" class="btn btn-sm btn-warning mr-1" data-action="rollback" data-file="' + encodeURIComponent(String(b.filename)) + '">';
                html += '<i class="fas fa-undo"></i> 恢复</button>';
                html += '<button type="button" class="btn btn-sm btn-danger" data-action="delete" data-file="' + encodeURIComponent(String(b.filename)) + '">';
                html += '<i class="fas fa-trash"></i> 删除</button>';
                html += '</td></tr>';
            });
            html += '</tbody></table></div>';
            box.innerHTML = html;
            bindBackupActions(box);
        })
        .catch(function() {
            document.getElementById('backupList').innerHTML = '<div class="text-danger">加载失败</div>';
        });
}

// 备份列表按钮事件委托（替代内联 onclick，杜绝文件名拼接进 JS 字符串）
function bindBackupActions(container) {
    container.onclick = function(e) {
        var target = e.target;
        var btn = target && target.closest ? target.closest('button[data-action]') : null;
        if (!btn) return;
        var action = btn.getAttribute('data-action');
        var filename = decodeURIComponent(btn.getAttribute('data-file') || '');
        if (action === 'rollback') doRollback(filename);
        else if (action === 'delete') deleteBackup(filename);
    };
}

function loadUpdateHistory() {
    fetch('update.php?action=logs', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var box = document.getElementById('updateHistoryList');
            if (!data.success || !data.logs || data.logs.length === 0) {
                box.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-inbox"></i> 暂无更新记录</div>';
                return;
            }
            var html = '<div class="table-responsive"><table class="table table-striped table-wrap-text"><thead><tr><th>时间</th><th>从版本</th><th>到版本</th><th>状态</th><th>操作人</th><th>说明</th></tr></thead><tbody>';
            data.logs.forEach(function(log) {
                var statusClass = 'badge-info';
                var statusText = log.status;
                if (log.status === 'success') { statusClass = 'badge-success'; statusText = '成功'; }
                else if (log.status === 'failed') { statusClass = 'badge-danger'; statusText = '失败'; }
                else if (log.status === 'rollback') { statusClass = 'badge-warning'; statusText = '回滚'; }
                html += '<tr>';
                // 数据库中 username/message 等一律转义，防存储型 XSS
                html += '<td>' + escapeHtml(log.timestamp || '-') + '</td>';
                html += '<td>' + escapeHtml(log.from_version || '-') + '</td>';
                html += '<td>' + escapeHtml(log.to_version || '-') + '</td>';
                html += '<td><span class="badge ' + statusClass + '">' + escapeHtml(statusText) + '</span></td>';
                html += '<td>' + escapeHtml(log.username || '-') + '</td>';
                html += '<td>' + escapeHtml(log.message || '-') + '</td>';
                html += '</tr>';
            });
            html += '</tbody></table></div>';
            box.innerHTML = html;
        })
        .catch(function() {
            document.getElementById('updateHistoryList').innerHTML = '<div class="text-danger">加载失败</div>';
        });
}

function doRollback(filename) {
    confirmDialog({
        title: '恢复备份',
        message: '确定要从备份文件恢复吗？这将覆盖当前所有文件。此操作不可撤销。',
        confirmText: '确认恢复',
        danger: true
    }).then(function(ok) { if (!ok) return; startRollback(filename); });
}

function startRollback(filename) {
    var formData = new FormData();
    formData.append('action', 'rollback');
    formData.append('backup', filename);
    formData.append('csrf_token', updateCsrfToken);
    fetch('update.php?action=rollback', { method: 'POST', body: formData })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('回滚成功！即将刷新页面...', 'success');
                setTimeout(function() { location.reload(); }, 1500);
            } else {
                showToast('回滚失败: ' + (data.error || '未知错误'), 'error');
            }
        })
        .catch(function(err) {
            showToast('请求失败: ' + err, 'error');
        });
}

// 删除备份文件
function deleteBackup(filename) {
    confirmDialog({
        title: '删除备份',
        message: '确定要删除备份文件 "' + filename + '" 吗？此操作不可撤销。',
        confirmText: '确认删除',
        danger: true
    }).then(function(ok) {
        if (!ok) return;
        var formData = new FormData();
        formData.append('action', 'delete_backup');
        formData.append('backup', filename);
        formData.append('csrf_token', updateCsrfToken);

        fetch('update.php', {
            method: 'POST',
            body: formData
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) {
                showToast('备份文件已删除', 'success');
                loadBackupList();
            } else {
                showToast('删除失败: ' + (data.error || '未知错误'), 'error');
            }
        })
        .catch(function(err) {
            showToast('请求失败: ' + err, 'error');
        });
    });
}

// 加载 GitHub Token 状态
function loadGithubToken() {
    fetch('update.php?action=settings', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            var tokenInput = document.getElementById('githubTokenInput');
            var tokenStatus = document.getElementById('tokenStatus');
            var clearBtn = document.getElementById('clearTokenBtn');
            if (data.success) {
                if (data.has_token) {
                    tokenStatus.textContent = '当前已设置 Token: ' + data.github_token;
                    tokenStatus.className = 'form-text text-success';
                    clearBtn.style.display = 'inline-block';
                } else {
                    tokenStatus.textContent = '未设置 Token';
                    tokenStatus.className = 'form-text text-muted';
                    clearBtn.style.display = 'none';
                }
            }
        })
        .catch(function() {});
}

// 保存 GitHub Token
function saveGithubToken() {
    var token = document.getElementById('githubTokenInput').value.trim();
    var formData = new FormData();
    formData.append('action', 'save_token');
    formData.append('token', token);
    formData.append('csrf_token', updateCsrfToken);

    document.getElementById('saveTokenBtn').disabled = true;
    document.getElementById('saveTokenBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';

    fetch('update.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast(data.message, 'success');
            document.getElementById('githubTokenInput').value = '';
            loadGithubToken();
            // 清除前端缓存，下次检查会获取最新版本信息
            try { localStorage.removeItem(UPDATE_CHECK_LOCAL_CACHE_KEY); } catch (e) {}
        } else {
            showToast('保存失败: ' + (data.error || '未知错误'), 'error');
        }
    })
    .catch(function(err) {
        showToast('请求失败: ' + err, 'error');
    })
    .finally(function() {
        document.getElementById('saveTokenBtn').disabled = false;
        document.getElementById('saveTokenBtn').innerHTML = '<i class="fas fa-save"></i> 保存';
    });
}

// 清空 GitHub Token
function clearGithubToken() {
    confirmDialog({
        title: '清空 Token',
        message: '确定要清空 GitHub Token 吗？清空后将使用匿名方式访问 GitHub API。',
        confirmText: '确认清空',
        danger: true
    }).then(function(ok) {
        if (!ok) return;
        document.getElementById('githubTokenInput').value = '';
        saveGithubToken();
    });
}

// 切换 Token 显示/隐藏
function toggleTokenVisibility() {
    var input = document.getElementById('githubTokenInput');
    var btn = document.getElementById('toggleTokenBtn');
    if (input.type === 'password') {
        input.type = 'text';
        btn.innerHTML = '<i class="fas fa-eye-slash"></i>';
    } else {
        input.type = 'password';
        btn.innerHTML = '<i class="fas fa-eye"></i>';
    }
}

// 自动加载：进入更新页面后立即检查版本
document.addEventListener('DOMContentLoaded', function() {
    if (APP.section === 'update') {
        checkUpdate(false);
        loadBackupList();
        loadUpdateHistory();
        loadGithubToken();
    } else if (APP.section === 'site') {
        loadSiteSettings();
    }
});

// ============================================
// 网站设置：加载与保存
// ============================================
function loadSiteSettings() {
    fetch('update.php?action=get_site_settings', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            document.getElementById('site_title').value = data.site_title || '';
            document.getElementById('site_name').value = data.site_name || '';
            document.getElementById('site_lead').value = data.site_lead || '';
            document.getElementById('site_copyright').value = data.site_copyright || '';
            document.getElementById('site_icp').value = data.site_icp || '';
            document.getElementById('rate_limit_api').value = data.rate_limit_api || '';
            document.getElementById('rate_limit_admin').value = data.rate_limit_admin || '';
            document.getElementById('image_mode').value = data.image_mode || 'redirect';
            document.getElementById('enable_json').value = data.enable_json || '0';
            // 0 是合法值（表示禁用自动落库），不能用 || 兜底，否则会误显示为空
            var flushInterval = data.stats_auto_flush_interval;
            document.getElementById('stats_auto_flush_interval').value = (flushInterval === 0 || flushInterval === '0') ? '0' : (flushInterval || '');
        }
    })
    .catch(function() {});
}

var siteSettingsForm = document.getElementById('siteSettingsForm');
if (siteSettingsForm) {
    siteSettingsForm.addEventListener('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);
    formData.append('action', 'save_site_settings');
    formData.append('csrf_token', updateCsrfToken);

    var submitBtn = this.querySelector('button[type="submit"]');
    var originalHtml = submitBtn.innerHTML;
    submitBtn.disabled = true;
    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> 保存中...';

    fetch('update.php', {
        method: 'POST',
        body: formData
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.success) {
            showToast(data.message, 'success');
        } else {
            showToast('保存失败: ' + (data.error || '未知错误'), 'error');
        }
    })
    .catch(function(err) {
        showToast('请求失败: ' + err, 'error');
    })
    .finally(function() {
        submitBtn.disabled = false;
        submitBtn.innerHTML = originalHtml;
    });
});
}

// ============================================
// jQuery 模态框事件绑定
// ============================================
$(document).ready(function() {
    $('#confirmModalYes').on('click', function() {
        resolveConfirm(true);
    });

    $('#confirmModalCancel').on('click', function() {
        resolveConfirm(false);
    });

    $('#confirmModalClose').on('click', function() {
        resolveConfirm(false);
    });

    $('#confirmModal').on('hidden.bs.modal', function() {
        // 通过 ESC / 点击遮罩关闭而未显式确认时，统一按“取消”处理
        if (confirmResolve) {
            var fn = confirmResolve;
            confirmResolve = null;
            fn(false);
        }
    });
});