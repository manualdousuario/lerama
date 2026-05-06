document.addEventListener('DOMContentLoaded', function () {
    var contentFrame  = document.getElementById('contentFrame');
    var urlInput      = document.getElementById('urlInput');
    var goButton      = document.getElementById('goButton');
    var openButton    = document.getElementById('openButton');
    var shuffleButton = document.getElementById('shuffleButton');
    if (!contentFrame || !urlInput || !shuffleButton) return;

    function loadUrl(url) {
        if (!url) return;
        urlInput.value    = url;
        if (openButton) openButton.href = url;
        contentFrame.src  = url;
    }

    function getRandomUrl() {
        fetch('/shuffle?ajax=1&_=' + Date.now(), {
            cache:   'no-store',
            headers: { 'Cache-Control': 'no-cache' }
        })
        .then(function (response) { return response.json(); })
        .then(function (data)     { if (data.url) loadUrl(data.url); })
        .catch(function (error)   { console.error('Error fetching random URL:', error); });
    }

    if (goButton) {
        goButton.addEventListener('click', function () { loadUrl(urlInput.value); });
    }

    urlInput.addEventListener('keypress', function (e) {
        if (e.key === 'Enter') loadUrl(urlInput.value);
    });

    shuffleButton.addEventListener('click', function () { getRandomUrl(); });

    if (urlInput.value) loadUrl(urlInput.value);
});
