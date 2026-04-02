document.addEventListener('DOMContentLoaded', function () {

    // ── Toggle password visibility ──────────────────────────
    document.querySelectorAll('.toggle-password').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var targetId = this.getAttribute('data-target');
            var input    = document.getElementById(targetId);
            var icon     = this.querySelector('i');

            if (!input) return;

            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.replace('bi-eye', 'bi-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.replace('bi-eye-slash', 'bi-eye');
            }
        });
    });

    // ── Register form: client-side password match check ─────
    var registerForm = document.getElementById('registerForm');
    if (registerForm) {
        registerForm.addEventListener('submit', function (e) {
            var pw  = document.getElementById('password');
            var cpw = document.getElementById('confirm_password');

            if (pw && cpw && pw.value !== cpw.value) {
                e.preventDefault();
                cpw.setCustomValidity('Passwords do not match.');
                cpw.reportValidity();
            } else if (cpw) {
                cpw.setCustomValidity('');
            }
        });
    }

});
