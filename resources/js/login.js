import Swal from 'sweetalert2';

document.addEventListener('DOMContentLoaded', function () {
    const flashError = document.querySelector('meta[name="flash-error"]');
    if (flashError) {
        Swal.fire({
            toast: true,
            position: 'top-end',
            icon: 'error',
            title: flashError.content,
            showConfirmButton: false,
            timer: 4000,
            timerProgressBar: true,
            background: '#1f1f1f',
            color: '#f0f0f0'
        });
    }
});
