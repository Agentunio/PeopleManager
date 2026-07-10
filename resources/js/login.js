import { guardGuestSubmitForms } from './guest-submit-guard';
import { showFlashError } from './notice';

document.addEventListener('DOMContentLoaded', function () {
    guardGuestSubmitForms();
    showFlashError();
});
