function showLoginError(message) {
    Swal.fire({
        toast: true,
        position: 'top-end',
        icon: 'error',
        title: message,
        showConfirmButton: false,
        timer: 4000,
        timerProgressBar: true,
        background: '#1f1f1f',
        color: '#f0f0f0'
    });
}
