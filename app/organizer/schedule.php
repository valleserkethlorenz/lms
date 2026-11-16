<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__ . '/../../config/dbconnect.php';
require_once __DIR__ . '/../../includes/schedulingHelper.php';
require_once __DIR__ . '/../../config/auth_guard.php';
require_once __DIR__ . '/../../includes/teamCorrectionDeadline.php';
require_once __DIR__ . '/../../includes/storageService.php';
require_once __DIR__ . '/../../config/leagueStatus.php';
require_once __DIR__ . '/../../includes/stageHelper.php';
require_once __DIR__ . '/../../includes/summaryStrip.php';

requireRole([1]);
requireActiveAccount($conn);
$userId = currentUserId();
$userType = currentRole();

$leagueId = intval($_GET['id'] ?? 0);

if (!$leagueId) {
    header("location: league.php");
    exit();
}

// Get league details
$sql = "SELECT * FROM tbl_league WHERE id = ? AND created_by = ? AND (is_archived=0 OR is_archived IS NULL)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $leagueId, $userId);
$stmt->execute();
$leagueResult = $stmt->get_result();
$league = $leagueResult->fetch_assoc();
$stmt->close();

if (!$league) {
    $_SESSION['league_flash'] = ['danger', 'That league belongs to another organizer.'];
    header("location: league.php");
    exit();
}

// Check scheduling prerequisites
$approvedCountStmt = $conn->prepare("SELECT COUNT(*) as cnt FROM tbl_team WHERE league_id = ? AND LOWER(status) = 'approved'");
$approvedCountStmt->bind_param("i", $leagueId);
$approvedCountStmt->execute();
$approvedCount = $approvedCountStmt->get_result()->fetch_assoc()['cnt'];
$approvedCountStmt->close();

$isSlotsFilled      = $approvedCount >= intval($league['max_team']);
$isRegDatePassed    = date('Y-m-d') > $league['regis_end'];
$isRegManuallyClosed = intval($league['registration_window_open']) === 0;
$hasOpenCorrections = leagueHasOpenCorrections($conn, $leagueId);
// Unlock if ANY one condition is met
$canSchedule = ($isSlotsFilled || $isRegDatePassed || $isRegManuallyClosed) && !$hasOpenCorrections;

$committees = [];
$committeeStmt = $conn->prepare("SELECT id, fname, lname FROM tbl_user WHERE role='4' AND stat='1' ORDER BY fname, lname");
$committeeStmt->execute();
$committeeResult = $committeeStmt->get_result();
while ($committee = $committeeResult->fetch_assoc()) $committees[] = $committee;
$committeeStmt->close();
$venues = [];
$venueStmt = $conn->prepare("SELECT name FROM tbl_utility WHERE category='venue' AND is_active=1 ORDER BY name");
$venueStmt->execute();
$venueResult = $venueStmt->get_result();
while ($venue = $venueResult->fetch_assoc()) $venues[] = $venue['name'];
$venueStmt->close();
$leagueGroups=[];
if((int)($league['grouping_enabled'] ?? 0)===1){
    $groupStmt=$conn->prepare("SELECT id,name FROM tbl_league_group WHERE league_id=? ORDER BY display_order,name");
    $groupStmt->bind_param('i',$leagueId); $groupStmt->execute(); $groupResult=$groupStmt->get_result(); while($group=$groupResult->fetch_assoc())$leagueGroups[]=$group; $groupStmt->close();
}

// Stage model (ANP2026: brackets -> crossover/pools -> playoffs -> battle for third)
$leagueStages = [];
foreach (getLeagueStages($conn, $leagueId) as $stageRow) {
    $groups = [];
    foreach (getStageGroups($conn, (int)$stageRow['id']) as $groupRow) {
        $groupRow['team_count'] = count(getStageGroupTeams($conn, (int)$groupRow['id']));
        $groupRow['sources']    = getStageGroupSources($conn, (int)$groupRow['id']);
        $groups[] = $groupRow;
    }
    $stageRow['groups'] = $groups;
    $leagueStages[] = $stageRow;
}
$stageTypeLabels = stageTypeLabels();
$hasStages = !empty($leagueStages);

    include 'includes/header.php';
?>

<style>
    /* ==========================================================================
       Schedule workspace — page-specific design.
       The shared breadcrumb, hero, tabs and KPI row keep their league-view
       styling; this block designs everything below them: the match board, the
       build-matches tools and the tournament progression strip.
       ========================================================================== */

    /* ---------- Modal stacking ---------------------------------------------- */
    /* The reschedule / conflict dialogs must sit above the schedule board. */
    #editMatchModal,
    #conflictModal {
        z-index: 2000;
    }

    .modal-backdrop {
        z-index: 1990;
    }

    #editMatchModal .modal-content,
    #conflictModal .modal-content {
        pointer-events: auto;
    }

    /* ---------- Workspace rhythm -------------------------------------------- */
    .sc-workspace { display:flex; flex-direction:column; gap:20px; }

    /* ---------- Tournament progression strip -------------------------------- */
    .sc-progress-panel { margin-bottom:0; }
    .sc-progress-panel .activity-header { padding:16px 22px; border-bottom:1px solid #eef2f7; }
    .sc-progress-panel .sc-form-body { padding:16px 22px 18px; }
    .sc-progress-cards { display:grid; grid-template-columns:repeat(auto-fill,minmax(190px,1fr)); gap:10px; }
    .sc-progress-card { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:10px 14px; border:1px solid #eef2f7; border-radius:9px; background:#f8fafc; }
    .sc-progress-card strong { margin:0; font-size:12.5px; color:#123f70; }
    .sc-chip { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:20px; font-size:10.5px; font-weight:600; letter-spacing:.3px; text-transform:uppercase; white-space:nowrap; }
    .sc-chip.is-done { background:#d1fae5; color:#065f46; }
    .sc-chip.is-active { background:#fef3c7; color:#92400e; }
    .sc-chip.is-info { background:#dbeafe; color:#1e40af; }
    .sc-champion { display:flex; align-items:center; gap:14px; padding:16px 18px; border:1px solid #a7f3d0; border-radius:10px; background:linear-gradient(105deg,#ecfdf5,#fff); }
    .sc-champion i { font-size:1.6rem; color:#059669; }
    .sc-champion .stat-label { font-size:10px; font-weight:600; letter-spacing:.6px; text-transform:uppercase; color:#94a3b8; }
    .sc-next-action { display:inline-flex; align-items:center; gap:7px; padding:8px 16px; border:1.5px solid #b45309; border-radius:8px; background:#b45309; color:#fff; font-size:12.5px; font-weight:600; }
    .sc-next-action:hover { background:#92400e; border-color:#92400e; color:#fff; }

    /* ---------- Match board -------------------------------------------------- */
    .sc-board-card { margin-bottom:0; }
    .sc-board-head { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:14px; padding:18px 22px; border-bottom:1px solid #eef2f7; }
    .sc-board-title { display:flex; align-items:center; gap:13px; min-width:0; }
    .sc-board-icon { width:44px; height:44px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; border-radius:12px; background:linear-gradient(135deg,#e8edf7,#dbe6fa); color:#123f70; font-size:1.12rem; }
    .sc-board-heading { margin:0; font-size:16px; font-weight:700; letter-spacing:-.01em; color:var(--lp-ink); }
    .sc-board-sub { margin-top:2px; font-size:12px; color:#94a3b8; }
    .sc-board-actions { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
    .sc-search { position:relative; }
    .sc-search i { position:absolute; left:11px; top:50%; transform:translateY(-50%); font-size:12.5px; color:#9ca3af; pointer-events:none; }
    .sc-search input { width:230px; max-width:100%; padding:7px 12px 7px 32px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12px; color:#334155; transition:border-color .2s ease, box-shadow .2s ease, background .2s ease; }
    .sc-search input:focus { outline:none; border-color:var(--lp-blue); background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .sc-filter { width:auto; padding:7px 32px 7px 12px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12px; color:#334155; }
    .sc-filter:focus { border-color:var(--lp-blue); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .sc-view-toggle { display:inline-flex; gap:3px; padding:3px; border-radius:9px; background:#eef2f7; }
    .sc-view-toggle .btn { border:none !important; border-radius:7px; padding:6px 14px; font-size:11.5px; font-weight:600; color:var(--lp-muted); background:transparent; box-shadow:none !important; }
    .sc-view-toggle .btn:hover { color:var(--lp-navy); }
    .sc-view-toggle .btn.active { background:#fff; color:var(--lp-navy); box-shadow:0 2px 6px rgba(9,23,60,.14) !important; }
    .sc-refresh-btn { display:inline-flex; align-items:center; gap:7px; padding:7px 14px; border:1.5px solid var(--lp-border); border-radius:8px; background:#fff; color:var(--lp-ink); font-size:12px; font-weight:600; text-decoration:none !important; transition:border-color .2s ease, background .2s ease, color .2s ease; }
    .sc-refresh-btn:hover { border-color:var(--lp-blue); background:var(--lp-soft); color:var(--lp-blue); }
    .sc-refresh-btn .sc-spin { animation:sc-spin .7s linear infinite; }
    @keyframes sc-spin { to { transform:rotate(360deg); } }
    .sc-board-body { padding:18px 20px 20px; background:#f6f8fb; }

    /* ---------- Board: stage / round sections ------------------------------- */
    .sc-board { border:1px solid #e7ecf4; border-radius:10px; overflow:hidden; margin-bottom:14px; background:#fff; box-shadow:0 1px 2px rgba(9,23,60,.04); }
    .sc-board:last-child { margin-bottom:0; }
    .schedule-section-header { display:flex; align-items:center; flex-wrap:wrap; gap:10px; padding:11px 16px; background:#f8fafc; border-bottom:1px solid #eef2f7; color:#123f70; font-size:11.5px; font-weight:700; letter-spacing:.55px; text-transform:uppercase; }
    .schedule-section-header > i { color:var(--lp-blue); font-size:13px; }
    .schedule-section-header .badge { display:inline-flex; align-items:center; padding:3px 9px; border-radius:20px; background:#e8edf7 !important; border:1px solid #d9e3f3; color:#123f70 !important; font-size:9.5px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; }
    .schedule-section-header .match-count { margin-left:auto; font-size:10.5px; font-weight:600; letter-spacing:.4px; color:#94a3b8; }

    /* ---------- Board: match table ------------------------------------------ */
    /* Scoped under .sc-board so these rules outrank the shared .activity-table
       defaults that are emitted later in the document body. */
    .sc-board .sc-table { margin:0; font-size:12.5px; }
    .sc-board .sc-table thead th { padding:10px 14px; background:#eef3f9; color:#5c6b82; font-size:9.5px; font-weight:700; letter-spacing:.8px; text-transform:uppercase; border:none; white-space:nowrap; }
    .sc-board .sc-table tbody td { padding:12px 14px; border-color:#f1f5f9; vertical-align:middle; }
    .sc-board .sc-table tbody tr { transition:background .15s ease; }
    .sc-board .sc-table tbody tr:hover { background:#f8faff; }
    .sc-board .sc-table tbody tr.is-completed-row:hover { background:#f4faf7; }
    .sc-board .sc-table tbody tr.is-bye-row { background:#fbfcfe; }
    .sc-board .sc-table tbody tr.is-bye-row td { color:#94a3b8; }
    .sc-board .sc-table tbody tr.is-bye-row:hover { background:#f7f9fc; }
    .sc-board .sc-table tbody tr.is-conflict-row > td { background:#fff6f6; }
    .sc-board .sc-table tbody tr.is-conflict-row:hover > td { background:#fff0f0; }
    .sc-board .sc-table tbody tr.is-conflict-row > td:first-child { box-shadow:inset 3px 0 0 #dc3545; }
    .sc-board .sc-table thead th:last-child,
    .sc-board .sc-table tbody td:last-child { min-width:150px; white-space:nowrap; text-align:right; }

    .sc-when { display:flex; flex-direction:column; gap:2px; }
    .sc-when-date { font-size:12.5px; font-weight:600; color:var(--lp-ink); white-space:nowrap; }
    .sc-when-time { display:inline-flex; align-items:center; gap:5px; font-size:11px; color:#94a3b8; white-space:nowrap; }
    .sc-when-time i { font-size:10.5px; }
    .sc-matchup { min-width:230px; }
    .sc-team { display:flex; align-items:center; gap:7px; line-height:1.5; }
    .sc-team-name { font-weight:600; color:#1f2937; text-transform:capitalize; }
    .sc-team.is-winner .sc-team-name { color:#0f7b4f; }
    .sc-team.is-loser .sc-team-name { color:#dc2626; }
    .sc-team.is-bye .sc-team-name { font-style:italic; font-weight:600; color:#94a3b8; }
    .sc-score.is-bye { font-size:10px; font-weight:700; letter-spacing:.6px; color:#94a3b8; }
    .sc-team .sc-team-win { font-size:11px; color:#0f7b4f; }
    .sc-seed { display:inline-flex; align-items:center; justify-content:center; min-width:20px; padding:1px 6px; border-radius:6px; background:#e8edf7; color:#09173c; font-size:10px; font-weight:700; letter-spacing:.3px; }
    .sc-stage-tag { display:block; margin-top:2px; font-size:10.5px; font-weight:500; color:#94a3b8; }
    .sc-cell-muted { color:#64748b; font-size:12px; white-space:nowrap; }
    .sc-cell-muted i { margin-right:5px; color:#b0bccd; font-size:11.5px; }
    .sc-score { display:inline-flex; align-items:center; gap:5px; font-variant-numeric:tabular-nums; font-size:14px; font-weight:700; color:var(--lp-ink); white-space:nowrap; }
    .sc-score .sc-score-sep { font-size:11px; font-weight:600; color:#cbd5e1; }
    .sc-score.is-pending { font-size:13px; font-weight:500; color:#cbd5e1; }
    .sc-actions { display:inline-flex; align-items:center; justify-content:flex-end; gap:5px; white-space:nowrap; }
    .sc-actions .league-icon-btn { width:32px; height:32px; border-radius:9px; font-size:.85rem; }
    .sc-published { display:inline-flex; align-items:center; gap:5px; font-size:10.5px; font-weight:600; color:#0f7b4f; white-space:nowrap; }
    .sc-board .lp-row-status { padding:3px 10px; font-size:10px; letter-spacing:.35px; }

    /* ---------- Build matches ------------------------------------------------ */
    .sc-builder { margin-bottom:0; }
    .sc-builder .activity-header { padding:18px 22px 14px; border-bottom:1px solid #eef2f7; }
    .sc-builder-tabs { display:flex; flex-wrap:wrap; gap:4px; padding:0 22px; border-bottom:1px solid #eef2f7; background:#fbfcfe; }
    .sc-builder-tab { position:relative; display:inline-flex; align-items:center; gap:7px; padding:13px 14px 12px; border:0; border-bottom:2px solid transparent; background:transparent; color:#64748b; font-size:12.5px; font-weight:600; cursor:pointer; transition:color .15s ease, border-color .15s ease; }
    .sc-builder-tab i { font-size:13px; }
    .sc-builder-tab:hover { color:#123f70; }
    .sc-builder-tab.is-active { color:#123f70; border-bottom-color:var(--lp-blue); }
    .sc-builder-tab:focus-visible { outline:2px solid rgba(37,99,235,.35); outline-offset:-2px; }
    .sc-builder-body { padding:20px 22px 22px; }
    .sc-builder-pane { display:none; }
    .sc-builder-pane.is-active { display:block; animation:fadeInUp .25s ease-out both; }

    .sc-lock-note { display:flex; align-items:flex-start; gap:11px; margin-bottom:18px; padding:12px 15px; border:1px solid #f5d9a8; border-radius:10px; background:#fffaf0; color:#7a4b02; font-size:12px; line-height:1.55; }
    .sc-lock-note > i { margin-top:2px; font-size:14px; }
    .sc-lock-note strong { font-weight:700; }
    /* Points creation actions (stage fixtures / qualifiers) at the single Setup page. */
    .sc-setup-note { display:flex; align-items:flex-start; gap:11px; margin-bottom:18px; padding:13px 15px; border:1px solid #d7e3f5; border-radius:10px; background:#f4f8ff; color:#334155; font-size:12px; line-height:1.55; }
    .sc-setup-note > i { margin-top:2px; font-size:15px; color:#2563eb; }
    .sc-setup-note strong { display:block; font-weight:700; color:#123f70; }
    .sc-setup-note-link { display:inline-flex; align-items:center; gap:5px; margin-top:6px; font-size:11.5px; font-weight:700; color:#2563eb; text-decoration:none; }
    .sc-setup-note-link:hover { color:#1d4ed8; text-decoration:underline; }

    /* ---------- Build matches: forms ---------------------------------------- */
    .sc-vs-col { display:flex; align-items:center; justify-content:center; padding-top:24px; }
    .sc-vs-badge { display:inline-flex; align-items:center; justify-content:center; width:38px; height:38px; border-radius:50%; background:#e8edf7; color:#123f70; font-size:10px; font-weight:800; letter-spacing:.6px; text-transform:uppercase; }
    .sc-form-hint { display:block; font-size:11.5px; line-height:1.55; color:#94a3b8; }
    .sc-form-actions { display:flex; align-items:center; flex-wrap:wrap; gap:12px; padding-top:4px; }
    .sc-builder .btn-primary { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; border-radius:9px; font-size:12.5px; font-weight:600; background:#123f70; border-color:#123f70; }
    .sc-builder .btn-primary:hover, .sc-builder .btn-primary:focus { background:#0d3160; border-color:#0d3160; }
    .sc-builder .btn-success { display:inline-flex; align-items:center; gap:7px; padding:9px 18px; border-radius:9px; font-size:12.5px; font-weight:600; }
    .sc-builder .btn-outline-primary { font-size:12px; font-weight:600; border-radius:8px; }
    .sc-builder .alert { border-radius:10px; font-size:12.5px; }



    /* ---------- Bracket view ------------------------------------------------- */
    .sc-bracket-toolbar { display:flex; align-items:flex-end; justify-content:space-between; flex-wrap:wrap; gap:12px; margin-bottom:12px; }
    .sc-bracket-toolbar-field { display:flex; flex-direction:column; gap:5px; min-width:220px; }
    .sc-bracket-toolbar-field label { font-size:10px; font-weight:700; letter-spacing:.5px; text-transform:uppercase; color:var(--lp-muted); }
    .sc-bracket-toolbar-field .form-select { padding:7px 30px 7px 11px; border:1.5px solid var(--lp-border); border-radius:8px; font-size:12.5px; background-color:#fff; }
    .sc-bracket-legend { display:flex; align-items:center; flex-wrap:wrap; gap:14px; font-size:11px; color:#64748b; }
    .sc-bracket-legend span { display:inline-flex; align-items:center; gap:6px; }
    .sc-bracket-hint { flex:1 1 100%; font-size:11px; color:#94a3b8; }
    .sc-legend-dot { display:inline-block; width:9px; height:9px; border-radius:50%; }
    .sc-legend-dot.is-win { background:#0f7b4f; }
    .sc-legend-dot.is-bye { background:#94a3b8; }
    .sc-legend-dot.is-tbd { background:#cbd5e1; }
    .sc-bracket-shell { border:1px solid #e7ecf4; border-radius:10px; background:#fff; padding:14px; }
    .sc-bracket-section { margin-bottom:18px; }
    .sc-bracket-section:last-child { margin-bottom:0; }
    .sc-bracket-section-head {
        display:flex; align-items:center; gap:8px; margin-bottom:10px;
        font-size:11px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:#475569;
    }
    .sc-bracket-section-head span { margin-left:auto; font-size:10px; font-weight:600; color:#94a3b8; letter-spacing:.3px; }
    .sc-bracket-section.is-winners .sc-bracket-section-head { color:#1d4ed8; }
    .sc-bracket-section.is-losers .sc-bracket-section-head { color:#b45309; }
    .sc-bracket-section.is-grand_final .sc-bracket-section-head { color:#0f7b4f; }
    .sc-bracket-section.is-grand_final .sc-bracket { min-width:320px; }
    .sc-bracket-scroll { overflow-x:auto; padding-bottom:4px; }
    .sc-bracket { width:100%; min-width:640px; border-collapse:separate; border-spacing:0; }
    .sc-bracket th { padding:0 0 12px; font-size:10px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:#64748b; text-align:center; white-space:nowrap; }
    .sc-bracket th.is-final { color:var(--lp-navy); }
    .sc-bracket td { position:relative; padding:6px 0; vertical-align:middle; }
    .sc-bracket td.sc-bracket-cell { padding-right:34px; }
    .sc-bracket td.sc-bracket-cell::after { content:''; position:absolute; top:50%; right:0; width:34px; border-top:2px solid #dbe4f0; }
    .sc-bracket td.is-final { padding-right:0; }
    .sc-bracket td.is-final::after { display:none; }
    .sc-bracket td.has-feeders { padding-left:34px; }
    .sc-bracket td.has-feeders::before { content:''; position:absolute; left:0; top:25%; height:50%; border-left:2px solid #dbe4f0; }
    .sc-bracket-match { position:relative; display:flex; flex-direction:column; gap:2px; min-width:190px; padding:8px 10px; border:1px solid #e2e8f2; border-radius:9px; background:#fff; box-shadow:0 1px 2px rgba(9,23,60,.04); }
    .sc-bracket td.has-feeders .sc-bracket-match::before { content:''; position:absolute; left:-34px; top:50%; width:34px; border-top:2px solid #dbe4f0; }
    .sc-bracket-match.is-bye, .sc-bracket-match.is-tbd { background:#fbfcfe; border-style:dashed; }
    .sc-bracket-side { display:flex; align-items:center; gap:7px; min-width:0; font-size:12.5px; line-height:1.5; }
    .sc-bracket-seed { flex:0 0 auto; min-width:24px; padding:1px 6px; border-radius:5px; background:#eef3f9; color:#5c6b82; font-size:9.5px; font-weight:700; letter-spacing:.4px; text-align:center; text-transform:uppercase; }
    .sc-bracket-name { flex:1 1 auto; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight:600; color:#1f2937; }
    .sc-bracket-side.is-winner .sc-bracket-name { color:#0f7b4f; }
    .sc-bracket-side.is-winner .sc-bracket-score { color:#0f7b4f; }
    .sc-bracket-side.is-loser .sc-bracket-name { color:#dc2626; }
    .sc-bracket-side.is-loser .sc-bracket-score { color:#dc2626; }
    .sc-bracket-side.is-bye .sc-bracket-name { font-style:italic; color:#94a3b8; }
    .sc-bracket-check { flex:0 0 auto; font-size:11px; color:#0f7b4f; }
    .sc-bracket-score { flex:0 0 auto; min-width:18px; text-align:right; font-size:11.5px; font-weight:700; color:#123f70; }
    .sc-bracket-foot { display:flex; align-items:center; flex-wrap:wrap; gap:6px; margin-top:4px; }
    .sc-bracket-chip { display:inline-flex; align-items:center; padding:2px 8px; border-radius:20px; font-size:9.5px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; }
    .sc-bracket-chip.is-draft { background:#eef2f7; color:#64748b; }
    .sc-bracket-chip.is-scheduled { background:#dbeafe; color:#1e40af; }
    .sc-bracket-chip.is-complete { background:#d1fae5; color:#065f46; }
    .sc-bracket-chip.is-bye { background:#e8edf7; color:#5c6b82; }
    .sc-bracket-chip.is-tbd { background:#f1f5f9; color:#94a3b8; }
    .sc-bracket-chip.is-cancelled { background:#fee2e2; color:#991b1b; }
    .sc-bracket-date { font-size:10px; color:#94a3b8; }
    .sc-bracket-columns { display:flex; gap:22px; overflow-x:auto; padding-bottom:4px; }
    .sc-bracket-column { flex:1 0 200px; display:flex; flex-direction:column; gap:10px; }
    .sc-bracket-column-head { font-size:10px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:#64748b; }
    @media (max-width:640px) {
        .sc-bracket td.sc-bracket-cell { padding-right:20px; }
        .sc-bracket td.sc-bracket-cell::after { width:20px; }
        .sc-bracket td.has-feeders { padding-left:20px; }
        .sc-bracket td.has-feeders .sc-bracket-match::before { left:-20px; width:20px; }
        .sc-bracket-match { min-width:150px; }
    }

    /* ---------- FullCalendar alignment -------------------------------------- */
    #scheduleCalendar { padding:16px; border:1px solid #e7ecf4; border-radius:10px; background:#fff; }
    #scheduleCalendar .fc-toolbar-title { font-size:1.05rem; font-weight:600; color:var(--lp-ink); }
    #scheduleCalendar .fc-button-primary { background:#09173c; border-color:#09173c; font-size:12px; font-weight:600; text-transform:none; }
    #scheduleCalendar .fc-button-primary:hover { background:#1a3a7c; border-color:#1a3a7c; }
    #scheduleCalendar .fc-button-primary:disabled { background:#cbd5e1; border-color:#cbd5e1; }
    #scheduleCalendar .fc-button-primary:not(:disabled).fc-button-active,
    #scheduleCalendar .fc-button-primary:not(:disabled):active { background:#2563eb; border-color:#2563eb; }
    #scheduleCalendar .fc-daygrid-event { border:none; border-radius:6px; padding:2px 5px; font-size:11.5px; font-weight:500; }
    #scheduleCalendar .fc-col-header-cell-cushion { font-size:11px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; color:var(--lp-muted); text-decoration:none; }
    #scheduleCalendar .fc-daygrid-day-number { font-size:12px; color:var(--lp-ink); text-decoration:none; }
    #scheduleCalendar .fc-today-button { text-transform:none; }
    .sc-board-body .lp-empty { padding:46px 20px; }
    .lp-empty .spinner-border { margin-bottom:12px; }

    /* ---------- Responsive --------------------------------------------------- */
    @media (max-width:991.98px) { .sc-vs-col { display:none; } }
    @media (max-width:767.98px) {
        .sc-board-head, .sc-board-body { padding:16px; }
        .sc-board-actions { width:100%; }
        .sc-search { flex:1 1 100%; }
        .sc-search input { width:100%; }
        .sc-filter { flex:1 1 auto; }
        .sc-builder .activity-header { padding:16px 16px 12px; }
        .sc-builder-tabs { padding:0 16px; }
        .sc-builder-body { padding:16px; }
        .schedule-section-header .match-count { margin-left:0; }
        #scheduleCalendar { padding:10px; }
    }
</style>

<body id="page-top" class="body">
    <?php include 'includes/bar.php'; ?>

    <?php
    $lockTooltip = $hasOpenCorrections
        ? 'Scheduling is locked until all returned applications are resubmitted or their 72-hour correction deadlines expire.'
        : 'Unlock by: filling all team slots (' . $approvedCount . '/' . intval($league['max_team']) . '), waiting for registration to end (' . date('M d, Y', strtotime($league['regis_end'])) . '), or manually closing registration from the Overview page.';

    /* ------------------------------------------------------------------
       Schedule overview numbers for the KPI row (prepared statement only).
       ------------------------------------------------------------------ */
    $schedStats = ['total' => 0, 'drafts' => 0, 'published' => 0, 'completed' => 0, 'conflicts' => 0];
    $statsStmt = $conn->prepare(
        "SELECT COUNT(*) AS total,
                SUM(CASE WHEN published_at IS NULL THEN 1 ELSE 0 END) AS drafts,
                SUM(CASE WHEN LOWER(status) = 'completed' THEN 1 ELSE 0 END) AS completed,
                SUM(CASE WHEN conflict_detected = 1 THEN 1 ELSE 0 END) AS conflicts
         FROM tbl_schedule
         WHERE league_id = ? AND (is_archived = 0 OR is_archived IS NULL)
           AND NOT (team_b = 0 AND seed_b = 'BYE')"
    );
    $statsStmt->bind_param('i', $leagueId);
    $statsStmt->execute();
    $schedRow = $statsStmt->get_result()->fetch_assoc();
    $statsStmt->close();
    if ($schedRow) {
        $schedStats['total']     = (int) $schedRow['total'];
        $schedStats['drafts']    = (int) $schedRow['drafts'];
        $schedStats['completed'] = (int) $schedRow['completed'];
        $schedStats['conflicts'] = (int) $schedRow['conflicts'];
    }
    $schedStats['published'] = max(0, $schedStats['total'] - $schedStats['drafts']);
    $completedPct = $schedStats['total'] > 0 ? min(100, (int) round($schedStats['completed'] / $schedStats['total'] * 100)) : 0;
    $publishedPct = $schedStats['total'] > 0 ? min(100, (int) round($schedStats['published'] / $schedStats['total'] * 100)) : 0;

    // Hero banner data.
    $maxTeams        = max(1, (int) ($league['max_team'] ?? 1));
    $slotPct         = min(100, (int) round($approvedCount / $maxTeams * 100));
    $leagueStatus    = getLeagueStatus($league);
    $statusLabel     = getLeagueStatusLabel($leagueStatus);
    $statusPill      = 'lp-pill-' . strtolower($leagueStatus);
    $schedStatePill  = $canSchedule ? 'lp-pill-open' : 'lp-pill-upcoming';
    $schedStateIcon  = $canSchedule ? 'bi-unlock-fill' : 'bi-lock-fill';
    $schedStateLabel = $canSchedule ? 'Scheduling ready' : 'Scheduling locked';

    $heroRaw = (string) ($league['league_image'] ?? '');
    if ($heroRaw !== '' && StorageService::isCloudinary($heroRaw)) {
        $heroImage = $heroRaw;
    } else {
        $heroFile  = basename($heroRaw);
        $heroImage = ($heroFile !== '' && is_file(__DIR__ . '/../../assets/uploads/' . $heroFile))
            ? '../../assets/uploads/' . $heroFile
            : '../../assets/images/default-league.svg';
    }

    $fmtDate = static function ($value) {
        $value = trim((string) $value);
        $ts = $value !== '' ? strtotime($value) : false;
        return $ts ? date('M d, Y', $ts) : '—';
    };
    $leagueNameSafe  = htmlspecialchars((string) ($league['league_name'] ?? ''));
    $leagueDateRange = $fmtDate($league['start_date'] ?? '') . ' – ' . $fmtDate($league['end_date'] ?? '');
    $regisDateRange  = $fmtDate($league['regis_start'] ?? '') . ' – ' . $fmtDate($league['regis_end'] ?? '');
    $sportLabel      = trim((string) ($league['sport'] ?? '')) !== '' ? $league['sport'] : '—';
    $divisionLabel   = trim((string) ($league['division'] ?? '')) !== '' ? $league['division'] : '—';
    $categoryLabel   = trim((string) ($league['category'] ?? '')) !== '' ? $league['category'] : '—';
    $venueLabel      = trim((string) ($league['venue'] ?? '')) !== '' ? $league['venue'] : '—';
    ?>

    <div class="container-fluid lp-page" style="padding-top:20px;min-height:800px;">
        <div class="row p-3">
            <div class="card cards" style="background:transparent;box-shadow:none;border:none;">
                <div class="card-body p-0">
                    <?php include 'includes/dashboard-styles.php'; ?>
                    <?php include 'includes/league-view-styles.php'; ?>
                    <?php include_once __DIR__ . '/../../includes/summary-strip-styles.php'; ?>

                    <!-- ==================== BREADCRUMB ==================== -->
                    <nav class="lp-crumbs dash-animate" aria-label="Breadcrumb">
                        <a href="league.php"><i class="bi bi-arrow-left"></i> League Management</a>
                        <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
                        <span class="lp-crumb-current"><?= $leagueNameSafe ?></span>
                    </nav>
                    <?php $leagueHeroData = $league; $leagueHeroApprovedCount = $approvedCount; include 'includes/league-hero.php'; ?>
                    <!-- ==================== LEAGUE TABS ==================== -->
                    <div class="lp-tabs dash-animate dash-animate-d1">
                        <?php $leagueTabLeagueId = $leagueId; include 'includes/league_tabs.php'; ?>
                    </div>
                    

                    <div class="sc-workspace">

                        

                        <!-- ==================== MATCH SCHEDULE ==================== -->
                        <div class="activity-section sc-board-card dash-animate dash-animate-d3">
                            <div class="sc-board-head">
                                <div class="sc-board-title">
                                    <span class="sc-board-icon"><i class="bi bi-calendar2-week-fill"></i></span>
                                    <div>
                                        <div class="sc-board-heading">Match Schedule</div>
                                        
                                    </div>
                                    
                                </div>
                                <div class="sc-board-actions">
                                    <div class="sc-search">
                                        <i class="bi bi-search"></i>
                                        <input type="search" id="scScheduleSearch" placeholder="Search teams, venue, Committee…" autocomplete="off" aria-label="Search matches">
                                    </div>
                                    <select id="scStatusFilter" class="sc-filter form-select" aria-label="Filter by status">
                                        <option value="all">All statuses</option>
                                        <option value="draft">Drafts</option>
                                        <option value="scheduled">Scheduled</option>
                                        <option value="upcoming">Upcoming</option>
                                        <option value="postponed">Postponed</option>
                                        <option value="completed">Completed</option>
                                        <option value="cancelled">Cancelled</option>
                                        <option value="conflicts">With conflicts</option>
                                    </select>
                                    <div class="sc-view-toggle" role="group" aria-label="Schedule view">
                                        <button type="button" class="btn active" id="btnTableView"><i class="bi bi-table"></i> Table</button>
                                        <button type="button" class="btn" id="btnCalendarView"><i class="bi bi-calendar3"></i> Calendar</button>
                                        <button type="button" class="btn" id="btnBracketView"><i class="bi bi-diagram-3"></i> Bracket</button>
                                    </div>
                                    <button type="button" class="sc-refresh-btn" id="scRefreshBtn"><i class="bi bi-arrow-clockwise"></i> Refresh</button>
                                </div>
                            </div>
                            <div class="sc-board-body">
                                <div id="tableViewWrapper">
                                    <!-- Boards are generated by JavaScript -->
                                    <div id="scheduleTablesContainer">
                                        <div class="lp-empty">
                                            <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
                                            <p class="mt-3">Loading schedule…</p>
                                        </div>
                                    </div>
                                </div>
                                <div id="calendarViewWrapper" style="display:none;">
                                    <div id="scheduleCalendar"></div>
                                </div>
                                <div id="bracketViewWrapper" style="display:none;">
                                    <div class="sc-bracket-toolbar">
                                        <div class="sc-bracket-toolbar-field">
                                            <label for="scBracketStage">Knockout stage</label>
                                            <select id="scBracketStage" class="form-select" aria-label="Choose the knockout stage to draw"></select>
                                        </div>
                                        <div class="sc-bracket-legend" aria-label="Bracket legend">
                                            <span><i class="sc-legend-dot is-win" aria-hidden="true"></i>Winner</span>
                                            <span><i class="sc-legend-dot is-bye" aria-hidden="true"></i>Bye / auto-advanced</span>
                                            <span><i class="sc-legend-dot is-tbd" aria-hidden="true"></i>To be decided</span>
                                        </div>
                                        <span class="sc-bracket-hint">The bracket always shows the whole stage; search and status filters apply to the Table and Calendar views.</span>
                                    </div>
                                    <div id="scheduleBracket" class="sc-bracket-shell">
                                        <div class="lp-empty">
                                            <span class="spinner-border text-primary" role="status" aria-hidden="true"></span>
                                            <p class="mt-3">Loading bracket…</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- ==================== ADD MATCHES ==================== -->
                        <div class="activity-section sc-builder dash-animate dash-animate-d4" id="matchBuilder">
                            <div class="activity-header">
                                <div class="activity-title"><i class="bi bi-plus-square-fill"></i> Add Matches</div>
                                <span class="activity-badge-count">Drafts first · publish after review</span>
                            </div>
                            <?php if (!$hasStages): ?>
                            <div class="sc-builder-tabs" role="tablist" aria-label="Match building options">
                                <button type="button" class="sc-builder-tab is-active" role="tab" aria-selected="true" data-builder-tab="manual">
                                    <i class="bi bi-plus-square-fill"></i> Manual match
                                </button>
                                <button type="button" class="sc-builder-tab" role="tab" aria-selected="false" data-builder-tab="auto">
                                    <i class="bi bi-stars"></i> Auto pairing
                                </button>
                            </div>
                            <?php endif; ?>
                            <div class="sc-builder-body">
                                <?php if (!$canSchedule): ?>
                                <div class="sc-lock-note">
                                    <i class="bi bi-lock-fill"></i>
                                    <div><strong>Scheduling is locked.</strong> <span><?= htmlspecialchars($lockTooltip) ?></span></div>
                                </div>
                                <?php endif; ?>

                               

                                <!-- Manual match pane -->
                                <div class="sc-builder-pane is-active" role="tabpanel" data-builder-pane="manual">
                                    <form id="manualMatchForm" class="row g-3 lp-form">
                                        <div class="col-lg-5">
                                            <label class="form-label">Team A</label>
                                            <select name="team_a_id" class="form-select" required>
                                                <option value="">Select Team</option>
                                                <?php
                                                $teams = getApprovedTeams($conn, $leagueId);
                                                foreach ($teams as $team) {
                                                    echo '<option value="' . $team['id'] . '">' . htmlspecialchars($team['team_name']) . '</option>';
                                                }
                                                ?>
                                            </select>
                                        </div>
                                        <div class="col-lg-2 sc-vs-col">
                                            <span class="sc-vs-badge">vs</span>
                                        </div>
                                        <div class="col-lg-5">
                                            <label class="form-label">Team B</label>
                                            <select name="team_b_id" class="form-select" required>
                                                <option value="">Select Team</option>
                                                <?php foreach ($teams as $team): ?>
                                                    <option value="<?= (int)$team['id'] ?>"><?= htmlspecialchars($team['team_name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>

                                        <div class="col-md-4">
                                            <label class="form-label">Date</label>
                                            <input type="date" name="date" class="form-control" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Time</label>
                                            <input type="time" name="time" class="form-control" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Venue</label>
                                            <select name="venue" class="form-select">
                                                <option value="">Assign later</option>
                                                <?php foreach ($venues as $venue): ?>
                                                    <option value="<?= htmlspecialchars($venue, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($venue, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Committee</label>
                                            <select name="committee_user_id" class="form-select">
                                                <option value="">Assign later</option>
                                                <?php foreach ($committees as $committee): ?>
                                                    <option value="<?= (int)$committee['id'] ?>"><?= htmlspecialchars($committee['fname'].' '.$committee['lname']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <?php if ($leagueStages): ?>
                                        <div class="col-md-4">
                                            <label class="form-label">Stage / Bracket</label>
                                            <select name="stage_group_id" class="form-select">
                                                <option value="0">Not a stage match</option>
                                                <?php foreach ($leagueStages as $stageOption): ?>
                                                    <optgroup label="<?= htmlspecialchars($stageOption['name']) ?> — <?= htmlspecialchars($stageTypeLabels[$stageOption['stage_type']] ?? $stageOption['stage_type']) ?>">
                                                        <?php foreach ($stageOption['groups'] as $groupOption): ?>
                                                            <option value="<?= (int)$groupOption['id'] ?>"><?= htmlspecialchars($groupOption['name']) ?> · <?= (int)$groupOption['team_count'] ?> team(s)</option>
                                                        <?php endforeach; ?>
                                                    </optgroup>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Tag the match so it counts in that stage's table/bracket.</div>
                                        </div>
                                        <?php endif; ?>
                                        <?php if ((int)($league['grouping_enabled'] ?? 0) === 1): ?>
                                        <div class="col-md-4">
                                            <label class="form-label">Match Group</label>
                                            <select name="league_group_id" class="form-select">
                                                <option value="0">No group / progression match</option>
                                                <?php foreach ($leagueGroups as $group): ?>
                                                    <option value="<?= (int)$group['id'] ?>"><?= htmlspecialchars($group['name']) ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text">Select a group only for matches that count toward that group's standings.</div>
                                        </div>
                                        <?php endif; ?>
                                        <div class="col-12 sc-form-actions">
                                            <?php if ($canSchedule): ?>
                                                <button type="submit" class="btn btn-primary">
                                                    <i class="bi bi-plus-lg"></i> Save Match Draft
                                                </button>
                                            <?php else: ?>
                                                <span data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($lockTooltip, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="button" class="btn btn-primary" disabled>
                                                        <i class="bi bi-lock-fill"></i> Save Match Draft
                                                    </button>
                                                </span>
                                            <?php endif; ?>
                                            <span class="sc-form-hint">Matches are saved as drafts — set the date, time, venue and Committee, then publish from the board above.</span>
                                        </div>
                                    </form>
                                </div>
                                <!-- /manual match pane -->

                                <?php if (!$hasStages): ?>
                                <!-- Auto pairing pane -->
                                <div class="sc-builder-pane" role="tabpanel" data-builder-pane="auto">
                                    <div class="alert alert-warning d-flex align-items-start gap-2">
                                        <i class="bi bi-exclamation-triangle-fill"></i>
                                        <span>
                                            <strong>No competition structure yet.</strong>
                                            This league falls back to the round-robin pairing assistant. Applying a structure in Setup unlocks
                                            bracket, pool and playoff generation.
                                            <a class="alert-link" href="setup.php?id=<?= (int) $leagueId ?>#step-format">Set it up &rarr;</a>
                                        </span>
                                    </div>
                                    <form id="autoScheduleForm" class="row g-3 lp-form">
                                        <div class="col-md-4">
                                            <label class="form-label">Start Date</label>
                                            <input type="date" name="start_date" class="form-control" required>
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Venue (Optional)</label>
                                            <select name="venue" class="form-select">
                                                <option value="">Assign later</option>
                                                <?php foreach ($venues as $venue): ?>
                                                    <option value="<?= htmlspecialchars($venue, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($venue, ENT_QUOTES, 'UTF-8') ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-4 d-flex align-items-center">
                                            <span class="sc-form-hint">Pairings are generated as unpublished drafts you can review, reschedule, then publish.</span>
                                        </div>
                                        <div class="col-12 sc-form-actions">
                                            <?php if ($canSchedule): ?>
                                                <button type="submit" class="btn btn-success">
                                                    <i class="bi bi-magic"></i> Generate Draft Pairings
                                                </button>
                                            <?php else: ?>
                                                <span data-bs-toggle="tooltip" data-bs-placement="top" title="<?= htmlspecialchars($lockTooltip, ENT_QUOTES, 'UTF-8') ?>">
                                                    <button type="button" class="btn btn-success" disabled>
                                                        <i class="bi bi-lock-fill"></i> Generate Draft Pairings
                                                    </button>
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </form>
                                </div>
                                <!-- /auto pairing pane -->
                                <?php endif; ?>
                            </div><!-- /sc-builder-body -->
                        </div><!-- /sc-builder -->

                    </div><!-- /sc-workspace -->
                </div>
            </div>
        </div>
    </div>

    <!-- Reschedule Match Modal -->
    <div class="modal fade lp-modal" id="editMatchModal" tabindex="-1" aria-labelledby="editMatchModalLabel">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="editMatchModalLabel"><i class="bi bi-calendar-event me-2"></i>Reschedule Match</h5>
                        <span class="modal-subtitle">Update the date, time, venue or assigned Committee</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" onclick="hideScheduleModal('editMatchModal')" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="editMatchForm" class="row g-3 lp-form">
                        <input type="hidden" name="schedule_id" id="editScheduleId">
                        <div class="row g-3">
                            <div class="col-md-6"><label class="form-label">Date</label>
                                <input type="date" name="date" id="editDate" class="form-control" required>
                            </div>
                            <div class="col-md-6"><label class="form-label">Time</label>
                                <input type="time" name="time" id="editTime" class="form-control" required>
                            </div>
                        </div>
                        <div class="row g-3 mt-1">
                            <div class="col-md-6"><label class="form-label">Venue</label>
                                <select name="venue" id="editVenue" class="form-select">
                                    <option value="">Assign later</option>
                                    <?php foreach ($venues as $venue): ?>
                                        <option value="<?= htmlspecialchars($venue, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($venue, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6"><label class="form-label">Assigned Committee</label>
                                <select name="committee_user_id" id="editCommitteeUserId" class="form-select">
                                    <option value="">Assign later</option>
                                    <?php foreach ($committees as $committee): ?>
                                        <option value="<?= (int)$committee['id'] ?>"><?= htmlspecialchars($committee['fname'].' '.$committee['lname']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal" onclick="hideScheduleModal('editMatchModal')">Close</button>
                    <button type="button" class="btn btn-primary" onclick="saveEditMatch()">Save Reschedule</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Conflict Details Modal -->
    <div class="modal fade lp-modal" id="conflictModal" tabindex="-1" aria-labelledby="conflictModalLabel">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger">
                    <div>
                        <h5 class="modal-title" id="conflictModalLabel"><i class="bi bi-exclamation-triangle-fill me-2"></i>Scheduling Conflict</h5>
                        <span class="modal-subtitle">Resolve these clashes before publishing the match</span>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" data-dismiss="modal" onclick="hideScheduleModal('conflictModal')"></button>
                </div>
                <div class="modal-body">
                    <div id="conflictDetails"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal" data-dismiss="modal" onclick="hideScheduleModal('conflictModal')">Close</button>
                </div>
            </div>
        </div>
    </div>

    </div><!-- /#content (opened in includes/bar.php) -->

    <?php include 'includes/footer.php'; ?>
    <!-- FullCalendar (v6 global bundle) powers the Calendar view below.
         It must load after jQuery, which includes/footer.php pulls in. -->
    <script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/fullcalendar/6.1.10/index.global.min.js"></script>

    <script>
        const leagueId = <?php echo htmlspecialchars(json_encode($leagueId), ENT_QUOTES, 'UTF-8'); ?>;
        <?php
            $protestWindowMinutes = max(1, (int)(getLeagueSportProfile($conn, (int)$leagueId)['protest_window_minutes'] ?? 1440));
            $protestWindowText = $protestWindowMinutes >= 1440
                ? round($protestWindowMinutes / 1440, 2) . ' hours'
                : $protestWindowMinutes . ' minutes';
        ?>
        const protestWindowText = <?= json_encode($protestWindowText) ?>;
        const csrfToken = <?= json_encode(csrfToken()) ?>;
        let scheduleCalendarInstance = null;

        // The schedule is fetched once; search and status filters only re-render
        // the boards from this cache, so filtering never costs an extra request.
        let latestSchedule = [];
        const scheduleFilter = { term: '', status: 'all' };

        // ---- View toggle: table / calendar / bracket ---------------------------
        // One map keeps the three views mutually exclusive and lets refresh /
        // filter code ask which wrapper is visible without repeating IDs.
        const scheduleViews = {
            table:    { button: 'btnTableView',    wrapper: 'tableViewWrapper' },
            calendar: { button: 'btnCalendarView', wrapper: 'calendarViewWrapper' },
            bracket:  { button: 'btnBracketView',  wrapper: 'bracketViewWrapper' }
        };

        function showScheduleView(viewKey) {
            Object.keys(scheduleViews).forEach(function (key) {
                const view = scheduleViews[key];
                const button = document.getElementById(view.button);
                const wrapper = document.getElementById(view.wrapper);
                if (!button || !wrapper) return;
                const isActive = key === viewKey;
                wrapper.style.display = isActive ? '' : 'none';
                button.classList.toggle('active', isActive);
            });
            // Rebuild so the chosen view always reflects the latest schedule data.
            if (viewKey === 'calendar') initScheduleCalendar();
            if (viewKey === 'bracket') initScheduleBracket();
        }

        Object.keys(scheduleViews).forEach(function (key) {
            const button = document.getElementById(scheduleViews[key].button);
            if (button) button.addEventListener('click', function () { showScheduleView(key); });
        });

        // Conflict chips keep their JSON payload in a data attribute, so the row
        // template never interpolates it into an inline onclick handler.
        document.getElementById('scheduleTablesContainer').addEventListener('click', function (event) {
            const chip = event.target.closest('.js-conflict');
            if (!chip) return;
            showConflictDetails(chip.getAttribute('data-reason') || '');
        });

        // ---- Board toolbar: search, status filter and refresh -----------------
        const scheduleSearchInput = document.getElementById('scScheduleSearch');
        const scheduleStatusFilter = document.getElementById('scStatusFilter');

        function applyScheduleFilterChange() {
            displaySchedule(latestSchedule);
            if (scheduleCalendarInstance && document.getElementById('calendarViewWrapper').style.display !== 'none') {
                initScheduleCalendar();
            }
        }

        if (scheduleSearchInput) {
            scheduleSearchInput.addEventListener('input', function () {
                scheduleFilter.term = this.value.trim().toLowerCase();
                applyScheduleFilterChange();
            });
        }

        if (scheduleStatusFilter) {
            scheduleStatusFilter.addEventListener('change', function () {
                scheduleFilter.status = this.value;
                applyScheduleFilterChange();
            });
        }

        const scheduleRefreshBtn = document.getElementById('scRefreshBtn');
        if (scheduleRefreshBtn) {
            scheduleRefreshBtn.addEventListener('click', function () {
                const icon = scheduleRefreshBtn.querySelector('i');
                if (icon) icon.classList.add('sc-spin');
                loadSchedule();
                loadProgressionSummary();
                window.setTimeout(function () { if (icon) icon.classList.remove('sc-spin'); }, 700);
            });
        }

        // ---- Build-matches tabs ------------------------------------------------
        document.querySelectorAll('.sc-builder-tab').forEach(function (tab) {
            tab.addEventListener('click', function () {
                const target = tab.getAttribute('data-builder-tab');
                document.querySelectorAll('.sc-builder-tab').forEach(function (item) {
                    const isActive = item === tab;
                    item.classList.toggle('is-active', isActive);
                    item.setAttribute('aria-selected', isActive ? 'true' : 'false');
                });
                document.querySelectorAll('.sc-builder-pane').forEach(function (pane) {
                    pane.classList.toggle('is-active', pane.getAttribute('data-builder-pane') === target);
                });
            });
        });

        function initScheduleCalendar() {
            // FullCalendar is loaded from assets/vendor at the end of this page. If it is
            // unavailable (missing file, deploy problem), explain it instead of leaving
            // the panel blank — `new FullCalendar.Calendar` would throw otherwise.
            if (typeof FullCalendar === 'undefined') {
                document.getElementById('scheduleCalendar').innerHTML =
                    '<div class="lp-empty"><i class="bi bi-calendar-x"></i><p>The calendar library could not be loaded. Reload the page — the Table view still works.</p></div>';
                return;
            }

            $.ajax({
                url: 'action/schedulingAction.php',
                type: 'POST',
                data: { getSchedule: true, league_id: leagueId, csrf_token: csrfToken },
                dataType: 'json',
                success: function(res) {
                    if (res.status !== 200) return;
                    // Re-building after a filter change must retire the old instance,
                    // otherwise FullCalendar keeps the previous event set.
                    if (scheduleCalendarInstance) {
                        scheduleCalendarInstance.destroy();
                        scheduleCalendarInstance = null;
                    }
                    const statusColors = { completed:'#198754', scheduled:'#0d6efd', upcoming:'#0d6efd', postponed:'#ffc107', cancelled:'#dc3545' };
                    const events = res.schedule.filter(function (m) {
                        return m.date && matchMatchesFilter(m);
                    }).map(m => {
                        const color = statusColors[String(m.status || '').toLowerCase()] || '#6c757d';
                        return {
                            id: m.id,
                            // FullCalendar renders titles as text, so no HTML escaping.
                            title: (m.team_a_name || 'TBD') + ' vs ' + (m.team_b_name || 'TBD'),
                            start: m.date + 'T' + (m.time || '00:00'),
                            backgroundColor: color,
                            borderColor: color,
                            extendedProps: { match: m }
                        };
                    });
                    const calEl = document.getElementById('scheduleCalendar');
                    scheduleCalendarInstance = new FullCalendar.Calendar(calEl, {
                        initialView: 'dayGridMonth',
                        headerToolbar: { left: 'prev,next today', center: 'title', right: 'dayGridMonth,timeGridWeek,listWeek' },
                        height: 'auto',
                        events: events,
                        eventClick: function(info) {
                            const m = info.event.extendedProps.match;
                            const isCompleted = String(m.status || '').toLowerCase() === 'completed';
                            Swal.fire({
                                title: escapeHtml(m.team_a_name) + ' vs ' + escapeHtml(m.team_b_name),
                                html: `<table class="table table-sm text-start">
                                    <tr><th>Date</th><td>${escapeHtml(m.date)}</td></tr>
                                    <tr><th>Time</th><td>${escapeHtml(m.time)}</td></tr>
                                    <tr><th>Venue</th><td>${escapeHtml(m.venue || '\u2014')}</td></tr>
                                    <tr><th>Committee</th><td>${escapeHtml(m.committee_name || 'Unassigned')}</td></tr>
                                    <tr><th>Status</th><td>${escapeHtml(m.status)}</td></tr>
                                    <tr><th>Score</th><td>${escapeHtml(m.a_score || 0)} - ${escapeHtml(m.b_score || 0)}</td></tr>
                                </table>`,
                                showCancelButton: !isCompleted,
                                confirmButtonText: isCompleted ? 'Close' : 'Reschedule',
                                cancelButtonText: 'Close',
                                confirmButtonColor: isCompleted ? '#6c757d' : '#0d6efd'
                            }).then(result => {
                                if (result.isConfirmed && !isCompleted) {
                                    editMatch(m.id, m.date, m.time, m.venue || '', m.committee_user_id || '');
                                }
                            });
                        }
                    });
                    scheduleCalendarInstance.render();
                },
                error: function () {
                    document.getElementById('scheduleCalendar').innerHTML =
                        '<div class="lp-empty"><i class="bi bi-calendar-x"></i><p>Could not load the schedule data. Please refresh the page and try again.</p></div>';
                }
            });
        }

        function getScheduleModalElement(modalId) {
            return document.getElementById(modalId);
        }

        function showScheduleModal(modalId) {
            const modalEl = getScheduleModalElement(modalId);
            if (!modalEl) return;

            if (window.bootstrap && bootstrap.Modal && typeof bootstrap.Modal.getOrCreateInstance === 'function') {
                bootstrap.Modal.getOrCreateInstance(modalEl).show();
                return;
            }

            if (window.jQuery && typeof $('#' + modalId).modal === 'function') {
                $('#' + modalId).modal('show');
                return;
            }

            modalEl.classList.add('show');
            modalEl.style.display = 'block';
            modalEl.removeAttribute('aria-hidden');
            document.body.classList.add('modal-open');
        }

        function hideScheduleModal(modalId) {
            const modalEl = getScheduleModalElement(modalId);
            if (!modalEl) return;

            if (window.bootstrap && bootstrap.Modal) {
                const instance = typeof bootstrap.Modal.getInstance === 'function'
                    ? bootstrap.Modal.getInstance(modalEl)
                    : null;

                if (instance && typeof instance.hide === 'function') {
                    instance.hide();
                    return;
                }
            }

            if (window.jQuery && typeof $('#' + modalId).modal === 'function') {
                $('#' + modalId).modal('hide');
                return;
            }

            modalEl.classList.remove('show');
            modalEl.style.display = 'none';
            modalEl.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('modal-open');
            document.querySelectorAll('.modal-backdrop').forEach(backdrop => backdrop.remove());
        }

        // Load schedule on page load
        document.addEventListener('DOMContentLoaded', function() {
            loadSchedule();
            loadProgressionSummary();
            // Deep link: schedule.php?id=X#bracket (or ?view=bracket) opens the
            // bracket view. It is re-drawn by loadSchedule() once data arrives.
            const initialView = new URLSearchParams(window.location.search).get('view');
            if (initialView === 'bracket' || window.location.hash === '#bracket') {
                showScheduleView('bracket');
            }
            // Init Bootstrap tooltips
            document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => new bootstrap.Tooltip(el));
        });

        function loadSchedule() {
            $.ajax({
                url: 'action/schedulingAction.php',
                type: 'POST',
                data: { getSchedule: true, league_id: leagueId, csrf_token: csrfToken },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 200) {
                        displaySchedule(res.schedule);
                        // A manual refresh must also rebuild the calendar when it is visible.
                        if (scheduleCalendarInstance) initScheduleCalendar();
                        // …and the bracket, so results / byes show up immediately.
                        const bracketWrapper = document.getElementById('bracketViewWrapper');
                        if (bracketWrapper && bracketWrapper.style.display !== 'none') initScheduleBracket();
                    }
                }
            });
        }

        // One predicate drives both the table boards and the calendar so the two
        // views always show the same filtered set.
        function matchMatchesFilter(match) {
            const status = String(match.status || '').toLowerCase();
            const wanted = scheduleFilter.status;

            // Byes are bracket slots, not playable matches: they belong to the
            // complete bracket picture, so a specific status filter hides them.
            if (wanted !== 'all' && isByeMatch(match)) return false;

            if (wanted === 'conflicts') {
                if (Number(match.conflict_detected) !== 1) return false;
            } else if (wanted === 'draft') {
                if (match.published_at) return false;
            } else if (wanted !== 'all') {
                if (status !== wanted) return false;
            }

            if (scheduleFilter.term !== '') {
                const haystack = [
                    match.team_a_name, match.team_b_name, match.venue,
                    match.committee_name, match.stage_name, match.stage_group_name,
                    match.date, match.time, match.seed_a, match.seed_b
                ].map(function (value) { return String(value || ''); }).join(' ').toLowerCase();
                if (haystack.indexOf(scheduleFilter.term) === -1) return false;
            }

            return true;
        }

        // Bye rows are the one-team bracket slots the generator auto-completes. The
        // board lists them so the whole bracket is visible, but they are not
        // playable matches and never carry scheduling actions or counters.
        function isByeMatch(match) {
            return Number(match.team_b) === 0 && String(match.seed_b || '').toUpperCase() === 'BYE';
        }

        function updateScheduleCount(count) {
            const badge = document.getElementById('scMatchCount');
            if (!badge) return;
            badge.textContent = count + (count === 1 ? ' match' : ' matches');
        }

        function formatScheduleDate(value) {
            if (!value) return 'TBD';
            const parts = String(value).split('-');
            if (parts.length !== 3) return String(value);
            const date = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
            if (isNaN(date.getTime())) return String(value);
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
        }

        function displaySchedule(schedule) {
            latestSchedule = Array.isArray(schedule) ? schedule : [];
            const container = document.getElementById('scheduleTablesContainer');
            const filtered = latestSchedule.filter(matchMatchesFilter);
            // Byes are bracket slots, not matches: keep the badge in step with the
            // KPI row, which counts playable fixtures only.
            updateScheduleCount(filtered.filter(match => !isByeMatch(match)).length);
            container.innerHTML = '';

            if (latestSchedule.length === 0) {
                container.innerHTML = '<div class="lp-empty"><i class="bi bi-calendar-x"></i>'
                    + '<p>No matches yet. Add one with the build tools below.</p></div>';
                return;
            }

            if (filtered.length === 0) {
                container.innerHTML = '<div class="lp-empty"><i class="bi bi-search"></i>'
                    + '<p>No matches match your search or status filter.</p></div>';
                return;
            }

            const sections = buildScheduleSections(filtered.slice().sort(scheduleBoardOrder));

            sections.forEach(section => {
                const board = document.createElement('div');
                board.className = 'sc-board';

                const headerDiv = document.createElement('div');
                headerDiv.className = 'schedule-section-header';
                const byeCount = section.matches.filter(isByeMatch).length;
                const playableCount = section.matches.length - byeCount;
                const sectionCount = playableCount + ' match' + (playableCount === 1 ? '' : 'es')
                    + (byeCount > 0 ? ' \u00b7 ' + byeCount + ' bye' + (byeCount === 1 ? '' : 's') : '');
                headerDiv.innerHTML = `
                    <i class="${section.icon}" aria-hidden="true"></i>
                    <span>${escapeHtml(section.label)}</span>
                    ${section.badge ? `<span class="badge">${escapeHtml(section.badge)}</span>` : ''}
                    <span class="match-count">${sectionCount}</span>
                `;
                board.appendChild(headerDiv);

                const tableWrapper = document.createElement('div');
                tableWrapper.className = 'table-responsive';
                const table = document.createElement('table');
                table.className = 'table activity-table sc-table align-middle mb-0';
                table.innerHTML = `
                    <thead>
                        <tr>
                            <th style="width:132px;">Schedule</th>
                            <th>Matchup</th>
                            <th style="width:150px;">Venue</th>
                            <th style="width:170px;">Committee</th>
                            <th style="width:190px;">Status</th>
                            <th style="width:90px;">Score</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                `;
                const tbody = table.querySelector('tbody');
                section.matches.forEach(match => {
                    tbody.insertAdjacentHTML('beforeend', buildMatchRow(match));
                });
                tableWrapper.appendChild(table);
                board.appendChild(tableWrapper);
                container.appendChild(board);
            });
        }

        function buildMatchRow(match) {
            const status = String(match.status || '').toLowerCase();
            const isConflict = Number(match.conflict_detected) === 1;
            // Bye rows are auto-completed brackets slots (one team, seed_b = BYE):
            // they are listed for picture completeness, never as playable matches.
            const isBye = isByeMatch(match);
            const isCompleted = !isBye && status === 'completed';
            const isDraft = !match.published_at;
            const statusClass = isDraft ? 'is-neutral'
                : isCompleted ? 'is-completed'
                : status === 'cancelled' ? 'is-cancelled'
                : (status === 'scheduled' || status === 'upcoming' || status === 'postponed') ? 'is-scheduled'
                : 'is-neutral';
            const winner = Number(match.winner || 0);
            const teamAWon = isCompleted && winner > 0 && Number(match.team_a) === winner;
            const teamBWon = isCompleted && winner > 0 && Number(match.team_b) === winner;
            const committeeName = String(match.committee_name || '').trim() || 'Unassigned';
            let scoreCell = isCompleted
                ? `<span class="sc-score">${escapeHtml(match.a_score || 0)}<span class="sc-score-sep">–</span>${escapeHtml(match.b_score || 0)}</span>`
                : '<span class="sc-score is-pending">–</span>';
            if (isBye) scoreCell = '<span class="sc-score is-bye">BYE</span>';
            const rowClass = [
                isBye ? 'is-bye-row' : '',
                isConflict ? 'is-conflict-row' : '',
                isCompleted ? 'is-completed-row' : ''
            ].filter(Boolean).join(' ');

            const rescheduleButton = isCompleted
                ? `<button type="button" class="league-icon-btn league-action-warn" title="Log protest / reopen result" aria-label="Log protest or reopen result" onclick="protestResult(${match.id})"><i class="bi bi-flag" aria-hidden="true"></i></button>`
                : `<button type="button" class="league-icon-btn league-action-warn" title="Reschedule" aria-label="Reschedule" onclick="editMatch(${match.id},'${escapeQuotes(match.date || '')}','${escapeQuotes(match.time || '')}','${escapeQuotes(match.venue || '')}', '${escapeQuotes(match.committee_user_id || '')}')"><i class="bi bi-calendar-event" aria-hidden="true"></i></button>`;
            // Published matches render nothing extra here so the Reschedule and
            // Archive icon buttons sit side by side. Drafts still get Publish.
            const publishButton = match.published_at
                ? ''
                : `<button type="button" class="league-icon-btn league-action-ok" title="Publish" aria-label="Publish" onclick="publishMatch(${match.id})"><i class="bi bi-send-fill" aria-hidden="true"></i></button>`;
            // The conflict payload travels through a data attribute so the JSON
            // reason is never embedded inside an inline onclick handler.
            const conflictChip = isConflict
                ? `<button type="button" class="lp-row-status is-conflict js-conflict" data-reason="${escapeAttr(match.conflict_reason || '')}" title="View conflict details"><i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>Conflict</button>`
                : '';
            // Result-type chip (forfeit / double forfeit / walkover / decision) plus the
            // protest / correction state, both surfaced straight from the match row.
            const winTypeLabel = ({ Forfeit: 'Forfeit', Walkover: 'Walkover', DoubleForfeit: 'Double FF', Decision: 'Decision' })[match.win_type] || '';
            const resultChip = winTypeLabel
                ? `<span class="lp-row-status is-cancelled" title="${escapeAttr(match.result_note || winTypeLabel)}">${escapeHtml(winTypeLabel)}</span>`
                : (match.result_status && match.result_status !== 'Final'
                    ? `<span class="lp-row-status is-pending" title="Result ${escapeAttr(match.result_status)}">${escapeHtml(match.result_status)}</span>`
                    : '');

            const teamA = `<div class="sc-team ${teamAWon ? 'is-winner' : (isCompleted && winner > 0 ? 'is-loser' : '')}">
                    ${match.seed_a ? `<span class="sc-seed">${escapeHtml(match.seed_a)}</span>` : ''}
                    <span class="sc-team-name">${escapeHtml(match.team_a_name || 'TBD')}</span>
                    ${teamAWon ? '<i class="bi bi-check-circle-fill sc-team-win" aria-hidden="true"></i>' : ''}
                </div>`;
            const teamB = isBye
                ? `<div class="sc-team is-bye"><span class="sc-team-name">BYE</span></div>`
                : `<div class="sc-team ${teamBWon ? 'is-winner' : (isCompleted && winner > 0 ? 'is-loser' : '')}">
                    ${match.seed_b ? `<span class="sc-seed">${escapeHtml(match.seed_b)}</span>` : ''}
                    <span class="sc-team-name">${escapeHtml(match.team_b_name || 'TBD')}</span>
                    ${teamBWon ? '<i class="bi bi-check-circle-fill sc-team-win" aria-hidden="true"></i>' : ''}
                </div>`;
            const stageTag = (match.stage_group_name || match.stage_name)
                ? `<span class="sc-stage-tag">${escapeHtml(match.stage_group_name || match.stage_name)}</span>`
                : '';
            const statusChip = isBye
                ? '<span class="lp-row-status is-neutral" title="No opponent - advances automatically">Bye</span>'
                : `<span class="lp-row-status ${statusClass}">${escapeHtml(match.status || 'Draft')}</span>`;
            const actionCell = isBye
                ? `<span class="sc-published" title="No opponent - advances automatically"><i class="bi bi-check2-circle" aria-hidden="true"></i>Auto-advanced</span>
                    <button type="button" class="league-icon-btn league-action-muted" title="Archive" aria-label="Archive" onclick="deleteMatch(${match.id})"><i class="bi bi-archive" aria-hidden="true"></i></button>`
                : `${rescheduleButton}${publishButton}
                    <button type="button" class="league-icon-btn league-action-muted" title="Archive" aria-label="Archive" onclick="deleteMatch(${match.id})"><i class="bi bi-archive" aria-hidden="true"></i></button>`;

            return `<tr class="${rowClass}">
                <td>
                    <span class="sc-when">
                        <span class="sc-when-date">${escapeHtml(formatScheduleDate(match.date))}</span>
                        <span class="sc-when-time"><i class="bi bi-clock" aria-hidden="true"></i>${escapeHtml(match.time || '—')}</span>
                    </span>
                </td>
                <td class="sc-matchup">${teamA}${teamB}${stageTag}</td>
                <td class="sc-cell-muted"><i class="bi bi-geo-alt" aria-hidden="true"></i>${escapeHtml(match.venue || '—')}</td>
                <td class="sc-cell-muted"><i class="bi bi-person-badge" aria-hidden="true"></i>${escapeHtml(committeeName)}</td>
                <td><div class="d-flex align-items-center gap-2 flex-wrap">${conflictChip}${resultChip}${statusChip}</div></td>
                <td>${scoreCell}</td>
                <td><div class="sc-actions">${actionCell}</div></td>
            </tr>`;
        }

        /**
         * Keep the organizer board in bracket order instead of date order.
         *
         * Editing a draft used to give it a new date and move it to the bottom
         * of its round. Stage -> round -> schedule id is the order the bracket
         * generator wrote, so table rows and bracket columns now agree and an
         * edit never changes a match's position.
         */
        function scheduleBoardOrder(a, b) {
            const aStageOrder = Number(a.stage_order != null && a.stage_order !== '' ? a.stage_order : (a.stage_id || 0));
            const bStageOrder = Number(b.stage_order != null && b.stage_order !== '' ? b.stage_order : (b.stage_id || 0));
            if (aStageOrder !== bStageOrder) return aStageOrder - bStageOrder;

            const aStageId = Number(a.stage_id || 0);
            const bStageId = Number(b.stage_id || 0);
            if (aStageId !== bStageId) return aStageId - bStageId;

            const aRound = Number(a.round || 1);
            const bRound = Number(b.round || 1);
            if (aRound !== bRound) return aRound - bRound;

            return Number(a.id || 0) - Number(b.id || 0);
        }

        function buildScheduleSections(schedule) {
            const sections = [];

            // Stage-based leagues: one section per stage + round.
            const staged = schedule.filter(m => m.stage_name || m.stage_id);
            if (staged.length > 0) {
                const byStage = {};
                staged.forEach(m => {
                    const key = m.stage_name || ('Stage ' + (m.stage_id || ''));
                    if (!byStage[key]) byStage[key] = [];
                    byStage[key].push(m);
                });
                Object.keys(byStage).forEach(stageName => {
                    const byRound = {};
                    byStage[stageName].forEach(m => {
                        const r = m.round || 1;
                        if (!byRound[r]) byRound[r] = [];
                        byRound[r].push(m);
                    });
                    Object.keys(byRound).map(Number).sort((a, b) => a - b).forEach(r => {
                        sections.push({
                            label: stageName + ' — Round ' + r,
                            icon: 'bi bi-diagram-3-fill',
                            badge: null,
                            matches: byRound[r]
                        });
                    });
                });

                const untagged = schedule.filter(m => !m.stage_name && !m.stage_id);
                if (untagged.length > 0) {
                    sections.push({
                        label: 'Other Matches',
                        icon: 'bi bi-calendar-event',
                        badge: null,
                        matches: untagged
                    });
                }
                return sections;
            }

            // No stages (Custom round robin): one board.
            sections.push({
                label: 'All Matches',
                icon: 'bi bi-calendar-event',
                badge: 'Round Robin',
                matches: schedule
            });
            return sections;
        }

        // ========== BRACKET VIEW ==========
        // Draws the selected knockout stage as one column per round. A later-round
        // card spans the rows of its two feeders, so byes and TBD slots stay aligned
        // with the bracket order the generator (and progression) wrote.
        let bracketStageChoice = 0;

        function knockoutStagesFromSchedule(schedule) {
            const stages = {};
            schedule.forEach(function (match) {
                // A bracket stage is a knockout stage, or any stage whose format
                // is single / double elimination (the structure engine writes it).
                const isBracket = String(match.stage_type || '') === 'knockout'
                    || ['single_elimination', 'double_elimination'].indexOf(String(match.stage_format || '')) !== -1;
                if (!isBracket) return;
                const id = Number(match.stage_id || 0);
                if (!id) return;
                if (!stages[id]) {
                    stages[id] = {
                        id: id,
                        name: match.stage_name || ('Stage ' + id),
                        format: match.stage_format || 'single_elimination',
                        matches: 0
                    };
                }
                stages[id].matches += 1;
            });
            return Object.keys(stages).map(function (key) { return stages[key]; })
                .sort(function (a, b) { return a.id - b.id; });
        }

        function bracketRoundLabel(roundCount, roundNumber) {
            const fromEnd = roundCount - roundNumber + 1;
            if (fromEnd === 1) return 'Final';
            if (fromEnd === 2) return 'Semifinal';
            if (fromEnd === 3) return 'Quarterfinal';
            if (fromEnd === 4) return 'Round of 16';
            return 'Round of ' + Math.pow(2, fromEnd);
        }

        function bracketSide(match, side) {
            const isMatchBye = isByeMatch(match);
            const bye = side === 'b' && isMatchBye;
            const seed = side === 'a' ? match.seed_a : match.seed_b;
            const name = side === 'a' ? match.team_a_name : match.team_b_name;
            const teamId = Number(side === 'a' ? match.team_a : match.team_b);
            const winner = Number(match.winner || 0);
            const completed = String(match.status || '').toLowerCase() === 'completed';
            const isWinner = !isMatchBye && completed && winner > 0 && teamId === winner;
            // The losing side of a completed fixture is tinted red.
            const isLoser = !isMatchBye && completed && winner > 0 && teamId > 0 && teamId !== winner;
            // A bye team is already through, so tint it like a winner.
            const advanced = isMatchBye && side === 'a';
            const score = completed && !isMatchBye ? (side === 'a' ? match.a_score : match.b_score) : '';
            const seedText = seed === null || seed === undefined ? '' : String(seed);
            const showSeed = !bye && seedText !== '';
            return `<div class="sc-bracket-side${isWinner || advanced ? ' is-winner' : ''}${isLoser ? ' is-loser' : ''}${bye ? ' is-bye' : ''}">
                ${showSeed ? `<span class="sc-bracket-seed">${escapeHtml(seedText)}</span>` : ''}
                <span class="sc-bracket-name">${escapeHtml(name || (bye ? 'BYE' : 'TBD'))}</span>
                <span class="sc-bracket-score">${score === '' ? '' : escapeHtml(score || 0)}</span>
                ${isWinner ? '<i class="bi bi-check-circle-fill sc-bracket-check" aria-hidden="true"></i>' : ''}
            </div>`;
        }

        function bracketCard(match, roundNumber, roundCount) {
            const isMatchBye = isByeMatch(match);
            const status = String(match.status || '').toLowerCase();
            const isTbd = !isMatchBye && Number(match.team_a) === 0 && Number(match.team_b) === 0;
            const chips = [];
            if (isMatchBye) chips.push('<span class="sc-bracket-chip is-bye">Bye</span>');
            else if (isTbd) chips.push('<span class="sc-bracket-chip is-tbd">TBD</span>');
            else if (status === 'completed') chips.push('<span class="sc-bracket-chip is-complete">Final</span>');
            else if (status === 'scheduled' || status === 'upcoming') chips.push('<span class="sc-bracket-chip is-scheduled">Scheduled</span>');
            else if (status === 'cancelled') chips.push('<span class="sc-bracket-chip is-cancelled">Cancelled</span>');
            else chips.push('<span class="sc-bracket-chip is-draft">Draft</span>');
            if (!isMatchBye && match.date) {
                chips.push('<span class="sc-bracket-date">' + escapeHtml(formatScheduleDate(match.date)) + '</span>');
            }
            const title = isMatchBye
                ? 'Bye - the team advances automatically'
                : (match.stage_name || 'Knockout') + ' - ' + bracketRoundLabel(roundCount, roundNumber);
            return `<div class="sc-bracket-match${isMatchBye ? ' is-bye' : ''}${isTbd ? ' is-tbd' : ''}" title="${escapeAttr(title)}">
                ${bracketSide(match, 'a')}
                ${bracketSide(match, 'b')}
                <div class="sc-bracket-foot">${chips.join('')}</div>
            </div>`;
        }

        function renderBracketRounds(matches, sideTitle) {
            const byRound = {};
            matches.forEach(function (match) {
                const round = Number(match.round || 1);
                if (!byRound[round]) byRound[round] = [];
                byRound[round].push(match);
            });
            const roundNumbers = Object.keys(byRound).map(Number).sort(function (a, b) { return a - b; });
            if (!roundNumbers.length) {
                return '<div class="lp-empty"><i class="bi bi-diagram-3"></i>'
                    + '<p>This stage has no fixtures yet. Generate it in <strong>Setup &rarr; Matches</strong>, then refresh.</p></div>';
            }
            roundNumbers.forEach(function (round) {
                byRound[round].sort(function (a, b) { return Number(a.id) - Number(b.id); });
            });
            const rounds = roundNumbers.map(function (round) { return byRound[round]; });
            const baseRows = rounds[0].length;

            // A classic bracket halves every round. A custom multi-pool playoff can
            // skip a size; draw simple columns instead of misaligned connectors.
            const canSpan = rounds.every(function (list) {
                return list.length > 0 && baseRows % list.length === 0;
            });
            if (!canSpan) {
                return '<div class="sc-bracket-columns">' + rounds.map(function (list, index) {
                    return '<div class="sc-bracket-column">'
                        + '<div class="sc-bracket-column-head">' + bracketRoundLabel(rounds.length, index + 1) + '</div>'
                        + list.map(function (match) { return bracketCard(match, index + 1, rounds.length); }).join('')
                        + '</div>';
                }).join('') + '</div>';
            }

            let html = '<div class="sc-bracket-scroll"><table class="sc-bracket"><thead><tr>';
            rounds.forEach(function (list, index) {
                html += '<th class="sc-bracket-head' + (index === rounds.length - 1 ? ' is-final' : '') + '">'
                    + bracketRoundLabel(rounds.length, index + 1) + '</th>';
            });
            html += '</tr></thead><tbody>';
            for (let row = 0; row < baseRows; row++) {
                html += '<tr>';
                rounds.forEach(function (list, index) {
                    const span = baseRows / list.length;
                    if (row % span !== 0) return;
                    const match = list[row / span];
                    if (!match) return;
                    const feeds = index > 0 && rounds[index - 1].length === list.length * 2;
                    const className = 'sc-bracket-cell' + (feeds ? ' has-feeders' : '')
                        + (index === rounds.length - 1 ? ' is-final' : '');
                    html += '<td rowspan="' + span + '" class="' + className + '">'
                        + bracketCard(match, index + 1, rounds.length) + '</td>';
                });
                html += '</tr>';
            }
            html += '</tbody></table></div>';
            return html;
        }

        /**
         * Bracket view: a double-elimination stage is drawn as two distinct
         * sections (winners above, losers below) plus the grand final, exactly
         * like the traditional side-to-side branch diagram. A single-elimination
         * stage or a legacy bracket falls back to one round-by-round table.
         */
        function renderBracketStage(matches) {
            const hasSides = matches.some(function (match) {
                return match.bracket_side && String(match.bracket_side) !== '';
            });
            if (!hasSides) return renderBracketRounds(matches, '');

            const sections = [
                { key: 'winners',     label: 'Winners bracket', icon: 'bi-arrow-up-right-circle' },
                { key: 'losers',      label: 'Losers bracket',  icon: 'bi-arrow-down-right-circle' },
                { key: 'grand_final', label: 'Grand final',     icon: 'bi-trophy-fill' }
            ];

            let html = '';
            sections.forEach(function (section) {
                const subset = matches.filter(function (match) {
                    const side = String(match.bracket_side || 'winners');
                    return side === section.key;
                });
                if (!subset.length) return;
                html += '<div class="sc-bracket-section is-' + section.key + '">'
                    + '<div class="sc-bracket-section-head"><i class="bi ' + section.icon + '" aria-hidden="true"></i> '
                    + section.label
                    + '<span>' + subset.length + ' fixture' + (subset.length === 1 ? '' : 's') + '</span></div>'
                    + renderBracketRounds(subset, section.label)
                    + '</div>';
            });
            return html;
        }

        function initScheduleBracket() {
            const container = document.getElementById('scheduleBracket');
            const stageSelect = document.getElementById('scBracketStage');
            if (!container || !stageSelect) return;

            const stages = knockoutStagesFromSchedule(latestSchedule);
            if (!stages.length) {
                stageSelect.innerHTML = '';
                stageSelect.disabled = true;
                if (!latestSchedule.length) {
                    // The first AJAX load has not returned yet.
                    container.innerHTML = '<div class="lp-empty"><span class="spinner-border text-primary" role="status" aria-hidden="true"></span>'
                        + '<p class="mt-3">Loading bracket…</p></div>';
                    return;
                }
                container.innerHTML = '<div class="lp-empty"><i class="bi bi-diagram-3"></i>'
                    + '<p>A bracket needs a knockout stage. Apply <strong>Single elimination</strong>, '
                    + '<strong>Double elimination</strong>, <strong>Round robin + playoffs</strong> or '
                    + '<strong>Groups &rarr; knockout</strong> in Setup, then generate it from the Structure page.</p></div>';
                return;
            }

            stageSelect.disabled = false;
            if (!stages.some(function (stage) { return stage.id === bracketStageChoice; })) {
                bracketStageChoice = stages[0].id;
            }
            stageSelect.innerHTML = stages.map(function (stage) {
                return '<option value="' + stage.id + '"' + (stage.id === bracketStageChoice ? ' selected' : '') + '>'
                    + escapeHtml(stage.name) + ' (' + stage.matches + ' fixture' + (stage.matches === 1 ? '' : 's') + ')</option>';
            }).join('');

            const matches = latestSchedule.filter(function (match) {
                return Number(match.stage_id) === bracketStageChoice;
            });
            container.innerHTML = renderBracketStage(matches);
        }

        const bracketStageSelect = document.getElementById('scBracketStage');
        if (bracketStageSelect) {
            bracketStageSelect.addEventListener('change', function () {
                bracketStageChoice = Number(this.value || 0);
                initScheduleBracket();
            });
        }

        function publishMatch(scheduleId) {
            Swal.fire({title:'Publish this match?',text:'Teams and the assigned Committee will be notified.',icon:'question',showCancelButton:true,confirmButtonText:'Publish'}).then(result=>{
                if(!result.isConfirmed) return;
                $.post('action/schedulingAction.php',{publishMatch:true,schedule_id:scheduleId,csrf_token:csrfToken},function(res){
                    if(typeof res==='string') res=JSON.parse(res);
                    if(res.status===201 || res.status===200) Swal.fire('Published',res.msg,'success').then(loadSchedule);
                    else Swal.fire('Cannot publish',res.msg || 'Please complete the match details.','error');
                },'json');
            });
        }

        function showConflictDetails(conflictReason) {
            let inner = '';
            try {
                const conflicts = JSON.parse(conflictReason);
                conflicts.forEach(conflict => {
                    const isTeam = conflict.type === 'team_conflict';
                    inner += '<div class="d-flex align-items-start gap-3 mb-3">'
                        + '<span class="lp-row-status ' + (isTeam ? 'is-pending' : 'is-neutral') + '">' + (isTeam ? 'Team' : 'Venue') + '</span>'
                        + '<div><div>' + escapeHtml(conflict.message) + '</div>'
                        + '<small class="text-muted">Existing match: ' + escapeHtml(conflict.existing_match) + '</small></div>'
                        + '</div>';
                });
            } catch (e) {
                // Legacy rows may store a plain sentence instead of JSON.
                inner = '<div>' + escapeHtml(conflictReason || 'No details available.') + '</div>';
            }
            document.getElementById('conflictDetails').innerHTML = '<div class="lp-panel mb-0">' + inner + '</div>';
            showScheduleModal('conflictModal');
        }

        // Auto-schedule form (only rendered for leagues without stages)
        const autoScheduleForm = document.getElementById('autoScheduleForm');
        if (autoScheduleForm) autoScheduleForm.addEventListener('submit', function(e) {
            e.preventDefault();
            console.log('Auto-schedule form submitted');
            
            const formData = new FormData(this);
            formData.append('autoSchedule', true);
            formData.append('league_id', leagueId);
            formData.append('csrf_token', csrfToken);

            // Debug: Log what we're sending
            console.log('Sending data:', {
                autoSchedule: true,
                league_id: leagueId,
                start_date: formData.get('start_date'),
                venue: formData.get('venue')
            });

            $.ajax({
                url: 'action/schedulingAction.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    console.log('Response:', res);
                    if (res.status === 201) {
                        Swal.fire('Success', `Generated ${res.saved} matches (${res.conflicts} with conflicts)`, 'success');
                        loadSchedule();
                        document.getElementById('autoScheduleForm').reset();
                    } else {
                        Swal.fire('Error', res.msg, 'error');
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX Error:', {xhr, status, error});
                    console.error('Response Text:', xhr.responseText);
                    Swal.fire('Error', 'Failed to generate schedule. Check console for details.', 'error');
                }
            });
        });

        // Manual match form
        document.getElementById('manualMatchForm').addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            formData.append('createMatch', true);
            formData.append('league_id', leagueId);
            formData.append('csrf_token', csrfToken);

            $.ajax({
                url: 'action/schedulingAction.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.status === 201) {
                        if (res.has_conflict) {
                            Swal.fire({
                                icon: 'warning',
                                title: 'Match Created with Conflicts',
                                text: 'This match has scheduling conflicts and is pending review',
                                confirmButtonText: 'OK'
                            });
                        } else {
                            Swal.fire('Draft saved', res.msg, 'success');
                        }
                        loadSchedule();
                        document.getElementById('manualMatchForm').reset();
                    } else {
                        Swal.fire('Error', res.msg, 'error');
                    }
                }
            });
        });

        function editMatch(scheduleId, date, time, venue, committeeUserId) {
            document.getElementById('editScheduleId').value = scheduleId;
            document.getElementById('editDate').value = date;
            document.getElementById('editTime').value = time;
            document.getElementById('editVenue').value = venue;
            document.getElementById('editCommitteeUserId').value = committeeUserId;
            showScheduleModal('editMatchModal');
        }

        function saveEditMatch() {
            const formData = new FormData(document.getElementById('editMatchForm'));
            formData.append('updateMatch', true);
            formData.append('csrf_token', csrfToken);

            $.ajax({
                url: 'action/schedulingAction.php',
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                dataType: 'json',
                success: function(res) {
                    if (res.status === 200) {
                        Swal.fire('Success', res.msg, 'success');
                        hideScheduleModal('editMatchModal');
                        loadSchedule();
                    } else {
                        Swal.fire('Error', res.msg, 'error');
                    }
                }
            });
        }

        function deleteMatch(scheduleId) {
            Swal.fire({
                title: 'Delete Match?',
                text: 'This action cannot be undone',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Delete'
            }).then(result => {
                if (result.isConfirmed) {
                    $.ajax({
                        url: 'action/schedulingAction.php',
                        type: 'POST',
                        data: { deleteMatch: true, schedule_id: scheduleId, csrf_token: csrfToken },
                        dataType: 'json',
                        success: function(res) {
                            if (res.status === 200) {
                                Swal.fire('Success', res.msg, 'success');
                                loadSchedule();
                            } else {
                                Swal.fire('Error', res.msg, 'error');
                            }
                        }
                    });
                }
            });
        }

        function escapeHtml(text) {
            if (text === null || text === undefined) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            };
            return String(text).replace(/[&<>"']/g, m => map[m]);
        }

        // Attribute-safe helper for data-* payloads (conflict reasons etc.).
        function escapeAttr(text) {
            return escapeHtml(text);
        }

        function escapeQuotes(text) {
            return String(text === null || text === undefined ? '' : text).replace(/'/g, "\\'").replace(/"/g, '\\"');
        }

        // ========== TOURNAMENT PROGRESSION ==========
        function loadProgressionSummary() {
            $.ajax({
                url: 'action/schedulingAction.php',
                type: 'POST',
                data: { getProgressionSummary: true, league_id: leagueId, csrf_token: csrfToken },
                dataType: 'json',
                success: function(res) {
                    if (res.status === 200 && res.summary) {
                        displayProgressionSummary(res.summary);
                    }
                }
            });
        }


        // ---- Protest / reopen a completed result (ANP2026) ------------------
        function protestResult(scheduleId) {
            Swal.fire({
                title: 'Log protest / reopen result',
                html: '<div class="text-start small mb-2">A formal written protest must reach the Tournament Organizer within <strong>' + protestWindowText + '</strong> of the result. Upholding it restores the affected teams and removes bracket rows created from this result, so the Committee can submit a corrected score.</div>'
                    + '<textarea id="protestReason" class="form-control" rows="3" placeholder="Reason / protest details" maxlength="1000"></textarea>'
                    + '<select id="protestDecision" class="form-select mt-2">'
                    + '<option value="Upheld">Uphold — reopen the result for correction</option>'
                    + '<option value="Denied">Deny — the official result stands</option>'
                    + '<option value="Open">Log only — decide later</option></select>'
                    + '<select id="protestParty" class="form-select mt-2">'
                    + '<option value="team">Filed by a team (24-hour rule)</option>'
                    + '<option value="committee">Filed by the committee</option>'
                    + '<option value="organizer">Organizer decision</option></select>',
                width: 620,
                showCancelButton: true,
                confirmButtonText: 'Save',
                focusConfirm: false,
                preConfirm: function () {
                    const reason = document.getElementById('protestReason').value.trim();
                    if (!reason) { Swal.showValidationMessage('A reason is required.'); return false; }
                    return {
                        reason: reason,
                        decision: document.getElementById('protestDecision').value,
                        party: document.getElementById('protestParty').value
                    };
                }
            }).then(function (r) {
                if (!r.isConfirmed) return;
                $.ajax({
                    url: 'action/schedulingAction.php', type: 'POST',
                    data: $.extend({ protestResult: true, schedule_id: scheduleId, league_id: leagueId, csrf_token: csrfToken }, r.value),
                    dataType: 'json',
                    success: function (res) {
                        if (res.status !== 201) { Swal.fire('Error', res.msg || 'Failed.', 'error'); return; }
                        Swal.fire('Saved', res.msg, 'success').then(function () { loadSchedule(); loadProgressionSummary(); });
                    },
                    error: function (xhr) {
                        Swal.fire('Error', (xhr.responseJSON && xhr.responseJSON.msg) || 'Request failed.', 'error');
                    }
                });
            });
        }

        function displayProgressionSummary(s) {
            const wrapper = document.getElementById('progressionPanelWrapper');
            const content = document.getElementById('progressionContent');

            // Only stage-based leagues have progression to report; a plain round
            // robin is just a standings table.
            if (!s.stages || s.stages.length === 0) {
                wrapper.style.display = 'none';
                return;
            }
            wrapper.style.display = '';

            let html = '<div class="d-flex flex-wrap gap-2 mb-3">';
            html += '<span class="sc-chip is-done">' + (s.completed_matches || 0) + ' / ' + (s.total_matches || 0) + ' matches completed</span>';
            html += '</div>';

            if (s.tournament_complete && s.champion) {
                html += '<div class="sc-champion"><i class="bi bi-trophy-fill" aria-hidden="true"></i><div>';
                html += '<div class="stat-label" style="margin:0;">Tournament complete</div>';
                html += '<strong style="font-size:1.05rem;color:var(--lp-ink);">' + escapeHtml(s.champion.team_name) + '</strong>';
                html += '</div></div>';
                content.innerHTML = html;
                return;
            }

            html += '<div class="mb-2"><span class="stat-label" style="margin:0;">Stage status</span></div>';
            html += '<div class="sc-progress-cards mb-3">';
            s.stages.forEach(function(stage) {
                const statusChip = stage.complete
                    ? '<span class="sc-chip is-done">Complete</span>'
                    : '<span class="sc-chip is-active">In progress</span>';
                html += '<div class="sc-progress-card"><strong>' + escapeHtml(stage.name) + '</strong>' + statusChip + '</div>';
            });
            html += '</div>';

            html += '<div class="text-muted mt-2"><em>Progression is automatic — results submitted by the Committee advance the teams.</em></div>';
            content.innerHTML = html;
        }

    </script>
</body>
</html>
<?php $conn->close(); ?>
