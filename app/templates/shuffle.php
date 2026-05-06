<?php $this->layout('layout-fullscreen', ['title' => $title]) ?>

<div class="d-flex flex-column vh-100">
    <div class="container-fluid py-2 bg-dark border-bottom shadow">
        <div class="row g-3 align-items-center">
            <div class="col-auto">
                <a href="/" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-book-half text-white"></i>
                </a>
            </div>
            <div class="col-auto">
                <button id="shuffleButton" class="btn btn-sm btn-primary">
                    <i class="bi bi-arrow-clockwise"></i> <?= __('shuffle.button') ?>
                </button>
            </div>
            <div class="col flex-grow-1">
                <div class="input-group input-group-sm">
                    <input type="text" id="urlInput" class="form-control" value="<?= $this->e($initialUrl) ?>"
                        placeholder="https://example.com">
                    <button id="goButton" class="btn btn-secondary"><?= __('shuffle.go') ?></button>
                    <a id="openButton" href="<?= $this->e($initialUrl) ?>" target="_blank"
                        class="btn btn-sm btn-outline-secondary">
                        <i class="bi bi-box-arrow-up-right"></i> <?= __('shuffle.open') ?>
                    </a>
                </div>
            </div>
            <div class="col-auto">
                <button id="darkModeToggle" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-sun d-none" id="lightIcon"></i>
                    <i class="bi bi-moon" id="darkIcon"></i>
                </button>
            </div>
        </div>
    </div>

    <div class="flex-grow-1 position-relative">
        <iframe id="contentFrame" src="<?= $this->e($initialUrl) ?>" class="position-absolute w-100 h-100 border-0"
            sandbox="allow-downloads allow-forms allow-modals allow-pointer-lock allow-popups allow-same-origin allow-scripts"
            loading="lazy"></iframe>
    </div>
</div>
