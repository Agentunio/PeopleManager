import '../css/notice.css';

const DEFAULT_TIMER = 4000;
const LEAVE_MS = 200;
const TYPES = ['success', 'error', 'warning', 'info'];

let stack = null;

function getStack() {
    if (stack && document.contains(stack)) return stack;

    stack = document.createElement('div');
    stack.className = 'pm-notice-stack';
    stack.setAttribute('role', 'region');
    stack.setAttribute('aria-live', 'polite');
    stack.setAttribute('aria-label', 'Powiadomienia');
    document.body.append(stack);

    return stack;
}

export function showToast(type, message, options = {}) {
    const safeType = TYPES.includes(type) ? type : 'info';
    const duration = Number(options.timer) > 0 ? Number(options.timer) : DEFAULT_TIMER;

    const notice = document.createElement('div');
    const title = document.createElement('p');
    const progress = document.createElement('span');

    notice.className = `pm-notice pm-notice--${safeType}`;
    notice.setAttribute('role', safeType === 'error' ? 'alert' : 'status');

    title.className = 'pm-notice-title';
    // textContent, nie innerHTML — treść bywa komunikatem z odpowiedzi serwera.
    title.textContent = String(message ?? '');

    progress.className = 'pm-notice-progress';
    progress.style.animationDuration = `${duration}ms`;

    notice.append(title, progress);
    getStack().append(notice);

    let timer = null;
    let remaining = duration;
    let startedAt = Date.now();
    let dismissed = false;

    // Usunięcie po stałym czasie, nie na animationend: to zdarzenie bąbelkuje
    // też z paska postępu i w ogóle nie leci przy prefers-reduced-motion,
    // przez co toast potrafił zostać na ekranie na stałe.
    function dismiss() {
        if (dismissed) return;

        dismissed = true;
        window.clearTimeout(timer);
        notice.classList.add('is-leaving');
        window.setTimeout(() => notice.remove(), LEAVE_MS);
    }

    function resume() {
        if (dismissed) return;

        startedAt = Date.now();
        timer = window.setTimeout(dismiss, remaining);
        progress.style.animationPlayState = 'running';
    }

    function pause() {
        if (dismissed) return;

        window.clearTimeout(timer);
        remaining -= Date.now() - startedAt;
        progress.style.animationPlayState = 'paused';
    }

    notice.addEventListener('mouseenter', pause);
    notice.addEventListener('mouseleave', resume);
    notice.addEventListener('click', dismiss);
    resume();

    return notice;
}

export const toast = {
    success: (message, options = {}) => showToast('success', message, options),
    error: (message, options = {}) => showToast('error', message, options),
    warning: (message, options = {}) => showToast('warning', message, options),
    info: (message, options = {}) => showToast('info', message, options),
};

export function showFlashError(selector = 'meta[name="flash-error"]') {
    const flashError = document.querySelector(selector);
    if (!flashError) {
        return;
    }

    toast.error(flashError.content);
}

window.showToast = toast;
