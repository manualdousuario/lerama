document.addEventListener('DOMContentLoaded', function () {
    var copySeloButton = document.getElementById('copySeloLerama');
    if (!copySeloButton) return;

    copySeloButton.addEventListener('click', function () {
        var cfg = window.LERAMA || {};
        var appUrl = cfg.appUrl || '';
        var seloHtml = '<a href="' + appUrl + '"><img src="' + appUrl + '/88x31.gif" alt="Lerama" width="81" height="33"></a>';

        navigator.clipboard.writeText(seloHtml)
            .then(function () {
                var originalText = copySeloButton.innerHTML;
                copySeloButton.innerHTML = '<i class="bi bi-check-lg"></i> ' + (cfg.i18n && cfg.i18n.footerCopied ? cfg.i18n.footerCopied : '');
                setTimeout(function () {
                    copySeloButton.innerHTML = originalText;
                }, 2000);
            })
            .catch(function (err) {
                console.error('Erro ao copiar: ', err);
                alert(cfg.i18n && cfg.i18n.footerCopyError ? cfg.i18n.footerCopyError : 'Erro ao copiar.');
            });
    });
});
