import { guardGuestSubmitForms } from './guest-submit-guard';
import { showFlashError, toast } from './notice';
import { initPasswordValidation } from './password-validation';

document.addEventListener('DOMContentLoaded', function () {
    guardGuestSubmitForms();
    initPasswordValidation();
    showFlashError();

    const status = document.querySelector('meta[name="flash-status"]');
    if (status) {
        toast.success(status.content);
    }
});
