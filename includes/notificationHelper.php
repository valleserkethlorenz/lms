<?php
/**
 * Notification Helper Functions
 * Used to create notifications for various events
 *
 * A notification is always a side effect of a business action (approving a
 * team, publishing a result, releasing a schedule) and must never be the reason
 * that action fails. createNotification() therefore validates its input, writes
 * at most one row and returns false (logging why) instead of throwing: a missing
 * recipient or a failed insert can no longer roll back the transaction of the
 * caller — committee result submission and match publication both write inside
 * an open transaction.
 */

function createNotification($conn, $userId, $type, $title, $message, $relatedId = null) {
    $userId  = (int) $userId;
    $type    = trim((string) $type);
    $title   = trim((string) $title);
    $message = (string) $message;

    // tbl_notification.user_id is NOT NULL with a foreign key to tbl_user, so an
    // unknown/empty recipient (e.g. a team row whose user_id is NULL) has to be
    // skipped instead of written as user 0.
    if ($userId <= 0 || $type === '' || $title === '') {
        error_log('[notification] Skipped insert: invalid recipient (' . $userId . '), type "' . $type . '" or empty title.');
        return false;
    }

    // Keep the values inside their column widths (type varchar(50),
    // title varchar(255)) so one long subject can never abort the insert.
    if (mb_strlen($type) > 50) {
        $type = mb_substr($type, 0, 50);
    }
    if (mb_strlen($title) > 255) {
        $title = mb_substr($title, 0, 252) . '...';
    }

    // related_id is nullable: 0 / '' / null all mean "no related record".
    $relatedId = ($relatedId === null || (int) $relatedId <= 0) ? null : (int) $relatedId;

    $sql = "INSERT INTO tbl_notification (user_id, type, title, message, related_id) VALUES (?, ?, ?, ?, ?)";

    $stmt = null;
    try {
        $stmt = $conn->prepare($sql);
        if (!$stmt) {
            error_log('[notification] Prepare failed for user ' . $userId . ': ' . ($conn->error ?? 'unknown error'));
            return false;
        }

        $stmt->bind_param('isssi', $userId, $type, $title, $message, $relatedId);
        if (!$stmt->execute()) {
            error_log('[notification] Insert failed for user ' . $userId . ' (type ' . $type . '): ' . $stmt->error);
            return false;
        }
    } catch (Throwable $e) {
        // PHP 8.1+ makes mysqli report errors by throwing (MYSQLI_REPORT_STRICT),
        // so a foreign-key/column failure arrives here instead of as a false
        // return value. Swallow it: the caller's transaction must survive.
        error_log('[notification] Insert failed for user ' . $userId . ' (type ' . $type . '): ' . $e->getMessage());
        return false;
    } finally {
        if (is_object($stmt) && method_exists($stmt, 'close')) {
            $stmt->close();
        }
    }

    return true;
}

/**
 * Unread counter shared by the AJAX endpoint, the bell badge and the dashboards.
 */
function notificationUnreadCount(mysqli $conn, int $userId): int {
    if ($userId <= 0) {
        return 0;
    }
    $stmt = $conn->prepare("SELECT COUNT(*) AS c FROM tbl_notification WHERE user_id = ? AND is_read = 0");
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['c'] ?? 0);
}

/**
 * Notify organizer when team registers
 */
function notifyTeamRegistration($conn, $leagueId, $teamName, $teamId) {
    $sql = "SELECT created_by FROM tbl_league WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $leagueId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $organizerId = $row['created_by'] ?? null;

    // Fallback: find any organizer (role=1) if created_by is not set
    if (empty($organizerId)) {
        $fStmt = $conn->prepare("SELECT id FROM tbl_user WHERE role = 1 LIMIT 1");
        $fStmt->execute();
        $fRow = $fStmt->get_result()->fetch_assoc();
        $fStmt->close();
        $organizerId = $fRow['id'] ?? null;
    }

    if ($organizerId) {
        createNotification($conn, $organizerId, 'team_registration',
            "New Team Registration",
            "Team \"$teamName\" has registered for your league. Please review and approve.",
            $teamId);
    }
}

/**
 * Notify team representative when team is approved
 */
function notifyTeamApproved($conn, $teamId, $teamName) {
    $sql = "SELECT user_id FROM tbl_team WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        createNotification($conn, $row['user_id'], 'team_approved',
            "Team Approved",
            "Your team \"$teamName\" has been approved and added to the tournament bracket.",
            $teamId);
    }
}

/**
 * Notify team representative when team is rejected
 */
function notifyTeamRejected($conn, $teamId, $teamName, $reason = '') {
    $sql = "SELECT user_id FROM tbl_team WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    
    if ($row) {
        createNotification($conn, $row['user_id'], 'team_rejected',
            "Team Registration Rejected",
            "Your team \"$teamName\" registration has been permanently rejected and cannot be resubmitted." . ($reason ? " Reason: $reason" : ""),
            $teamId);
    }
}

function notifyTeamCorrectionRequested($conn, $teamId, $teamName, $remarks) {
    $stmt = $conn->prepare("SELECT user_id FROM tbl_team WHERE id=?");
    $stmt->bind_param('i', $teamId); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($row) {
        createNotification($conn, (int)$row['user_id'], 'team_correction', 'Registration Returned for Correction',
            "Please correct and resubmit the registration for \"$teamName\". Remarks: $remarks", $teamId);
    }
}

/** Send one reminder within the final 24 hours and one notice after expiration. */
function notifyTeamCorrectionDeadlines($conn, $userId) {
    $stmt = $conn->prepare("SELECT id,team_name,correction_due_at FROM tbl_team WHERE user_id=? AND LOWER(status)='correction requested' AND correction_due_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 24 HOUR)");
    $stmt->bind_param('i', $userId); $stmt->execute(); $rows = $stmt->get_result();
    while ($team = $rows->fetch_assoc()) {
        $check=$conn->prepare("SELECT 1 FROM tbl_notification WHERE user_id=? AND type='team_correction_reminder' AND related_id=? AND DATE(created)=CURDATE() LIMIT 1");
        $check->bind_param('ii',$userId,$team['id']); $check->execute(); $exists=(bool)$check->get_result()->fetch_row(); $check->close();
        if(!$exists) createNotification($conn,$userId,'team_correction_reminder','Correction Deadline Approaching','Correct and resubmit "'.$team['team_name'].'" by '.date('M d, Y h:i A',strtotime($team['correction_due_at'])).'.',$team['id']);
    }
    $stmt->close();

    $stmt = $conn->prepare("SELECT id,team_name FROM tbl_team WHERE user_id=? AND LOWER(status)='expired' AND DATE(correction_expired_at)=CURDATE()");
    $stmt->bind_param('i', $userId); $stmt->execute(); $rows = $stmt->get_result();
    while ($team = $rows->fetch_assoc()) {
        $check=$conn->prepare("SELECT 1 FROM tbl_notification WHERE user_id=? AND type='team_correction_expired' AND related_id=? LIMIT 1");
        $check->bind_param('ii',$userId,$team['id']); $check->execute(); $exists=(bool)$check->get_result()->fetch_row(); $check->close();
        if(!$exists) createNotification($conn,$userId,'team_correction_expired','Correction Deadline Expired','The correction period for "'.$team['team_name'].'" has expired. The application can no longer be resubmitted.',$team['id']);
    }
    $stmt->close();
}

/**
 * Notify all approved teams in a league about a schedule update
 */
function notifyScheduleUpdate($conn, $leagueId, $scheduleId, $message) {
    $stmt = $conn->prepare("SELECT user_id FROM tbl_team WHERE league_id = ? AND status = 'Approved'");
    $stmt->bind_param('i', $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        createNotification($conn, $row['user_id'], 'schedule_update', 'Schedule Update', $message, $scheduleId);
    }
    $stmt->close();
}

/**
 * Notify only the two teams involved when their match is rescheduled
 */
function notifyMatchRescheduled($conn, $scheduleId, $teamAId, $teamBId, $newDate, $newTime, $venue = null) {
    $venueStr = $venue ? " at $venue" : '';
    $message = "Your match has been rescheduled to " . date('M d, Y', strtotime($newDate)) . " at " . date('h:i A', strtotime($newTime)) . $venueStr . ".";

    foreach ([$teamAId, $teamBId] as $teamId) {
        $stmt = $conn->prepare("SELECT user_id FROM tbl_team WHERE id = ?");
        $stmt->bind_param('i', $teamId);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        if ($row) {
            createNotification($conn, $row['user_id'], 'schedule_update', 'Match Rescheduled', $message, $scheduleId);
        }
    }
}

/**
 * Notify all approved teams in a league when the schedule is first released
 */
function notifyScheduleReleased($conn, $leagueId, $leagueName) {
    $stmt = $conn->prepare("SELECT user_id FROM tbl_team WHERE league_id = ? AND status = 'Approved'");
    $stmt->bind_param('i', $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        createNotification($conn, $row['user_id'], 'schedule_update',
            'Schedule Released',
            "The match schedule for \"$leagueName\" has been released. Check your upcoming matches.",
            $leagueId);
    }
    $stmt->close();
}

/**
 * Notify all existing team representatives about a new league opening
 */
function notifyNewLeague($conn, $leagueId, $leagueName, $regisEnd) {
    $deadline = date('M d, Y', strtotime($regisEnd));
    // Notify all TR users (role = 2)
    $stmt = $conn->prepare("SELECT id FROM tbl_user WHERE role = 2");
    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        createNotification($conn, $row['id'], 'announcement',
            'New League Available',
            "A new league \"$leagueName\" is now open for registration. Deadline: $deadline.",
            $leagueId);
    }
    $stmt->close();
}

/**
 * Notify team representatives whose league registration is closing in X days
 * Call this daily (e.g. on dashboard load or via cron)
 */
function notifyRegistrationClosingSoon($conn, $daysAhead = 3) {
    $targetDate = date('Y-m-d', strtotime("+$daysAhead days"));
    // Find leagues whose regis_end is exactly $daysAhead days from now
    $stmt = $conn->prepare("SELECT id, league_name, regis_end FROM tbl_league WHERE DATE(regis_end) = ?");
    $stmt->bind_param('s', $targetDate);
    $stmt->execute();
    $leagues = $stmt->get_result();
    $stmt->close();

    while ($league = $leagues->fetch_assoc()) {
        $deadline = date('M d, Y', strtotime($league['regis_end']));
        // Notify all TR users who have NOT yet registered in this league
        // NOT EXISTS instead of NOT IN: tbl_team.user_id is nullable (migration
        // 026), and one NULL inside a NOT IN sub-query makes the whole predicate
        // UNKNOWN — which silently suppressed every "closing soon" reminder.
        $uStmt = $conn->prepare(
            "SELECT u.id FROM tbl_user u
              WHERE u.role = 2
                AND NOT EXISTS (SELECT 1 FROM tbl_team t WHERE t.league_id = ? AND t.user_id = u.id)"
        );
        $uStmt->bind_param('i', $league['id']);
        $uStmt->execute();
        $users = $uStmt->get_result();
        $uStmt->close();

        while ($user = $users->fetch_assoc()) {
            // Avoid duplicate notifications for the same league+user
            $chk = $conn->prepare(
                "SELECT id FROM tbl_notification WHERE user_id = ? AND type = 'registration_closing' AND related_id = ? AND DATE(created) = CURDATE()"
            );
            $chk->bind_param('ii', $user['id'], $league['id']);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$exists) {
                createNotification($conn, $user['id'], 'registration_closing',
                    'Registration Closing Soon',
                    "Registration for \"" . $league['league_name'] . "\" closes on $deadline. Register your team now!",
                    $league['id']);
            }
        }
    }
}

/**
 * Notify team representatives about their matches scheduled for today
 * Call this daily (e.g. on dashboard load)
 */
function notifyUpcomingMatchToday($conn) {
    $today = date('Y-m-d');
    // Only matches that have not started yet: the reminder is fired from the
    // dashboard, so without the time guard a representative signing in in the
    // evening was told "You have a match today at 8:00 AM" long after it ended.
    $stmt = $conn->prepare(
        "SELECT s.id, s.team_a, s.team_b, s.time, s.venue, s.league_id,
                ta.team_name AS team_a_name, tb.team_name AS team_b_name
         FROM tbl_schedule s
         JOIN tbl_team ta ON ta.id = s.team_a
         JOIN tbl_team tb ON tb.id = s.team_b
         WHERE DATE(s.date) = ? AND s.status = 'Scheduled'
           AND (s.date > CURDATE() OR s.time >= CURTIME())"
    );
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $matches = $stmt->get_result();
    $stmt->close();

    while ($match = $matches->fetch_assoc()) {
        $time = date('h:i A', strtotime($match['time']));
        $venueStr = $match['venue'] ? " at " . $match['venue'] : '';
        $message = "You have a match today: " . $match['team_a_name'] . " vs " . $match['team_b_name'] . " at $time" . $venueStr . ".";

        foreach ([$match['team_a'], $match['team_b']] as $teamId) {
            $uStmt = $conn->prepare("SELECT user_id FROM tbl_team WHERE id = ?");
            $uStmt->bind_param('i', $teamId);
            $uStmt->execute();
            $uRow = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();
            if (!$uRow) continue;

            // Avoid duplicate today reminders
            $chk = $conn->prepare(
                "SELECT id FROM tbl_notification WHERE user_id = ? AND type = 'upcoming_match' AND related_id = ? AND DATE(created) = CURDATE()"
            );
            $chk->bind_param('ii', $uRow['user_id'], $match['id']);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$exists) {
                createNotification($conn, $uRow['user_id'], 'upcoming_match', 'Match Today', $message, $match['id']);
            }
        }
    }
}

/**
 * Notify all teams in a league about announcement
 */
function notifyAnnouncement($conn, $leagueId, $announcementId, $title, $message) {
    $stmt = $conn->prepare("SELECT user_id FROM tbl_team WHERE league_id = ? AND status = 'Approved'");
    $stmt->bind_param('i', $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();

    // Publishing the same announcement twice (double submit, retry after a
    // timeout) must not ring the bell twice for every team. The title is part of
    // the key because "announcement" rows also carry league ids for
    // notifyNewLeague(), so related_id alone is ambiguous.
    // Compare the value createNotification() actually stores (varchar(255)).
    $storedTitle = mb_strlen($title) > 255 ? mb_substr($title, 0, 252) . '...' : $title;
    $dupe = $conn->prepare(
        "SELECT 1 FROM tbl_notification
          WHERE user_id = ? AND type = 'announcement' AND related_id = ? AND title = ?
          LIMIT 1"
    );

    while ($row = $result->fetch_assoc()) {
        $recipientId = (int) ($row['user_id'] ?? 0);
        if ($recipientId <= 0) {
            continue;
        }
        if ($dupe) {
            $dupe->bind_param('iis', $recipientId, $announcementId, $storedTitle);
            $dupe->execute();
            $exists = (bool) $dupe->get_result()->fetch_row();
            if ($exists) {
                continue;
            }
        }
        createNotification($conn, $recipientId, 'announcement', $title, $message, $announcementId);
    }

    if ($dupe) {
        $dupe->close();
    }
    $stmt->close();
}

/**
 * Notify organizer about announcement creation
 */
function notifyOrganizerAnnouncement($conn, $leagueId, $announcementId, $title, $message) {
    $stmt = $conn->prepare("SELECT created_by FROM tbl_league WHERE id = ?");
    $stmt->bind_param('i', $leagueId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($row && !empty($row['created_by'])) {
        createNotification($conn, $row['created_by'], 'announcement', $title, $message, $announcementId);
    }
}

/**
 * Resolve the league a notification refers to.
 *
 * tbl_notification.related_id stores the id of the record that triggered the
 * notification, which is not always a league id: team notifications carry a
 * team id, schedule notifications a schedule id and announcements an
 * announcement id. Organizer pages need the league id, so translate here.
 */
function notificationLeagueId(mysqli $conn, string $type, int $relatedId, string $title = ''): int {
    if ($relatedId <= 0) {
        return 0;
    }

    // These older notification variants store a league id directly.
    if (($type === 'announcement' && $title === 'New League Available') ||
        ($type === 'schedule_update' && $title === 'Schedule Released')) {
        return $relatedId;
    }

    $teamTypes = [
        'team_registration', 'team_cancelled', 'team_correction',
        'team_correction_reminder', 'team_correction_expired',
        'team_approved', 'team_rejected',
    ];
    $scheduleTypes = [
        'schedule_update', 'schedule_updated', 'committee_assignment',
        'upcoming_match', 'match_result',
    ];

    if (in_array($type, $teamTypes, true)) {
        $sql = "SELECT league_id FROM tbl_team WHERE id = ? LIMIT 1";
    } elseif (in_array($type, $scheduleTypes, true)) {
        $sql = "SELECT league_id FROM tbl_schedule WHERE id = ? LIMIT 1";
    } elseif ($type === 'announcement') {
        $sql = "SELECT league_id FROM tbl_announcement WHERE id = ? LIMIT 1";
    } else {
        // Round/group/tournament milestones and registration reminders already
        // carry a league id in related_id.
        return $relatedId;
    }

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $relatedId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return (int) ($row['league_id'] ?? 0);
}

/**
 * Build the organizer page a notification should open.
 *
 * Organizer pages (pending_teams.php, league_info.php, schedule.php,
 * standing.php) expect the league id in `?id=`. Linking them with the raw
 * related_id used to send `?notif_id=<team id>` (or no id at all), which made
 * them reject the league with "That league belongs to another organizer."
 */
function organizerNotificationUrl(mysqli $conn, string $type, int $relatedId): string {
    $pages = [
        'team_registration'        => 'pending_teams.php',
        'team_cancelled'           => 'pending_teams.php',
        'team_correction'          => 'pending_teams.php',
        'team_correction_reminder' => 'pending_teams.php',
        'team_correction_expired'  => 'pending_teams.php',
        'team_approved'            => 'league_info.php',
        'team_rejected'            => 'league_info.php',
        'announcement'             => 'league_info.php',
        'schedule_updated'         => 'schedule.php',
        'schedule_update'          => 'schedule.php',
        'upcoming_match'           => 'schedule.php',
        'committee_assignment'     => 'schedule.php',
        'match_result'             => 'standing.php',
        'round_complete'           => 'standing.php',
        'group_stage_complete'     => 'standing.php',
        'tournament_complete'      => 'standing.php',
        'registration_closing'     => 'league.php',
    ];

    $page = $pages[$type] ?? 'dashboard.php';

    // These pages do not need a league id.
    if ($page === 'dashboard.php' || $page === 'league.php') {
        return $page;
    }

    $leagueId = notificationLeagueId($conn, $type, $relatedId);
    return $leagueId > 0 ? $page . '?id=' . $leagueId : 'league.php';
}

/** Build a TR portal-relative destination shared by the bell and dashboard. */
function teamRepNotificationUrl(mysqli $conn, string $type, int $relatedId, string $title = ''): string {
    $teamTypes = [
        'team_registration', 'team_cancelled', 'team_approved', 'team_rejected',
        'team_correction', 'team_correction_reminder', 'team_correction_expired',
    ];
    if (in_array($type, $teamTypes, true)) {
        return $relatedId > 0 ? 'view_team.php?id=' . $relatedId : 'league.php';
    }

    $leagueTypes = [
        'announcement', 'registration_closing', 'schedule_update', 'schedule_updated',
        'upcoming_match', 'committee_assignment', 'match_result', 'round_complete',
        'group_stage_complete', 'tournament_complete',
    ];
    if (!in_array($type, $leagueTypes, true)) {
        return 'dashboard.php';
    }

    $leagueId = notificationLeagueId($conn, $type, $relatedId, $title);
    return $leagueId > 0 ? '../../team_information.php?id=' . $leagueId : 'browse_leagues.php';
}
/**
 * Build the committee page a notification should open.
 *
 * Committee pages take the schedule id in `?id=` when a single match is meant
 * (assignments, schedule changes, reminders and results); round/tournament
 * milestones have no per-match page, so they open the assigned-match list.
 */
function committeeNotificationUrl(string $type, int $relatedId): string {
    if ($type === 'announcement') {
        return 'dashboard.php';
    }

    $matchTypes = [
        'committee_assignment', 'schedule_updated', 'schedule_update',
        'upcoming_match', 'match_result',
    ];

    return (in_array($type, $matchTypes, true) && $relatedId > 0)
        ? 'manage_match.php?id=' . $relatedId
        : 'matches.php';
}
?>
