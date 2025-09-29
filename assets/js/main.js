// JavaScript สำหรับระบบจัดการห้องเช่า

document.addEventListener('DOMContentLoaded', function() {
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(function(alert) {
        setTimeout(function() {
            alert.style.transition = 'opacity 0.5s';
            alert.style.opacity = '0';
            setTimeout(function() {
                alert.remove();
            }, 500);
        }, 5000);
    });

    // Form validation helpers
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;

            requiredFields.forEach(function(field) {
                if (!field.value.trim()) {
                    field.style.borderColor = '#dc3545';
                    isValid = false;
                } else {
                    field.style.borderColor = '#ddd';
                }
            });

            if (!isValid) {
                e.preventDefault();
                showAlert('กรุณากรอกข้อมูลที่จำเป็นให้ครบถ้วน', 'error');
            }
        });
    });

    // Phone number formatting
    const phoneInputs = document.querySelectorAll('input[type="tel"], input[name*="phone"]');
    phoneInputs.forEach(function(input) {
        input.addEventListener('input', function(e) {
            // Remove non-digits
            let value = e.target.value.replace(/\D/g, '');

            // Limit to 10 digits for Thai phone numbers
            if (value.length > 10) {
                value = value.substring(0, 10);
            }

            // Format as XXX-XXX-XXXX
            if (value.length >= 6) {
                value = value.substring(0, 3) + '-' + value.substring(3, 6) + '-' + value.substring(6);
            } else if (value.length >= 3) {
                value = value.substring(0, 3) + '-' + value.substring(3);
            }

            e.target.value = value;
        });
    });

    // Date input defaults
    const dateInputs = document.querySelectorAll('input[type="date"]');
    dateInputs.forEach(function(input) {
        if (!input.value && input.name === 'start_date') {
            input.value = new Date().toISOString().split('T')[0];
        }
        if (!input.value && input.name === 'end_date') {
            const nextYear = new Date();
            nextYear.setFullYear(nextYear.getFullYear() + 1);
            input.value = nextYear.toISOString().split('T')[0];
        }
    });

    // Confirm delete actions
    const deleteButtons = document.querySelectorAll('[onclick*="delete"]');
    deleteButtons.forEach(function(button) {
        button.addEventListener('click', function(e) {
            if (!confirm('คุณแน่ใจหรือไม่ที่จะลบข้อมูลนี้?')) {
                e.preventDefault();
                e.stopPropagation();
                return false;
            }
        });
    });

    // Table row highlighting
    const tableRows = document.querySelectorAll('tbody tr');
    tableRows.forEach(function(row) {
        row.addEventListener('mouseenter', function() {
            this.style.backgroundColor = '#f8f9fa';
        });
        row.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });

    // Room status colors
    updateRoomColors();

    // Auto-refresh dashboard every 5 minutes
    if (window.location.pathname.includes('dashboard.php')) {
        setInterval(function() {
            // Only refresh if user is still active (not focused elsewhere for too long)
            if (document.hasFocus() || (Date.now() - lastActivity) < 300000) { // 5 minutes
                location.reload();
            }
        }, 300000); // 5 minutes
    }
});

// Track user activity
let lastActivity = Date.now();
document.addEventListener('click', function() {
    lastActivity = Date.now();
});
document.addEventListener('keypress', function() {
    lastActivity = Date.now();
});

// Utility functions
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.textContent = message;

    const container = document.querySelector('.container');
    if (container) {
        container.insertBefore(alertDiv, container.firstChild);

        setTimeout(function() {
            alertDiv.style.transition = 'opacity 0.5s';
            alertDiv.style.opacity = '0';
            setTimeout(function() {
                alertDiv.remove();
            }, 500);
        }, 3000);
    }
}

function updateRoomColors() {
    const roomItems = document.querySelectorAll('.room-item');
    roomItems.forEach(function(room) {
        const status = room.className.match(/room-(\w+)/);
        if (status) {
            switch(status[1]) {
                case 'available':
                    room.style.backgroundColor = '#d4edda';
                    room.style.color = '#155724';
                    break;
                case 'occupied':
                    room.style.backgroundColor = '#f8d7da';
                    room.style.color = '#721c24';
                    break;
                case 'maintenance':
                    room.style.backgroundColor = '#fff3cd';
                    room.style.color = '#856404';
                    break;
            }
        }
    });
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('th-TH', {
        style: 'currency',
        currency: 'THB'
    }).format(amount);
}

function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('th-TH', {
        year: 'numeric',
        month: '2-digit',
        day: '2-digit'
    });
}

// Modal utilities
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // ESC to close modals
    if (e.key === 'Escape') {
        const modals = document.querySelectorAll('[id*="Modal"]');
        modals.forEach(function(modal) {
            if (modal.style.display === 'block') {
                modal.style.display = 'none';
            }
        });
    }

    // Ctrl+S to save forms
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        const visibleForms = document.querySelectorAll('form:not([style*="display: none"])');
        if (visibleForms.length > 0) {
            visibleForms[0].submit();
        }
    }
});

// Print optimization
window.addEventListener('beforeprint', function() {
    // Hide unnecessary elements
    const elementsToHide = document.querySelectorAll('.btn, nav, .no-print');
    elementsToHide.forEach(function(element) {
        element.style.display = 'none';
    });
});

window.addEventListener('afterprint', function() {
    // Show elements again
    const elementsToShow = document.querySelectorAll('.btn, nav, .no-print');
    elementsToShow.forEach(function(element) {
        element.style.display = '';
    });
});

// Export utilities
function exportTableToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    if (!table) return;

    let csv = '';
    const rows = table.querySelectorAll('tr');

    rows.forEach(function(row) {
        const cols = row.querySelectorAll('td, th');
        const rowData = [];

        cols.forEach(function(col) {
            let cellData = col.textContent.trim();
            // Escape quotes and wrap in quotes if necessary
            if (cellData.includes(',') || cellData.includes('"') || cellData.includes('\n')) {
                cellData = '"' + cellData.replace(/"/g, '""') + '"';
            }
            rowData.push(cellData);
        });

        csv += rowData.join(',') + '\n';
    });

    // Download CSV
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    const url = URL.createObjectURL(blob);
    link.setAttribute('href', url);
    link.setAttribute('download', filename + '.csv');
    link.style.visibility = 'hidden';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

// Form auto-save (for draft functionality)
function initAutoSave() {
    const forms = document.querySelectorAll('form[data-autosave]');

    forms.forEach(function(form) {
        const formId = form.getAttribute('data-autosave');
        const inputs = form.querySelectorAll('input, textarea, select');

        // Load saved data
        inputs.forEach(function(input) {
            const savedValue = localStorage.getItem(`autosave_${formId}_${input.name}`);
            if (savedValue && !input.value) {
                input.value = savedValue;
            }
        });

        // Save data on input
        inputs.forEach(function(input) {
            input.addEventListener('input', function() {
                localStorage.setItem(`autosave_${formId}_${this.name}`, this.value);
            });
        });

        // Clear saved data on successful submission
        form.addEventListener('submit', function() {
            inputs.forEach(function(input) {
                localStorage.removeItem(`autosave_${formId}_${input.name}`);
            });
        });
    });
}

// Initialize auto-save if forms have the attribute
document.addEventListener('DOMContentLoaded', initAutoSave);

console.log('🏠 ระบบจัดการห้องเช่า - JavaScript loaded successfully');