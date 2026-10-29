<?php

declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: auth/login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$db = database();
$error = '';
$success = '';
$userQuery = $db->prepare('SELECT username, phone FROM users WHERE id = ? LIMIT 1');
$userQuery->execute([$userId]);
$user = $userQuery->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    destroy_app_session();
    header('Location: login.php');
    exit;
}

$username = (string) $user['username'];
$phone = (string) $user['phone'];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $usernameInput = $_POST['username'] ?? null;
    $phoneInput = $_POST['phone'] ?? null;
    $username = is_string($usernameInput) ? trim($usernameInput) : '';
    $phoneInputValue = is_string($phoneInput) ? trim($phoneInput) : '';
    $normalizedPhone = normalize_phone_number($phoneInputValue);

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Reload the page and try again.';
    } elseif (preg_match('/\A[A-Za-z0-9_]{3,50}\z/', $username) !== 1) {
        $error = 'Username must be 3–50 characters using letters, numbers, or underscores.';
    } elseif ($normalizedPhone === null) {
        $error = 'Enter a phone number in international format, for example +251912345678.';
    } else {
        try {
            $update = $db->prepare('UPDATE users SET username = ?, phone = ? WHERE id = ?');
            $update->execute([$username, $normalizedPhone, $userId]);
            $_SESSION['username'] = $username;
            $phone = $normalizedPhone;
            $success = 'Your profile has been updated.';
        } catch (PDOException $exception) {
            if ($exception->getCode() === '23000') {
                $error = 'That username or phone number is already being used.';
            } else {
                error_log(sprintf('Selam profile update failed [%s]: %s', (string) $exception->getCode(), $exception->getMessage()));
                $error = 'We could not update your profile. Please try again.';
            }
        }
    }
}

$appRoot = '';
$pageTitle = 'Edit profile';
$pageDescription = 'Update your username and phone number.';
$activeNav = 'profile';
require __DIR__ . '/includes/app_header.php';
?>
<section class="data-panel profile-edit-panel">
    <div class="data-panel-header"><h2>Your account details</h2></div>
    <form method="post" class="p-4" data-loading-form>
        <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
        <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
        <?php if ($success !== ''): ?><div class="alert alert-success" role="status"><?= html_escape($success) ?></div><?php endif; ?>
        <div class="mb-3">
            <label class="form-label" for="profile-username">Username</label>
            <input class="form-control" id="profile-username" name="username" value="<?= html_escape($username) ?>" minlength="3" maxlength="50" pattern="[A-Za-z0-9_]{3,50}" autocomplete="username" required>
        </div>
        <div class="mb-4">
            <label class="form-label" for="profile-phone">Phone number</label>
            <input class="form-control" id="profile-phone" name="phone" type="tel" value="<?= html_escape($phone) ?>" maxlength="20" autocomplete="tel" placeholder="+251912345678" required>
            <div class="form-text">Use international format. For example, +251912345678.</div>
        </div>
        <button class="btn btn-primary w-100" type="submit" data-loading-button>Save changes</button>
        <a class="btn btn-outline-light w-100 mt-2" href="team.php">Back to team</a>
    </form>
</section>
<?php require __DIR__ . '/includes/app_footer.php'; ?>
