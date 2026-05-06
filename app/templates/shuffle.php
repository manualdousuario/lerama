<?php $this->layout('layout-fullscreen', ['title' => $title]) ?>

<div class="d-flex flex-column vh-100">
    <div class="container-fluid py-2 bg-dark border-bottom shadow lerama-topbar" role="toolbar" aria-label="<?= __('nav.shuffle') ?>">
        <div class="row g-3 align-items-center">
            <div class="col-auto">
                <a href="/" class="btn btn-sm btn-outline-secondary" aria-label="<?= __('a11y.back_home') ?>">
                    <i class="bi bi-book-half text-white" aria-hidden="true"></i>
                </a>
            </div>
            <div class="col-auto">
                <button id="shuffleButton" class="btn btn-sm btn-primary" type="button">
                    <i class="bi bi-arrow-clockwise" aria-hidden="true"></i> <?= __('shuffle.button') ?>
                </button>
            </div>
            <div class="col flex-grow-1">
                <label for="urlInput" class="visually-hidden-focusable"><?= __('shuffle.go') ?></label>
                <div class="input-group input-group-sm">
                    <input type="url" id="urlInput" class="form-control" value="<?= $this->e($initialUrl) ?>"
                        placeholder="https://example.com">
                    <button id="goButton" class="btn btn-secondary" type="button"><?= __('shuffle.go') ?></button>
                    <a id="openButton" href="<?= $this->e($initialUrl) ?>" target="_blank" rel="noopener"
                        class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i> <?= __('shuffle.open') ?>
                    </a>
                </div>
            </div>
            <div class="col-auto">
                <button id="darkModeToggle" class="btn btn-sm btn-outline-secondary" type="button" aria-label="<?= __('a11y.toggle_dark_mode') ?>" aria-pressed="false">
                    <i class="bi bi-sun d-none" id="lightIcon" aria-hidden="true"></i>
                    <i class="bi bi-moon" id="darkIcon" aria-hidden="true"></i>
                </button>
            </div>
        </div>
    </div>

    <main id="main-content" class="flex-grow-1 position-relative">
        <iframe id="contentFrame" src="<?= $this->e($initialUrl) ?>" class="position-absolute w-100 h-100 border-0"
            title="<?= __('nav.shuffle') ?>"
            sandbox="allow-downloads allow-forms allow-modals allow-pointer-lock allow-popups allow-same-origin allow-scripts"
            loading="lazy"></iframe>
    </main>
</div>
