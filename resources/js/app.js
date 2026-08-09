import './notice';

document.addEventListener('DOMContentLoaded', function() {
    const mobileMenuToggle = document.getElementById('mobileMenuToggle');
    const mobileMenuClose = document.getElementById('mobileMenuClose');
    const sidebar = document.getElementById('sidebar');
    const sidebarOverlay = document.getElementById('sidebarOverlay');
    const sidebarBackground = sidebar?.parentElement
        ? Array.from(sidebar.parentElement.children).filter((element) => (
            element.classList.contains('admin-mobile-header')
            || element.classList.contains('main-content')
        ))
        : [];
    const focusableSelector = 'a[href], button:not([disabled]), [tabindex]:not([tabindex="-1"])';

    if (mobileMenuToggle && sidebar && sidebarOverlay) {
        mobileMenuToggle.addEventListener('click', function() {
            toggleMenu();
        });

        sidebarOverlay.addEventListener('click', function() {
            closeMenu();
        });

        mobileMenuClose?.addEventListener('click', function() {
            closeMenu();
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Tab' && sidebar.classList.contains('active') && window.innerWidth <= 768) {
                trapSidebarFocus(e);
                return;
            }

            if (e.key === 'Escape' && sidebar.classList.contains('active')) {
                closeMenu();
            }
        });

        const navLinks = sidebar.querySelectorAll('.nav-links a');
        navLinks.forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    closeMenu();
                }
            });
        });

        syncSidebarAccessibility();
    }

    function toggleMenu() {
        const isOpen = sidebar.classList.toggle('active');
        mobileMenuToggle.classList.toggle('active', isOpen);
        sidebarOverlay.classList.toggle('active', isOpen);
        mobileMenuToggle.setAttribute('aria-expanded', String(isOpen));
        sidebarOverlay.setAttribute('aria-hidden', String(!isOpen));
        syncSidebarAccessibility();
        document.body.style.overflow = isOpen ? 'hidden' : '';

        if (isOpen) {
            window.setTimeout(() => (mobileMenuClose || sidebar.querySelector(focusableSelector))?.focus(), 0);
        }
    }

    function closeMenu() {
        const wasOpen = sidebar.classList.contains('active');
        mobileMenuToggle.classList.remove('active');
        sidebar.classList.remove('active');
        sidebarOverlay.classList.remove('active');
        mobileMenuToggle.setAttribute('aria-expanded', 'false');
        sidebarOverlay.setAttribute('aria-hidden', 'true');
        syncSidebarAccessibility();
        document.body.style.overflow = '';
        if (wasOpen && window.innerWidth <= 768) mobileMenuToggle.focus();
    }

    function syncSidebarAccessibility() {
        if (!sidebar) return;

        const isMobile = window.innerWidth <= 768;
        const isOpen = sidebar.classList.contains('active');
        sidebar.inert = isMobile && !isOpen;
        sidebarBackground.forEach((element) => {
            element.inert = isMobile && isOpen;
        });

        if (isMobile) {
            sidebar.setAttribute('aria-hidden', String(!isOpen));
        } else {
            sidebar.removeAttribute('aria-hidden');
        }
    }

    function trapSidebarFocus(event) {
        const focusable = Array.from(sidebar.querySelectorAll(focusableSelector))
            .filter(element => element.offsetParent !== null);
        if (!focusable.length) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];
        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    if (!mobileMenuToggle || !sidebar || !sidebarOverlay) return;

    let resizeTimer;
    window.addEventListener('resize', function() {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(function() {
            if (window.innerWidth > 768 && sidebar.classList.contains('active')) closeMenu();
            syncSidebarAccessibility();
        }, 250);
    });
});
