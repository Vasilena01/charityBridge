<?php
if (!function_exists('can_view_campaign')) {
    function can_view_campaign($campaign, $userId, $pdo) {
        if (!$campaign) return false;
        $uid = $userId ? (int)$userId : 0;

        if ($uid && (int)$campaign['organizer_id'] === $uid) return true;
        if ($campaign['status'] === 'draft') return false;
        return true;
    }
}

if (!function_exists('can_act_on_campaign')) {
    function can_act_on_campaign($campaign, $userId, $pdo) {
        if (!$campaign) return false;
        if ($campaign['status'] !== 'published') return false;
        $uid = $userId ? (int)$userId : 0;
        if (!$uid) return false;
        if ((int)$campaign['organizer_id'] === $uid) return false;
        return true;
    }
}
