$(document).ready(function () {
    // Obsługa potwierdzenia usuwania przez SweetAlert2
    $('.delete-form').on('submit', function(e) {
        e.preventDefault();
        const form = this;
        const name = $(this).data('name');
        
        Swal.fire({
            title: 'Czy na pewno?',
            text: `Chcesz usunąć pakiet: ${name}?`,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#e50914',
            cancelButtonColor: '#555',
            confirmButtonText: 'Tak, usuń',
            cancelButtonText: 'Anuluj',
            background: '#1f1f1f',
            color: '#f0f0f0'
        }).then((result) => {
            if (result.isConfirmed) {
                form.submit();
            }
        });
    });
});
