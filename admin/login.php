<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();

if (isset($_SESSION['admin_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usernameInput = $_POST['username'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $csrfInput = $_POST['csrf_token'] ?? '';

    $username = is_string($usernameInput) ? trim($usernameInput) : '';
    $password = is_string($passwordInput) ? $passwordInput : '';

    if (!csrf_token_is_valid($csrfInput)) {
        $error = 'Your form expired. Please reload the page.';
    } elseif ($username === '' || $password === '') {
        $error = 'Please enter your username and password.';
    } else {
        try {
            $connection = database();

            $statement = $connection->prepare(
                'SELECT id, username, password_hash, status
                 FROM admins
                 WHERE username = :username
                 LIMIT 1'
            );

            $statement->execute([
                'username' => $username
            ]);

            $admin = $statement->fetch();

            if (
                $admin &&
                $admin['status'] === 'active' &&
                password_verify($password, $admin['password_hash'])
            ) {
                session_regenerate_id(true);

                $_SESSION['admin_id'] = (int) $admin['id'];
                $_SESSION['admin_username'] = (string) $admin['username'];

                header('Location: dashboard.php');
                exit;
            }

            $error = 'Invalid admin username or password.';
        } catch (PDOException $exception) {
            error_log(
                'GoferBet admin login error: ' . $exception->getMessage()
            );

            $error = 'Unable to sign in right now.';
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0b0b0f">

    <title>Admin Login · Selam Market</title>

    <link rel="icon" type="image/svg+xml" href="../assets/images/selam-icon.svg">

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link href="../assets/css/style.css?v=selam-5" rel="stylesheet">
    <link href="../assets/css/tiktok-app.css?v=market-4" rel="stylesheet">
    <link href="../assets/css/tiktok-admin.css?v=market-2" rel="stylesheet">
</head>

<body>

<main class="auth-wrap page-enter">

    <?php
    $appRoot = '../';
    $brandHref = '../';
    require __DIR__ . '/../includes/brand.php';
    ?>

    <section class="auth-panel">

        <p class="eyebrow mb-2">Operations</p>

        <h1 class="h3 mb-2">
            Admin sign in
        </h1>

        <p class="text-secondary mb-4">
            Sign in to manage Selam.
        </p>

        <?php if ($error !== ''): ?>
            <div class="alert alert-danger d-flex align-items-center gap-2 mb-4">
                <i class="bi bi-exclamation-circle"></i>
                <span>
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </span>
            </div>
        <?php endif; ?>

        <form method="post" autocomplete="on">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= htmlspecialchars(csrf_token(), ENT_QUOTES, 'UTF-8') ?>"
            >

            <div class="mb-3">
                <label
                    class="form-label"
                    for="admin_username"
                >
                    Admin username
                </label>

                <input
                    class="form-control"
                    id="admin_username"
                    name="username"
                    type="text"
                    value="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>"
                    autocomplete="username"
                    required
                    autofocus
                >
            </div>

            <div class="mb-4">
                <label
                    class="form-label"
                    for="admin_password"
                >
                    Password
                </label>

                <input
                    class="form-control"
                    id="admin_password"
                    name="password"
                    type="password"
                    autocomplete="current-password"
                    required
                >
            </div>

            <button
                class="btn btn-primary w-100"
                type="submit"
            >
                <i class="bi bi-shield-lock me-1" aria-hidden="true"></i>
                Admin Login
            </button>

        </form>

        <a
            class="btn btn-secondary w-100 mt-3"
            href="../"
        >
            <i class="bi bi-arrow-left me-1"></i>
            Return to Selam
        </a>

    </section>

</main>

<script src="../assets/js/app.js?v=feedback-2"></script>
</body>
</html>