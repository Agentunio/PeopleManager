
    document.addEventListener('DOMContentLoaded', function() {
    const formatDate = (date) => {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

    const today = new Date();
    const firstDay = new Date(today.getFullYear(), today.getMonth(), 1);

    const todayString = formatDate(today);
    const firstDayString = formatDate(firstDay);

    const rangeInputs = document.querySelectorAll('.flatpickr-range-input');

    rangeInputs.forEach(input => {

    flatpickr(input, {
    theme: "dark",
    mode: "range",
    dateFormat: "Y-m-d",
    defaultDate: [firstDayString, todayString],
    locale: "pl",
    onChange: function(selectedDates, dateStr, instance) {
    const form = instance.element.closest('.date-range-form-inner');
    if (!form) return;
    const fromInput = form.querySelector('input[name="dateFrom"]');
    const toInput = form.querySelector('input[name="dateTo"]');
    if (selectedDates.length === 2 && fromInput && toInput) {
    fromInput.value = formatDate(selectedDates[0]);
    toInput.value = formatDate(selectedDates[1]);
}
}
});
});
});