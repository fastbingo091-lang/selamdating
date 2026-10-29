<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';

start_app_session();

/*
 * Continue profile setup for authenticated users before opening the dashboard.
 */
if (isset($_SESSION['user_id'])) {
header('Location: products.php');
    exit;
}

$error = '';
$identifier = '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedToken = $_POST['csrf_token'] ?? null;
    $identifierInput = $_POST['identifier'] ?? '';
    $passwordInput = $_POST['password'] ?? '';

    $identifier = is_string($identifierInput)
        ? trim($identifierInput)
        : '';

    $password = is_string($passwordInput)
        ? $passwordInput
        : '';

    /*
     * CSRF validation
     */
    if (!csrf_token_is_valid($submittedToken)) {
        $error = 'Your form expired. Please reload the page and try again.';
    }

    /*
     * Rate limiting
     */
    elseif (login_is_rate_limited()) {
        $error = 'Too many login attempts. Please try again in 15 minutes.';
    }

    /*
     * Basic validation
     */
    elseif (
        $identifier === '' ||
        $password === '' ||
        strlen($identifier) > 254
    ) {
        record_login_failure();
        $error = 'Please enter your username or phone number and password.';
    }

    /*
     * Password policy
     *
     * Exactly 6 characters.
     * Letters and numbers only.
     */
    elseif (!preg_match('/^[A-Za-z0-9]{6}$/', $password)) {
        record_login_failure();
        $error = 'Password must be exactly 6 letters or numbers.';
    }

    else {
        try {
            $connection = database();

            /*
             * Determine whether the identifier is a phone number
             * or username.
             */
            $phoneIdentifier = normalize_phone_number($identifier);

            if ($phoneIdentifier !== null) {
                $lookupColumn = 'phone';
                $lookupValue = $phoneIdentifier;
            } else {
                $lookupColumn = 'username';
                $lookupValue = $identifier;
            }

            $lookup = $connection->prepare(
                "SELECT id, username, password_hash, status
                 FROM users
                 WHERE {$lookupColumn} = :identifier
                 LIMIT 1"
            );

            $lookup->execute([
                'identifier' => $lookupValue
            ]);

            $user = $lookup->fetch();

            /*
             * Verify password.
             */
            if (
                $user &&
                isset($user['password_hash']) &&
                password_verify($password, (string) $user['password_hash'])
            ) {

                /*
                 * Check account status.
                 */
                if (($user['status'] ?? '') !== 'active') {
                    record_login_failure();
                    $error = 'Your account is inactive. Please contact support.';
                } else {

                    /*
                     * Prevent session fixation.
                     */
                    session_regenerate_id(true);

                    $_SESSION['user_id'] = (int) $user['id'];
                    $_SESSION['username'] = (string) $user['username'];

                    unset($_SESSION['login_failures']);

                    /*
                     * Login successful.
                     */
                    header('Location: products.php');
                    exit;
                }

            } else {
                record_login_failure();

                /*
                 * Do not reveal whether the username exists.
                 */
                $error = 'Invalid username or password.';
            }

        } catch (PDOException $exception) {

            /*
             * Keep database errors out of the UI.
             * Log the real error for development/server debugging.
             */
            error_log(
                sprintf(
                    'GoferBet login PDO failure [%s]: %s',
                    (string) $exception->getCode(),
                    $exception->getMessage()
                )
            );

            $error = 'We could not sign you in right now. Please try again.';
        }
    }
}

$registered =
    isset($_GET['registered']) &&
    $_GET['registered'] === '1';

$loggedOut =
    isset($_GET['logged_out']) &&
    $_GET['logged_out'] === '1';

$rateLimited = login_is_rate_limited();
?>
<!doctype html>
<html lang="en">

<head>

    <meta charset="utf-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1, viewport-fit=cover"
    >

    <meta
        name="theme-color"
        content="#090b0a"
    >

    <title>Sign in | Selam Market</title>

    <link rel="icon" type="image/svg+xml" href="assets/images/selam-icon.svg">

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Bootstrap Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <!-- Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Main site CSS -->
    <link
        href="assets/css/style.css?v=selam-5"
        rel="stylesheet"
    >
    <link
        href="assets/css/tiktok-app.css?v=market-4"
        rel="stylesheet"
    >

    <style>

        :root {
            --gb-bg: #090b0a;
            --gb-card: #141715;
            --gb-card-light: #1c211e;
            --gb-primary: #20e68a;
            --gb-primary-hover: #13c977;
            --gb-blue: #20e68a;
            --gb-text: #f5f7f6;
            --gb-muted: #aab3ae;
            --gb-border: #292f2b;
            --gb-danger: #EF4444;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            min-height: 100%;
            margin: 0;
        }

        body {
            background: var(--gb-bg);
            color: var(--gb-text);
            font-family:
                "DM Sans",
                "Noto Sans Ethiopic",
                sans-serif;
        }

        /*
         * Main mobile-first authentication wrapper
         */
        .login-page {
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding:
                24px
                20px
                calc(24px + env(safe-area-inset-bottom));
        }

        /*
         * Login container
         */
        .login-container {
            width: 100%;
            max-width: 430px;
            margin: 0 auto;
        }

        /*
         * Brand
         */
        .login-brand {
            display: flex;
            justify-content: center;
            align-items: center;
            margin-bottom: 30px;
        }

        .login-brand img {
            display: block;
            width: auto;
            height: 58px;
            max-width: 260px;
            object-fit: contain;
        }

        /*
         * Login card
         */
        .login-card {
            background: var(--gb-card);
            border: 1px solid var(--gb-border);
            border-radius: 20px;
            padding: 24px;
            box-shadow:
                0 20px 50px rgba(0, 0, 0, 0.25);
        }

        .login-heading {
            margin-bottom: 24px;
        }

        .login-heading h1 {
            margin: 0 0 8px;
            font-size: 26px;
            line-height: 1.2;
            font-weight: 700;
            letter-spacing: -0.4px;
        }

        .login-heading p {
            margin: 0;
            color: var(--gb-muted);
            font-size: 14px;
            line-height: 1.6;
        }

        /*
         * Form labels
         */
        .login-card .form-label {
            color: var(--gb-text);
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        /*
         * Inputs
         */
        .login-card .form-control {
            min-height: 52px;
            padding: 12px 14px;
            color: var(--gb-text);
            background: #0d100e;
            border: 1px solid var(--gb-border);
            border-radius: 12px;
            font-size: 15px;
        }

        .login-card .form-control::placeholder {
            color: #89938d;
        }

        .login-card .form-control:focus {
            color: var(--gb-text);
            background: #0d100e;
            border-color: var(--gb-primary);
            box-shadow:
                0 0 0 3px rgba(32, 230, 138, 0.16);
        }

        /*
         * Password wrapper
         */
        .password-wrapper {
            position: relative;
        }

        .password-wrapper .form-control {
            padding-right: 52px;
        }

        .password-toggle {
            position: absolute;
            top: 50%;
            right: 8px;
            transform: translateY(-50%);

            width: 40px;
            height: 40px;

            display: flex;
            align-items: center;
            justify-content: center;

            border: 0;
            background: transparent;
            color: var(--gb-muted);
            border-radius: 10px;
        }

        .password-toggle:hover,
        .password-toggle:focus {
            color: var(--gb-text);
            background: rgba(255, 255, 255, 0.07);
        }

        /*
         * Sign in button
         */
        .login-button {
            min-height: 52px;
            border: 0;
            border-radius: 12px;
            background: var(--gb-primary);
            color: #07120c;
            font-size: 15px;
            font-weight: 700;
            transition:
                background 0.2s ease,
                transform 0.15s ease;
        }

        .login-button:hover {
            background: var(--gb-primary-hover);
            color: #07120c;
        }

        .login-button:active {
            transform: scale(0.99);
        }

        .login-button:disabled {
            opacity: 0.65;
        }

        /*
         * Alerts
         */
        .login-alert {
            border-radius: 12px;
            border: 1px solid transparent;
            font-size: 13px;
            line-height: 1.5;
        }

        /*
         * Register link
         */
        .register-text {
            margin: 22px 0 0;
            text-align: center;
            color: var(--gb-muted);
            font-size: 14px;
        }

        .register-text a {
            color: var(--gb-primary);
            font-weight: 600;
            text-decoration: none;
        }

        .register-text a:hover {
            color: var(--gb-primary);
            text-decoration: underline;
        }

        /*
         * Small footer
         */
        .login-footer {
            margin-top: 24px;
            text-align: center;
            color: var(--gb-muted);
            font-size: 12px;
        }

        /*
         * Desktop
         */
        @media (min-width: 768px) {

            .login-page {
                padding: 40px 24px;
            }

            .login-card {
                padding: 32px;
            }

            .login-brand {
                margin-bottom: 34px;
            }

            .login-brand img {
                height: 64px;
            }
        }

        /*
         * Very small phones
         */
        @media (max-width: 360px) {

            .login-page {
                padding-left: 14px;
                padding-right: 14px;
            }

            .login-card {
                padding: 20px;
                border-radius: 18px;
            }

            .login-heading h1 {
                font-size: 24px;
            }

            .login-brand img {
                height: 50px;
                max-width: 220px;
            }
        }

    </style>

</head>

<body>

<main class="login-page">

    <div class="login-container">

        <!-- Logo -->
        <div class="login-brand">

            <?php
            $brandHref = './';
            require __DIR__ . '/includes/brand.php';
            ?>

        </div>

        <!-- Login card -->
        <section
            class="login-card"
            aria-labelledby="login-title"
        >

            <div class="login-heading">

                <h1 id="login-title">
                    Welcome to Selam
                </h1>

                <p>
                    Sign in and continue your journey.
                </p>

            </div>


            <!-- Success messages -->

            <?php if ($registered): ?>

                <div
                    class="alert alert-success login-alert"
                    role="status"
                >
                    Welcome to Selam Market! Your account has been created. You can now sign in. Your welcome message will also remain in your notifications inbox.
                </div>

            <?php endif; ?>


            <?php if ($loggedOut): ?>

                <div
                    class="alert alert-success login-alert"
                    role="status"
                >
                    You have been signed out successfully.
                </div>

            <?php endif; ?>


            <!-- Error -->

            <?php if ($error !== ''): ?>

                <div
                    class="alert alert-danger login-alert"
                    role="alert"
                >
                    <?= html_escape($error) ?>
                </div>

            <?php endif; ?>


            <?php if ($rateLimited): ?>

                <div
                    class="alert alert-warning login-alert"
                    role="alert"
                >
                    Too many attempts. Please try again in 15 minutes.
                </div>

            <?php endif; ?>


            <!-- Login form -->

            <form
                method="post"
                action="login.php"
                autocomplete="on"
                data-loading-form
                novalidate
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= html_escape(csrf_token()) ?>"
                >


                <!-- Identifier -->

                <div class="mb-3">

                    <label
                        class="form-label"
                        for="identifier"
                    >
                        Username or phone number
                    </label>

                    <input
                        class="form-control"
                        id="identifier"
                        name="identifier"
                        value="<?= html_escape($identifier) ?>"
                        maxlength="254"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                        placeholder="Enter your username or phone"
                        required
                    >

                </div>


                <!-- Password -->

                <div class="mb-4">

                    <label
                        class="form-label"
                        for="password"
                    >
                        Password
                    </label>

                    <div class="password-wrapper">

                        <input
                            class="form-control"
                            id="password"
                            name="password"
                            type="password"
                            minlength="6"
                            maxlength="6"
                            pattern="[A-Za-z0-9]{6}"
                            autocomplete="current-password"
                            placeholder="6 letters or numbers"
                            inputmode="text"
                            required
                        >

                        <button
                            class="password-toggle"
                            type="button"
                            id="togglePassword"
                            aria-label="Show password"
                            title="Show password"
                        >
                            <i
                                class="bi bi-eye"
                                aria-hidden="true"
                            ></i>
                        </button>

                    </div>

                    <div
                        class="form-text"
                        style="color:#64748B; font-size:12px; margin-top:7px;"
                    >
                        Use exactly 6 letters or numbers.
                    </div>

                </div>


                <!-- Submit -->

                <button
                    class="btn login-button w-100"
                    type="submit"
                    data-loading-button
                    <?= $rateLimited ? 'disabled' : '' ?>
                >

                    <i
                        class="bi bi-box-arrow-in-right"
                        aria-hidden="true"
                    ></i>

                    <span>Sign in</span>

                </button>

            </form>


            <!-- Register -->

            <p class="register-text">

                Don't have an account?

                <a href="register.php">
                    Create an account
                </a>

            </p>

        </section>


        <!-- Small footer -->

        <div class="login-footer">
            © <?= date('Y') ?> Selam Market
        </div>

    </div>

</main>


<script>
/*
 * Password visibility toggle
 */
document.addEventListener('DOMContentLoaded', function () {

    const passwordInput =
        document.getElementById('password');

    const toggleButton =
        document.getElementById('togglePassword');

    if (passwordInput && toggleButton) {

        toggleButton.addEventListener('click', function () {

            const icon =
                toggleButton.querySelector('i');

            if (passwordInput.type === 'password') {

                passwordInput.type = 'text';

                toggleButton.setAttribute(
                    'aria-label',
                    'Hide password'
                );

                toggleButton.setAttribute(
                    'title',
                    'Hide password'
                );

                if (icon) {
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                }

            } else {

                passwordInput.type = 'password';

                toggleButton.setAttribute(
                    'aria-label',
                    'Show password'
                );

                toggleButton.setAttribute(
                    'title',
                    'Show password'
                );

                if (icon) {
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }
        });
    }

});
</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/app.js?v=feedback-2"></script>

</body>
</html>