/**
 * MediCare Hub - General Application JavaScript
 * Handles sidebar, flash messages, modals, form validation, and tooltips.
 */
document.addEventListener('DOMContentLoaded', function () {

    // ──────────────────────────────────────────────
    // 1. Sidebar Toggle
    // ──────────────────────────────────────────────
    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.querySelector('.sidebar');
    const sidebarOverlay = document.querySelector('.sidebar-overlay');

    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function () {
            sidebar.classList.toggle('show');

            // Create overlay if it doesn't exist yet
            let overlay = document.querySelector('.sidebar-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.classList.add('sidebar-overlay');
                document.body.appendChild(overlay);
            }

            if (sidebar.classList.contains('show')) {
                overlay.classList.add('active');
            } else {
                overlay.classList.remove('active');
            }

            // Close sidebar when overlay is clicked
            overlay.addEventListener('click', function () {
                sidebar.classList.remove('show');
                overlay.classList.remove('active');
            });
        });
    }

    // Also close sidebar if a pre-existing overlay is clicked
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', function () {
            if (sidebar) {
                sidebar.classList.remove('show');
            }
            sidebarOverlay.classList.remove('active');
        });
    }

    // ──────────────────────────────────────────────
    // 2. Auto-hide Flash Messages (after 4 seconds)
    // ──────────────────────────────────────────────
    const flashMessages = document.querySelectorAll('.flash-message');
    flashMessages.forEach(function (msg) {
        setTimeout(function () {
            msg.classList.add('hiding');
            msg.addEventListener('animationend', function () {
                msg.remove();
            });
            // Fallback removal in case animationend doesn't fire
            setTimeout(function () {
                if (msg.parentNode) {
                    msg.remove();
                }
            }, 600);
        }, 4000);
    });

    // ──────────────────────────────────────────────
    // 3. Delete Modal Handler
    // ──────────────────────────────────────────────
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function (event) {
            const triggerButton = event.relatedTarget;

            // Get action URL and item name from trigger button data attributes
            const actionUrl = triggerButton.getAttribute('data-action');
            const itemName = triggerButton.getAttribute('data-name');

            // Set the form action
            const deleteForm = deleteModal.querySelector('form');
            if (deleteForm && actionUrl) {
                deleteForm.setAttribute('action', actionUrl);
            }

            // Display the item name in the modal
            const itemNameDisplay = deleteModal.querySelector('.item-name');
            if (itemNameDisplay && itemName) {
                itemNameDisplay.textContent = itemName;
            }
        });
    }

    // ──────────────────────────────────────────────
    // 4. Form Validation (Bootstrap)
    // ──────────────────────────────────────────────
    const forms = document.querySelectorAll('form.needs-validation');
    forms.forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        });
    });

    // ──────────────────────────────────────────────
    // 5. Close Sidebar on Nav Link Click (Mobile)
    // ──────────────────────────────────────────────
    const navLinks = document.querySelectorAll('.sidebar .nav-link');
    navLinks.forEach(function (link) {
        link.addEventListener('click', function () {
            if (window.innerWidth < 992 && sidebar) {
                sidebar.classList.remove('show');
                const overlay = document.querySelector('.sidebar-overlay');
                if (overlay) {
                    overlay.classList.remove('active');
                }
            }
        });
    });

    // ──────────────────────────────────────────────
    // 6. Bootstrap Tooltip Initialization
    // ──────────────────────────────────────────────
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(function (tooltipTriggerEl) {
        new bootstrap.Tooltip(tooltipTriggerEl);
    });

});
