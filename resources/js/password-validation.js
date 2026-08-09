export function initPasswordValidation() {
    const passwordInput = document.getElementById('password');
    const confirmationInput = document.getElementById('password_confirmation');
    const matchInfo = document.getElementById('password-match');

    if (!passwordInput || !confirmationInput || !matchInfo) {
        return;
    }

    const rules = [
        { id: 'req-length', test: value => value.length >= 8 },
        { id: 'req-uppercase', test: value => /[A-Z]/.test(value) },
        { id: 'req-lowercase', test: value => /[a-z]/.test(value) },
        { id: 'req-number', test: value => /\d/.test(value) },
        { id: 'req-special', test: value => /[^A-Za-z0-9]/.test(value) },
    ];

    function checkMatch() {
        if (!confirmationInput.value) {
            matchInfo.classList.add('hidden');
            return;
        }

        const matches = passwordInput.value === confirmationInput.value;
        matchInfo.textContent = matches ? 'Hasła są zgodne' : 'Hasła nie są zgodne';
        matchInfo.className = `password-match ${matches ? 'match' : 'no-match'}`;
    }

    passwordInput.addEventListener('input', function () {
        rules.forEach(rule => {
            const element = document.getElementById(rule.id);
            const isValid = rule.test(this.value);

            element?.classList.toggle('valid', isValid);
            element?.classList.toggle('invalid', !isValid);
        });

        checkMatch();
    });

    confirmationInput.addEventListener('input', checkMatch);
}
