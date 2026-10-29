(() => {
    const panel = document.getElementById('wallet-request-panel');
    const closeButton = document.getElementById('wallet-request-panel-close');
    const loading = document.getElementById('wallet-request-panel-loading');
    const message = document.getElementById('wallet-request-panel-message');
    const details = document.getElementById('wallet-request-panel-details');
    if (!panel || !closeButton || !loading || !message || !details) {
        return;
    }

    const statusLabels = {
        pending: 'Awaiting admin review',
        verified: 'Approved and credited',
        completed: 'Paid',
        rejected: 'Rejected',
    };

    const addDetail = (label, value) => {
        if (typeof value !== 'string' || value === '') {
            return;
        }
        const row = document.createElement('div');
        row.className = 'wallet-request-detail-row';
        const name = document.createElement('span');
        name.textContent = label;
        const content = document.createElement('strong');
        content.textContent = value;
        row.append(name, content);
        details.append(row);
    };

    const displayRequest = (request) => {
        const status = String(request.status || '').toLowerCase();
        const kindLabel = request.kind === 'deposit' ? 'Deposit' : 'Withdrawal';
        const statusRow = document.createElement('div');
        statusRow.className = 'wallet-request-status';
        const icon = document.createElement('i');
        icon.className = 'bi bi-arrow-repeat';
        icon.setAttribute('aria-hidden', 'true');
        const label = document.createElement('span');
        label.textContent = statusLabels[status] || status;
        statusRow.append(icon, label);
        details.append(statusRow);

        addDetail('Request', `#${request.id}`);
        addDetail('Type', kindLabel);
        addDetail(
            'Amount',
            `${Number(request.amount).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })} ${request.currency}`
        );
        addDetail('Payment method', request.provider);
        addDetail('Submitted', request.created_at);
        addDetail('Reviewed', request.verified_at || request.reviewed_at || '');
        addDetail('Reference', request.transaction_reference || request.payout_reference || '');
        addDetail('Account name', request.destination_name || '');
        addDetail('Destination', request.destination_account || '');
        addDetail('Review note', request.rejection_reason || '');

        if (status === 'pending') {
            message.textContent = 'Your request is waiting for admin review. Reopen details to check for a status update.';
            statusRow.classList.add('is-pending');
            icon.classList.add('wallet-request-spin');
        } else {
            statusRow.classList.add(status === 'rejected' ? 'is-rejected' : 'is-complete');
            message.textContent = status === 'rejected'
                ? 'The request was not approved. Review the note above or contact support.'
                : 'The request has been processed.';
        }
    };

    document.addEventListener('click', async (event) => {
        const target = event.target;
        const button = target instanceof Element
            ? target.closest('[data-wallet-request-kind][data-wallet-request-id]')
            : null;
        if (!button) {
            return;
        }

        panel.hidden = false;
        panel.classList.add('is-loading');
        loading.hidden = false;
        message.textContent = '';
        details.replaceChildren();

        const query = new URLSearchParams({
            kind: button.dataset.walletRequestKind,
            id: button.dataset.walletRequestId,
        });
        const originalContent = Array.from(button.childNodes, (node) => node.cloneNode(true));
        const spinner = document.createElement('span');
        spinner.className = 'loading-spinner';
        spinner.setAttribute('aria-hidden', 'true');
        button.disabled = true;
        button.setAttribute('aria-busy', 'true');
        button.replaceChildren(spinner, document.createTextNode(' Loading'));

        try {
            const response = await fetch(`${panel.dataset.endpoint}?${query}`, {
                headers: { Accept: 'application/json' },
                credentials: 'same-origin',
            });
            const result = await response.json();
            if (!response.ok || !result.success || !result.request) {
                throw new Error(result.message || 'Unable to load request details.');
            }
            displayRequest(result.request);
        } catch (error) {
            const errorMessage = error instanceof Error ? error.message : 'Unable to load request details.';
            message.textContent = errorMessage;
            window.SelamToast?.show(errorMessage, 'error');
        } finally {
            loading.hidden = true;
            panel.classList.remove('is-loading');
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.replaceChildren(...originalContent);
        }
    });

    closeButton.addEventListener('click', () => {
        panel.hidden = true;
    });
})();
