const initializeApp = () => {
    const toastRegion = document.createElement('div');
    toastRegion.className = 'global-toast-region';
    toastRegion.setAttribute('aria-live', 'polite');
    toastRegion.setAttribute('aria-relevant', 'additions');
    toastRegion.setAttribute('aria-label', 'App notifications');
    document.body.append(toastRegion);

    const showToast = (message, type = 'success') => {
        const content = typeof message === 'string' ? message.trim() : '';
        if (content === '') return;

        const isError = type === 'error' || type === 'danger' || type === 'failed';
        const toast = document.createElement('div');
        toast.className = `global-toast ${isError ? 'is-error' : 'is-success'}`;
        toast.setAttribute('role', isError ? 'alert' : 'status');

        const icon = document.createElement('span');
        icon.className = 'global-toast-icon';
        icon.setAttribute('aria-hidden', 'true');
        icon.innerHTML = `<i class="bi ${isError ? 'bi-x-lg' : 'bi-check-lg'}"></i>`;

        const text = document.createElement('span');
        text.className = 'global-toast-message';
        text.textContent = content;

        const close = document.createElement('button');
        close.className = 'global-toast-close';
        close.type = 'button';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.innerHTML = '<i class="bi bi-x" aria-hidden="true"></i>';
        close.addEventListener('click', () => toast.remove());

        toast.append(icon, text, close);
        toastRegion.append(toast);
        window.setTimeout(() => toast.remove(), 6000);
    };

    window.SelamToast = { show: showToast };

    const showAlertToast = (alert) => {
        if (!(alert instanceof HTMLElement) || alert.dataset.globalToastShown === 'true') return;
        if (alert.classList.contains('d-none') || alert.hidden) return;

        const type = alert.classList.contains('alert-danger') ? 'error'
            : alert.classList.contains('alert-success') ? 'success'
                : null;
        if (!type) return;

        const message = alert.innerText.trim();
        if (message === '') return;

        alert.dataset.globalToastShown = 'true';
        showToast(message, type);
    };

    document.querySelectorAll('.alert-success, .alert-danger').forEach(showAlertToast);

    const alertObserver = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type === 'attributes' && mutation.target instanceof HTMLElement) {
                showAlertToast(mutation.target);
                return;
            }

            mutation.addedNodes.forEach((node) => {
                if (!(node instanceof HTMLElement)) return;
                showAlertToast(node);
                node.querySelectorAll('.alert-success, .alert-danger').forEach(showAlertToast);
                showAlertToast(node.closest('.alert-success, .alert-danger'));
            });

            if (mutation.target instanceof HTMLElement) {
                showAlertToast(mutation.target.closest('.alert-success, .alert-danger'));
            }
        });
    });
    alertObserver.observe(document.body, {
        attributes: true,
        attributeFilter: ['class', 'hidden'],
        childList: true,
        characterData: true,
        subtree: true,
    });

    const navigationSpinner = document.createElement('div');
    navigationSpinner.className = 'app-navigation-spinner';
    navigationSpinner.setAttribute('role', 'status');
    navigationSpinner.setAttribute('aria-label', 'Loading page');
    navigationSpinner.setAttribute('aria-live', 'polite');
    navigationSpinner.innerHTML = '<span class="loading-spinner" aria-hidden="true"></span>';
    document.body.append(navigationSpinner);
    let spinnerTimer = 0;
    const scheduleSpinner = () => {
        window.clearTimeout(spinnerTimer);
        navigationSpinner.classList.remove('is-visible');
        spinnerTimer = window.setTimeout(() => navigationSpinner.classList.add('is-visible'), 2000);
    };
    const hideSpinner = () => {
        window.clearTimeout(spinnerTimer);
        navigationSpinner.classList.remove('is-visible');
    };
    window.addEventListener('pageshow', hideSpinner);

    document.addEventListener('click', (event) => {
        if (event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
        const target = event.target;
        const link = target instanceof Element ? target.closest('a[href]') : null;
        if (!link || link.hasAttribute('download') || (link.target && link.target !== '_self')) return;

        let destination;
        try {
            destination = new URL(link.href, window.location.href);
        } catch {
            return;
        }
        if (destination.origin !== window.location.origin) return;
        if (destination.href === window.location.href || (destination.pathname === window.location.pathname && destination.search === window.location.search && destination.hash !== '')) return;

        scheduleSpinner();
    });

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

    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement) || event.defaultPrevented || form.hasAttribute('data-no-loading')) return;
        if (form.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }

        const submitter = event.submitter;
        const button = submitter instanceof HTMLButtonElement
            ? submitter
            : form.querySelector('button[type="submit"]:not(:disabled):not([aria-disabled="true"]), button:not([type]):not(:disabled):not([aria-disabled="true"]), input[type="submit"]:not(:disabled)');
        if (!button || button.matches(':disabled, [aria-disabled="true"]')) return;

        form.dataset.submitting = 'true';
        button.setAttribute('aria-busy', 'true');
        button.classList.add('is-loading');
        scheduleSpinner();
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
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initializeApp, { once: true });
} else {
    initializeApp();
}