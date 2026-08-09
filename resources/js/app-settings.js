document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-app-setting-form]');
    const toggle = document.querySelector('[data-app-setting-toggle]');

    if (!form || !toggle) {
        return;
    }

    toggle.addEventListener('change', () => {
        form.classList.add('is-submitting');
        toggle.setAttribute('aria-busy', 'true');
        form.requestSubmit();
    });
});
