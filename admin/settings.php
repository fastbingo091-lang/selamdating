<?php
declare(strict_types=1);
$adminTitle = 'Settings';
$adminActive = 'settings';
require __DIR__ . '/../includes/admin_header.php';
?>
<section class="surface"><div class="section-heading"><h2>Platform settings</h2><span class="status-badge warning">Read-only</span></div><div class="d-flex justify-content-between align-items-center gap-3 py-3 border-bottom" style="border-color:var(--border)!important"><span><strong>Wallet currency</strong><span class="small-note d-block">Internal wallet and ledger currency</span></span><span>ETB</span></div><div class="d-flex justify-content-between align-items-center gap-3 py-3"><span><strong>External payments</strong><span class="small-note d-block">Agent verified; payouts are not automated</span></span><span class="status-badge">Manual processing</span></div><p class="small-note mb-0">Payment provider settings cannot be changed from this page.</p></section>
<?php require __DIR__ . '/../includes/admin_footer.php'; ?>