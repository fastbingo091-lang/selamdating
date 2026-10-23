<?php
declare(strict_types=1);

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/security.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$error = '';
$success = '';
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

try {
    $db = database();
    $profileQuery = $db->prepare('SELECT * FROM dating_profiles WHERE user_id = ? LIMIT 1');
    $profileQuery->execute([$userId]);
    $profile = $profileQuery->fetch();
} catch (PDOException $exception) {
    error_log(sprintf('Selam profile read failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
    http_response_code(503);
    exit('Dating profile setup is not ready. Apply database/migrations/20261023_dating_profiles.sql and try again.');
}

if (!$profile) {
    header('Location: dating/onboarding.php');
    exit;
}

$profileId = (int) $profile['id'];
$formProfile = $profile;
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $error = 'Your form expired. Reload the page and try again.';
    } else {
        $actionInput = $_POST['action'] ?? '';
        $action = is_string($actionInput) ? $actionInput : '';

        if ($action === 'save_profile') {
            $displayNameInput = $_POST['display_name'] ?? '';
            $birthDateInput = $_POST['birth_date'] ?? '';
            $genderInput = $_POST['gender'] ?? '';
            $cityInput = $_POST['city'] ?? '';
            $bioInput = $_POST['bio'] ?? '';
            $telegramInput = $_POST['telegram_username'] ?? '';
            $visibilityInput = $_POST['visibility'] ?? 'private';
            $preferencesInput = $_POST['preferences'] ?? [];

            $displayName = is_string($displayNameInput) ? trim($displayNameInput) : '';
            $birthDate = is_string($birthDateInput) ? $birthDateInput : '';
            $gender = is_string($genderInput) ? $genderInput : '';
            $city = is_string($cityInput) ? trim($cityInput) : '';
            $bio = is_string($bioInput) ? trim($bioInput) : '';
            $telegramUsername = is_string($telegramInput) ? ltrim(trim($telegramInput), '@') : '';
            $visibility = is_string($visibilityInput) ? $visibilityInput : '';
            $preferences = is_array($preferencesInput)
                ? array_values(array_unique(array_filter(
                    $preferencesInput,
                    static fn (mixed $value): bool => is_string($value)
                )))
                : [];

            $formProfile['display_name'] = $displayName;
            $formProfile['birth_date'] = $birthDate;
            $formProfile['gender'] = $gender;
            $formProfile['city'] = $city;
            $formProfile['bio'] = $bio;
            $formProfile['telegram_username'] = $telegramUsername;
            $formProfile['visibility'] = $visibility;
            $formPreferences = $preferences;

            $birthDateObject = DateTimeImmutable::createFromFormat('!Y-m-d', $birthDate);
            $birthDateErrors = DateTimeImmutable::getLastErrors();
            $validBirthDate = $birthDateObject !== false
                && ($birthDateErrors === false || ($birthDateErrors['warning_count'] === 0 && $birthDateErrors['error_count'] === 0))
                && $birthDateObject->format('Y-m-d') === $birthDate;
            $today = new DateTimeImmutable('today');
            $adult = $validBirthDate
                && $birthDateObject <= $today
                && $birthDateObject->diff($today)->y >= 18;

            if ($displayName === '' || mb_strlen($displayName) > 50) {
                $error = 'Your display name must be between 1 and 50 characters.';
            } elseif (!$adult) {
                $error = 'Your date of birth must confirm that you are at least 18 years old.';
            } elseif (!isset($genderOptions[$gender])) {
                $error = 'Choose one of the available gender options.';
            } elseif (mb_strlen($city) > 80) {
                $error = 'City must be 80 characters or fewer.';
            } elseif (mb_strlen($bio) > 500) {
                $error = 'Your introduction must be 500 characters or fewer.';
            } elseif ($telegramUsername !== '' && preg_match('/\A[A-Za-z][A-Za-z0-9_]{4,31}\z/', $telegramUsername) !== 1) {
                $error = 'Enter a Telegram username with 5–32 letters, numbers, or underscores, beginning with a letter.';
            } elseif (!in_array($visibility, ['private', 'discoverable'], true)) {
                $error = 'Choose whether your profile should be private or discoverable.';
            } elseif ($preferences === [] || array_diff($preferences, array_keys($preferenceOptions)) !== []) {
                $error = 'Choose at least one preference for who you would like to meet.';
            } else {
                try {
                    $db->beginTransaction();
                    $updateProfile = $db->prepare("
                        UPDATE dating_profiles
                        SET display_name = :display_name, birth_date = :birth_date, gender = :gender,
                            city = :city, bio = :bio, telegram_username = :telegram_username,
                            visibility = :visibility
                        WHERE id = :id AND user_id = :user_id
                    ");
                    $updateProfile->execute([
                        'display_name' => $displayName,
                        'birth_date' => $birthDate,
                        'gender' => $gender,
                        'city' => $city !== '' ? $city : null,
                        'bio' => $bio !== '' ? $bio : null,
                        'telegram_username' => $telegramUsername !== '' ? $telegramUsername : null,
                        'visibility' => $visibility,
                        'id' => $profileId,
                        'user_id' => $userId,
                    ]);

                    $deletePreferences = $db->prepare('DELETE FROM dating_profile_preferences WHERE profile_id = ?');
                    $deletePreferences->execute([$profileId]);
                    $insertPreference = $db->prepare(
                        'INSERT INTO dating_profile_preferences (profile_id, preference) VALUES (?, ?)'
                    );
                    foreach ($preferences as $preference) {
                        $insertPreference->execute([$profileId, $preference]);
                    }
                    $db->commit();
                    header('Location: profile.php?updated=1');
                    exit;
                } catch (PDOException $exception) {
                    if ($db->inTransaction()) {
                        $db->rollBack();
                    }
                    error_log(sprintf('Selam profile update failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
                    $error = 'We could not update your profile right now. Please try again.';
                }
            }
        } elseif ($action === 'upload_media') {
            $mediaTypeInput = $_POST['media_type'] ?? '';
            $mediaType = is_string($mediaTypeInput) ? $mediaTypeInput : '';
            $fileField = $mediaType === 'photo' ? ($_FILES['photos'] ?? null) : ($_FILES['video'] ?? null);
            $isMultiple = $mediaType === 'photo';

            if (!in_array($mediaType, ['photo', 'video'], true) || !is_array($fileField)) {
                $error = 'Choose valid photo or video files to upload.';
            } else {
                $existingCount = null;
                try {
                    $fileCountStmt = $db->prepare('SELECT COUNT(*) FROM dating_profile_media WHERE profile_id = ? AND media_type = ?');
                    $fileCountStmt->execute([$profileId, $mediaType]);
                    $existingCount = (int) $fileCountStmt->fetchColumn();
                } catch (PDOException $exception) {
                    error_log(sprintf('Selam media count failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
                    $error = 'We could not check your upload capacity right now. Please try again.';
                }

                $maxCount = $mediaType === 'photo' ? 6 : 1;

                $fileNames = $isMultiple ? ($fileField['name'] ?? []) : [$fileField['name'] ?? ''];
                $fileTmpNames = $isMultiple ? ($fileField['tmp_name'] ?? []) : [$fileField['tmp_name'] ?? ''];
                $fileErrors = $isMultiple ? ($fileField['error'] ?? []) : [$fileField['error'] ?? UPLOAD_ERR_NO_FILE];
                $fileSizes = $isMultiple ? ($fileField['size'] ?? []) : [$fileField['size'] ?? 0];
                $fileTotal = is_array($fileNames) ? count($fileNames) : 0;
                $validFileShape = is_array($fileNames)
                    && is_array($fileTmpNames)
                    && is_array($fileErrors)
                    && is_array($fileSizes)
                    && array_keys($fileNames) === array_keys($fileTmpNames)
                    && array_keys($fileNames) === array_keys($fileErrors)
                    && array_keys($fileNames) === array_keys($fileSizes);

                    if ($error === '' && !$validFileShape) {
                        $error = 'Choose valid photo or video files to upload.';
                    } elseif ($error === '' && $existingCount >= $maxCount) {
                        $error = $mediaType === 'photo'
                            ? 'You already have the maximum of 6 photos.'
                            : 'You already have a video on your profile. Remove it before uploading another.';
                    } elseif ($error === '' && ($fileTotal === 0 || $fileTotal > $maxCount - $existingCount)) {
                        $remaining = $maxCount - $existingCount;
                        $error = $mediaType === 'photo'
                            ? 'You can add up to ' . $remaining . ' more photo' . ($remaining === 1 ? '' : 's') . '.'
                            : 'Choose one video to upload.';
                    } elseif ($error === '') {
                    $uploadDirectory = __DIR__ . '/uploads/private-dating';
                    $storedFiles = [];
                    try {
                        if (!is_dir($uploadDirectory) && !mkdir($uploadDirectory, 0750, true) && !is_dir($uploadDirectory)) {
                            throw new RuntimeException('The private upload directory could not be created.');
                        }

                        $db->beginTransaction();
                        $lockProfile = $db->prepare('SELECT id FROM dating_profiles WHERE id = ? AND user_id = ? FOR UPDATE');
                        $lockProfile->execute([$profileId, $userId]);
                        if ($lockProfile->fetchColumn() === false) {
                            throw new RuntimeException('Your dating profile could not be found.');
                        }

                        $lockedCountQuery = $db->prepare(
                            'SELECT COUNT(*) FROM dating_profile_media WHERE profile_id = ? AND media_type = ?'
                        );
                        $lockedCountQuery->execute([$profileId, $mediaType]);
                        $lockedCount = (int) $lockedCountQuery->fetchColumn();
                        if ($fileTotal > $maxCount - $lockedCount) {
                            throw new RuntimeException($mediaType === 'photo'
                                ? 'There is not enough space for all selected photos. Remove an existing photo or choose fewer files.'
                                : 'You already have a video on your profile. Remove it before uploading another.');
                        }

                        foreach (array_keys($fileNames) as $index) {
                            $uploadErrorValue = $fileErrors[$index] ?? UPLOAD_ERR_NO_FILE;
                            $uploadError = is_int($uploadErrorValue) || is_string($uploadErrorValue)
                                ? (int) $uploadErrorValue
                                : UPLOAD_ERR_NO_FILE;
                            if ($uploadError !== UPLOAD_ERR_OK) {
                                throw new RuntimeException(match ($uploadError) {
                                    UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A selected file is larger than the server upload limit.',
                                    UPLOAD_ERR_PARTIAL => 'A file was only partially uploaded. Please try again.',
                                    UPLOAD_ERR_NO_FILE => 'Choose a file before uploading.',
                                    default => 'A selected file could not be uploaded. Please try again.',
                                });
                            }

                            $temporaryPath = $fileTmpNames[$index] ?? '';
                            $fileSize = (int) ($fileSizes[$index] ?? 0);
                            $maxBytes = $mediaType === 'photo' ? 10 * 1024 * 1024 : 40 * 1024 * 1024;
                            if (!is_string($temporaryPath) || !is_uploaded_file($temporaryPath)) {
                                throw new RuntimeException('The uploaded file could not be verified.');
                            }
                            if ($fileSize < 1 || $fileSize > $maxBytes) {
                                throw new RuntimeException($mediaType === 'photo'
                                    ? 'Each photo must be smaller than 10 MB.'
                                    : 'The video must be smaller than 40 MB.');
                            }
                            $actualFileSize = filesize($temporaryPath);
                            if ($actualFileSize === false || $actualFileSize !== $fileSize) {
                                throw new RuntimeException('The uploaded file size could not be verified.');
                            }

                            $finfo = new finfo(FILEINFO_MIME_TYPE);
                            $mimeType = $finfo->file($temporaryPath);
                            if (!is_string($mimeType)) {
                                throw new RuntimeException('The type of an uploaded file could not be verified.');
                            }

                            $extensions = $mediaType === 'photo'
                                ? ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp']
                                : ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
                            if (!isset($extensions[$mimeType])) {
                                throw new RuntimeException($mediaType === 'photo'
                                    ? 'Photos must be JPEG, PNG, or WebP images.'
                                    : 'Videos must be MP4 or WebM files.');
                            }

                            if ($mediaType === 'photo') {
                                $imageInfo = getimagesize($temporaryPath);
                                if ($imageInfo === false || ($imageInfo['mime'] ?? '') !== $mimeType) {
                                    throw new RuntimeException('A selected photo is not a valid image.');
                                }
                                $width = (int) ($imageInfo[0] ?? 0);
                                $height = (int) ($imageInfo[1] ?? 0);
                                if ($width < 1 || $height < 1 || $width > 10000 || $height > 10000 || $width * $height > 40000000) {
                                    throw new RuntimeException('Photo dimensions must not exceed 10,000 pixels per side or 40 megapixels.');
                                }
                            }

                            $randomId = bin2hex(random_bytes(16));
                            $storageName = substr($randomId, 0, 8) . '-' . substr($randomId, 8, 4) . '-4' . substr($randomId, 13, 3) . '-a' . substr($randomId, 17, 3) . '-' . substr($randomId, 20);
                            $relativeName = $storageName . '.' . $extensions[$mimeType];
                            $destination = $uploadDirectory . DIRECTORY_SEPARATOR . $relativeName;

                            if (!move_uploaded_file($temporaryPath, $destination)) {
                                throw new RuntimeException('A selected file could not be saved. Please try again.');
                            }
                            $storedFiles[] = $destination;

                            $insertMedia = $db->prepare("
                                INSERT INTO dating_profile_media (profile_id, media_type, storage_name, mime_type)
                                VALUES (?, ?, ?, ?)
                            ");
                            $insertMedia->execute([$profileId, $mediaType, $relativeName, $mimeType]);
                        }

                        $db->commit();
                        header('Location: profile.php?media_added=1');
                        exit;
                    } catch (PDOException | RuntimeException $exception) {
                        if ($db->inTransaction()) {
                            $db->rollBack();
                        }
                        foreach ($storedFiles as $storedFile) {
                            if (is_file($storedFile) && !unlink($storedFile)) {
                                error_log('Selam private media cleanup failed for an uploaded file.');
                            }
                        }
                        if ($exception instanceof PDOException) {
                            error_log(sprintf('Selam media upload database failure [%s]: %s', $exception->getCode(), $exception->getMessage()));
                            $error = 'Your upload could not be saved. Please try again.';
                        } else {
                            $error = $exception->getMessage();
                        }
                    }
                }
            }
        } elseif ($action === 'delete_media') {
            $mediaIdInput = $_POST['media_id'] ?? '';
            $mediaId = is_string($mediaIdInput) || is_int($mediaIdInput) ? filter_var($mediaIdInput, FILTER_VALIDATE_INT) : false;

            if (!is_int($mediaId) || $mediaId < 1) {
                $error = 'Choose a valid media item to remove.';
            } else {
                try {
                    $findMedia = $db->prepare('SELECT storage_name FROM dating_profile_media WHERE id = ? AND profile_id = ?');
                    $findMedia->execute([$mediaId, $profileId]);
                    $storageName = $findMedia->fetchColumn();
                    if (!is_string($storageName)) {
                        $error = 'That media item could not be found.';
                    } else {
                        $deleteMedia = $db->prepare('DELETE FROM dating_profile_media WHERE id = ? AND profile_id = ?');
                        $deleteMedia->execute([$mediaId, $profileId]);
                        if (preg_match('/\A[a-f0-9]{8}-[a-f0-9]{4}-4[a-f0-9]{3}-a[a-f0-9]{3}-[a-f0-9]{12}\.(?:jpg|png|webp|mp4|webm)\z/', $storageName)) {
                            $mediaPath = __DIR__ . '/uploads/private-dating/' . $storageName;
                            if (is_file($mediaPath) && !unlink($mediaPath)) {
                                error_log('Selam private media cleanup failed for a removed file.');
                            }
                        } else {
                            error_log('Selam private media cleanup skipped an invalid stored filename.');
                        }
                        header('Location: profile.php?media_removed=1');
                        exit;
                    }
                } catch (PDOException $exception) {
                    error_log(sprintf('Selam media deletion failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
                    $error = 'We could not remove that media item right now. Please try again.';
                }
            }
        } else {
            $error = 'Choose a valid profile action.';
        }
    }
}

$updated = isset($_GET['updated']) && $_GET['updated'] === '1';
$mediaAdded = isset($_GET['media_added']) && $_GET['media_added'] === '1';
$mediaRemoved = isset($_GET['media_removed']) && $_GET['media_removed'] === '1';

try {
    if (!isset($formPreferences)) {
        $preferencesQuery = $db->prepare('SELECT preference FROM dating_profile_preferences WHERE profile_id = ? ORDER BY preference');
        $preferencesQuery->execute([$profileId]);
        $formPreferences = $preferencesQuery->fetchAll(PDO::FETCH_COLUMN);
    }
    $mediaQuery = $db->prepare('SELECT id, media_type, storage_name, mime_type FROM dating_profile_media WHERE profile_id = ? ORDER BY created_at, id');
    $mediaQuery->execute([$profileId]);
    $mediaItems = $mediaQuery->fetchAll();
} catch (PDOException $exception) {
    error_log(sprintf('Selam profile media read failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
    http_response_code(503);
    exit('Your profile data is temporarily unavailable. Please try again.');
}
$selectedPreferences = $formPreferences;
$photoCount = count(array_filter($mediaItems, static fn (array $item): bool => $item['media_type'] === 'photo'));
$videoCount = count(array_filter($mediaItems, static fn (array $item): bool => $item['media_type'] === 'video'));

$profileGender = (string) $formProfile['gender'];
$appRoot = '';
$pageTitle = 'Your dating profile';
$pageDescription = 'Update your private profile and manage your photos and video.';
$activeNav = 'profile';

require __DIR__ . '/includes/app_header.php';
?>

<div class="dating-profile-page">
    <div class="dating-profile-intro">
        <div>
            <span class="eyebrow">YOUR PRIVATE SPACE</span>
            <h2><?= html_escape((string) $profile['display_name']) ?></h2>
            <p class="small-note mb-0">Selam user ID: <strong>#<?= $userId ?></strong> · Share this ID to receive transfers.</p>
            <p><?= $profile['visibility'] === 'discoverable' ? 'Your profile is shown only to members whose preferences match yours and whom you also prefer.' : 'Your profile is private and visible only to you until you opt in to Discover.' ?></p>
        </div>
        <span class="status-badge <?= $profile['visibility'] === 'discoverable' ? 'success' : 'warning' ?>"><i class="bi bi-<?= $profile['visibility'] === 'discoverable' ? 'heart-fill' : 'lock-fill' ?>" aria-hidden="true"></i> <?= $profile['visibility'] === 'discoverable' ? 'Discoverable' : 'Private' ?></span>
    </div>

    <?php if ($error !== ''): ?><div class="alert alert-danger" role="alert"><?= html_escape($error) ?></div><?php endif; ?>
    <?php if ($updated): ?><div class="alert alert-success" role="status">Your profile was updated.</div><?php endif; ?>
    <?php if ($mediaAdded): ?><div class="alert alert-success" role="status">Your media was uploaded. Sharing follows your profile visibility setting.</div><?php endif; ?>
    <?php if ($mediaRemoved): ?><div class="alert alert-success" role="status">The media item was removed.</div><?php endif; ?>

    <section class="data-panel dating-editor-panel">
        <div class="data-panel-header">
            <div><h2>About you</h2><p>Your date of birth and dating preferences stay private.</p></div>
        </div>
        <form method="post" class="dating-edit-form">
            <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
            <input type="hidden" name="action" value="save_profile">
            <div class="dating-edit-grid">
                <div class="dating-field">
                    <label class="form-label" for="display_name">Display name</label>
                    <input class="form-control" id="display_name" name="display_name" maxlength="50" value="<?= html_escape((string) $formProfile['display_name']) ?>" required>
                </div>
                <div class="dating-field">
                    <label class="form-label" for="birth_date">Date of birth</label>
                    <input class="form-control" id="birth_date" name="birth_date" type="date" max="<?= html_escape(date('Y-m-d', strtotime('-18 years'))) ?>" value="<?= html_escape((string) $formProfile['birth_date']) ?>" required>
                    <small>Private · members must be 18 or older.</small>
                </div>
                <fieldset class="dating-field dating-fieldset">
                    <legend class="form-label">Your gender</legend>
                    <?php foreach ($genderOptions as $value => $label): ?>
                        <label class="dating-choice">
                            <input type="radio" name="gender" value="<?= html_escape($value) ?>" <?= $profileGender === $value ? 'checked' : '' ?> required>
                            <span><?= html_escape($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <fieldset class="dating-field dating-fieldset">
                    <legend class="form-label">Who you would like to meet</legend>
                    <?php foreach ($preferenceOptions as $value => $label): ?>
                        <label class="dating-choice">
                            <input type="checkbox" name="preferences[]" value="<?= html_escape($value) ?>" <?= in_array($value, $selectedPreferences, true) ? 'checked' : '' ?>>
                            <span><?= html_escape($label) ?></span>
                        </label>
                    <?php endforeach; ?>
                </fieldset>
                <div class="dating-field">
                    <label class="form-label" for="city">City <span class="dating-optional">Optional</span></label>
                    <input class="form-control" id="city" name="city" maxlength="80" value="<?= html_escape((string) ($formProfile['city'] ?? '')) ?>">
                </div>
                <div class="dating-field dating-field-wide">
                    <label class="form-label" for="bio">About me <span class="dating-optional">Optional</span></label>
                    <textarea class="form-control" id="bio" name="bio" rows="4" maxlength="500"><?= html_escape((string) ($formProfile['bio'] ?? '')) ?></textarea>
                    <small>Up to 500 characters.</small>
                </div>
                <div class="dating-field">
                    <label class="form-label" for="telegram_username">Telegram username <span class="dating-optional">Optional</span></label>
                    <input class="form-control" id="telegram_username" name="telegram_username" maxlength="33" autocomplete="off" placeholder="your_username" value="<?= html_escape((string) ($formProfile['telegram_username'] ?? '')) ?>">
                    <small>Your @username is hidden from non-premium members.</small>
                </div>
                <div class="dating-field">
                    <label class="form-label" for="visibility">Profile visibility</label>
                    <select class="form-select" id="visibility" name="visibility">
                        <option value="private" <?= $formProfile['visibility'] === 'private' ? 'selected' : '' ?>>Private — only me</option>
                        <option value="discoverable" <?= $formProfile['visibility'] === 'discoverable' ? 'selected' : '' ?>>Discoverable — reciprocal matches</option>
                    </select>
                    <small>Only members who match your preferences and whom you prefer can see your profile and photos.</small>
                </div>
            </div>
            <button class="btn btn-primary dating-save-button" type="submit"><i class="bi bi-check2" aria-hidden="true"></i> Save profile</button>
        </form>
    </section>

    <section class="data-panel dating-editor-panel">
        <div class="data-panel-header">
            <div><h2>Photos and video</h2><p>Up to 6 photos (10 MB each) and one MP4/WebM video (40 MB).</p></div>
            <span class="status-badge"><?= $photoCount ?>/6 photos · <?= $videoCount ?>/1 video</span>
        </div>

        <?php if ($mediaItems): ?>
            <div class="dating-media-grid">
                <?php foreach ($mediaItems as $media): ?>
                    <?php $mediaUrl = 'dating/media.php?id=' . (int) $media['id']; ?>
                    <article class="dating-media-card">
                        <?php if ($media['media_type'] === 'photo'): ?>
                            <img src="<?= html_escape($mediaUrl) ?>" alt="Your private profile photo" loading="lazy">
                        <?php else: ?>
                            <video src="<?= html_escape($mediaUrl) ?>" controls preload="metadata" aria-label="Your private profile video"></video>
                        <?php endif; ?>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                            <input type="hidden" name="action" value="delete_media">
                            <input type="hidden" name="media_id" value="<?= (int) $media['id'] ?>">
                            <button class="btn btn-outline-secondary btn-sm" type="submit"><i class="bi bi-trash" aria-hidden="true"></i> Remove</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="dating-media-empty"><i class="bi bi-images" aria-hidden="true"></i><strong>Your profile is waiting for its first photo.</strong><span>Photos are private until you opt into Discover; then only reciprocal matches can see them.</span></div>
        <?php endif; ?>

        <div class="dating-upload-grid">
            <form method="post" enctype="multipart/form-data" class="dating-upload-card">
                <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_media">
                <input type="hidden" name="media_type" value="photo">
                <label class="form-label" for="photos">Add photos</label>
                <input class="form-control" id="photos" name="photos[]" type="file" accept="image/jpeg,image/png,image/webp" multiple <?= $photoCount >= 6 ? 'disabled' : '' ?>>
                <small>JPEG, PNG, or WebP. You can add <?= max(0, 6 - $photoCount) ?> more.</small>
                <button class="btn btn-primary" type="submit" <?= $photoCount >= 6 ? 'disabled' : '' ?>><i class="bi bi-image" aria-hidden="true"></i> Upload photos</button>
            </form>
            <form method="post" enctype="multipart/form-data" class="dating-upload-card">
                <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                <input type="hidden" name="action" value="upload_media">
                <input type="hidden" name="media_type" value="video">
                <label class="form-label" for="video">Add one video</label>
                <input class="form-control" id="video" name="video" type="file" accept="video/mp4,video/webm" <?= $videoCount >= 1 ? 'disabled' : '' ?>>
                <small>MP4 or WebM, up to 40 MB.</small>
                <button class="btn btn-primary" type="submit" <?= $videoCount >= 1 ? 'disabled' : '' ?>><i class="bi bi-camera-video" aria-hidden="true"></i> Upload video</button>
            </form>
        </div>
    </section>

    <section class="dating-privacy-note">
        <i class="bi bi-shield-lock" aria-hidden="true"></i>
        <div><strong>Your privacy is in your hands</strong><span>When set to Discoverable, only reciprocal matches can see your dating profile and photos. Your Telegram username remains hidden unless the viewer has an active Selam membership.</span></div>
    </section>
</div>

<style>
.dating-profile-page {
    display: grid;
    gap: 18px;
}

.dating-profile-intro {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 14px;
}

.dating-profile-intro h2 {
    margin: 3px 0;
    color: #382a32;
    font-size: 24px;
    font-weight: 750;
}

.dating-profile-intro p {
    margin: 0;
    color: #796c74;
    font-size: 12px;
}

.dating-editor-panel {
    border-radius: 17px;
}

.dating-editor-panel .data-panel-header {
    min-height: 68px;
}

.dating-editor-panel .data-panel-header p {
    margin: 4px 0 0;
    font-size: 11px;
}

.dating-edit-form,
.dating-upload-grid {
    padding: 19px;
}

.dating-edit-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 17px;
}

.dating-field {
    min-width: 0;
}

.dating-field-wide {
    grid-column: 1 / -1;
}

.dating-field .form-control {
    min-height: 44px;
    border-color: #eadce1;
    border-radius: 11px;
    background-color: #fffdfd;
    color: #352630;
}

.dating-field textarea.form-control {
    min-height: 100px;
}

.dating-field small,
.dating-upload-card small {
    display: block;
    margin-top: 5px;
    color: #887a82;
    font-size: 10px;
}

.dating-fieldset {
    display: flex;
    flex-wrap: wrap;
    align-content: flex-start;
    gap: 7px;
    margin: 0;
    padding: 0;
    border: 0;
}

.dating-fieldset legend {
    float: none;
    width: 100%;
    margin-bottom: 5px;
}

.dating-choice {
    display: inline-flex;
    min-height: 35px;
    align-items: center;
    gap: 7px;
    padding: 6px 9px;
    border: 1px solid #efdee4;
    border-radius: 10px;
    background: #fff9f8;
    color: #55434d;
    cursor: pointer;
    font-size: 11px;
}

.dating-choice:has(input:checked) {
    border-color: #d994a7;
    background: #fff0f3;
    color: #9f3e5c;
}

.dating-choice input {
    accent-color: #bd5574;
}

.dating-optional {
    color: #988891;
    font-size: 10px;
    font-weight: 400;
}

.dating-save-button {
    margin-top: 18px;
    border-color: #b94e6d;
    background: #b94e6d;
    color: #fff;
}

.dating-save-button:hover {
    border-color: #a33f5d;
    background: #a33f5d;
    color: #fff;
}

.dating-media-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
    gap: 12px;
    padding: 18px 19px 0;
}

.dating-media-card {
    overflow: hidden;
    border: 1px solid #efdee4;
    border-radius: 13px;
    background: #fff;
}

.dating-media-card img,
.dating-media-card video {
    display: block;
    width: 100%;
    aspect-ratio: 4 / 3;
    object-fit: cover;
    background: #fff0f3;
}

.dating-media-card form {
    padding: 8px;
}

.dating-media-empty {
    display: grid;
    justify-items: center;
    gap: 5px;
    padding: 23px 16px 6px;
    color: #796c74;
    text-align: center;
}

.dating-media-empty > i {
    margin-bottom: 4px;
    color: #be5a78;
    font-size: 26px;
}

.dating-media-empty strong {
    color: #493941;
    font-size: 12px;
}

.dating-media-empty span {
    font-size: 10px;
}

.dating-upload-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 12px;
}

.dating-upload-card {
    display: grid;
    align-content: start;
    gap: 8px;
    padding: 14px;
    border: 1px solid #efdee4;
    border-radius: 13px;
    background: #fffafa;
}

.dating-upload-card .form-control {
    min-height: 42px;
    border-color: #eadce1;
    border-radius: 10px;
    background: #fff;
    font-size: 11px;
}

.dating-upload-card .btn {
    justify-self: start;
    margin-top: 5px;
    border-color: #b94e6d;
    background: #b94e6d;
    color: #fff;
    font-size: 11px;
}

.dating-upload-card .btn:hover {
    border-color: #a33f5d;
    background: #a33f5d;
    color: #fff;
}

.dating-privacy-note {
    display: flex;
    align-items: flex-start;
    gap: 12px;
    padding: 14px 16px;
    border: 1px solid #efdee4;
    border-radius: 14px;
    background: #fff4f5;
    color: #ae4967;
}

.dating-privacy-note > i {
    margin-top: 2px;
    font-size: 17px;
}

.dating-privacy-note div {
    display: grid;
    gap: 3px;
}

.dating-privacy-note strong {
    color: #493941;
    font-size: 12px;
}

.dating-privacy-note span {
    color: #796c74;
    font-size: 11px;
    line-height: 1.5;
}

@media (max-width: 620px) {
    .dating-profile-intro {
        align-items: flex-start;
        flex-direction: column;
    }

    .dating-edit-grid,
    .dating-upload-grid {
        grid-template-columns: 1fr;
    }

    .dating-field-wide {
        grid-column: auto;
    }

    .dating-save-button {
        width: 100%;
    }
}
</style>

<?php require __DIR__ . '/includes/app_footer.php'; ?>
