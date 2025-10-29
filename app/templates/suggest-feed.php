<?php $this->layout('layout', ['title' => $title]) ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h3 class="fs-5 fw-medium mb-0 mt-1">
            <i class="bi bi-megaphone me-1"></i>
            Sugerir
        </h3>
    </div>

    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <div>
                    <?= $this->e($success) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                <i class="bi bi-exclamation-circle-fill me-2"></i>
                <div>
                    <?= $this->e($errors['general']) ?>
                </div>
            </div>
        <?php endif; ?>

        <p class="text-secondary mb-4">
            Conhece um blog interessante que deveria estar no nosso agregador? Sugira aqui!
            Valide que o feed é acessível antes de enviar a sugestão.
        </p>

        <form id="suggest-form" method="POST" action="/suggest-feed">
            <div class="mb-3">
                <label for="title" class="form-label">
                    Título do Site
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-file-text"></i>
                    </span>
                    <input type="text" name="title" id="title"
                           value="<?= $this->e($data['title'] ?? '') ?>"
                           class="form-control" required
                           placeholder="Ex: Blog do João">
                </div>
                <?php if (isset($errors['title'])): ?>
                    <div class="form-text text-danger"><?= $this->e($errors['title']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="feed_url" class="form-label">
                    URL do Feed (RSS/Atom)
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-journal-text"></i>
                    </span>
                    <input type="url" name="feed_url" id="feed_url"
                           value="<?= $this->e($data['feed_url'] ?? '') ?>"
                           class="form-control" required
                           placeholder="https://exemplo.com/feed.xml">
                </div>
                <div class="form-text text-secondary">A URL do arquivo RSS/Atom do blog</div>
                <?php if (isset($errors['feed_url'])): ?>
                    <div class="form-text text-danger"><?= $this->e($errors['feed_url']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="site_url" class="form-label">
                    URL do Blog
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-link-45deg"></i>
                    </span>
                    <input type="url" name="site_url" id="site_url"
                           value="<?= $this->e($data['site_url'] ?? '') ?>"
                           class="form-control" required
                           placeholder="https://exemplo.com">
                </div>
                <div class="form-text text-secondary">A URL principal do site/blog</div>
                <?php if (isset($errors['site_url'])): ?>
                    <div class="form-text text-danger"><?= $this->e($errors['site_url']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="language" class="form-label">
                    Idioma
                </label>
                <div class="input-group">
                    <span class="input-group-text">
                        <i class="bi bi-translate"></i>
                    </span>
                    <select name="language" id="language" class="form-select" required>
                        <option value="pt-BR" <?= ($data['language'] ?? 'pt-BR') === 'pt-BR' ? 'selected' : '' ?>>Português (Brasil)</option>
                        <option value="en" <?= ($data['language'] ?? '') === 'en' ? 'selected' : '' ?>>Inglês</option>
                        <option value="es" <?= ($data['language'] ?? '') === 'es' ? 'selected' : '' ?>>Espanhol</option>
                    </select>
                </div>
                <?php if (isset($errors['language'])): ?>
                    <div class="form-text text-danger"><?= $this->e($errors['language']) ?></div>
                <?php endif; ?>
            </div>

            <div class="mb-3">
                <label for="captcha" class="form-label">
                    Código de Verificação
                </label>
                <div>
                        <div class="input-group">
                            <span class="input-group-text">
                                <img src="/captcha" alt="CAPTCHA" id="captcha-image" class="rounded" style="cursor: pointer;" title="Clique para atualizar">
                            </span>
                            <input type="text" name="captcha" id="captcha"
                                   class="form-control" required
                                   placeholder="Digite o código ao lado"
                                   autocomplete="off">
                        </div>
                        <div class="form-text text-secondary">
                            <small>Clique na imagem para gerar um novo código</small>
                        </div>
                        <?php if (isset($errors['captcha'])): ?>
                            <div class="form-text text-danger"><?= $this->e($errors['captcha']) ?></div>
                        <?php endif; ?>
                </div>
            </div>

            <div class="d-flex justify-content-end gap-2 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-send me-1"></i>
                    Enviar Sugestão
                </button>
            </div>
        </form>
    </div>
</div>

<?php $this->start('scripts') ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const suggestForm = document.getElementById('suggest-form');
        const captchaImage = document.getElementById('captcha-image');
        const captchaInput = document.getElementById('captcha');

        // Refresh captcha on click
        captchaImage.addEventListener('click', function() {
            this.src = '/captcha?' + new Date().getTime();
            captchaInput.value = '';
            captchaInput.focus();
        });

        suggestForm.addEventListener('submit', function() {
            const submitButton = suggestForm.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Validando feed...';
            submitButton.disabled = true;
        });
    });
</script>
<?php $this->stop() ?>