<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$db = database();

$userId = (int) $_SESSION['user_id'];

$stmt = $db->prepare("
    SELECT
        vp.id,
        vp.name,
        vp.price,
        vp.profit,
        vp.product_count,
        vp.status,

        COUNT(DISTINCT vpp.product_id) AS assigned_products

    FROM vip_packages vp

    LEFT JOIN vip_package_products vpp
        ON vpp.vip_package_id = vp.id

    LEFT JOIN products p
        ON p.id = vpp.product_id
        AND p.status = 'active'
        AND p.published = 1

    WHERE vp.status = 'active'

    GROUP BY
        vp.id,
        vp.name,
        vp.price,
        vp.profit,
        vp.product_count,
        vp.status

    ORDER BY vp.id ASC
");

$stmt->execute();

$packages = $stmt->fetchAll();

$pageTitle = 'Membership';
$pageDescription = 'Explore the Selam packages currently available.';
$activeNav = 'products';

require_once __DIR__ . '/includes/app_header.php';

?>

<div class="container-fluid py-4">

    <div class="mb-4">

        <span class="eyebrow">
            Selam
        </span>

        <h1 class="h3 mb-2">
            Selam Membership
        </h1>

        <p class="text-secondary mb-0">
            Browse the packages currently available in Selam.
        </p>

    </div>


    <?php if (empty($packages)): ?>

        <div class="card border-0 shadow-sm">

            <div class="card-body text-center py-5">

                <i class="bi bi-gem fs-1 text-secondary"></i>

                <h4 class="mt-3">
                    No packages available
                </h4>

                <p class="text-secondary mb-0">
                    Please check again later.
                </p>

            </div>

        </div>

    <?php else: ?>

        <div class="vstack gap-3">

            <?php foreach ($packages as $package): ?>

                <?php

                $requiredProducts = (int) $package['product_count'];

                $assignedProducts = (int) $package['assigned_products'];

                $packageReady =
                    $requiredProducts > 0
                    && $assignedProducts >= $requiredProducts;

                ?>

                <div class="card border-0 shadow-sm">

                    <div class="card-body">

                        <div class="d-flex justify-content-between align-items-start gap-3">

                            <div>

                                <span class="badge bg-dark mb-2">

                                    <i class="bi bi-gem me-1"></i>

                                    SELAM MEMBERSHIP

                                </span>

                                <h2 class="h5 mb-1">

                                    <?= htmlspecialchars(
                                        (string) $package['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </h2>

                                <p class="text-secondary mb-0">

                                    <?= $requiredProducts ?>

                                    items included

                                </p>

                            </div>

                            <span class="badge bg-success">

                                Active

                            </span>

                        </div>


                        <div class="border-top mt-3 pt-3">

                            <div class="row g-3">

                                <div class="col-6">

                                    <small class="text-secondary d-block">
                                        Price
                                    </small>

                                    <strong>

                                        <?= number_format(
                                            (float) $package['price'],
                                            2
                                        ) ?>

                                        ETB

                                    </strong>

                                </div>


                                <div class="col-6">

                                    <small class="text-secondary d-block">
                                        Reward
                                    </small>

                                    <strong class="text-success">

                                        +

                                        <?= number_format(
                                            (float) $package['profit'],
                                            2
                                        ) ?>

                                        ETB

                                    </strong>

                                </div>


                                <div class="col-6">

                                    <small class="text-secondary d-block">
                                        Products
                                    </small>

                                    <strong>

                                        <?= $assignedProducts ?>

                                        /

                                        <?= $requiredProducts ?>

                                    </strong>

                                </div>


                                <div class="col-6">

                                    <small class="text-secondary d-block">
                                        Status
                                    </small>

                                    <?php if ($packageReady): ?>

                                        <span class="text-success fw-semibold">

                                            Ready

                                        </span>

                                    <?php else: ?>

                                        <span class="text-warning fw-semibold">

                                            Preparing

                                        </span>

                                    <?php endif; ?>

                                </div>

                            </div>

                        </div>


                        <div class="mt-3">

                            <?php if ($packageReady): ?>

                                <a
                                    href="vip/buy.php?id=<?= (int) $package['id'] ?>"
                                    class="btn selam-membership-button w-100"
                                >

                                    <i class="bi bi-cart-check me-1"></i>

                                    Buy <?= htmlspecialchars(
                                        (string) $package['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>

                                </a>

                            <?php else: ?>

                                <button
                                    type="button"
                                    class="btn btn-secondary w-100"
                                    disabled
                                >

                                    Not available yet

                                </button>

                            <?php endif; ?>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php endif; ?>

</div>

<?php

if (file_exists(__DIR__ . '/includes/app_footer.php')) {
    require_once __DIR__ . '/includes/app_footer.php';
}

?>