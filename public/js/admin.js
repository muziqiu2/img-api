// ============================================
// 非阻塞 Toast 通知
// ============================================
// 转义 HTML 特殊字符（含引号，可安全用于属性上下文：& < > " '）
function escapeHtml(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
        return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
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
        bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmModal')).show();
    });
}

function resolveConfirm(result) {
    var fn = confirmResolve;
    confirmResolve = null;
    bootstrap.Modal.getOrCreateInstance(document.getElementById('confirmModal')).hide();
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

// 图片删除事件委托：取代旧式内联 onclick（data-* 传参，与全站风格统一，
// 消除 URL/类型拼接进 JS 字符串的注入风险）。document 级委托一次绑定，分页重渲染也无需重新绑定。
document.addEventListener('click', function (e) {
    var target = e.target;
    var delBtn = target.closest ? target.closest('.btn-del-url') : null;
    if (delBtn) {
        e.preventDefault();
        // dataset 由浏览器自动解码 HTML 实体，URL 中的引号/& 等可安全传递
        showDeleteConfirm(delBtn.dataset.url, delBtn.dataset.type, APP.csrf);
        return;
    }
    var selBtn = target.closest ? target.closest('#deleteSelectedBtn') : null;
    if (selBtn) {
        deleteSelected(selBtn.dataset.type, APP.csrf);
    }
});

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
        (data.env.errors || []).forEach(function (m) { html += '<li>' + escapeHtml(m) + '</li>'; });
        html += '</ul></div>';
        document.getElementById('envWarningBox').innerHTML = html;
    } else if (data.env && data.env.warnings && data.env.warnings.length > 0) {
        var whtml = '<div class="alert alert-warning">';
        whtml += '<i class="icon fas fa-exclamation"></i> 警告:<ul class="mt-2">';
        (data.env.warnings || []).forEach(function (m) { whtml += '<li>' + escapeHtml(m) + '</li>'; });
        whtml += '</ul></div>';
        document.getElementById('envWarningBox').innerHTML = whtml;
    }

    if (data.has_update) {
        var cacheHint = fromCache && cacheTime ? '（数据更新于 ' + cacheTime + '，5 分钟内自动使用本地缓存，点击右上角按钮可强制重新检查）' : '';
        setUpdateStatus(
            '发现新版本 ' + latest + '（当前版本 ' + data.current + '）。建议立即更新。' + cacheHint,
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
                // 避免内联 onclick 字符串拼接造成的 XSS 与 JS 语法破坏。
                // 文件名列使用 backup-name 类（超长省略号 + title 提示），避免移动端长文件名换行撑乱表格
                html += '<td class="backup-name" title="' + escapeHtml(b.filename) + '">' + escapeHtml(b.filename) + '</td>';
                html += '<td>' + escapeHtml(b.size) + ' KB</td>';
                html += '<td>' + escapeHtml(b.time) + '</td>';
                html += '<td class="nowrap">';
                html += '<button type="button" class="btn btn-sm btn-warning me-1" data-action="rollback" data-file="' + encodeURIComponent(String(b.filename)) + '">';
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

// 更新历史：前端分页状态（数据由后端一次性返回，前端分页展示，避免记录多时页面过长）
var updateHistoryData = [];
var updateHistoryPage = 1;
var updateHistoryPageSize = 10;

function loadUpdateHistory() {
    fetch('update.php?action=logs', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            updateHistoryData = (data.success && data.logs) ? data.logs : [];
            updateHistoryPage = 1;
            renderUpdateHistory();
        })
        .catch(function() {
            document.getElementById('updateHistoryList').innerHTML = '<div class="text-danger">加载失败</div>';
        });
}

function renderUpdateHistory() {
    var box = document.getElementById('updateHistoryList');
    if (!updateHistoryData.length) {
        box.innerHTML = '<div class="text-center text-muted py-3"><i class="fas fa-inbox"></i> 暂无更新记录</div>';
        return;
    }
    var totalPages = Math.ceil(updateHistoryData.length / updateHistoryPageSize);
    if (updateHistoryPage < 1) updateHistoryPage = 1;
    if (updateHistoryPage > totalPages) updateHistoryPage = totalPages;
    var start = (updateHistoryPage - 1) * updateHistoryPageSize;
    var pageData = updateHistoryData.slice(start, start + updateHistoryPageSize);

    var html = '<div class="table-responsive"><table class="table table-striped table-wrap-text"><thead><tr><th>时间</th><th>从版本</th><th>到版本</th><th>状态</th><th>操作人</th><th>说明</th></tr></thead><tbody>';
    pageData.forEach(function(log) {
        var statusClass = 'text-bg-info';
        var statusText = log.status;
        if (log.status === 'success') { statusClass = 'text-bg-success'; statusText = '成功'; }
        else if (log.status === 'failed') { statusClass = 'text-bg-danger'; statusText = '失败'; }
        else if (log.status === 'rollback') { statusClass = 'text-bg-warning'; statusText = '回滚'; }
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

    // 分页控件（页码经 data-page 传递 + 事件委托，不再使用内联 onclick）
    html += '<nav class="mt-2"><ul class="pagination pagination-sm justify-content-end mb-0">';
    html += '<li class="page-item' + (updateHistoryPage <= 1 ? ' disabled' : '') + '">';
    html += '<a class="page-link" href="#" data-page="' + (updateHistoryPage - 1) + '" aria-label="上一页">&laquo;</a></li>';
    for (var i = 1; i <= totalPages; i++) {
        html += '<li class="page-item' + (i === updateHistoryPage ? ' active' : '') + '">';
        html += '<a class="page-link" href="#" data-page="' + i + '">' + i + '</a></li>';
    }
    html += '<li class="page-item' + (updateHistoryPage >= totalPages ? ' disabled' : '') + '">';
    html += '<a class="page-link" href="#" data-page="' + (updateHistoryPage + 1) + '" aria-label="下一页">&raquo;</a></li>';
    html += '</ul></nav>';

    box.innerHTML = html;
}

// 更新历史分页点击：事件委托（与文件内其它按钮的 data-* 委托风格统一，
// 页码为纯整数，分页重渲染后无需重新绑定）
document.addEventListener('click', function (e) {
    var link = e.target && e.target.closest ? e.target.closest('#updateHistoryList a[data-page]') : null;
    if (!link) return;
    e.preventDefault();
    updateHistoryGo(parseInt(link.getAttribute('data-page'), 10));
});

function updateHistoryGo(page) {
    var totalPages = Math.ceil(updateHistoryData.length / updateHistoryPageSize);
    if (page < 1 || page > totalPages) return;
    updateHistoryPage = page;
    renderUpdateHistory();
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
// 侧边栏折叠（替代原 AdminLTE push-menu）
// - 桌面端：折叠记忆；移动端：抽屉 + 遮罩（点击遮罩/ESC 关闭）
// ============================================
document.addEventListener('DOMContentLoaded', function () {
    var layout = document.getElementById('appLayout');
    var toggle = document.getElementById('sidebarToggle');
    var backdrop = document.getElementById('sidebarBackdrop');

    // 移动端判定优先用 matchMedia：与 CSS 的 @media (max-width: 991.98px) 由同一引擎、同一条件求值，
    // 保证 JS 分支与侧边栏实际显隐永远一致。个别手机浏览器 innerWidth 可能与媒体查询判定不符
    // （例如内嵌 WebView/浏览器 UI 导致布局视口偏差），若只用 innerWidth 会误走桌面分支，
    // 表现为"点击汉堡栏没反应"（侧边栏已被 CSS 隐藏，桌面分支仅切换 sidebar-collapsed 无可见变化）。
    var isMobile = function () {
        if (window.matchMedia) {
            return window.matchMedia('(max-width: 991.98px)').matches;
        }
        return window.innerWidth <= 991.98;
    };

    function setMobileDrawer(open) {
        if (!layout) return;
        if (isMobile()) {
            layout.classList.toggle('sidebar-open', !!open);
        }
    }

    if (toggle && layout) {
        toggle.addEventListener('click', function () {
            // 始终切换"打开"态：移动端由 CSS 决定抽屉显隐（@media max-width: 991.98px），
            // 桌面端该 class 无样式效果。这样即使视口判定偶发偏差，汉堡栏也一定有可见反馈，
            // 不会出现"点了没反应"（侧边栏已被 CSS 隐藏却只切换了桌面折叠态）。
            layout.classList.toggle('sidebar-open');
            if (window.matchMedia('(min-width: 992px)').matches) {
                layout.classList.toggle('sidebar-collapsed');
                try {
                    localStorage.setItem('app_sidebar_collapsed', layout.classList.contains('sidebar-collapsed') ? '1' : '0');
                } catch (e) {}
            }
        });
    }

    // 移动端：点击遮罩或按 ESC 关闭抽屉
    if (backdrop) {
        backdrop.addEventListener('click', function () { setMobileDrawer(false); });
    }
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') setMobileDrawer(false);
    });

    // 恢复上次折叠状态
    if (layout && !isMobile()) {
        try {
            if (localStorage.getItem('app_sidebar_collapsed') === '1') {
                layout.classList.add('sidebar-collapsed');
            }
        } catch (e) {}
    }

    // 视口变化：切到桌面端时收起移动端抽屉，切到移动端时移除桌面折叠态
    document.addEventListener('resize', function () {
        if (!layout) return;
        if (isMobile()) {
            layout.classList.remove('sidebar-collapsed');
        } else {
            layout.classList.remove('sidebar-open');
        }
    });
});

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