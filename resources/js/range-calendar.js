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
    'Grudzień'
];

const WEEKDAYS = [
    ['Pn', 'Poniedziałek'],
    ['Wt', 'Wtorek'],
    ['Śr', 'Środa'],
    ['Cz', 'Czwartek'],
    ['Pt', 'Piątek'],
    ['So', 'Sobota'],
    ['Nd', 'Niedziela']
];

export function pad2(value) {
    return value < 10 ? `0${value}` : String(value);
}

export function isoOf(year, month, day) {
    return `${year}-${pad2(month + 1)}-${pad2(day)}`;
}

export function monthKeyOf(year, month) {
    const normalized = new Date(year, month, 1);

    return `${normalized.getFullYear()}-${pad2(normalized.getMonth() + 1)}`;
}

export function daysInMonth(year, month) {
    return new Date(year, month + 1, 0).getDate();
}

export function shortIso(value) {
    return `${value.slice(8, 10)}.${value.slice(5, 7)}`;
}

function monthView(value) {
    return {
        year: Number(value.slice(0, 4)),
        month: Number(value.slice(5, 7)) - 1
    };
}

function normalizeRange(from, to) {
    return from <= to ? { from, to } : { from: to, to: from };
}

function formatAccessibleDate(value) {
    const date = new Date(
        Number(value.slice(0, 4)),
        Number(value.slice(5, 7)) - 1,
        Number(value.slice(8, 10))
    );

    return date.toLocaleDateString('pl-PL', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        year: 'numeric'
    });
}

function normalizeClassNames(value) {
    if (!value) return [];

    return (Array.isArray(value) ? value : String(value).split(/\s+/)).filter(Boolean);
}

export function createRangeCalendar(options) {
    const container = options.container;

    if (!container) return null;

    const initialRange = normalizeRange(options.initialFrom, options.initialTo);
    let range = initialRange;
    let pendingStart = null;
    let view = monthView(options.initialMonth || initialRange.to.slice(0, 7));

    const content = document.createElement('div');
    const status = document.createElement('span');
    status.className = 'range-calendar-status';
    status.setAttribute('aria-live', 'polite');
    status.setAttribute('aria-atomic', 'true');

    container.replaceChildren(content, status);
    container.setAttribute('role', 'group');
    if (!container.hasAttribute('aria-label')) {
        container.setAttribute('aria-label', 'Kalendarz wyboru zakresu dat');
    }

    function announce(message) {
        status.textContent = '';
        window.requestAnimationFrame(() => {
            status.textContent = message;
        });
    }

    function isDateDisabled(iso) {
        return (options.minDate && iso < options.minDate)
            || (options.maxDate && iso > options.maxDate);
    }

    function canMoveTo(key) {
        return (!options.minMonth || key >= options.minMonth)
            && (!options.maxMonth || key <= options.maxMonth);
    }

    function createNavigationButton(direction, key) {
        const button = document.createElement('button');
        const isPrevious = direction === 'previous';
        const adjacent = monthKeyOf(view.year, view.month + (isPrevious ? -1 : 1));

        button.type = 'button';
        button.className = 'cal-nav';
        button.dataset.calendarNavigation = direction;
        button.setAttribute('aria-label', isPrevious ? 'Poprzedni miesiąc' : 'Następny miesiąc');
        button.textContent = isPrevious ? '‹' : '›';
        button.disabled = !canMoveTo(adjacent);

        return button;
    }

    function restoreFocus(focusTarget) {
        if (!focusTarget) return;

        const selector = focusTarget === 'previous' || focusTarget === 'next'
            ? `[data-calendar-navigation="${focusTarget}"]`
            : `[data-date="${focusTarget}"]`;
        const target = content.querySelector(selector);
        const fallback = content.querySelector('[data-calendar-navigation]:not([disabled])');

        if (target && !target.disabled) {
            target.focus();
        } else {
            fallback?.focus();
        }
    }

    function render(focusTarget = null) {
        const activeElement = document.activeElement;
        const retainedFocus = focusTarget || (content.contains(activeElement)
            ? (activeElement.dataset.date || activeElement.dataset.calendarNavigation)
            : null);
        const key = monthKeyOf(view.year, view.month);
        const firstWeekday = (new Date(view.year, view.month, 1).getDay() + 6) % 7;
        const totalDays = daysInMonth(view.year, view.month);
        const fragment = document.createDocumentFragment();
        const header = document.createElement('div');
        const title = document.createElement('span');
        const weekdays = document.createElement('div');
        const grid = document.createElement('div');

        header.className = 'cal-head';
        title.className = 'cal-title';
        title.textContent = `${MONTHS[view.month]} ${view.year}`;
        title.setAttribute('aria-live', 'polite');
        header.append(
            createNavigationButton('previous', key),
            title,
            createNavigationButton('next', key)
        );

        weekdays.className = 'cal-dows';
        weekdays.setAttribute('aria-hidden', 'true');
        WEEKDAYS.forEach(([shortName, fullName]) => {
            const weekday = document.createElement('abbr');
            weekday.title = fullName;
            weekday.textContent = shortName;
            weekdays.append(weekday);
        });

        grid.className = 'cal-grid';
        for (let index = 0; index < firstWeekday; index += 1) {
            const padding = document.createElement('span');
            padding.className = 'cal-pad';
            padding.setAttribute('aria-hidden', 'true');
            grid.append(padding);
        }

        for (let day = 1; day <= totalDays; day += 1) {
            const iso = isoOf(view.year, view.month, day);
            const button = document.createElement('button');
            const isSelected = iso >= range.from && iso <= range.to;

            button.type = 'button';
            button.className = 'cal-day';
            button.classList.add(...normalizeClassNames(options.getDayClassNames?.(iso)));
            button.classList.toggle('is-today', iso === options.today);
            button.classList.toggle('is-start', iso === range.from);
            button.classList.toggle('is-end', iso === range.to);
            button.classList.toggle('is-in-range', iso > range.from && iso < range.to);
            button.dataset.date = iso;
            button.textContent = String(day);
            button.disabled = Boolean(isDateDisabled(iso));
            button.setAttribute('aria-label', formatAccessibleDate(iso));
            button.setAttribute('aria-pressed', String(isSelected));
            if (iso === options.today) {
                button.setAttribute('aria-current', 'date');
            }

            grid.append(button);
        }

        fragment.append(header, weekdays, grid);
        content.replaceChildren(fragment);
        restoreFocus(retainedFocus);
    }

    function shiftMonth(delta, focusTarget) {
        const moved = new Date(view.year, view.month + delta, 1);
        const key = monthKeyOf(moved.getFullYear(), moved.getMonth());

        if (!canMoveTo(key)) return;

        view = { year: moved.getFullYear(), month: moved.getMonth() };
        render(focusTarget);
        announce(`Wyświetlono ${MONTHS[view.month]} ${view.year}.`);
        options.onViewChange?.(key);
    }

    function pickDay(iso) {
        if (pendingStart === null) {
            pendingStart = iso;
            range = { from: iso, to: iso };
            render(iso);
            announce(`Wybrano datę początkową: ${formatAccessibleDate(iso)}. Wybierz datę końcową.`);
            options.onRangePreview?.({ ...range });
            return;
        }

        range = normalizeRange(pendingStart, iso);
        pendingStart = null;
        render(iso);
        announce(`Wybrano zakres od ${formatAccessibleDate(range.from)} do ${formatAccessibleDate(range.to)}.`);
        options.onRangeChange?.({ ...range });
    }

    function handleClick(event) {
        const previous = event.target.closest('[data-calendar-navigation="previous"]');
        if (previous) {
            shiftMonth(-1, 'previous');
            return;
        }

        const next = event.target.closest('[data-calendar-navigation="next"]');
        if (next) {
            shiftMonth(1, 'next');
            return;
        }

        const day = event.target.closest('.cal-day');
        if (day && !day.disabled) {
            pickDay(day.dataset.date);
        }
    }

    content.addEventListener('click', handleClick);

    const filter = container.closest('.range-filter');
    const toggle = filter?.querySelector('[data-range-toggle]');
    if (toggle) {
        toggle.addEventListener('click', () => {
            const isOpen = filter.classList.toggle('is-open');
            toggle.textContent = isOpen
                ? (toggle.dataset.hideLabel || 'Ukryj kalendarz')
                : (toggle.dataset.showLabel || 'Pokaż kalendarz');
            toggle.setAttribute('aria-expanded', String(isOpen));
        });
    }

    render();

    return {
        getRange() {
            return { ...range };
        },
        getView() {
            return {
                year: view.year,
                month: view.month,
                key: monthKeyOf(view.year, view.month)
            };
        },
        render,
        setRange(from, to) {
            pendingStart = null;
            range = normalizeRange(from, to);
            render();
        },
        setView(value) {
            const nextView = monthView(value.slice(0, 7));
            const key = monthKeyOf(nextView.year, nextView.month);

            if (!canMoveTo(key)) return;

            view = nextView;
            render();
            options.onViewChange?.(key);
        }
    };
}
