<?php
require_once __DIR__.'/../../config/dbconnect.php';
require_once __DIR__.'/../../config/auth_guard.php';
require_once __DIR__.'/../../includes/storageService.php';
require_once __DIR__.'/../../includes/stageHelper.php';
require_once __DIR__.'/../../includes/schedulingHelper.php';
if(session_status()!==PHP_SESSION_ACTIVE) session_start();
requireRole([1]); requireActiveAccount($conn);
$userId=currentUserId(); $id=(int)($_GET['id'] ?? $_POST['league_id'] ?? 0);
if(!$id || !organizerOwnsLeague($conn,$id,$userId)){
    $_SESSION['league_flash'] = ['danger', 'That league belongs to another organizer.'];
    header('Location: league.php'); exit;
}

$leagueStmt=$conn->prepare("SELECT * FROM tbl_league WHERE id=? AND created_by=?");
$leagueStmt->bind_param('ii',$id,$userId); $leagueStmt->execute(); $league=$leagueStmt->get_result()->fetch_assoc(); $leagueStmt->close();

if($_SERVER['REQUEST_METHOD']==='POST'){
    $token=(string)($_POST['csrf_token'] ?? '');
    if(!$token || !hash_equals(csrfToken(),$token)){ $_SESSION['standing_flash']=['danger','Form session expired.']; header("Location: standing.php?id=$id"); exit; }
    try {
        if(isset($_POST['assignGroup'])){
            $teamId=(int)$_POST['team_id']; $groupId=(int)($_POST['group_id'] ?? 0);
            $teamStmt=$conn->prepare("SELECT 1 FROM tbl_team WHERE id=? AND league_id=? AND LOWER(status)='approved'"); $teamStmt->bind_param('ii',$teamId,$id); $teamStmt->execute(); $validTeam=(bool)$teamStmt->get_result()->fetch_row(); $teamStmt->close();
            if(!$validTeam) throw new RuntimeException('Approved team not found in this league.');
            if($groupId===0){ $stmt=$conn->prepare("DELETE FROM tbl_league_group_team WHERE team_id=?"); $stmt->bind_param('i',$teamId); $stmt->execute(); $stmt->close(); }
            else {
                $groupStmt=$conn->prepare("SELECT 1 FROM tbl_league_group WHERE id=? AND league_id=?"); $groupStmt->bind_param('ii',$groupId,$id); $groupStmt->execute(); $validGroup=(bool)$groupStmt->get_result()->fetch_row(); $groupStmt->close();
                if(!$validGroup) throw new RuntimeException('Group not found in this league.');
                $stmt=$conn->prepare("INSERT INTO tbl_league_group_team(group_id,team_id) VALUES (?,?) ON DUPLICATE KEY UPDATE group_id=VALUES(group_id),assigned_at=NOW()"); $stmt->bind_param('ii',$groupId,$teamId); $stmt->execute(); $stmt->close();
            }
            auditLog($conn,'Assign Team Group',"Assigned team ID $teamId to group ID $groupId in league ID $id"); $_SESSION['standing_flash']=['success','Team grouping updated.'];
        } elseif(isset($_POST['setProgression'])){
            $teamId=(int)$_POST['team_id']; $decision=$_POST['decision'] ?? '';
            if(!in_array($decision,['Undecided','Qualified','Eliminated'],true)) throw new RuntimeException('Invalid progression decision.');
            $stmt=$conn->prepare("UPDATE tbl_team SET progression_status=?,progression_decided_by=?,progression_decided_at=IF(?='Undecided',NULL,NOW()) WHERE id=? AND league_id=? AND LOWER(status)='approved'");
            $stmt->bind_param('sisii',$decision,$userId,$decision,$teamId,$id); $stmt->execute();
            if($stmt->affected_rows<1 && $stmt->errno) throw new RuntimeException('Unable to update progression.'); $stmt->close();
            auditLog($conn,'Set Team Progression',"Marked team ID $teamId as $decision in league ID $id"); $_SESSION['standing_flash']=['success',"Team marked $decision."];
        }
    } catch(Throwable $e){ $_SESSION['standing_flash']=['danger',$e->getCode()===1062?'That group already exists.':$e->getMessage()]; }
    header("Location: standing.php?id=$id"); exit;
}

$groups=[]; $groupStmt=$conn->prepare("SELECT id,name,display_order FROM tbl_league_group WHERE league_id=? ORDER BY display_order,name"); $groupStmt->bind_param('i',$id); $groupStmt->execute(); $groupRes=$groupStmt->get_result(); while($g=$groupRes->fetch_assoc())$groups[]=$g; $groupStmt->close();
$groupingEnabled=(int)$league['grouping_enabled'];
/* Standings now come from the single canonical provider (config/scoringRules.php +
   includes/standingsProvider.php) so the configured points and tiebreaks apply
   everywhere — including set-based sports such as Volleyball. */
/* Grouped and ungrouped rankings now come from one shared helper so the
   organizer page and the report module cannot drift apart. */
$rankings=getLeagueStandingsWithGroups($conn,$id);
/* ------------------------------------------------------------------
   Standings analytics — feeds the KPI row so the table, the badges and
   the hero banner all read from the same numbers. Prepared statements only.
   ------------------------------------------------------------------ */
$groupingOn = (int)$league['grouping_enabled'] === 1;
$groupCount = count($groups);
$totalTeams = count($rankings);
/* KPI counts come straight from tbl_schedule so untagged group matches or matches
   involving non-approved teams can never skew the numbers. Only published matches
   count — drafts are excluded from both the numerator and the denominator. */
$completedMatches = 0; $totalMatches = 0;
$matchCountStmt = $conn->prepare("SELECT COUNT(*) AS total,
    COALESCE(SUM(CASE WHEN LOWER(status)='completed' THEN 1 ELSE 0 END),0) AS completed
    FROM tbl_schedule WHERE league_id=? AND published_at IS NOT NULL AND (is_archived=0 OR is_archived IS NULL)");
if($matchCountStmt){
    $matchCountStmt->bind_param('i',$id); $matchCountStmt->execute();
    $matchCounts = $matchCountStmt->get_result()->fetch_assoc() ?: [];
    $completedMatches = (int)($matchCounts['completed'] ?? 0);
    $totalMatches = (int)($matchCounts['total'] ?? 0);
    $matchCountStmt->close();
}
$playedPct = $totalMatches > 0 ? min(100,(int)round($completedMatches/$totalMatches*100)) : 0;
$qualifiedCount = 0; $eliminatedCount = 0;
foreach($rankings as $r){
    if($r['progression_status'] === 'Qualified') $qualifiedCount++;
    elseif($r['progression_status'] === 'Eliminated') $eliminatedCount++;
}
$undecidedCount = max(0, $totalTeams - $qualifiedCount - $eliminatedCount);
$maxTeams = max(1,(int)$league['max_team']);
$slotPct = min(100,(int)round($totalTeams/$maxTeams*100));


$flash=$_SESSION['standing_flash'] ?? null; unset($_SESSION['standing_flash']); $csrf=csrfToken();
include 'includes/header.php';
?>
<body id="page-top" class="body"><?php include 'includes/bar.php'; ?>
<div class="container-fluid lp-page" style="padding-top:20px;min-height:800px;"><div class="row p-3"><div class="card cards" style="background:transparent;box-shadow:none;border:none;"><div class="card-body p-0">
<?php include 'includes/dashboard-styles.php'; include 'includes/league-view-styles.php'; ?>
<nav class="lp-crumbs dash-animate" aria-label="Breadcrumb">
    <a href="league.php"><i class="bi bi-arrow-left"></i> League Management</a>
    <i class="bi bi-chevron-right lp-crumb-sep" aria-hidden="true"></i>
    <span class="lp-crumb-current"><?= htmlspecialchars((string) $league['league_name']) ?></span>
</nav>
<?php $leagueHeroData = $league; $leagueHeroApprovedCount = count($rankings); include 'includes/league-hero.php'; ?>
<div class="lp-tabs dash-animate dash-animate-d1">
<?php $leagueTabLeagueId=$id; include 'includes/league_tabs.php'; ?>
</div>
<style>
    /* ==========================================================
       Standings — page-specific additions only.
       Shared hero / breadcrumb / pills / buttons / tabs / panels /
       modals live in includes/league-view-styles.php
       ========================================================== */

    /* ---------- Flash notice ----------------------------------- */
    .lp-flash { display:flex; align-items:flex-start; gap:11px; margin-bottom:20px; padding:13px 18px; border:1px solid var(--lp-border); border-radius:10px; background:#fff; font-size:13px; font-weight:500; }
    .lp-flash i { margin-top:1px; font-size:15px; }
    .lp-flash span { line-height:1.5; }
    .lp-flash-success { border-color:#a7f3d0; background:#ecfdf5; color:#065f46; }
    .lp-flash-danger { border-color:#fecaca; background:#fef2f2; color:#991b1b; }
    .lp-flash-info { border-color:#bfdbfe; background:#eff6ff; color:#1e40af; }

    /* ---------- Shared section body rhythm ---------------------- */
    /* The shared .activity-section ships without a bottom margin (the other
       pages wrap it in a .col-*-mb-4), so spacing is restored here. */
    .lp-page .activity-section { margin-bottom:20px; }
    .lp-page .activity-section:last-child { margin-bottom:0; }
    .lp-section-body { padding:20px 24px 24px; }
    .lp-divider { margin:20px 0; border:0; border-top:1px solid #eef2f7; opacity:1; }
    .lp-section-body .lp-divider { margin:20px 0; border-color:#f1f5f9; opacity:1; }
    .lp-section-body .btn-primary { padding:8px 16px; border-radius:8px; font-size:12.5px; font-weight:600; background:#123f70; border-color:#123f70; }
    .lp-section-body .btn-primary:hover, .lp-section-body .btn-primary:focus { background:#0d3160; border-color:#0d3160; }
    .lp-search { position:relative; }
    .lp-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:13px; color:#9ca3af; }
    .lp-search input { min-width:220px; padding:7px 14px 7px 32px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12.5px; transition:border-color .2s ease, box-shadow .2s ease; }
    .lp-search input:focus { outline:none; border-color:var(--lp-blue); background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.1); }

    /* ---------- Group setup ------------------------------------- */
    .lp-setup { display:flex; align-items:center; gap:16px; flex-wrap:wrap; }
    .lp-setup-icon { width:46px; height:46px; flex:0 0 auto; display:flex; align-items:center; justify-content:center; border-radius:12px; background:#e8edf7; color:#09173c; font-size:1.25rem; }
    .lp-setup-text { flex:1 1 280px; min-width:0; }
    .lp-setup-text strong { display:block; font-size:14px; color:var(--lp-ink); }
    .lp-setup-text small { font-size:12.5px; line-height:1.5; color:var(--lp-muted); }
    .lp-switch { display:inline-flex; align-items:center; margin:0; padding:0; cursor:pointer; user-select:none; }
    /* Bootstrap 4 (sb-admin-2) and Bootstrap 5 both ship .form-check rules and
       they conflict — BS4 forces the input to position:absolute — so this
       switch is fully self-contained instead of using .form-check-input. */
    .lp-switch input { position:absolute; width:1px; height:1px; margin:0; padding:0; opacity:0; overflow:hidden; clip:rect(0 0 0 0); pointer-events:none; }
    .lp-switch-track { position:relative; display:block; flex:0 0 auto; width:52px; height:28px; border-radius:999px; background:#d1d5db; box-shadow:inset 0 0 0 1px rgba(9,23,60,.06); transition:background .2s ease; }
    .lp-switch-track::after { content:''; position:absolute; top:3px; left:3px; width:22px; height:22px; border-radius:50%; background:#fff; box-shadow:0 1px 3px rgba(9,23,60,.28); transition:transform .2s ease; }
    .lp-switch:hover .lp-switch-track { filter:brightness(.97); }
    .lp-switch input:checked + .lp-switch-track { background:var(--lp-blue); }
    .lp-switch input:checked + .lp-switch-track::after { transform:translateX(24px); }
    .lp-switch input:focus-visible + .lp-switch-track { box-shadow:0 0 0 3px rgba(37,99,235,.25); }
    .lp-prog-actions { display:flex; align-items:center; flex-wrap:wrap; gap:8px; }
    .lp-group-list { display:flex; flex-wrap:wrap; gap:10px; }
    .lp-group-chip { display:inline-flex; align-items:center; gap:9px; padding:6px 8px 6px 13px; border:1.5px solid var(--lp-border); border-radius:999px; background:#fff; font-size:12.5px; font-weight:600; color:var(--lp-ink); }
    .lp-group-order { display:inline-flex; align-items:center; justify-content:center; width:20px; height:20px; border-radius:50%; background:#e8edf7; color:#09173c; font-size:10.5px; font-weight:700; }
    .lp-group-chip form { display:flex; margin:0; }
    .lp-group-remove { display:inline-flex; align-items:center; justify-content:center; width:22px; height:22px; padding:0; border:none; border-radius:50%; background:#f3f4f6; color:#6b7280; font-size:11px; line-height:1; transition:background .15s ease, color .15s ease; }
    .lp-group-remove:hover { background:#fee2e2; color:#b91c1c; }
    .lp-groups-empty { display:flex; align-items:center; gap:8px; font-size:12.5px; color:var(--lp-muted); }

    /* ---------- Progression manager ------------------------------ */
    .lp-prog-controls { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .lp-prog-controls .lp-inline-label { margin:0; font-size:11.5px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; color:var(--lp-muted); }
    .lp-prog-steps { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:12px; margin-bottom:18px; }
    .lp-prog-step { display:flex; align-items:flex-start; gap:11px; padding:13px 15px; border:1px solid #eef2f7; border-radius:10px; background:#f8fafc; }
    .lp-step-num { display:inline-flex; align-items:center; justify-content:center; width:24px; height:24px; flex:0 0 auto; border-radius:50%; background:#09173c; color:#fff; font-size:11.5px; font-weight:700; }
    .lp-step-text { font-size:12.5px; line-height:1.5; color:#475569; }
    .lp-step-text b { display:block; color:var(--lp-ink); font-weight:600; }
    @media (max-width:767px) { .lp-prog-steps { grid-template-columns:1fr; } }
    .lp-prog .alert { margin-bottom:16px; border:none; border-left:4px solid var(--lp-blue); border-radius:10px; background:#eff6ff; color:#1e3a8a; font-size:12.5px; }
    .lp-prog .alert.alert-success { border-left-color:#059669; background:#ecfdf5; color:#065f46; }
    .lp-prog .btn { border-radius:8px; font-size:12.5px; font-weight:600; }
    .lp-prog .btn-outline-primary { color:#123f70; border-color:#123f70; }
    .lp-prog .btn-outline-primary:hover { background:#123f70; border-color:#123f70; color:#fff; }
    .lp-prog .btn-outline-secondary { color:#4b5563; border-color:#d1d5db; }
    .lp-prog .btn-outline-secondary:hover { background:#f3f4f6; border-color:#9ca3af; color:#374151; }
    .lp-prog .btn-success { background:#15803d; border-color:#15803d; }
    .lp-prog .btn-success:hover { background:#166534; border-color:#166534; }
    #confirmedContainer h6 { display:flex; align-items:center; gap:7px; font-size:12.5px; }
    #confirmedList .badge { display:inline-flex; align-items:center; gap:6px; padding:7px 15px; border-radius:999px; background:#d1fae5 !important; color:#065f46 !important; font-size:12px; font-weight:600; }
    #suggestionsContainer h6 { font-size:12.5px; color:var(--lp-ink); }
    #suggestionsContainer .table { font-size:12.5px; }
    #suggestionsContainer .table thead th { padding:9px 12px; border-color:#09173c; background:#09173c; color:#fff; font-size:10.5px; letter-spacing:.5px; text-transform:uppercase; white-space:nowrap; }
    #suggestionsContainer .table tbody td { padding:9px 12px; border-color:#eef2f7; vertical-align:middle; }
    #suggestionsContainer .table-bordered { border-color:#eef2f7; }

    /* ---------- Standings table ---------------------------------- */
    .lp-standings-tools { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .lp-group-filter { min-width:150px; padding:7px 12px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12.5px; }
    .lp-table-wrap { overflow-x:auto; }
    .lp-standings-table { font-size:13.5px; margin:0; width:100%; }
    .lp-standings-table thead th { white-space:nowrap; }
    .lp-standings-table tbody td { padding:11px 14px; }
    .lp-standings-table .lp-num { text-align:center; font-weight:500; color:#334155; font-variant-numeric:tabular-nums; }
    .lp-rank { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:9px; background:#f1f5f9; color:#475569; font-size:12px; font-weight:700; }
    .lp-rank.is-1 { background:#fff4d6; color:#92400e; box-shadow:inset 0 0 0 1px #f2d693; }
    .lp-rank.is-2 { background:#eef2f7; color:#475569; box-shadow:inset 0 0 0 1px #dbe2ea; }
    .lp-rank.is-3 { background:#fdece0; color:#9a3412; box-shadow:inset 0 0 0 1px #f3c9a8; }
    .lp-team-cell { display:flex; align-items:center; gap:12px; min-width:200px; }
    .lp-team-avatar { width:38px; height:38px; flex:0 0 auto; border-radius:10px; border:1px solid #e8edf4; background:#eef2f7; object-fit:cover; }
    .lp-team-name { font-weight:600; color:var(--lp-ink); line-height:1.3; text-transform:capitalize; }
    .lp-team-sub { font-size:11px; color:#9ca3af; }
    .lp-diff { font-weight:600; font-variant-numeric:tabular-nums; }
    .lp-diff.is-pos { color:#0f7b4f; }
    .lp-diff.is-neg { color:#b42318; }
    .lp-diff.is-zero { color:#6b7280; }
    .lp-group-form { margin:0; }
    .lp-group-select { min-width:132px; padding:5px 10px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12.5px; }
    .lp-group-select:focus { border-color:var(--lp-blue); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .lp-decide { display:inline-flex; align-items:center; justify-content:flex-end; gap:6px; margin:0; }
    .lp-standings-table .league-icon-btn { width:32px; height:32px; border-radius:9px; font-size:.85rem; }
    .lp-action-danger { color:#b42318; background:#fee2e2; border-color:#fecaca; }
    .lp-action-danger:hover { color:#fff; background:#b42318; border-color:#b42318; }
    .lp-action-reset { color:#475569; background:#f3f4f6; border-color:#e5e7eb; }
    .lp-action-reset:hover { color:#123f70; background:#e8edf7; border-color:#c7d5ea; }
    .lp-standings-legend { display:flex; align-items:center; flex-wrap:wrap; gap:6px 18px; padding:13px 24px; border-top:1px solid #f1f5f9; font-size:11.5px; color:#9ca3af; }
    .lp-standings-legend b { color:#475569; font-weight:600; }
    .lp-standings-legend .lp-legend-note { display:inline-flex; align-items:center; gap:6px; margin-left:auto; }
    @media (max-width:575px) { .lp-standings-legend .lp-legend-note { margin-left:0; } }
</style>
<?php if($flash): $flashType = in_array($flash[0], ['success','danger'], true) ? $flash[0] : 'info';
      $flashIcon = $flashType === 'success' ? 'bi-check-circle-fill' : ($flashType === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-info-circle-fill'); ?>
<div class="lp-flash lp-flash-<?= $flashType ?> dash-animate" role="alert"><i class="bi <?= $flashIcon ?>"></i><span><?= htmlspecialchars($flash[1]) ?></span></div>
<?php endif; ?>



<!-- ==================== TEAM STANDINGS ==================== -->
<div class="activity-section dash-animate dash-animate-d8">
    <div class="activity-header">
        <div class="activity-title">
            <i class="bi bi-bar-chart-line-fill"></i> Team Standings
            <span class="activity-badge-count"><?= $totalTeams ?> team<?= $totalTeams === 1 ? '' : 's' ?></span>
        </div>
        <div class="lp-standings-tools">
            <?php if ($groupingOn && $groups): ?>
                <select id="lpGroupFilter" class="lp-group-filter" aria-label="Filter by group">
                    <option value="">All groups</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= htmlspecialchars($g['name'], ENT_QUOTES) ?>"><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                    <option value="Ungrouped">Ungrouped</option>
                </select>
            <?php endif; ?>
            <div class="lp-search">
                <i class="bi bi-search"></i>
                <input type="text" id="lpStandingSearch" placeholder="Search team" autocomplete="off">
            </div>
        </div>
    </div>
    <div class="lp-table-wrap">
        <table class="table activity-table align-middle mb-0 lp-standings-table" id="lpStandingsTable">
            <thead>
                <tr>
                    <th style="width:70px;"><i class="bi bi-hash me-1"></i>Rank</th>
                    <?php if ($groupingOn): ?><th style="width:160px;"><i class="bi bi-diagram-3 me-1"></i>Group</th><?php endif; ?>
                    <th><i class="bi bi-shield-fill me-1"></i>Team</th>
                    <th class="text-center" title="Matches played">MP</th>
                    <th class="text-center" title="Wins">W</th>
                    <th class="text-center" title="Draws">D</th>
                    <th class="text-center" title="Losses">L</th>
                    <th class="text-center" title="Points for">PF</th>
                    <th class="text-center" title="Points against">PA</th>
                    <th class="text-center" title="Points difference">Diff</th>
                    <th style="width:150px;"><i class="bi bi-award me-1"></i>Progression</th>
                    <th class="text-end" style="width:150px;"><i class="bi bi-gear me-1"></i>Actions</th>
                </tr>
            </thead>
            <tbody>
<?php
$positions = [];
foreach ($rankings as $team):
    $key      = $groupingOn ? ($team['group_name'] ?: 'Ungrouped') : 'all';
    $positions[$key] = ($positions[$key] ?? 0) + 1;
    $position = $positions[$key];
    $rankCls  = $position <= 3 ? ' is-' . $position : '';
    $diff     = (int) $team['points_for'] - (int) $team['points_against'];
    $diffCls  = $diff > 0 ? 'is-pos' : ($diff < 0 ? 'is-neg' : 'is-zero');
    $diffText = ($diff > 0 ? '+' : '') . $diff;
    $progress = (string) $team['progression_status'];
    $progCls  = $progress === 'Qualified' ? 'is-approved' : ($progress === 'Eliminated' ? 'is-cancelled' : 'is-neutral');
    $teamImgRaw = (string) ($team['team_image'] ?? '');
    if ($teamImgRaw !== '' && StorageService::isCloudinary($teamImgRaw)) {
        $teamImgSrc = $teamImgRaw;
    } else {
        $teamImgFile = basename($teamImgRaw);
        $teamImgSrc  = ($teamImgFile !== '' && is_file(__DIR__ . '/../../assets/uploads/' . $teamImgFile))
            ? '../../assets/uploads/' . $teamImgFile
            : '../../assets/images/default-league.svg';
    }
    $teamNameSafe = htmlspecialchars((string) $team['team_name']);
?>
<tr class="lp-standing-row" data-team="<?= htmlspecialchars(strtolower((string) $team['team_name']), ENT_QUOTES) ?>" data-group="<?= htmlspecialchars($key, ENT_QUOTES) ?>">
    <td><span class="lp-rank<?= $rankCls ?>"><?= $position ?></span></td>
    <?php if ($groupingOn): ?>
        <td>
            <form method="post" class="lp-group-form">
                <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
                <input type="hidden" name="league_id" value="<?= $id ?>">
                <input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>">
                <input type="hidden" name="assignGroup" value="1">
                <select name="group_id" class="lp-group-select" aria-label="Assign group for <?= $teamNameSafe ?>" onchange="this.form.submit()">
                    <option value="0">Ungrouped</option>
                    <?php foreach ($groups as $g): ?>
                        <option value="<?= (int) $g['id'] ?>" <?= (int) $team['group_id'] === (int) $g['id'] ? 'selected' : '' ?>><?= htmlspecialchars($g['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </form>
        </td>
    <?php endif; ?>
    <td>
        <div class="lp-team-cell">
            <img class="lp-team-avatar" src="<?= htmlspecialchars($teamImgSrc, ENT_QUOTES) ?>" alt="" loading="lazy" onerror="this.onerror=null;this.src='../../assets/images/default-league.svg';">
            <div>
                <div class="lp-team-name"><?= $teamNameSafe ?></div>
                <div class="lp-team-sub"><?= $groupingOn ? htmlspecialchars($key) : 'Overall table' ?></div>
            </div>
        </div>
    </td>
    <td class="lp-num"><?= (int) $team['played'] ?></td>
    <td class="lp-num"><?= (int) $team['wins'] ?></td>
    <td class="lp-num"><?= (int) $team['draws'] ?></td>
    <td class="lp-num"><?= (int) $team['losses'] ?></td>
    <td class="lp-num"><?= (int) $team['points_for'] ?></td>
    <td class="lp-num"><?= (int) $team['points_against'] ?></td>
    <td class="text-center"><span class="lp-diff <?= $diffCls ?>"><?= $diffText ?></span></td>
    <td><span class="lp-row-status <?= $progCls ?>"><?= htmlspecialchars($progress) ?></span></td>
    <td class="text-end">
        <form method="post" class="lp-decide">
            <input type="hidden" name="csrf_token" value="<?= $csrf ?>">
            <input type="hidden" name="league_id" value="<?= $id ?>">
            <input type="hidden" name="team_id" value="<?= (int) $team['id'] ?>">
            <input type="hidden" name="setProgression" value="1">
            <button name="decision" value="Qualified" class="league-icon-btn league-action-ok" title="Mark qualified" aria-label="Mark <?= $teamNameSafe ?> qualified"><i class="bi bi-check2-circle"></i></button>
            <button name="decision" value="Eliminated" class="league-icon-btn lp-action-danger" title="Mark eliminated" aria-label="Mark <?= $teamNameSafe ?> eliminated"><i class="bi bi-slash-circle"></i></button>
            <button name="decision" value="Undecided" class="league-icon-btn lp-action-reset" title="Reset to undecided" aria-label="Reset <?= $teamNameSafe ?> to undecided"><i class="bi bi-arrow-counterclockwise"></i></button>
        </form>
    </td>
</tr>
<?php endforeach; ?>

            <?php if (!$rankings): ?>
                <tr><td colspan="<?= $groupingOn ? 12 : 11 ?>"><div class="lp-empty"><i class="bi bi-people"></i><p>No approved teams yet. Approve team applications first — completed matches will then fill this table automatically.</p></div></td></tr>
            <?php endif; ?>
            <tr id="lpStandingsNoMatch" style="display:none;"><td colspan="<?= $groupingOn ? 12 : 11 ?>"><div class="lp-empty"><i class="bi bi-search"></i><p>No teams match your search or group filter.</p></div></td></tr>
            </tbody>
        </table>
    </div>
    <div class="lp-standings-legend">
        <span><b>MP</b> matches played</span>
        <span><b>W</b> wins</span>
        <span><b>D</b> draws</span>
        <span><b>L</b> losses</span>
        <span><b>PF</b> points for</span>
        <span><b>PA</b> points against</span>
        <span><b>Diff</b> points difference</span>
        <span class="lp-legend-note"><i class="bi bi-info-circle"></i>Only published, completed matches are counted.</span>
    </div>
</div>



</div></div></div></div><?php include 'includes/footer.php'; ?>
<script>window.LEAGUE_ID = <?= (int)$id ?>; window.CSRF_TOKEN = <?= json_encode($csrf) ?>;</script>
<script src="js/progression-manager.js"></script>
<script>
(function () {
    var table = document.getElementById('lpStandingsTable');
    if (!table) return;
    var search = document.getElementById('lpStandingSearch');
    var groupFilter = document.getElementById('lpGroupFilter');
    var noMatch = document.getElementById('lpStandingsNoMatch');
    var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr[data-team]'));

    function applyFilter() {
        var q = search ? search.value.toLowerCase().trim() : '';
        var grp = groupFilter ? groupFilter.value : '';
        var shown = 0;
        rows.forEach(function (row) {
            var hay = row.getAttribute('data-team') || '';
            var rowGroup = row.getAttribute('data-group') || '';
            var visible = (q === '' || hay.indexOf(q) !== -1) && (grp === '' || rowGroup === grp);
            row.style.display = visible ? '' : 'none';
            if (visible) shown++;
        });
        if (noMatch) noMatch.style.display = shown === 0 ? '' : 'none';
    }
    if (search) search.addEventListener('input', applyFilter);
    if (groupFilter) groupFilter.addEventListener('change', applyFilter);
})();
</script>
</body></html>
