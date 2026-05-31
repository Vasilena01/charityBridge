<?php
if (!function_exists('campaign_invite_status')) {
    function campaign_invite_status($campaignId, $userId, $pdo) {
        if (!$userId) return null;
        $stmt = $pdo->prepare(
            "SELECT status FROM campaign_invites
             WHERE campaign_id = :cid AND invited_user_id = :uid"
        );
        $stmt->execute(['cid' => (int)$campaignId, 'uid' => (int)$userId]);
        $s = $stmt->fetchColumn();
        return $s ?: null;
    }
}

if (!function_exists('can_view_campaign')) {
    function can_view_campaign($campaign, $userId, $pdo) {
        if (!$campaign) return false;
        $uid = $userId ? (int)$userId : 0;

        if ($uid && (int)$campaign['organizer_id'] === $uid) return true;
        if ($campaign['status'] === 'draft') return false;
        if (($campaign['visibility'] ?? 'public') === 'public') return true;

        $status = campaign_invite_status($campaign['id'], $uid, $pdo);
        return in_array($status, ['pending', 'accepted'], true);
    }
}

if (!function_exists('can_act_on_campaign')) {
    function can_act_on_campaign($campaign, $userId, $pdo) {
        if (!$campaign) return false;
        if ($campaign['status'] !== 'published') return false;
        $uid = $userId ? (int)$userId : 0;
        if (!$uid) return false;
        if ((int)$campaign['organizer_id'] === $uid) return false;

        if (($campaign['visibility'] ?? 'public') === 'public') return true;

        return campaign_invite_status($campaign['id'], $uid, $pdo) === 'accepted';
    }
}

if (!function_exists('campaign_visibility_sql')) {
    function campaign_visibility_sql($userIdParam = ':viewer_id') {
        return "(
            c.visibility = 'public'
            OR ($userIdParam IS NOT NULL AND c.organizer_id = $userIdParam)
            OR ($userIdParam IS NOT NULL AND EXISTS (
                SELECT 1 FROM campaign_invites ci
                WHERE ci.campaign_id = c.id
                  AND ci.invited_user_id = $userIdParam
                  AND ci.status IN ('pending', 'accepted')
            ))
        )";
    }
}
