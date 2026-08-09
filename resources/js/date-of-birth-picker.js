const MIN_YEAR = 1950;

const MONTHS = [
    'Styczeń',
    'Luty',
    'Marzec',
    'Kwiecień',
    'Maj',
    'Czerwiec',
    'Lipiec',
    'Sierpień',
    'Wrzesień',
    'Październik',
    'Listopad',
    'Grudzień',
];

const MONTHS_IN_DATE = [
    'stycznia',
    'lutego',
    'marca',
    'kwietnia',
    'maja',
    'czerwca',
    'lipca',
    'sierpnia',
    'września',
    'października',
    'listopada',
    'grudnia',
];

const STEP_CONTENT = [
    {
        title: 'Wybierz rok',
        help: 'Najpierw wybierz rok urodzenia.',
        className: 'year-options',
    },
    {
        title: 'Wybierz miesiąc',
        help: 'Teraz wybierz miesiąc urodzenia.',
        className: 'month-options',
    },
    {
        title: 'Wybierz dzień',
        help: 'Na końcu wybierz dzień urodzenia.',
        className: 'day-options',
    },
];

function parseIsoDate(value, today) {
    const match = /^(\d{4})-(\d{2})-(\d{2})$/.exec(value);

    if (!match) {
        return null;
    }

    const year = Number(match[1]);
    const month = Number(match[2]) - 1;
    const day = Number(match[3]);
    const date = new Date(year, month, day);

    if (
        year < MIN_YEAR
        || date.getFullYear() !== year
        || date.getMonth() !== month
        || date.getDate() !== day
        || date > today
    ) {
        return null;
    }

    return { year, month, day };
}

function toIsoDate({ year, month, day }) {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function toDisplayDate({ year, month, day }) {
    return `${day} ${MONTHS_IN_DATE[month]} ${year}`;
}

export function initDateOfBirthPicker() {
    const trigger = document.getElementById('date-picker-trigger');
    const dialog = document.getElementById('date-picker-dialog');
    const hiddenInput = document.getElementById('date_of_birth');

    if (!trigger || !dialog || !hiddenInput) {
        return;
    }

    const form = trigger.closest('form');
    const value = trigger.querySelector('.date-picker-value');
    const title = dialog.querySelector('#date-picker-title');
    const help = dialog.querySelector('#date-picker-help');
    const progress = dialog.querySelector('#date-picker-progress');
    const options = dialog.querySelector('#date-picker-options');
    const stepIndicators = [...dialog.querySelectorAll('.date-picker-steps span')];
    const backButton = dialog.querySelector('.date-picker-back');
    const closeButtons = dialog.querySelectorAll('.date-picker-close, .date-picker-cancel');
    const error = document.getElementById('date-picker-error');
    const today = new Date();
    today.setHours(23, 59, 59, 999);

    let selectedDate = parseIsoDate(hiddenInput.value, today);
    let draftDate = selectedDate ? { ...selectedDate } : { year: null, month: null, day: null };
    let step = 0;

    if (!selectedDate) {
        hiddenInput.value = '';
    }

    function updateTrigger() {
        const hasValue = selectedDate !== null;
        value.textContent = hasValue ? toDisplayDate(selectedDate) : 'Wybierz datę urodzenia';
        value.classList.toggle('is-placeholder', !hasValue);
        trigger.classList.remove('is-invalid');
        trigger.setAttribute('aria-invalid', 'false');
        error.hidden = true;
    }

    function createOption(label, isSelected, onSelect) {
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'date-picker-option';
        button.textContent = label;
        button.classList.toggle('is-selected', isSelected);
        button.setAttribute('aria-pressed', isSelected ? 'true' : 'false');
        button.addEventListener('click', onSelect);
        return button;
    }

    function renderYears(fragment) {
        for (let year = today.getFullYear(); year >= MIN_YEAR; year -= 1) {
            fragment.appendChild(createOption(String(year), draftDate.year === year, () => {
                draftDate = { year, month: null, day: null };
                step = 1;
                renderStep();
            }));
        }
    }

    function renderMonths(fragment) {
        const lastMonth = draftDate.year === today.getFullYear() ? today.getMonth() : 11;

        for (let month = 0; month <= lastMonth; month += 1) {
            fragment.appendChild(createOption(MONTHS[month], draftDate.month === month, () => {
                draftDate.month = month;
                draftDate.day = null;
                step = 2;
                renderStep();
            }));
        }
    }

    function renderDays(fragment) {
        const daysInMonth = new Date(draftDate.year, draftDate.month + 1, 0).getDate();
        const isCurrentMonth = draftDate.year === today.getFullYear() && draftDate.month === today.getMonth();
        const lastDay = isCurrentMonth ? today.getDate() : daysInMonth;

        for (let day = 1; day <= lastDay; day += 1) {
            fragment.appendChild(createOption(String(day), draftDate.day === day, () => {
                draftDate.day = day;
                selectedDate = { ...draftDate };
                hiddenInput.value = toIsoDate(selectedDate);
                updateTrigger();
                closeDialog();
            }));
        }
    }

    function renderStep() {
        const content = STEP_CONTENT[step];
        title.textContent = content.title;
        help.textContent = content.help;
        progress.textContent = `Krok ${step + 1} z 3`;
        backButton.hidden = step === 0;
        options.className = `date-picker-options ${content.className}`;
        options.replaceChildren();

        stepIndicators.forEach((indicator, index) => {
            indicator.classList.toggle('is-active', index <= step);
        });

        if (step === 2 && draftDate.year !== null && draftDate.month !== null) {
            help.textContent = `${MONTHS[draftDate.month]} ${draftDate.year}. Wybierz dzień urodzenia.`;
        }

        const fragment = document.createDocumentFragment();

        if (step === 0) {
            renderYears(fragment);
        } else if (step === 1) {
            renderMonths(fragment);
        } else {
            renderDays(fragment);
        }

        options.appendChild(fragment);

        requestAnimationFrame(() => {
            const nextFocus = options.querySelector('.is-selected') || options.querySelector('.date-picker-option');
            nextFocus?.focus({ preventScroll: true });
            nextFocus?.scrollIntoView({ block: 'nearest' });
        });
    }

    function openDialog() {
        draftDate = selectedDate ? { ...selectedDate } : { year: null, month: null, day: null };
        step = 0;
        renderStep();
        trigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('date-picker-open');

        if (typeof dialog.showModal === 'function') {
            dialog.showModal();
        } else {
            dialog.setAttribute('open', '');
        }
    }

    function closeDialog() {
        if (dialog.open && typeof dialog.close === 'function') {
            dialog.close();
        } else {
            dialog.removeAttribute('open');
            trigger.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('date-picker-open');
            trigger.focus();
        }
    }

    trigger.addEventListener('click', openDialog);

    closeButtons.forEach(button => {
        button.addEventListener('click', closeDialog);
    });

    backButton.addEventListener('click', () => {
        if (step > 0) {
            step -= 1;
            renderStep();
        }
    });

    dialog.addEventListener('click', event => {
        if (event.target === dialog) {
            closeDialog();
        }
    });

    dialog.addEventListener('close', () => {
        trigger.setAttribute('aria-expanded', 'false');
        document.body.classList.remove('date-picker-open');
        trigger.focus();
    });

    form?.addEventListener('submit', event => {
        if (hiddenInput.value) {
            return;
        }

        event.preventDefault();
        trigger.classList.add('is-invalid');
        trigger.setAttribute('aria-invalid', 'true');
        error.hidden = false;
        openDialog();
    });

    updateTrigger();
}
