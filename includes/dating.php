<?php
declare(strict_types=1);

function dating_has_active_membership(PDO $db, int $userId): bool
{
    $membership = $db->prepare("
        SELECT 1
        FROM vip_purchases purchase
        INNER JOIN vip_packages package ON package.id = purchase.vip_package_id
        WHERE purchase.user_id = :user_id
          AND purchase.status = 'active'
          AND package.status = 'active'
        LIMIT 1
    ");
    $membership->execute(['user_id' => $userId]);

    return $membership->fetchColumn() !== false;
}

function dating_preferences_accept_gender(array $preferences, string $gender): bool
{
    if (in_array('everyone', $preferences, true)) {
        return true;
    }

    $preference = match ($gender) {
        'woman' => 'women',
        'man' => 'men',
        default => null,
    };

    return $preference !== null && in_array($preference, $preferences, true);
}

function dating_profiles_are_mutual_matches(PDO $db, int $firstUserId, int $secondUserId): bool
{
    if ($firstUserId < 1 || $secondUserId < 1 || $firstUserId === $secondUserId) {
        return false;
    }

    $profilesQuery = $db->prepare("
        SELECT p.user_id, p.gender, p.visibility, GROUP_CONCAT(DISTINCT preference.preference) AS preferences
        FROM dating_profiles p
        INNER JOIN users u ON u.id = p.user_id AND u.status = 'active'
        INNER JOIN dating_profile_preferences preference ON preference.profile_id = p.id
        WHERE p.user_id IN (:first_user, :second_user)
        GROUP BY p.user_id, p.gender, p.visibility
    ");
    $profilesQuery->execute([
        'first_user' => $firstUserId,
        'second_user' => $secondUserId,
    ]);

    $profiles = [];
    foreach ($profilesQuery->fetchAll(PDO::FETCH_ASSOC) as $profile) {
        $profiles[(int) $profile['user_id']] = $profile;
    }
    $first = $profiles[$firstUserId] ?? null;
    $second = $profiles[$secondUserId] ?? null;

    if (
        !$first
        || !$second
        || $first['visibility'] !== 'discoverable'
        || $second['visibility'] !== 'discoverable'
    ) {
        return false;
    }

    $firstPreferences = explode(',', (string) $first['preferences']);
    $secondPreferences = explode(',', (string) $second['preferences']);

    return dating_preferences_accept_gender($firstPreferences, (string) $second['gender'])
        && dating_preferences_accept_gender($secondPreferences, (string) $first['gender']);
}

function dating_user_is_conversation_member(array $conversation, int $userId): bool
{
    return (int) $conversation['user_low_id'] === $userId
        || (int) $conversation['user_high_id'] === $userId;
}
