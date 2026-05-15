import './bootstrap';

window.showToast = function showToast(message) {
    Swal.mixin({
        toast: true,
        position: 'top-end',
        showConfirmButton: false,
        timer: 3000,
        timerProgressBar: true,
    }).fire({
        icon: 'success',
        title: message
    });
}

window.addEventListener("pageshow", function (event) {
    if (event.persisted || (window.performance && window.performance.navigation.type === 2)) {
        window.location.reload();
    }
});

// Themed SweetAlert Confirmation for Delete/Archive actions
window.confirmAction = window.ConfirmAction = function confirmAction(button, message, color = '#e22161') { 
    Swal.fire({
        title: 'Are you sure?',
        text: message,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: color,
        cancelButtonColor: '#94a3b8',
        confirmButtonText: 'Yes, proceed',
        customClass: { popup: 'rounded-2xl shadow-2xl' }
    }).then((result) => {
        if (result.isConfirmed) {
            let form = button.nextElementSibling;
            while (form && form.tagName !== 'FORM') form = form.nextElementSibling;
            if (form) form.submit();
        }
    });
}
