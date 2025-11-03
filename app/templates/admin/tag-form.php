<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>admin-tags<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h3 class="fs-5 fw-medium mb-0">
            <i class="bi bi-tags me-1"></i>
            <?= $this->e($title) ?>
        </h3>
    </div>
    
    <div class="card-body">
        <form method="POST">
            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle me-2"></i>
                    <?= $this->e($errors['general']) ?>
                </div>
            <?php endif; ?>

            <div class="mb-3">
                <label class="form-label fw-medium" for="name">
                    Nome *
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-tags"></i>
                    </span>
                    <input 
                        class="form-control <?= !empty($errors['name']) ? 'is-invalid' : '' ?>" 
                        id="name" 
                        name="name" 
                        type="text" 
                        value="<?= $this->e($tag['name'] ?? '') ?>" 
                        required
                    >
                </div>
                <?php if (!empty($errors['name'])): ?>
                    <div class="invalid-feedback d-block">
                        <?= $this->e($errors['name']) ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <div class="mb-3">
                <label class="form-label fw-medium" for="slug">
                    Slug
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-link-45deg"></i>
                    </span>
                    <input 
                        class="form-control <?= !empty($errors['slug']) ? 'is-invalid' : '' ?>" 
                        id="slug" 
                        name="slug" 
                        type="text" 
                        value="<?= $this->e($tag['slug'] ?? '') ?>"
                    >
                </div>
                <div class="form-text text-secondary">Deixe em branco para gerar automaticamente</div>
                <?php if (!empty($errors['slug'])): ?>
                    <div class="invalid-feedback d-block">
                        <?= $this->e($errors['slug']) ?>
                    </div>
                <?php endif; ?>
            </div>

            <div class="d-flex gap-2 justify-content-end">
                <a href="/admin/tags" class="btn btn-secondary">
                    <i class="bi bi-x-lg me-1"></i>
                    Cancelar
                </a>
                <button class="btn btn-primary" type="submit">
                    <i class="bi bi-check-lg me-1"></i>
                    Salvar
                </button>
            </div>
        </form>
    </div>
</div>