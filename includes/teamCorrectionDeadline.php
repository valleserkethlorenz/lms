<?php

if (!function_exists('expireTeamCorrectionDeadlines')) {
    function expireTeamCorrectionDeadlines(mysqli $conn, ?int $leagueId = null): int
    {
        if ($leagueId !== null) {
            $stmt = $conn->prepare("UPDATE tbl_team SET status='Expired',correction_expired_at=NOW() WHERE league_id=? AND LOWER(status)='correction requested' AND correction_due_at IS NOT NULL AND correction_due_at<NOW()");
            $stmt->bind_param('i', $leagueId);
        } else {
            $stmt = $conn->prepare("UPDATE tbl_team SET status='Expired',correction_expired_at=NOW() WHERE LOWER(status)='correction requested' AND correction_due_at IS NOT NULL AND correction_due_at<NOW()");
        }
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        return $affected;
    }
}

if (!function_exists('leagueHasOpenCorrections')) {
    function leagueHasOpenCorrections(mysqli $conn, int $leagueId): bool
    {
        expireTeamCorrectionDeadlines($conn, $leagueId);
        $stmt = $conn->prepare("SELECT 1 FROM tbl_team WHERE league_id=? AND LOWER(status)='correction requested' AND correction_due_at>=NOW() LIMIT 1");
        $stmt->bind_param('i', $leagueId);
        $stmt->execute();
        $exists = (bool) $stmt->get_result()->fetch_row();
        $stmt->close();
        return $exists;
    }
}

