<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>admin-tags<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header d-md-flex justify-content-between align-items-center">
        <div>
            <h3 class="fs-5 fw-medium m-0">
                <i class="bi bi-tags me-1"></i>
                <?= __('admin.tags.title') ?>
            </h3>
        </div>
        <div class="pt-2 pb-1 pt-md-0 pb-md-0">
            <a href="/admin/tags/new" class="btn btn-primary d-inline-flex align-items-center">
                <i class="bi bi-plus-lg me-1"></i>
                <?= __('admin.tags.new') ?>
            </a>
        </div>
    </div>

    <?php if (empty($tags)): ?>
        <div class="card-body text-center p-4">
            <p class="text-secondary mb-0 mt-1">
                <i class="bi bi-exclamation-circle me-1"></i>
                <?= __('admin.tags.no_tags') ?>
            </p>
        </div>
    <?php else: ?>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="small text-uppercase"><?= __('common.name') ?></th>
                            <th scope="col" class="small text-uppercase"><?= __('common.slug') ?></th>
                            <th scope="col" class="small text-uppercase"><?= __('admin.tags.feeds') ?></th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tags as $tag): ?>
                            <tr data-id="<?= $tag['id'] ?>">
                                <td class="align-middle fw-medium">
                                    <?= $this->e($tag['name']) ?>
                                </td>
                                <td class="align-middle text-secondary">
                                    <?= $this->e($tag['slug']) ?>
                                </td>
                                <td class="align-middle">
                                    <span class="badge bg-success">
                                        <?= $tag['feed_count'] ?> <?= __('admin.tags.feeds') ?>
                                    </span>
                                </td>
                                <td class="align-middle text-end">
                                    <div class="text-truncate">
                                        <a href="/admin/tags/<?= $tag['id'] ?>/edit" class="d-inline-block btn btn-sm btn-outline-primary me-2">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button class="d-inline-block btn btn-sm btn-outline-danger delete-btn" data-id="<?= $tag['id'] ?>">
                                            <i class="bi bi-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php $this->start('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete tag
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            if (confirm('<?= __('admin.tags.delete_confirm') ?>')) {
                fetch(`/admin/tags/${id}`, {
                    method: 'DELETE',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        location.reload();
                    } else {
                        alert(data.message || 'Erro ao excluir tag');
                    }
                });
            }
        });
    });
});
</script>
<?php $this->stop() ?>