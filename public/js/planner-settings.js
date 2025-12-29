$(document).ready(function() {
    // Inicjalizacja date pickerów
    flatpickr("#date-from", {
        enableTime: true,
        time_24hr: true,
        locale: "pl",
        dateFormat: "Y-m-d H:i",
        minDate: "today"
    });

    flatpickr("#date-to", {
        enableTime: true,
        time_24hr: true,
        locale: "pl",
        dateFormat: "Y-m-d H:i",
        minDate: "today"
    });

    // Obsługa wyboru opcji
    $('.availability-option').on('click', function() {
        $('.availability-option').removeClass('selected');
        $(this).addClass('selected');
        $('#save-settings').prop('disabled', false);
    });

    // Aktualizacja dat dla opcji "tydzień"
    function updateWeekDates(days = 7) {
        const now = new Date();
        const end = new Date(now.getTime() + days * 24 * 60 * 60 * 1000);
        
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        
        $('#week-start').text(now.toLocaleDateString('pl-PL', options));
        $('#week-end').text(end.toLocaleDateString('pl-PL', options));
    }

    updateWeekDates();

    // Quick action buttons
    $('.quick-action-btn').on('click', function(e) {
        e.stopPropagation();
        const days = $(this).data('days');
        $('.quick-action-btn').removeClass('active');
        $(this).addClass('active');
        updateWeekDates(days);
    });
});
