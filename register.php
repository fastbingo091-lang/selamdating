<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';
require_once __DIR__ . '/includes/telegram.php';

start_app_session();

if (isset($_SESSION['user_id'])) {
    header('Location: products.php');
    exit;
}

$error = '';
$username = '';
$phoneInputValue = '';
$referralCodeInput = $_SERVER['REQUEST_METHOD'] === 'POST'
    ? ($_POST['referral_code'] ?? '')
    : ($_GET['ref'] ?? '');
$referralCode = is_string($referralCodeInput)
    ? strtoupper(trim($referralCodeInput))
    : '';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {

    $submittedToken = $_POST['csrf_token'] ?? null;
    $usernameInput = $_POST['username'] ?? '';
    $phoneInput = $_POST['phone'] ?? '';
    $passwordInput = $_POST['password'] ?? '';
    $confirmInput = $_POST['password_confirm'] ?? '';
    $referralCodeInput = $_POST['referral_code'] ?? '';
    $telegramInitDataInput = $_POST['telegram_init_data'] ?? '';

    $username = is_string($usernameInput)
        ? trim($usernameInput)
        : '';

    $phoneInputValue = is_string($phoneInput)
        ? trim($phoneInput)
        : '';

    $phone = normalize_phone_number($phoneInputValue);

    $password = is_string($passwordInput)
        ? $passwordInput
        : '';

    $passwordConfirm = is_string($confirmInput)
        ? $confirmInput
        : '';
    $referralCode = is_string($referralCodeInput)
        ? strtoupper(trim($referralCodeInput))
        : '';

    $telegramInitData = is_string($telegramInitDataInput)
        ? $telegramInitDataInput
        : '';

    $telegramUser = null;

    /*
     * CSRF
     */
    if (!csrf_token_is_valid($submittedToken)) {

        $error = 'Your form expired. Please reload the page and try again.';

    } else {

        /*
         * Verify Telegram Mini App data when available.
         */
        try {

            $telegramUser = verified_telegram_user($telegramInitData);

        } catch (RuntimeException $exception) {

            $error = $exception->getMessage();
        }
    }

    /*
     * Use verified Telegram username when available.
     */
    if (
        $error === '' &&
        $telegramUser !== null &&
        $telegramUser['username'] !== null
    ) {
        $username = $telegramUser['username'];
    }

    /*
     * Username validation
     */
    if (
        $error === '' &&
        !preg_match('/\A[A-Za-z0-9_]{3,50}\z/', $username)
    ) {

        $error =
            'Username must be 3 to 50 characters and use only letters, numbers, or underscores.';

    }

    /*
     * Phone validation
     */
    elseif (
        $error === '' &&
        $phone === null
    ) {

        $error =
            'Enter a phone number in international format, for example +251912345678.';

    }

    /*
     * Password validation
     */
    elseif (
        $error === '' &&
        !preg_match('/^[A-Za-z0-9]{6}$/', $password)
    ) {

        $error =
            'Password must be exactly 6 letters or numbers.';

    }

    /*
     * Password confirmation
     */
    elseif (
        $error === '' &&
        $password !== $passwordConfirm
    ) {

        $error = 'The passwords do not match.';
    }

    /*
     * Create account
     */
    if ($error === '') {

        $connection = null;

        try {

            $connection = database();

            $connection->beginTransaction();
            $referrerId = null;
            if ($referralCode !== '') {
                $referrerQuery = $connection->prepare(
                    'SELECT id FROM users WHERE referral_code = ? AND status = "active" LIMIT 1'
                );
                $referrerQuery->execute([$referralCode]);
                $referrerId = $referrerQuery->fetchColumn();
                if ($referrerId === false) {
                    throw new RuntimeException('That referral code is not valid.');
                }
            }

            /*
             * Create user.
             */
            $newReferralCode = 'SELAM' . strtoupper(bin2hex(random_bytes(6)));
            $userInsert = $connection->prepare(
                'INSERT INTO users
                (username, phone, email, password_hash, telegram_user_id, telegram_username, referral_code, referred_by_user_id)
                VALUES
                (:username, :phone, NULL, :password_hash, :telegram_user_id, :telegram_username, :referral_code, :referred_by_user_id)'
            );

            $userInsert->execute([
                'username' => $username,
                'phone' => $phone,
                'password_hash' => password_hash(
                    $password,
                    PASSWORD_DEFAULT
                ),
                'telegram_user_id' =>
                    $telegramUser['id'] ?? null,
                'telegram_username' =>
                    $telegramUser['username'] ?? null,
                'referral_code' => $newReferralCode,
                'referred_by_user_id' => $referrerId,
            ]);
            $newUserId = (int) $connection->lastInsertId();

            /*
             * Create wallet.
             *
             * New accounts use the same currency shown in the wallet UI.
             */
            $walletInsert = $connection->prepare(
                'INSERT INTO wallets
                (user_id, currency, balance, locked_balance)
                VALUES
                (:user_id, :currency, 0.00000000, 0.00000000)'
            );

            $walletInsert->execute([
                'user_id' => $newUserId,
                'currency' => 'ETB',
            ]);

            $welcomeInsert = $connection->prepare("
                INSERT INTO user_notifications (recipient_user_id, title, message)
                VALUES (?, 'Welcome to Selam Market', ?)
            ");
            $welcomeInsert->execute([
                $newUserId,
                'Welcome to Selam Market! Your account is ready. Browse products, track your purchases in My Products, and claim eligible daily profit. Keep this welcome message in your inbox as a guide to getting started.',
            ]);

            $connection->commit();

            header('Location: login.php?registered=1');
            exit;

        } catch (RuntimeException $exception) {

            if ($connection instanceof PDO && $connection->inTransaction()) {
                $connection->rollBack();
            }
            $error = $exception->getMessage();

        } catch (PDOException $exception) {

            if (
                $connection instanceof PDO &&
                $connection->inTransaction()
            ) {
                $connection->rollBack();
            }

            if ($exception->getCode() === '23000') {

                $error =
                    'That username, phone number, or Telegram account is already in use.';

            } else {

                error_log(
                    sprintf(
                        'GoferBet registration database operation failed [%s]: %s',
                        (string) $exception->getCode(),
                        $exception->getMessage()
                    )
                );

                $error =
                    'We could not create your account right now. Please try again.';
            }
        }
    }
}
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

    <title>Join Selam | Selam Market</title>

    <link rel="icon" type="image/svg+xml" href="assets/images/selam-icon.svg">

    <!-- Bootstrap -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <!-- Icons -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css"
        rel="stylesheet"
    >

    <!-- Fonts -->
    <link
        href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Noto+Sans+Ethiopic:wght@400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <!-- Main CSS -->
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
            --gb-primary: #20e68a;
            --gb-primary-hover: #13c977;
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
         * Main mobile-first layout
         */
        .register-page {
            min-height: 100dvh;

            display: flex;
            flex-direction: column;
            justify-content: center;

            padding:
                24px
                20px
                calc(24px + env(safe-area-inset-bottom));
        }

        .register-container {
            width: 100%;
            max-width: 430px;
            margin: 0 auto;
        }

        /*
         * Logo
         */
        .register-brand {
            display: flex;
            justify-content: center;
            align-items: center;

            margin-bottom: 28px;
        }

        .register-brand img {
            display: block;

            width: auto;
            height: 58px;
            max-width: 260px;

            object-fit: contain;
        }

        /*
         * Card
         */
        .register-card {
            background: var(--gb-card);

            border: 1px solid var(--gb-border);
            border-radius: 20px;

            padding: 24px;

            box-shadow:
                0 20px 50px rgba(0, 0, 0, 0.25);
        }

        /*
         * Heading
         */
        .register-heading {
            margin-bottom: 24px;
        }

        .register-heading h1 {
            margin: 0 0 8px;

            font-size: 26px;
            line-height: 1.2;
            font-weight: 700;

            letter-spacing: -0.4px;
        }

        .register-heading p {
            margin: 0;

            color: var(--gb-muted);

            font-size: 14px;
            line-height: 1.6;
        }

        /*
         * Labels
         */
        .register-card .form-label {
            color: var(--gb-text);

            font-size: 14px;
            font-weight: 600;

            margin-bottom: 8px;
        }

        /*
         * Inputs
         */
        .register-card .form-control {
            min-height: 52px;

            padding: 12px 14px;

            color: var(--gb-text);
            background: #0d100e;

            border: 1px solid var(--gb-border);
            border-radius: 12px;

            font-size: 15px;
        }

        .register-card .form-control::placeholder {
            color: #89938d;
        }

        .register-card .form-control:focus {
            color: var(--gb-text);
            background: #0d100e;

            border-color: var(--gb-primary);

            box-shadow:
                0 0 0 3px rgba(32, 230, 138, 0.16);
        }

        /*
         * Password control
         */
        .password-control {
            position: relative;
        }

        .password-control .form-control {
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
            border-radius: 10px;

            background: transparent;
            color: var(--gb-muted);
        }

        .password-toggle:hover,
        .password-toggle:focus {
            color: var(--gb-text);
            background: rgba(255,255,255,0.07);
        }

        /*
         * Helper text
         */
        .register-card .form-text {
            color: var(--gb-muted);
            font-size: 12px;
            line-height: 1.5;
            margin-top: 7px;
        }

        /*
         * Password strength
         */
        .password-progress {
            height: 4px;

            margin-top: 8px;

            overflow: hidden;

            background: #252b27;

            border-radius: 99px;
        }

        .password-progress .progress-bar {
            width: 0;
            height: 100%;

            transition:
                width 0.2s ease,
                background 0.2s ease;

            background: var(--gb-danger);
        }

        /*
         * Create account button
         */
        .register-button {
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

        .register-button:hover {
            background: var(--gb-primary-hover);
            color: #07120c;
        }

        .register-button:active {
            transform: scale(0.99);
        }

        /*
         * Alert
         */
        .register-alert {
            border-radius: 12px;

            font-size: 13px;
            line-height: 1.5;
        }

        /*
         * Login link
         */
        .login-text {
            margin: 22px 0 0;

            text-align: center;

            color: var(--gb-muted);

            font-size: 14px;
        }

        .login-text a {
            color: var(--gb-primary);

            font-weight: 600;

            text-decoration: none;
        }

        .login-text a:hover {
            color: var(--gb-primary);
            text-decoration: underline;
        }

        /*
         * Small footer
         */
        .register-footer {
            margin-top: 24px;

            text-align: center;

            color: var(--gb-muted);

            font-size: 12px;
        }

        /*
         * Tablet / desktop
         */
        @media (min-width: 768px) {

            .register-page {
                padding: 40px 24px;
            }

            .register-card {
                padding: 32px;
            }

            .register-brand {
                margin-bottom: 34px;
            }

            .register-brand img {
                height: 64px;
            }
        }

        /*
         * Small phones
         */
        @media (max-width: 360px) {

            .register-page {
                padding-left: 14px;
                padding-right: 14px;
            }

            .register-card {
                padding: 20px;
                border-radius: 18px;
            }

            .register-heading h1 {
                font-size: 24px;
            }

            .register-brand img {
                height: 50px;
                max-width: 220px;
            }
        }

    </style>

</head>

<body>

<main class="register-page">

    <div class="register-container">

        <!-- Logo -->
        <div class="register-brand">

            <?php
            $brandHref = './';
            require __DIR__ . '/includes/brand.php';
            ?>

        </div>


        <!-- Registration card -->
        <section
            class="register-card"
            aria-labelledby="register-title"
        >

            <div class="register-heading">

                <h1 id="register-title">
                    Join Selam
                </h1>

                <p>
                    Set up your account in a few simple steps.
                </p>

            </div>


            <?php if ($error !== ''): ?>

                <div
                    class="alert alert-danger register-alert"
                    role="alert"
                >
                    <?= html_escape($error) ?>
                </div>

            <?php endif; ?>


            <form
                method="post"
                action="register.php"
                autocomplete="on"
                data-loading-form
                novalidate
            >

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= html_escape(csrf_token()) ?>"
                >

                <!-- Telegram -->
                <input
                    type="hidden"
                    id="telegram_init_data"
                    name="telegram_init_data"
                    value=""
                >


                <!-- Username -->

                <div class="mb-3">

                    <label
                        class="form-label"
                        for="username"
                    >
                        Username
                    </label>

                    <input
                        class="form-control"
                        id="username"
                        name="username"
                        value="<?= html_escape($username) ?>"
                        minlength="3"
                        maxlength="50"
                        pattern="[A-Za-z0-9_]+"
                        autocomplete="username"
                        autocapitalize="none"
                        spellcheck="false"
                        placeholder="Choose a username"
                        required
                    >

                    <div
                        class="form-text"
                        id="telegram_username_note"
                    >
                        3–50 characters using letters, numbers, or underscores.
                    </div>

                </div>


                <!-- Phone -->

                <div class="mb-3">

                    <label
                        class="form-label"
                        for="phone"
                    >
                        Phone number
                    </label>

                    <input
                        class="form-control"
                        id="phone"
                        name="phone"
                        type="tel"
                        value="<?= html_escape($phoneInputValue) ?>"
                        placeholder="+251912345678"
                        pattern="\+[1-9][0-9]{7,14}"
                        maxlength="20"
                        autocomplete="tel"
                        inputmode="tel"
                        required
                    >

                    <div class="form-text">
                        Include your country code.
                    </div>

                </div>

                <div class="mb-3">
                    <label class="form-label" for="referral_code">Referral code <span class="text-secondary">Optional</span></label>
                    <input
                        class="form-control text-uppercase"
                        id="referral_code"
                        name="referral_code"
                        value="<?= html_escape($referralCode) ?>"
                        maxlength="24"
                        autocomplete="off"
                    >
                    <div class="form-text">A team member can share a referral link or code with you.</div>
                </div>

                <!-- Password -->

                <div class="mb-3">

                    <label
                        class="form-label"
                        for="password"
                    >
                        Password
                    </label>

                    <div class="password-control">

                        <input
                            class="form-control"
                            id="password"
                            name="password"
                            type="password"
                            minlength="6"
                            maxlength="6"
                            pattern="[A-Za-z0-9]{6}"
                            autocomplete="new-password"
                            data-password-strength
                            placeholder="Create a 6-character password"
                            required
                        >

                        <button
                            class="password-toggle"
                            type="button"
                            data-password-toggle
                            aria-controls="password"
                            aria-label="Show password"
                        >
                            <i
                                class="bi bi-eye"
                                aria-hidden="true"
                            ></i>
                        </button>

                    </div>

                    <div
                        class="form-text"
                        data-strength-label
                    >
                        Use exactly 6 letters or numbers.
                    </div>

                    <div
                        class="password-progress"
                        aria-hidden="true"
                    >
                        <div
                            class="progress-bar"
                            data-strength-meter
                        ></div>
                    </div>

                </div>


                <!-- Confirm password -->

                <div class="mb-4">

                    <label
                        class="form-label"
                        for="password_confirm"
                    >
                        Confirm password
                    </label>

                    <div class="password-control">

                        <input
                            class="form-control"
                            id="password_confirm"
                            name="password_confirm"
                            type="password"
                            minlength="6"
                            maxlength="6"
                            pattern="[A-Za-z0-9]{6}"
                            autocomplete="new-password"
                            placeholder="Enter your password again"
                            required
                        >

                        <button
                            class="password-toggle"
                            type="button"
                            data-password-toggle
                            aria-controls="password_confirm"
                            aria-label="Show password"
                        >
                            <i
                                class="bi bi-eye"
                                aria-hidden="true"
                            ></i>
                        </button>

                    </div>

                </div>


                <!-- Submit -->

                <button
                    class="btn register-button w-100"
                    type="submit"
                    data-loading-button
                >

                    <i
                        class="bi bi-person-plus"
                        aria-hidden="true"
                    ></i>

                    <span>Create account</span>

                </button>

            </form>


            <!-- Login -->

            <p class="login-text">

                Already have an account?

                <a href="login.php">
                    Sign in
                </a>

            </p>

        </section>


        <!-- Footer -->

        <div class="register-footer">
            © <?= date('Y') ?> Selam Market
        </div>

    </div>

</main>


<!-- Telegram Mini App -->
<script src="https://telegram.org/js/telegram-web-app.js"></script>

<script>

(() => {

    const webApp = window.Telegram?.WebApp;

    if (!webApp) {
        return;
    }

    webApp.ready();

    const initDataField =
        document.getElementById('telegram_init_data');

    const usernameField =
        document.getElementById('username');

    const usernameNote =
        document.getElementById('telegram_username_note');

    const telegramUsername =
        webApp.initDataUnsafe?.user?.username;

    if (initDataField) {
        initDataField.value = webApp.initData || '';
    }

    /*
     * If opened inside Telegram,
     * use the verified Telegram username.
     */
    if (
        telegramUsername &&
        usernameField
    ) {

        usernameField.value =
            telegramUsername;

        usernameField.readOnly = true;

        if (usernameNote) {

            usernameNote.textContent =
                'Your Telegram username will be used for your account.';
        }
    }

    const form =
        document.querySelector('form');

    if (form) {

        form.addEventListener(
            'submit',
            () => {

                if (initDataField) {
                    initDataField.value =
                        webApp.initData || '';
                }

            }
        );
    }

})();

</script>


<!-- Password controls -->
<script>

document.addEventListener(
    'DOMContentLoaded',
    function () {

        /*
         * Show/hide password buttons
         */
        document
            .querySelectorAll('[data-password-toggle]')
            .forEach(function (button) {

                button.addEventListener(
                    'click',
                    function () {

                        const targetId =
                            button.getAttribute(
                                'aria-controls'
                            );

                        const input =
                            document.getElementById(
                                targetId
                            );

                        const icon =
                            button.querySelector('i');

                        if (!input) {
                            return;
                        }

                        if (
                            input.type === 'password'
                        ) {

                            input.type = 'text';

                            button.setAttribute(
                                'aria-label',
                                'Hide password'
                            );

                            if (icon) {

                                icon.classList.remove(
                                    'bi-eye'
                                );

                                icon.classList.add(
                                    'bi-eye-slash'
                                );
                            }

                        } else {

                            input.type = 'password';

                            button.setAttribute(
                                'aria-label',
                                'Show password'
                            );

                            if (icon) {

                                icon.classList.remove(
                                    'bi-eye-slash'
                                );

                                icon.classList.add(
                                    'bi-eye'
                                );
                            }
                        }
                    }
                );

            });


        /*
         * Password strength / validation indicator
         */
        const password =
            document.querySelector(
                '[data-password-strength]'
            );

        const meter =
            document.querySelector(
                '[data-strength-meter]'
            );

        const label =
            document.querySelector(
                '[data-strength-label]'
            );

        if (password && meter && label) {

            password.addEventListener(
                'input',
                function () {

                    const value =
                        password.value;

                    const valid =
                        /^[A-Za-z0-9]{6}$/.test(value);

                    if (value.length === 0) {

                        meter.style.width = '0%';

                        label.textContent =
                            'Use exactly 6 letters or numbers.';

                        return;
                    }

                    if (valid) {

                        meter.style.width = '100%';

                        meter.style.background =
                            '#22C55E';

                        label.textContent =
                            'Password format is valid.';

                    } else {

                        const progress =
                            Math.min(
                                (value.length / 6) * 100,
                                100
                            );

                        meter.style.width =
                            progress + '%';

                        meter.style.background =
                            '#EF4444';

                        label.textContent =
                            'Use exactly 6 letters or numbers.';
                    }

                }
            );
        }

    }
);

</script>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script src="assets/js/app.js?v=feedback-2"></script>

</body>

</html>