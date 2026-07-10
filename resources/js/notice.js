import Swal from 'sweetalert2';
import '../css/notice.css';

const baseCustomClass = {
    popup: 'pm-notice',
    title: 'pm-notice-title',
};

const Notice = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 4000,
    timerProgressBar: true,
    customClass: baseCustomClass,
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    },
});

function getCustomClass(type, customClass = {}) {
    return {
        ...baseCustomClass,
        ...customClass,
        popup: [
            baseCustomClass.popup,
            `pm-notice--${type}`,
            customClass.popup,
        ].filter(Boolean).join(' '),
    };
}

export function showToast(type, message, options = {}) {
    const { customClass, ...toastOptions } = options;

    return Notice.fire({
        title: message,
        customClass: getCustomClass(type, customClass),
        ...toastOptions,
    });
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
