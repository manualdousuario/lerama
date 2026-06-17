<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>feeds<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <div class="row align-items-center">
            <div class="col-12 col-md-8">
                <h1 class="fs-5 fw-medium m-0 py-1">
                    <i class="bi bi-journal-text me-1" aria-hidden="true"></i>
                    <?= $this->e($feed['title']) ?>
                </h1>
                <p class="mb-0 text-secondary small">
                    <?= __('feeds.items') ?>: <?= $feed['item_count'] ?? 0 ?>
                    <span class="mx-1">|</span>
                    <a href="<?= $this->e($feed['site_url']) ?>" target="_blank" class="text-decoration-none">
                        <?= $this->e($feed['site_url']) ?>
                        <i class="bi bi-box-arrow-up-right ms-1"></i>
                    </a>
                </p>
            </div>
            <div class="col-12 col-md-4 text-md-end mt-2 mt-md-0">
                <a href="/feeds" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i>
                    <?= __('nav.feeds') ?>
                </a>
            </div>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="card-body text-center p-4">
            <p class="text-secondary mb-0">
                <i class="bi bi-exclamation-circle me-1"></i>
                <?= __('home.no_items') ?>
            </p>
        </div>
    <?php else: ?>
        <ul class="list-group list-group-flush" id="list-view">
            <?php foreach ($items as $item): ?>
                <li class="list-group-item p-3 hover-bg-light">
                    <div class="d-block d-md-flex">
                        <?php if (!empty($item['image_url'])) : ?>
                            <div class="pe-md-3 pb-2 pb-md-0 image-thumbnail">
                                <img class="rounded-2" src="<?= $this->e($thumbnailService->getThumbnail($item['image_url'], 180, 100)) ?>" width="180" height="100" loading="lazy" decoding="async" alt="<?= $this->e($item['title']) ?>">
                            </div>
                        <?php endif; ?>
                        <div class="flex-fill">
                            <div class="pb-2 pb-md-0">
                                <h4 class="fs-5 fw-medium text-primary m-0">
                                    <a href="<?= $this->e($item['url']) . (parse_url($item['url'], PHP_URL_QUERY) ? '&' : '?') ?>utm_source=lerama" target="_blank" class="text-decoration-none hover-underline">
                                        <?= $this->e($item['title']) ?>
                                    </a>
                                </h4>
                            </div>

                            <p class="d-flex align-items-center small mb-0">
                                <?php if (!empty($item['published_at'])): ?>
                                    <i class="bi bi-calendar me-1"></i>
                                    <?= date('j/m/Y \à\s H:i', strtotime($item['published_at'])) ?>
                                <?php endif; ?>
                                <?php if (!empty($item['author'])): ?>
                                    <i class="ms-2 bi bi-person me-1"></i>
                                    <?= $this->e($item['author']) ?>
                                <?php endif; ?>
                            </p>

                            <?php if (!empty($item['content']) && strlen($item['content']) >= 30): ?>
                                <div class="mt-2 small content">
                                    <?= substr(strip_tags($item['content']), 0, 300) ?>...
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (isset($pagination) && $pagination['total'] > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center p-3">
                <div class="d-flex justify-content-center align-items-center w-100">
                    <nav aria-label="Pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($pagination['current'] > 1): ?>
                                <li class="page-item">
                                    <a href="<?= $pagination['baseUrl'] . ($pagination['current'] - 1) ?>" class="page-link" aria-label="<?= __('a11y.previous_page') ?>" rel="prev">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $pagination['current'] - 2);
                            $end = min($pagination['total'], $pagination['current'] + 2);

                            if ($start > 1) {
                                echo '<li class="page-item"><a href="' . $pagination['baseUrl'] . '1" class="page-link">1</a></li>';
                                if ($start > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $start; $i <= $end; $i++) {
                                if ($i == $pagination['current']) {
                                    echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                } else {
                                    echo '<li class="page-item"><a href="' . $pagination['baseUrl'] . $i . '" class="page-link">' . $i . '</a></li>';
                                }
                            }

                            if ($end < $pagination['total']) {
                                if ($end < $pagination['total'] - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a href="' . $pagination['baseUrl'] . $pagination['total'] . '" class="page-link">' . $pagination['total'] . '</a></li>';
                            }
                            ?>

                            <?php if ($pagination['current'] < $pagination['total']): ?>
                                <li class="page-item">
                                    <a href="<?= $pagination['baseUrl'] . ($pagination['current'] + 1) ?>" class="page-link" aria-label="<?= __('a11y.next_page') ?>" rel="next">
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
