document.addEventListener('DOMContentLoaded', function () {
    const ratesPage = document.querySelector('.rates-page');

    if (!ratesPage) {
        return;
    }

    const addToggle = document.getElementById('toggle-package-form');
    const editToggles = Array.from(document.querySelectorAll('.rate-edit-toggle'));
    const deleteContainers = Array.from(document.querySelectorAll('[data-delete-form], [data-new-delete]'));
    const newDeleteSubmit = document.querySelector('[data-new-delete-submit]');
    let openDeleteForm = null;

    function focusFirstField(container) {
        window.requestAnimationFrame(function () {
            container?.querySelector('input:not([type="hidden"])')?.focus();
        });
    }

    function closeEditForms(exceptToggle = null) {
        editToggles.forEach(function (toggle) {
            if (toggle !== exceptToggle) {
                toggle.checked = false;
            }
        });
    }

    addToggle?.addEventListener('change', function () {
        if (!addToggle.checked) {
            return;
        }

        closeEditForms();
        focusFirstField(document.getElementById('packageForm'));
    });

    editToggles.forEach(function (toggle) {
        toggle.addEventListener('change', function () {
            if (!toggle.checked) {
                return;
            }

            if (addToggle) {
                addToggle.checked = false;
            }

            closeEditForms(toggle);
            focusFirstField(toggle.closest('.rate-row')?.querySelector('.rate-edit-form'));
        });
    });

    document.querySelectorAll('[data-default-form]').forEach(function (form) {
        form.addEventListener('submit', function () {
            form.classList.add('is-submitting');
            form.querySelector('button[type="submit"]')?.setAttribute('aria-busy', 'true');
        });
    });

    function setDeleteOpen(form, isOpen, restoreFocus = false) {
        const trigger = form.querySelector('[data-delete-trigger]');
        const confirm = form.querySelector('[data-delete-confirm]');

        if (!trigger || !confirm) {
            return;
        }

        confirm.hidden = !isOpen;
        trigger.setAttribute('aria-expanded', String(isOpen));

        if (isOpen) {
            openDeleteForm = form;

            if (window.matchMedia('(max-width: 768px)').matches) {
                document.body.classList.add('rates-modal-open');
            }

            window.requestAnimationFrame(function () {
                form.querySelector('[data-delete-cancel]')?.focus();
            });

            return;
        }

        if (openDeleteForm === form) {
            openDeleteForm = null;
        }

        document.body.classList.remove('rates-modal-open');

        if (restoreFocus) {
            trigger.focus();
        }
    }

    deleteContainers.forEach(function (form) {
        const trigger = form.querySelector('[data-delete-trigger]');
        const cancel = form.querySelector('[data-delete-cancel]');
        const confirm = form.querySelector('[data-delete-confirm]');

        trigger?.addEventListener('click', function () {
            if (openDeleteForm && openDeleteForm !== form) {
                setDeleteOpen(openDeleteForm, false);
            }

            setDeleteOpen(form, trigger.getAttribute('aria-expanded') !== 'true');
        });

        cancel?.addEventListener('click', function () {
            setDeleteOpen(form, false, true);
        });

        confirm?.addEventListener('click', function (event) {
            if (event.target === confirm) {
                setDeleteOpen(form, false, true);
            }
        });
    });

    newDeleteSubmit?.addEventListener('click', function () {
        const addForm = document.getElementById('packageForm');

        if (openDeleteForm) {
            setDeleteOpen(openDeleteForm, false);
        }

        addForm?.reset();

        if (addToggle) {
            addToggle.checked = false;
        }
    });

    document.addEventListener('click', function (event) {
        if (openDeleteForm && !openDeleteForm.contains(event.target)) {
            setDeleteOpen(openDeleteForm, false);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && openDeleteForm) {
            setDeleteOpen(openDeleteForm, false, true);
        }
    });

    window.addEventListener('resize', function () {
        if (!window.matchMedia('(max-width: 768px)').matches) {
            document.body.classList.remove('rates-modal-open');
        }
    });
});