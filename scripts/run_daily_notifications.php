<?php
/**
 * Daily notification runner
 * =========================
 * Writes the reminders that used to depend on a Team Representative opening the
 * dashboard:
 *   - correction-expiry notices (written while expiring applications)
 *   - final-24-hour correction reminders
 *   - match-day reminders for the two teams
 *   - match-day and overdue-result reminders for Committees
 *   - "registration closing soon" reminders
 *
 * Run once a day. Examples:
 *   Linux/cron:        15 6 * * *  php /path/to/lms/scripts/run_daily_notifications.php
 *   Windows Task Scheduler:
 *     schtasks /Create /SC DAILY /TN "LMS Daily Notifications" ^
 *       /TR "\"C:\xampp\php\php.exe\" \"C:\xampp\htdocs\lms\scripts\run_daily_notifications.php\"" /ST 06:15
 *
 * Use --dry-run to execute every query inside a transaction that is rolled
 * back at the end (safe for testing on a live database).
 *
 * A MySQL advisory lock keeps two overlapping runs from duplicating work. The
 * per-notification dedupe checks remain the final safety net.
 *
 * Exit code 0 = completed or skipped because another run held the lock.
 * Exit code 1 = the database could not be reached.
 */
require_once __DIR__ . '/../config/dbconnect.php';
require_once __DIR__ . '/../includes/notificationHelper.php';
require_once __DIR__ . '/../includes/teamCorrectionDeadline.php';

$lockName = 'lms_daily_notifications_' . date('Y-m-d');
$lockValue = null;

$lockStmt = $conn->prepare("SELECT GET_LOCK(?, 5) AS acquired");
if (!$lockStmt) {
    fwrite(STDERR, "Unable to prepare the daily-notification lock.\n");
    exit(1);
}
$lockStmt->bind_param('s', $lockName);
$lockStmt->execute();
$lockRow = $lockStmt->get_result()->fetch_assoc();
$lockValue = $lockRow['acquired'] ?? null;
$lockStmt->close();

if ((int) $lockValue !== 1) {
    echo "Skipped: another daily-notification run is already in progress.\n";
    exit(0);
}

$dryRun = in_array('--dry-run', $argv ?? [], true);
if ($dryRun) {
    $conn->begin_transaction();
    echo "Dry run: every write will be rolled back.\n";
}

try {
    $expired = expireTeamCorrectionDeadlines($conn);
    echo "Expired applications: $expired\n";

    $matchReminders = notifyUpcomingMatchToday($conn);
    echo "Match-day reminders: $matchReminders\n";

    $committeeMatchReminders = notifyCommitteeUpcomingMatchToday($conn);
    echo "Committee match-day reminders: $committeeMatchReminders\n";

    $committeeResultReminders = notifyCommitteePendingResults($conn);
    echo "Committee result reminders: $committeeResultReminders\n";

    $closingReminders = notifyRegistrationClosingSoon($conn, 3);
    echo "Registration-closing reminders: $closingReminders\n";

    $deadlineReminders = 0;
    $trUsers = $conn->query("SELECT id FROM tbl_user WHERE role = '2' AND stat = '1'");
    if ($trUsers) {
        while ($tr = $trUsers->fetch_assoc()) {
            $deadlineReminders += notifyTeamCorrectionDeadlines($conn, (int) $tr['id']);
        }
    }
    echo "Correction-deadline reminders: $deadlineReminders\n";

    echo "Daily notification run complete.\n";
} catch (Throwable $e) {
    error_log('[notification] Daily run failed: ' . $e->getMessage());
    fwrite(STDERR, 'Daily notification run failed: ' . $e->getMessage() . "\n");
    $exitCode = 1;
} finally {
    if ($dryRun) {
        $conn->rollback();
        echo "Dry run rolled back; no rows were kept.\n";
    }
    $release = $conn->prepare("SELECT RELEASE_LOCK(?)");
    if ($release) {
        $release->bind_param('s', $lockName);
        $release->execute();
        $release->close();
    }
    $conn->close();
}

exit($exitCode ?? 0);
