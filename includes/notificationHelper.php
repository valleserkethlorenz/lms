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
 * Notify organizer when a team registers or resubmits a corrected application.
 */
function notifyTeamRegistration($conn, $leagueId, $teamName, $teamId, $isResubmission = false) {
    $stmt = $conn->prepare("SELECT created_by, league_name FROM tbl_league WHERE id = ?");
    $stmt->bind_param('i', $leagueId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    $organizerId = (int) ($row['created_by'] ?? 0);
    $leagueName  = trim((string) ($row['league_name'] ?? ''));

    // Never guess a recipient. Falling back to "the first organizer" used to
    // drop another organizer's application into the wrong workspace whenever a
    // legacy league had no created_by.
    if ($organizerId <= 0) {
        error_log('[notification] Skipped registration notice for team ' . (int) $teamId . ': league ' . (int) $leagueId . ' has no owner.');
        return false;
    }

    $leagueLabel = $leagueName !== '' ? " in \"$leagueName\"" : '';
    $title = $isResubmission ? 'Team Registration Resubmitted' : 'New Team Registration';
    $message = $isResubmission
        ? "Team \"$teamName\" has resubmitted its corrected application$leagueLabel. Please review and approve."
        : "Team \"$teamName\" has registered$leagueLabel. Please review and approve.";

    return createNotification($conn, $organizerId, 'team_registration', $title, $message, $teamId);
}

/**
 * Notify team representative when team is approved
 */
function notifyTeamApproved($conn, $teamId, $teamName) {
    $stmt = $conn->prepare(
        "SELECT t.user_id, l.league_name
           FROM tbl_team t
           LEFT JOIN tbl_league l ON l.id = t.league_id
          WHERE t.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && (int) ($row['user_id'] ?? 0) > 0) {
        $leagueName = trim((string) ($row['league_name'] ?? ''));
        $message = $leagueName !== ''
            ? "Your team \"$teamName\" has been approved for \"$leagueName\"."
            : "Your team \"$teamName\" has been approved and added to the league.";
        return createNotification($conn, (int) $row['user_id'], 'team_approved',
            'Team Approved', $message, $teamId);
    }

    return false;
}

/**
 * Notify team representative when team is rejected
 */
function notifyTeamRejected($conn, $teamId, $teamName, $reason = '') {
    $stmt = $conn->prepare(
        "SELECT t.user_id, l.league_name
           FROM tbl_team t
           LEFT JOIN tbl_league l ON l.id = t.league_id
          WHERE t.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($row && (int) ($row['user_id'] ?? 0) > 0) {
        $leagueName = trim((string) ($row['league_name'] ?? ''));
        $leagueLabel = $leagueName !== '' ? " in \"$leagueName\"" : '';
        return createNotification($conn, (int) $row['user_id'], 'team_rejected',
            'Team Registration Rejected',
            "The registration for \"$teamName\"$leagueLabel was rejected and cannot be resubmitted."
                . ($reason ? " Reason: $reason" : ''),
            $teamId);
    }

    return false;
}

function notifyTeamCorrectionRequested($conn, $teamId, $teamName, $remarks) {
    $stmt = $conn->prepare(
        "SELECT t.user_id, l.league_name
           FROM tbl_team t
           LEFT JOIN tbl_league l ON l.id = t.league_id
          WHERE t.id = ? LIMIT 1"
    );
    $stmt->bind_param('i', $teamId); $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc(); $stmt->close();
    if ($row && (int) ($row['user_id'] ?? 0) > 0) {
        $leagueName = trim((string) ($row['league_name'] ?? ''));
        $leagueLabel = $leagueName !== '' ? " in \"$leagueName\"" : '';
        return createNotification($conn, (int) $row['user_id'], 'team_correction', 'Registration Returned for Correction',
            "Please correct and resubmit the registration for \"$teamName\"$leagueLabel. Remarks: $remarks", $teamId);
    }

    return false;
}

/**
 * Notify the league owner when a representative cancels a pending application.
 *
 * The team_cancelled type is already wired into every bell/inbox route map, but
 * nothing wrote it, so organizers never learned that a pending slot had been
 * withdrawn. Related_id stays the team id (the organizer URL helper resolves the
 * league from it).
 */
function notifyTeamCancelled($conn, $teamId, $teamName) {
    $stmt = $conn->prepare(
        "SELECT l.created_by, l.league_name
           FROM tbl_team t
           JOIN tbl_league l ON l.id = t.league_id
          WHERE t.id = ? LIMIT 1"
    );
    if (!$stmt) {
        error_log('[notification] Cancellation lookup failed for team ' . (int) $teamId . ': ' . ($conn->error ?? 'unknown error'));
        return false;
    }
    $stmt->bind_param('i', $teamId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    $ownerId    = (int) ($row['created_by'] ?? 0);
    $leagueName = trim((string) ($row['league_name'] ?? ''));
    if ($ownerId <= 0) {
        error_log('[notification] Skipped cancellation notice for team ' . (int) $teamId . ': the league has no owner.');
        return false;
    }

    $leagueLabel = $leagueName !== '' ? " in \"$leagueName\"" : '';
    return createNotification($conn, $ownerId, 'team_cancelled',
        'Team Registration Cancelled',
        "Team \"$teamName\"$leagueLabel has cancelled its registration application.",
        $teamId);
}

/**
 * Send the final-24-hour correction reminder to one representative.
 *
 * The expired notice is written by expireTeamCorrectionDeadlines() at the
 * moment the application actually expires, so it no longer depends on this
 * representative logging in on the expiry day.
 */
function notifyTeamCorrectionDeadlines($conn, $userId) {
    $stmt = $conn->prepare(
        "SELECT t.id, t.team_name, t.correction_due_at, l.league_name
           FROM tbl_team t
           LEFT JOIN tbl_league l ON l.id = t.league_id
          WHERE t.user_id = ?
            AND LOWER(t.status) = 'correction requested'
            AND t.correction_due_at BETWEEN NOW() AND DATE_ADD(NOW(), INTERVAL 24 HOUR)"
    );
    $stmt->bind_param('i', $userId); $stmt->execute(); $rows = $stmt->get_result();
    $sent = 0;
    while ($team = $rows->fetch_assoc()) {
        $check=$conn->prepare("SELECT 1 FROM tbl_notification WHERE user_id=? AND type='team_correction_reminder' AND related_id=? AND DATE(created)=CURDATE() LIMIT 1");
        $check->bind_param('ii',$userId,$team['id']); $check->execute(); $exists=(bool)$check->get_result()->fetch_row(); $check->close();
        $leagueName = trim((string) ($team['league_name'] ?? ''));
        $leagueLabel = $leagueName !== '' ? " in \"$leagueName\"" : '';
        if(!$exists && createNotification($conn,$userId,'team_correction_reminder','Correction Deadline Approaching','Correct and resubmit "'.$team['team_name'].'"'.$leagueLabel.' by '.date('M d, Y h:i A',strtotime($team['correction_due_at'])).'.',$team['id'])) {
            $sent++;
        }
    }
    $stmt->close();

    return $sent;
}

/**
 * Notify the representatives of a set of teams.
 *
 * Match-level notifications (publication, reschedule, result, cancellation) must
 * reach only the representatives of the teams involved. This resolver
 * deduplicates the team ids (one representative may own both teams), ignores
 * teams without an owner and writes one row per distinct user, so a league-wide
 * fan-out can never leak one team's fixture or score to another representative.
 *
 * @param int[] $teamIds
 * @return int Number of notifications written.
 */
function notifyTeamRepresentatives($conn, array $teamIds, $type, $title, $message, $relatedId = null) {
    $teamIds = array_values(array_unique(array_filter(
        array_map('intval', $teamIds),
        static function ($teamId) {
            return $teamId > 0;
        }
    )));

    if (empty($teamIds)) {
        return 0;
    }

    $placeholders = implode(',', array_fill(0, count($teamIds), '?'));
    $stmt = $conn->prepare(
        "SELECT DISTINCT user_id
           FROM tbl_team
          WHERE id IN ($placeholders)
            AND user_id IS NOT NULL
            AND user_id > 0"
    );
    if (!$stmt) {
        error_log('[notification] Team-recipient lookup failed: ' . ($conn->error ?? 'unknown error'));
        return 0;
    }

    $types = str_repeat('i', count($teamIds));
    $stmt->bind_param($types, ...$teamIds);
    if (!$stmt->execute()) {
        error_log('[notification] Team-recipient lookup failed: ' . $stmt->error);
        $stmt->close();
        return 0;
    }

    $result = $stmt->get_result();
    if (!$result) {
        $stmt->close();
        return 0;
    }

    $sent = 0;
    while ($row = $result->fetch_assoc()) {
        $recipientId = (int) ($row['user_id'] ?? 0);
        if ($recipientId > 0 && createNotification($conn, $recipientId, $type, $title, $message, $relatedId)) {
            $sent++;
        }
    }
    $stmt->close();

    return $sent;
}

/**
 * Notify only the two teams involved when their match is published.
 *
 * This used to select every approved team in the league, so each representative
 * received "Match published: Team A vs Team B" for fixtures that were not
 * theirs. The schedule id is validated against the league and the recipients are
 * resolved from team_a/team_b only.
 */
function notifyScheduleUpdate($conn, $leagueId, $scheduleId, $message) {
    $stmt = $conn->prepare(
        "SELECT team_a, team_b
           FROM tbl_schedule
          WHERE id = ? AND league_id = ?
          LIMIT 1"
    );
    if (!$stmt) {
        error_log('[notification] Schedule lookup failed for match ' . (int) $scheduleId . ': ' . ($conn->error ?? 'unknown error'));
        return 0;
    }
    $stmt->bind_param('ii', $scheduleId, $leagueId);
    $stmt->execute();
    $result = $stmt->get_result();
    $match = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$match) {
        return 0;
    }

    return notifyTeamRepresentatives(
        $conn,
        [(int) $match['team_a'], (int) $match['team_b']],
        'schedule_update',
        'Schedule Update',
        $message,
        $scheduleId
    );
}

/**
 * Notify the two teams and the assigned Committee when a result is reopened.
 *
 * Reopening happens when a protest is upheld. The Committee has to enter the
 * corrected line and both teams must know the published result is under review.
 */
function notifyResultReopened($conn, $scheduleId) {
    $stmt = $conn->prepare(
        "SELECT s.team_a, s.team_b, s.committee_user_id, s.date, s.time,
                l.league_name,
                ta.team_name AS team_a_name, tb.team_name AS team_b_name
           FROM tbl_schedule s
           JOIN tbl_league l ON l.id = s.league_id
           LEFT JOIN tbl_team ta ON ta.id = s.team_a
           LEFT JOIN tbl_team tb ON tb.id = s.team_b
          WHERE s.id = ?
          LIMIT 1"
    );
    if (!$stmt) {
        error_log('[notification] Result-reopen lookup failed for match ' . (int) $scheduleId . ': ' . ($conn->error ?? 'unknown error'));
        return 0;
    }
    $stmt->bind_param('i', $scheduleId);
    $stmt->execute();
    $result = $stmt->get_result();
    $match = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$match) {
        return 0;
    }

    $leagueName = trim((string) ($match['league_name'] ?? ''));
    $teamAName  = trim((string) ($match['team_a_name'] ?? 'Team A'));
    $teamBName  = trim((string) ($match['team_b_name'] ?? 'Team B'));
    $dateLabel  = !empty($match['date']) ? date('M d, Y', strtotime((string) $match['date'])) : '';
    $context = ($leagueName !== '' ? "\"$leagueName\": " : '')
        . "$teamAName vs $teamBName"
        . ($dateLabel !== '' ? " on $dateLabel" : '');

    $sent = notifyTeamRepresentatives(
        $conn,
        [(int) $match['team_a'], (int) $match['team_b']],
        'match_result',
        'Result Under Review',
        "The official result for $context has been reopened for correction. It will be updated once the Committee submits the corrected result.",
        $scheduleId
    );

    $committeeId = (int) ($match['committee_user_id'] ?? 0);
    if ($committeeId > 0) {
        $notified = createNotification($conn, $committeeId, 'committee_assignment',
            'Result Reopened',
            "The result for $context was reopened. Submit the corrected result.",
            $scheduleId);
        if ($notified) {
            $sent++;
        }
    }

    return $sent;
}

/**
 * Notify all approved teams in a league when the schedule is first released
 */
function notifyScheduleReleased($conn, $leagueId, $leagueName) {
    $stmt = $conn->prepare("SELECT DISTINCT user_id FROM tbl_team WHERE league_id = ? AND status = 'Approved' AND user_id IS NOT NULL AND user_id > 0");
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
function notifyNewLeague($conn, $leagueId, $leagueName, $regisEnd, $regisStart = null) {
    $endTs   = $regisEnd ? strtotime($regisEnd) : false;
    $startTs = $regisStart ? strtotime($regisStart) : false;
    $deadline = $endTs ? date('M d, Y', $endTs) : 'the published deadline';
    $todayTs  = strtotime(date('Y-m-d'));

    // Publishing a league does not require registration to have started yet.
    // Say when it opens instead of claiming it is open now.
    if ($startTs && $todayTs && $startTs > $todayTs) {
        $timing = 'Registration opens on ' . date('M d, Y', $startTs) . " and closes on $deadline.";
    } elseif ($endTs) {
        $timing = "Registration is open now until $deadline.";
    } else {
        $timing = 'Registration is open now.';
    }

    // Only active team representatives receive the broadcast.
    $stmt = $conn->prepare("SELECT id FROM tbl_user WHERE role = 2 AND stat = '1'");
    $stmt->execute();
    $result = $stmt->get_result();
    $sent = 0;
    while ($row = $result->fetch_assoc()) {
        if (createNotification($conn, $row['id'], 'announcement',
            'New League Available',
            "A new league \"$leagueName\" is available. $timing",
            $leagueId)) {
            $sent++;
        }
    }
    $stmt->close();

    return $sent;
}

/**
 * Notify team representatives whose league registration is closing in X days
 * Call this daily (e.g. on dashboard load or via cron)
 */
function notifyRegistrationClosingSoon($conn, $daysAhead = 3) {
    $targetDate = date('Y-m-d', strtotime("+$daysAhead days"));
    // Only leagues a representative can actually join right now: published,
    // not archived, registration explicitly open, already started, and with a
    // free slot. Draft or manually-closed leagues used to reach reps here.
    $stmt = $conn->prepare(
        "SELECT l.id, l.league_name, l.regis_end
           FROM tbl_league l
          WHERE DATE(l.regis_end) = ?
            AND l.publication_status = 'Published'
            AND (l.is_archived = 0 OR l.is_archived IS NULL)
            AND l.registration_window_open = 1
            AND l.regis_start <= CURDATE()
            AND (l.max_team IS NULL OR l.max_team <= 0
                 OR (SELECT COUNT(*) FROM tbl_team ta
                      WHERE ta.league_id = l.id AND LOWER(ta.status) = 'approved') < l.max_team)"
    );
    $stmt->bind_param('s', $targetDate);
    $stmt->execute();
    $leagues = $stmt->get_result();
    $stmt->close();

    $sent = 0;
    while ($league = $leagues->fetch_assoc()) {
        $deadline = date('M d, Y', strtotime($league['regis_end']));
        // Notify active TR users without an active application in this league.
        // Rejected/cancelled/expired rows do not count as "already registered":
        // those representatives are free to apply again. NOT EXISTS instead of
        // NOT IN keeps nullable user ids safe (migration 026).
        $uStmt = $conn->prepare(
            "SELECT u.id FROM tbl_user u
              WHERE u.role = 2
                AND u.stat = '1'
                AND NOT EXISTS (
                    SELECT 1 FROM tbl_team t
                     WHERE t.league_id = ?
                       AND t.user_id = u.id
                       AND LOWER(t.status) NOT IN ('rejected', 'cancelled', 'canceled', 'expired')
                )"
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
            if (!$exists && createNotification($conn, $user['id'], 'registration_closing',
                'Registration Closing Soon',
                "Registration for \"" . $league['league_name'] . "\" closes on $deadline. Register your team now!",
                $league['id'])) {
                $sent++;
            }
        }
    }

    return $sent;
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
    // published_at / archived guards keep drafts and voided fixtures out.
    $stmt = $conn->prepare(
        "SELECT s.id, s.team_a, s.team_b, s.time, s.venue, s.league_id,
                l.league_name,
                ta.team_name AS team_a_name, tb.team_name AS team_b_name
           FROM tbl_schedule s
           JOIN tbl_team ta ON ta.id = s.team_a
           JOIN tbl_team tb ON tb.id = s.team_b
           JOIN tbl_league l ON l.id = s.league_id
          WHERE DATE(s.date) = ?
            AND LOWER(s.status) IN ('scheduled', 'upcoming')
            AND s.published_at IS NOT NULL
            AND (s.is_archived = 0 OR s.is_archived IS NULL)
            AND (l.is_archived = 0 OR l.is_archived IS NULL)
            AND (s.date > CURDATE() OR s.time >= CURTIME())"
    );
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $matches = $stmt->get_result();
    $stmt->close();

    $sent = 0;
    while ($match = $matches->fetch_assoc()) {
        $time = date('h:i A', strtotime($match['time']));
        $venueStr = $match['venue'] ? " at " . $match['venue'] : '';
        $leagueLabel = trim((string) ($match['league_name'] ?? ''));
        $message = 'You have a match today'
            . ($leagueLabel !== '' ? " in \"$leagueLabel\"" : '')
            . ': ' . $match['team_a_name'] . ' vs ' . $match['team_b_name'] . " at $time" . $venueStr . '.';

        foreach ([$match['team_a'], $match['team_b']] as $teamId) {
            $uStmt = $conn->prepare("SELECT user_id FROM tbl_team WHERE id = ?");
            $uStmt->bind_param('i', $teamId);
            $uStmt->execute();
            $uRow = $uStmt->get_result()->fetch_assoc();
            $uStmt->close();
            $recipientId = (int) ($uRow['user_id'] ?? 0);
            if ($recipientId <= 0) continue;

            // Avoid duplicate today reminders
            $chk = $conn->prepare(
                "SELECT id FROM tbl_notification WHERE user_id = ? AND type = 'upcoming_match' AND related_id = ? AND DATE(created) = CURDATE()"
            );
            $chk->bind_param('ii', $recipientId, $match['id']);
            $chk->execute();
            $exists = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$exists && createNotification($conn, $recipientId, 'upcoming_match', 'Match Today', $message, $match['id'])) {
                $sent++;
            }
        }
    }

    return $sent;
}

/**
 * Notify each Committee member about the matches they officiate today.
 */
function notifyCommitteeUpcomingMatchToday($conn) {
    $today = date('Y-m-d');
    $stmt = $conn->prepare(
        "SELECT s.id, s.committee_user_id, s.time, s.venue,
                l.league_name,
                ta.team_name AS team_a_name, tb.team_name AS team_b_name
           FROM tbl_schedule s
           JOIN tbl_league l ON l.id = s.league_id
           JOIN tbl_team ta ON ta.id = s.team_a
           JOIN tbl_team tb ON tb.id = s.team_b
          WHERE DATE(s.date) = ?
            AND s.committee_user_id IS NOT NULL
            AND s.committee_user_id > 0
            AND s.published_at IS NOT NULL
            AND LOWER(s.status) IN ('scheduled', 'upcoming')
            AND (s.is_archived = 0 OR s.is_archived IS NULL)
            AND (l.is_archived = 0 OR l.is_archived IS NULL)
            AND (s.date > CURDATE() OR s.time >= CURTIME())"
    );
    $stmt->bind_param('s', $today);
    $stmt->execute();
    $matches = $stmt->get_result();
    $stmt->close();

    $sent = 0;
    while ($match = $matches->fetch_assoc()) {
        $committeeId = (int) ($match['committee_user_id'] ?? 0);
        if ($committeeId <= 0) {
            continue;
        }
        $time = date('h:i A', strtotime((string) $match['time']));
        $venueStr = $match['venue'] ? ' at ' . $match['venue'] : '';
        $leagueLabel = trim((string) ($match['league_name'] ?? ''));
        $message = 'You are officiating today'
            . ($leagueLabel !== '' ? " in \"$leagueLabel\"" : '')
            . ': ' . $match['team_a_name'] . ' vs ' . $match['team_b_name'] . " at $time" . $venueStr . '.';

        $chk = $conn->prepare(
            "SELECT id FROM tbl_notification WHERE user_id = ? AND type = 'upcoming_match' AND related_id = ? AND DATE(created) = CURDATE()"
        );
        $chk->bind_param('ii', $committeeId, $match['id']);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$exists && createNotification($conn, $committeeId, 'upcoming_match', 'Match Today', $message, $match['id'])) {
            $sent++;
        }
    }

    return $sent;
}

/**
 * Remind each Committee member about assigned matches whose result is overdue.
 *
 * A match is overdue when its scheduled date/time has passed by at least three
 * hours, it is still published/not completed/cancelled, and no result was
 * submitted. The grace period avoids asking for a result while the match is
 * still being played; the seven-day window keeps an old backlog from generating
 * an endless daily reminder.
 */
function notifyCommitteePendingResults($conn) {
    $stmt = $conn->prepare(
        "SELECT s.id, s.committee_user_id, s.date, s.time, s.venue,
                l.league_name,
                ta.team_name AS team_a_name, tb.team_name AS team_b_name
           FROM tbl_schedule s
           JOIN tbl_league l ON l.id = s.league_id
           JOIN tbl_team ta ON ta.id = s.team_a
           JOIN tbl_team tb ON tb.id = s.team_b
          WHERE s.committee_user_id IS NOT NULL
            AND s.committee_user_id > 0
            AND s.published_at IS NOT NULL
            AND (s.is_archived = 0 OR s.is_archived IS NULL)
            AND (l.is_archived = 0 OR l.is_archived IS NULL)
            AND s.result_submitted_by IS NULL
            AND LOWER(s.status) NOT IN ('completed', 'cancelled', 'canceled', 'postponed')
            AND CONCAT(s.date, ' ', s.time) < DATE_SUB(NOW(), INTERVAL 3 HOUR)
            AND s.date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)"
    );
    $stmt->execute();
    $matches = $stmt->get_result();
    $stmt->close();

    $sent = 0;
    while ($match = $matches->fetch_assoc()) {
        $committeeId = (int) ($match['committee_user_id'] ?? 0);
        if ($committeeId <= 0) {
            continue;
        }
        $dateLabel = !empty($match['date']) ? date('M d, Y', strtotime((string) $match['date'])) : 'the scheduled date';
        $timeLabel = !empty($match['time']) ? date('h:i A', strtotime((string) $match['time'])) : '';
        $leagueLabel = trim((string) ($match['league_name'] ?? ''));
        $message = 'The result for '
            . ($leagueLabel !== '' ? "\"$leagueLabel\": " : '')
            . $match['team_a_name'] . ' vs ' . $match['team_b_name']
            . " on $dateLabel" . ($timeLabel !== '' ? " at $timeLabel" : '')
            . ' has not been submitted. Please enter it.';

        $chk = $conn->prepare(
            "SELECT id FROM tbl_notification WHERE user_id = ? AND type = 'result_reminder' AND related_id = ? AND DATE(created) = CURDATE()"
        );
        $chk->bind_param('ii', $committeeId, $match['id']);
        $chk->execute();
        $exists = $chk->get_result()->fetch_assoc();
        $chk->close();
        if (!$exists && createNotification($conn, $committeeId, 'result_reminder', 'Result Needed', $message, $match['id'])) {
            $sent++;
        }
    }

    return $sent;
}

/**
 * Notify all teams in a league about announcement
 */
function notifyAnnouncement($conn, $leagueId, $announcementId, $title, $message) {
    $stmt = $conn->prepare("SELECT DISTINCT user_id FROM tbl_team WHERE league_id = ? AND status = 'Approved' AND user_id IS NOT NULL AND user_id > 0");
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
        'team_cancelled'           => 'league_info.php',
        'team_correction'          => 'pending_teams.php',
        'team_correction_reminder' => 'pending_teams.php',
        'team_correction_expired'  => 'league_info.php',
        'team_approved'            => 'league_info.php',
        'team_rejected'            => 'league_info.php',
        'announcement'             => 'league_info.php',
        'league_full'              => 'league_info.php',
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

    // Fixture notices open the public schedule board, score notices the
    // standings table. Both render the TR portal chrome for role 2. The path is
    // relative to app/tr/ pages; root pages prepend their routeBase (app/tr/),
    // which resolves to the same files.
    $scheduleTypes = [
        'schedule_update', 'schedule_updated', 'upcoming_match', 'committee_assignment',
    ];
    $resultTypes = [
        'match_result', 'round_complete', 'group_stage_complete', 'tournament_complete',
    ];
    $leagueInfoTypes = ['announcement', 'registration_closing'];

    if (!in_array($type, $scheduleTypes, true)
        && !in_array($type, $resultTypes, true)
        && !in_array($type, $leagueInfoTypes, true)) {
        return 'dashboard.php';
    }

    $leagueId = notificationLeagueId($conn, $type, $relatedId, $title);
    if ($leagueId <= 0) {
        return 'browse_leagues.php';
    }

    if (in_array($type, $scheduleTypes, true)) {
        return '../../schedule.php?id=' . $leagueId;
    }
    if (in_array($type, $resultTypes, true)) {
        return '../../standing.php?id=' . $leagueId;
    }

    return '../../team_information.php?id=' . $leagueId;
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
        'upcoming_match', 'match_result', 'result_reminder',
    ];

    return (in_array($type, $matchTypes, true) && $relatedId > 0)
        ? 'manage_match.php?id=' . $relatedId
        : 'matches.php';
}
?>
