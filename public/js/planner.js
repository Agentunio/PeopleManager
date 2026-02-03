$(document).ready(function() {
    const settledDays = window.settledDays || [];

    flatpickr("#calendar-inline", {
        inline: true,
        locale: "pl",
        dateFormat: "Y-m-d",
        onChange: function(selectedDates, dateStr) {
            window.location.href = "/grafik/" + dateStr;
        },
        onDayCreate: function(dObj, dStr, fp, dayElem) {
            const date = dayElem.dateObj;
            const year = date.getFullYear();
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const day = String(date.getDate()).padStart(2, '0');
            const dateStr = `${year}-${month}-${day}`;

            if (settledDays.includes(dateStr)) {
                dayElem.classList.add('day-settled');
            }
        }
    });
});
