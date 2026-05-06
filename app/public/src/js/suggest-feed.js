document.addEventListener('DOMContentLoaded', function() {
    const suggestForm = document.getElementById('suggest-form');
    const captchaImage = document.getElementById('captcha-image');
    const captchaImageBtn = document.getElementById('captcha-image-btn');
    const captchaInput = document.getElementById('captcha');
    const feedUrlInput = document.getElementById('feed_url');
    const siteUrlInput = document.getElementById('site_url');

    captchaImageBtn.addEventListener('click', function() {
        captchaImage.src = '/captcha?' + new Date().getTime();
        captchaInput.value = '';
        captchaInput.focus();
    });

    suggestForm.addEventListener('submit', function(event) {
        if (feedUrlInput.value.trim() === siteUrlInput.value.trim()) {
            event.preventDefault();

            let errorDiv = document.querySelector('#feed_url_error');
            if (!errorDiv) {
                errorDiv = document.createElement('div');
                errorDiv.id = 'feed_url_error';
                errorDiv.className = 'form-text text-danger';
                feedUrlInput.parentNode.parentNode.appendChild(errorDiv);
            }

            errorDiv.textContent = window.LERAMA.i18n.suggestFeedUrlSameAsSite;

            feedUrlInput.classList.add('is-invalid');
            siteUrlInput.classList.add('is-invalid');

            feedUrlInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
            return false;
        } else {
            feedUrlInput.classList.remove('is-invalid');
            siteUrlInput.classList.remove('is-invalid');

            const errorDiv = document.querySelector('#feed_url_error');
            if (errorDiv) {
                errorDiv.remove();
            }

            const submitButton = suggestForm.querySelector('button[type="submit"]');
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span> ' + window.LERAMA.i18n.suggestValidating;
            submitButton.disabled = true;
        }
    });
});
