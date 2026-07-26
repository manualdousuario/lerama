/* Vanilla JS, no build step: theme toggle, badge copy, home view prefs. */
(function () {
    'use strict';

    /* --- Light/dark theme --------------------------------------------- */
    function initTheme() {
        var toggle = document.getElementById('darkModeToggle');
        if (!toggle) return;

        var lightIcon = document.getElementById('lightIcon');
        var darkIcon = document.getElementById('darkIcon');

        function apply(dark) {
            document.documentElement.classList.toggle('dark', dark);
            if (lightIcon) lightIcon.classList.toggle('hidden', !dark);
            if (darkIcon) darkIcon.classList.toggle('hidden', dark);
            toggle.setAttribute('aria-pressed', dark ? 'true' : 'false');
        }

        apply(document.documentElement.classList.contains('dark'));

        toggle.addEventListener('click', function () {
            var dark = !document.documentElement.classList.contains('dark');
            apply(dark);
            try { localStorage.setItem('theme', dark ? 'dark' : 'light'); } catch (e) {}
        });
    }

    /* --- Copy badge --------------------------------------------------- */
    function initBadgeCopy() {
        var btn = document.getElementById('copySeloLerama');
        if (!btn) return;

        btn.addEventListener('click', function () {
            var cfg = window.LERAMA || {};
            var appUrl = cfg.appUrl || '';
            var html = '<a href="' + appUrl + '"><img src="' + appUrl + '/88x31.gif" alt="Lerama" width="81" height="33"></a>';
            var original = btn.innerHTML;

            navigator.clipboard.writeText(html)
                .then(function () {
                    btn.innerHTML = '<i class="ti ti-check"></i> ' + ((cfg.i18n && cfg.i18n.footerCopied) || '');
                    setTimeout(function () { btn.innerHTML = original; }, 2000);
                })
                .catch(function (err) {
                    console.error('Copy failed: ', err);
                    alert((cfg.i18n && cfg.i18n.footerCopyError) || 'Copy failed.');
                });
        });
    }

    /* --- Home: saved view mode and filters ---------------------------- */
    function initHomeView() {
        var simplifiedCheckbox = document.getElementById('simplified-view');
        var viewModeSelect = document.getElementById('view-mode');
        var listView = document.getElementById('list-view');
        var cardsView = document.getElementById('cards-view');

        if (simplifiedCheckbox && viewModeSelect && listView && cardsView) {
            var updateView = function () {
                var isSimplified = simplifiedCheckbox.checked;
                var isCards = viewModeSelect.value === 'cards';

                listView.classList.toggle('hidden', isCards);
                cardsView.classList.toggle('hidden', !isCards);

                [listView, cardsView].forEach(function (view) {
                    view.querySelectorAll('.image-thumbnail').forEach(function (el) {
                        el.classList.toggle('hidden', isSimplified);
                    });
                });
                listView.querySelectorAll('.content').forEach(function (el) {
                    el.classList.toggle('hidden', isSimplified);
                });
            };

            try {
                if (localStorage.getItem('simplifiedView') === 'true') simplifiedCheckbox.checked = true;
                viewModeSelect.value = localStorage.getItem('viewMode') || 'cards';
                localStorage.setItem('viewMode', viewModeSelect.value);
            } catch (e) {}

            updateView();

            simplifiedCheckbox.addEventListener('change', function () {
                try { localStorage.setItem('simplifiedView', this.checked); } catch (e) {}
                updateView();
            });
            viewModeSelect.addEventListener('change', function () {
                try { localStorage.setItem('viewMode', this.value); } catch (e) {}
                updateView();
            });
        }

        var categorySelect = document.getElementById('category-select');
        var tagSelect = document.getElementById('tag-select');
        var latestPerFeed = document.getElementById('latest-per-feed');
        var saveFilterBtn = document.getElementById('save-filter-btn');
        var clearFilterBtn = document.getElementById('clear-filter-btn');

        if (!categorySelect || !tagSelect || !saveFilterBtn || !clearFilterBtn) return;

        var filterForm = categorySelect.closest('form');
        if (!filterForm) return;

        categorySelect.addEventListener('change', function () { filterForm.submit(); });
        tagSelect.addEventListener('change', function () { filterForm.submit(); });
        if (latestPerFeed) {
            latestPerFeed.addEventListener('change', function () { filterForm.submit(); });
        }

        try {
            var savedCategory = localStorage.getItem('savedFilterCategory');
            var savedTag = localStorage.getItem('savedFilterTag');
            if (!categorySelect.value && !tagSelect.value && (savedCategory || savedTag)) {
                if (savedCategory) categorySelect.value = savedCategory;
                if (savedTag) tagSelect.value = savedTag;
                filterForm.submit();
            }
        } catch (e) {}

        saveFilterBtn.addEventListener('click', function () {
            try {
                localStorage.setItem('savedFilterCategory', categorySelect.value);
                localStorage.setItem('savedFilterTag', tagSelect.value);
            } catch (e) {}

            var icon = saveFilterBtn.querySelector('i');
            var origClass = icon ? icon.className : '';
            if (icon) icon.className = 'ti ti-bookmark-filled';
            saveFilterBtn.classList.add('btn-success');
            setTimeout(function () {
                if (icon) icon.className = origClass;
                saveFilterBtn.classList.remove('btn-success');
            }, 2000);
        });

        clearFilterBtn.addEventListener('click', function () {
            try {
                localStorage.removeItem('savedFilterCategory');
                localStorage.removeItem('savedFilterTag');
            } catch (e) {}
            categorySelect.value = '';
            tagSelect.value = '';
            window.location.href = '/';
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTheme();
        initBadgeCopy();
        initHomeView();
    });
})();
