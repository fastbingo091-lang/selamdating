<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/dating.php';

start_app_session();

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

$userId = (int) $_SESSION['user_id'];
$db = database();
$profileQuery = $db->prepare('SELECT id, gender, visibility FROM dating_profiles WHERE user_id = ? LIMIT 1');
$profileQuery->execute([$userId]);
$viewer = $profileQuery->fetch(PDO::FETCH_ASSOC);

if (!$viewer) {
    header('Location: onboarding.php');
    exit;
}

$matches = [];
if ($viewer['visibility'] === 'discoverable') {
    try {
        $viewerPreferencesQuery = $db->prepare(
            'SELECT preference FROM dating_profile_preferences WHERE profile_id = ?'
        );
        $viewerPreferencesQuery->execute([(int) $viewer['id']]);
        $viewerPreferences = $viewerPreferencesQuery->fetchAll(PDO::FETCH_COLUMN);

        $candidateQuery = $db->prepare("
            SELECT p.user_id, p.id AS profile_id, p.display_name, p.gender, p.birth_date, p.city, p.bio,
                GROUP_CONCAT(DISTINCT target_preference.preference) AS preferences,
                (SELECT media.id FROM dating_profile_media media
                 WHERE media.profile_id = p.id
                 ORDER BY media.created_at, media.id LIMIT 1) AS featured_media_id,
                (SELECT media.media_type FROM dating_profile_media media
                 WHERE media.profile_id = p.id
                 ORDER BY media.created_at, media.id LIMIT 1) AS featured_media_type
            FROM dating_profiles p
            INNER JOIN users u ON u.id = p.user_id AND u.status = 'active'
            INNER JOIN dating_profile_preferences target_preference ON target_preference.profile_id = p.id
            WHERE p.user_id <> :viewer_id AND p.visibility = 'discoverable'
            GROUP BY p.id, p.user_id, p.display_name, p.gender, p.birth_date, p.city, p.bio
            ORDER BY p.updated_at DESC, p.id DESC
            LIMIT 250
        ");
        $candidateQuery->execute(['viewer_id' => $userId]);

        foreach ($candidateQuery->fetchAll(PDO::FETCH_ASSOC) as $candidate) {
            $candidatePreferences = explode(',', (string) $candidate['preferences']);
            if (
                dating_preferences_accept_gender($viewerPreferences, (string) $candidate['gender'])
                && dating_preferences_accept_gender($candidatePreferences, (string) $viewer['gender'])
            ) {
                $birthDate = DateTimeImmutable::createFromFormat('!Y-m-d', (string) $candidate['birth_date']);
                if ($birthDate !== false) {
                    $candidate['age'] = $birthDate->diff(new DateTimeImmutable('today'))->y;
                    $matches[] = $candidate;
                }
            }
        }
    } catch (PDOException $exception) {
        error_log(sprintf('Selam discovery search failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
        http_response_code(503);
        exit('Discover is temporarily unavailable. Please try again.');
    }
}

$matchedProfileIds = array_map(
    static fn (array $match): int => (int) $match['profile_id'],
    $matches
);
$feedback = $_SESSION['discover_feedback'] ?? null;
unset($_SESSION['discover_feedback']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $actionInput = $_POST['action'] ?? '';
    $action = is_string($actionInput) ? $actionInput : '';
    $profileInput = filter_var($_POST['profile_id'] ?? null, FILTER_VALIDATE_INT);
    $targetProfileId = $profileInput === false || $profileInput === null ? 0 : (int) $profileInput;

    if (!csrf_token_is_valid($_POST['csrf_token'] ?? null)) {
        $_SESSION['discover_feedback'] = ['type' => 'error', 'message' => 'Your action expired. Reload the page and try again.'];
        header('Location: discover.php');
        exit;
    }
    if (!in_array($targetProfileId, $matchedProfileIds, true)) {
        $_SESSION['discover_feedback'] = ['type' => 'error', 'message' => 'That profile is no longer available.'];
        header('Location: discover.php');
        exit;
    }

    try {
        if ($action === 'toggle_like') {
            $existingLike = $db->prepare('SELECT 1 FROM dating_profile_likes WHERE profile_id = ? AND user_id = ?');
            $existingLike->execute([$targetProfileId, $userId]);
            if ($existingLike->fetchColumn()) {
                $removeLike = $db->prepare('DELETE FROM dating_profile_likes WHERE profile_id = ? AND user_id = ?');
                $removeLike->execute([$targetProfileId, $userId]);
            } else {
                $addLike = $db->prepare('INSERT INTO dating_profile_likes (profile_id, user_id) VALUES (?, ?)');
                $addLike->execute([$targetProfileId, $userId]);
            }
            $_SESSION['discover_feedback'] = ['type' => 'success', 'message' => 'Your like was updated.'];
        } elseif ($action === 'add_comment') {
            $commentInput = $_POST['comment'] ?? '';
            $comment = is_string($commentInput) ? trim($commentInput) : '';
            if ($comment === '' || mb_strlen($comment) > 500) {
                $_SESSION['discover_feedback'] = ['type' => 'error', 'message' => 'Comments must be between 1 and 500 characters.'];
            } else {
                $addComment = $db->prepare('INSERT INTO dating_profile_comments (profile_id, user_id, comment) VALUES (?, ?, ?)');
                $addComment->execute([$targetProfileId, $userId, $comment]);
                $_SESSION['discover_feedback'] = ['type' => 'success', 'message' => 'Your comment was posted.'];
            }
        } else {
            $_SESSION['discover_feedback'] = ['type' => 'error', 'message' => 'Unsupported Discover action.'];
        }
    } catch (PDOException $exception) {
        error_log(sprintf('Selam profile interaction failed [%s]: %s', $exception->getCode(), $exception->getMessage()));
        http_response_code(503);
        exit('Discover interactions are temporarily unavailable. Please try again.');
    }

    header('Location: discover.php#profile-' . $targetProfileId);
    exit;
}

$likesByProfile = [];
$commentsByProfile = [];
if ($matchedProfileIds !== []) {
    $placeholders = implode(',', array_fill(0, count($matchedProfileIds), '?'));
    $likeQuery = $db->prepare("
        SELECT profile_id, COUNT(*) AS like_count, MAX(user_id = ?) AS viewer_liked
        FROM dating_profile_likes
        WHERE profile_id IN ($placeholders)
        GROUP BY profile_id
    ");
    $likeQuery->execute(array_merge([$userId], $matchedProfileIds));
    foreach ($likeQuery->fetchAll(PDO::FETCH_ASSOC) as $interaction) {
        $likesByProfile[(int) $interaction['profile_id']] = [
            'count' => (int) $interaction['like_count'],
            'liked' => (bool) $interaction['viewer_liked'],
        ];
    }

    $commentCountQuery = $db->prepare("
        SELECT profile_id, COUNT(*) AS comment_count
        FROM dating_profile_comments
        WHERE profile_id IN ($placeholders)
        GROUP BY profile_id
    ");
    $commentCountQuery->execute($matchedProfileIds);
    foreach ($commentCountQuery->fetchAll(PDO::FETCH_ASSOC) as $interaction) {
        $commentsByProfile[(int) $interaction['profile_id']] = [
            'count' => (int) $interaction['comment_count'],
            'recent' => [],
        ];
    }

    $recentCommentQuery = $db->prepare("
        SELECT latest.profile_id, latest.display_name, latest.comment, latest.created_at
        FROM (
            SELECT c.profile_id, p.display_name, c.comment, c.created_at,
                ROW_NUMBER() OVER (PARTITION BY c.profile_id ORDER BY c.created_at DESC, c.id DESC) AS comment_rank
            FROM dating_profile_comments c
            INNER JOIN users u ON u.id = c.user_id AND u.status = 'active'
            INNER JOIN dating_profiles p ON p.user_id = c.user_id
            WHERE c.profile_id IN ($placeholders)
        ) latest
        WHERE latest.comment_rank <= 2
        ORDER BY latest.profile_id, latest.created_at DESC
    ");
    $recentCommentQuery->execute($matchedProfileIds);
    foreach ($recentCommentQuery->fetchAll(PDO::FETCH_ASSOC) as $commentRow) {
        $profileId = (int) $commentRow['profile_id'];
        $commentsByProfile[$profileId] ??= ['count' => 0, 'recent' => []];
        $commentsByProfile[$profileId]['recent'][] = $commentRow;
    }
}

$pageTitle = 'Discover';
$pageDescription = 'Profiles that match each other’s preferences.';
$activeNav = 'discover';
$appRoot = '../';
$pageClass = 'tiktok-discover-page';
require __DIR__ . '/../includes/app_header.php';
?>

<section class="tiktok-discover" aria-label="Discover mutual matches">
    <header class="tiktok-discover-bar">
        <a class="tiktok-wordmark" href="../"><span class="tiktok-mark"><i class="bi bi-heart-fill" aria-hidden="true"></i></span>selam</a>
        <nav class="tiktok-feed-tabs" aria-label="Discover navigation"><span class="feed-tab-active">For you</span><a href="chats.php">Chats</a><a href="../profile.php">Profile</a></nav>
        <a class="tiktok-top-chat" href="chats.php" aria-label="Open chats"><i class="bi bi-chat-dots" aria-hidden="true"></i></a>
    </header>

    <?php if (is_array($feedback)): ?>
        <div class="tiktok-feedback <?= ($feedback['type'] ?? '') === 'error' ? 'is-error' : '' ?>" role="status"><?= html_escape((string) ($feedback['message'] ?? '')) ?></div>
    <?php endif; ?>

    <?php if ($viewer['visibility'] !== 'discoverable'): ?>
        <div class="tiktok-empty-state">
            <span class="tiktok-empty-icon"><i class="bi bi-eye-slash" aria-hidden="true"></i></span>
            <h2>Your profile is private</h2>
            <p>Turn on Discover from your profile to see reciprocal matches and let them find you.</p>
            <a class="tiktok-action-pill" href="../profile.php">Update profile <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
    <?php elseif ($matches === []): ?>
        <div class="tiktok-empty-state">
            <span class="tiktok-empty-icon"><i class="bi bi-heart" aria-hidden="true"></i></span>
            <h2>No mutual matches just yet</h2>
            <p>Matches appear when both people opt into Discover and choose preferences that include each other.</p>
            <a class="tiktok-action-pill" href="../profile.php">Edit preferences <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
        </div>
    <?php else: ?>
        <div class="tiktok-feed" aria-label="Swipe vertically through profiles">
            <?php foreach ($matches as $match): ?>
                <?php $profileId = (int) $match['profile_id']; ?>
                <?php $likeInfo = $likesByProfile[$profileId] ?? ['count' => 0, 'liked' => false]; ?>
                <?php $commentInfo = $commentsByProfile[$profileId] ?? ['count' => 0, 'recent' => []]; ?>
                <article class="tiktok-profile-card" id="profile-<?= $profileId ?>">
                    <?php if ($match['featured_media_id'] !== null && $match['featured_media_type'] === 'video'): ?>
                        <video class="tiktok-profile-media" src="media.php?id=<?= (int) $match['featured_media_id'] ?>" muted loop playsinline preload="none" aria-label="Video from <?= html_escape((string) $match['display_name']) ?>"></video>
                    <?php elseif ($match['featured_media_id'] !== null): ?>
                        <img class="tiktok-profile-media" src="media.php?id=<?= (int) $match['featured_media_id'] ?>" alt="Profile photo for <?= html_escape((string) $match['display_name']) ?>" loading="lazy">
                    <?php else: ?>
                        <div class="tiktok-profile-placeholder"><i class="bi bi-person-heart" aria-hidden="true"></i></div>
                    <?php endif; ?>
                    <div class="tiktok-card-shade"></div>
                    <div class="tiktok-profile-copy">
                        <p class="tiktok-profile-location"><?= !empty($match['city']) ? '<i class="bi bi-geo-alt-fill" aria-hidden="true"></i> ' . html_escape((string) $match['city']) . ' · ' : '' ?><?= html_escape(ucwords(str_replace('_', ' ', (string) $match['gender']))) ?></p>
                        <h2><?= html_escape((string) $match['display_name']) ?> <span><?= (int) $match['age'] ?></span></h2>
                        <?php if (!empty($match['bio'])): ?><p class="tiktok-profile-bio"><?= nl2br(html_escape((string) $match['bio'])) ?></p><?php endif; ?>
                        <span class="tiktok-mutual-note"><i class="bi bi-heart-fill" aria-hidden="true"></i> You’re looking for each other</span>
                        <?php if ($commentInfo['recent'] !== []): ?>
                            <div class="tiktok-recent-comments" aria-label="Recent comments">
                                <?php foreach ($commentInfo['recent'] as $recentComment): ?>
                                    <p><strong><?= html_escape((string) $recentComment['display_name']) ?></strong> <?= html_escape((string) $recentComment['comment']) ?></p>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        <form class="tiktok-comment-form" method="post">
                            <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                            <input type="hidden" name="action" value="add_comment">
                            <input type="hidden" name="profile_id" value="<?= $profileId ?>">
                            <label class="visually-hidden" for="comment-<?= $profileId ?>">Comment on <?= html_escape((string) $match['display_name']) ?>’s profile</label>
                            <input id="comment-<?= $profileId ?>" name="comment" type="text" maxlength="500" placeholder="Add a comment..." required>
                            <button type="submit" aria-label="Post comment"><i class="bi bi-send-fill" aria-hidden="true"></i></button>
                        </form>
                    </div>
                    <aside class="tiktok-card-actions" aria-label="Profile actions">
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                            <input type="hidden" name="action" value="toggle_like">
                            <input type="hidden" name="profile_id" value="<?= $profileId ?>">
                            <button class="tiktok-like-action<?= $likeInfo['liked'] ? ' is-liked' : '' ?>" type="submit" aria-label="<?= $likeInfo['liked'] ? 'Unlike' : 'Like' ?> <?= html_escape((string) $match['display_name']) ?>’s profile">
                                <i class="bi bi-heart-fill" aria-hidden="true"></i>
                                <span><?= number_format((int) $likeInfo['count']) ?></span>
                            </button>
                        </form>
                        <span class="tiktok-comment-count"><i class="bi bi-chat-fill" aria-hidden="true"></i><span><?= number_format((int) $commentInfo['count']) ?></span></span>
                        <form method="post" action="chat.php">
                            <input type="hidden" name="csrf_token" value="<?= html_escape(csrf_token()) ?>">
                            <input type="hidden" name="action" value="start_chat">
                            <input type="hidden" name="user_id" value="<?= (int) $match['user_id'] ?>">
                            <button class="tiktok-chat-action" type="submit" aria-label="Say hello to <?= html_escape((string) $match['display_name']) ?>"><i class="bi bi-chat-heart-fill" aria-hidden="true"></i><span>Chat</span></button>
                        </form>
                    </aside>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/app_footer.php'; ?>
