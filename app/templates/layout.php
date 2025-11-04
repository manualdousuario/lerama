<!DOCTYPE html>
<html lang="<?= current_language() ?>" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->e(isset($title) ? $title . ' | ' . $_ENV['APP_NAME'] : $_ENV['APP_NAME']) ?></title>
    <link rel="icon" type="image/png" href="/assets/favicon-96x96.png" sizes="96x96" />
    <link rel="icon" type="image/svg+xml" href="/assets/favicon.svg" />
    <link rel="shortcut icon" href="/assets/favicon.ico" />
    <link rel="apple-touch-icon" sizes="180x180" href="/assets/apple-touch-icon.png" />
    <meta name="apple-mobile-web-app-title" content="<?= $this->e($title ?? $_ENV['APP_NAME']) ?>" />
    <link rel="manifest" href="/assets/site.webmanifest" />
    <meta property="og:type" content="website">
    <meta property="og:title" content="<?= $this->e($title ?? $_ENV['APP_NAME']) ?>">
    <meta property="og:url" content="<?= $_ENV['APP_URL'] ?>">
    <meta property="og:image" content="/assets/ogimage.png">
    <meta property="og:description" content="<?= __('meta.description') ?>">

    <link href="//cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="//cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="//cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body class="bg-light min-vh-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom shadow">
        <div class="container">
            <div class="d-block d-md-flex">
                <div class="me-3">
                    <?php if(isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) { ?>
                        <i class="bi bi-file-earmark-lock-fill fs-5 me-2"></i>
                    <?php } else { ?>
                        <i class="bi bi-book-half text-white fs-5 me-2"></i>
                    <?php } ?>
                    <a href="/" class="fs-4 fw-bold text-white text-decoration-none">Lerama</a>
                    
                </div>
                <nav class="d-block d-md-flex pb-1 pb-md-0">
                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                        <a href="/admin" class="d-inline-flex align-items-center py-2 me-3 pl-0 pl-md-2 text-decoration-none <?= $this->section('active') === 'admin' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                            <i class="bi bi-newspaper me-1"></i>
                            <?= __('nav.articles') ?>
                        </a>
                        <a href="/admin/feeds" class="d-inline-flex align-items-center px-2 py-2 me-3 text-decoration-none <?= $this->section('active') === 'admin-feeds' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                            <i class="bi bi-grid me-1"></i>
                            <?= __('nav.feeds') ?>
                        </a>
                        <a href="/admin/categories" class="d-inline-flex align-items-center px-2 py-2 me-3 text-decoration-none <?= $this->section('active') === 'admin-categories' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                            <i class="bi bi-folder me-1"></i>
                            <?= __('nav.categories') ?>
                        </a>
                        <a href="/admin/tags" class="d-inline-flex align-items-center px-2 py-2 me-3 text-decoration-none <?= $this->section('active') === 'admin-tags' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                            <i class="bi bi-tags me-1"></i>
                            <?= __('nav.topics') ?>
                        </a>
                        <a href="/admin/logout" class="d-inline-flex align-items-center px-2 py-2 me-3 text-secondary text-decoration-none hover-text-white">
                            <i class="bi bi-box-arrow-right me-1"></i>
                            <?= __('nav.logout') ?>
                        </a>
                    <?php else : ?>
                        <a href="/" class="d-inline-flex align-items-center pe-1 py-2 me-3 text-decoration-none <?= $this->section('active') === 'home' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                            <i class="bi bi-house-door me-1"></i>
                            <?= __('nav.home') ?>
                        </a>
                        <a href="/feeds" class="d-inline-flex align-items-center pe-1 py-2 me-3 text-decoration-none <?= $this->section('active') === 'feeds' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                            <i class="bi bi-journal-text me-1"></i>
                            <?= __('nav.feeds') ?>
                        </a>
                        <a href="/suggest-feed" class="d-inline-flex align-items-center pe-1 py-2 me-3 text-decoration-none <?= $this->section('active') === 'suggest-feed' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                            <i class="bi bi-plus-circle me-1"></i>
                            <?= __('nav.suggest') ?>
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
            <div class="d-flex align-items-center">
                <a href="/feed-builder" class="btn btn-sm btn-outline-secondary me-2" title="<?= __('nav.feed_builder') ?>">
                    <i class="bi bi-braces"></i> Feed
                </a>
                <button id="darkModeToggle" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-sun d-none" id="lightIcon"></i>
                    <i class="bi bi-moon" id="darkIcon"></i>
                </button>
            </div>
        </div>
    </nav>
    
    <?php if (file_exists(__DIR__ . '/partner.php')) include __DIR__ . '/partner.php'; ?>
    
    <main>
        <div class="container py-4">
            <?= $this->section('content') ?>
        </div>
    </main>
    <footer class="py-4 border-top mt-auto">
        <div class="container">
            <p class="text-center text-secondary small p-0 m-0">
                &copy; <?= date('Y') ?> - <?= __('footer.description') ?>
            </p>
            <p class="text-center mt-2 mb-0">
                <a href="/feed-builder" class="btn btn-sm btn-outline-secondary mx-1" title="<?= __('nav.feed_builder') ?>">
                    <i class="bi bi-braces"></i> Feed
                </a>
                <a href="https://github.com/manualdousuario/lerama" target="_blank" class="btn btn-sm btn-outline-secondary mx-1" title="GitHub">
                    <i class="bi bi-github"></i> GitHub
                </a>
                <a href="/categories" class="btn btn-sm btn-outline-secondary mx-1" title="<?= __('nav.categories') ?>">
                    <i class="bi bi-folder me-1"></i>
                    <?= __('nav.categories') ?>
                </a>
                <a href="/tags" class="btn btn-sm btn-outline-secondary mx-1" title="<?= __('nav.topics') ?>">
                    <i class="bi bi-tags me-1"></i>
                    <?= __('nav.topics') ?>
                </a>
                <button id="copySeloLerama" class="btn btn-sm btn-outline-secondary mx-1" title="<?= __('footer.seal') ?>">
                    <i class="bi bi-clipboard"></i> <?= __('footer.seal') ?>
                </button>
            </p>
        </div>
    </footer>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const darkModeToggle = document.getElementById('darkModeToggle');
            const html = document.documentElement;
            const lightIcon = document.getElementById('lightIcon');
            const darkIcon = document.getElementById('darkIcon');
            const savedTheme = localStorage.getItem('theme');

            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                html.setAttribute('data-bs-theme', 'dark');
                document.body.classList.remove('bg-light');
                document.body.classList.add('bg-dark');
                lightIcon.classList.remove('d-none');
                darkIcon.classList.add('d-none');
            } else {
                html.setAttribute('data-bs-theme', 'light');
                document.body.classList.add('bg-light');
                document.body.classList.remove('bg-dark');
                lightIcon.classList.add('d-none');
                darkIcon.classList.remove('d-none');
            }

            darkModeToggle.addEventListener('click', function() {
                if (html.getAttribute('data-bs-theme') === 'dark') {
                    html.setAttribute('data-bs-theme', 'light');
                    document.body.classList.add('bg-light');
                    document.body.classList.remove('bg-dark');
                    lightIcon.classList.add('d-none');
                    darkIcon.classList.remove('d-none');
                    localStorage.setItem('theme', 'light');
                } else {
                    html.setAttribute('data-bs-theme', 'dark');
                    document.body.classList.remove('bg-light');
                    document.body.classList.add('bg-dark');
                    lightIcon.classList.remove('d-none');
                    darkIcon.classList.add('d-none');
                    localStorage.setItem('theme', 'dark');
                }
            });
        });
    </script>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const copySeloButton = document.getElementById('copySeloLerama');

            copySeloButton.addEventListener('click', function() {
                const seloHtml = `<a href="<?= $_ENV['APP_URL'] ?>"><img src="<?= $_ENV['APP_URL'] ?>/88x31.gif" alt="Lerama" width="81" height="33"></a>`;

                navigator.clipboard.writeText(seloHtml)
                    .then(() => {
                        const originalText = copySeloButton.innerHTML;
                        copySeloButton.innerHTML = '<i class="bi bi-check-lg"></i> <?= __('footer.copied') ?>';

                        setTimeout(() => {
                            copySeloButton.innerHTML = originalText;
                        }, 2000);
                    })
                    .catch(err => {
                        console.error('Erro ao copiar: ', err);
                        alert('<?= __('footer.copy_error') ?>');
                    });
            });
        });
    </script>

    <?= $this->section('scripts', '') ?>
</body>

</html>