<?php $this->layout('layout', []) ?>

<?php $this->start('active') ?>home<?php $this->stop() ?>

<div class="card shadow-sm">
    <div class="card-header">
        <div class="row">
            <div class="col-12">
                <h3 class="fs-5 fw-medium m-0 py-1">
                    <i class="bi bi-grid me-1"></i>
                    <?= __('home.title') ?>
                </h3>
            </div>
            <div class="border-top col-12 pb-0 pt-2 mt-2">
                <form action="<?= isset($pagination) && $pagination['current'] > 1 ? $pagination['baseUrl'] . $pagination['current'] : '/' ?>" method="GET">
                    <div class="d-md-flex justify-content-between align-items-center">
                        <div class="me-md-2 mb-2 mb-md-0">
                            <input type="checkbox" id="simplified-view" /> <label for="simplified-view"><?= __('common.simplified') ?></label>
                        </div>
                        <div class="d-md-flex">
                            <div class="d-md-flex me-md-2 mb-3 mb-md-0">
                                <select name="category" id="category-select" class="form-select me-md-2 mb-2 mb-md-0">
                                    <option value=""><?= __('common.all_categories') ?></option>
                                    <?php foreach ($categories as $category): ?>
                                        <option value="<?= $this->e($category['slug']) ?>" <?= ($selectedCategory ?? '') == $category['slug'] ? 'selected' : '' ?>>
                                            <?= $this->e($category['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <select name="tag" id="tag-select" class="form-select me-md-2 mb-2 mb-md-0">
                                    <option value=""><?= __('common.all_topics') ?></option>
                                    <?php foreach ($tags as $tag): ?>
                                        <option value="<?= $this->e($tag['slug']) ?>" <?= ($selectedTag ?? '') == $tag['slug'] ? 'selected' : '' ?>>
                                            <?= $this->e($tag['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>

                                <div class="input-group flex-nowrap">
                                    <button type="button" id="save-filter-btn" class="btn btn-outline-secondary w-100 d-flex align-items-center justify-content-center" title="<?= __('common.save_filter') ?>">
                                        <span class="text-nowrap"></i><?= __('common.save_filter') ?></span>
                                    </button>
                                    <button type="button" id="clear-filter-btn" class="btn btn-outline-danger w-100 d-flex align-items-center justify-content-center" title="<?= __('common.clear_filters') ?>">
                                        <span class="text-nowrap"></i><?= __('common.clear_filters') ?></span>
                                    </button>
                                </div>
                            </div>
                            <div>
                                <div class="input-group">
                                    <input type="text" name="search" value="<?= $this->e($search) ?>" class="form-control" placeholder="<?= __('common.search_placeholder') ?>">
                                    <button type="submit" class="btn btn-primary d-flex align-items-center">
                                        <i class="bi bi-search me-1"></i>
                                        <?= __('common.search') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <?php if (empty($items)): ?>
        <div class="card-body text-center p-4">
            <p class="text-secondary mb-0">
                <i class="bi bi-exclamation-circle me-1"></i>
                <?= __('home.no_items') ?>
            </p>
        </div>
    <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($items as $item): ?>
                <li class="list-group-item p-3 hover-bg-light">
                    <div class="d-block d-md-flex">
                        <?php if (!empty($item['image_url'])) : ?>
                            <div class="pe-md-3 pb-2 pb-md-0 image-thumbnail">
                                <img class="rounded-2" src="<?= $this->e($thumbnailService->getThumbnail($item['image_url'], 180, 100)) ?>" width="180" height="100" loading="lazy" decoding="async" alt="<?= $this->e($item['title']) ?>">
                            </div>
                        <?php endif; ?>
                        <div class="flex-fill">
                            <div class="pb-2 pb-md-0">
                                <h4 class="fs-5 fw-medium text-primary m-0">
                                    <a href="<?= $this->e($item['url']) . (parse_url($item['url'], PHP_URL_QUERY) ? '&' : '?') ?>utm_source=lerama" target="_blank" class="text-decoration-none hover-underline">
                                            <?= $this->e($item['title']) ?>
                                        </a>
                                </h4>
                                <div class="d-block">
                                    <span><?= __('common.in') ?></span>
                                    <a href="<?= $this->e($item['site_url']) . (parse_url($item['site_url'], PHP_URL_QUERY) ? '&' : '?') ?>utm_source=lerama" target="_blank" class="text-truncate">
                                        <?= $this->e($item['feed_title']) ?>
                                    </a>
                                </div>
                            </div>
                            
                            <p class="d-flex align-items-center small mb-0">
                                <?php if (!empty($item['published_at'])): ?>
                                    <i class="bi bi-calendar me-1"></i>
                                    <?= date('j/m/Y \à\s H:i', strtotime($item['published_at'])) ?>
                                <?php endif; ?>
                                <?php if (!empty($item['author'])): ?>
                                    <i class="ms-2 bi bi-person me-1"></i>
                                    <?= $this->e($item['author']) ?>
                                <?php endif; ?>

                            </p>
                            
                            <?php if (!empty($item['content']) && strlen($item['content']) >= 30): ?>
                                <div class="mt-2 small content">
                                    <?= substr(strip_tags($item['content']), 0, 300) ?>...
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (isset($pagination) && $pagination['total'] > 1): ?>
            <div class="card-footer d-flex justify-content-between align-items-center p-3">
                <div class="d-flex justify-content-center align-items-center w-100">
                    <nav aria-label="Pagination">
                        <ul class="pagination pagination-sm mb-0">
                            <?php
                            $queryParams = array_filter([
                                'search' => $search,
                                'feed' => $selectedFeed,
                                'category' => $selectedCategory ?? null,
                                'tag' => $selectedTag ?? null
                            ]);
                            $queryString = !empty($queryParams) ? '?' . http_build_query($queryParams) : '';
                            ?>
                            
                            <?php if ($pagination['current'] > 1): ?>
                                <li class="page-item">
                                    <a href="<?= $pagination['baseUrl'] . ($pagination['current'] - 1) . $queryString ?>" class="page-link" aria-label="Previous">
                                        <span aria-hidden="true">&laquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <?php
                            $start = max(1, $pagination['current'] - 2);
                            $end = min($pagination['total'], $pagination['current'] + 2);

                            if ($start > 1) {
                                echo '<li class="page-item"><a href="' . $pagination['baseUrl'] . '1' . $queryString . '" class="page-link">1</a></li>';
                                if ($start > 2) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                            }

                            for ($i = $start; $i <= $end; $i++) {
                                if ($i == $pagination['current']) {
                                    echo '<li class="page-item active"><span class="page-link">' . $i . '</span></li>';
                                } else {
                                    echo '<li class="page-item"><a href="' . $pagination['baseUrl'] . $i . $queryString . '" class="page-link">' . $i . '</a></li>';
                                }
                            }

                            if ($end < $pagination['total']) {
                                if ($end < $pagination['total'] - 1) {
                                    echo '<li class="page-item disabled"><span class="page-link">...</span></li>';
                                }
                                echo '<li class="page-item"><a href="' . $pagination['baseUrl'] . $pagination['total'] . $queryString . '" class="page-link">' . $pagination['total'] . '</a></li>';
                            }
                            ?>

                            <?php if ($pagination['current'] < $pagination['total']): ?>
                                <li class="page-item">
                                    <a href="<?= $pagination['baseUrl'] . ($pagination['current'] + 1) . $queryString ?>" class="page-link" aria-label="Next">
                                        <span aria-hidden="true">&raquo;</span>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php $this->start('scripts') ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Simplified view functionality
    const simplifiedCheckbox = document.getElementById('simplified-view');
    const imageThumbnails = document.querySelectorAll('.image-thumbnail');
    const contentDivs = document.querySelectorAll('.content');
    
    function updateVisibility(isSimplified) {
        const displayValue = isSimplified ? 'none' : '';
        
        imageThumbnails.forEach(function(element) {
            element.style.display = displayValue;
        });
        
        contentDivs.forEach(function(element) {
            element.style.display = displayValue;
        });
    }
    
    const savedState = localStorage.getItem('simplifiedView');
    if (savedState === 'true') {
        simplifiedCheckbox.checked = true;
        updateVisibility(true);
    }
    
    simplifiedCheckbox.addEventListener('change', function() {
        const isChecked = this.checked;
        
        updateVisibility(isChecked);
        
        localStorage.setItem('simplifiedView', isChecked);
    });

    // Filter save/load functionality
    const categorySelect = document.getElementById('category-select');
    const tagSelect = document.getElementById('tag-select');
    const saveFilterBtn = document.getElementById('save-filter-btn');
    const clearFilterBtn = document.getElementById('clear-filter-btn');
    const filterForm = categorySelect.closest('form');
    
    // Auto-submit form when category or tag changes
    categorySelect.addEventListener('change', function() {
        filterForm.submit();
    });
    
    tagSelect.addEventListener('change', function() {
        filterForm.submit();
    });
        
    // Load saved filters on page load
    function loadSavedFilters() {
        const savedCategory = localStorage.getItem('savedFilterCategory');
        const savedTag = localStorage.getItem('savedFilterTag');
        
        const hasCurrentFilters = categorySelect.value || tagSelect.value;
        
        if (!hasCurrentFilters && (savedCategory || savedTag)) {
            if (savedCategory) {
                categorySelect.value = savedCategory;
            }
            if (savedTag) {
                tagSelect.value = savedTag;
            }
            
            if (savedCategory || savedTag) {
                filterForm.submit();
            }
        }
    }
    
    function saveFilters() {
        const category = categorySelect.value;
        const tag = tagSelect.value;
        
        localStorage.setItem('savedFilterCategory', category);
        localStorage.setItem('savedFilterTag', tag);
        
        const icon = saveFilterBtn.querySelector('i');
        const originalClass = icon.className;
        icon.className = 'bi bi-bookmark-check-fill';
        saveFilterBtn.classList.remove('btn-outline-secondary');
        saveFilterBtn.classList.add('btn-success');
        
        setTimeout(function() {
            icon.className = originalClass;
            saveFilterBtn.classList.remove('btn-success');
            saveFilterBtn.classList.add('btn-outline-secondary');
        }, 2000);
    }
    
    // Clear saved filters
    function clearFilters() {
        localStorage.removeItem('savedFilterCategory');
        localStorage.removeItem('savedFilterTag');
        
        categorySelect.value = '';
        tagSelect.value = '';
        
        window.location.href = '/';
    }
    
    saveFilterBtn.addEventListener('click', saveFilters);
    clearFilterBtn.addEventListener('click', clearFilters);
    
    loadSavedFilters();
});
</script>
<?php $this->stop() ?>