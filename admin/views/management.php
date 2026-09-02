<!-- 统计卡片 -->
                <div class="row">
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-info">
                            <div class="inner">
                                <h3><?php echo number_format($stats['total'] ?? 0); ?></h3>
                                <p>总调用次数</p>
                            </div>
                            <div class="icon"><i class="fas fa-chart-line"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-success">
                            <div class="inner">
                                <h3><?php echo getImageCount('pc'); ?></h3>
                                <p>PC端图片数</p>
                            </div>
                            <div class="icon"><i class="fas fa-desktop"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-primary">
                            <div class="inner">
                                <h3><?php echo getImageCount('pe'); ?></h3>
                                <p>移动端图片数</p>
                            </div>
                            <div class="icon"><i class="fas fa-mobile-alt"></i></div>
                        </div>
                    </div>
                    <div class="col-lg-3 col-6">
                        <div class="small-box bg-warning">
                            <div class="inner">
                                <h3><?php echo $stats['daily'][date('Y-m-d')]['total'] ?? 0; ?></h3>
                                <p>今日调用</p>
                            </div>
                            <div class="icon"><i class="fas fa-calendar-day"></i></div>
                        </div>
                    </div>
                </div>

                <!-- 类型切换 -->
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">图片链接管理</h3>
                        <div class="card-tools">
                            <ul class="nav nav-pills ms-auto">
                                <li class="nav-item">
                                    <a href="?section=management&type=pc" class="nav-link <?php echo $currentType === 'pc' ? 'active' : ''; ?>">PC端</a>
                                </li>
                                <li class="nav-item">
                                    <a href="?section=management&type=pe" class="nav-link <?php echo $currentType === 'pe' ? 'active' : ''; ?>">移动端</a>
                                </li>
                            </ul>
                        </div>
                    </div>
                    <div class="card-body">
                        <!-- 添加链接表单 -->
                        <form method="post" action="?section=management&type=<?php echo $currentType; ?>">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                            <div class="mb-3">
                                <label class="form-label">添加图片链接（每行一个URL）</label>
                                <textarea name="urls" class="form-control" rows="3" placeholder="https://example.com/image1.jpg"></textarea>
                            </div>
                            <button type="submit" name="add_urls" class="btn btn-primary">
                                <i class="fas fa-plus"></i> 添加图片链接
                            </button>
                            <?php if (!empty($urls)): ?>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>

                <!-- 图片列表 -->
                <?php if (!empty($urls)): ?>
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">
                            <?php echo $currentType === 'pc' ? 'PC端' : '移动端'; ?>图片链接列表 (共 <?php echo $imageData['total']; ?> 个)
                        </h3>
                        <button type="button" class="btn btn-danger btn-sm float-end" id="deleteSelectedBtn" data-type="<?php echo htmlspecialchars($currentType, ENT_QUOTES); ?>" style="display:none;">
                            <i class="fas fa-trash"></i> 删除选中
                        </button>
                    </div>
                    <div class="card-body table-responsive p-0" style="max-height: 400px;">
                        <table class="table table-head-fixed text-nowrap">
                            <thead>
                                <tr>
                                    <th style="width: 5%"><input type="checkbox" id="selectAll" onchange="toggleSelectAll()"></th>
                                    <th style="width: 85%">URL</th>
                                    <th style="width: 10%">操作</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($urls as $url): ?>
                                <tr>
                                    <td><input type="checkbox" class="url-checkbox" value="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>" onchange="updateDeleteButton()"></td>
                                    <td>
                                        <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" title="<?php echo htmlspecialchars($url); ?>">
                                            <?php echo htmlspecialchars(strlen($url) > 80 ? substr($url, 0, 80) . '...' : $url); ?>
                                        </a>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm btn-del-url" data-url="<?php echo htmlspecialchars($url, ENT_QUOTES); ?>" data-type="<?php echo htmlspecialchars($currentType, ENT_QUOTES); ?>">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php if ($totalPages > 1): ?>
                    <div class="card-footer clearfix">
                        <ul class="pagination pagination-sm m-0 float-end">
                            <?php if ($currentPage > 1): ?>
                            <li class="page-item"><a class="page-link" href="?section=management&type=<?php echo $currentType; ?>&page=<?php echo $currentPage - 1; ?>">&laquo;</a></li>
                            <?php endif; ?>
                            <?php
                            // 窗口式分页：仅渲染首页、当前页附近与末页（含省略号），
                            // 避免图片量大时渲染数千个页码节点导致页面卡顿
                            $pageItems = [];
                            if ($totalPages <= 7) {
                                $pageItems = range(1, $totalPages);
                            } else {
                                $near = range(max(1, $currentPage - 2), min($totalPages, $currentPage + 2));
                                $windowPages = array_values(array_unique(array_merge([1], $near, [$totalPages])));
                                $prev = 0;
                                foreach ($windowPages as $p) {
                                    if ($prev && $p - $prev > 1) {
                                        $pageItems[] = '...';
                                    }
                                    $pageItems[] = $p;
                                    $prev = $p;
                                }
                            }
                            ?>
                            <?php foreach ($pageItems as $item): ?>
                                <?php if ($item === '...'): ?>
                                <li class="page-item disabled"><span class="page-link">&hellip;</span></li>
                                <?php else: ?>
                                <li class="page-item <?php echo $item == $currentPage ? 'active' : ''; ?>">
                                    <a class="page-link" href="?section=management&type=<?php echo $currentType; ?>&page=<?php echo $item; ?>"><?php echo $item; ?></a>
                                </li>
                                <?php endif; ?>
                            <?php endforeach; ?>
                            <?php if ($currentPage < $totalPages): ?>
                            <li class="page-item"><a class="page-link" href="?section=management&type=<?php echo $currentType; ?>&page=<?php echo $currentPage + 1; ?>">&raquo;</a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div class="alert alert-warning">
                    <i class="icon fas fa-info"></i> 没有找到图片链接，请添加新的图片链接
                </div>
                <?php endif; ?>