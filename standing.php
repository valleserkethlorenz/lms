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

require_once __DIR__ . '/includes/standingsSections.php';
require_once __DIR__ . '/includes/standingsView.php';

/* One shared builder feeds the public and organizer pages. It reads the
   league's stage structure, so every format renders its own table or bracket
   instead of collapsing every completed match into one league table. */
$standingsData = getLeagueStandingsSections($conn, (int)$id);
$sections      = $standingsData['sections'];
$usesSets      = $standingsData['uses_sets'];
$leagueProfile = $standingsData['profile'];
$publicRules   = $standingsData['rules'];
$stageCount    = $standingsData['stage_count'];
$totalTeams    = $standingsData['team_count'];

$stageNav = [];
foreach ($sections as $section) {
    $sid = (int)($section['stage_id'] ?? 0);
    if ($sid > 0 && !isset($stageNav[$sid])) {
        $stageNav[$sid] = [
            'id'     => $sid,
            'name'   => (string)$section['stage_name'],
            'format' => (string)$section['stage_format'],
        ];
    }
}
?>
<link rel="stylesheet" href="assets/vendor/font-awesome/6.4.0/css/all.min.css">
<style>
    <?php include __DIR__ . '/includes/standings-stage-styles.php'; ?>

    /* ---------- Page-specific shell -------------------------------- */
    .lp-standings-intro { display:flex; align-items:flex-start; gap:10px; margin:0 0 18px; padding:12px 16px; border:1px solid #bfdbfe; border-radius:10px; background:#eff6ff; color:#1e3a8a; font-size:12.5px; line-height:1.55; }
    .lp-standings-intro i { margin-top:1px; font-size:14px; }
    .lp-standings-footnote { display:flex; align-items:flex-start; gap:7px; margin:18px 0 0; padding:11px 14px; border-top:1px dashed #e2e8f0; font-size:11.5px; line-height:1.6; color:#64748b; }
    .lp-standings-footnote i { margin-top:1px; }
    .lp-stage-hint { display:flex; align-items:flex-start; gap:7px; margin:12px 0 0; font-size:11.5px; line-height:1.5; color:#64748b; }
    .lp-stage-hint a { font-weight:600; color:#1d4ed8; text-decoration:none; }
    .lp-stage-hint a:hover { text-decoration:underline; }
    .lp-rank { display:inline-flex; align-items:center; justify-content:center; width:28px; height:28px; border-radius:9px; background:#f1f5f9; color:#475569; font-size:12px; font-weight:700; }
    .lp-table tbody tr:nth-child(1) .lp-rank { background:#fff4d6; color:#92400e; box-shadow:inset 0 0 0 1px #f2d693; }
    .lp-table tbody tr:nth-child(2) .lp-rank { background:#eef2f7; color:#475569; box-shadow:inset 0 0 0 1px #dbe2ea; }
    .lp-table tbody tr:nth-child(3) .lp-rank { background:#fdece0; color:#9a3412; box-shadow:inset 0 0 0 1px #f3c9a8; }
    .lp-stage-table .lp-table { font-size:13px; }
    .lp-stage-table .lp-table thead th { padding:10px 12px; }
    .lp-stage-table .lp-table tbody td { padding:10px 12px; }
    .lp-stage-table .lp-standings-team-name { max-width:220px; }
    @media (max-width:767.98px) {
        .lp-stage-table .lp-table { font-size:12.5px; }
        .lp-stage-table .lp-table thead th, .lp-stage-table .lp-table tbody td { padding:9px 10px; }
        .lp-stage-table .lp-standings-team-name { max-width:150px; }
    }
</style>
<div class="container-fluid lp-page">
    <div class="lp-standings-intro">
        <i class="bi bi-info-circle-fill"></i>
        <span>Standings are calculated <b>per competition stage</b>. Only published, completed matches count toward a table or bracket.</span>
    </div>

    <?php if(!$sections): ?>
        <div class="lp-empty">
            <i class="bi bi-trophy"></i>
            <h5>No standings yet</h5>
            <p>Rankings appear here once the organizer records match results.</p>
        </div>
    <?php else: ?>
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

        <?php foreach($sections as $sectionIndex => $section): ?>
            <?php
            $sectionStageId = (int)($section['stage_id'] ?? 0);
            $sectionAnchor  = $sectionStageId > 0
                ? 'stage-' . $sectionStageId
                : 'standings-' . ($sectionIndex + 1);
            $sectionKind    = (string)($section['kind'] ?? 'table');
            ?>
            <section class="lp-panel lp-stage-panel" id="<?= htmlspecialchars($sectionAnchor, ENT_QUOTES) ?>">
                <?= renderStandingsStageHeader($section, ['show_status' => $sectionStageId > 0]) ?>
                <div class="lp-stage-body">
                    <?php if($sectionKind === 'table'): ?>
                        <?php $tables = $section['tables'] ?? []; ?>
                        <?php if($tables): ?>
                            <div class="lp-stage-tables<?= count($tables) > 1 ? ' is-grid' : '' ?>">
                                <?php foreach($tables as $table): ?>
                                    <div class="lp-stage-table">
                                        <?php if(count($tables) > 1 && !empty($table['group_name'])): ?>
                                            <div class="lp-stage-table-head">
                                                <b><?= htmlspecialchars((string)$table['group_name']) ?></b>
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
                                            'asset_prefix'      => '',
                                            'show_sets'         => $usesSets,
                                            'show_progression'  => true,
                                            'caption'           => trim((string)($section['stage_name'] ?? 'Standings') . (!empty($table['group_name']) ? ' — ' . $table['group_name'] : '')),
                                            'empty_title'       => 'No standings yet',
                                            'empty_message'     => 'This table fills as soon as a published match is completed.',
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
                                <a href="schedule.php?id=<?= (int)$id ?>&amp;view=bracket#bracket">Open the full schedule bracket</a>.
                            </span>
                        </p>
                    <?php else: ?>
                        <?= renderStandingsPlacement($section) ?>
                    <?php endif; ?>
                </div>
            </section>
        <?php endforeach; ?>

        <p class="lp-standings-footnote">
            <i class="bi bi-info-circle"></i>
            <span><b>MP</b> matches played · <b>W</b> wins · <b>D</b> draws · <b>L</b> losses · <b>Pts</b> points · <b>PF/PA</b> points for/against · <b>Diff</b> points difference. For set-based sports, <b>SW/SL</b> are sets won/lost and <b>PW/PL</b> are points won/lost.</span>
        </p>
    <?php endif; ?>
</div>
<?php require_once __DIR__.'/includes/footer.php'; ?>
