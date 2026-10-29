<?php
require_once __DIR__ . '/security.php';

$brandAppRoot = isset($appRoot) && is_string($appRoot) ? $appRoot : '';
$brandHref = isset($brandHref) && is_string($brandHref) ? $brandHref : $brandAppRoot . './';
$brandLabel = 'Selam Market';
?>
<a class="brand" href="<?= html_escape($brandHref) ?>" aria-label="<?= html_escape($brandLabel) ?> home">
    <span class="brand-mark" aria-hidden="true"><i class="bi bi-bag-fill"></i></span>
    <span class="brand-copy"><span class="brand-name">Selam</span><span class="brand-subtitle">Market</span></span>
</a>