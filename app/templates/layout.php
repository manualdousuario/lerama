<!DOCTYPE html>
<html lang="pt-BR" data-bs-theme="light">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $this->e($title ?? 'Lerama - Agregador de Feeds') ?></title>
    <link href="//cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="//cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <script src="//cdn.jsdelivr.net/npm/bootstrap@5.3.5/dist/js/bootstrap.bundle.min.js"></script>
</head>

<body class="bg-light min-vh-100">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark border-bottom shadow">
        <div class="container">
            <div class="d-flex">
                <div class="me-3">
                    <a href="/" class="fs-4 fw-bold text-white text-decoration-none">Lerama</a>
                </div>
                <nav class="d-flex">
                    <a href="/" class="d-inline-flex align-items-center px-2 py-2 me-3 text-decoration-none <?= $this->section('active') === 'home' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                        <i class="bi bi-house-door me-1"></i>
                        Início
                    </a>
                    <a href="/feeds" class="d-inline-flex align-items-center px-2 py-2 me-3 text-decoration-none <?= $this->section('active') === 'feeds' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                        <i class="bi bi-journal-text me-1"></i>
                        Feeds
                    </a>
                    <?php if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true): ?>
                        <a href="/admin" class="d-inline-flex align-items-center px-2 py-2 me-3 text-decoration-none <?= $this->section('active') === 'admin' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                            <i class="bi bi-file-earmark-lock-fill me-1"></i>
                            Admin
                        </a>
                        <a href="/admin/feeds" class="d-inline-flex align-items-center px-2 py-2 me-3 text-decoration-none <?= $this->section('active') === 'admin-feeds' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                            <i class="bi bi-grid me-1"></i>
                            Gerenciar Feeds
                        </a>
                        <a href="/admin/logout" class="d-inline-flex align-items-center px-2 py-2 me-3 text-secondary text-decoration-none hover-text-white">
                            <i class="bi bi-box-arrow-right me-1"></i>
                            Sair
                        </a>
                    <?php else: ?>
                        <a href="/admin/login" class="d-inline-flex align-items-center px-2 py-2 me-3 text-decoration-none <?= $this->section('active') === 'login' ? 'border-white text-white' : 'text-secondary hover-text-white' ?>">
                            <i class="bi bi-file-earmark-lock-fill me-1"></i>
                            Admin
                        </a>
                    <?php endif; ?>
                </nav>
            </div>
            <div class="d-flex align-items-center">
                <a href="/feed/rss" target="_blank" class="btn btn-sm btn-outline-secondary me-2" title="Feed RSS">
                    <i class="bi bi-rss"></i>
                </a>
                <a href="/feed/json" target="_blank" class="btn btn-sm btn-outline-secondary me-2" title="Feed JSON">
                    <i class="bi bi-braces"></i>
                </a>
                <button id="darkModeToggle" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-sun d-none" id="lightIcon"></i>
                    <i class="bi bi-moon" id="darkIcon"></i>
                </button>
            </div>
        </div>
    </nav>
    <main>
        <div class="container py-4">
            <?= $this->section('content') ?>
        </div>
    </main>
    <footer class="py-4 border-top mt-auto">
        <div class="container">
            <p class="text-center text-secondary small p-0 m-0">
                &copy; <?= date('Y') ?> - Diretório e buscador de blogs pessoais atualizado em tempo real.
            </p>
            <p class="text-center text-secondary small p-0 m-0">
                Quer incluir o seu blog pessoal no Lerama? <a href="mailto:ghedin@manualdousuario.net">Envie um e-mail</a>.
            </p>
            <p class="text-center mt-2 mb-0">
                <a href="/feed/rss" target="_blank" class="btn btn-sm btn-outline-secondary mx-1" title="Feed RSS">
                    <i class="bi bi-rss"></i> RSS
                </a>
                <a href="/feed/json" target="_blank" class="btn btn-sm btn-outline-secondary mx-1" title="Feed JSON">
                    <i class="bi bi-braces"></i> JSON
                </a>
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

    <?= $this->section('scripts', '') ?>
</body>

</html>