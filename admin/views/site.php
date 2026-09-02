<div class="card">
                    <div class="card-header">
                        <h3 class="card-title">网站设置</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted mb-3">配置前台首页展示的文字内容。默认值即界面当前展示的内容，留空字段将保持默认。</p>
                        <form id="siteSettingsForm">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="mb-3">
                                <label for="site_title" class="form-label">网页标题</label>
                                <input type="text" class="form-control" id="site_title" name="site_title" placeholder="浏览器标签页显示的名称" maxlength="100">
                                <small class="form-text text-muted">显示在浏览器标签页的 title 名称</small>
                            </div>
                            <div class="mb-3">
                                <label for="site_name" class="form-label">网站名称</label>
                                <input type="text" class="form-control" id="site_name" name="site_name" placeholder="首页顶部大标题" maxlength="100">
                                <small class="form-text text-muted">首页顶部大标题展示的网站名称</small>
                            </div>
                            <div class="mb-3">
                                <label for="site_lead" class="form-label">副标题</label>
                                <input type="text" class="form-control" id="site_lead" name="site_lead" placeholder="首页顶部的描述文字" maxlength="200">
                                <small class="form-text text-muted">首页顶部大标题下方的描述文字</small>
                            </div>
                            <div class="mb-3">
                                <label for="site_copyright" class="form-label">版权文字</label>
                                <input type="text" class="form-control" id="site_copyright" name="site_copyright" placeholder="底部版权文字（链接到本项目仓库）" maxlength="200">
                                <small class="form-text text-muted">底部版权文字，默认链接到本项目 GitHub 仓库</small>
                            </div>
                            <div class="mb-3">
                                <label for="site_icp" class="form-label">ICP 备案号</label>
                                <input type="text" class="form-control" id="site_icp" name="site_icp" placeholder="如：粤ICP备xxxxxxxx号（可留空不展示）" maxlength="100">
                                <small class="form-text text-muted">底部展示的备案号，链接到工信部网站。留空则不展示备案信息</small>
                            </div>
                            <hr>
                            <h6 class="text-muted mb-3">图片访问模式</h6>
                            <div class="mb-3">
                                <label for="image_mode" class="form-label">图片访问模式</label>
                                <select class="form-select" id="image_mode" name="image_mode">
                                    <option value="redirect">302 跳转模式（默认）</option>
                                    <option value="proxy">代理模式（隐藏真实图片链接）</option>
                                </select>
                                <small class="form-text text-muted">代理模式：所有 API（api.php/pc.php/pe.php）由服务器代为下载并转发图片，用户无法看到真实图片 URL，可隐藏图片链接；302 跳转模式：API 直接重定向到真实图片 URL。此设置对全部 API 生效，调用方传参不再影响返回方式。</small>
                            </div>
                            <div class="mb-3">
                                <label for="enable_json" class="form-label">JSON 格式输出</label>
                                <select class="form-select" id="enable_json" name="enable_json">
                                    <option value="0">关闭（默认）</option>
                                    <option value="1">开启</option>
                                </select>
                                <small class="form-text text-warning">开启后，可在 api.php/pc.php/pe.php 后加 <code>?format=json</code> 返回图片地址的 JSON 数据。注意：当「图片访问模式」为代理模式时，JSON 会返回真实的图片 URL，从而暴露代理模式本应隐藏的图片链接，请仅在确认无泄露风险时开启。</small>
                            </div>
                            <hr>
                            <h6 class="text-muted mb-3">频率限制设置</h6>
                            <div class="mb-3">
                                <label for="rate_limit_api" class="form-label">API 每分钟最大请求数</label>
                                <input type="number" class="form-control" id="rate_limit_api" name="rate_limit_api" min="1" max="10000" placeholder="默认 100">
                                <small class="form-text text-muted">每个 IP 每分钟最多可请求 API（api.php/pc.php/pe.php）的次数，超过返回 429。留空使用默认 100</small>
                            </div>
                            <div class="mb-3">
                                <label for="rate_limit_admin" class="form-label">后台操作每分钟最大请求数</label>
                                <input type="number" class="form-control" id="rate_limit_admin" name="rate_limit_admin" min="1" max="10000" placeholder="默认 10">
                                <small class="form-text text-muted">后台敏感操作（增删图片、更新、回滚等）每分钟最大次数，防自动化脚本。留空使用默认 10</small>
                            </div>
                            <hr>
                            <h6 class="text-muted mb-3">统计设置</h6>
                            <div class="mb-3">
                                <label for="stats_auto_flush_interval" class="form-label">统计自动落库间隔（秒）</label>
                                <input type="number" class="form-control" id="stats_auto_flush_interval" name="stats_auto_flush_interval" min="0" max="86400" step="1" placeholder="默认 60">
                                <small class="form-text text-muted">API 调用统计先写入缓冲、按此间隔自动合并进数据库。填 0 表示关闭自动落库（仅打开后台时才落库）；建议 10~3600。留空恢复默认 60 秒。</small>
                            </div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> 保存设置
                            </button>
                        </form>
                    </div>
                </div>