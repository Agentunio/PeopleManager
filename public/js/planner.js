$(document).ready(function() {
    flatpickr("#calendar-inline", {
        inline: true,
        locale: "pl",
        dateFormat: "Y-m-d",
        onChange: function(selectedDates, dateStr) {
            window.location.href = window.plannerDayUrl + "/" + dateStr;
        }
    });
});
