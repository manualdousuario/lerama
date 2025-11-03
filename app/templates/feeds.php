<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>feeds<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <div class="row">
            <div class="col-12 col-md-4">
                <h3 class="fs-5 fw-medium mb-0 mt-1 mt-md-2">
                    <i class="bi bi-journal-text me-1"></i>
                    Feeds
                </h3>
            </div>
            <div class="col-12 col-md-8 pb-1 pb-md-0 pt-3 pt-md-0">
                <form action="/feeds" method="GET">
                    <div class="row g-2">
                        <div class="col-6 col-md-5">
                            <select name="category" class="form-select">
                                <option value="">Todas Categorias</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $this->e($category['slug']) ?>" <?= ($selectedCategory ?? '') == $category['slug'] ? 'selected' : '' ?>>
                                        <?= $this->e($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-6 col-md-5">
                            <select name="tag" class="form-select">
                                <option value="">Todas Tags</option>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?= $this->e($tag['slug']) ?>" <?= ($selectedTag ?? '') == $tag['slug'] ? 'selected' : '' ?>>
                                        <?= $this->e($tag['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-2">
                            <button type="submit" class="btn btn-primary w-100 d-flex align-items-center justify-content-center">
                                <i class="bi bi-funnel me-1"></i>
                                Filtrar
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (empty($feeds)): ?>
        <div class="card-body text-center p-4">
            <p class="text-secondary mb-0">Nenhum feed encontrado.</p>
        </div>
    <?php else: ?>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-journal-text me-1"></i>
                                    Feed
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-folder me-1"></i>
                                    Categorias
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-tags me-1"></i>
                                    Tags
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-clock me-1"></i>
                                    Status
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center text-truncate">
                                    <i class="bi bi-clock-history me-1"></i>
                                    Verificação/Atualização
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-collection me-1"></i>
                                    Artigos
                                </div>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeds as $feed): ?>
                            <tr>
                                <td>
                                    <div class="d-flex">
                                        <div>
                                            <div class="fw-medium">
                                                <a href="<?= $this->e($feed['site_url']) ?>" target="_blank" class="text-decoration-none">
                                                    <?= $this->e($feed['title']) ?>
                                                    <i class="bi bi-box-arrow-up-right ms-1"></i>
                                                </a>
                                            </div>
                                            <div class="small text-secondary text-truncate" style="max-width: 250px;">
                                                <a href="<?= $this->e($feed['feed_url']) ?>" target="_blank" class="text-decoration-none text-secondary">
                                                    <?= $this->e($feed['feed_url']) ?>
                                                    <i class="bi bi-journal-text ms-1"></i>
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <?php if (!empty($feed['categories'])): ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($feed['categories'] as $category): ?>
                                                <span class="badge bg-primary text-dark">
                                                    <?= $this->e($category['name']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-secondary small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <?php if (!empty($feed['tags'])): ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($feed['tags'] as $tag): ?>
                                                <span class="badge bg-secondary">
                                                    <?= $this->e($tag['name']) ?>
                                                </span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-secondary small">-</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <?php if ($feed['status'] === 'online'): ?>
                                        <span class="badge bg-success">
                                            <i class="bi bi-check-circle me-1"></i>
                                            Online
                                        </span>
                                    <?php elseif ($feed['status'] === 'offline'): ?>
                                        <span class="badge bg-danger">
                                            <i class="bi bi-x-circle me-1"></i>
                                            Offline
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-warning text-dark">
                                            <i class="bi bi-pause-circle me-1"></i>
                                            Pausado
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle small text-secondary">
                                    <div>
                                        <strong>Verif:</strong> <?= $feed['last_checked'] ? date('d/m/Y H:i', strtotime($feed['last_checked'])) : 'Nunca' ?>
                                    </div>
                                    <div>
                                        <strong>Atual:</strong> <?= $feed['last_updated'] ? date('d/m/Y H:i', strtotime($feed['last_updated'])) : 'Nunca' ?>
                                    </div>
                                </td>
                                <td class="align-middle small text-secondary">
                                    <?= $feed['item_count'] ?? 0 ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (isset($pagination) && $pagination['total'] > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center p-3">
                <div class="d-flex justify-content-center align-items-center w-100">
                    <nav aria-label="Pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            $queryParams = array_filter([
                                'category' => $selectedCategory ?? null,
                                'tag' => $selectedTag ?? null
                            ]);
                            $queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
                            ?>
                            
                            <?php if ($pagination['current'] > 1): ?>
                                <li class="page-item">
                                    <a href="<?= $pagination['baseUrl'] . ($pagination['current'] - 1) . $queryString ?>" class="page-link" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $pagination['current'] - 2);
                            $end = min($pagination['total'], $pagination['current'] + 2);

                            if ($start > 1) {
                                echo '<li class="page-item"><a href="' . $pagination['baseUrl'] . '1' . $queryString . '" class="page-link">1</a></li>';
                                if ($start > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $start; $i <= $end; $i++) {
                                if ($i == $pagination['current']) {
                                    echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                } else {
                                    echo '<li class="page-item"><a href="' . $pagination['baseUrl'] . $i . $queryString . '" class="page-link">' . $i . '</a></li>';
                                }
                            }

                            if ($end < $pagination['total']) {
                                if ($end < $pagination['total'] - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a href="' . $pagination['baseUrl'] . $pagination['total'] . $queryString . '" class="page-link">' . $pagination['total'] . '</a></li>';
                            }
                            ?>

                            <?php if ($pagination['current'] < $pagination['total']): ?>
                                <li class="page-item">
                                    <a href="<?= $pagination['baseUrl'] . ($pagination['current'] + 1) . $queryString ?>" class="page-link" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>