<?php

if (!function_exists('getTeamRegistrationBadgeClass')) {
    function getTeamRegistrationBadgeClass($status)
    {
        return match (strtolower(trim((string) $status))) {
            'approved' => 'registration-status-approved',
            'rejected' => 'registration-status-rejected',
            'cancelled', 'canceled' => 'registration-status-cancelled',
            'correction requested' => 'registration-status-correction',
            'expired' => 'registration-status-expired',
            'pending' => 'registration-status-pending',
            default => 'registration-status-default',
        };
    }
}
