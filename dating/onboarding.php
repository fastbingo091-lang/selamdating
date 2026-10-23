<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];

try {
    $db = database();
    $existingProfile = $db->prepare('SELECT id FROM dating_profiles WHERE user_id = ? LIMIT 1');
    $existingProfile->execute([$userId]);
    if ($existingProfile->fetchColumn() !== false) {
        header('Location: ../dashboard/index.php');
        exit;
    }
} catch (PDOException $exception) {
    error_log(sprintf('Selam onboarding profile check failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
    http_response_code(503);
    exit('Dating profile setup is not ready. Apply database/migrations/20261023_dating_profiles.sql and try again.');
}

$error = '';
$gender = '';
$birthDate = '';
$displayName = trim((string) ($_SESSION['username'] ?? ''));
$city = '';
$bio = '';
$preferences = [];

$genderOptions = [
    'woman' => 'Woman',
    'man' => 'Man',
    'non_binary' => 'Non-binary',
    'prefer_not_to_say' => 'Prefer not to say',
];
$preferenceOptions = [
    'women' => 'Women',
    'men' => 'Men',
    'everyone' => 'Everyone',
];

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Reload the page and try again.';
    } else {
        $genderInput = $_POST['gender'] ?? '';
        $birthDateInput = $_POST['birth_date'] ?? '';
        $displayNameInput = $_POST['display_name'] ?? '';
        $cityInput = $_POST['city'] ?? '';
        $bioInput = $_POST['bio'] ?? '';
        $preferenceInput = $_POST['preferences'] ?? [];

        $gender = is_string($genderInput) ? $genderInput : '';
        $birthDate = is_string($birthDateInput) ? $birthDateInput : '';
        $displayName = is_string($displayNameInput) ? trim($displayNameInput) : '';
        $city = is_string($cityInput) ? trim($cityInput) : '';
        $bio = is_string($bioInput) ? trim($bioInput) : '';
        $preferences = is_array($preferenceInput)
            ? array_values(array_unique(array_filter(
                $preferenceInput,
                static fn (mixed $value): bool => is_string($value)
            )))
            : [];

        $birthDateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
        $birthDateErrors = DateTimeImmutable::getLastErrors();
        $validBirthDate = $birthDateObject !== false
            && ($birthDateErrors === false || ($birthDateErrors['warning_count'] === 0 && $birthDateErrors['error_count'] === 0))
            && $birthDateObject->format('Y-m-d') === $birthDate;
        $today = new DateTimeImmutable('today');
        $adult = $validBirthDate
            && $birthDateObject <= $today
            && $birthDateObject->diff($today)->y >= 18;

        if (!isset($genderOptions[$gender])) {
            $error = 'Choose one of the available gender options.';
        } elseif (!$adult) {
            $error = 'Selam dating profiles are for adults aged 18 and over. Enter a valid date of birth to continue.';
        } elseif ($displayName === '' || mb_strlen($displayName) > 50) {
            $error = 'Your display name must be between 1 and 50 characters.';
        } elseif (mb_strlen($city) > 80) {
            $error = 'City must be 80 characters or fewer.';
        } elseif (mb_strlen($bio) > 500) {
            $error = 'Your introduction must be 500 characters or fewer.';
        } elseif ($preferences === [] || array_diff($preferences, array_keys($preferenceOptions)) !== []) {
            $error = 'Choose at least one preference for who you would like to meet.';
        } elseif (($_POST['confirm_adult'] ?? '') !== '1') {
            $error = 'Confirm that you are at least 18 years old.';
        } else {
            try {
                $db->beginTransaction();
                $insertProfile = $db->prepare("
                    INSERT INTO dating_profiles (user_id, display_name, birth_date, gender, city, bio, visibility)
                    VALUES (:user_id, :display_name, :birth_date, :gender, :city, :bio, 'private')
                ");
                $insertProfile->execute([
                    'user_id' => $userId,
                    'display_name' => $displayName,
                    'birth_date' => $birthDate,
                    'gender' => $gender,
                    'city' => $city !== '' ? $city : null,
                    'bio' => $bio !== '' ? $bio : null,
                ]);
                $profileId = (int) $db->lastInsertId();
                $insertPreference = $db->prepare(
                    'INSERT INTO dating_profile_preferences (profile_id, preference) VALUES (?, ?)'
                );
                foreach ($preferences as $preference) {
                    $insertPreference->execute([$profileId, $preference]);
                }
                $db->commit();

                header('Location: ../dashboard/index.php');
                exit;
            } catch (PDOException $exception) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log(sprintf('Selam onboarding save failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
                $error = 'We could not save your dating profile right now. Please try again.';
            }
        }
    }
}

$appRoot = '../';
$pageTitle = 'A few things about you';
$pageDescription = 'Set up your private Selam dating profile.';
$activeNav = 'profile';

require __DIR__ . '/../includes/app_header.php';
?>

<section class="dating-form-panel" aria-labelledby="onboarding-heading">
    <div class="dating-form-heading">
        <span class="dating-step-label"><i class="bi bi-heart-fill" aria-hidden="true"></i> YOUR SELAM PROFILE · 1 OF 1</span>
        <h2 id="onboarding-heading">A few things about you</h2>
        <p>This helps prepare your dating experience. Your profile stays private until you opt in to appear in Discover.</p>
    </div>

    <?php if ($error !== ''): ?>
        <div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div>
    <?php endif; ?>

    <form method="post" action="onboarding.php" class="dating-form">
        <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">

        <div class="dating-form-grid">
            <div class="dating-field dating-field-wide">
                <label class="form-label" for="display_name">What should people call you?</label>
                <input class="form-control" id="display_name" name="display_name" maxlength="50" autocomplete="nickname" value="<?= html_escape($displayName) ?>" required>
            </div>

            <div class="dating-field">
                <label class="form-label" for="birth_date">Date of birth</label>
                <input class="form-control" id="birth_date" name="birth_date" type="date" max="<?= html_escape(date('Y-m-d', strtotime('-18 years'))) ?>" value="<?= html_escape($birthDate) ?>" required>
                <small>Your date of birth is private and is not shown on your profile.</small>
            </div>

            <fieldset class="dating-field dating-fieldset">
                <legend class="form-label">How do you describe yourself?</legend>
                <?php foreach ($genderOptions as $value => $label): ?>
                    <label class="dating-choice">
                        <input type="radio" name="gender" value="<?= html_escape($value) ?>" <?= $gender === $value ? 'checked' : '' ?> required>
                        <span><?= html_escape($label) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>

            <div class="dating-field">
                <label class="form-label" for="city">City <span class="dating-optional">Optional</span></label>
                <input class="form-control" id="city" name="city" maxlength="80" autocomplete="address-level2" value="<?= html_escape($city) ?>" placeholder="Your city">
            </div>

            <fieldset class="dating-field dating-fieldset">
                <legend class="form-label">Who would you like to meet?</legend>
                <?php foreach ($preferenceOptions as $value => $label): ?>
                    <label class="dating-choice">
                        <input type="checkbox" name="preferences[]" value="<?= html_escape($value) ?>" <?= in_array($value, $preferences, true) ? 'checked' : '' ?>>
                        <span><?= html_escape($label) ?></span>
                    </label>
                <?php endforeach; ?>
                <small>Select one or more.</small>
            </fieldset>

            <div class="dating-field dating-field-wide">
                <label class="form-label" for="bio">A little introduction <span class="dating-optional">Optional</span></label>
                <textarea class="form-control" id="bio" name="bio" rows="4" maxlength="500" placeholder="Tell us a little about yourself."><?= html_escape($bio) ?></textarea>
                <small>Up to 500 characters.</small>
            </div>
        </div>

        <label class="dating-consent">
            <input type="checkbox" name="confirm_adult" value="1" <?= ($_POST['confirm_adult'] ?? '') === '1' ? 'checked' : '' ?> required>
            <span>I confirm I am at least 18 years old and the date of birth above is accurate.</span>
        </label>

        <button class="btn btn-primary dating-submit" type="submit">
            Save and continue <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>
    </form>
</section>

<style>
.dating-form-panel {
    max-width: 850px;
    margin: 0 auto 28px;
    padding: clamp(20px, 4vw, 36px);
    border: 1px solid #efdee4;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 16px 45px rgba(102, 47, 68, .07);
}

.dating-step-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #ae4967;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .1em;
}

.dating-form-heading h2 {
    margin: 9px 0 5px;
    color: #352630;
    font-size: clamp(23px, 4vw, 30px);
    font-weight: 750;
}

.dating-form-heading p {
    max-width: 610px;
    margin: 0 0 22px;
    color: #796c74;
    font-size: 13px;
    line-height: 1.6;
}

.dating-form-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
}

.dating-field {
    min-width: 0;
}

.dating-field-wide {
    grid-column: 1 / -1;
}

.dating-field .form-control {
    min-height: 46px;
    border-color: #eadce1;
    border-radius: 12px;
    background-color: #fffdfd;
    color: #352630;
}

.dating-field .form-control:focus {
    border-color: #c45170;
    box-shadow: 0 0 0 3px rgba(196, 81, 112, .12);
}

.dating-field textarea.form-control {
    min-height: 110px;
}

.dating-field small {
    display: block;
    margin-top: 6px;
    color: #887a82;
    font-size: 11px;
}

.dating-fieldset {
    display: flex;
    flex-wrap: wrap;
    align-content: flex-start;
    gap: 8px;
    margin: 0;
    padding: 0;
    border: 0;
}

.dating-fieldset legend {
    float: none;
    width: 100%;
    margin-bottom: 7px;
}

.dating-choice {
    display: inline-flex;
    min-height: 38px;
    align-items: center;
    gap: 8px;
    padding: 7px 11px;
    border: 1px solid #efdee4;
    border-radius: 11px;
    background: #fff9f8;
    color: #55434d;
    cursor: pointer;
    font-size: 12px;
}

.dating-choice:has(input:checked) {
    border-color: #d994a7;
    background: #fff0f3;
    color: #9f3e5c;
}

.dating-choice input,
.dating-consent input {
    accent-color: #bd5574;
}

.dating-optional {
    color: #988891;
    font-size: 10px;
    font-weight: 400;
}

.dating-consent {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    margin: 22px 0;
    color: #665760;
    font-size: 12px;
}

.dating-consent input {
    margin-top: 3px;
}

.dating-submit {
    min-height: 46px;
    padding: 0 18px;
    border: 0;
    border-radius: 12px;
    background: #b94e6d;
    color: #fff;
    font-weight: 700;
}

.dating-submit:hover {
    background: #a33f5d;
    color: #fff;
}

@media (max-width: 600px) {
    .dating-form-grid {
        grid-template-columns: 1fr;
        gap: 15px;
    }

    .dating-field-wide {
        grid-column: auto;
    }

    .dating-submit {
        width: 100%;
    }
}
</style>

<?php require __DIR__ . '/../includes/app_footer.php'; ?>
