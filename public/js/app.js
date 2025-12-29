// Global Toast configuration
const Toast = Swal.mixin({
    toast: true,
    position: 'top-end',
    showConfirmButton: false,
    timer: 3000,
    timerProgressBar: true,
    background: '#1f1f1f',
    color: '#f0f0f0',
    didOpen: (toast) => {
        toast.onmouseenter = Swal.stopTimer;
        toast.onmouseleave = Swal.resumeTimer;
    }
});

window.showToast = {
    success: (message) => Toast.fire({ icon: 'success', title: message }),
    error: (message) => Toast.fire({ icon: 'error', title: message }),
    warning: (message) => Toast.fire({ icon: 'warning', title: message }),
    info: (message) => Toast.fire({ icon: 'info', title: message })
};
