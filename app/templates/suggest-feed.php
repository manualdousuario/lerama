<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>suggest-feed<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h1 class="fs-5 fw-medium mb-1 mt-1">
            <i class="bi bi-megaphone me-1" aria-hidden="true"></i>
            <?= __('suggest.heading') ?>
        </h1>
    </div>

    <div class="card-body">
        <?php if (isset($success)): ?>
            <div class="alert alert-success d-flex align-items-center mb-4" role="status" aria-live="polite">
                <i class="bi bi-check-circle-fill me-2" aria-hidden="true"></i>
                <div>
                    <?= $this->e($success) ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($errors['general'])): ?>
            <div class="alert alert-danger d-flex align-items-center mb-4" role="alert" aria-live="assertive">
                <i class="bi bi-exclamation-circle-fill me-2" aria-hidden="true"></i>
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
                        <span class="input-group-text" aria-hidden="true">
                            <i class="bi bi-file-text"></i>
                        </span>
                        <input type="text" name="title" id="title"
                            value="<?= $this->e($data['title'] ?? '') ?>"
                            class="form-control" required
                            <?= isset($errors['title']) ? 'aria-invalid="true" aria-describedby="title-error"' : '' ?>
                            placeholder="<?= __('suggest.form.title_placeholder') ?>">
                    </div>
                    <?php if (isset($errors['title'])): ?>
                        <div id="title-error" class="form-text text-danger"><?= $this->e($errors['title']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-md-6 mb-3">
                    <label for="site_url" class="form-label">
                        <?= __('suggest.form.site_url') ?>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">
                            <i class="bi bi-link-45deg"></i>
                        </span>
                        <input type="url" name="site_url" id="site_url"
                            value="<?= $this->e($data['site_url'] ?? '') ?>"
                            class="form-control" required
                            aria-describedby="site_url-help<?= isset($errors['site_url']) ? ' site_url-error' : '' ?>"
                            <?= isset($errors['site_url']) ? 'aria-invalid="true"' : '' ?>
                            placeholder="https://exemplo.com">
                    </div>
                    <div id="site_url-help" class="form-text text-secondary"><?= __('suggest.form.site_url_help') ?></div>
                    <?php if (isset($errors['site_url'])): ?>
                        <div id="site_url-error" class="form-text text-danger"><?= $this->e($errors['site_url']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="col-12 col-md-6 mb-3">
                    <label for="feed_url" class="form-label">
                        <?= __('suggest.form.feed_url') ?>
                    </label>
                    <div class="input-group">
                        <span class="input-group-text" aria-hidden="true">
                            <i class="bi bi-journal-text"></i>
                        </span>
                        <input type="url" name="feed_url" id="feed_url"
                            value="<?= $this->e($data['feed_url'] ?? '') ?>"
                            class="form-control" required
                            aria-describedby="feed_url-help<?= isset($errors['feed_url']) ? ' feed_url-error' : '' ?>"
                            <?= isset($errors['feed_url']) ? 'aria-invalid="true"' : '' ?>
                            placeholder="https://exemplo.com/feed.xml">
                    </div>
                    <div id="feed_url-help" class="form-text text-secondary"><?= __('suggest.form.feed_url_help') ?></div>
                    <?php if (isset($errors['feed_url'])): ?>
                        <div id="feed_url-error" class="form-text text-danger"><?= $this->e($errors['feed_url']) ?></div>
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
                        <label for="tags" class="form-label">
                            <?= __('suggest.form.tags') ?>
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-tag"></i>
                            </span>
                            <select name="tags[]" id="tags" class="form-select" required>
                                <option value=""><?= __('suggest.form.select_tag') ?></option>
                                <?php foreach ($tags as $tag): ?>
                                    <option value="<?= $tag['id'] ?>"
                                        <?= in_array($tag['id'], ($data['selected_tags'] ?? [])) ? 'selected' : '' ?>>
                                        <?= $this->e($tag['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (isset($errors['tags'])): ?>
                            <div class="form-text text-danger"><?= $this->e($errors['tags']) ?></div>
                        <?php endif; ?>
                    </div>
                <?php endif; ?>

                <div class="col-12 mb-3">
                    <label for="captcha" class="form-label">
                        <?= __('suggest.form.captcha') ?>
                    </label>
                    <div>
                        <div class="input-group">
                            <button type="button" id="captcha-image-btn" class="input-group-text p-1 border" aria-label="<?= __('a11y.refresh_captcha') ?>" style="cursor: pointer;">
                                <img src="/captcha" alt="" id="captcha-image" class="rounded" aria-hidden="true">
                            </button>
                            <input type="text" name="captcha" id="captcha"
                                class="form-control" required
                                aria-describedby="captcha-help<?= isset($errors['captcha']) ? ' captcha-error' : '' ?>"
                                <?= isset($errors['captcha']) ? 'aria-invalid="true"' : '' ?>
                                placeholder="<?= __('suggest.form.captcha_placeholder') ?>"
                                autocomplete="off">
                        </div>
                        <div id="captcha-help" class="form-text text-secondary">
                            <small><?= __('suggest.form.captcha_help') ?></small>
                        </div>
                        <?php if (isset($errors['captcha'])): ?>
                            <div id="captcha-error" class="form-text text-danger"><?= $this->e($errors['captcha']) ?></div>
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
window.LERAMA = window.LERAMA || {};
window.LERAMA.i18n = window.LERAMA.i18n || {};
window.LERAMA.i18n.suggestFeedUrlSameAsSite = <?= json_encode(__('suggest.form.feed_url_same_as_site') ?? 'O URL do feed não pode ser o mesmo que o URL do site') ?>;
window.LERAMA.i18n.suggestValidating = <?= json_encode(__('suggest.form.validating')) ?>;
</script>
<script src="/assets/js/suggest-feed.min.js"></script>
<?php $this->stop() ?>