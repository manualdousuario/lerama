<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>admin-categories<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header d-md-flex justify-content-between align-items-center">
        <div>
            <h3 class="fs-5 fw-medium m-0">
                <i class="bi bi-folder me-1"></i>
                <?= __('admin.categories.title') ?>
            </h3>
        </div>
        <div class="pt-2 pb-1 pt-md-0 pb-md-0">
            <a href="/admin/categories/new" class="btn btn-primary d-inline-flex align-items-center">
                <i class="bi bi-plus-lg me-1"></i>
                <?= __('admin.categories.new') ?>
            </a>
        </div>
    </div>

    <?php if (empty($categories)): ?>
        <div class="card-body text-center p-4">
            <p class="text-secondary mb-0 mt-1">
                <i class="bi bi-exclamation-circle me-1"></i>
                <?= __('admin.categories.no_categories') ?>
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
                            <th scope="col" class="small text-uppercase"><?= __('admin.categories.feeds') ?></th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $category): ?>
                            <tr data-id="<?= $category['id'] ?>">
                                <td class="align-middle fw-medium">
                                    <?= $this->e($category['name']) ?>
                                </td>
                                <td class="align-middle text-secondary">
                                    <?= $this->e($category['slug']) ?>
                                </td>
                                <td class="align-middle">
                                    <span class="badge bg-success">
                                        <?= $category['feed_count'] ?> <?= __('admin.categories.feeds') ?>
                                    </span>
                                </td>
                                <td class="align-middle text-end">
                                    <div class="text-truncate">
                                        <a href="/admin/categories/<?= $category['id'] ?>/edit" class="d-inline-block btn btn-sm btn-outline-primary me-2">
                                            <i class="bi bi-pencil"></i>
                                        </a>
                                        <button class="d-inline-block btn btn-sm btn-outline-danger delete-btn" data-id="<?= $category['id'] ?>">
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
    // Delete category
    document.querySelectorAll('.delete-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            if (confirm('<?= __('admin.categories.delete_confirm') ?>')) {
                fetch(`/admin/categories/${id}`, {
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
                        alert(data.message || 'Erro ao excluir categoria');
                    }
                });
            }
        });
    });
});
</script>
<?php $this->stop() ?>