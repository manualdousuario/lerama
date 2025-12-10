<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>admin<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <div class="row">
            <div class="col-12 col-md-6  d-flex align-items-center">
                <h3 class="fs-5 fw-medium m-0">
                    <i class="bi bi-collection me-1"></i>
                    <?= __('admin.items.title') ?>
                </h3>
            </div>
            <div class="col-12 col-md-6 pb-1 pb-md-0 pt-3 pt-md-0">
                <form action="/admin" method="GET" class="d-flex gap-2">
                    <div>
                        <select name="feed" class="form-select">
                            <option value=""><?= __('admin.items.feeds') ?></option>
                            <?php foreach ($feeds as $feed): ?>
                                <option value="<?= $feed['id'] ?>" <?= $selectedFeed == $feed['id'] ? 'selected' : '' ?>>
                                    <?= $this->e($feed['title']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="input-group">
                        <input type="text" name="search" value="<?= $this->e($search) ?>" class="form-control" placeholder="<?= __('common.search_placeholder') ?>">
                        <button type="submit" class="btn btn-primary d-flex align-items-center">
                            <i class="bi bi-search me-1"></i>
                            <?= __('common.search') ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="card-body text-center p-4">
            <p class="text-secondary mb-0">
                <i class="bi bi-exclamation-circle me-1"></i>
                <?= __('admin.items.no_items') ?>
            </p>
        </div>
    <?php else: ?>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-file-text me-1"></i>
                                    <?= __('suggest.form.title') ?>
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-journal-text me-1"></i>
                                    <?= __('admin.items.feed') ?>
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person me-1"></i>
                                    <?= __('admin.items.author') ?>
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-calendar me-1"></i>
                                    <?= __('admin.items.published') ?>
                                </div>
                            </th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="align-middle fw-medium">
                                    <a href="<?= $this->e($item['url']) . (parse_url($item['url'], PHP_URL_QUERY) ? '&' : '?') ?>utm_source=lerama" target="_blank" class="text-decoration-none">
                                        <?= $this->e($item['title']) ?>
                                        <i class="bi bi-box-arrow-up-right ms-1"></i>
                                    </a>
                                </td>
                                <td class="align-middle">
                                    <?= $this->e($item['feed_title']) ?>
                                </td>
                                <td class="align-middle">
                                    <?= $this->e($item['author'] ?? __('admin.items.unknown_author')) ?>
                                </td>
                                <td class="small text-secondary align-middle">
                                    <?= $item['published_at'] ? date('d/m/Y \à\s H:i', strtotime($item['published_at'])) : 'Nunca' ?>
                                </td>
                                <td class="align-middle text-end">
                                    <button
                                        data-id="<?= $item['id'] ?>"
                                        data-visible="<?= $item['is_visible'] ? '1' : '0' ?>"
                                        class="d-inline-block btn btn-sm <?= $item['is_visible'] ? 'btn-outline-success' : 'btn-outline-danger' ?> toggle-visibility">
                                        <?php if ($item['is_visible']): ?>
                                            <i class="bi bi-eye"></i>
                                        <?php else: ?>
                                            <i class="bi bi-eye-slash"></i>
                                        <?php endif; ?>
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($pagination['total'] > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center p-3">
                <div class="d-flex justify-content-center align-items-center w-100">
                    <nav aria-label="Pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <?php if ($pagination['current'] > 1): ?>
                                <li class="page-item">
                                    <a href="<?= $pagination['baseUrl'] ?>&page=<?= $pagination['current'] - 1 ?>" class="page-link" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $pagination['current'] - 2);
                            $end = min($pagination['total'], $pagination['current'] + 2);

                            if ($start > 1) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }

                            for ($i = $start; $i <= $end; $i++) {
                                if ($i == $pagination['current']) {
                                    echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                } else {
                                    echo '<li class="page-item"><a href="' . $pagination['baseUrl'] . '&page=' . $i . '" class="page-link">' . $i . '</a></li>';
                                }
                            }

                            if ($end < $pagination['total']) {
                                echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                            }
                            ?>

                            <?php if ($pagination['current'] < $pagination['total']): ?>
                                <li class="page-item">
                                    <a href="<?= $pagination['baseUrl'] ?>&page=<?= $pagination['current'] + 1 ?>" class="page-link" aria-label="Next">
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

<?php $this->start('scripts') ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const toggleButtons = document.querySelectorAll('.toggle-visibility');

        toggleButtons.forEach(button => {
            button.addEventListener('click', function() {
                const id = this.dataset.id;
                const currentVisible = this.dataset.visible === '1';
                const newVisible = !currentVisible;

                fetch(`/admin/items/${id}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            is_visible: newVisible
                        }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (newVisible) {
                                this.innerHTML = '<i class="bi bi-eye"></i>';
                                this.classList.remove('btn-danger');
                                this.classList.add('btn-success');
                            } else {
                                this.innerHTML = '<i class="bi bi-eye-slash"></i>';
                                this.classList.remove('btn-success');
                                this.classList.add('btn-danger');
                            }
                            this.dataset.visible = newVisible ? '1' : '0';
                        } else {
                            alert('Erro ao atualizar item: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Ocorreu um erro ao atualizar o item.');
                    });
            });
        });
    });
</script>
<?php $this->stop() ?>