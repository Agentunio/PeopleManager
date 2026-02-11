document.addEventListener('DOMContentLoaded', function() {
    const dateRangePicker = flatpickr("#dateRangePicker", {
        mode: "range",
        dateFormat: "d.m.Y",
        locale: "pl",
        defaultDate: [
            new Date(new Date().getFullYear(), new Date().getMonth(), 1),
            new Date()
        ],
        onChange: function(selectedDates, dateStr, instance) {
            if (selectedDates.length === 2) {
                console.log('Date range selected:', selectedDates);
            }
        }
    });

    const refreshBtn = document.getElementById('refreshData');
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function() {
            const icon = this.querySelector('i');
            icon.classList.add('fa-spin');

            setTimeout(() => {
                icon.classList.remove('fa-spin');
            }, 1000);
        });
    }
});
