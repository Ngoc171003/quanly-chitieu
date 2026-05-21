// Main JavaScript file

document.addEventListener('DOMContentLoaded', function() {
    // Initialize tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });

    // Dynamic Custom Confirm Modal implementation to replace browser's native confirm
    if (!document.getElementById('customConfirmDeleteModal')) {
        const modalHtml = `
        <div class="modal fade" id="customConfirmDeleteModal" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-dialog-centered" style="max-width: 380px;">
                <div class="modal-content border-0 shadow-lg" style="border-radius: 24px; background: rgba(255, 255, 255, 0.98);">
                    <div class="modal-body text-center py-4 px-4">
                        <div class="mb-3">
                            <div class="d-inline-flex align-items-center justify-content-center" style="width: 72px; height: 72px; background-color: rgba(255, 107, 107, 0.12); border-radius: 50%; color: var(--danger-color);">
                                <i class="fas fa-exclamation-triangle fa-2x"></i>
                            </div>
                        </div>
                        <h5 class="fw-bold mb-2" style="color: var(--text-color); font-size: 1.2rem;">Xác nhận xóa</h5>
                        <p class="text-muted mb-4" id="customConfirmMessage" style="font-size: 0.95rem; line-height: 1.5;"></p>
                        <div class="d-flex gap-2 justify-content-center">
                            <button type="button" class="btn btn-secondary w-50 py-2 border" data-bs-dismiss="modal" style="border-radius: 14px; font-weight: 600; background: #fff; border-color: rgba(223, 229, 247, 0.95) !important; color: var(--text-color);">Hủy</button>
                            <button type="button" class="btn btn-danger w-50 py-2" id="btnCustomConfirmDelete" style="border-radius: 14px; font-weight: 600; background: linear-gradient(135deg, var(--danger-color) 0%, #ff6b6b 100%); border: none; color: #fff;">Xóa</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <style>
        .modal-backdrop.show {
            backdrop-filter: blur(4px);
            background-color: rgba(46, 61, 88, 0.4) !important;
        }
        </style>
        `;
        
        // Inject the modal html into body
        const modalContainer = document.createElement('div');
        modalContainer.innerHTML = modalHtml;
        document.body.appendChild(modalContainer);

        const confirmModalEl = document.getElementById('customConfirmDeleteModal');
        const confirmModal = new bootstrap.Modal(confirmModalEl);
        const confirmMessageEl = document.getElementById('customConfirmMessage');
        const confirmBtn = document.getElementById('btnCustomConfirmDelete');
        
        let pendingAction = null;
        
        // Function to handle confirmation triggers
        function setupConfirmTriggers() {
            var deleteLinks = document.querySelectorAll('a[onclick*="confirm"], a.confirm-delete');
            deleteLinks.forEach(function(link) {
                let message = 'Bạn chắc chắn muốn xóa chứ?';
                
                // Extract original confirm message if present in onclick
                if (link.hasAttribute('onclick')) {
                    const onclickVal = link.getAttribute('onclick');
                    const match = onclickVal.match(/confirm\s*\(\s*['"](.*?)['"]\s*\)/);
                    if (match && match[1]) {
                        message = match[1];
                    }
                    // Strip the onclick attribute and property so native confirm never executes
                    link.removeAttribute('onclick');
                    link.onclick = null;
                } else if (link.dataset.message) {
                    message = link.dataset.message;
                }
                
                link.dataset.confirmMessage = message;
                
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    const targetHref = link.getAttribute('href');
                    
                    confirmMessageEl.textContent = link.dataset.confirmMessage;
                    confirmModal.show();
                    
                    pendingAction = function() {
                        if (targetHref && targetHref !== '#') {
                            window.location.href = targetHref;
                        }
                    };
                });
            });
        }
        
        // Initialize triggers
        setupConfirmTriggers();
        
        // Bind click for deletion confirmation
        confirmBtn.addEventListener('click', function() {
            if (pendingAction) {
                pendingAction();
            }
            confirmModal.hide();
        });
    }

    // Format numbers on input (Thousand separators for better UX)
    const amountInputs = document.querySelectorAll('.amount-input');
    amountInputs.forEach(input => {
        const originalName = input.getAttribute('name');
        if (!originalName) return; // Already processed

        // Handle float inputs correctly by converting them to int string
        let initialVal = input.value;
        if (initialVal.includes('.')) {
            initialVal = parseFloat(initialVal).toFixed(0);
        }

        // Create hidden input
        const hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = originalName;
        hiddenInput.value = initialVal.replace(/\D/g, '');
        input.parentNode.appendChild(hiddenInput);
        
        // Remove name from the visible input
        input.removeAttribute('name');

        const formatValue = (val) => {
            if (!val) return '';
            return parseInt(val).toLocaleString('vi-VN').replace(/,/g, '.'); // Force dot as separator
        };

        // Initial format
        if (input.value) {
            input.value = formatValue(initialVal.replace(/\D/g, ''));
        }

        input.addEventListener('input', function() {
            let val = this.value.replace(/\D/g, '');
            hiddenInput.value = val;
            this.value = formatValue(val);
        });
    });

    // Auto-hide alerts after 5 seconds
    var alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            var bsAlert = new bootstrap.Alert(alert);
            bsAlert.close();
        }, 5000);
    });

    // Active navigation link
    var currentLocation = location.pathname.split('/').pop();
    var navLinks = document.querySelectorAll('.navbar-nav a.nav-link');
    navLinks.forEach(function(link) {
        var href = link.getAttribute('href').split('/').pop();
        if (href === currentLocation || (currentLocation === '' && href === 'dashboard.php')) {
            link.classList.add('active');
        }
    });
});

// Format currency display
function formatCurrency(amount) {
    return new Intl.NumberFormat('vi-VN', {
        style: 'currency',
        currency: 'VND',
        minimumFractionDigits: 0,
        maximumFractionDigits: 0
    }).format(amount);
}

// Format date
function formatDate(dateStr) {
    return new Date(dateStr).toLocaleDateString('vi-VN');
}

// Show loading spinner
function showLoading(element) {
    if (element) {
        element.innerHTML = '<div class="spinner-border spinner-border-sm" role="status"><span class="visually-hidden">Loading...</span></div>';
    }
}

// Show success message
function showSuccess(message, element = null) {
    if (!element) {
        element = document.createElement('div');
        document.body.insertBefore(element, document.body.firstChild);
    }
    
    element.innerHTML = `<div class="alert alert-success alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    
    scrollToTop();
}

// Show error message
function showError(message, element = null) {
    if (!element) {
        element = document.createElement('div');
        document.body.insertBefore(element, document.body.firstChild);
    }
    
    element.innerHTML = `<div class="alert alert-danger alert-dismissible fade show" role="alert">
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>`;
    
    scrollToTop();
}

// Scroll to top
function scrollToTop() {
    window.scrollTo({
        top: 0,
        behavior: 'smooth'
    });
}

// Confirm action
function confirmAction(message = 'Bạn có chắc chắn?') {
    return confirm(message);
}

var deferredInstallPrompt = null;
window.addEventListener('beforeinstallprompt', function(event) {
    event.preventDefault();
    deferredInstallPrompt = event;
    console.log('PWA install prompt is ready.');
});

// Check if field is empty
function isEmpty(value) {
    return value === null || value === undefined || value.toString().trim() === '';
}

// Validate email
function isValidEmail(email) {
    var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Validate amount
function isValidAmount(amount) {
    return !isNaN(amount) && parseFloat(amount) > 0;
}

// Export functions for use in global scope
window.formatCurrency = formatCurrency;
window.formatDate = formatDate;
window.showLoading = showLoading;
window.showSuccess = showSuccess;
window.showError = showError;
window.scrollToTop = scrollToTop;
window.confirmAction = confirmAction;
window.isEmpty = isEmpty;
window.isValidEmail = isValidEmail;
window.isValidAmount = isValidAmount;
