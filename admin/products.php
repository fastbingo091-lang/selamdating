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
| Handle product actions
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? '')) {

        $error = 'Invalid security token. Please try again.';

    } else {

        $action = $_POST['action'] ?? '';

        /*
        |--------------------------------------------------------------------------
        | Create product
        |--------------------------------------------------------------------------
        */

        if ($action === 'create') {

            $name = trim($_POST['name'] ?? '');
            $description = trim($_POST['description'] ?? '');

            $price = filter_var(
                $_POST['price'] ?? null,
                FILTER_VALIDATE_FLOAT
            );

            $profit = filter_var(
                $_POST['profit'] ?? null,
                FILTER_VALIDATE_FLOAT
            );

            $quantity = filter_var(
                $_POST['quantity'] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($name === '') {

                $error = 'Product name is required.';

            } elseif ($price === false || $price <= 0) {

                $error = 'Enter a valid product price.';

            } elseif ($profit === false || $profit < 0) {

                $error = 'Enter a valid product profit.';

            } elseif ($quantity === false || $quantity < 1) {

                $error = 'Quantity must be at least 1.';

            } else {

                try {

                    $stmt = $db->prepare("
                        INSERT INTO products
                        (
                            name,
                            description,
                            price,
                            profit,
                            quantity,
                            status,
                            published
                        )
                        VALUES
                        (
                            :name,
                            :description,
                            :price,
                            :profit,
                            :quantity,
                            'active',
                            0
                        )
                    ");

                    $stmt->execute([
                        ':name' => $name,
                        ':description' => $description !== ''
                            ? $description
                            : null,
                        ':price' => $price,
                        ':profit' => $profit,
                        ':quantity' => $quantity
                    ]);

                    $message = 'Product created successfully.';

                } catch (Throwable $e) {

                    error_log(
                        'Admin product create error: ' . $e->getMessage()
                    );

                    $error = 'Unable to create the product.';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Publish product
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'publish') {

            $productId = filter_var(
                $_POST['product_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$productId) {

                $error = 'Invalid product.';

            } else {

                try {

                    $stmt = $db->prepare("
                        UPDATE products
                        SET
                            published = 1,
                            published_at = NOW(),
                            status = 'active'
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':id' => $productId
                    ]);

                    $message = 'Product published successfully.';

                } catch (Throwable $e) {

                    error_log(
                        'Admin product publish error: ' . $e->getMessage()
                    );

                    $error = 'Unable to publish the product.';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Unpublish product
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'unpublish') {

            $productId = filter_var(
                $_POST['product_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$productId) {

                $error = 'Invalid product.';

            } else {

                try {

                    $stmt = $db->prepare("
                        UPDATE products
                        SET
                            published = 0,
                            published_at = NULL
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':id' => $productId
                    ]);

                    $message = 'Product unpublished.';

                } catch (Throwable $e) {

                    error_log(
                        'Admin product unpublish error: ' . $e->getMessage()
                    );

                    $error = 'Unable to unpublish the product.';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Activate product
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'activate') {

            $productId = filter_var(
                $_POST['product_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$productId) {

                $error = 'Invalid product.';

            } else {

                try {

                    $stmt = $db->prepare("
                        UPDATE products
                        SET status = 'active'
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':id' => $productId
                    ]);

                    $message = 'Product activated.';

                } catch (Throwable $e) {

                    error_log(
                        'Admin product activate error: ' . $e->getMessage()
                    );

                    $error = 'Unable to activate the product.';
                }
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Deactivate product
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'deactivate') {

            $productId = filter_var(
                $_POST['product_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$productId) {

                $error = 'Invalid product.';

            } else {

                try {

                    $stmt = $db->prepare("
                        UPDATE products
                        SET status = 'inactive'
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':id' => $productId
                    ]);

                    $message = 'Product deactivated.';

                } catch (Throwable $e) {

                    error_log(
                        'Admin product deactivate error: ' . $e->getMessage()
                    );

                    $error = 'Unable to deactivate the product.';
                }
            }
        }
    }
}

/*
|--------------------------------------------------------------------------
| Load products
|--------------------------------------------------------------------------
*/

try {

    $stmt = $db->query("
        SELECT
            id,
            name,
            description,
            price,
            profit,
            quantity,
            status,
            published,
            published_at,
            created_at
        FROM products
        ORDER BY id DESC
    ");

    $products = $stmt->fetchAll();

} catch (Throwable $e) {

    error_log(
        'Admin products query error: ' . $e->getMessage()
    );

    $products = [];
    $error = 'Unable to load products.';
}

$pageTitle = 'Products';
$adminTitle = 'Products';
$adminActive = 'products';
$adminRoot = './';

require_once __DIR__ . '/../includes/admin_header.php';

?>

<div class="container-fluid py-4">

    <!-- =====================================================
         PAGE HEADER
         ===================================================== -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>

            <h1 class="h3 mb-1">
                Products
            </h1>

            <p class="text-secondary mb-0">
                Create and publish products for your users.
            </p>

        </div>

        <button
            type="button"
            class="btn btn-success"
            data-bs-toggle="modal"
            data-bs-target="#createProductModal"
        >
            <i class="bi bi-plus-lg me-1"></i>
            Create Product
        </button>

    </div>


    <!-- =====================================================
         SUCCESS MESSAGE
         ===================================================== -->

    <?php if ($message !== ''): ?>

        <div
            class="alert alert-success alert-dismissible fade show"
            role="alert"
        >

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
                aria-label="Close"
            ></button>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         ERROR MESSAGE
         ===================================================== -->

    <?php if ($error !== ''): ?>

        <div
            class="alert alert-danger alert-dismissible fade show"
            role="alert"
        >

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
                aria-label="Close"
            ></button>

        </div>

    <?php endif; ?>


    <!-- =====================================================
         PRODUCTS TABLE
         ===================================================== -->

    <div class="card border-0 shadow-sm">

        <div class="card-body p-0">

            <?php if (!$products): ?>

                <div class="text-center py-5">

                    <i class="bi bi-box-seam fs-1 text-secondary"></i>

                    <h5 class="mt-3">
                        No products yet
                    </h5>

                    <p class="text-secondary">
                        Create your first product to make it available to users.
                    </p>

                </div>

            <?php else: ?>

                <div class="table-responsive">

                    <table class="table table-hover align-middle mb-0">

                        <thead>

                            <tr>

                                <th>ID</th>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Profit</th>
                                <th>Quantity</th>
                                <th>Status</th>
                                <th>Published</th>
                                <th class="text-end">
                                    Actions
                                </th>

                            </tr>

                        </thead>

                        <tbody>

                        <?php foreach ($products as $product): ?>

                            <tr>

                                <td>
                                    #<?= (int) $product['id'] ?>
                                </td>


                                <td>

                                    <div class="fw-semibold">

                                        <?= htmlspecialchars(
                                            $product['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </div>

                                    <?php if (!empty($product['description'])): ?>

                                        <small class="text-secondary">

                                            <?= htmlspecialchars(
                                                mb_strimwidth(
                                                    $product['description'],
                                                    0,
                                                    70,
                                                    '...'
                                                ),
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>

                                        </small>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?= number_format(
                                        (float) $product['price'],
                                        2
                                    ) ?>

                                    ETB

                                </td>


                                <td class="text-success">

                                    +

                                    <?= number_format(
                                        (float) $product['profit'],
                                        2
                                    ) ?>

                                    ETB

                                </td>


                                <td>
                                    <?= (int) $product['quantity'] ?>
                                </td>


                                <td>

                                    <?php if ($product['status'] === 'active'): ?>

                                        <span class="badge text-bg-success">
                                            Active
                                        </span>

                                    <?php else: ?>

                                        <span class="badge text-bg-secondary">
                                            Inactive
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td>

                                    <?php if ((int) $product['published'] === 1): ?>

                                        <span class="badge text-bg-primary">
                                            Published
                                        </span>

                                    <?php else: ?>

                                        <span class="badge text-bg-warning">
                                            Draft
                                        </span>

                                    <?php endif; ?>

                                </td>


                                <td class="text-end">

                                    <div class="d-flex justify-content-end gap-2 flex-wrap">


                                        <!-- PUBLISH / UNPUBLISH -->

                                        <?php if ((int) $product['published'] === 1): ?>

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
                                                    value="unpublish"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="product_id"
                                                    value="<?= (int) $product['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-warning"
                                                >
                                                    Unpublish
                                                </button>

                                            </form>

                                        <?php else: ?>

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
                                                    value="publish"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="product_id"
                                                    value="<?= (int) $product['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-success"
                                                >

                                                    <i class="bi bi-send me-1"></i>

                                                    Publish

                                                </button>

                                            </form>

                                        <?php endif; ?>


                                        <!-- ACTIVATE / DEACTIVATE -->

                                        <?php if ($product['status'] === 'active'): ?>

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
                                                    value="deactivate"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="product_id"
                                                    value="<?= (int) $product['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-danger"
                                                >
                                                    Disable
                                                </button>

                                            </form>

                                        <?php else: ?>

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
                                                    value="activate"
                                                >

                                                <input
                                                    type="hidden"
                                                    name="product_id"
                                                    value="<?= (int) $product['id'] ?>"
                                                >

                                                <button
                                                    type="submit"
                                                    class="btn btn-sm btn-outline-success"
                                                >
                                                    Enable
                                                </button>

                                            </form>

                                        <?php endif; ?>

                                    </div>

                                </td>

                            </tr>

                        <?php endforeach; ?>

                        </tbody>

                    </table>

                </div>

            <?php endif; ?>

        </div>

    </div>

</div>


<!-- =====================================================
     CREATE PRODUCT MODAL
     ===================================================== -->

<div
    class="modal fade"
    id="createProductModal"
    tabindex="-1"
    aria-labelledby="createProductModalLabel"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form
                method="POST"
                action=""
            >

                <div class="modal-header">

                    <h5
                        class="modal-title"
                        id="createProductModalLabel"
                    >
                        Create Product
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
                        aria-label="Close"
                    ></button>

                </div>


                <div class="modal-body">

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
                        value="create"
                    >


                    <!-- PRODUCT NAME -->

                    <div class="mb-3">

                        <label
                            for="product-name"
                            class="form-label"
                        >
                            Product name
                        </label>

                        <input
                            type="text"
                            id="product-name"
                            name="name"
                            class="form-control"
                            required
                            maxlength="150"
                            placeholder="Example: VIP Product 1"
                        >

                    </div>


                    <!-- DESCRIPTION -->

                    <div class="mb-3">

                        <label
                            for="product-description"
                            class="form-label"
                        >
                            Description
                        </label>

                        <textarea
                            id="product-description"
                            name="description"
                            class="form-control"
                            rows="3"
                            placeholder="Describe the product..."
                        ></textarea>

                    </div>


                    <!-- PRICE + PROFIT -->

                    <div class="row">

                        <div class="col-md-6 mb-3">

                            <label
                                for="product-price"
                                class="form-label"
                            >
                                Price (ETB)
                            </label>

                            <input
                                type="number"
                                id="product-price"
                                name="price"
                                class="form-control"
                                min="0.01"
                                step="0.01"
                                required
                                placeholder="500.00"
                            >

                        </div>


                        <div class="col-md-6 mb-3">

                            <label
                                for="product-profit"
                                class="form-label"
                            >
                                Profit (ETB)
                            </label>

                            <input
                                type="number"
                                id="product-profit"
                                name="profit"
                                class="form-control"
                                min="0"
                                step="0.01"
                                required
                                placeholder="75.00"
                            >

                        </div>

                    </div>


                    <!-- QUANTITY -->

                    <div class="mb-3">

                        <label
                            for="product-quantity"
                            class="form-label"
                        >
                            Quantity
                        </label>

                        <input
                            type="number"
                            id="product-quantity"
                            name="quantity"
                            class="form-control"
                            min="1"
                            value="1"
                            required
                        >

                    </div>

                </div>


                <div class="modal-footer">

                    <button
                        type="button"
                        class="btn btn-secondary"
                        data-bs-dismiss="modal"
                    >
                        Cancel
                    </button>

                    <button
                        type="submit"
                        class="btn btn-success"
                    >

                        <i class="bi bi-plus-lg me-1"></i>

                        Create Product

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<!-- =====================================================
     BOOTSTRAP JAVASCRIPT
     ===================================================== -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>