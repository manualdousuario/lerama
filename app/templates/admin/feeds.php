<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>admin-feeds<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header d-md-flex justify-content-between align-items-center">
        <div>
            <h3 class="fs-5 fw-medium m-0">
                <i class="bi bi-grid me-1"></i>
                Gerenciar Feeds
            </h3>
        </div>
        <div class="pt-2 pb-1 pt-md-0 pb-md-0">
            <a href="/admin/feeds/new" class="btn btn-primary d-inline-flex align-items-center">
                <i class="bi bi-plus-lg me-1"></i>
                Adicionar Novo Feed
            </a>
        </div>
    </div>

    <!-- Filters and Bulk Actions -->
    <div class="card-body border-bottom">
        <div class="row g-3 align-items-end">
            <!-- Status Filter -->
            <div class="col-md-4">
                <label for="status-filter" class="form-label small fw-medium">Filtrar por Status</label>
                <select id="status-filter" class="form-select" onchange="window.location.href='/admin/feeds?status=' + this.value">
                    <option value="">Todos os Status</option>
                    <option value="online" <?= $currentStatus === 'online' ? 'selected' : '' ?>>Online</option>
                    <option value="offline" <?= $currentStatus === 'offline' ? 'selected' : '' ?>>Offline</option>
                    <option value="paused" <?= $currentStatus === 'paused' ? 'selected' : '' ?>>Pausado</option>
                    <option value="pending" <?= $currentStatus === 'pending' ? 'selected' : '' ?>>Pendente</option>
                    <option value="rejected" <?= $currentStatus === 'rejected' ? 'selected' : '' ?>>Rejeitado</option>
                </select>
            </div>

            <!-- Bulk Actions -->
            <div class="col-md-8">
                <div class="d-flex gap-2 flex-wrap">
                    <button id="bulk-categories-btn" class="btn btn-outline-primary" disabled>
                        <i class="bi bi-folder me-1"></i>
                        Editar Categorias
                    </button>
                    <button id="bulk-tags-btn" class="btn btn-outline-primary" disabled>
                        <i class="bi bi-tags me-1"></i>
                        Editar Tags
                    </button>
                    <span id="selection-count" class="align-self-center text-secondary small" style="display: none;">
                        <span id="count-number">0</span> selecionado(s)
                    </span>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($feeds)): ?>
        <div class="card-body text-center p-4">
            <p class="text-secondary mb-0 mt-1">
                <i class="bi bi-exclamation-circle me-1"></i>
                Nenhum feed encontrado. <?= $currentStatus ? 'Tente outro filtro ou ' : '' ?>Adicione seu primeiro feed usando o botão acima.
            </p>
        </div>
    <?php else: ?>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-striped table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col" style="width: 40px;">
                                <input type="checkbox" id="select-all" class="form-check-input">
                            </th>
                            <th scope="col" class="small text-uppercase">Feed</th>
                            <th scope="col" class="small text-uppercase">Categorias</th>
                            <th scope="col" class="small text-uppercase">Tags</th>
                            <th scope="col" class="small text-uppercase">Idioma</th>
                            <th scope="col" class="small text-uppercase">Status</th>
                            <th scope="col" class="small text-uppercase text-truncate">Verificação/Atualização</th>
                            <th scope="col" class="small text-uppercase">Itens</th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($feeds as $feed): ?>
                            <tr data-feed-id="<?= $feed['id'] ?>">
                                <td>
                                    <input type="checkbox" class="form-check-input feed-checkbox" value="<?= $feed['id'] ?>">
                                </td>
                                <td>
                                    <div>
                                        <div class="fw-medium">
                                            <a href="<?= $this->e($feed['site_url']) ?>" target="_blank" class="text-decoration-none">
                                                <?= $this->e($feed['title']) ?>
                                            </a>
                                        </div>
                                        <div class="small text-secondary text-truncate" style="max-width: 250px;">
                                            <a href="<?= $this->e($feed['feed_url']) ?>" target="_blank" class="text-decoration-none text-secondary">
                                                <?= $this->e($feed['feed_url']) ?>
                                            </a>
                                        </div>
                                    </div>
                                </td>
                                <td class="align-middle">
                                    <?php if (!empty($feed['categories'])): ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($feed['categories'] as $category): ?>
                                                <span class="badge bg-primary"><?= $this->e($category['name']) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-secondary small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <?php if (!empty($feed['tags'])): ?>
                                        <div class="d-flex flex-wrap gap-1">
                                            <?php foreach ($feed['tags'] as $tag): ?>
                                                <span class="badge bg-secondary"><?= $this->e($tag['name']) ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php else: ?>
                                        <span class="text-secondary small">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="align-middle">
                                    <?php
                                    $languages = [
                                        'en' => 'Inglês',
                                        'pt-BR' => 'Português-Brasil',
                                        'es' => 'Espanhol'
                                    ];
                                    echo $this->e($languages[$feed['language']] ?? $feed['language']);
                                    ?>
                                </td>
                                <td class="align-middle">
                                    <select class="form-select form-select-sm status-select" data-feed-id="<?= $feed['id'] ?>" data-original-value="<?= $feed['status'] ?>">
                                        <option value="online" <?= $feed['status'] === 'online' ? 'selected' : '' ?>>Online</option>
                                        <option value="offline" <?= $feed['status'] === 'offline' ? 'selected' : '' ?>>Offline</option>
                                        <option value="paused" <?= $feed['status'] === 'paused' ? 'selected' : '' ?>>Pausado</option>
                                        <option value="pending" <?= $feed['status'] === 'pending' ? 'selected' : '' ?>>Pendente</option>
                                        <option value="rejected" <?= $feed['status'] === 'rejected' ? 'selected' : '' ?>>Rejeitado</option>
                                    </select>
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
                                <td class="align-middle text-end">
                                    <div class="text-truncate">
                                    <a href="/admin/feeds/<?= $feed['id'] ?>/edit" class="d-inline-block btn btn-sm btn-outline-primary me-2">
                                        <i class="bi bi-pencil"></i>
                                    </a><button class="d-inline-block btn btn-sm btn-outline-danger delete-feed" data-feed-id="<?= $feed['id'] ?>">
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

        <!-- Pagination -->
        <?php if ($pagination['total'] > 1): ?>
            <div class="card-footer">
                <nav aria-label="Navegação de páginas">
                    <ul class="pagination pagination-sm mb-0 justify-content-center">
                        <?php if ($pagination['current'] > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $pagination['baseUrl'] ?>&page=<?= $pagination['current'] - 1 ?>">
                                    <i class="bi bi-chevron-left"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link"><i class="bi bi-chevron-left"></i></span>
                            </li>
                        <?php endif; ?>

                        <?php
                        $start = max(1, $pagination['current'] - 2);
                        $end = min($pagination['total'], $pagination['current'] + 2);
                        
                        if ($start > 1):
                        ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $pagination['baseUrl'] ?>&page=1">1</a>
                            </li>
                            <?php if ($start > 2): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php for ($i = $start; $i <= $end; $i++): ?>
                            <li class="page-item <?= $i === $pagination['current'] ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $pagination['baseUrl'] ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>

                        <?php if ($end < $pagination['total']): ?>
                            <?php if ($end < $pagination['total'] - 1): ?>
                                <li class="page-item disabled"><span class="page-link">...</span></li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $pagination['baseUrl'] ?>&page=<?= $pagination['total'] ?>"><?= $pagination['total'] ?></a>
                            </li>
                        <?php endif; ?>

                        <?php if ($pagination['current'] < $pagination['total']): ?>
                            <li class="page-item">
                                <a class="page-link" href="<?= $pagination['baseUrl'] ?>&page=<?= $pagination['current'] + 1 ?>">
                                    <i class="bi bi-chevron-right"></i>
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="page-item disabled">
                                <span class="page-link"><i class="bi bi-chevron-right"></i></span>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="delete-modal" tabindex="-1" aria-labelledby="modal-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modal-title">Excluir Feed</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="d-flex">
                    <div class="me-3">
                        <i class="bi bi-exclamation-triangle text-danger fs-4"></i>
                    </div>
                    <div>
                        <p class="mb-0">
                            Tem certeza que deseja excluir este feed? Todos os itens do feed também serão excluídos. Esta ação não pode ser desfeita.
                        </p>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" id="cancel-delete" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-danger" id="confirm-delete">
                    <i class="bi bi-trash me-1"></i>
                    Excluir
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Edit Categories Modal -->
<div class="modal fade" id="bulk-categories-modal" tabindex="-1" aria-labelledby="bulk-categories-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulk-categories-title">Editar Categorias em Lote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary mb-3">
                    Selecione as categorias que deseja aplicar aos <span id="bulk-cat-count">0</span> feed(s) selecionado(s).
                    As categorias atuais serão substituídas.
                </p>
                <div id="categories-list">
                    <?php foreach ($allCategories as $category): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input category-checkbox" type="checkbox" value="<?= $category['id'] ?>" id="cat-<?= $category['id'] ?>">
                            <label class="form-check-label" for="cat-<?= $category['id'] ?>">
                                <?= $this->e($category['name']) ?>
                                <?php if (!empty($category['description'])): ?>
                                    <small class="text-secondary d-block"><?= $this->e($category['description']) ?></small>
                                <?php endif; ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="confirm-bulk-categories">
                    <i class="bi bi-check-lg me-1"></i>
                    Aplicar Categorias
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bulk Edit Tags Modal -->
<div class="modal fade" id="bulk-tags-modal" tabindex="-1" aria-labelledby="bulk-tags-title" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="bulk-tags-title">Editar Tags em Lote</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="text-secondary mb-3">
                    Selecione as tags que deseja aplicar aos <span id="bulk-tag-count">0</span> feed(s) selecionado(s).
                    As tags atuais serão substituídas.
                </p>
                <div id="tags-list">
                    <?php foreach ($allTags as $tag): ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input tag-checkbox" type="checkbox" value="<?= $tag['id'] ?>" id="tag-<?= $tag['id'] ?>">
                            <label class="form-check-label" for="tag-<?= $tag['id'] ?>">
                                <?= $this->e($tag['name']) ?>
                            </label>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-lg me-1"></i>
                    Cancelar
                </button>
                <button type="button" class="btn btn-primary" id="confirm-bulk-tags">
                    <i class="bi bi-check-lg me-1"></i>
                    Aplicar Tags
                </button>
            </div>
        </div>
    </div>
</div>

<?php $this->start('scripts') ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const deleteModal = new bootstrap.Modal(document.getElementById('delete-modal'));
        const bulkCategoriesModal = new bootstrap.Modal(document.getElementById('bulk-categories-modal'));
        const bulkTagsModal = new bootstrap.Modal(document.getElementById('bulk-tags-modal'));

        const cancelDeleteButton = document.getElementById('cancel-delete');
        const confirmDeleteButton = document.getElementById('confirm-delete');
        const deleteFeedButtons = document.querySelectorAll('.delete-feed');
        const statusSelects = document.querySelectorAll('.status-select');
        
        // Bulk selection
        const selectAllCheckbox = document.getElementById('select-all');
        const feedCheckboxes = document.querySelectorAll('.feed-checkbox');
        const bulkCategoriesBtn = document.getElementById('bulk-categories-btn');
        const bulkTagsBtn = document.getElementById('bulk-tags-btn');
        const selectionCount = document.getElementById('selection-count');
        const countNumber = document.getElementById('count-number');

        // Select all functionality
        selectAllCheckbox.addEventListener('change', function() {
            feedCheckboxes.forEach(checkbox => {
                checkbox.checked = this.checked;
            });
            updateBulkButtons();
        });

        // Individual checkbox
        feedCheckboxes.forEach(checkbox => {
            checkbox.addEventListener('change', updateBulkButtons);
        });

        function updateBulkButtons() {
            const selectedCount = Array.from(feedCheckboxes).filter(cb => cb.checked).length;
            const hasSelection = selectedCount > 0;
            
            bulkCategoriesBtn.disabled = !hasSelection;
            bulkTagsBtn.disabled = !hasSelection;
            
            if (hasSelection) {
                selectionCount.style.display = 'inline';
                countNumber.textContent = selectedCount;
            } else {
                selectionCount.style.display = 'none';
            }
            
            // Update select all checkbox state
            selectAllCheckbox.checked = selectedCount === feedCheckboxes.length;
            selectAllCheckbox.indeterminate = selectedCount > 0 && selectedCount < feedCheckboxes.length;
        }

        // Bulk categories
        bulkCategoriesBtn.addEventListener('click', function() {
            const selectedCount = Array.from(feedCheckboxes).filter(cb => cb.checked).length;
            document.getElementById('bulk-cat-count').textContent = selectedCount;
            bulkCategoriesModal.show();
        });

        document.getElementById('confirm-bulk-categories').addEventListener('click', function() {
            const selectedFeeds = Array.from(feedCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => parseInt(cb.value));
            
            const selectedCategories = Array.from(document.querySelectorAll('.category-checkbox:checked'))
                .map(cb => parseInt(cb.value));
            
            if (selectedCategories.length === 0) {
                alert('Selecione pelo menos uma categoria');
                return;
            }

            fetch('/admin/feeds/bulk/categories', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    feed_ids: selectedFeeds,
                    category_ids: selectedCategories
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bulkCategoriesModal.hide();
                    alert(data.message);
                    // Uncheck all
                    feedCheckboxes.forEach(cb => cb.checked = false);
                    document.querySelectorAll('.category-checkbox').forEach(cb => cb.checked = false);
                    updateBulkButtons();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocorreu um erro ao atualizar as categorias.');
            });
        });

        // Bulk tags
        bulkTagsBtn.addEventListener('click', function() {
            const selectedCount = Array.from(feedCheckboxes).filter(cb => cb.checked).length;
            document.getElementById('bulk-tag-count').textContent = selectedCount;
            bulkTagsModal.show();
        });

        document.getElementById('confirm-bulk-tags').addEventListener('click', function() {
            const selectedFeeds = Array.from(feedCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => parseInt(cb.value));
            
            const selectedTags = Array.from(document.querySelectorAll('.tag-checkbox:checked'))
                .map(cb => parseInt(cb.value));
            
            if (selectedTags.length === 0) {
                alert('Selecione pelo menos uma tag');
                return;
            }

            fetch('/admin/feeds/bulk/tags', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    feed_ids: selectedFeeds,
                    tag_ids: selectedTags
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    bulkTagsModal.hide();
                    alert(data.message);
                    // Uncheck all
                    feedCheckboxes.forEach(cb => cb.checked = false);
                    document.querySelectorAll('.tag-checkbox').forEach(cb => cb.checked = false);
                    updateBulkButtons();
                } else {
                    alert('Erro: ' + data.message);
                }
            })
            .catch(error => {
                console.error('Error:', error);
                alert('Ocorreu um erro ao atualizar as tags.');
            });
        });

        // Delete feed
        deleteFeedButtons.forEach(button => {
            button.addEventListener('click', function() {
                const feedId = this.dataset.feedId;
                confirmDeleteButton.dataset.feedId = feedId;
                deleteModal.show();
            });
        });

        confirmDeleteButton.addEventListener('click', function() {
            const feedId = this.dataset.feedId;

            fetch(`/admin/feeds/${feedId}`, {
                    method: 'DELETE',
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const row = document.querySelector(`tr[data-feed-id="${feedId}"]`);
                        row.remove();

                        deleteModal.hide();

                        if (document.querySelectorAll('tbody tr').length === 0) {
                            window.location.reload();
                        }
                        
                        updateBulkButtons();
                    } else {
                        alert('Erro ao excluir feed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ocorreu um erro ao excluir o feed.');
                });
        });

        // Status change
        statusSelects.forEach(select => {
            select.addEventListener('change', function() {
                const feedId = this.dataset.feedId;
                const newStatus = this.value;
                const originalValue = this.dataset.originalValue;

                fetch(`/admin/feeds/${feedId}`, {
                        method: 'PUT',
                        headers: {
                            'Content-Type': 'application/json',
                        },
                        body: JSON.stringify({
                            status: newStatus
                        }),
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            this.dataset.originalValue = newStatus;
                            alert('Status do feed atualizado com sucesso para: ' + newStatus);
                        } else {
                            this.value = originalValue;
                            alert('Erro ao atualizar status do feed: ' + data.message);
                        }
                    })
                    .catch(error => {
                        this.value = originalValue;
                        console.error('Error:', error);
                        alert('Ocorreu um erro ao atualizar o status do feed.');
                    });
            });
        });
    });
</script>
<?php $this->stop() ?>