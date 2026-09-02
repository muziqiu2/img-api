<div class="card">
                    <div class="card-header">
                        <h3 class="card-title">操作日志</h3>
                    </div>
                    <div class="card-body table-responsive p-0" style="max-height: 500px;">
                        <?php if (empty($adminLogs)): ?>
                        <div class="alert alert-warning m-3">
                            <i class="icon fas fa-info"></i> 暂无操作日志
                        </div>
                        <?php else: ?>
                        <table class="table table-head-fixed text-nowrap">
                            <thead>
                                <tr>
                                    <th style="width: 18%">时间</th>
                                    <th style="width: 12%">用户</th>
                                    <th style="width: 50%">操作</th>
                                    <th style="width: 20%">IP地址</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminLogs as $log): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($log['time']); ?></td>
                                    <td><span class="badge badge-primary"><?php echo htmlspecialchars($log['username']); ?></span></td>
                                    <td><?php echo htmlspecialchars($log['action']); ?></td>
                                    <td><code><?php echo htmlspecialchars($log['ip']); ?></code></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>
                    </div>
                </div>