<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>admin<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header d-flex justify-content-between align-items-center">
        <div>
            <h3 class="fs-5 fw-medium mb-0 mt-1">
                <i class="bi bi-collection me-1"></i>
                Gerenciar artigos de feeds
            </h3>
        </div>
        <div>
            <form action="/admin" method="GET" class="d-flex gap-2">
                <div>
                    <select name="feed" class="form-select">
                        <option value="">Feeds</option>
                        <?php foreach ($feeds as $feed): ?>
                            <option value="<?= $feed['id'] ?>" <?= $selectedFeed == $feed['id'] ? 'selected' : '' ?>>
                                <?= $this->e($feed['title']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="input-group">
                    <input type="text" name="search" value="<?= $this->e($search) ?>" class="form-control" placeholder="Pesquisar...">
                    <button type="submit" class="btn btn-primary d-flex align-items-center">
                        <i class="bi bi-search me-1"></i>
                        Pesquisar
                    </button>
                </div>
            </form>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="card-body text-center p-4">
            <p class="text-secondary mb-0">
                <i class="bi bi-exclamation-circle me-1"></i>
                Nenhum item encontrado. Tente ajustar sua pesquisa ou filtro.
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
                                    Título
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-journal-text me-1"></i>
                                    Feed
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-person me-1"></i>
                                    Autor
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-calendar me-1"></i>
                                    Data
                                </div>
                            </th>
                            <th scope="col"></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td class="align-middle fw-medium">
                                    <a href="<?= $this->e($item['url']) ?>" target="_blank" class="text-decoration-none">
                                        <?= $this->e($item['title']) ?>
                                        <i class="bi bi-box-arrow-up-right ms-1"></i>
                                    </a>
                                </td>
                                <td class="align-middle">
                                    <?= $this->e($item['feed_title']) ?>
                                </td>
                                <td class="align-middle">
                                    <?= $this->e($item['author'] ?? 'Desconhecido') ?>
                                </td>
                                <td class="small text-secondary align-middle">
                                    <?= date('j M Y', strtotime($item['published_at'])) ?>
                                </td>
                                <td class="align-middle text-end">
                                    <button
                                        data-id="<?= $item['id'] ?>"
                                        data-visible="<?= $item['is_visible'] ? '1' : '0' ?>"
                                        class="d-inline-block btn btn-sm btn-outline-primary toggle-visibility">
                                        <?php if ($item['is_visible']): ?>
                                            <i class="bi bi-eye-slash"></i>
                                        <?php else: ?>
                                            <i class="bi bi-eye"></i>
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
            <div class="card-footer d-flex justify-content-between align-items-center">
                <div class="d-flex d-sm-none w-100 justify-content-between">
                    <?php if ($pagination['current'] > 1): ?>
                        <a href="<?= $pagination['baseUrl'] ?>&page=<?= $pagination['current'] - 1 ?>" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                            <i class="bi bi-chevron-left me-1"></i>
                            Anterior
                        </a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
                    <?php if ($pagination['current'] < $pagination['total']): ?>
                        <a href="<?= $pagination['baseUrl'] ?>&page=<?= $pagination['current'] + 1 ?>" class="btn btn-outline-secondary btn-sm d-flex align-items-center">
                            Próximo
                            <i class="bi bi-chevron-right ms-1"></i>
                        </a>
                    <?php else: ?>
                        <div></div>
                    <?php endif; ?>
                </div>
                <div class="d-none d-sm-flex justify-content-between align-items-center w-100">
                    <div>
                        <p class="small text-secondary mb-0">
                            Mostrando página <span class="fw-medium"><?= $pagination['current'] ?></span> de <span class="fw-medium"><?= $pagination['total'] ?></span>
                        </p>
                    </div>
                    <div>
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
                            // Update button text and icon
                            if (newVisible) {
                                this.innerHTML = '<i class="bi bi-eye-slash me-1"></i> Ocultar';
                            } else {
                                this.innerHTML = '<i class="bi bi-eye me-1"></i> Mostrar';
                            }
                            this.dataset.visible = newVisible ? '1' : '0';

                            // Update visibility badge
                            const row = this.closest('tr');
                            const badge = row.querySelector('td:nth-child(5) span');

                            if (newVisible) {
                                badge.innerHTML = '<i class="bi bi-eye me-1"></i> Visível';
                                badge.classList.remove('bg-danger');
                                badge.classList.add('bg-success');
                            } else {
                                badge.innerHTML = '<i class="bi bi-eye-slash me-1"></i> Oculto';
                                badge.classList.remove('bg-success');
                                badge.classList.add('bg-danger');
                            }
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