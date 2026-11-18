<?php
require_once __DIR__.'/../../config/dbconnect.php';
require_once __DIR__.'/../../config/auth_guard.php';
require_once __DIR__.'/../../includes/storageService.php';
require_once __DIR__.'/../../includes/stageHelper.php';
require_once __DIR__.'/../../includes/schedulingHelper.php';
require_once __DIR__.'/../../includes/standingsSections.php';
require_once __DIR__.'/../../includes/standingsView.php';
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
            /* Legacy group assignment (leagues without a stage structure). */
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
        } elseif(isset($_POST['assignStageGroup'])){
            /* A team belongs to one group per stage. Moving it here keeps the
               stage table, the carry-over pools and the scheduler in sync. */
            $teamId=(int)($_POST['team_id'] ?? 0);
            $stageId=(int)($_POST['stage_id'] ?? 0);
            $stageGroupId=(int)($_POST['stage_group_id'] ?? 0);
            if($teamId<=0 || $stageId<=0) throw new RuntimeException('Choose a team and stage.');
            $teamStmt=$conn->prepare("SELECT 1 FROM tbl_team WHERE id=? AND league_id=? AND LOWER(status)='approved'"); $teamStmt->bind_param('ii',$teamId,$id); $teamStmt->execute(); $validTeam=(bool)$teamStmt->get_result()->fetch_row(); $teamStmt->close();
            if(!$validTeam) throw new RuntimeException('Approved team not found in this league.');
            $stage=getStage($conn,$stageId);
            if(!$stage || (int)$stage['league_id']!==$id) throw new RuntimeException('Stage not found in this league.');
            if(!stageFormatUsesTable(stageFormat($stage))) throw new RuntimeException('Teams can only be moved between groups of a table stage.');
            if($stageGroupId>0){
                $group=getStageGroup($conn,$stageGroupId);
                if(!$group || (int)$group['stage_id']!==$stageId || (int)$group['league_id']!==$id) throw new RuntimeException('Stage group not found in this stage.');
            }
            $stageFixtureStmt=$conn->prepare("SELECT COUNT(*) AS c FROM tbl_schedule WHERE league_id=? AND stage_id=? AND (is_archived=0 OR is_archived IS NULL)");
            $stageFixtureCount=0;
            if($stageFixtureStmt){
                $stageFixtureStmt->bind_param('ii',$id,$stageId); $stageFixtureStmt->execute();
                $stageFixtureCount=(int)($stageFixtureStmt->get_result()->fetch_assoc()['c'] ?? 0);
                $stageFixtureStmt->close();
            }
            if($stageFixtureCount>0) throw new RuntimeException('This stage already has fixtures. Change its draw in Setup → Teams before moving teams.');
            moveStageTeamToGroup($conn,$stageId,$teamId,$stageGroupId>0?$stageGroupId:null);
            auditLog($conn,'Assign Stage Team Group',"Assigned team ID $teamId to stage group ID $stageGroupId in stage ID $stageId (league ID $id)");
            $_SESSION['standing_flash']=['success','Team stage grouping updated.'];
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

/* Legacy groups are still used when the league has no stage structure. */
$groups=[]; $groupStmt=$conn->prepare("SELECT id,name,display_order FROM tbl_league_group WHERE league_id=? ORDER BY display_order,name"); $groupStmt->bind_param('i',$id); $groupStmt->execute(); $groupRes=$groupStmt->get_result(); while($g=$groupRes->fetch_assoc())$groups[]=$g; $groupStmt->close();
$groupingOn = (int)($league['grouping_enabled'] ?? 0) === 1;
$groupCount = count($groups);

/* One shared section builder feeds the public and organizer standings pages. */
$standingsData = getLeagueStandingsSections($conn, (int)$id);
$sections      = $standingsData['sections'];
$usesSets      = $standingsData['uses_sets'];
$stageCount    = (int)$standingsData['stage_count'];
$totalTeams    = (int)$standingsData['team_count'];

$stageNav = [];
foreach ($sections as $section) {
    $stageId = (int)($section['stage_id'] ?? 0);
    if ($stageId > 0 && !isset($stageNav[$stageId])) {
        $stageNav[$stageId] = [
            'id'     => $stageId,
            'name'   => (string)$section['stage_name'],
            'format' => (string)$section['stage_format'],
        ];
    }
}

/* Published-match progress for the header. Drafts never enter either count. */
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
$statusStmt = $conn->prepare("SELECT progression_status, COUNT(*) AS c
    FROM tbl_team WHERE league_id=? AND LOWER(status)='approved' GROUP BY progression_status");
if($statusStmt){
    $statusStmt->bind_param('i',$id); $statusStmt->execute();
    $statusRes = $statusStmt->get_result();
    while($statusRow = $statusRes->fetch_assoc()){
        if($statusRow['progression_status'] === 'Qualified') $qualifiedCount = (int)$statusRow['c'];
        elseif($statusRow['progression_status'] === 'Eliminated') $eliminatedCount = (int)$statusRow['c'];
    }
    $statusStmt->close();
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
<?php $leagueHeroData = $league; $leagueHeroApprovedCount = $totalTeams; include 'includes/league-hero.php'; ?>
<div class="lp-tabs dash-animate dash-animate-d1">
<?php $leagueTabLeagueId=$id; include 'includes/league_tabs.php'; ?>
</div>
<style>
    <?php include __DIR__ . '/../../includes/standings-stage-styles.php'; ?>

    /* ---------- Flash notice ---------------------------------------- */
    .lp-flash { display:flex; align-items:flex-start; gap:11px; margin-bottom:20px; padding:13px 18px; border:1px solid var(--lp-border); border-radius:10px; background:#fff; font-size:13px; font-weight:500; }
    .lp-flash i { margin-top:1px; font-size:15px; }
    .lp-flash span { line-height:1.5; }
    .lp-flash-success { border-color:#a7f3d0; background:#ecfdf5; color:#065f46; }
    .lp-flash-danger { border-color:#fecaca; background:#fef2f2; color:#991b1b; }
    .lp-flash-info { border-color:#bfdbfe; background:#eff6ff; color:#1e40af; }

    /* ---------- Page shell ------------------------------------------ */
    .lp-page .activity-section.lp-stage-panel { margin-bottom:20px; }
    .lp-page .lp-stage-panel .lp-stage-head { padding:18px 24px 14px; }
    .lp-page .lp-stage-panel .lp-stage-body { padding:0 24px 24px; }
    .lp-page .lp-stage-panel > .lp-stage-head { margin-bottom:0; }
    .lp-standings-tools { display:flex; align-items:center; gap:10px; flex-wrap:wrap; }
    .lp-group-filter { min-width:150px; padding:7px 12px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12.5px; }
    .lp-search { position:relative; }
    .lp-search i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:13px; color:#9ca3af; }
    .lp-search input { min-width:220px; padding:7px 14px 7px 32px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12.5px; transition:border-color .2s ease, box-shadow .2s ease; }
    .lp-search input:focus { outline:none; border-color:var(--lp-blue); background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .lp-standings-table { font-size:13.5px; margin:0; width:100%; }
    .lp-standings-table thead th { white-space:nowrap; }
    .lp-standings-table tbody td { padding:11px 14px; }
    .lp-standings-table .lp-num { text-align:center; font-weight:500; color:#334155; font-variant-numeric:tabular-nums; }
    .lp-rank { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:9px; background:#f1f5f9; color:#475569; font-size:12px; font-weight:700; }
    .lp-rank.is-1 { background:#fff4d6; color:#92400e; box-shadow:inset 0 0 0 1px #f2d693; }
    .lp-rank.is-2 { background:#eef2f7; color:#475569; box-shadow:inset 0 0 0 1px #dbe2ea; }
    .lp-rank.is-3 { background:#fdece0; color:#9a3412; box-shadow:inset 0 0 0 1px #f3c9a8; }
    .lp-group-form { margin:0; }
    .lp-group-select { min-width:132px; padding:5px 10px; border:1.5px solid var(--lp-border); border-radius:8px; background:#f9fafb; font-size:12.5px; }
    .lp-group-select:focus { border-color:var(--lp-blue); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .lp-decide { display:inline-flex; align-items:center; justify-content:flex-end; gap:6px; margin:0; }
    .lp-standings-table .league-icon-btn { width:32px; height:32px; border-radius:9px; font-size:.85rem; }
    .lp-action-danger { color:#b42318; background:#fee2e2; border-color:#fecaca; }
    .lp-action-danger:hover { color:#fff; background:#b42318; border-color:#b42318; }
    .lp-action-reset { color:#475569; background:#f3f4f6; border-color:#e5e7eb; }
    .lp-action-reset:hover { color:#123f70; background:#e8edf7; border-color:#c7d5ea; }
    .lp-stage-hint { display:flex; align-items:flex-start; gap:7px; margin:12px 0 0; font-size:11.5px; line-height:1.5; color:#64748b; }
    .lp-stage-hint a { font-weight:600; color:#1d4ed8; text-decoration:none; }
    .lp-stage-hint a:hover { text-decoration:underline; }
    .lp-standings-no-match { margin:20px 0; }
    @media (max-width:575px) {
        .lp-page .lp-stage-panel .lp-stage-head { padding:16px 16px 12px; }
        .lp-page .lp-stage-panel .lp-stage-body { padding:0 16px 18px; }
    }
</style>
<?php if($flash): $flashType = in_array($flash[0], ['success','danger'], true) ? $flash[0] : 'info';
      $flashIcon = $flashType === 'success' ? 'bi-check-circle-fill' : ($flashType === 'danger' ? 'bi-exclamation-octagon-fill' : 'bi-info-circle-fill'); ?>
<div class="lp-flash lp-flash-<?= $flashType ?> dash-animate" role="alert"><i class="bi <?= $flashIcon ?>"></i><span><?= htmlspecialchars($flash[1]) ?></span></div>
<?php endif; ?>

<!-- ==================== STANDINGS HEADER ==================== -->
<div class="activity-section lp-stage-panel dash-animate dash-animate-d8">
    <div class="lp-stage-head">
        <div class="lp-stage-heading">
            <span class="lp-stage-icon"><i class="bi bi-bar-chart-line-fill"></i></span>
            <div>
                <h2 class="lp-stage-title">Team Standings</h2>
                <p class="lp-stage-caption">
                    Manage progression and stage groups. Tables and brackets follow the league's competition structure.
                    <?php if($totalMatches > 0): ?>
                        <?= $completedMatches ?> of <?= $totalMatches ?> published matches played.
                    <?php endif; ?>
                </p>
            </div>
        </div>
        <div class="lp-stage-badges">
            <span class="lp-stage-count"><i class="bi bi-people"></i><?= $totalTeams ?> team<?= $totalTeams === 1 ? '' : 's' ?></span>
            <?php if($stageCount > 0): ?>
                <span class="lp-stage-format"><?= $stageCount ?> stage<?= $stageCount === 1 ? '' : 's' ?></span>
            <?php endif; ?>
            <?php if($stageCount === 0 && $groupingOn && $groups): ?>
                <select id="lpGroupFilter" class="lp-group-filter" aria-label="Filter by group">
                    <option value="">All groups</option>
                    <?php foreach($groups as $g): ?>
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
</div>

<?php if(count($stageNav) > 1): ?>
    <nav class="lp-stage-nav" aria-label="Competition stages">
        <span class="lp-stage-nav-label">Stages</span>
        <?php foreach($stageNav as $navStage): ?>
            <a href="#stage-<?= (int)$navStage['id'] ?>">
                <i class="bi <?= htmlspecialchars(standingsFormatIcon((string)$navStage['format']), ENT_QUOTES) ?>"></i>
                <?= htmlspecialchars($navStage['name']) ?>
            </a>
        <?php endforeach; ?>
    </nav>
<?php endif; ?>

<div id="lpStandingsNoMatch" class="lp-empty lp-standings-no-match" style="display:none;">
    <i class="bi bi-search"></i>
    <h5>No teams match your search</h5>
    <p>Try a different team name or group.</p>
</div>

<?php if(!$sections): ?>
    <div class="activity-section lp-stage-panel">
        <div class="lp-stage-body" style="padding-top:20px;">
            <?= renderStandingsEmpty('No standings yet', 'Rankings appear here once the organizer records match results.', 'bi-trophy') ?>
        </div>
    </div>
<?php else: ?>
    <?php foreach($sections as $sectionIndex => $section): ?>
        <?php
        $sectionStageId = (int)($section['stage_id'] ?? 0);
        $sectionAnchor  = $sectionStageId > 0
            ? 'stage-' . $sectionStageId
            : 'standings-' . ($sectionIndex + 1);
        $sectionKind    = (string)($section['kind'] ?? 'table');
        ?>
        <div class="activity-section lp-stage-panel dash-animate" id="<?= htmlspecialchars($sectionAnchor, ENT_QUOTES) ?>" data-has-standings="1">
            <?= renderStandingsStageHeader($section, ['show_status' => $sectionStageId > 0]) ?>
            <div class="lp-stage-body">
                <?php if($sectionKind === 'table'): ?>
                    <?php $tables = $section['tables'] ?? []; ?>
                    <?php if($tables): ?>
                        <div class="lp-stage-tables<?= count($tables) > 1 ? ' is-grid' : '' ?>">
                            <?php foreach($tables as $table): ?>
                                <?php
                                $tableGroupId   = isset($table['group_id']) ? (int)$table['group_id'] : 0;
                                $tableGroupName = trim((string)($table['group_name'] ?? ''));
                                $showStageMove  = $sectionStageId > 0
                                    && !empty($section['groups'])
                                    && $tableGroupId > 0
                                    && empty($section['stage_has_matches']);
                                $showLegacyMove = $sectionStageId === 0 && !empty($section['is_legacy_group']) && $groupingOn && $groups;
                                $extraHeaders   = [];
                                if($showStageMove || $showLegacyMove){
                                    $extraHeaders[] = ['label' => 'Group', 'icon' => 'bi-diagram-3', 'style' => 'width:150px;'];
                                }
                                $extraHeaders[] = ['label' => 'Actions', 'icon' => 'bi-gear', 'class' => 'text-end', 'style' => 'width:150px;'];
                                $extraCells = function(array $row) use ($csrf, $id, $section, $sectionStageId, $tableGroupId, $showStageMove, $showLegacyMove, $groups): array {
                                    $teamId = (int)($row['id'] ?? 0);
                                    $teamNameSafe = htmlspecialchars((string)($row['team_name'] ?? ''));
                                    $cells = [];
                                    if($showStageMove){
                                        $options = '<option value="0">Unassigned</option>';
                                        foreach(($section['groups'] ?? []) as $groupOption){
                                            $selected = (int)$groupOption['id'] === $tableGroupId ? ' selected' : '';
                                            $options .= '<option value="' . (int)$groupOption['id'] . '"' . $selected . '>' . htmlspecialchars((string)$groupOption['name']) . '</option>';
                                        }
                                        $cells[] = '<td><form method="post" class="lp-group-form">'
                                            . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf, ENT_QUOTES) . '">'
                                            . '<input type="hidden" name="league_id" value="' . (int)$id . '">'
                                            . '<input type="hidden" name="team_id" value="' . $teamId . '">'
                                            . '<input type="hidden" name="stage_id" value="' . (int)$sectionStageId . '">'
                                            . '<input type="hidden" name="assignStageGroup" value="1">'
                                            . '<select name="stage_group_id" class="lp-group-select" aria-label="Move ' . $teamNameSafe . ' to another stage group" onchange="this.form.submit()">' . $options . '</select>'
                                            . '</form></td>';
                                    } elseif($showLegacyMove){
                                        $options = '<option value="0">Ungrouped</option>';
                                        foreach($groups as $groupOption){
                                            $selected = (int)$groupOption['id'] === (int)($row['group_id'] ?? 0) ? ' selected' : '';
                                            $options .= '<option value="' . (int)$groupOption['id'] . '"' . $selected . '>' . htmlspecialchars((string)$groupOption['name']) . '</option>';
                                        }
                                        $cells[] = '<td><form method="post" class="lp-group-form">'
                                            . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf, ENT_QUOTES) . '">'
                                            . '<input type="hidden" name="league_id" value="' . (int)$id . '">'
                                            . '<input type="hidden" name="team_id" value="' . $teamId . '">'
                                            . '<input type="hidden" name="assignGroup" value="1">'
                                            . '<select name="group_id" class="lp-group-select" aria-label="Move ' . $teamNameSafe . ' to another group" onchange="this.form.submit()">' . $options . '</select>'
                                            . '</form></td>';
                                    }
                                    $cells[] = '<td class="text-end"><form method="post" class="lp-decide">'
                                        . '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($csrf, ENT_QUOTES) . '">'
                                        . '<input type="hidden" name="league_id" value="' . (int)$id . '">'
                                        . '<input type="hidden" name="team_id" value="' . $teamId . '">'
                                        . '<input type="hidden" name="setProgression" value="1">'
                                        . '<button name="decision" value="Qualified" class="league-icon-btn league-action-ok" title="Mark qualified" aria-label="Mark ' . $teamNameSafe . ' qualified"><i class="bi bi-check2-circle"></i></button>'
                                        . '<button name="decision" value="Eliminated" class="league-icon-btn lp-action-danger" title="Mark eliminated" aria-label="Mark ' . $teamNameSafe . ' eliminated"><i class="bi bi-slash-circle"></i></button>'
                                        . '<button name="decision" value="Undecided" class="league-icon-btn lp-action-reset" title="Reset to undecided" aria-label="Reset ' . $teamNameSafe . ' to undecided"><i class="bi bi-arrow-counterclockwise"></i></button>'
                                        . '</form></td>';
                                    return $cells;
                                };
                                ?>
                                <div class="lp-stage-table">
                                    <?php if(count($tables) > 1 && $tableGroupName !== ''): ?>
                                        <div class="lp-stage-table-head">
                                            <b><?= htmlspecialchars($tableGroupName) ?></b>
                                            <span><?= count($table['rows'] ?? []) ?> team<?= count($table['rows'] ?? []) === 1 ? '' : 's' ?></span>
                                            <?php if(!empty($table['sources'])): ?>
                                                <span class="lp-source-badge"><i class="bi bi-arrow-down-right-circle"></i>Fed by <?= htmlspecialchars(implode(', ', $table['sources'])) ?></span>
                                            <?php endif; ?>
                                        </div>
                                    <?php elseif(!empty($table['sources'])): ?>
                                        <div class="lp-stage-table-head">
                                            <span class="lp-source-badge"><i class="bi bi-arrow-down-right-circle"></i>Fed by <?= htmlspecialchars(implode(', ', $table['sources'])) ?></span>
                                        </div>
                                    <?php endif; ?>
                                    <?= renderStandingsTable($table['rows'] ?? [], [
                                        'table_class'      => 'table activity-table align-middle mb-0 lp-standings-table',
                                        'table_id'         => 'lpStandingsTable-' . ($sectionStageId ?: 'legacy') . '-' . ($tableGroupId ?: 'all'),
                                        'asset_prefix'     => '../../',
                                        'show_sets'        => $usesSets,
                                        'show_progression' => true,
                                        'row_group'        => $tableGroupName,
                                        'data_attrs'       => 'data-stage-id="' . (int)$sectionStageId . '" data-group-id="' . (int)$tableGroupId . '"',
                                        'caption'          => trim((string)($section['stage_name'] ?? 'Standings') . ($tableGroupName !== '' ? ' — ' . $tableGroupName : '')),
                                        'empty_title'      => 'No teams in this table yet',
                                        'empty_message'    => 'Assign teams in Setup → Teams or wait for the draw.',
                                        'extra_headers'    => $extraHeaders,
                                        'extra_cells'      => $extraCells,
                                    ]) ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <?= renderStandingsEmpty('No teams in this stage yet', 'Teams appear here once they are placed in the stage draw.') ?>
                    <?php endif; ?>
                <?php elseif($sectionKind === 'bracket'): ?>
                    <?= renderStandingsBracket($section) ?>
                    <p class="lp-stage-hint">
                        <i class="bi bi-info-circle"></i>
                        <span>Bracket results advance automatically when a Committee submits a score.
                            <a href="schedule.php?id=<?= (int)$id ?>&amp;view=bracket#bracket">Open the schedule bracket</a>.
                        </span>
                    </p>
                <?php else: ?>
                    <?= renderStandingsPlacement($section) ?>
                <?php endif; ?>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

</div></div></div></div><?php include 'includes/footer.php'; ?>
<script>window.LEAGUE_ID = <?= (int)$id ?>; window.CSRF_TOKEN = <?= json_encode($csrf) ?>;</script>
<script src="js/progression-manager.js"></script>
<script>
(function () {
    var search = document.getElementById('lpStandingSearch');
    var groupFilter = document.getElementById('lpGroupFilter');
    var noMatch = document.getElementById('lpStandingsNoMatch');
    var rows = Array.prototype.slice.call(document.querySelectorAll('.lp-standings-table tbody tr[data-team]'));
    var cards = Array.prototype.slice.call(document.querySelectorAll('.lp-bracket-match[data-team]'));
    var panels = Array.prototype.slice.call(document.querySelectorAll('.lp-stage-panel[data-has-standings]'));
    if (!search && !groupFilter) return;

    function setHidden(el, hidden) {
        if (!el) return;
        el.classList.toggle('is-hidden', !!hidden);
    }

    function applyFilter() {
        var q = search ? search.value.toLowerCase().trim() : '';
        var group = groupFilter ? groupFilter.value : '';
        var hasFilter = q !== '' || group !== '';
        var visibleRows = 0;
        var visibleCards = 0;

        rows.forEach(function (row) {
            var hay = row.getAttribute('data-team') || '';
            var rowGroup = row.getAttribute('data-group') || '';
            var visible = (q === '' || hay.indexOf(q) !== -1) && (group === '' || rowGroup === group);
            setHidden(row, !visible);
            if (visible) visibleRows++;
        });

        cards.forEach(function (card) {
            var hay = card.getAttribute('data-team') || '';
            var visible = q === '' || hay.indexOf(q) !== -1;
            setHidden(card, !visible);
            if (visible) visibleCards++;
        });

        document.querySelectorAll('.lp-bracket-round').forEach(function (round) {
            setHidden(round, !round.querySelector('.lp-bracket-match:not(.is-hidden)'));
        });
        document.querySelectorAll('.lp-bracket-side').forEach(function (side) {
            setHidden(side, !side.querySelector('.lp-bracket-round:not(.is-hidden)'));
        });
        document.querySelectorAll('.lp-bracket-board').forEach(function (board) {
            setHidden(board, !board.querySelector('.lp-bracket-side:not(.is-hidden)'));
        });

        panels.forEach(function (panel) {
            var hasRows = panel.querySelector('tr[data-team]:not(.is-hidden)');
            var hasCards = panel.querySelector('.lp-bracket-match:not(.is-hidden)');
            var hasEmpty = panel.querySelector('.lp-stage-empty');
            setHidden(panel, hasFilter && !hasRows && !hasCards && !hasEmpty);
        });

        if (noMatch) {
            noMatch.style.display = (hasFilter && visibleRows === 0 && visibleCards === 0) ? '' : 'none';
        }
    }

    if (search) search.addEventListener('input', applyFilter);
    if (groupFilter) groupFilter.addEventListener('change', applyFilter);
})();
</script>
</body></html>
