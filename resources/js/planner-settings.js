import flatpickr from 'flatpickr';
import { Polish } from 'flatpickr/dist/l10n/pl.js';
import 'flatpickr/dist/flatpickr.min.css';
import 'flatpickr/dist/themes/dark.css';

flatpickr.localize(Polish);

$(document).ready(function () {
    const container = document.querySelector('.planner-settings-container');

    const deadlinePicker = flatpickr('#signup-deadline', {
        enableTime: true,
        time_24hr: true,
        locale: 'pl',
        dateFormat: 'Y-m-d H:i',
        minDate: 'today',
        static: true,
        appendTo: container,
    });

    const startPicker = flatpickr('#range-start', {
        locale: 'pl',
        dateFormat: 'Y-m-d',
        minDate: 'today',
        static: true,
        appendTo: container,
    });

    const endPicker = flatpickr('#range-end', {
        locale: 'pl',
        dateFormat: 'Y-m-d',
        minDate: 'today',
        static: true,
        appendTo: container,
    });

    $('#signup-deadline, #range-start, #range-end').on('click', function (e) {
        e.stopPropagation();
    });

    function fillWeekOffset(weeksAhead) {
        const now = new Date();
        const dayOfWeek = now.getDay();

        const daysToNextMonday = dayOfWeek === 0 ? 1 : 8 - dayOfWeek;

        const targetMonday = new Date(now);
        targetMonday.setHours(0, 0, 0, 0);
        targetMonday.setDate(now.getDate() + daysToNextMonday + (weeksAhead - 1) * 7);

        const targetSunday = new Date(targetMonday);
        targetSunday.setDate(targetMonday.getDate() + 6);


        const deadline = new Date(targetMonday);
        deadline.setDate(targetMonday.getDate() - 1);
        deadline.setHours(23, 59, 0, 0);

        deadlinePicker.setDate(deadline, true);
        startPicker.setDate(targetMonday, true);
        endPicker.setDate(targetSunday, true);

        const signupRadio = document.querySelector('input[name="type"][value="signup"]');
        if (signupRadio) {
            signupRadio.checked = true;
        }
    }

    $('#quick-next-week').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        fillWeekOffset(1);
    });

    $('#quick-in-two-weeks').on('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        fillWeekOffset(2);
    });
});
