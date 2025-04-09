<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>login<?php $this->stop() ?>

<div class="min-vh-100 d-flex align-items-center justify-content-center py-5">
    <div class="card shadow-sm" style="max-width: 400px; width: 100%;">
        <div class="card-body p-4">
            <div class="text-center mb-4">
                <h2 class="fs-1 fw-bold">
                    <i class="bi bi-file-earmark-lock-fill me-2"></i>
                </h2>
            </div>

            <?php if (isset($error)): ?>
                <div class="alert alert-danger d-flex align-items-center" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <div>
                        <?= $this->e($error) ?>
                    </div>
                </div>
            <?php endif; ?>

            <form action="/admin/login" method="POST">
                <div class="mb-3">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-person"></i>
                        </span>
                        <input id="username" name="username" type="text" required class="form-control" placeholder="Nome de usuário">
                    </div>
                </div>
                <div class="mb-4">
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-lock"></i>
                        </span>
                        <input id="password" name="password" type="password" required class="form-control" placeholder="Senha">
                    </div>
                </div>

                <div class="d-grid">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-box-arrow-in-right me-2"></i>
                        Entrar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>