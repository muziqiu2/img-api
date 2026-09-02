<!-- 当前版本信息卡 -->
                <div class="row">
                    <div class="col-lg-6 col-12">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo htmlspecialchars(APP_VERSION); ?></h3>
                                <p>当前版本</p>
                            </div>
                            <div class="icon"><i class="fas fa-code-branch"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-6 col-12">
                        <div class="small-box bg-warning" id="latestVersionBox">
                            <div class="inner">
                                <h3 id="latestVersionText">检查中...</h3>
                                <p id="latestVersionLabel">最新版本</p>
                            </div>
                            <div class="icon"><i class="fas fa-cloud-download-alt"></i></div>
                        </div>
                    </div>
                </div>

                <!-- 版本检查与一键更新 -->
                <div class="card" id="updateCard">
                    <div class="card-header">
                        <h3 class="card-title">版本检查与更新</h3>
                        <div class="card-tools">
                            <button type="button" class="btn btn-sm btn-primary" onclick="checkUpdate(true)">
                                <i class="fas fa-redo"></i> 重新检查
                            </button>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="form-group" id="releaseInfoBox" style="display:none;">
                            <label>最新版本发布信息</label>
                            <div class="card bg-light p-3" id="releaseDetails">
                                <div class="mb-2">
                                    <strong id="releaseName"></strong>
                                    <small class="text-muted ml-2" id="releaseDate"></small>
                                </div>
                                <div class="mb-2">
                                    <a id="releaseUrl" href="#" target="_blank" class="btn btn-outline-primary btn-sm">
                                        <i class="fab fa-github"></i> 查看 GitHub Release
                                    </a>
                                </div>
                                <pre id="releaseBody" style="white-space:pre-wrap;background:#f8f9fa;padding:10px;border-radius:4px;"></pre>
                            </div>
                        </div>

                        <div id="envWarningBox"></div>

                        <div class="alert alert-info" id="updateStatus">
                            <i class="icon fas fa-info-circle"></i> 正在检查 GitHub 最新版本...
                        </div>

                        <div class="mt-3" id="updateActionBox" style="display:none;">
                            <button type="button" class="btn btn-success btn-lg" id="updateBtn" onclick="doUpdate()">
                                <i class="fas fa-download"></i> 立即更新到最新版本
                            </button>
                            <small class="form-text text-muted mt-2">
                                更新前将自动备份当前文件；如果更新失败将自动回滚。
                            </small>
                        </div>

                        <div class="progress mt-3" id="progressBar" style="display:none;">
                            <div class="progress-bar progress-bar-striped progress-bar-animated bg-primary" id="progressBarInner" style="width:100%"></div>
                        </div>

                        <div class="mt-3" id="updateLogBox" style="display:none;">
                            <label>更新日志</label>
                            <pre id="updateLog" class="bg-dark text-light p-3 rounded" style="max-height:300px;overflow:auto;font-size:13px;"></pre>
                        </div>
                    </div>
                </div>

                <!-- GitHub Token 设置 -->
                <div class="card mt-3" id="tokenSettingsCard">
                    <div class="card-header">
                        <h3 class="card-title">GitHub Token 设置</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">
                            填写 GitHub Personal Access Token 可大幅提升 API 请求限制（从 60次/小时 提升至 5000次/小时）。<br>
                            不填写也可以正常使用，但可能遇到频率限制。如需 Token，请前往 GitHub Settings → Developer settings → Personal access tokens 生成。
                        </p>
                        <div class="row">
                            <div class="col-md-8">
                                <div class="input-group">
                                    <input type="password" class="form-control" id="githubTokenInput" placeholder="ghp_xxxxxxxxxxxxxxxxxx">
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-secondary" id="toggleTokenBtn" onclick="toggleTokenVisibility()">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <button type="button" class="btn btn-primary" id="saveTokenBtn" onclick="saveGithubToken()">
                                            <i class="fas fa-save"></i> 保存
                                        </button>
                                        <button type="button" class="btn btn-outline-danger" id="clearTokenBtn" onclick="clearGithubToken()" style="display:none;">
                                            <i class="fas fa-trash"></i> 清空
                                        </button>
                                    </div>
                                </div>
                                <small class="form-text text-muted" id="tokenStatus"></small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 备份管理 -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">备份管理</h3>
                    </div>
                    <div class="card-body">
                        <p class="text-muted small">每次自动更新时会在更新前创建备份。您也可以在此处手动从任一备份恢复系统。</p>
                        <div id="backupList">
                            <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> 正在加载备份列表...</div>
                        </div>
                    </div>
                </div>

                <!-- 更新历史日志 -->
                <div class="card mt-3">
                    <div class="card-header">
                        <h3 class="card-title">更新历史</h3>
                    </div>
                    <div class="card-body">
                        <div id="updateHistoryList">
                            <div class="text-center text-muted py-3"><i class="fas fa-spinner fa-spin"></i> 正在加载更新历史...</div>
                        </div>
                    </div>
                </div>

                <!-- 环境检测入口（完整检测已独立到「环境检测」页，避免重复展示） -->
                <div class="alert alert-info mt-3">
                    <i class="fas fa-info-circle"></i>
                    运行环境、依赖扩展与目录权限的完整检测结果已整合至
                    <a href="?section=environment">环境检测</a> 页面。
                </div>