<div class="card">
                    <div class="card-header">
                        <h3 class="card-title">用户设置</h3>
                    </div>
                    <?php if ($mustChangePassword): ?>
                    <div class="alert alert-warning mb-0 rounded-0">
                        <i class="icon fas fa-exclamation-triangle"></i>
                        系统检测到您仍在使用默认密码，请立即修改密码后再使用其他功能。
                    </div>
                    <?php endif; ?>
                    <div class="card-body">
                        <form method="post" action="?section=user">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="mb-3">
                                <label for="current_password" class="form-label">原密码</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" placeholder="请输入原密码" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_username" class="form-label">用户名</label>
                                <input type="text" class="form-control" id="new_username" name="new_username" value="<?php echo htmlspecialchars($currentUsername); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="new_password" class="form-label">新密码</label>
                                <input type="password" class="form-control" id="new_password" name="new_password" placeholder="不修改请留空">
                                <div class="form-text">密码长度至少6位</div>
                            </div>
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label">确认新密码</label>
                                <input type="password" class="form-control" id="confirm_password" name="confirm_password" placeholder="再次输入新密码">
                            </div>
                            <button type="submit" name="update_user" class="btn btn-primary">
                                <i class="fas fa-save"></i> 保存设置
                            </button>
                        </form>
                    </div>
                </div>