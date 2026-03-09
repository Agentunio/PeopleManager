document.addEventListener('DOMContentLoaded', function () {
    const overlay = document.getElementById('shiftModalOverlay');
    const modalDate = document.getElementById('shiftModalDate');
    const closeBtn = document.getElementById('shiftModalClose');
    const cancelBtn = document.getElementById('shiftModalCancel');
    const saveBtn = document.getElementById('shiftModalSave');
    const morningCheckbox = document.getElementById('shiftMorning');
    const afternoonCheckbox = document.getElementById('shiftAfternoon');

    const months = [
        'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca',
        'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia'
    ];

    let selectedDate = null;

    document.querySelectorAll('.cal-day').forEach(function (day) {
        day.addEventListener('click', function () {
            selectedDate = this.dataset.date;
            const d = new Date(selectedDate + 'T00:00:00');
            modalDate.textContent = d.getDate() + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();

            // Check if user already signed up by looking for .mine workers in shift sections
            const morningSection = this.querySelector('.morning-label');
            const afternoonSection = this.querySelector('.afternoon-label');
            const hasMorning = morningSection && morningSection.closest('.cal-shift-section').querySelector('.cal-worker.mine');
            const hasAfternoon = afternoonSection && afternoonSection.closest('.cal-shift-section').querySelector('.cal-worker.mine');

            morningCheckbox.checked = !!hasMorning;
            afternoonCheckbox.checked = !!hasAfternoon;

            overlay.classList.add('active');
        });
    });

    function closeModal() {
        overlay.classList.remove('active');
        selectedDate = null;
    }

    closeBtn.addEventListener('click', closeModal);
    cancelBtn.addEventListener('click', closeModal);

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) {
            closeModal();
        }
    });

    saveBtn.addEventListener('click', function () {
        // Placeholder - w przyszłości AJAX request
        closeModal();
    });
});
