<?php
/**
 * Notification recipient-scoping smoke check
 * =========================================
 * Verifies the rule that a match notification reaches only the representatives
 * of the two teams in that match:
 *   1. the participant lookup resolves the two team owners;
 *   2. an unrelated approved team in the same league is identified;
 *   3. notifyScheduleUpdate() writes one row per participant and none for the
 *      unrelated representative;
 *   4. the daily reminder queries (match day, registration closing, correction
 *      expiry) execute against the live schema.
 *
 * Everything runs inside transactions that are rolled back, so the database is
 * left exactly as it was.
 *
 * Usage (from the lms/ directory):  php scripts/notification_scoping_smoke.php
 * Exit code 1 means a check reported an unexpected value.
 */
require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/../includes/notificationHelper.php';

$failures = 0;

function row(string $label, $value, $expected = true, bool $check = false): void {
    global $failures;
    $text = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
    echo $label . ': ' . $text . PHP_EOL;
    if ($check && $value !== $expected) {
        $failures++;
        echo '  ^ expected ' . var_export($expected, true) . PHP_EOL;
    }
}

// 1. Pick a match whose two teams both have a representative.
$match = $conn->query(
    "SELECT s.id, s.league_id, s.team_a, s.team_b
       FROM tbl_schedule s
       JOIN tbl_team ta ON ta.id = s.team_a
       JOIN tbl_team tb ON tb.id = s.team_b
      WHERE ta.user_id IS NOT NULL AND tb.user_id IS NOT NULL
        AND ta.user_id > 0 AND tb.user_id > 0
      ORDER BY s.published_at IS NULL, s.id DESC
      LIMIT 1"
)->fetch_assoc();

if (!$match) {
    row('eligible_match', 'skipped: no match with two owned teams in this database');
    exit(0);
}

$scheduleId = (int) $match['id'];
$leagueId   = (int) $match['league_id'];
$teamAId    = (int) $match['team_a'];
$teamBId    = (int) $match['team_b'];
row('schedule_id', $scheduleId);
row('league_id', $leagueId);
row('team_a', $teamAId);
row('team_b', $teamBId);

// 2. Participant reps resolved exactly the way notifyTeamRepresentatives does.
$participantIds = [];
$stmt = $conn->prepare(
    "SELECT DISTINCT user_id
       FROM tbl_team
      WHERE id IN (?, ?)
        AND user_id IS NOT NULL
        AND user_id > 0"
);
$stmt->bind_param('ii', $teamAId, $teamBId);
$stmt->execute();
$rows = $stmt->get_result();
while ($participant = $rows->fetch_assoc()) {
    $participantIds[] = (int) $participant['user_id'];
}
$stmt->close();
row('participant_recipients', implode(',', $participantIds));
row('participants_resolve', count($participantIds) > 0, true, true);

// 3. An unrelated approved team rep in the same league must stay untouched.
$unrelatedId = 0;
$stmt = $conn->prepare(
    "SELECT DISTINCT user_id
       FROM tbl_team
      WHERE league_id = ?
        AND id NOT IN (?, ?)
        AND LOWER(status) = 'approved'
        AND user_id IS NOT NULL
        AND user_id > 0
      LIMIT 1"
);
$stmt->bind_param('iii', $leagueId, $teamAId, $teamBId);
$stmt->execute();
$unrelated = $stmt->get_result()->fetch_assoc();
$stmt->close();
$unrelatedId = (int) ($unrelated['user_id'] ?? 0);
row('unrelated_representative', $unrelatedId > 0 ? $unrelatedId : 'skipped: no third approved team');

$countBefore = static function (mysqli $conn, int $userId, int $scheduleId): int {
    $stmt = $conn->prepare(
        "SELECT COUNT(*) AS c
           FROM tbl_notification
          WHERE user_id = ? AND type = 'schedule_update'
            AND related_id = ? AND title = 'Schedule Update'"
    );
    $stmt->bind_param('ii', $userId, $scheduleId);
    $stmt->execute();
    $count = (int) ($stmt->get_result()->fetch_assoc()['c'] ?? 0);
    $stmt->close();
    return $count;
};

$before = [];
foreach (array_merge($participantIds, [$unrelatedId]) as $userId) {
    if ($userId > 0) {
        $before[$userId] = $countBefore($conn, $userId, $scheduleId);
    }
}

// 4. Run the helper inside a transaction and inspect its writes.
$conn->begin_transaction();
try {
    $sent = notifyScheduleUpdate($conn, $leagueId, $scheduleId, 'Scoping smoke check');

    foreach ($participantIds as $userId) {
        $written = $countBefore($conn, $userId, $scheduleId) - $before[$userId];
        row('participant_' . $userId . '_rows_added', $written, 1, true);
    }
    if ($unrelatedId > 0) {
        $written = $countBefore($conn, $unrelatedId, $scheduleId) - $before[$unrelatedId];
        row('unrelated_' . $unrelatedId . '_rows_added', $written, 0, true);
    }
    row('notifications_sent', $sent, count($participantIds), true);
} finally {
    $conn->rollback();
}

// 5. The daily reminder queries must execute against the live schema. Every
//    write is rolled back; this validates SQL and recipient loops without
//    touching real inboxes.
require_once __DIR__ . '/../includes/teamCorrectionDeadline.php';
$conn->begin_transaction();
try {
    $beforeCount = (int) $conn->query("SELECT COUNT(*) AS c FROM tbl_notification")->fetch_assoc()['c'];
    $closing  = notifyRegistrationClosingSoon($conn, 3);
    $matchDay = notifyUpcomingMatchToday($conn);
    $cmtMatchDay = notifyCommitteeUpcomingMatchToday($conn);
    $cmtResults  = notifyCommitteePendingResults($conn);
    $expired  = expireTeamCorrectionDeadlines($conn);
    $afterCount = (int) $conn->query("SELECT COUNT(*) AS c FROM tbl_notification")->fetch_assoc()['c'];
    row('registration_closing_reminders', $closing);
    row('match_day_reminders', $matchDay);
    row('committee_match_day_reminders', $cmtMatchDay);
    row('committee_result_reminders', $cmtResults);
    row('expired_applications', $expired);
    row('daily_notification_rows_written', $afterCount - $beforeCount);
    row('daily_reminder_queries', 'ok', 'ok', true);
} catch (Throwable $e) {
    row('daily_reminder_queries', 'failed: ' . $e->getMessage(), 'ok', true);
} finally {
    $conn->rollback();
}

echo $failures === 0 ? PHP_EOL . 'All notification-scoping checks passed.' . PHP_EOL
    : PHP_EOL . "$failures check(s) failed." . PHP_EOL;
exit($failures === 0 ? 0 : 1);
