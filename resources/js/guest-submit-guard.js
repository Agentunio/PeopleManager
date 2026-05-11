export function guardGuestSubmitForms() {
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', event => {
            if (event.defaultPrevented) {
                return;
            }

            if (form.dataset.submitted === 'true') {
                event.preventDefault();
                return;
            }

            form.dataset.submitted = 'true';

            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(button => {
                button.disabled = true;
            });
        });
    });
}
