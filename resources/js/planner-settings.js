$(document).ready(function() {
    flatpickr("#date-from", {
        enableTime: true,
        time_24hr: true,
        locale: "pl",
        dateFormat: "Y-m-d H:i",
        minDate: "today",
        static: true,
        appendTo: document.querySelector('.planner-settings-container')
    });

    flatpickr("#date-to", {
        enableTime: true,
        time_24hr: true,
        locale: "pl",
        dateFormat: "Y-m-d H:i",
        minDate: "today",
        static: true,
        appendTo: document.querySelector('.planner-settings-container')
    });

    function formatDate(date) {
        return date.toLocaleString('pl-PL', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    }

    function updateWeekDates() {
        const days = parseInt($('input[name="days"]:checked').val()) || 7;
        const now = new Date();
        const end = new Date(now);
        end.setDate(end.getDate() + days);

        $('#week-start').text(formatDate(now));
        $('#week-end').text(formatDate(end));
    }

    updateWeekDates();

    $('input[name="days"]').on('change', function() {
        updateWeekDates();
    });

    $('#date-from, #date-to').on('click', function(e) {
        e.stopPropagation();
    });
});
