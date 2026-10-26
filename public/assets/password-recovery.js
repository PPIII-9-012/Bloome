(() => {
    const email = document.getElementById('recovery-email');
    if (email) {
        const button = document.getElementById('recovery-send-button');
        const status = document.getElementById('recovery-email-status');
        const updateEmailState = () => {
            const valid = email.value.trim() !== '' && email.validity.valid;
            button.disabled = !valid;
            status.textContent = valid
                ? 'Formato de correo válido.'
                : (email.value ? 'Ingresá una dirección de correo válida.' : 'Ingresá un correo válido para habilitar el botón.');
            status.classList.toggle('is-valid', valid);
            status.classList.toggle('is-invalid', email.value !== '' && !valid);
        };
        email.addEventListener('input', updateEmailState);
        email.addEventListener('change', updateEmailState);
        updateEmailState();
    }

    const countdown = document.getElementById('recovery-countdown');
    if (countdown) {
        const button = document.getElementById('recovery-resend-button');
        const until = Number(countdown.dataset.until || 0) * 1000;
        const updateCountdown = () => {
            const remaining = Math.max(0, Math.ceil((until - Date.now()) / 1000));
            const timeText = `${String(Math.floor(remaining / 60)).padStart(2, '0')}:${String(remaining % 60).padStart(2, '0')}`;
            button.disabled = remaining > 0;
            button.textContent = remaining > 0 ? `Podés solicitar otro código en ${timeText}` : 'Solicitar otro código';
            if (remaining > 0) window.setTimeout(updateCountdown, 250);
        };
        updateCountdown();
    }

    const code = document.getElementById('recovery-code');
    if (code) code.addEventListener('input', () => { code.value = code.value.replace(/\D/g, '').slice(0, 6); });

    const password = document.getElementById('recovery-password');
    const confirm = document.getElementById('recovery-password-confirm');
    if (password && confirm) {
        const status = document.getElementById('recovery-password-match');
        const checkMatch = () => {
            if (!confirm.value) status.textContent = '';
            else status.textContent = password.value === confirm.value ? 'Las contraseñas coinciden.' : 'Las contraseñas no coinciden.';
            status.classList.toggle('is-valid', !!confirm.value && password.value === confirm.value);
            status.classList.toggle('is-invalid', !!confirm.value && password.value !== confirm.value);
        };
        password.addEventListener('input', checkMatch);
        confirm.addEventListener('input', checkMatch);
    }
})();
