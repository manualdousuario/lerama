document.addEventListener('DOMContentLoaded', function () {
    var darkModeToggle = document.getElementById('darkModeToggle');
    if (!darkModeToggle) return;

    var html = document.documentElement;
    var lightIcon = document.getElementById('lightIcon');
    var darkIcon = document.getElementById('darkIcon');
    var savedTheme = localStorage.getItem('theme');

    function applyTheme(dark) {
        if (dark) {
            html.setAttribute('data-bs-theme', 'dark');
            document.body.classList.remove('bg-light');
            document.body.classList.add('bg-dark');
            if (lightIcon) lightIcon.classList.remove('d-none');
            if (darkIcon)  darkIcon.classList.add('d-none');
            darkModeToggle.setAttribute('aria-pressed', 'true');
        } else {
            html.setAttribute('data-bs-theme', 'light');
            document.body.classList.add('bg-light');
            document.body.classList.remove('bg-dark');
            if (lightIcon) lightIcon.classList.add('d-none');
            if (darkIcon)  darkIcon.classList.remove('d-none');
            darkModeToggle.setAttribute('aria-pressed', 'false');
        }
    }

    var prefersDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    applyTheme(savedTheme === 'dark' || (!savedTheme && prefersDark));

    darkModeToggle.addEventListener('click', function () {
        var isDark = html.getAttribute('data-bs-theme') === 'dark';
        applyTheme(!isDark);
        localStorage.setItem('theme', isDark ? 'light' : 'dark');
    });
});
