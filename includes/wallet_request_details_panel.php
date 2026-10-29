<aside
    class="wallet-request-panel"
    id="wallet-request-panel"
    aria-labelledby="wallet-request-panel-title"
    aria-live="polite"
    data-endpoint="<?= html_escape($appRoot . 'api/wallet-request-details.php') ?>"
    hidden
>
    <div class="wallet-request-panel-header">
        <div>
            <span class="eyebrow">REQUEST TRACKING</span>
            <h2 id="wallet-request-panel-title">Request details</h2>
        </div>
        <button class="btn-close" type="button" id="wallet-request-panel-close" aria-label="Close details"></button>
    </div>
    <div class="wallet-request-panel-content">
        <div class="wallet-request-loading" id="wallet-request-panel-loading" hidden>
            <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
            <span>Loading request details…</span>
        </div>
        <p class="mb-0" id="wallet-request-panel-message"></p>
        <div id="wallet-request-panel-details"></div>
    </div>
</aside>
<script src="<?= html_escape($appRoot . 'assets/js/wallet-request-tracking.js?v=2') ?>" defer></script>
