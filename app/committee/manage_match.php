<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/livestreamHelper.php';
require_once __DIR__ . '/../../includes/notificationHelper.php';
require_once __DIR__ . '/../../includes/tournamentProgression.php';
require_once __DIR__ . '/../../config/sportRules.php';
require_once __DIR__ . '/../../includes/matchSetHelper.php';
requireRole([4]);
requireActiveAccount($conn);

$userId   = currentUserId();
$full     = currentUserName();
$userType = currentRole();
$msg      = '';

// Handle livestream update — only for assigned committee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['updateLivestream'])) {
    requireJsonCsrf();
    $sid = intval($_POST['schedule_id']);
    $url = trim($_POST['livestream_url'] ?? '');
    $lstatus = trim($_POST['livestream_status'] ?? '');
    $allowedLivestreamStatuses = ['', 'Scheduled', 'Live', 'Ended'];
    if (!in_array($lstatus, $allowedLivestreamStatuses, true)) {
        echo json_encode(['status'=>400,'msg'=>'Invalid livestream status.']); exit;
    }

    // Ownership check: ensure this schedule is assigned to this committee
    $chk = $conn->prepare("SELECT id FROM tbl_schedule WHERE id=? AND committee_user_id=? AND published_at IS NOT NULL");
    $chk->bind_param('ii', $sid, $userId); $chk->execute(); $chk->store_result();
    if ($chk->num_rows === 0) {
        echo json_encode(['status'=>403,'msg'=>'Unauthorized.']); exit;
    }
    $chk->close();

    // Validate URL format and supported platform
    if ($url) {
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            echo json_encode(['status'=>400,'msg'=>'Invalid URL format.']); exit;
        }
        if (!isSupportedLivestreamUrl($url)) {
            echo json_encode(['status'=>400,'msg'=>'That link cannot be embedded. Paste the video/live URL itself (YouTube: youtube.com/watch?v=… or youtube.com/live/…; Facebook: facebook.com/…/videos/… or facebook.com/…/live/).']); exit;
        }
    }

    // A link saved without an explicit status means the stream is starting, so
    // it is stored as Live instead of staying invisible to spectators. An
    // explicit Scheduled / Live / Ended choice always wins.
    $resolvedStatus = resolveLivestreamStatus($lstatus, $url);
    $lstatus        = $resolvedStatus['status'];

    $stmt = $conn->prepare("UPDATE tbl_schedule SET livestream_url=?, livestream_status=? WHERE id=? AND committee_user_id=?");
    $stmt->bind_param('ssii', $url, $lstatus, $sid, $userId);
    if ($stmt->execute()) {
        $autoNote = $resolvedStatus['auto'] ? ', auto' : '';
        auditLog($conn, 'Update Livestream', "Updated livestream for match ID $sid: $url (status: $lstatus$autoNote)");
        echo json_encode([
            'status'      => 200,
            'msg'         => $resolvedStatus['auto']
                ? 'Livestream saved. Stream status set to Live automatically.'
                : 'Livestream updated.',
            'auto_status' => $resolvedStatus['auto'],
        ]);
    } else {
        echo json_encode(['status'=>500,'msg'=>$stmt->error]);
    }
    $stmt->close();
    exit;
}

// Handle result submission — only for assigned committee
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submitResult'])) {
    requireJsonCsrf();
    header('Content-Type: application/json');
    $sid     = intval($_POST['schedule_id']);
    $aScore  = intval($_POST['a_score']);
    $bScore  = intval($_POST['b_score']);

    $breakdown = trim($_POST['result_breakdown'] ?? '');
    if ($breakdown !== '' && json_decode($breakdown, true) === null && json_last_error() !== JSON_ERROR_NONE) {
        echo json_encode(['status'=>400,'msg'=>'Period/set breakdown must be valid JSON.']); exit;
    }

    $conn->begin_transaction();
    // Assignment and state check, locked to prevent duplicate submissions.
    $chk = $conn->prepare("SELECT team_a, team_b, league_id, status, result_submitted_by, best_of, stage_id, seed_a, seed_b, stage FROM tbl_schedule WHERE id=? AND committee_user_id=? AND published_at IS NOT NULL FOR UPDATE");
    $chk->bind_param('ii', $sid, $userId); $chk->execute();
    $matchRow = $chk->get_result()->fetch_assoc();
    $chk->close();

    if (!$matchRow) {
        $conn->rollback();
        echo json_encode(['status'=>403,'msg'=>'Unauthorized or match not found.']); exit;
    }

    if (strtolower($matchRow['status']) === 'completed' || !empty($matchRow['result_submitted_by'])) {
        $conn->rollback();
        echo json_encode(['status'=>409,'msg'=>'An official result has already been submitted for this match.']); exit;
    }
    if (in_array(strtolower($matchRow['status']), ['cancelled','postponed'], true)) {
        $conn->rollback();
        echo json_encode(['status'=>400,'msg'=>'Results cannot be submitted for a cancelled or postponed match.']); exit;
    }

    if ($aScore < 0 || $bScore < 0) {
        $conn->rollback();
        echo json_encode(['status'=>400,'msg'=>'Scores cannot be negative.']); exit;
    }

    /* ---- Result type (ANP2026: forfeits, walkovers, decisions, penalties) ---- */
    $winType = (string)($_POST['win_type'] ?? 'Regular');
    if (!in_array($winType, ['Regular', 'Forfeit', 'DoubleForfeit', 'Walkover', 'Decision'], true)) {
        $conn->rollback();
        echo json_encode(['status' => 400, 'msg' => 'Invalid result type.']); exit;
    }
    $penaltyA = max(0, min(99, intval($_POST['penalty_a'] ?? 0)));
    $penaltyB = max(0, min(99, intval($_POST['penalty_b'] ?? 0)));
    $note = trim((string)($_POST['result_note'] ?? ''));
    if ($note !== '' && function_exists('mb_substr')) $note = mb_substr($note, 0, 255);
    $noteValue = $note !== '' ? $note : null;

    $teamAId = intval($matchRow['team_a']);
    $teamBId = intval($matchRow['team_b']);
    $winnerTeamInput = intval($_POST['winner_team'] ?? 0);

    /* ---- Set-based sports (Volleyball ANP2026): per-set scores decide the match ---- */
    $leagueProfile = getLeagueSportProfile($conn, (int)$matchRow['league_id']);
    $usesSets      = sportProfileUsesSets($leagueProfile);
    $seriesBestOf  = $usesSets ? matchBestOfForSchedule($conn, $matchRow, $leagueProfile, matchIsChampionship($matchRow)) : 1;
    $setRows       = [];
    $setsAValue    = null;
    $setsBValue    = null;
    $pointsAValue  = null;
    $pointsBValue  = null;
    $bestOfValue   = null;

    if ($usesSets && $winType === 'Regular') {
        $parsedSets = parseSetInput((array)($_POST['set_a'] ?? []), (array)($_POST['set_b'] ?? []));
        if ($parsedSets === null) {
            $conn->rollback();
            echo json_encode(['status'=>400,'msg'=>'Every set row needs both scores (or leave the row blank).']); exit;
        }
        $setSeriesError = validateSetSeries($leagueProfile, $seriesBestOf, $parsedSets);
        if ($setSeriesError) {
            $conn->rollback();
            echo json_encode(['status'=>400,'msg'=>$setSeriesError]); exit;
        }
        $setRows = $parsedSets;
        $setSummary = summarizeSets($parsedSets, $seriesBestOf);
        // a_score/b_score keep meaning "the unit that decides the match" -> sets won
        $aScore = (int)$setSummary['sets_a'];
        $bScore = (int)$setSummary['sets_b'];
        $pointsAValue = (int)$setSummary['points_a'];
        $pointsBValue = (int)$setSummary['points_b'];
    }

    if ($winType === 'DoubleForfeit') {
        // Both teams at fault: no winner, no automatic advance.
        $winnerValue = 0;
        $aScore = 0; $bScore = 0;
        $setRows = [];
        if ($usesSets) { $pointsAValue = 0; $pointsBValue = 0; }
    } elseif (in_array($winType, ['Forfeit', 'Walkover', 'Decision'], true)) {
        if (!in_array($winnerTeamInput, [$teamAId, $teamBId], true)) {
            $conn->rollback();
            echo json_encode(['status' => 400, 'msg' => 'Select which team is awarded the win.']); exit;
        }
        $winnerValue = $winnerTeamInput;
        if ($usesSets) {
            // Manual §10: a forfeit/default is recorded with the official line
            // (BO3 -> 0-2 with 0-25 sets, BO5 -> 0-3 with 0-25 sets).
            $forfeitLine  = sportProfileForfeitLine($leagueProfile, $seriesBestOf, $winnerValue === $teamAId ? 'a' : 'b');
            $setRows      = $forfeitLine['sets'];
            $aScore       = (int)$forfeitLine['a_sets'];
            $bScore       = (int)$forfeitLine['b_sets'];
            $forfeitSums  = summarizeSets($setRows, $seriesBestOf);
            $pointsAValue = (int)$forfeitSums['points_a'];
            $pointsBValue = (int)$forfeitSums['points_b'];
        } elseif ($aScore === $bScore) {
            // No usable score entered — apply the standard forfeit score.
            if ($winnerValue === $teamAId) { $aScore = 20; $bScore = 0; } else { $aScore = 0; $bScore = 20; }
        }
    } else {
        $winnerId = null;
        if ($aScore > $bScore) $winnerId = $teamAId;
        elseif ($bScore > $aScore) $winnerId = $teamBId;
        $winnerValue = $winnerId ?? 0;
    }

    // Set-based sports also keep the sets-won summary, the total points and the
    // series length so standings, ratios and the scoresheet stay consistent.
    if ($usesSets) {
        $setsAValue   = $aScore;
        $setsBValue   = $bScore;
        $bestOfValue  = $seriesBestOf;
        if ($breakdown === '' && !empty($setRows)) {
            $breakdownRows = [];
            foreach ($setRows as $row) $breakdownRows[] = ['a' => (int)$row[0], 'b' => (int)$row[1]];
            $breakdown = json_encode(['sets' => $breakdownRows, 'best_of' => $seriesBestOf]);
        }
    }

    $breakdownValue = $breakdown !== '' ? $breakdown : null;
    $stmt = $conn->prepare("UPDATE tbl_schedule SET a_score=?, b_score=?, winner=?, win_type=?, result_note=?, penalty_a=?, penalty_b=?, sets_a=?, sets_b=?, a_points=?, b_points=?, best_of=?, result_status='Final', status='Completed', result_submitted_by=?, result_breakdown=?, published_at=NOW() WHERE id=? AND committee_user_id=?");
    $stmt->bind_param('iiissiiiiiiiisii', $aScore, $bScore, $winnerValue, $winType, $noteValue, $penaltyA, $penaltyB, $setsAValue, $setsBValue, $pointsAValue, $pointsBValue, $bestOfValue, $userId, $breakdownValue, $sid, $userId);
    if (!$stmt->execute()) {
        $conn->rollback();
        echo json_encode(['status'=>500,'msg'=>$stmt->error]); $stmt->close(); exit;
    }
    $stmt->close();

    // Persist the per-set detail (set-based sports only).
    if ($usesSets) {
        saveMatchSets($conn, $sid, (int)$matchRow['league_id'], $setRows, $seriesBestOf);
    }

    $resultSummary = $usesSets && !empty($setRows)
        ? ($aScore . ' - ' . $bScore . ' (' . formatMatchSets(array_map(fn($r) => ['a_points' => $r[0], 'b_points' => $r[1]], $setRows)) . ')')
        : ($aScore . ' - ' . $bScore);
    auditLog($conn, 'Submit Result', "Committee submitted $winType result for match ID $sid: $resultSummary (winner=$winnerValue, A+$penaltyA, B+$penaltyB)");
    $teamUsers = $conn->prepare("SELECT DISTINCT user_id FROM tbl_team WHERE id IN (?,?)");
    $teamUsers->bind_param('ii', $matchRow['team_a'], $matchRow['team_b']);
    $teamUsers->execute();
    $userRows = $teamUsers->get_result();
    while ($teamUser = $userRows->fetch_assoc()) {
        createNotification($conn, (int)$teamUser['user_id'], 'match_result', 'Match Result Published', "Official result: $aScore - $bScore", $sid);
    }
    $teamUsers->close();
    $organizerStmt = $conn->prepare("SELECT created_by FROM tbl_league WHERE id=?");
    $organizerStmt->bind_param('i', $matchRow['league_id']);
    $organizerStmt->execute();
    $organizer = $organizerStmt->get_result()->fetch_assoc();
    $organizerStmt->close();
    if (!empty($organizer['created_by'])) {
        createNotification($conn, (int)$organizer['created_by'], 'match_result', 'Match Result Published', "Committee published result $aScore - $bScore for match #$sid.", $sid);
    }

    // Auto-progress the tournament based on format
    try {
        $progressionActions = progressMatchResult($conn, (int)$matchRow['league_id'], $sid, (int)$winnerValue, $userId);
        if (!empty($progressionActions['tournament_complete'])) {
            auditLog($conn, 'Tournament Complete', "League {$matchRow['league_id']} champion: team {$progressionActions['champion']}");
        }
        if (!empty($progressionActions['eliminated'])) {
            auditLog($conn, 'Team Eliminated', "Teams eliminated: " . implode(',', $progressionActions['eliminated']));
        }
    } catch (\Exception $e) {
        error_log("Progression error for match $sid: " . $e->getMessage());
    }

    $conn->commit();
    echo json_encode(['status'=>200,'msg'=>'Result submitted successfully.']);
    exit;
}

// Fetch the fixture this result sheet belongs to. It must be published and
// assigned to the signed-in committee member.
$scheduleId    = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$selectedMatch = null;
$viewProfile   = null;
$viewUsesSets  = false;
$viewBestOf    = 1;
$viewSets      = [];
$existingUrl   = '';
$parsed        = null;
$cmtStream     = '';
if ($scheduleId > 0) {
    $stmt = $conn->prepare("
        SELECT s.*, l.league_name, ta.team_name AS team_a_name, tb.team_name AS team_b_name
        FROM tbl_schedule s
        JOIN tbl_league l ON l.id = s.league_id
        JOIN tbl_team ta ON ta.id = s.team_a
        JOIN tbl_team tb ON tb.id = s.team_b
        WHERE s.id=? AND s.committee_user_id=? AND s.published_at IS NOT NULL
    ");
    $stmt->bind_param('ii', $scheduleId, $userId);
    $stmt->execute();
    $selectedMatch = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($selectedMatch) {
        // The sport profile decides whether the sheet asks for sets or points.
        $viewProfile  = getLeagueSportProfile($conn, (int) $selectedMatch['league_id']);
        $viewUsesSets = sportProfileUsesSets($viewProfile);
        if ($viewUsesSets) {
            $viewBestOf = matchBestOfForSchedule($conn, $selectedMatch, $viewProfile, matchIsChampionship($selectedMatch));
            $viewSets   = getMatchSets($conn, (int) $selectedMatch['id']);
        }

        $existingUrl = (string) ($selectedMatch['livestream_url'] ?? '');
        $parsed      = $existingUrl !== '' ? parseLivestreamUrl($existingUrl) : null;
        $cmtStream   = (string) ($selectedMatch['livestream_status'] ?? '');
    }
}

// Presentation helpers shared with matches.php.
$cmtTeamInitials = static function ($name): string {
    $name = trim((string) $name);
    if ($name === '') {
        return '--';
    }
    $parts = preg_split('/\s+/', $name) ?: [];
    if (count($parts) === 1) {
        $word = function_exists('mb_substr') ? mb_substr($parts[0], 0, 2) : substr($parts[0], 0, 2);
        return function_exists('mb_strtoupper') ? mb_strtoupper($word) : strtoupper($word);
    }
    $first = function_exists('mb_substr') ? mb_substr($parts[0], 0, 1) : substr($parts[0], 0, 1);
    $lastWord = $parts[count($parts) - 1];
    $last = function_exists('mb_substr') ? mb_substr($lastWord, 0, 1) : substr($lastWord, 0, 1);
    return function_exists('mb_strtoupper') ? mb_strtoupper($first . $last) : strtoupper($first . $last);
};

$cmtStatusTone = static function ($status): string {
    return match (strtolower(trim((string) $status))) {
        'completed' => 'is-completed',
        'cancelled' => 'is-cancelled',
        'postponed' => 'is-postponed',
        'live', 'ongoing' => 'is-live',
        'scheduled', 'upcoming' => 'is-scheduled',
        default => 'is-default',
    };
};

$cmtStreamTone = static function ($status): string {
    return match (strtolower(trim((string) $status))) {
        'live' => 'is-live',
        'scheduled' => 'is-scheduled',
        'ended' => 'is-ended',
        default => 'is-none',
    };
};

$cmtStatusLabel = static function ($status): string {
    $label = trim((string) $status);
    return $label !== '' ? ucfirst($label) : 'Scheduled';
};

include 'includes/header.php';
?>

<body id="page-top" class="body committee-matches committee-manage-page">
<?php include 'includes/bar.php'; ?>
<?php include 'includes/matches-page-styles.php'; ?>
<div class="container-fluid" style="padding-top:20px;">
<div class="row p-3">
    <div class="mt-page w-100">

        <nav class="mt-crumbs" aria-label="Breadcrumb">
            <i class="bi bi-arrow-left" aria-hidden="true"></i>
            <a href="matches.php">Matches</a>
            <i class="bi bi-chevron-right mt-crumb-sep" aria-hidden="true"></i>
            <span class="mt-crumb-current" aria-current="page">Manage match</span>
        </nav>

        <div role="status" data-lms-toast-source><?= $msg ?></div>

        <?php if (!$selectedMatch): ?>
        <section class="mt-result-empty" aria-labelledby="resultEmptyTitle">
            <i class="bi bi-clipboard2-x" aria-hidden="true"></i>
            <h1 id="resultEmptyTitle">Match workspace unavailable</h1>
            <p>This fixture could not be found, or it is not assigned to your committee account. Open the match register and choose a published fixture to manage.</p>
            <a class="mt-btn mt-btn-primary" href="matches.php"><i class="bi bi-arrow-left" aria-hidden="true"></i>Back to register</a>
        </section>
        <?php else: ?>
        <?php
        $cmtVenue         = trim((string) ($selectedMatch['venue'] ?? '')) !== '' ? $selectedMatch['venue'] : 'Venue to be announced';
        $cmtStatusKey     = strtolower(trim((string) $selectedMatch['status']));
        $cmtIsCompleted   = $cmtStatusKey === 'completed';
        $cmtHasResult     = $cmtIsCompleted || !empty($selectedMatch['result_submitted_by']);
        $cmtResultBlocked = in_array($cmtStatusKey, ['cancelled', 'postponed'], true);
        $cmtResultStatus  = (string) ($selectedMatch['result_status'] ?? 'Final');
        ?>
       

        <section class="mt-room mt-result-room" aria-label="Match workspace">
            <header class="mt-room-head">
                
                <h2 class="mt-room-faceoff">
                    <span class="mt-versus-team">
                        <span class="mt-crest" aria-hidden="true"><?= htmlspecialchars($cmtTeamInitials($selectedMatch['team_a_name'])) ?></span>
                        <span class="mt-room-team-name"><?= htmlspecialchars($selectedMatch['team_a_name']) ?></span>
                    </span>
                    <span class="mt-room-vs" aria-hidden="true">vs</span>
                    <span class="mt-versus-team is-b">
                        <span class="mt-crest" aria-hidden="true"><?= htmlspecialchars($cmtTeamInitials($selectedMatch['team_b_name'])) ?></span>
                        <span class="mt-room-team-name"><?= htmlspecialchars($selectedMatch['team_b_name']) ?></span>
                    </span>
                </h2>
                
            </header>

            <div class="mt-room-grid">
                <section class="mt-panel" aria-label="Livestream control">
                    <div class="mt-panel-head">
                        <h2 class="mt-panel-title">Livestream</h2>
                        <span class="mt-status <?= $cmtStreamTone($cmtStream) ?>">
                            <?= $cmtStream !== '' ? htmlspecialchars($cmtStream) : 'Not scheduled' ?>
                        </span>
                    </div>

                    <?php if ($parsed): ?>
                    <div class="mt-preview-head">
                        <span class="mt-preview-platform"><i class="bi <?= livestreamPlatformIcon($parsed['platform']) ?>" aria-hidden="true"></i><?= htmlspecialchars(livestreamPlatformLabel($parsed['platform'])) ?> preview</span>
                        <a class="mt-preview-link" href="<?= htmlspecialchars($parsed['original_url']) ?>" target="_blank" rel="noopener">
                            <i class="bi bi-box-arrow-up-right" aria-hidden="true"></i>Open stream
                        </a>
                    </div>
                    <div class="mt-preview">
                        <?= livestreamEmbedHtml($parsed['embed_url'], $selectedMatch['team_a_name'] . ' vs ' . $selectedMatch['team_b_name'], 320) ?>
                    </div>
                    <?php
                    $publicWatchUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http')
                        . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost')
                        . rtrim(dirname(dirname(dirname($_SERVER['SCRIPT_NAME']))), '/')
                        . '/live.php?id=' . (int) $selectedMatch['id'];
                    ?>
                    <div class="mt-watch">
                        <span class="mt-watch-label"><i class="bi bi-link-45deg" aria-hidden="true"></i> Public watch link</span>
                        <div class="mt-watch-row">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($publicWatchUrl) ?>" id="publicWatchUrl" readonly aria-label="Public watch link">
                            <button class="mt-icon-btn" type="button" aria-label="Copy public watch link" onclick="navigator.clipboard.writeText(document.getElementById('publicWatchUrl').value).then(function(){Toast.fire({icon:'success',title:'Watch link copied'});})"><i class="bi bi-clipboard" aria-hidden="true"></i></button>
                            <a href="<?= htmlspecialchars($publicWatchUrl) ?>" target="_blank" rel="noopener" class="mt-icon-btn" aria-label="Open public watch page"><i class="bi bi-box-arrow-up-right" aria-hidden="true"></i></a>
                        </div>
                    </div>
                    <?php elseif ($existingUrl !== ''): ?>
                    <div class="mt-note is-warning">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <div>This saved stream link cannot be embedded, so no player is shown. Paste the direct video URL (for example <code>https://www.youtube.com/live/VIDEO_ID</code>) and save again.</div>
                    </div>
                    <?php endif; ?>

                    <form id="livestreamForm" class="mt-form mt-form-stack">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="schedule_id" value="<?= (int) $selectedMatch['id'] ?>">
                        <div class="mt-field">
                            <label class="form-label" for="livestreamUrl">Stream URL <span class="mt-required">*</span></label>
                            <input type="url" id="livestreamUrl" name="livestream_url" class="form-control" placeholder="https://www.youtube.com/live/VIDEO_ID" value="<?= htmlspecialchars($existingUrl) ?>">
                            
                        </div>
                        <div class="mt-field">
                            <label class="form-label" for="livestreamStatus">Stream status</label>
                            <select id="livestreamStatus" name="livestream_status" class="form-select">
                                <option value="">&mdash; Select &mdash;</option>
                                <option value="Scheduled" <?= $cmtStream === 'Scheduled' ? 'selected' : '' ?>>Scheduled</option>
                                <option value="Live" <?= $cmtStream === 'Live' ? 'selected' : '' ?>>Live</option>
                                <option value="Ended" <?= $cmtStream === 'Ended' ? 'selected' : '' ?>>Ended</option>
                            </select>
                            <p class="mt-hint"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Leave this on &ldquo;Select&rdquo; and saving a stream URL marks it Live automatically.</span></p>
                        </div>
                        <div class="mt-actions">
                            <button type="submit" class="mt-btn mt-btn-primary"><i class="bi bi-check2" aria-hidden="true"></i>Save stream</button>
                            <?php if (strtolower($cmtStream) !== 'live'): ?>
                            <button type="button" class="mt-btn mt-btn-danger quick-status-btn" data-status="Live"><i class="bi bi-broadcast" aria-hidden="true"></i>Go live</button>
                            <?php else: ?>
                            <button type="button" class="mt-btn mt-btn-muted quick-status-btn" data-status="Ended"><i class="bi bi-stop-fill" aria-hidden="true"></i>End stream</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </section>

                <section class="mt-panel mt-result-panel" aria-label="Official result form">
                    <div class="mt-panel-head">
                        <h2 class="mt-panel-title">Official result</h2>
                        <?php if ($cmtResultStatus !== 'Final'): ?>
                        <span class="mt-status is-postponed"><?= htmlspecialchars($cmtResultStatus) ?></span>
                        <?php elseif ($cmtHasResult): ?>
                        <span class="mt-status is-completed">Final</span>
                        <?php elseif ($cmtResultBlocked): ?>
                        <span class="mt-status is-default">Locked</span>
                        <?php else: ?>
                        <span class="mt-status is-scheduled">Awaiting result</span>
                        <?php endif; ?>
                    </div>

                    <?php if ($cmtResultStatus !== 'Final'): ?>
                    <div class="mt-note is-warning">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <div>
                            This result is <strong><?= htmlspecialchars($cmtResultStatus) ?></strong>. Wait for the Tournament Organizer's decision before submitting again.
                            <?php if (!empty($selectedMatch['result_note'])): ?>
                            <span class="mt-note-sub">Previous note: <?= htmlspecialchars($selectedMatch['result_note']) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endif; ?>


                    <?php if ($cmtHasResult): ?>
                    
                    <div class="mt-final">
                        <span class="mt-final-label"><i class="bi bi-patch-check-fill" aria-hidden="true"></i>Final score</span>
                        <div class="mt-final-score">
                            <?= (int) $selectedMatch['a_score'] ?><span>&ndash;</span><?= (int) $selectedMatch['b_score'] ?>
                            <?php if ($viewUsesSets): ?><small>sets</small><?php endif; ?>
                        </div>
                        <?php if ($viewUsesSets && !empty($viewSets)): ?>
                        <p class="mt-final-meta"><?= htmlspecialchars(formatMatchSets($viewSets)) ?></p>
                        <?php endif; ?>
                        <p class="mt-final-meta">Match results is filed successfully.</p>
                    </div>
                    <?php if (!empty($selectedMatch['result_note'])): ?>
                    <p class="mt-hint"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Result note: <?= htmlspecialchars($selectedMatch['result_note']) ?></span></p>
                    <?php endif; ?>
                    <a class="mt-btn mt-btn-muted mt-btn-block" href="matches.php">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>Back to register
                    </a>
                    <?php elseif ($cmtResultBlocked): ?>
                    <div class="mt-note is-warning">
                        <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
                        <div>This fixture is <strong><?= htmlspecialchars($cmtStatusLabel($selectedMatch['status'])) ?></strong>. Results can only be filed for scheduled or ongoing matches.</div>
                    </div>
                    <a class="mt-btn mt-btn-muted mt-btn-block" href="matches.php">
                        <i class="bi bi-arrow-left" aria-hidden="true"></i>Back to register
                    </a>
                    <?php else: ?>

                    <form id="resultForm" class="mt-form">
                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken()) ?>">
                        <input type="hidden" name="schedule_id" value="<?= (int) $selectedMatch['id'] ?>">
                        <div class="mt-field">
                            <label class="form-label" for="winType">Result type</label>
                            <select name="win_type" id="winType" class="form-select">
                                <option value="Regular">Regular</option>
                                <option value="Forfeit">Forfeit</option>
                                <option value="Walkover">Walkover</option>
                                <option value="DoubleForfeit">Double forfeit</option>
                                <option value="Decision">Decision / protest ruling</option>
                            </select>
                        </div>
                        <div class="mt-field" id="winnerTeamWrap" style="display:none;">
                            <label class="form-label" for="winnerTeam">Winner</label>
                            <select name="winner_team" id="winnerTeam" class="form-select">
                                <option value="<?= (int) $selectedMatch['team_a'] ?>"><?= htmlspecialchars($selectedMatch['team_a_name']) ?></option>
                                <option value="<?= (int) $selectedMatch['team_b'] ?>"><?= htmlspecialchars($selectedMatch['team_b_name']) ?></option>
                            </select>
                        </div>

                        <?php if ($viewUsesSets): ?>
                        <?php
                        $setTargets = [];
                        for ($setNo = 1; $setNo <= $viewBestOf; $setNo++) {
                            $setTargets[$setNo] = sportProfileSetTarget($viewProfile, $setNo, $viewBestOf);
                        }
                        ?>
                        <input type="hidden" name="a_score" value="0">
                        <input type="hidden" name="b_score" value="0">
                        <div class="mt-field">
                            <div class="mt-field-head">
                                <span class="mt-field-label">Set scores</span>
                                <span class="mt-chip">Best of <?= (int) $viewBestOf ?></span>
                                <span class="mt-chip is-info"><?= (int) $viewProfile['set_target'] ?> pts &middot; deciding <?= (int) $viewProfile['deciding_target'] ?> &middot; win by <?= (int) $viewProfile['win_by'] ?></span>
                            </div>

                            <table class="mt-set-table" id="setScoreTable">
                                <thead>
                                    <tr>
                                        <th scope="col" style="width:86px;">Set</th>
                                        <th scope="col"><?= htmlspecialchars($selectedMatch['team_a_name']) ?></th>
                                        <th scope="col"><?= htmlspecialchars($selectedMatch['team_b_name']) ?></th>
                                    </tr>
                                </thead>
                                <tbody>
                                <?php for ($setNo = 1; $setNo <= $viewBestOf; $setNo++): ?>
                                    <tr>
                                        <td><span class="mt-set-label">Set <?= (int) $setNo ?></span><span class="mt-set-target">to <?= (int) $setTargets[$setNo] ?></span></td>
                                        <td><input type="number" name="set_a[]" class="form-control set-a" min="0" max="99" inputmode="numeric" aria-label="Set <?= (int) $setNo ?> <?= htmlspecialchars($selectedMatch['team_a_name']) ?> score"></td>
                                        <td><input type="number" name="set_b[]" class="form-control set-b" min="0" max="99" inputmode="numeric" aria-label="Set <?= (int) $setNo ?> <?= htmlspecialchars($selectedMatch['team_b_name']) ?> score"></td>
                                    </tr>
                                <?php endfor; ?>
                                </tbody>
                            </table>
                            <p class="mt-hint"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Enter only the sets played.</span></p>
                        </div>
                        <?php else: ?>
                        <div class="mt-field-grid">
                            <div class="mt-field">
                                <label class="form-label" for="teamAScore"><?= htmlspecialchars($selectedMatch['team_a_name']) ?> score</label>
                                <input type="number" id="teamAScore" name="a_score" class="form-control" min="0" required>
                            </div>
                            <div class="mt-field">
                                <label class="form-label" for="teamBScore"><?= htmlspecialchars($selectedMatch['team_b_name']) ?> score</label>
                                <input type="number" id="teamBScore" name="b_score" class="form-control" min="0" required>
                            </div>
                        </div>
                        <?php endif; ?>

                        <div class="mt-field-grid">
                            <div class="mt-field">
                                <label class="form-label" for="penaltyA">Adjustment &middot; <?= htmlspecialchars($selectedMatch['team_a_name']) ?> (+)</label>
                                <input type="number" id="penaltyA" name="penalty_a" class="form-control" min="0" max="99" value="0">
                            </div>
                            <div class="mt-field">
                                <label class="form-label" for="penaltyB">Adjustment &middot; <?= htmlspecialchars($selectedMatch['team_b_name']) ?> (+)</label>
                                <input type="number" id="penaltyB" name="penalty_b" class="form-control" min="0" max="99" value="0">
                            </div>
                        </div>
                        <p class="mt-hint"><i class="bi bi-info-circle" aria-hidden="true"></i><span>Added to the team's points-for.</span></p>
                        <div class="mt-field">
                            <label class="form-label" for="resultNote">Note <span class="mt-optional">(optional)</span></label>
                            <input type="text" id="resultNote" name="result_note" class="form-control" maxlength="255" placeholder="Optional note">
                        </div>
                        <button type="submit" class="mt-btn mt-btn-success mt-btn-block"><i class="bi bi-clipboard2-check" aria-hidden="true"></i>Submit official result</button>
                    </form>
                    <?php endif; ?>
                </section>
            </div>
        </section>
        <?php endif; ?>
    </div>
</div>
</div>

<?php include 'includes/footer.php'; ?>
<script>
(function ($) {
    'use strict';

    function cmtToast(icon, title, text) {
        if (window.Toast && typeof window.Toast.fire === 'function') {
            return Toast.fire({ icon: icon, title: title, text: text });
        }
        return Swal.fire({ icon: icon, title: title, text: text, toast: true, position: 'top-end', showConfirmButton: false, timer: 1800 });
    }

    var $livestreamForm = $('#livestreamForm');
    if ($livestreamForm.length) {
        $livestreamForm.on('submit', function (e) {
            e.preventDefault();
            $.ajax({
                url: '', type: 'POST',
                data: $livestreamForm.serialize() + '&updateLivestream=1',
                dataType: 'json',
                success: function (res) {
                    if (res.status === 200) {
                        if (res.auto_status) {
                            cmtToast('success', 'Livestream saved', 'Stream status was set to Live automatically.')
                                .then(function () { location.reload(); });
                        } else {
                            cmtToast('success', res.msg).then(function () { location.reload(); });
                        }
                    } else {
                        Swal.fire('Error', res.msg, 'error');
                    }
                },
                error: function () {
                    Swal.fire('Error', 'The server could not save the livestream.', 'error');
                }
            });
        });

        $('.quick-status-btn').on('click', function () {
            var newStatus = $(this).data('status');
            $livestreamForm.find('[name=livestream_status]').val(newStatus);
            $livestreamForm.trigger('submit');
        });
    }

    var $winType = $('#winType');
    if ($winType.length) {
        $winType.on('change', function () {
            var type = String($(this).val());
            $('#winnerTeamWrap').toggle(['Forfeit', 'Walkover', 'Decision'].indexOf(type) !== -1);
        });
    }

    var $resultForm = $('#resultForm');
    if ($resultForm.length) {
        $resultForm.on('submit', function (e) {
            e.preventDefault();

            var aScore = parseInt($resultForm.find('[name=a_score]').first().val(), 10);
            var bScore = parseInt($resultForm.find('[name=b_score]').first().val(), 10);
            var winType = String($resultForm.find('[name=win_type]').val() || 'Regular');
            var winnerName = winType === 'Regular' ? '' : $resultForm.find('[name=winner_team] option:selected').text();
            var penaltyA = parseInt($resultForm.find('[name=penalty_a]').val(), 10) || 0;
            var penaltyB = parseInt($resultForm.find('[name=penalty_b]').val(), 10) || 0;

            // Set-based sports: validate the set grid client-side and summarise it.
            var setsSummary = '';
            var $setA = $resultForm.find('.set-a');
            if ($setA.length) {
                var played = [];
                var setsA = 0, setsB = 0, problem = '';
                $setA.each(function (i) {
                    var aRaw = String($(this).val() || '').trim();
                    var bRaw = String($resultForm.find('.set-b').eq(i).val() || '').trim();
                    if (aRaw === '' && bRaw === '') return;
                    if (aRaw === '' || bRaw === '') { problem = 'Set ' + (i + 1) + ': enter both scores.'; return; }
                    var aVal = parseInt(aRaw, 10), bVal = parseInt(bRaw, 10);
                    if (isNaN(aVal) || isNaN(bVal)) { problem = 'Set ' + (i + 1) + ': enter numbers.'; return; }
                    if (aVal === bVal) { problem = 'Set ' + (i + 1) + ': a set cannot end level.'; return; }
                    played.push(aVal + '-' + bVal);
                    if (aVal > bVal) setsA++; else setsB++;
                });
                if (problem) { Swal.fire('Check the set scores', problem, 'warning'); return; }
                if (!played.length && winType === 'Regular') {
                    Swal.fire('Set scores required', 'Enter the scores of the sets that were played.', 'warning'); return;
                }
                if (played.length) setsSummary = played.join(', ') + ' &rarr; <b>' + setsA + '-' + setsB + ' sets</b>';
            }

            if (['Forfeit', 'Walkover', 'Decision'].indexOf(winType) !== -1 && !winnerName) {
                Swal.fire('Winner required', 'Select which team is awarded the win.', 'warning');
                return;
            }


            var detail = 'Type: <b>' + winType + '</b>';
            if (winType === 'DoubleForfeit') {
                detail += '<br>Both teams are recorded as forfeiting &mdash; no winner.';
            } else if (winType !== 'Regular') {
                detail += '<br>Winner: <b>' + winnerName + '</b> (score ' + aScore + ' - ' + bScore + ')';
            } else if (setsSummary) {
                detail += '<br>Sets: ' + setsSummary;
            } else {
                detail += '<br>Score: <b>' + aScore + ' - ' + bScore + '</b>';
            }
            if (penaltyA || penaltyB) detail += '<br>Adjustments: +' + penaltyA + ' / +' + penaltyB;

            Swal.fire({
                title: 'Submit Official Result?',
                html: detail + '<br>This action cannot be undone.',
                icon: 'warning', showCancelButton: true,
                confirmButtonColor: '#09173c', cancelButtonColor: '#6c757d',
                confirmButtonText: 'Yes, submit'
            }).then(function (r) {
                if (!r.isConfirmed) return;
                $.ajax({
                    url: '', type: 'POST',
                    data: $resultForm.serialize() + '&submitResult=1',
                    dataType: 'json',
                    success: function (res) {
                        if (res.status === 200) {
                            cmtToast('success', res.msg).then(function () { location.reload(); });
                        } else {
                            Swal.fire('Error', res.msg, 'error');
                        }
                    },
                    error: function () {
                        Swal.fire('Error', 'The server could not submit the result.', 'error');
                    }
                });
            });
        });
    }
})(window.jQuery);
</script>
</body></html>