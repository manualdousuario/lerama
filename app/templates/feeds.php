<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>feeds<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h3 class="fs-5 fw-medium mb-0 mt-1">
            <i class="bi bi-journal-text me-1"></i>
            Feeds
        </h3>
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
                                    <i class="bi bi-book me-1"></i>
                                    Tipo
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-clock me-1"></i>
                                    Status
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-clock-history me-1"></i>
                                    Última Verificação
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-arrow-repeat me-1"></i>
                                    Última Atualização
                                </div>
                            </th>
                            <th scope="col" class="small text-uppercase">
                                <div class="d-flex align-items-center">
                                    <i class="bi bi-collection me-1"></i>
                                    Itens
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
                                <td class="align-middle fw-medium">
                                    <?= $this->e(strtoupper($feed['feed_type'])) ?>
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
                                    <?= $feed['last_checked'] ? date('M j, Y H:i', strtotime($feed['last_checked'])) : 'Nunca' ?>
                                </td>
                                <td class="align-middle small text-secondary">
                                    <?= $feed['last_updated'] ? date('M j, Y H:i', strtotime($feed['last_updated'])) : 'Nunca' ?>
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
    <?php endif; ?>
</div>