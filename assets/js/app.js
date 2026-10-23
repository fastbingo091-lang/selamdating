document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-password-strength]').forEach((input) => {
        const meter = document.querySelector('[data-strength-meter]');
        const label = document.querySelector('[data-strength-label]');
        if (!meter || !label) return;

        input.addEventListener('input', () => {
            const value = input.value;
            const isValid = /^[A-Za-z0-9]{6}$/.test(value);
            meter.style.width = `${Math.min(value.length, 6) / 6 * 100}%`;
            meter.style.backgroundColor = isValid ? 'var(--success)' : 'var(--danger)';
            label.textContent = isValid
                ? 'Password meets requirements.'
                : 'Password must be exactly 6 letters or numbers.';
        });
    });

    document.querySelectorAll('[data-password-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const input = document.getElementById(button.getAttribute('aria-controls'));
            if (!input) return;

            const revealing = input.type === 'password';
            input.type = revealing ? 'text' : 'password';
            button.setAttribute('aria-label', revealing ? 'Hide password' : 'Show password');
            button.innerHTML = `<i class="bi ${revealing ? 'bi-eye-slash' : 'bi-eye'}" aria-hidden="true"></i>`;
        });
    });

    document.querySelectorAll('[data-loading-form]').forEach((form) => {
        form.addEventListener('submit', () => {
            const button = form.querySelector('[data-loading-button]');
            if (!button || button.disabled) return;

            button.disabled = true;
            button.classList.add('is-loading');
            button.setAttribute('aria-busy', 'true');
            button.dataset.originalLabel = button.textContent.trim();
            button.innerHTML = '<span class="loading-spinner" aria-hidden="true"></span> Loading...';
        });
    });

    document.querySelectorAll('[data-table-search]').forEach((input) => {
        input.addEventListener('input', () => {
            const table = document.querySelector(input.dataset.tableSearch);
            if (!table) return;

            const query = input.value.trim().toLocaleLowerCase();
            table.querySelectorAll('tbody tr[data-searchable]').forEach((row) => {
                row.hidden = query !== '' && !row.textContent.toLocaleLowerCase().includes(query);
            });
        });
    });

    document.querySelectorAll('[data-dismiss-alert]').forEach((button) => {
        button.addEventListener('click', () => button.closest('.alert')?.remove());
    });
});