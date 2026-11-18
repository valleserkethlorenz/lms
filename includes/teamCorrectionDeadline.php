<?php

if (!function_exists('expireTeamCorrectionDeadlines')) {
    function expireTeamCorrectionDeadlines($conn, ?int $leagueId = null): int
    {
        // Capture the rows that are about to expire so the rep and the league
        // owner can be notified even when the rep never opens the dashboard on
        // the expiry day.
        $where = "LOWER(t.status) = 'correction requested'
                  AND t.correction_due_at IS NOT NULL
                  AND t.correction_due_at < NOW()";
        $selectSql = "SELECT t.id, t.team_name, t.user_id, t.league_id,
                             l.league_name, l.created_by AS organizer_id
                        FROM tbl_team t
                        JOIN tbl_league l ON l.id = t.league_id
                       WHERE " . ($leagueId !== null ? 't.league_id = ? AND ' : '') . $where;

        $expiring = [];
        $select = $conn->prepare($selectSql);
        if ($select) {
            if ($leagueId !== null) {
                $select->bind_param('i', $leagueId);
            }
            $select->execute();
            $result = $select->get_result();
            while ($row = $result->fetch_assoc()) {
                $expiring[] = $row;
            }
            $select->close();
        }

        $affected = 0;
        if ($leagueId !== null) {
            $stmt = $conn->prepare("UPDATE tbl_team SET status='Expired',correction_expired_at=NOW() WHERE league_id=? AND LOWER(status)='correction requested' AND correction_due_at IS NOT NULL AND correction_due_at<NOW()");
            if ($stmt) {
                $stmt->bind_param('i', $leagueId);
                if ($stmt->execute()) {
                    $affected = $stmt->affected_rows;
                }
                $stmt->close();
            }
        } else {
            $stmt = $conn->prepare("UPDATE tbl_team SET status='Expired',correction_expired_at=NOW() WHERE LOWER(status)='correction requested' AND correction_due_at IS NOT NULL AND correction_due_at<NOW()");
            if ($stmt) {
                if ($stmt->execute()) {
                    $affected = $stmt->affected_rows;
                }
                $stmt->close();
            }
        }

        // Only the request that actually moved rows to Expired sends the notice.
        // A concurrent request that selected the same rows but updated none
        // must not duplicate the notification.
        if ($expiring && $affected > 0) {
            if (!function_exists('createNotification')) {
                require_once __DIR__ . '/notificationHelper.php';
            }

            try {
                foreach ($expiring as $team) {
                    $teamId      = (int) ($team['id'] ?? 0);
                    $teamName    = (string) ($team['team_name'] ?? '');
                    $leagueName  = (string) ($team['league_name'] ?? '');
                    $repId       = (int) ($team['user_id'] ?? 0);
                    $organizerId = (int) ($team['organizer_id'] ?? 0);

                    $recipients = [];
                    if ($repId > 0) {
                        $recipients[$repId] = 'The correction period for "' . $teamName . '" has expired. The application can no longer be resubmitted.';
                    }
                    if ($organizerId > 0) {
                        $recipients[$organizerId] = 'The correction deadline for "' . $teamName . '"'
                            . ($leagueName !== '' ? ' in "' . $leagueName . '"' : '')
                            . ' expired. The application is now marked Expired.';
                    }

                    foreach ($recipients as $userId => $message) {
                        $check = $conn->prepare("SELECT 1 FROM tbl_notification WHERE user_id = ? AND type = 'team_correction_expired' AND related_id = ? LIMIT 1");
                        if (!$check) {
                            continue;
                        }
                        $check->bind_param('ii', $userId, $teamId);
                        $check->execute();
                        $exists = (bool) $check->get_result()->fetch_row();
                        $check->close();
                        if (!$exists) {
                            createNotification($conn, $userId, 'team_correction_expired',
                                'Correction Deadline Expired', $message, $teamId);
                        }
                    }
                }
            } catch (Throwable $e) {
                // Notification delivery must never undo the expiry state change.
                error_log('[notification] Correction-expiry notice failed: ' . $e->getMessage());
            }
        }

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

