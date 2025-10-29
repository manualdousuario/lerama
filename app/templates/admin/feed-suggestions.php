<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>admin-suggestions<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h3 class="fs-5 fw-medium mb-0 mt-1">
            <i class="bi bi-lightbulb me-1"></i>
            Sugestões de Feeds
        </h3>
    </div>

    <div class="card-body">
        <div class="btn-group mb-3" role="group" aria-label="Filtrar sugestões">
            <a href="/admin/feed-suggestions?status=pending" 
               class="btn <?= $currentStatus === 'pending' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <i class="bi bi-hourglass-split me-1"></i>
                Pendentes
            </a>
            <a href="/admin/feed-suggestions?status=online" 
               class="btn <?= $currentStatus === 'online' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <i class="bi bi-check-circle me-1"></i>
                Aprovadas
            </a>
            <a href="/admin/feed-suggestions?status=rejected" 
               class="btn <?= $currentStatus === 'rejected' ? 'btn-primary' : 'btn-outline-secondary' ?>">
                <i class="bi bi-x-circle me-1"></i>
                Rejeitadas
            </a>
        </div>

        <?php if (empty($suggestions)): ?>
            <div class="text-center p-4">
                <p class="text-secondary mb-0">
                    <i class="bi bi-exclamation-circle me-1"></i>
                    Nenhuma sugestão encontrada
                </p>
            </div>
        <?php else: ?>
            <div class="list-group">
                <?php foreach ($suggestions as $suggestion): ?>
                    <div class="list-group-item" data-id="<?= $suggestion['id'] ?>">
                        <div class="d-flex w-100 justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <h5 class="mb-2 fw-medium">
                                    <i class="bi bi-journal-text me-1"></i>
                                    <?= $this->e($suggestion['title']) ?>
                                </h5>
                                
                                <div class="mb-2">
                                    <p class="mb-1">
                                        <strong><i class="bi bi-rss me-1"></i>Feed URL:</strong>
                                        <a href="<?= $this->e($suggestion['feed_url']) ?>" target="_blank" class="text-decoration-none">
                                            <?= $this->e($suggestion['feed_url']) ?>
                                            <i class="bi bi-box-arrow-up-right ms-1"></i>
                                        </a>
                                    </p>
                                    <p class="mb-1">
                                        <strong><i class="bi bi-globe me-1"></i>Site URL:</strong>
                                        <a href="<?= $this->e($suggestion['site_url']) ?>" target="_blank" class="text-decoration-none">
                                            <?= $this->e($suggestion['site_url']) ?>
                                            <i class="bi bi-box-arrow-up-right ms-1"></i>
                                        </a>
                                    </p>
                                    <p class="mb-1">
                                        <strong><i class="bi bi-translate me-1"></i>Idioma:</strong>
                                        <?= $this->e($suggestion['language']) ?>
                                    </p>
                                    <?php if ($suggestion['feed_type']): ?>
                                        <p class="mb-1">
                                            <strong><i class="bi bi-file-code me-1"></i>Tipo de Feed:</strong>
                                            <?= $this->e(strtoupper($suggestion['feed_type'])) ?>
                                        </p>
                                    <?php endif; ?>
                                    <?php if (!empty($suggestion['submitter_email'])): ?>
                                        <p class="mb-1">
                                            <strong><i class="bi bi-envelope me-1"></i>Email do Solicitante:</strong>
                                            <?= $this->e($suggestion['submitter_email']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="mb-1">
                                        <strong><i class="bi bi-calendar me-1"></i>Data da Sugestão:</strong>
                                        <?= date('d/m/Y H:i', strtotime($suggestion['created_at'])) ?>
                                    </p>
                                    <p class="mb-0">
                                        <strong><i class="bi bi-info-circle me-1"></i>Status:</strong>
                                        <?php if ($suggestion['status'] === 'online'): ?>
                                            <span class="badge bg-success">
                                                <i class="bi bi-check-lg me-1"></i>Aprovado
                                            </span>
                                        <?php elseif ($suggestion['status'] === 'rejected'): ?>
                                            <span class="badge bg-danger">
                                                <i class="bi bi-x-lg me-1"></i>Rejeitado
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-warning text-dark">
                                                <i class="bi bi-hourglass-split me-1"></i>Pendente
                                            </span>
                                        <?php endif; ?>
                                    </p>
                                </div>
                            </div>
                            
                            <?php if ($currentStatus === 'pending'): ?>
                                <div class="ms-3 d-flex flex-column gap-2">
                                    <button class="btn btn-success btn-sm approve-btn" data-id="<?= $suggestion['id'] ?>">
                                        <i class="bi bi-check-lg me-1"></i>
                                        Aprovar
                                    </button>
                                    <button class="btn btn-danger btn-sm reject-btn" data-id="<?= $suggestion['id'] ?>">
                                        <i class="bi bi-x-lg me-1"></i>
                                        Rejeitar
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php $this->start('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Approve suggestion
    document.querySelectorAll('.approve-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            if (confirm('Tem certeza que deseja aprovar esta sugestão? Um novo feed será criado.')) {
                const submitButton = this;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Aprovando...';
                submitButton.disabled = true;

                fetch(`/admin/feed-suggestions/${id}/approve`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Sugestão aprovada com sucesso!');
                        location.reload();
                    } else {
                        alert(data.message || 'Erro ao aprovar sugestão');
                        submitButton.innerHTML = '<i class="bi bi-check-lg me-1"></i> Aprovar';
                        submitButton.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ocorreu um erro ao aprovar a sugestão.');
                    submitButton.innerHTML = '<i class="bi bi-check-lg me-1"></i> Aprovar';
                    submitButton.disabled = false;
                });
            }
        });
    });

    // Reject suggestion
    document.querySelectorAll('.reject-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.dataset.id;
            if (confirm('Tem certeza que deseja rejeitar esta sugestão?')) {
                const submitButton = this;
                submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-1" role="status" aria-hidden="true"></span> Rejeitando...';
                submitButton.disabled = true;

                fetch(`/admin/feed-suggestions/${id}/reject`, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('Sugestão rejeitada');
                        location.reload();
                    } else {
                        alert(data.message || 'Erro ao rejeitar sugestão');
                        submitButton.innerHTML = '<i class="bi bi-x-lg me-1"></i> Rejeitar';
                        submitButton.disabled = false;
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Ocorreu um erro ao rejeitar a sugestão.');
                    submitButton.innerHTML = '<i class="bi bi-x-lg me-1"></i> Rejeitar';
                    submitButton.disabled = false;
                });
            }
        });
    });
});
</script>
<?php $this->stop() ?>