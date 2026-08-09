import '../css/pm-dialog.css';

const FOCUSABLE = 'button:not([disabled]), [href], input:not([disabled]), [tabindex]:not([tabindex="-1"])';

let openDialog = null;

function buildDialog({ title, text, confirmText, cancelText, tone }) {
    const backdrop = document.createElement('div');
    const dialog = document.createElement('div');
    const icon = document.createElement('div');
    const heading = document.createElement('h2');
    const body = document.createElement('p');
    const actions = document.createElement('div');
    const confirm = document.createElement('button');
    const cancel = document.createElement('button');
    const titleId = `pm-dialog-title-${Date.now()}`;

    backdrop.className = 'pm-dialog-backdrop';
    dialog.className = `pm-dialog pm-dialog--${tone}`;
    dialog.setAttribute('role', 'alertdialog');
    dialog.setAttribute('aria-modal', 'true');
    dialog.setAttribute('aria-labelledby', titleId);

    icon.className = 'pm-dialog-icon';
    icon.setAttribute('aria-hidden', 'true');
    icon.textContent = '!';

    heading.className = 'pm-dialog-title';
    heading.id = titleId;
    heading.textContent = title;

    body.className = 'pm-dialog-text';
    body.textContent = text || '';
    body.hidden = !text;

    confirm.type = 'button';
    confirm.className = 'pm-dialog-button pm-dialog-button--confirm';
    confirm.textContent = confirmText;

    cancel.type = 'button';
    cancel.className = 'pm-dialog-button pm-dialog-button--cancel';
    cancel.textContent = cancelText;

    actions.className = 'pm-dialog-actions';
    actions.append(confirm, cancel);
    dialog.append(icon, heading, body, actions);
    backdrop.append(dialog);

    return { backdrop, dialog, confirm, cancel };
}

/**
 * Potwierdzenie akcji przed operacją nieodwracalną.
 * Zwraca Promise<boolean>: true dla potwierdzenia, false dla anulowania,
 * Escape i kliknięcia w tło.
 */
export function confirmDialog({
    title,
    text = '',
    confirmText = 'Potwierdź',
    cancelText = 'Anuluj',
    tone = 'danger',
} = {}) {
    // Drugie wywołanie nie może osierocić pierwszego backdropu.
    if (openDialog) openDialog.close(false);

    const { backdrop, dialog, confirm, cancel } = buildDialog({ title, text, confirmText, cancelText, tone });
    const previouslyFocused = document.activeElement;

    return new Promise((resolve) => {
        function close(result) {
            if (openDialog !== handle) return;

            openDialog = null;
            document.removeEventListener('keydown', onKeydown, true);
            backdrop.remove();
            document.body.classList.remove('pm-dialog-open');

            if (previouslyFocused instanceof HTMLElement && document.contains(previouslyFocused)) {
                previouslyFocused.focus();
            }

            resolve(result);
        }

        function onKeydown(event) {
            if (event.key === 'Escape') {
                event.preventDefault();
                // Bez zatrzymania propagacji Escape zamyka też warstwę pod
                // dialogiem (np. drawer pracownika), a nie tylko potwierdzenie.
                event.stopPropagation();
                close(false);
                return;
            }

            if (event.key !== 'Tab') return;

            const items = Array.from(dialog.querySelectorAll(FOCUSABLE));
            if (!items.length) return;

            const first = items[0];
            const last = items[items.length - 1];

            if (event.shiftKey && document.activeElement === first) {
                event.preventDefault();
                last.focus();
            } else if (!event.shiftKey && document.activeElement === last) {
                event.preventDefault();
                first.focus();
            }
        }

        const handle = { close };
        openDialog = handle;

        confirm.addEventListener('click', () => close(true));
        cancel.addEventListener('click', () => close(false));
        backdrop.addEventListener('mousedown', (event) => {
            if (event.target === backdrop) close(false);
        });
        document.addEventListener('keydown', onKeydown, true);

        document.body.append(backdrop);
        document.body.classList.add('pm-dialog-open');
        // Bez requestAnimationFrame — w karcie w tle rAF nie odpala, a wtedy
        // dialog otwierałby się bez fokusu i pułapka Tab nie miałaby punktu startu.
        confirm.focus();
    });
}

export default confirmDialog;
