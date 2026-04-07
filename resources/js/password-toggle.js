document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.password-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const input = btn.parentElement.querySelector('input');
            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            btn.classList.toggle('is-visible', isHidden);
            btn.setAttribute('aria-label', isHidden ? 'Ukryj hasło' : 'Pokaż hasło');
            btn.setAttribute('aria-pressed', isHidden ? 'true' : 'false');
        });
    });
});
