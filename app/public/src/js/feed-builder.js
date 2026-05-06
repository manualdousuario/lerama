document.addEventListener('DOMContentLoaded', function () {
    var categoryCheckboxes = document.querySelectorAll('.category-checkbox');
    var tagCheckboxes      = document.querySelectorAll('.tag-checkbox');
    var rssUrlInput        = document.getElementById('rssUrl');
    var jsonUrlInput       = document.getElementById('jsonUrl');
    var rssLink            = document.getElementById('rssLink');
    var jsonLink           = document.getElementById('jsonLink');
    if (!rssUrlInput || !jsonUrlInput) return;

    var baseUrl = (window.LERAMA && window.LERAMA.appUrl) ? window.LERAMA.appUrl : '';

    function updateUrls() {
        var selectedCategories = [].filter.call(categoryCheckboxes, function (cb) { return cb.checked; })
                                    .map(function (cb) { return cb.value; });
        var selectedTags       = [].filter.call(tagCheckboxes, function (cb) { return cb.checked; })
                                    .map(function (cb) { return cb.value; });
        var params = [];

        if (selectedCategories.length > 0) params.push('categories=' + selectedCategories.join(','));
        if (selectedTags.length > 0)       params.push('tags='       + selectedTags.join(','));

        var qs      = params.length > 0 ? '?' + params.join('&') : '';
        var rssUrl  = baseUrl + '/feed/rss'  + qs;
        var jsonUrl = baseUrl + '/feed/json' + qs;

        rssUrlInput.value  = rssUrl;
        jsonUrlInput.value = jsonUrl;
        if (rssLink)  rssLink.href  = rssUrl;
        if (jsonLink) jsonLink.href = jsonUrl;
    }

    [].forEach.call(categoryCheckboxes, function (cb) { cb.addEventListener('change', updateUrls); });
    [].forEach.call(tagCheckboxes,      function (cb) { cb.addEventListener('change', updateUrls); });
});

function copyToClipboard(inputId) {
    var input = document.getElementById(inputId);
    if (!input) return;
    input.select();
    input.setSelectionRange(0, 99999);

    navigator.clipboard.writeText(input.value)
        .then(function () {
            var btn = event.target.closest('button');
            var originalHtml = btn.innerHTML;
            btn.innerHTML = '<i class="bi bi-check-lg"></i>';
            setTimeout(function () { btn.innerHTML = originalHtml; }, 2000);
        })
        .catch(function (err) {
            console.error('Erro ao copiar: ', err);
            alert('Não foi possível copiar a URL.');
        });
}
