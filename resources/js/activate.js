import flatpickr from 'flatpickr';
import { Polish } from 'flatpickr/dist/l10n/pl.js';
import 'flatpickr/dist/flatpickr.min.css';
import 'flatpickr/dist/themes/dark.css';
import Swal from 'sweetalert2';
import { guardGuestSubmitForms } from './guest-submit-guard';

document.addEventListener('DOMContentLoaded', function () {
    guardGuestSubmitForms();

    const dobDisplay = document.getElementById('date_of_birth_display');
    const dobHidden = document.getElementById('date_of_birth');
    const passwordInput = document.getElementById('password');
    const confirmationInput = document.getElementById('password_confirmation');
    const matchInfo = document.getElementById('password-match');

    if (dobDisplay) {
        const minYear = 1950;
        const maxYear = new Date().getFullYear();

        flatpickr(dobDisplay, {
            locale: Polish,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: 'j F Y',
            maxDate: 'today',
            onChange: function (selectedDates, dateStr) {
                dobHidden.value = dateStr;
            },
            onReady: function (selectedDates, dateStr, instance) {
                const yearInput = instance.calendarContainer.querySelector('.cur-year');
                if (!yearInput) return;

                const select = document.createElement('select');
                select.className = 'cur-year';
                select.tabIndex = -1;

                for (let y = maxYear; y >= minYear; y--) {
                    const opt = document.createElement('option');
                    opt.value = y;
                    opt.textContent = y;
                    select.appendChild(opt);
                }

                select.value = instance.currentYear;
                select.addEventListener('change', function () {
                    instance.changeYear(parseInt(this.value));
                });

                yearInput.replaceWith(select);
                instance._yearSelect = select;
            },
            onYearChange: function (selectedDates, dateStr, instance) {
                if (instance._yearSelect) {
                    instance._yearSelect.value = instance.currentYear;
                }
            }
        });
    }

    if (passwordInput) {
        const rules = [
            { id: 'req-length', test: v => v.length >= 8 },
            { id: 'req-uppercase', test: v => /[A-Z]/.test(v) },
            { id: 'req-lowercase', test: v => /[a-z]/.test(v) },
            { id: 'req-number', test: v => /\d/.test(v) },
            { id: 'req-special', test: v => /[^A-Za-z0-9]/.test(v) },
        ];

        passwordInput.addEventListener('input', function () {
            const val = this.value;
            rules.forEach(rule => {
                const el = document.getElementById(rule.id);
                el.classList.toggle('valid', rule.test(val));
                el.classList.toggle('invalid', !rule.test(val));
            });
            checkMatch();
        });

        confirmationInput.addEventListener('input', checkMatch);

        function checkMatch() {
            if (!confirmationInput.value) {
                matchInfo.classList.add('hidden');
                return;
            }
            matchInfo.classList.remove('hidden');
            if (passwordInput.value === confirmationInput.value) {
                matchInfo.textContent = 'Hasła są zgodne';
                matchInfo.className = 'password-match match';
            } else {
                matchInfo.textContent = 'Hasła nie są zgodne';
                matchInfo.className = 'password-match no-match';
            }
        }
    }

    const flashError = document.querySelector('meta[name="flash-error"]');
    if (flashError) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: flashError.content,
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            background: '#1f1f1f',
            color: '#f0f0f0'
        });
    }
});
