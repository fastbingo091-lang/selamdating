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
| IMAGE UPLOAD SETTINGS
|--------------------------------------------------------------------------
*/

$uploadDirectory = __DIR__ . '/../assets/uploads/vip/';
$uploadWebPath = '../assets/uploads/vip/';

$allowedMimeTypes = [
    'image/jpeg' => 'jpg',
    'image/png'  => 'png',
    'image/webp' => 'webp',
    'image/gif'  => 'gif',
];

$maxFileSize = 5 * 1024 * 1024; // 5 MB


/*
|--------------------------------------------------------------------------
| CREATE UPLOAD DIRECTORY
|--------------------------------------------------------------------------
*/

if (!is_dir($uploadDirectory)) {
    @mkdir($uploadDirectory, 0755, true);
}


/*
|--------------------------------------------------------------------------
| IMAGE UPLOAD FUNCTION
|--------------------------------------------------------------------------
*/

function uploadVipImage(
    array $file,
    string $uploadDirectory,
    array $allowedMimeTypes,
    int $maxFileSize
): string {

    if (
        !isset($file['error']) ||
        $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        throw new RuntimeException('Please select a membership image.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('The image upload failed.');
    }

    if (!isset($file['size']) || $file['size'] <= 0) {
        throw new RuntimeException('The uploaded image is empty.');
    }

    if ($file['size'] > $maxFileSize) {
        throw new RuntimeException(
            'Image size must be 5 MB or smaller.'
        );
    }

    if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
        throw new RuntimeException('Invalid uploaded image.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);

    $mimeType = $finfo->file($file['tmp_name']);

    if (!isset($allowedMimeTypes[$mimeType])) {
        throw new RuntimeException(
            'Invalid image type. Use JPG, PNG, WEBP, or GIF.'
        );
    }

    /*
    |--------------------------------------------------------------------------
    | Verify image contents
    |--------------------------------------------------------------------------
    */

    $imageInfo = @getimagesize($file['tmp_name']);

    if ($imageInfo === false) {
        throw new RuntimeException(
            'The uploaded file is not a valid image.'
        );
    }

    $extension = $allowedMimeTypes[$mimeType];

    /*
    |--------------------------------------------------------------------------
    | Generate random filename
    |--------------------------------------------------------------------------
    */

    $filename =
        'vip_' .
        bin2hex(random_bytes(16)) .
        '.' .
        $extension;

    $destination = $uploadDirectory . $filename;

    if (!move_uploaded_file(
        $file['tmp_name'],
        $destination
    )) {
        throw new RuntimeException(
            'Unable to save the uploaded image.'
        );
    }

    return $filename;
}


/*
|--------------------------------------------------------------------------
| DELETE OLD IMAGE
|--------------------------------------------------------------------------
*/

function deleteVipImage(
    ?string $filename,
    string $uploadDirectory
): void {

    if (!$filename) {
        return;
    }

    $filename = basename($filename);

    $path = $uploadDirectory . $filename;

    if (is_file($path)) {
        @unlink($path);
    }
}


/*
|--------------------------------------------------------------------------
| POST ACTIONS
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? '')) {

        $error = 'Invalid security token. Please try again.';

    } else {

        $action = $_POST['action'] ?? '';


        /*
        |--------------------------------------------------------------------------
        | CREATE VIP PACKAGE
        |--------------------------------------------------------------------------
        */

        if ($action === 'create') {

            $name = trim($_POST['name'] ?? '');

            $price = filter_var(
                $_POST['price'] ?? null,
                FILTER_VALIDATE_FLOAT
            );

            $profit = filter_var(
                $_POST['profit'] ?? null,
                FILTER_VALIDATE_FLOAT
            );

            $productCount = filter_var(
                $_POST['product_count'] ?? null,
                FILTER_VALIDATE_INT
            );

            if ($name === '') {

                $error = 'Membership name is required.';

            } elseif ($price === false || $price <= 0) {

                $error = 'Enter a valid package price.';

            } elseif ($profit === false || $profit < 0) {

                $error = 'Enter a valid package profit.';

            } elseif (
                $productCount === false ||
                $productCount < 1
            ) {

                $error = 'Product count must be at least 1.';

            } elseif (
                !isset($_FILES['image']) ||
                $_FILES['image']['error'] === UPLOAD_ERR_NO_FILE
            ) {

                $error = 'Membership image is required.';

            } else {

                try {

                    $imageFilename = uploadVipImage(
                        $_FILES['image'],
                        $uploadDirectory,
                        $allowedMimeTypes,
                        $maxFileSize
                    );

                    $stmt = $db->prepare("
                        INSERT INTO vip_packages
                        (
                            name,
                            image,
                            price,
                            profit,
                            product_count,
                            status
                        )
                        VALUES
                        (
                            :name,
                            :image,
                            :price,
                            :profit,
                            :product_count,
                            'active'
                        )
                    ");

                    $stmt->execute([
                        ':name' => $name,
                        ':image' => $imageFilename,
                        ':price' => $price,
                        ':profit' => $profit,
                        ':product_count' => $productCount
                    ]);

                    $message =
                        'Membership created successfully.';

                } catch (Throwable $e) {

                    if (
                        isset($imageFilename) &&
                        $imageFilename !== ''
                    ) {
                        deleteVipImage(
                            $imageFilename,
                            $uploadDirectory
                        );
                    }

                    error_log(
                        'VIP package create error: ' .
                        $e->getMessage()
                    );

                    $error =
                        $e->getMessage();
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | ACTIVATE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'activate') {

            $id = filter_var(
                $_POST['package_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id) {

                $error = 'Invalid membership.';

            } else {

                try {

                    $stmt = $db->prepare("
                        UPDATE vip_packages
                        SET status = 'active'
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':id' => $id
                    ]);

                    $message =
                        'Membership activated.';

                } catch (Throwable $e) {

                    error_log(
                        'VIP package activate error: ' .
                        $e->getMessage()
                    );

                    $error =
                        'Unable to activate the membership.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | DEACTIVATE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'deactivate') {

            $id = filter_var(
                $_POST['package_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id) {

                $error = 'Invalid membership.';

            } else {

                try {

                    $stmt = $db->prepare("
                        UPDATE vip_packages
                        SET status = 'inactive'
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':id' => $id
                    ]);

                    $message =
                        'Membership deactivated.';

                } catch (Throwable $e) {

                    error_log(
                        'VIP package deactivate error: ' .
                        $e->getMessage()
                    );

                    $error =
                        'Unable to deactivate the membership.';
                }
            }
        }


        /*
        |--------------------------------------------------------------------------
        | UPDATE VIP PACKAGE
        |--------------------------------------------------------------------------
        */

        elseif ($action === 'update') {

            $id = filter_var(
                $_POST['package_id'] ?? null,
                FILTER_VALIDATE_INT
            );

            $name = trim($_POST['name'] ?? '');

            $price = filter_var(
                $_POST['price'] ?? null,
                FILTER_VALIDATE_FLOAT
            );

            $profit = filter_var(
                $_POST['profit'] ?? null,
                FILTER_VALIDATE_FLOAT
            );

            $productCount = filter_var(
                $_POST['product_count'] ?? null,
                FILTER_VALIDATE_INT
            );

            if (!$id) {

                $error = 'Invalid membership.';

            } elseif ($name === '') {

                $error = 'Membership name is required.';

            } elseif ($price === false || $price <= 0) {

                $error = 'Enter a valid package price.';

            } elseif ($profit === false || $profit < 0) {

                $error = 'Enter a valid package profit.';

            } elseif (
                $productCount === false ||
                $productCount < 1
            ) {

                $error =
                    'Product count must be at least 1.';

            } else {

                try {

                    /*
                    |--------------------------------------------------------------------------
                    | Get old image
                    |--------------------------------------------------------------------------
                    */

                    $oldStmt = $db->prepare("
                        SELECT image
                        FROM vip_packages
                        WHERE id = :id
                        LIMIT 1
                    ");

                    $oldStmt->execute([
                        ':id' => $id
                    ]);

                    $oldPackage =
                        $oldStmt->fetch(PDO::FETCH_ASSOC);

                    if (!$oldPackage) {
                        throw new RuntimeException(
                            'Membership not found.'
                        );
                    }

                    $oldImage =
                        $oldPackage['image'] ?? null;

                    $newImage = $oldImage;

                    /*
                    |--------------------------------------------------------------------------
                    | Check for new image
                    |--------------------------------------------------------------------------
                    */

                    $hasNewImage =
                        isset($_FILES['image']) &&
                        $_FILES['image']['error'] !==
                        UPLOAD_ERR_NO_FILE;

                    if ($hasNewImage) {

                        $newImage = uploadVipImage(
                            $_FILES['image'],
                            $uploadDirectory,
                            $allowedMimeTypes,
                            $maxFileSize
                        );
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Update package
                    |--------------------------------------------------------------------------
                    */

                    $stmt = $db->prepare("
                        UPDATE vip_packages
                        SET
                            name = :name,
                            image = :image,
                            price = :price,
                            profit = :profit,
                            product_count = :product_count
                        WHERE id = :id
                    ");

                    $stmt->execute([
                        ':id' => $id,
                        ':name' => $name,
                        ':image' => $newImage,
                        ':price' => $price,
                        ':profit' => $profit,
                        ':product_count' => $productCount
                    ]);

                    /*
                    |--------------------------------------------------------------------------
                    | Delete old image after successful update
                    |--------------------------------------------------------------------------
                    */

                    if (
                        $hasNewImage &&
                        $oldImage &&
                        $oldImage !== $newImage
                    ) {
                        deleteVipImage(
                            $oldImage,
                            $uploadDirectory
                        );
                    }

                    $message =
                        'Membership updated successfully.';

                } catch (Throwable $e) {

                    /*
                    |--------------------------------------------------------------------------
                    | Delete newly uploaded image if update failed
                    |--------------------------------------------------------------------------
                    */

                    if (
                        isset($newImage) &&
                        isset($oldImage) &&
                        $newImage !== $oldImage
                    ) {
                        deleteVipImage(
                            $newImage,
                            $uploadDirectory
                        );
                    }

                    error_log(
                        'VIP package update error: ' .
                        $e->getMessage()
                    );

                    $error =
                        $e->getMessage();
                }
            }
        }
    }
}


/*
|--------------------------------------------------------------------------
| LOAD VIP PACKAGES
|--------------------------------------------------------------------------
*/

try {

    $stmt = $db->query("
        SELECT
            id,
            name,
            image,
            price,
            profit,
            product_count,
            status,
            created_at
        FROM vip_packages
        ORDER BY id ASC
    ");

    $packages = $stmt->fetchAll();

} catch (Throwable $e) {

    error_log(
        'VIP package query error: ' .
        $e->getMessage()
    );

    $packages = [];

    $error =
        'Unable to load memberships.';
}


$pageTitle = 'Memberships';
$adminTitle = 'Memberships';
$adminActive = 'vip-packages';
$adminRoot = './';

require_once __DIR__ . '/../includes/admin_header.php';

?>

<style>

.vip-package-image {
    width: 100%;
    height: 190px;
    object-fit: cover;
    border-radius: 12px;
    background: #fff0f3;
}

.vip-package-image-placeholder {
    width: 100%;
    height: 190px;
    border-radius: 12px;
    background: linear-gradient(
        135deg,
        #fff7f6,
        #f9e5eb
    );
    display: flex;
    align-items: center;
    justify-content: center;
    color: #a17585;
    font-size: 55px;
}

.vip-upload-preview {
    width: 100%;
    height: 180px;
    object-fit: cover;
    border-radius: 12px;
    display: none;
    margin-top: 12px;
}

.vip-upload-box {
    border: 2px dashed #e4c7d1;
    border-radius: 14px;
    padding: 20px;
    text-align: center;
    cursor: pointer;
    transition: .2s;
}

.vip-upload-box:hover {
    border-color: #c45170;
    background: rgba(196, 81, 112, .04);
}

.vip-upload-box i {
    font-size: 34px;
    color: #c45170;
}

.vip-upload-box p {
    margin: 8px 0 0;
}

.vip-current-image {
    width: 100%;
    max-height: 180px;
    object-fit: cover;
    border-radius: 12px;
    margin-bottom: 12px;
}

</style>


<div class="container-fluid py-4">

    <!-- PAGE HEADER -->

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4">

        <div>

            <h1 class="h3 mb-1">
                Memberships
            </h1>

            <p class="text-secondary mb-0">
                Create packages and assign their included items.
            </p>

        </div>

        <button
            type="button"
            class="btn btn-success"
            data-bs-toggle="modal"
            data-bs-target="#createVipModal"
        >
            <i class="bi bi-plus-lg me-1"></i>
            Create Membership
        </button>

    </div>


    <!-- SUCCESS -->

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


    <!-- ERROR -->

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


    <!-- PACKAGES -->

    <div class="row g-4">

        <?php if (!$packages): ?>

            <div class="col-12">

                <div class="card border-0 shadow-sm">

                    <div class="card-body text-center py-5">

                        <i class="bi bi-gem fs-1 text-secondary"></i>

                        <h5 class="mt-3">
                            No memberships yet
                        </h5>

                        <p class="text-secondary mb-0">
                            Create your first membership.
                        </p>

                    </div>

                </div>

            </div>

        <?php else: ?>

            <?php foreach ($packages as $package): ?>

                <div class="col-12 col-md-6 col-xl-4">

                    <div class="card border-0 shadow-sm h-100">

                        <div class="card-body">

                            <!-- IMAGE -->

                            <?php if (!empty($package['image'])): ?>

                                <img
                                    src="<?= htmlspecialchars(
                                        $uploadWebPath .
                                        $package['image'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    alt="<?= htmlspecialchars(
                                        $package['name'],
                                        ENT_QUOTES,
                                        'UTF-8'
                                    ) ?>"
                                    class="vip-package-image mb-3"
                                >

                            <?php else: ?>

                                <div class="vip-package-image-placeholder mb-3">

                                    <i class="bi bi-gem"></i>

                                </div>

                            <?php endif; ?>


                            <!-- TITLE -->

                            <div class="d-flex justify-content-between align-items-start mb-3">

                                <div>

                                    <h4 class="mb-1">

                                        <?= htmlspecialchars(
                                            $package['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>

                                    </h4>

                                    <small class="text-secondary">
                                        Package #<?= (int) $package['id'] ?>
                                    </small>

                                </div>

                                <?php if ($package['status'] === 'active'): ?>

                                    <span class="badge text-bg-success">
                                        Active
                                    </span>

                                <?php else: ?>

                                    <span class="badge text-bg-secondary">
                                        Inactive
                                    </span>

                                <?php endif; ?>

                            </div>


                            <!-- PRICE -->

                            <div class="mb-3">

                                <div class="text-secondary small">
                                    Package Price
                                </div>

                                <div class="fs-3 fw-bold">

                                    <?= number_format(
                                        (float) $package['price'],
                                        2
                                    ) ?>

                                    ETB

                                </div>

                            </div>


                            <!-- DETAILS -->

                            <div class="row g-2 mb-4">

                                <div class="col-6">

                                    <div class="border rounded p-3">

                                        <div class="text-secondary small">
                                            Profit
                                        </div>

                                        <div class="fw-bold text-success">

                                            +

                                            <?= number_format(
                                                (float) $package['profit'],
                                                2
                                            ) ?>

                                            ETB

                                        </div>

                                    </div>

                                </div>


                                <div class="col-6">

                                    <div class="border rounded p-3">

                                        <div class="text-secondary small">
                                            Products
                                        </div>

                                        <div class="fw-bold">

                                            <?= (int) $package['product_count'] ?>

                                        </div>

                                    </div>

                                </div>

                            </div>


                            <!-- ACTIONS -->

                            <div class="d-grid gap-2">

                                <a
                                    href="vip-package-products.php?id=<?= (int) $package['id'] ?>"
                                    class="btn btn-primary"
                                >

                                    <i class="bi bi-box-seam me-1"></i>

                                    Manage Products

                                </a>


                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    data-bs-toggle="modal"
                                    data-bs-target="#editVip<?= (int) $package['id'] ?>"
                                >

                                    <i class="bi bi-pencil me-1"></i>

                                    Edit Package

                                </button>


                                <?php if ($package['status'] === 'active'): ?>

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
                                            name="package_id"
                                            value="<?= (int) $package['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-outline-danger w-100"
                                        >
                                            Deactivate
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
                                            name="package_id"
                                            value="<?= (int) $package['id'] ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn btn-outline-success w-100"
                                        >
                                            Activate
                                        </button>

                                    </form>

                                <?php endif; ?>

                            </div>

                        </div>

                    </div>

                </div>


                <!-- EDIT MODAL -->

                <div
                    class="modal fade"
                    id="editVip<?= (int) $package['id'] ?>"
                    tabindex="-1"
                    aria-hidden="true"
                >

                    <div class="modal-dialog modal-dialog-centered">

                        <div class="modal-content">

                            <form
                                method="POST"
                                enctype="multipart/form-data"
                            >

                                <div class="modal-header">

                                    <h5 class="modal-title">
                                        Edit <?= htmlspecialchars(
                                            $package['name'],
                                            ENT_QUOTES,
                                            'UTF-8'
                                        ) ?>
                                    </h5>

                                    <button
                                        type="button"
                                        class="btn-close"
                                        data-bs-dismiss="modal"
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
                                        value="update"
                                    >

                                    <input
                                        type="hidden"
                                        name="package_id"
                                        value="<?= (int) $package['id'] ?>"
                                    >


                                    <!-- CURRENT IMAGE -->

                                    <?php if (!empty($package['image'])): ?>

                                        <label class="form-label">
                                            Current Image
                                        </label>

                                        <img
                                            src="<?= htmlspecialchars(
                                                $uploadWebPath .
                                                $package['image'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            class="vip-current-image"
                                            alt="Current package image"
                                        >

                                    <?php endif; ?>


                                    <!-- NEW IMAGE -->

                                    <div class="mb-3">

                                        <label class="form-label">
                                            Change Image
                                        </label>

                                        <input
                                            type="file"
                                            name="image"
                                            class="form-control"
                                            accept="image/jpeg,image/png,image/webp,image/gif"
                                        >

                                        <div class="form-text">
                                            JPG, PNG, WEBP or GIF. Maximum 5 MB.
                                        </div>

                                    </div>


                                    <div class="mb-3">

                                        <label class="form-label">
                                            Package name
                                        </label>

                                        <input
                                            type="text"
                                            name="name"
                                            class="form-control"
                                            value="<?= htmlspecialchars(
                                                $package['name'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            maxlength="100"
                                            required
                                        >

                                    </div>


                                    <div class="mb-3">

                                        <label class="form-label">
                                            Price (ETB)
                                        </label>

                                        <input
                                            type="number"
                                            name="price"
                                            class="form-control"
                                            min="0.01"
                                            step="0.01"
                                            value="<?= htmlspecialchars(
                                                (string) $package['price'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            required
                                        >

                                    </div>


                                    <div class="mb-3">

                                        <label class="form-label">
                                            Profit (ETB)
                                        </label>

                                        <input
                                            type="number"
                                            name="profit"
                                            class="form-control"
                                            min="0"
                                            step="0.01"
                                            value="<?= htmlspecialchars(
                                                (string) $package['profit'],
                                                ENT_QUOTES,
                                                'UTF-8'
                                            ) ?>"
                                            required
                                        >

                                    </div>


                                    <div class="mb-3">

                                        <label class="form-label">
                                            Product count
                                        </label>

                                        <input
                                            type="number"
                                            name="product_count"
                                            class="form-control"
                                            min="1"
                                            value="<?= (int) $package['product_count'] ?>"
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
                                        <i class="bi bi-check-lg me-1"></i>
                                        Save Changes
                                    </button>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        <?php endif; ?>

    </div>

</div>


<!-- CREATE VIP MODAL -->

<div
    class="modal fade"
    id="createVipModal"
    tabindex="-1"
    aria-hidden="true"
>

    <div class="modal-dialog modal-dialog-centered">

        <div class="modal-content">

            <form
                method="POST"
                enctype="multipart/form-data"
            >

                <div class="modal-header">

                    <h5 class="modal-title">
                        Create Membership
                    </h5>

                    <button
                        type="button"
                        class="btn-close"
                        data-bs-dismiss="modal"
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


                    <!-- IMAGE -->

                    <div class="mb-4">

                        <label class="form-label">
                            Membership Image
                        </label>

                        <label
                            for="vipImage"
                            class="vip-upload-box d-block"
                        >

                            <i class="bi bi-cloud-arrow-up"></i>

                            <p class="fw-semibold mb-1">
                                Click to upload image
                            </p>

                            <small class="text-secondary">
                                JPG, PNG, WEBP or GIF · Maximum 5 MB
                            </small>

                        </label>

                        <input
                            type="file"
                            id="vipImage"
                            name="image"
                            class="d-none"
                            accept="image/jpeg,image/png,image/webp,image/gif"
                            required
                        >

                        <img
                            id="vipImagePreview"
                            class="vip-upload-preview"
                            alt="VIP image preview"
                        >

                    </div>


                    <!-- NAME -->

                    <div class="mb-3">

                        <label class="form-label">
                            Package name
                        </label>

                        <input
                            type="text"
                            name="name"
                            class="form-control"
                            placeholder="VIP 1"
                            maxlength="100"
                            required
                        >

                    </div>


                    <!-- PRICE -->

                    <div class="mb-3">

                        <label class="form-label">
                            Package price (ETB)
                        </label>

                        <input
                            type="number"
                            name="price"
                            class="form-control"
                            placeholder="500"
                            min="0.01"
                            step="0.01"
                            required
                        >

                    </div>


                    <!-- PROFIT -->

                    <div class="mb-3">

                        <label class="form-label">
                            Total profit (ETB)
                        </label>

                        <input
                            type="number"
                            name="profit"
                            class="form-control"
                            placeholder="75"
                            min="0"
                            step="0.01"
                            required
                        >

                    </div>


                    <!-- PRODUCT COUNT -->

                    <div class="mb-3">

                        <label class="form-label">
                            Number of products
                        </label>

                        <input
                            type="number"
                            name="product_count"
                            class="form-control"
                            placeholder="5"
                            min="1"
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

                        Create Package

                    </button>

                </div>

            </form>

        </div>

    </div>

</div>


<script>

document.addEventListener('DOMContentLoaded', function () {

    const imageInput =
        document.getElementById('vipImage');

    const preview =
        document.getElementById('vipImagePreview');

    if (imageInput && preview) {

        imageInput.addEventListener(
            'change',
            function () {

                const file =
                    this.files && this.files[0];

                if (!file) {

                    preview.style.display = 'none';
                    preview.removeAttribute('src');

                    return;
                }

                if (!file.type.startsWith('image/')) {

                    alert(
                        'Please select a valid image.'
                    );

                    this.value = '';

                    preview.style.display = 'none';

                    return;
                }

                if (file.size > 5 * 1024 * 1024) {

                    alert(
                        'Image must be 5 MB or smaller.'
                    );

                    this.value = '';

                    preview.style.display = 'none';

                    return;
                }

                const reader =
                    new FileReader();

                reader.onload =
                    function (event) {

                        preview.src =
                            event.target.result;

                        preview.style.display =
                            'block';
                    };

                reader.readAsDataURL(file);
            }
        );
    }

});

</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>