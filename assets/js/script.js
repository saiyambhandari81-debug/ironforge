// Confirm before important actions
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('form[data-confirm]').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });
});

// Membership expiry preview
function formatLocalDate(date) {
    const y = date.getFullYear();
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const d = String(date.getDate()).padStart(2, '0');
    return `${y}-${m}-${d}`;
}

function updateExpiryPreview() {
    const select = document.getElementById('planSelect');
    const startInput = document.getElementById('startDate');
    const preview = document.getElementById('expiryPreview');
    if (!select || !startInput || !preview) return;

    const option = select.options[select.selectedIndex];
    const duration = option ? parseInt(option.dataset.duration, 10) : NaN;

    if (!duration || !startInput.value) {
        preview.textContent = '';
        return;
    }

    const start = new Date(startInput.value + 'T00:00:00');
    start.setDate(start.getDate() + duration);
    preview.textContent = 'Expires: ' + formatLocalDate(start);
}

document.addEventListener('DOMContentLoaded', function () {
    const select = document.getElementById('planSelect');
    const startInput = document.getElementById('startDate');
    if (select) {
        select.addEventListener('change', updateExpiryPreview);
        updateExpiryPreview();
    }
    if (startInput) {
        startInput.addEventListener('change', updateExpiryPreview);
    }
});
// Payment form: auto-fill amount with full due balance
document.addEventListener('DOMContentLoaded', function () {
    const membershipSelect = document.getElementById('membershipSelect');
    const amountInput = document.getElementById('amountInput');
    if (membershipSelect && amountInput) {
        membershipSelect.addEventListener('change', function () {
            const option = membershipSelect.options[membershipSelect.selectedIndex];
            if (option && option.dataset.due) {
                amountInput.value = option.dataset.due;
            }
        });
    }
});