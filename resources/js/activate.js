import { initDateOfBirthPicker } from './date-of-birth-picker';
import { guardGuestSubmitForms } from './guest-submit-guard';
import { showFlashError } from './notice';
import { initPasswordValidation } from './password-validation';

document.addEventListener('DOMContentLoaded', function () {
    initDateOfBirthPicker();
    guardGuestSubmitForms();

    initPasswordValidation();

    showFlashError();
});
