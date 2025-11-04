<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>suggest-feed<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h3 class="fs-5 fw-medium mb-1 mt-1">
            <i class="bi bi-megaphone me-1"></i>
            <?= __('suggest.heading') ?>
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
            <?= __('suggest.description') ?>
        </p>

        <form id="suggest-form" method="POST" action="/suggest-feed">
            <div class="row">
                <div class="col-12 mb-3">
                    <label for="title" class="form-label">
                        <?= __('suggest.form.title') ?>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-file-text"></i>
                        </span>
                        <input type="text" name="title" id="title"
                            value="<?= $this->e($data['title'] ?? '') ?>"
                            class="form-control" required
                            placeholder="<?= __('suggest.form.title_placeholder') ?>">
                    </div>
                    <?php if (isset($errors['title'])): ?>
                        <div class="form-text text-danger"><?= $this->e($errors['title']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-md-6 mb-3">
                    <label for="site_url" class="form-label">
                        <?= __('suggest.form.site_url') ?>
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
                    <div class="form-text text-secondary"><?= __('suggest.form.site_url_help') ?></div>
                    <?php if (isset($errors['site_url'])): ?>
                        <div class="form-text text-danger"><?= $this->e($errors['site_url']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-md-6 mb-3">
                    <label for="feed_url" class="form-label">
                        <?= __('suggest.form.feed_url') ?>
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
                    <div class="form-text text-secondary"><?= __('suggest.form.feed_url_help') ?></div>
                    <?php if (isset($errors['feed_url'])): ?>
                        <div class="form-text text-danger"><?= $this->e($errors['feed_url']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 <?= empty($categories) ? 'col-md-12' : 'col-md-4' ?> mb-3">
                    <label for="language" class="form-label">
                        <?= __('common.language') ?>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-translate"></i>
                        </span>
                        <select name="language" id="language" class="form-select" required>
                            <option value="pt-BR" <?= ($data['language'] ?? 'pt-BR') === 'pt-BR' ? 'selected' : '' ?>><?= __('lang.pt-BR') ?></option>
                            <option value="en" <?= ($data['language'] ?? '') === 'en' ? 'selected' : '' ?>><?= __('lang.en') ?></option>
                            <option value="es" <?= ($data['language'] ?? '') === 'es' ? 'selected' : '' ?>><?= __('lang.es') ?></option>
                        </select>
                    </div>
                    <?php if (isset($errors['language'])): ?>
                        <div class="form-text text-danger"><?= $this->e($errors['language']) ?></div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($categories)): ?>
                    <div class="col-12 col-md-4 mb-3">
                        <label for="category" class="form-label">
                            <?= __('suggest.form.category') ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-folder"></i>
                            </span>
                            <select name="category" id="category" class="form-select" required>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"
                                        <?= ($data['selected_category'] ?? '') == $category['id'] ? 'selected' : '' ?>>
                                        <?= $this->e($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (isset($errors['category'])): ?>
                            <div class="form-text text-danger"><?= $this->e($errors['category']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($tags)): ?>
                    <div class="col-12 col-md-4 mb-3">
                        <label for="tag" class="form-label">
                            <?= __('suggest.form.tags') ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-tag"></i>
                            </span>
                            <select name="tag" id="tag" class="form-select">
                                <option value=""><?= __('suggest.form.select_tag') ?></option>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?= $tag['id'] ?>"
                                        <?= ($data['selected_tag'] ?? '') == $tag['id'] ? 'selected' : '' ?>>
                                        <?= $this->e($tag['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (isset($errors['tag'])): ?>
                            <div class="form-text text-danger"><?= $this->e($errors['tag']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="col-12 mb-3">
                    <label for="captcha" class="form-label">
                        <?= __('suggest.form.captcha') ?>
                    </label>
                    <div>
                        <div class="input-group">
                            <span class="input-group-text">
                                <img src="/captcha" alt="CAPTCHA" id="captcha-image" class="rounded" style="cursor: pointer;" title="Clique para atualizar">
                            </span>
                            <input type="text" name="captcha" id="captcha"
                                class="form-control" required
                                placeholder="<?= __('suggest.form.captcha_placeholder') ?>"
                                autocomplete="off">
                        </div>
                        <div class="form-text text-secondary">
                            <small><?= __('suggest.form.captcha_help') ?></small>
                        </div>
                        <?php if (isset($errors['captcha'])): ?>
                            <div class="form-text text-danger"><?= $this->e($errors['captcha']) ?></div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <button type="submit" class="btn btn-primary">
                        <i class="bi bi-send me-1"></i>
                        <?= __('suggest.form.submit') ?>
                    </button>
                </div>
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
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> <?= __('suggest.form.validating') ?>';
            submitButton.disabled = true;
        });
    });
</script>
<?php $this->stop() ?>