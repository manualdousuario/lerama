<?php $this->layout('layout', ['title' => 'Construtor de Feed']) ?>

<?php $this->start('active') ?>feed-builder<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <h3 class="fs-5 fw-medium mb-1 mt-1">
            <i class="bi bi-braces me-1"></i>
            Construtor de Feed
        </h3>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6 mb-4">
                <h5 class="fs-6 fw-medium mb-3">
                    <i class="bi bi-folder me-1"></i>
                    Categorias
                </h5>
                <div class="list-group" style="max-height: 292px; overflow-y: auto;">
                    <?php foreach ($categories as $category): ?>
                        <label class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <input class="form-check-input me-2 category-checkbox" type="checkbox" value="<?= $this->e($category['slug']) ?>" data-name="<?= $this->e($category['name']) ?>">
                                <span><?= $this->e($category['name']) ?></span>
                            </div>
                            <span class="badge bg-secondary text-dark rounded-pill"><?= $category['item_count'] ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="col-md-6 mb-4">
                <h5 class="fs-6 fw-medium mb-3">
                    <i class="bi bi-tags me-1"></i>
                    Tópicos
                </h5>
                <div class="list-group" style="max-height: 292px; overflow-y: auto;">
                    <?php foreach ($tags as $tag): ?>
                        <label class="list-group-item d-flex justify-content-between align-items-center">
                            <div class="d-flex align-items-center">
                                <input class="form-check-input me-2 tag-checkbox" type="checkbox" value="<?= $this->e($tag['slug']) ?>" data-name="<?= $this->e($tag['name']) ?>">
                                <span><?= $this->e($tag['name']) ?></span>
                            </div>
                            <span class="badge bg-secondary rounded-pill"><?= $tag['item_count'] ?></span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <div class="row border-top pt-4">
            <div class="col-12 col-md-6">
                <label class="form-label fw-medium">
                    <i class="bi bi-rss me-1"></i>
                    Feed RSS
                </label>
                <div class="input-group">
                    <input type="text" class="form-control" id="rssUrl" readonly value="<?= $_ENV['APP_URL'] ?>/feed/rss">
                    <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('rssUrl')">
                        <i class="bi bi-clipboard"></i>
                    </button>
                    <a href="<?= $_ENV['APP_URL'] ?>/feed/rss" target="_blank" class="btn btn-outline-primary" id="rssLink">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
            </div>

            <div class="col-12 col-md-6">
                <label class="form-label fw-medium">
                    <i class="bi bi-braces me-1"></i>
                    Feed JSON
                </label>
                <div class="input-group">
                    <input type="text" class="form-control" id="jsonUrl" readonly value="<?= $_ENV['APP_URL'] ?>/feed/json">
                    <button class="btn btn-outline-secondary" type="button" onclick="copyToClipboard('jsonUrl')">
                        <i class="bi bi-clipboard"></i>
                    </button>
                    <a href="<?= $_ENV['APP_URL'] ?>/feed/json" target="_blank" class="btn btn-outline-primary" id="jsonLink">
                        <i class="bi bi-box-arrow-up-right"></i>
                    </a>
                </div>
            </div>
        </div>

    </div>
</div>

<?php $this->start('scripts') ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const categoryCheckboxes = document.querySelectorAll('.category-checkbox');
        const tagCheckboxes = document.querySelectorAll('.tag-checkbox');
        const rssUrlInput = document.getElementById('rssUrl');
        const jsonUrlInput = document.getElementById('jsonUrl');
        const rssLink = document.getElementById('rssLink');
        const jsonLink = document.getElementById('jsonLink');

        const baseUrl = '<?= $_ENV['APP_URL'] ?>';

        function updateUrls() {
            const selectedCategories = Array.from(categoryCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            const selectedTags = Array.from(tagCheckboxes)
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            const params = [];

            if (selectedCategories.length > 0) {
                params.push('categories=' + selectedCategories.join(','));
            }

            if (selectedTags.length > 0) {
                params.push('tags=' + selectedTags.join(','));
            }

            const queryString = params.length > 0 ? '?' + params.join('&') : '';

            const rssUrl = baseUrl + '/feed/rss' + queryString;
            const jsonUrl = baseUrl + '/feed/json' + queryString;

            rssUrlInput.value = rssUrl;
            jsonUrlInput.value = jsonUrl;
            rssLink.href = rssUrl;
            jsonLink.href = jsonUrl;
        }

        categoryCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateUrls);
        });

        tagCheckboxes.forEach(cb => {
            cb.addEventListener('change', updateUrls);
        });
    });

    function copyToClipboard(inputId) {
        const input = document.getElementById(inputId);
        input.select();
        input.setSelectionRange(0, 99999);

        navigator.clipboard.writeText(input.value)
            .then(() => {
                const btn = event.target.closest('button');
                const originalHtml = btn.innerHTML;
                btn.innerHTML = '<i class="bi bi-check-lg"></i>';

                setTimeout(() => {
                    btn.innerHTML = originalHtml;
                }, 2000);
            })
            .catch(err => {
                console.error('Erro ao copiar: ', err);
                alert('Não foi possível copiar a URL.');
            });
    }
</script>
<?php $this->stop() ?>