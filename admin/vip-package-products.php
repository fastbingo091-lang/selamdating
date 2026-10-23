<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();

if (!isset($_SESSION['admin_id'])) {
    header('Location: login.php');
    exit;
}

$db = database();

$message = '';
$error = '';

/*
|--------------------------------------------------------------------------
| GET PACKAGE
|--------------------------------------------------------------------------
*/

$packageId = filter_input(
    INPUT_GET,
    'id',
    FILTER_VALIDATE_INT
);

if (!$packageId) {
    header('Location: vip-packages.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| LOAD PACKAGE
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        id,
        name,
        price,
        profit,
        product_count,
        status
    FROM vip_packages
    WHERE id = :id
    LIMIT 1
");

$stmt->execute([
    ':id' => $packageId
]);

$package = $stmt->fetch();

if (!$package) {
    header('Location: vip-packages.php');
    exit;
}


/*
|--------------------------------------------------------------------------
| SAVE PRODUCTS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? '')) {

        $error = 'Invalid security token. Please try again.';

    } else {

        $action = $_POST['action'] ?? '';

        if ($action === 'save_products') {

            $selectedProducts = $_POST['products'] ?? [];

            if (!is_array($selectedProducts)) {
                $selectedProducts = [];
            }

            /*
            |--------------------------------------------------------------------------
            | Clean product IDs
            |--------------------------------------------------------------------------
            */

            $productIds = [];

            foreach ($selectedProducts as $productId) {

                $productId = filter_var(
                    $productId,
                    FILTER_VALIDATE_INT
                );

                if ($productId && $productId > 0) {
                    $productIds[] = $productId;
                }
            }

            $productIds = array_values(
                array_unique($productIds)
            );


            /*
            |--------------------------------------------------------------------------
            | Check product count
            |--------------------------------------------------------------------------
            */

            $requiredCount = (int) $package['product_count'];

            if (count($productIds) > $requiredCount) {

                $error =
                    'You can select a maximum of '
                    . $requiredCount
                    . ' products for this package.';

            } elseif (count($productIds) < $requiredCount) {

                $error =
                    'Please select exactly '
                    . $requiredCount
                    . ' products for this package.';

            } else {

                try {

                    $db->beginTransaction();


                    /*
                    |--------------------------------------------------------------------------
                    | Verify selected products actually exist
                    |--------------------------------------------------------------------------
                    */

                    $placeholders = implode(
                        ',',
                        array_fill(
                            0,
                            count($productIds),
                            '?'
                        )
                    );

                    $verifyStmt = $db->prepare("
                        SELECT id
                        FROM products
                        WHERE id IN ($placeholders)
                          AND status = 'active'
                    ");

                    $verifyStmt->execute($productIds);

                    $validProductIds = array_map(
                        'intval',
                        $verifyStmt->fetchAll(PDO::FETCH_COLUMN)
                    );


                    if (count($validProductIds) !== count($productIds)) {

                        throw new RuntimeException(
                            'One or more selected products are not active.'
                        );
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Remove old products
                    |--------------------------------------------------------------------------
                    */

                    $deleteStmt = $db->prepare("
                        DELETE FROM vip_package_products
                        WHERE vip_package_id = :package_id
                    ");

                    $deleteStmt->execute([
                        ':package_id' => $packageId
                    ]);


                    /*
                    |--------------------------------------------------------------------------
                    | Add new products
                    |--------------------------------------------------------------------------
                    */

                    $insertStmt = $db->prepare("
                        INSERT INTO vip_package_products
                        (
                            vip_package_id,
                            product_id,
                            quantity,
                            sort_order
                        )
                        VALUES
                        (
                            :package_id,
                            :product_id,
                            1,
                            :sort_order
                        )
                    ");

                    foreach ($productIds as $index => $productId) {

                        $insertStmt->execute([
                            ':package_id' => $packageId,
                            ':product_id' => $productId,
                            ':sort_order' => $index + 1
                        ]);
                    }


                    $db->commit();

                    $message =
                        'Products saved successfully for '
                        . $package['name']
                        . '.';

                } catch (Throwable $e) {

                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }

                    error_log(
                        'VIP package products error: '
                        . $e->getMessage()
                    );

                    $error = $e->getMessage();
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD ACTIVE PRODUCTS
|--------------------------------------------------------------------------
*/

$stmt = $db->query("
    SELECT
        id,
        name,
        description,
        price,
        profit,
        quantity,
        status,
        published
    FROM products
    WHERE status = 'active'
    ORDER BY id ASC
");

$products = $stmt->fetchAll();


/*
|--------------------------------------------------------------------------
| LOAD CURRENT PACKAGE PRODUCTS
|--------------------------------------------------------------------------
*/

$stmt = $db->prepare("
    SELECT
        product_id,
        quantity,
        sort_order
    FROM vip_package_products
    WHERE vip_package_id = :package_id
    ORDER BY sort_order ASC
");

$stmt->execute([
    ':package_id' => $packageId
]);

$currentRows = $stmt->fetchAll();


$currentProductIds = [];

foreach ($currentRows as $row) {
    $currentProductIds[] = (int) $row['product_id'];
}


$pageTitle = 'Membership Items';
$adminTitle = $package['name'] . ' Products';
$adminActive = 'vip-packages';
$adminRoot = './';

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="container-fluid py-4">

    <!-- =====================================================
         HEADER
         ===================================================== -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>

            <div class="mb-2">

                <a
                    href="vip-packages.php"
                    class="text-decoration-none"
                >
                    <i class="bi bi-arrow-left me-1"></i>
                    Back to Memberships
                </a>

            </div>

            <h1 class="h3 mb-1">

                <?= htmlspecialchars(
                    $package['name'],
                    ENT_QUOTES,
                    'UTF-8'
                ) ?>

            </h1>

            <p class="text-secondary mb-0">
                Select the items included in this membership.
            </p>

        </div>

    </div>


    <!-- =====================================================
         PACKAGE SUMMARY
         ===================================================== -->

    <div class="row g-3 mb-4">

        <div class="col-12 col-md-4">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="text-secondary small">
                        Package Price
                    </div>

                    <div class="fs-4 fw-bold">

                        <?= number_format(
                            (float) $package['price'],
                            2
                        ) ?>

                        ETB

                    </div>

                </div>

            </div>

        </div>


        <div class="col-12 col-md-4">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="text-secondary small">
                        Package Profit
                    </div>

                    <div class="fs-4 fw-bold text-success">

                        +

                        <?= number_format(
                            (float) $package['profit'],
                            2
                        ) ?>

                        ETB

                    </div>

                </div>

            </div>

        </div>


        <div class="col-12 col-md-4">

            <div class="card border-0 shadow-sm h-100">

                <div class="card-body">

                    <div class="text-secondary small">
                        Required Products
                    </div>

                    <div class="fs-4 fw-bold">

                        <?= (int) $package['product_count'] ?>

                    </div>

                </div>

            </div>

        </div>

    </div>


    <!-- =====================================================
         MESSAGES
         ===================================================== -->

    <?php if ($message !== ''): ?>

        <div class="alert alert-success alert-dismissible fade show">

            <i class="bi bi-check-circle me-2"></i>

            <?= htmlspecialchars(
                $message,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <?php if ($error !== ''): ?>

        <div class="alert alert-danger alert-dismissible fade show">

            <i class="bi bi-exclamation-circle me-2"></i>

            <?= htmlspecialchars(
                $error,
                ENT_QUOTES,
                'UTF-8'
            ) ?>

            <button
                type="button"
                class="btn-close"
                data-bs-dismiss="alert"
            ></button>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         PRODUCT SELECTION
         ===================================================== -->

    <form method="POST">

        <input
            type="hidden"
            name="csrf_token"
            value="<?= htmlspecialchars(
                csrf_token(),
                ENT_QUOTES,
                'UTF-8'
            ) ?>"
        >

        <input
            type="hidden"
            name="action"
            value="save_products"
        >


        <div class="card border-0 shadow-sm">

            <div class="card-header bg-transparent py-3">

                <div class="d-flex justify-content-between align-items-center">

                    <div>

                        <h5 class="mb-1">
                            Products
                        </h5>

                        <small class="text-secondary">
                            Select exactly
                            <?= (int) $package['product_count'] ?>
                            products.
                        </small>

                    </div>

                    <span
                        id="selectedCount"
                        class="badge text-bg-primary"
                    >
                        0 selected
                    </span>

                </div>

            </div>


            <div class="card-body">

                <?php if (!$products): ?>

                    <div class="text-center py-5">

                        <i class="bi bi-box-seam fs-1 text-secondary"></i>

                        <h5 class="mt-3">
                            No active products
                        </h5>

                        <p class="text-secondary">
                            Create and activate products first.
                        </p>

                        <a
                            href="products.php"
                            class="btn btn-success"
                        >
                            Go to Products
                        </a>

                    </div>

                <?php else: ?>

                    <div class="row g-3">

                        <?php foreach ($products as $product): ?>

                            <?php
                            $productId = (int) $product['id'];
                            $checked = in_array(
                                $productId,
                                $currentProductIds,
                                true
                            );
                            ?>

                            <div class="col-12 col-md-6 col-xl-4">

                                <label
                                    class="card h-100 border product-option"
                                    style="cursor:pointer;"
                                >

                                    <div class="card-body">

                                        <div class="d-flex gap-3">

                                            <div>

                                                <input
                                                    type="checkbox"
                                                    class="form-check-input product-checkbox"
                                                    name="products[]"
                                                    value="<?= $productId ?>"
                                                    <?= $checked ? 'checked' : '' ?>
                                                >

                                            </div>


                                            <div class="flex-grow-1">

                                                <div class="fw-semibold mb-1">

                                                    <?= htmlspecialchars(
                                                        $product['name'],
                                                        ENT_QUOTES,
                                                        'UTF-8'
                                                    ) ?>

                                                </div>


                                                <?php if (!empty($product['description'])): ?>

                                                    <div class="small text-secondary mb-2">

                                                        <?= htmlspecialchars(
                                                            mb_strimwidth(
                                                                $product['description'],
                                                                0,
                                                                100,
                                                                '...'
                                                            ),
                                                            ENT_QUOTES,
                                                            'UTF-8'
                                                        ) ?>

                                                    </div>

                                                <?php endif; ?>


                                                <div class="d-flex justify-content-between">

                                                    <span class="small">

                                                        <?= number_format(
                                                            (float) $product['price'],
                                                            2
                                                        ) ?>

                                                        ETB

                                                    </span>


                                                    <span class="small text-success">

                                                        +

                                                        <?= number_format(
                                                            (float) $product['profit'],
                                                            2
                                                        ) ?>

                                                        ETB

                                                    </span>

                                                </div>

                                            </div>

                                        </div>

                                    </div>

                                </label>

                            </div>

                        <?php endforeach; ?>

                    </div>


                    <!-- SAVE -->

                    <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top">

                        <div class="text-secondary">

                            Selected:

                            <strong id="selectedCountBottom">
                                0
                            </strong>

                            /

                            <?= (int) $package['product_count'] ?>

                        </div>


                        <button
                            type="submit"
                            class="btn btn-success"
                            id="saveProductsButton"
                        >

                            <i class="bi bi-check-lg me-1"></i>

                            Save Products

                        </button>

                    </div>

                <?php endif; ?>

            </div>

        </div>

    </form>

</div>


<style>

.product-option {
    transition: transform 0.15s ease, border-color 0.15s ease;
}

.product-option:hover {
    transform: translateY(-2px);
}

.product-option:has(.product-checkbox:checked) {
    border-color: #22C55E !important;
}

</style>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const checkboxes = document.querySelectorAll('.product-checkbox');

    const selectedCount =
        document.getElementById('selectedCount');

    const selectedCountBottom =
        document.getElementById('selectedCountBottom');

    const saveButton =
        document.getElementById('saveProductsButton');

    const requiredCount =
        <?= (int) $package['product_count'] ?>;


    function updateCount() {

        const selected =
            document.querySelectorAll(
                '.product-checkbox:checked'
            ).length;


        if (selectedCount) {

            selectedCount.textContent =
                selected + ' selected';

        }


        if (selectedCountBottom) {

            selectedCountBottom.textContent =
                selected;

        }


        if (saveButton) {

            saveButton.disabled =
                selected !== requiredCount;

        }


        if (selected === requiredCount) {

            selectedCount.className =
                'badge text-bg-success';

        } else {

            selectedCount.className =
                'badge text-bg-primary';

        }

    }


    checkboxes.forEach(function (checkbox) {

        checkbox.addEventListener(
            'change',
            function () {

                const selected =
                    document.querySelectorAll(
                        '.product-checkbox:checked'
                    ).length;


                if (
                    selected > requiredCount &&
                    this.checked
                ) {

                    this.checked = false;

                    alert(
                        'This package allows only '
                        + requiredCount
                        + ' products.'
                    );

                }

                updateCount();

            }
        );

    });


    updateCount();

});

</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>