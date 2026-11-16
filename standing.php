<?php
require_once __DIR__ . '/config/leagueSession.php';
require_once __DIR__.'/config/dbconnect.php';
$id=(int)($_GET['id'] ?? 0);
require_once __DIR__.'/includes/header.php';
if ((int) ($_SESSION['lms_role'] ?? 0) === 2) {
    require_once __DIR__ . '/includes/bar-tr-league.php'; // Team Representative portal chrome
} else {
    require_once __DIR__ . '/includes/bar.php'; // public navbar
}
require_once __DIR__.'/includes/league_header.php';

$groups=[]; $groupStmt=$conn->prepare("SELECT id,name,display_order FROM tbl_league_group WHERE league_id=? ORDER BY display_order,name"); $groupStmt->bind_param('i',$id); $groupStmt->execute(); $groupRes=$groupStmt->get_result(); while($g=$groupRes->fetch_assoc())$groups[]=$g; $groupStmt->close();
$grouping=(int)($league_row['grouping_enabled'] ?? 0)===1;
require_once __DIR__.'/includes/standingsProvider.php';
$leagueProfile = getLeagueSportProfile($conn,(int)$id);
$usesSets      = sportProfileUsesSets($leagueProfile);
$showPoints    = true;
$publicRules   = getLeagueScoringRules($conn,(int)$id);
$setModePublic = (($publicRules['mode'] ?? 'simple') === 'set_line');
/* One canonical provider feeds the public table, the organizer table and the
   stage tables, so every surface ranks teams the same way. */
$rows=[];
if(!$grouping){
    $rows=getLeagueStandings($conn,$id,['group_by'=>'legacy']);
} else {
    $seen=[];
    foreach($groups as $publicGroup){
        $publicGroupId=(int)$publicGroup['id'];
        foreach(getLeagueStandings($conn,$id,['group_by'=>'legacy','group_filter'=>$publicGroupId,'scope_sql'=>'s.league_group_id = ?','scope_params'=>[$publicGroupId]]) as $publicRow){
            $rows[]=$publicRow; $seen[(int)$publicRow['id']]=true;
        }
    }
    $allRows=getLeagueStandings($conn,$id,['group_by'=>'legacy']);
    $leftoverIds=[]; foreach($allRows as $allRow) if(!isset($seen[(int)$allRow['id']])) $leftoverIds[]=(int)$allRow['id'];
    if($leftoverIds){
        foreach(getLeagueStandings($conn,$id,['group_by'=>'legacy','team_ids'=>$leftoverIds,'scope_sql'=>'1=0']) as $leftoverRow){
            $leftoverRow['group_name']=$leftoverRow['group_name'] ?: 'Ungrouped'; $rows[]=$leftoverRow;
        }
    }
}
$sections=[]; foreach($rows as $row){$key=$grouping?($row['group_name']?:'Ungrouped'):'League Rankings';$sections[$key][]=$row;}
?>
<link rel="stylesheet" href="assets/vendor/font-awesome/6.4.0/css/all.min.css">
<style>
    /* ==========================================================
       Standings — page-specific additions only. Shared panels and
       tables live in includes/league-view-styles.php
       ========================================================== */
    .lp-count { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:20px; background:#e8edf7; color:#1a3a7c; font-size:11px; font-weight:600; }
    .lp-team-logo { width:30px; height:30px; border:1px solid #e8eef6; border-radius:50%; background:#e8eef6; object-fit:cover; }
    .lp-rank { display:inline-flex; align-items:center; justify-content:center; width:26px; height:26px; border-radius:8px; background:#eef2f7; color:#475569; font-size:11.5px; font-weight:700; }
    .lp-table tbody tr:nth-child(1) .lp-rank { background:#fef3c7; color:#92400e; }
    .lp-table tbody tr:nth-child(2) .lp-rank { background:#e2e8f0; color:#334155; }
    .lp-table tbody tr:nth-child(3) .lp-rank { background:#fde8ec; color:#b42335; }
    .lp-team-cell { display:inline-flex; align-items:center; gap:9px; min-width:0; font-weight:600; color:var(--lp-navy); }
    .lp-num { font-variant-numeric:tabular-nums; }
    .lp-pts { font-weight:700; }
    .lp-diff-up { color:#15803d; font-weight:600; }
    .lp-diff-down { color:#b91c1c; font-weight:600; }
    .lp-prog { display:inline-flex; align-items:center; gap:6px; padding:4px 11px; border-radius:20px; font-size:11px; font-weight:600; letter-spacing:.3px; text-transform:uppercase; }
    .lp-prog::before { content:''; width:6px; height:6px; border-radius:50%; background:currentColor; opacity:.75; }
    .lp-prog.is-qualified { background:#d1fae5; color:#065f46; }
    .lp-prog.is-eliminated { background:#fee2e2; color:#991b1b; }
    .lp-prog.is-neutral { background:#e5e7eb; color:#374151; }
    @media (max-width:767.98px) {
        .lp-table { font-size:12.5px; }
        .lp-table thead th, .lp-table tbody td { padding:10px 12px; }
    }
</style>
<div class="container-fluid lp-page">
    <?php if(!$sections): ?>
        <div class="lp-empty">
            <i class="bi bi-trophy"></i>
            <h5>No standings yet</h5>
            <p>Rankings appear here once the organizer records match results.</p>
        </div>
    <?php endif; ?>

    <?php foreach($sections as $section=>$teams): ?>
        <section class="lp-panel">
            <div class="lp-panel-head">
                <h2 class="lp-panel-title"><span class="lp-panel-icon"><i class="bi bi-trophy-fill"></i></span><?= htmlspecialchars($section) ?></h2>
                <span class="lp-count"><i class="bi bi-people"></i><?= count($teams) ?> team<?= count($teams) === 1 ? '' : 's' ?></span>
            </div>
            <div class="lp-table-wrap">
                <table class="lp-table">
                    <thead>
                        <tr>
                            <th class="lp-col-num">#</th>
                            <th>Team</th>
                            <th class="text-center">MP</th>
                            <th class="text-center">W</th>
                            <th class="text-center">D</th>
                            <th class="text-center">L</th>
                            <th class="text-center">Pts</th>
                            <?php if($usesSets): ?><th class="text-center">SW</th><th class="text-center">SL</th><th class="text-center">Set ratio</th><?php endif; ?>
                            <th class="text-center"><?= $usesSets?'PW':'PF' ?></th>
                            <th class="text-center"><?= $usesSets?'PL':'PA' ?></th>
                            <th class="text-center">Diff</th>
                            <th class="text-center">Progression</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach($teams as $index=>$team):
                        $tImg=$team['team_image']??'';
                        if(StorageService::isCloudinary($tImg)){$image=$tImg;}else{$image='assets/uploads/'.$tImg; if(empty($tImg)||!is_file(__DIR__.'/assets/uploads/'.rawurlencode($tImg))) $image='assets/images/default-league.svg';}
                        $prog=(string)($team['progression_status'] ?? '');
                        $progClass=$prog==='Qualified'?'is-qualified':($prog==='Eliminated'?'is-eliminated':'is-neutral');
                        $dispWins=(int)$team['wins']+(int)($team['forfeit_wins']??0);
                        $dispLosses=(int)$team['losses']+(int)($team['forfeit_losses']??0)+(int)($team['double_forfeits']??0);
                        $dispDiff=(int)$team['points_for']-(int)$team['points_against'];
                    ?>
                        <tr>
                            <td class="lp-col-num"><span class="lp-rank"><?= $index+1 ?></span></td>
                            <td>
                                <span class="lp-team-cell">
                                    <img src="<?= htmlspecialchars($image) ?>" class="lp-team-logo" alt="" onerror="this.onerror=null;this.src='assets/images/default-league.svg';">
                                    <?= htmlspecialchars($team['team_name']) ?>
                                </span>
                            </td>
                            <td class="text-center lp-num"><?= (int)$team['played'] ?></td>
                            <td class="text-center lp-num"><?= $dispWins ?></td>
                            <td class="text-center lp-num"><?= (int)$team['draws'] ?></td>
                            <td class="text-center lp-num"><?= $dispLosses ?></td>
                            <td class="text-center lp-num lp-pts"><?= (int)$team['points'] ?></td>
                            <?php if($usesSets): ?>
                                <td class="text-center lp-num"><?= (int)($team['sets_won']??0) ?></td>
                                <td class="text-center lp-num"><?= (int)($team['sets_lost']??0) ?></td>
                                <td class="text-center lp-num"><?= number_format((float)($team['set_ratio']??0),3) ?></td>
                            <?php endif; ?>
                            <td class="text-center lp-num"><?= (int)$team['points_for'] ?></td>
                            <td class="text-center lp-num"><?= (int)$team['points_against'] ?></td>
                            <td class="text-center lp-num <?= $dispDiff>0?'lp-diff-up':($dispDiff<0?'lp-diff-down':'') ?>"><?= ($dispDiff>0?'+':'').$dispDiff ?></td>
                            <td class="text-center"><span class="lp-prog <?= $progClass ?>"><?= htmlspecialchars($prog !== '' ? $prog : '—') ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </section>
    <?php endforeach; ?>
</div>
<?php require_once __DIR__.'/includes/footer.php'; ?>
