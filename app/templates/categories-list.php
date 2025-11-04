<?php $this->layout('layout', ['title' => 'Categorias']) ?>

<?php $this->start('active') ?>categories<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h3 class="fs-5 fw-medium mb-1 mt-1">
            <i class="bi bi-folder me-1"></i>
            <?= __('categories.title') ?>
        </h3>
    </div>
    
    <?php if (empty($categories)): ?>
        <div class="card-body text-center p-4">
            <p class="text-secondary mb-0"><?= __('categories.no_categories') ?></p>
        </div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($categories as $category): ?>
                <a href="/?category=<?= $this->e($category['slug']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="m-0 fs-6 d-flex justify-content-center align-items-center">
                            <i class="bi bi-folder me-2"></i>
                            <span><?= $this->e($category['name']) ?></span>
                        </h5>
                        <?php if (!empty($category['description'])): ?>
                            <p class="mb-0 text-secondary small"><?= $this->e($category['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-secondary text-dark rounded-pill">
                        <?= $category['item_count'] ?>
                        <?= $category['item_count'] == 1 ? __('categories.article') : __('categories.articles') ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>