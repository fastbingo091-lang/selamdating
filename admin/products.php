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
$productImageDirectory = __DIR__ . '/../assets/uploads/products/';
$productImageWebDirectory = 'assets/uploads/products/';

function uploadMarketplaceProductImage(array $file, string $directory): ?string
{
    $uploadError = $file['error'] ?? UPLOAD_ERR_NO_FILE;
    if ($uploadError === UPLOAD_ERR_NO_FILE) {
        return null;
    }
    if ($uploadError !== UPLOAD_ERR_OK || !is_string($file['tmp_name'] ?? null)) {
        throw new RuntimeException('The product image upload failed.');
    }
    if ((int) ($file['size'] ?? 0) <= 0 || (int) $file['size'] > 5 * 1024 * 1024) {
        throw new RuntimeException('Choose a product image no larger than 5 MB.');
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid product image upload.');
    }

    $mimeType = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!is_string($mimeType) || !isset($extensions[$mimeType]) || @getimagesize($file['tmp_name']) === false) {
        throw new RuntimeException('Use a valid JPG, PNG, or WEBP product image.');
    }

    if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
        throw new RuntimeException('Unable to prepare product image storage.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $directory . $filename)) {
        throw new RuntimeException('Unable to save the product image.');
    }

    return $filename;
}

function removeMarketplaceProductImage(?string $filename, string $directory): void
{
    if ($filename === null || $filename === '') {
        return;
    }
    $path = $directory . basename($filename);
    if (is_file($path) && !unlink($path)) {
        error_log('Unable to remove replaced product image: ' . $path);
    }
}

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

            $nameInput = $_POST['name'] ?? '';
            $descriptionInput = $_POST['description'] ?? '';
            $name = is_string($nameInput) ? trim($nameInput) : '';
            $description = is_string($descriptionInput) ? trim($descriptionInput) : '';

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
            $durationDays = filter_var($_POST['profit_duration_days'] ?? null, FILTER_VALIDATE_INT);

            if ($name === '' || mb_strlen($name) > 120) {

                $error = 'Product name is required and must be 120 characters or fewer.';

            } elseif ($price === false || $price <= 0) {

                $error = 'Enter a valid product price.';

            } elseif ($profit === false || $profit < 0) {

                $error = 'Enter a valid product profit.';

            } elseif ($quantity === false || $quantity < 1) {

                $error = 'Quantity must be at least 1.';

            } elseif ($durationDays === false || $durationDays < 1 || $durationDays > 3650) {

                $error = 'Profit duration must be from 1 to 3650 days.';

            } else {

                $imageFilename = null;
                try {
                    $imageFilename = uploadMarketplaceProductImage($_FILES['image'] ?? [], $productImageDirectory);

                    $stmt = $db->prepare("
                        INSERT INTO products
                        (
                            name,
                            description,
                            image_path,
                            price,
                            profit,
                            profit_duration_days,
                            quantity,
                            status,
                            published
                        )
                        VALUES
                        (
                            :name,
                            :description,
                            :image_path,
                            :price,
                            :profit,
                            :profit_duration_days,
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
                        ':image_path' => $imageFilename !== null
                            ? $productImageWebDirectory . $imageFilename
                            : null,
                        ':price' => $price,
                        ':profit' => $profit,
                        ':profit_duration_days' => $durationDays,
                        ':quantity' => $quantity
                    ]);

                    $message = 'Product created successfully.';

                } catch (Throwable $e) {
                    removeMarketplaceProductImage($imageFilename, $productImageDirectory);

                    error_log(
                        'Admin product create error: ' . $e->getMessage()
                    );

                    $error = $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Unable to create the product.';
                }
            }
        }

        elseif ($action === 'update') {

            $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
            $nameInput = $_POST['name'] ?? '';
            $descriptionInput = $_POST['description'] ?? '';
            $name = is_string($nameInput) ? trim($nameInput) : '';
            $description = is_string($descriptionInput) ? trim($descriptionInput) : '';
            $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
            $profit = filter_var($_POST['profit'] ?? null, FILTER_VALIDATE_FLOAT);
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            $durationDays = filter_var($_POST['profit_duration_days'] ?? null, FILTER_VALIDATE_INT);

            if (!$productId) {
                $error = 'Invalid product.';
            } elseif ($name === '' || mb_strlen($name) > 120) {
                $error = 'Product name is required and must be 120 characters or fewer.';
            } elseif ($price === false || $price <= 0) {
                $error = 'Enter a valid product price.';
            } elseif ($profit === false || $profit < 0) {
                $error = 'Enter a valid daily profit.';
            } elseif ($quantity === false || $quantity < 0) {
                $error = 'Quantity cannot be less than zero.';
            } elseif ($durationDays === false || $durationDays < 1 || $durationDays > 3650) {
                $error = 'Profit duration must be from 1 to 3650 days.';
            } else {
                $newImageFilename = null;
                try {
                    $currentImageQuery = $db->prepare('SELECT image_path FROM products WHERE id = ? LIMIT 1');
                    $currentImageQuery->execute([$productId]);
                    $currentImagePath = $currentImageQuery->fetchColumn();
                    if ($currentImagePath === false) {
                        throw new RuntimeException('Product not found.');
                    }

                    $newImageFilename = uploadMarketplaceProductImage($_FILES['image'] ?? [], $productImageDirectory);
                    $imagePath = $newImageFilename !== null
                        ? $productImageWebDirectory . $newImageFilename
                        : $currentImagePath;

                    $stmt = $db->prepare("
                        UPDATE products
                        SET name = :name,
                            description = :description,
                            image_path = :image_path,
                            price = :price,
                            profit = :profit,
                            profit_duration_days = :profit_duration_days,
                            quantity = :quantity
                        WHERE id = :id
                    ");
                    $stmt->execute([
                        ':name' => $name,
                        ':description' => $description !== '' ? $description : null,
                        ':image_path' => $imagePath,
                        ':price' => $price,
                        ':profit' => $profit,
                        ':profit_duration_days' => $durationDays,
                        ':quantity' => $quantity,
                        ':id' => $productId,
                    ]);

                    if ($newImageFilename !== null && is_string($currentImagePath)) {
                        removeMarketplaceProductImage(basename($currentImagePath), $productImageDirectory);
                    }
                    $message = 'Product details updated. Existing purchases keep their original price, image, daily profit, and duration.';
                } catch (Throwable $e) {
                    removeMarketplaceProductImage($newImageFilename, $productImageDirectory);
                    error_log('Admin product update error: ' . $e->getMessage());
                    $error = $e instanceof RuntimeException
                        ? $e->getMessage()
                        : 'Unable to update the product.';
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
        SELECT p.id, p.name, p.description, p.image_path, p.price, p.profit,
            p.profit_duration_days, p.quantity, p.status, p.published, p.published_at, p.created_at,
            COALESCE(SUM(mp.quantity), 0) AS units_sold
        FROM products p
        LEFT JOIN marketplace_purchases mp ON mp.product_id = p.id
        GROUP BY p.id, p.name, p.description, p.image_path, p.price, p.profit,
            p.profit_duration_days, p.quantity, p.status, p.published, p.published_at, p.created_at
        ORDER BY p.id DESC
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
                Add product photos, set daily profit and its duration, update stock, and publish to the marketplace.
            </p>
            <p class="small-note mb-0 mt-2">Referral commissions are credited when daily profit is claimed: 15% to level 1, 5% to level 2, and 3% to level 3.</p>

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
                                <th>Daily profit</th>
                                <th>Profit duration</th>
                                <th>Sold</th>
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
                                    <?php if (!empty($product['image_path'])): ?>
                                        <img src="../<?= htmlspecialchars((string) $product['image_path'], ENT_QUOTES, 'UTF-8') ?>" alt="" width="44" height="44" class="rounded object-fit-cover me-2">
                                    <?php endif; ?>
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
                                    <?= (int) $product['profit_duration_days'] ?> days
                                </td>

                                <td><?= number_format((int) $product['units_sold']) ?></td>

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

                                        <button
                                            type="button"
                                            class="btn btn-sm btn-outline-light"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editProductModal"
                                            data-id="<?= (int) $product['id'] ?>"
                                            data-name="<?= htmlspecialchars((string) $product['name'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-description="<?= htmlspecialchars((string) ($product['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                            data-price="<?= htmlspecialchars((string) $product['price'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-profit="<?= htmlspecialchars((string) $product['profit'], ENT_QUOTES, 'UTF-8') ?>"
                                            data-duration="<?= (int) $product['profit_duration_days'] ?>"
                                            data-quantity="<?= (int) $product['quantity'] ?>"
                                            data-image="<?= htmlspecialchars((string) ($product['image_path'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                        >
                                            <i class="bi bi-pencil me-1" aria-hidden="true"></i>
                                            Edit
                                        </button>

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
                enctype="multipart/form-data"
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
                            maxlength="120"
                            placeholder="Example: Desk lamp"
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

                    <div class="mb-3">
                        <label for="product-image" class="form-label">Product image</label>
                        <input type="file" id="product-image" name="image" class="form-control" accept="image/jpeg,image/png,image/webp">
                        <small class="form-text text-secondary">JPG, PNG, or WEBP; max 5 MB.</small>
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
                                Daily profit (ETB)
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

                        <div class="col-md-6 mb-3">
                            <label for="product-duration" class="form-label">Daily profit duration (days)</label>
                            <input type="number" id="product-duration" name="profit_duration_days" class="form-control" min="1" max="3650" value="30" required>
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

<div class="modal fade" id="editProductModal" tabindex="-1" aria-labelledby="editProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="post" enctype="multipart/form-data">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProductModalLabel">Edit product</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                    <input type="hidden" name="action" value="update">
                    <input type="hidden" name="product_id" id="edit-product-id">
                    <div class="mb-3">
                        <label for="edit-product-name" class="form-label">Product name</label>
                        <input type="text" class="form-control" id="edit-product-name" name="name" maxlength="120" required>
                    </div>
                    <div class="mb-3">
                        <label for="edit-product-description" class="form-label">Description</label>
                        <textarea class="form-control" id="edit-product-description" name="description" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label for="edit-product-image" class="form-label">Replace product image</label>
                        <input type="file" class="form-control" id="edit-product-image" name="image" accept="image/jpeg,image/png,image/webp">
                        <small class="form-text text-secondary">Leave empty to keep the current image. JPG, PNG, or WEBP; max 5 MB.</small>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="edit-product-price" class="form-label">Price (ETB)</label>
                            <input type="number" class="form-control" id="edit-product-price" name="price" min="0.01" step="0.01" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit-product-profit" class="form-label">Daily profit (ETB)</label>
                            <input type="number" class="form-control" id="edit-product-profit" name="profit" min="0" step="0.01" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit-product-duration" class="form-label">Duration (days)</label>
                            <input type="number" class="form-control" id="edit-product-duration" name="profit_duration_days" min="1" max="3650" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="edit-product-quantity" class="form-label">Stock</label>
                            <input type="number" class="form-control" id="edit-product-quantity" name="quantity" min="0" step="1" required>
                        </div>
                    </div>
                    <p class="small-note mb-0">Updates affect future purchases only. Users keep the purchase price and daily profit recorded when they bought the product.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>Save changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.getElementById('editProductModal').addEventListener('show.bs.modal', (event) => {
        const button = event.relatedTarget;
        if (!(button instanceof HTMLElement)) {
            return;
        }
        document.getElementById('edit-product-id').value = button.dataset.id || '';
        document.getElementById('edit-product-name').value = button.dataset.name || '';
        document.getElementById('edit-product-description').value = button.dataset.description || '';
        document.getElementById('edit-product-price').value = button.dataset.price || '';
        document.getElementById('edit-product-profit').value = button.dataset.profit || '';
        document.getElementById('edit-product-duration').value = button.dataset.duration || '30';
        document.getElementById('edit-product-quantity').value = button.dataset.quantity || '0';
    });
</script>


<!-- =====================================================
     BOOTSTRAP JAVASCRIPT
     ===================================================== -->

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>