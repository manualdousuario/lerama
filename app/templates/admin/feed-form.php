<?php $this->layout('layout', ['title' => $title]) ?>

<?php $this->start('active') ?>admin-feeds<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h3 class="fs-5 fw-medium mb-0">
            <?php if ($isEdit): ?>
                <i class="bi bi-pencil me-1"></i>
                Editar Feed
            <?php else: ?>
                <i class="bi bi-plus-lg me-1"></i>
                Adicionar Novo Feed
            <?php endif; ?>
        </h3>
    </div>

    <div class="card-body">
        <form id="feed-form" method="POST">
            <?php if ($isEdit): ?>
                <input type="hidden" id="feed-id" name="feed-id" value="<?= $feed['id'] ?>">
            <?php endif; ?>

            <?php if (isset($errors['general'])): ?>
                <div class="alert alert-danger d-flex align-items-center mb-4" role="alert">
                    <i class="bi bi-exclamation-circle-fill me-2"></i>
                    <div>
                        <?= $this->e($errors['general']) ?>
                    </div>
                </div>
            <?php endif; ?>

            <div>
                <div class="mb-3">
                    <label for="title" class="form-label">
                        Título do Site
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-file-text"></i>
                        </span>
                        <input type="text" name="title" id="title" value="<?= $isEdit ? $this->e($feed['title']) : '' ?>" class="form-control" required>
                    </div>
                    <?php if (isset($errors['title'])): ?>
                        <div class="form-text text-danger"><?= $this->e($errors['title']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="feed_url" class="form-label">
                        URL do Feed
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-journal-text"></i>
                        </span>
                        <input type="url" name="feed_url" id="feed_url" value="<?= $isEdit ? $this->e($feed['feed_url']) : '' ?>" class="form-control" required>
                    </div>
                    <?php if (isset($errors['feed_url'])): ?>
                        <div class="form-text text-danger"><?= $this->e($errors['feed_url']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="site_url" class="form-label">
                        URL do Site
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-link-45deg"></i>
                        </span>
                        <input type="url" name="site_url" id="site_url" value="<?= $isEdit ? $this->e($feed['site_url']) : '' ?>" class="form-control" required>
                    </div>
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
                            <option value="pt-BR" <?= $isEdit && $feed['language'] === 'pt-BR' ? 'selected' : '' ?>>Português (Brasil)</option>
                            <option value="en" <?= $isEdit && $feed['language'] === 'en' ? 'selected' : '' ?>>Inglês</option>
                            <option value="es" <?= $isEdit && $feed['language'] === 'es' ? 'selected' : '' ?>>Espanhol</option>
                        </select>
                    </div>
                    <?php if (isset($errors['language'])): ?>
                        <div class="form-text text-danger"><?= $this->e($errors['language']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="feed_type" class="form-label">
                        Tipo de Feed
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-book"></i>
                        </span>
                        <select name="feed_type" id="feed_type" class="form-select">
                            <option value="">Auto-detectar tipo de feed</option>
                            <option value="rss1" <?= $isEdit && $feed['feed_type'] === 'rss1' ? 'selected' : '' ?>>RSS 1.0</option>
                            <option value="rss2" <?= $isEdit && $feed['feed_type'] === 'rss2' ? 'selected' : '' ?>>RSS 2.0</option>
                            <option value="atom" <?= $isEdit && $feed['feed_type'] === 'atom' ? 'selected' : '' ?>>Atom</option>
                            <option value="rdf" <?= $isEdit && $feed['feed_type'] === 'rdf' ? 'selected' : '' ?>>RDF</option>
                            <option value="csv" <?= $isEdit && $feed['feed_type'] === 'csv' ? 'selected' : '' ?>>CSV</option>
                            <option value="json" <?= $isEdit && $feed['feed_type'] === 'json' ? 'selected' : '' ?>>JSON</option>
                            <option value="xml" <?= $isEdit && $feed['feed_type'] === 'xml' ? 'selected' : '' ?>>XML</option>
                        </select>
                    </div>
                    <div class="form-text text-secondary">Se não for selecionado, o sistema detectará automaticamente o tipo de feed.</div>
                    <?php if (isset($errors['feed_type'])): ?>
                        <div class="form-text text-danger"><?= $this->e($errors['feed_type']) ?></div>
                    <?php endif; ?>
                </div>

                <div class="mb-3">
                    <label for="categories" class="form-label">
                        Categorias
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-folder"></i>
                        </span>
                        <select name="categories[]" id="categories" class="form-select" multiple size="5">
                            <?php if (isset($allCategories) && !empty($allCategories)): ?>
                                <?php foreach ($allCategories as $category): ?>
                                    <option value="<?= $category['id'] ?>"
                                            <?= in_array($category['id'], $selectedCategories ?? []) ? 'selected' : '' ?>>
                                        <?= $this->e($category['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-text text-secondary">Mantenha Ctrl/Cmd pressionado para selecionar múltiplas categorias</div>
                </div>

                <div class="mb-3">
                    <label for="tags" class="form-label">
                        Tópicos
                    </label>
                    <div class="input-group">
                        <span class="input-group-text">
                            <i class="bi bi-tags"></i>
                        </span>
                        <select name="tags[]" id="tags" class="form-select" multiple size="5">
                            <?php if (isset($allTags) && !empty($allTags)): ?>
                                <?php foreach ($allTags as $tag): ?>
                                    <option value="<?= $tag['id'] ?>"
                                            <?= in_array($tag['id'], $selectedTags ?? []) ? 'selected' : '' ?>>
                                        <?= $this->e($tag['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </select>
                    </div>
                    <div class="form-text text-secondary">Mantenha Ctrl/Cmd pressionado para selecionar múltiplas tags</div>
                </div>

                <?php if ($isEdit): ?>
                    <div class="mb-3">
                        <label for="status" class="form-label">
                            Status
                        </label>
                        <div class="input-group">
                            <span class="input-group-text">
                                <i class="bi bi-clock"></i>
                            </span>
                            <select name="status" id="status" class="form-select">
                                <option value="online" <?= $feed['status'] === 'online' ? 'selected' : '' ?>>Online</option>
                                <option value="offline" <?= $feed['status'] === 'offline' ? 'selected' : '' ?>>Offline</option>
                                <option value="paused" <?= $feed['status'] === 'paused' ? 'selected' : '' ?>>Pausado</option>
                                <option value="pending" <?= $feed['status'] === 'pending' ? 'selected' : '' ?>>Pendente</option>
                                <option value="rejected" <?= $feed['status'] === 'rejected' ? 'selected' : '' ?>>Rejeitado</option>
                            </select>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="d-flex justify-content-end gap-2 mt-4">
                    <a href="/admin/feeds" class="btn btn-secondary">
                        <i class="bi bi-x-lg me-1"></i>
                        Cancelar
                    </a>
                    <button type="submit" class="btn btn-primary">
                        <?php if ($isEdit): ?>
                            <i class="bi bi-arrow-repeat me-1"></i>
                            Atualizar Feed
                        <?php else: ?>
                            <i class="bi bi-plus-lg me-1"></i>
                            Adicionar Feed
                        <?php endif; ?>
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php $this->start('scripts') ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const feedForm = document.getElementById('feed-form');

        feedForm.addEventListener('submit', function() {
            const submitButton = feedForm.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> Salvando...';
            submitButton.disabled = true;
        });
    });
</script>
<?php $this->stop() ?>