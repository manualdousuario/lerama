<?php $this->layout('layout', ['title' => 'Tags']) ?>

<?php $this->start('active') ?>tags<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h3 class="fs-5 fw-medium mb-1 mt-1">
            <i class="bi bi-tags me-1"></i>
            Tags
        </h3>
    </div>
    
    <?php if (empty($tags)): ?>
        <div class="card-body text-center p-4">
            <p class="text-secondary mb-0">Nenhuma tag encontrada.</p>
        </div>
    <?php else: ?>
        <div class="list-group list-group-flush">
            <?php foreach ($tags as $tag): ?>
                <a href="/?tag=<?= $this->e($tag['slug']) ?>" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="m-0 fs-6 d-flex justify-content-center align-items-center">
                            <i class="bi bi-tag me-2"></i>
                            <span><?= $this->e($tag['name']) ?></span>
                        </h5>
                        <?php if (!empty($tag['description'])): ?>
                            <p class="mb-0 text-secondary small"><?= $this->e($tag['description']) ?></p>
                        <?php endif; ?>
                    </div>
                    <span class="badge bg-secondary rounded-pill">
                        <?= $tag['item_count'] ?> 
                        <?= $tag['item_count'] == 1 ? 'artigo' : 'artigos' ?>
                    </span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>