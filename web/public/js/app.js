/**
 * NewsBot Writer Admin JavaScript
 */

document.addEventListener('DOMContentLoaded', function() {
    // Auto-dismiss flash alerts after 5 seconds
    document.querySelectorAll('.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            if (bsAlert) {
                bsAlert.close();
            }
        }, 5000);
    });

    // Confirm dialog for delete/destructive actions
    document.querySelectorAll('form[data-confirm]').forEach(form => {
        form.addEventListener('submit', function(e) {
            const message = this.dataset.confirm || 'Are you sure you want to proceed?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });

    // Confirm buttons
    document.querySelectorAll('[data-confirm]').forEach(el => {
        if (el.tagName !== 'FORM') {
            el.addEventListener('click', function(e) {
                const message = this.dataset.confirm || 'Are you sure?';
                if (!confirm(message)) {
                    e.preventDefault();
                }
            });
        }
    });

    // Initialize Bootstrap tooltips
    const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    tooltipTriggerList.forEach(el => new bootstrap.Tooltip(el));

    // Initialize Bootstrap popovers
    const popoverTriggerList = document.querySelectorAll('[data-bs-toggle="popover"]');
    popoverTriggerList.forEach(el => new bootstrap.Popover(el));

    // Select all checkbox handler
    const selectAllCheckbox = document.getElementById('select-all');
    if (selectAllCheckbox) {
        selectAllCheckbox.addEventListener('change', function() {
            const checkboxes = document.querySelectorAll('input[name="selected[]"]');
            checkboxes.forEach(cb => cb.checked = this.checked);
            updateBulkActions();
        });

        // Individual checkbox change
        document.querySelectorAll('input[name="selected[]"]').forEach(cb => {
            cb.addEventListener('change', updateBulkActions);
        });
    }

    // Handle bulk action form
    const bulkForm = document.getElementById('bulk-form');
    if (bulkForm) {
        bulkForm.addEventListener('submit', function(e) {
            const selected = document.querySelectorAll('input[name="selected[]"]:checked');
            if (selected.length === 0) {
                e.preventDefault();
                alert('Please select at least one item.');
                return;
            }

            const action = document.getElementById('bulk-action').value;
            if (!action) {
                e.preventDefault();
                alert('Please select an action.');
                return;
            }

            if (!confirm(`Are you sure you want to perform "${action}" on ${selected.length} item(s)?`)) {
                e.preventDefault();
            }
        });
    }
});

/**
 * Update bulk action button state
 */
function updateBulkActions() {
    const selected = document.querySelectorAll('input[name="selected[]"]:checked');
    const bulkBtn = document.getElementById('bulk-submit');
    if (bulkBtn) {
        bulkBtn.disabled = selected.length === 0;
        const countSpan = bulkBtn.querySelector('.count');
        if (countSpan) {
            countSpan.textContent = selected.length > 0 ? `(${selected.length})` : '';
        }
    }
}

/**
 * Escape HTML special characters
 * @param {string} text
 * @returns {string}
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text || '';
    return div.innerHTML;
}

/**
 * Format date for display
 * @param {string} dateStr
 * @returns {string}
 */
function formatDate(dateStr) {
    if (!dateStr) return '-';
    const date = new Date(dateStr);
    return date.toLocaleDateString() + ' ' + date.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'});
}

/**
 * Show loading spinner on button
 * @param {HTMLElement} button
 */
function showButtonLoading(button) {
    button.disabled = true;
    button.dataset.originalText = button.innerHTML;
    button.innerHTML = '<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Loading...';
}

/**
 * Hide loading spinner on button
 * @param {HTMLElement} button
 */
function hideButtonLoading(button) {
    button.disabled = false;
    if (button.dataset.originalText) {
        button.innerHTML = button.dataset.originalText;
    }
}

/**
 * Copy text to clipboard
 * @param {string} text
 */
function copyToClipboard(text) {
    navigator.clipboard.writeText(text).then(() => {
        // Show temporary success message
        const toast = document.createElement('div');
        toast.className = 'position-fixed bottom-0 end-0 p-3';
        toast.style.zIndex = '1100';
        toast.innerHTML = `
            <div class="toast show" role="alert">
                <div class="toast-body">
                    <i class="bi bi-check-circle text-success"></i> Copied to clipboard
                </div>
            </div>
        `;
        document.body.appendChild(toast);
        setTimeout(() => toast.remove(), 2000);
    }).catch(err => {
        console.error('Failed to copy:', err);
        alert('Failed to copy to clipboard');
    });
}
