$(document).ready(function() {
    var $alerts = $('.alert-error, .alert-success');

    if ($alerts.length) {
        setTimeout(function() {
            $alerts.fadeOut(600, function() {
                $(this).remove();
            });
        }, 5000);
    }
});