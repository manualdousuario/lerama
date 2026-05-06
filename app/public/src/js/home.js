document.addEventListener('DOMContentLoaded', function () {
    var simplifiedCheckbox = document.getElementById('simplified-view');
    var viewModeSelect     = document.getElementById('view-mode');
    if (!simplifiedCheckbox || !viewModeSelect) return;

    var listView  = document.getElementById('list-view');
    var cardsView = document.getElementById('cards-view');

    function updateView() {
        var isSimplified = simplifiedCheckbox.checked;
        var viewMode     = viewModeSelect.value;

        if (viewMode === 'cards') {
            if (listView)  listView.style.display  = 'none';
            if (cardsView) cardsView.style.display = '';
        } else {
            if (listView)  listView.style.display  = '';
            if (cardsView) cardsView.style.display = 'none';
        }

        var listThumbs   = listView  ? listView.querySelectorAll('.image-thumbnail')  : [];
        var cardsThumbs  = cardsView ? cardsView.querySelectorAll('.image-thumbnail') : [];
        var listContents = listView  ? listView.querySelectorAll('.content')          : [];

        [].forEach.call(listThumbs,   function (el) { el.style.display = isSimplified ? 'none' : ''; });
        [].forEach.call(listContents, function (el) { el.style.display = isSimplified ? 'none' : ''; });
        [].forEach.call(cardsThumbs,  function (el) { el.style.display = isSimplified ? 'none' : ''; });
    }

    var savedSimplified = localStorage.getItem('simplifiedView');
    var savedViewMode   = localStorage.getItem('viewMode');

    if (savedSimplified === 'true') simplifiedCheckbox.checked = true;

    if (savedViewMode) {
        viewModeSelect.value = savedViewMode;
    } else {
        viewModeSelect.value = 'cards';
        localStorage.setItem('viewMode', 'cards');
    }

    updateView();

    simplifiedCheckbox.addEventListener('change', function () {
        localStorage.setItem('simplifiedView', this.checked);
        updateView();
    });

    viewModeSelect.addEventListener('change', function () {
        localStorage.setItem('viewMode', this.value);
        updateView();
    });

    // Filter save/load
    var categorySelect        = document.getElementById('category-select');
    var tagSelect             = document.getElementById('tag-select');
    var latestPerFeedCheckbox = document.getElementById('latest-per-feed');
    var saveFilterBtn         = document.getElementById('save-filter-btn');
    var clearFilterBtn        = document.getElementById('clear-filter-btn');
    if (!categorySelect || !tagSelect || !saveFilterBtn || !clearFilterBtn) return;

    var filterForm = categorySelect.closest('form');

    categorySelect.addEventListener('change', function () { filterForm.submit(); });
    tagSelect.addEventListener('change',      function () { filterForm.submit(); });

    if (latestPerFeedCheckbox) {
        latestPerFeedCheckbox.addEventListener('change', function () { filterForm.submit(); });
    }

    function loadSavedFilters() {
        var savedCategory = localStorage.getItem('savedFilterCategory');
        var savedTag      = localStorage.getItem('savedFilterTag');
        var hasCurrent    = categorySelect.value || tagSelect.value;

        if (!hasCurrent && (savedCategory || savedTag)) {
            if (savedCategory) categorySelect.value = savedCategory;
            if (savedTag)      tagSelect.value      = savedTag;
            if (savedCategory || savedTag) filterForm.submit();
        }
    }

    function saveFilters() {
        localStorage.setItem('savedFilterCategory', categorySelect.value);
        localStorage.setItem('savedFilterTag',      tagSelect.value);

        var icon = saveFilterBtn.querySelector('i');
        var origClass = icon ? icon.className : '';
        if (icon) icon.className = 'bi bi-bookmark-check-fill';
        saveFilterBtn.classList.remove('btn-outline-secondary');
        saveFilterBtn.classList.add('btn-success');

        setTimeout(function () {
            if (icon) icon.className = origClass;
            saveFilterBtn.classList.remove('btn-success');
            saveFilterBtn.classList.add('btn-outline-secondary');
        }, 2000);
    }

    function clearFilters() {
        localStorage.removeItem('savedFilterCategory');
        localStorage.removeItem('savedFilterTag');
        categorySelect.value = '';
        tagSelect.value      = '';
        window.location.href = '/';
    }

    saveFilterBtn.addEventListener('click',  saveFilters);
    clearFilterBtn.addEventListener('click', clearFilters);

    loadSavedFilters();
});
