<?php
require_once __DIR__ . '/config/leagueSession.php';
require_once __DIR__ . '/config/dbconnect.php';
require_once __DIR__ . '/includes/rosterSize.php';
require_once __DIR__ . '/includes/header.php';
if ((int) ($_SESSION['lms_role'] ?? 0) === 2) {
    require_once __DIR__ . '/includes/bar-tr-league.php'; // Team Representative portal chrome
} else {
    require_once __DIR__ . '/includes/bar.php'; // public navbar
}
require_once __DIR__ . '/includes/league_header.php';

// Variables from league_header.php
/** @var int $id */
/** @var mysqli $conn */
/** @var bool $loggin */
?>
<style>
    /* ==========================================================
       Participants — page-specific additions only. Shared tokens,
       panels, stat tiles and empty states live in
       includes/league-view-styles.php
       ========================================================== */

    /* ---------- Summary tiles --------------------------------------------- */
    .pt-summary { margin-bottom:18px; }
    .pt-stat { padding:15px 17px; }
    .pt-stat .lp-stat-value { font-size:1.45rem; }
    .pt-stat .lp-stat-note { margin-top:9px; }
    .lp-stat-icon.is-teams { background:#eef2fb; color:#1e3a68; }
    .lp-stat-icon.is-players { background:#e9f1ff; color:#1d4ed8; }
    .lp-stat-icon.is-average { background:#e8f7f0; color:#0f7a52; }
    .lp-stat-bar.is-teams { background:#09173c; }
    .lp-stat-bar.is-players { background:#2563eb; }
    .lp-stat-bar.is-average { background:#0f7a52; }

    /* ---------- Panel header + toolbar ------------------------------------ */
    .pt-panel-heading { min-width:0; }
    .lp-count { display:inline-flex; align-items:center; gap:6px; padding:3px 10px; border-radius:20px; background:#e8edf7; color:#1a3a7c; font-size:11px; font-weight:600; }
    .pt-toolbar { display:flex; align-items:center; flex-wrap:wrap; gap:10px; margin:0 0 18px; }
    .pt-search { position:relative; flex:1 1 250px; max-width:340px; min-width:0; }
    .pt-search > i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:13px; color:#94a3b8; pointer-events:none; }
    .pt-search input { width:100%; height:40px; padding:8px 38px 8px 34px; border:1.5px solid var(--lp-border); border-radius:9px; background:#f9fafb; font-size:13px; transition:border-color .2s ease, box-shadow .2s ease, background .2s ease; }
    .pt-search input:focus { outline:none; border-color:var(--lp-blue); background:#fff; box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .pt-search input::-webkit-search-cancel-button { -webkit-appearance:none; appearance:none; }
    .pt-search-clear { position:absolute; right:6px; top:50%; display:none; align-items:center; justify-content:center; width:26px; height:26px; padding:0; border:0; border-radius:7px; background:#eef2f7; color:#64748b; font-size:11px; cursor:pointer; transform:translateY(-50%); transition:background .15s ease, color .15s ease; }
    .pt-search-clear:hover { background:#e2e8f0; color:#0f172a; }
    .pt-search.has-value .pt-search-clear { display:inline-flex; }
    .pt-sort { position:relative; flex:0 0 auto; min-width:178px; }
    .pt-sort > i { position:absolute; left:12px; top:50%; transform:translateY(-50%); font-size:13px; color:#94a3b8; pointer-events:none; }
    .pt-sort .form-select { height:40px; padding:8px 32px 8px 33px; border:1.5px solid var(--lp-border); border-radius:9px; background-color:#f9fafb; font-size:12.5px; font-weight:500; }
    .pt-sort .form-select:focus { border-color:var(--lp-blue); box-shadow:0 0 0 3px rgba(37,99,235,.1); }
    .pt-results { margin-left:auto; font-size:12px; color:var(--lp-muted); }
    .pt-results strong { color:var(--lp-navy); font-weight:700; }
    /* ---------- Team grid -------------------------------------------------- */
    .lp-team-grid { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:18px; }
    .lp-team-card { position:relative; display:flex; flex-direction:column; width:100%; height:100%; padding:0; overflow:hidden; border:1px solid var(--lp-border); border-radius:14px; background:#fff; color:inherit; font:inherit; text-align:left; text-decoration:none !important; box-shadow:0 6px 18px rgba(9,23,60,.06); cursor:pointer; transition:transform .2s ease, box-shadow .2s ease, border-color .2s ease; }
    .lp-team-card:hover { transform:translateY(-4px); border-color:#c9dbf5; box-shadow:0 16px 32px rgba(9,23,60,.13); color:inherit; }
    .lp-team-card:focus-visible { outline:3px solid rgba(37,99,235,.45); outline-offset:2px; }
    .lp-team-media { position:relative; display:block; aspect-ratio:16/10; overflow:hidden; background:#e8eef6; }
    .lp-team-media img { position:absolute; inset:0; z-index:0; width:100%; height:100%; object-fit:cover; }
    .lp-team-media.is-fallback { background:#1a3a7c; }
    .lp-team-monogram { position:absolute; inset:0; z-index:1; display:none; align-items:center; justify-content:center; padding-bottom:8px; color:#fff; font-size:2rem; font-weight:800; letter-spacing:.1em; }
    .lp-team-media.is-fallback .lp-team-monogram { display:flex; }
    .lp-team-flag { position:absolute; top:10px; left:10px; z-index:3; display:inline-flex; align-items:center; gap:5px; padding:4px 10px; border:1px solid rgba(255,255,255,.28); border-radius:999px; background:rgba(9,23,60,.55); color:#fff; font-size:10.5px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; }
    .lp-team-flag i { font-size:11px; color:#6ee7b7; }
    .lp-team-view { position:absolute; left:12px; bottom:12px; z-index:3; display:inline-flex; align-items:center; gap:6px; padding:5px 11px; border-radius:999px; background:rgba(255,255,255,.94); color:#0b2559; font-size:11px; font-weight:600; opacity:0; transform:translateY(6px); transition:opacity .2s ease, transform .2s ease; }
    .lp-team-card:hover .lp-team-view, .lp-team-card:focus-visible .lp-team-view { opacity:1; transform:none; }
    .lp-team-body { display:flex; align-items:center; justify-content:space-between; gap:10px; padding:14px 16px 15px; }
    .lp-team-copy { display:flex; flex-direction:column; gap:3px; min-width:0; }
    .lp-team-name { display:-webkit-box; min-width:0; overflow:hidden; -webkit-box-orient:vertical; -webkit-line-clamp:2; line-clamp:2; color:var(--lp-navy); font-size:.86rem; font-weight:700; letter-spacing:.2px; line-height:1.35; text-transform:uppercase; overflow-wrap:anywhere; }
    .lp-team-meta { display:inline-flex; align-items:center; gap:5px; font-size:11.5px; font-weight:500; color:var(--lp-muted); }
    .lp-team-meta i { font-size:11px; color:#94a3b8; }
    .lp-team-go { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; width:30px; height:30px; border-radius:9px; background:#f1f5fb; color:#1e3a68; font-size:13px; transition:background .2s ease, color .2s ease, transform .2s ease; }
    .lp-team-card:hover .lp-team-go { background:var(--lp-navy); color:#fff; transform:translateX(1px); }

    /* ---------- Search, empty and reset states ----------------------------- */
    .pt-empty { padding:46px 24px; }
    .pt-reset { display:inline-flex; align-items:center; gap:7px; margin-top:16px; padding:8px 16px; border:1.5px solid #dbe6f2; border-radius:8px; background:#fff; color:var(--lp-navy); font-size:12.5px; font-weight:600; cursor:pointer; transition:background .18s ease, border-color .18s ease, color .18s ease; }
    .pt-reset:hover { border-color:var(--lp-navy); background:var(--lp-navy); color:#fff; }
    .pt-page [hidden] { display:none !important; }

    /* ---------- Roster modal ----------------------------------------------- */
    .roster-modal { display:none; position:fixed; inset:0; z-index:1050; padding:16px; overflow-y:auto; background:rgba(9,23,60,.58); }
    .roster-modal.show { display:flex; align-items:center; justify-content:center; animation:fadeIn .2s ease; }
    .roster-content { display:flex; flex-direction:column; width:100%; max-width:640px; max-height:min(84vh,760px); overflow:hidden; border-radius:14px; background:#fff; box-shadow:0 30px 70px rgba(9,23,60,.4); animation:slideUp .24s ease; }
    .roster-content:focus { outline:none; }
    .roster-header { display:flex; align-items:center; gap:12px; flex:0 0 auto; padding:16px 20px; background:#09173c; }
    .roster-crest { display:flex; align-items:center; justify-content:center; flex:0 0 auto; width:46px; height:46px; overflow:hidden; border:1px solid rgba(255,255,255,.24); border-radius:12px; background:rgba(255,255,255,.14); color:#fff; font-size:1.15rem; }
    .roster-crest img { width:100%; height:100%; object-fit:cover; }
    .roster-crest.is-fallback { background:#eef2f7; border-color:transparent; color:#09173c; }
    .roster-crest-initials { font-size:.95rem; font-weight:800; letter-spacing:.06em; }
    .roster-heading { flex:1 1 auto; min-width:0; }
    .roster-kicker { display:block; margin-bottom:1px; color:rgba(255,255,255,.6); font-size:9.5px; font-weight:600; letter-spacing:.14em; text-transform:uppercase; }
    .roster-header h5 { margin:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:#fff; font-size:1.05rem; font-weight:700; letter-spacing:.2px; line-height:1.25; }
    .roster-sub { margin:2px 0 0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:rgba(255,255,255,.72); font-size:11.5px; }
    .roster-sub[hidden] { display:none; }
    .close-roster { display:flex; align-items:center; justify-content:center; flex:0 0 auto; width:34px; height:34px; padding:0; border:0; border-radius:10px; background:rgba(255,255,255,.14); color:#fff; cursor:pointer; transition:background .18s ease; }
    .close-roster:hover { background:rgba(255,255,255,.28); }
    .close-roster:focus-visible { outline:2px solid rgba(255,255,255,.85); outline-offset:-4px; }
    .close-roster i { font-size:15px; }

    /* Roster summary strip (position breakdown) */
    .roster-summary { display:flex; align-items:center; flex:0 0 auto; flex-wrap:wrap; gap:8px 12px; padding:10px 20px; border-bottom:1px solid #eef2f7; background:#fbfdff; }
    .roster-summary[hidden] { display:none; }
    .roster-summary-label { flex:0 0 auto; color:#94a3b8; font-size:9.5px; font-weight:700; letter-spacing:.12em; text-transform:uppercase; }
    .roster-summary-chips { display:flex; align-items:center; flex-wrap:wrap; gap:6px; }
    .roster-summary-chip { display:inline-flex; align-items:center; gap:6px; padding:3px 9px; border:1px solid #e2e8f0; border-radius:999px; background:#fff; color:#475569; font-size:10px; font-weight:600; letter-spacing:.4px; text-transform:uppercase; }
    .roster-summary-chip strong { display:inline-flex; align-items:center; justify-content:center; min-width:17px; height:17px; padding:0 4px; border-radius:4px; background:#f1f5f9; color:#09173c; font-size:10px; font-weight:700; }
    .roster-summary-chip.is-more { padding:3px 9px; color:#64748b; background:#f8fafc; }
    .roster-body { flex:1 1 auto; min-height:0; padding:6px 20px; overflow-y:auto; overscroll-behavior:contain; scrollbar-width:thin; scrollbar-color:#c7d4e6 transparent; background:#fff; }
    .roster-body::-webkit-scrollbar { display:block; width:6px; }
    .roster-body::-webkit-scrollbar-track { background:transparent; }
    .roster-body::-webkit-scrollbar-thumb { border-radius:999px; background:#c7d4e6; }
    .roster-footer { display:flex; align-items:center; justify-content:space-between; gap:12px; flex:0 0 auto; padding:11px 20px; border-top:1px solid #eef2f7; background:#fbfdff; font-size:11px; color:var(--lp-muted); }
    .roster-footer[hidden] { display:none; }
    .roster-footer i { margin-right:4px; color:#94a3b8; }
    .roster-done { padding:7px 16px; border:1.5px solid #dbe6f2; border-radius:8px; background:#fff; color:var(--lp-navy); font-size:12px; font-weight:600; white-space:nowrap; cursor:pointer; transition:background .18s ease, border-color .18s ease, color .18s ease; }
    .roster-done:hover { border-color:var(--lp-navy); background:var(--lp-navy); color:#fff; }
    /* ---------- Roster rows (rendered by JS) ------------------------------- */
    .pt-player { display:flex; align-items:center; gap:12px; margin:0 -10px; padding:10px; border-bottom:1px solid #eef2f7; border-radius:8px; transition:background .15s ease; }
    .pt-player:hover { background:#f8fafc; }
    .pt-player:last-child { border-bottom:0; }
    .pt-player-jersey { display:inline-flex; align-items:center; justify-content:center; flex:0 0 auto; min-width:44px; height:40px; padding:0 8px; border:1px solid #dbe7fb; border-radius:10px; background:#eef4ff; color:#1d4ed8; font-size:12px; font-weight:700; font-variant-numeric:tabular-nums; letter-spacing:.2px; }
    .pt-player-copy { flex:1 1 auto; min-width:0; }
    .pt-player-name { display:block; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; color:var(--lp-navy); font-size:.88rem; font-weight:600; line-height:1.35; }
    .pt-player-meta { display:block; margin-top:1px; font-size:11px; color:var(--lp-muted); }
    .player-position { flex:0 0 auto; max-width:130px; overflow:hidden; padding:3px 9px; border:1px solid #e2e8f0; border-radius:999px; background:#f8fafc; color:#334155; font-size:10px; font-weight:600; letter-spacing:.4px; text-overflow:ellipsis; text-transform:uppercase; white-space:nowrap; }

    /* ---------- Roster states (loading / empty / error) -------------------- */
    .roster-loading { display:flex; flex-direction:column; gap:8px; padding:14px 0 16px; }
    .roster-skeleton { display:flex; align-items:center; gap:12px; }
    .roster-skeleton .sk-badge { width:44px; height:40px; border-radius:10px; }
    .roster-skeleton .sk-stack { display:flex; flex:1 1 auto; flex-direction:column; gap:6px; }
    .roster-skeleton .sk-line { width:min(70%,200px); height:11px; border-radius:6px; }
    .roster-skeleton .sk-line.is-short { width:108px; height:9px; }
    .roster-skeleton .sk-pill { width:62px; height:20px; border-radius:999px; }
    .roster-skeleton .sk-badge, .roster-skeleton .sk-line, .roster-skeleton .sk-pill { display:block; background:#eef2f7; animation:ptPulse 1.4s ease-in-out infinite; }
    .roster-state { display:flex; flex-direction:column; align-items:center; gap:3px; padding:34px 16px 30px; text-align:center; }
    .roster-state > i { margin-bottom:7px; font-size:2rem; color:#cbd5e1; }
    .roster-state strong { color:var(--lp-navy); font-size:.95rem; font-weight:700; }
    .roster-state span { max-width:38ch; font-size:12.5px; line-height:1.55; color:var(--lp-muted); }
    .roster-state.is-error > i { color:#fca5a5; }
    .roster-retry { margin-top:13px; padding:8px 16px; border:1.5px solid #fecaca; border-radius:8px; background:#fff5f5; color:#b42318; font-size:12px; font-weight:600; cursor:pointer; transition:background .18s ease, border-color .18s ease, color .18s ease; }
    .roster-retry:hover { border-color:#b42318; background:#b42318; color:#fff; }

    @keyframes fadeIn { from { opacity:0 } to { opacity:1 } }
    @keyframes slideUp { from { transform:translateY(24px); opacity:0 } to { transform:translateY(0); opacity:1 } }
    @keyframes ptPulse { 0%,100% { opacity:.55 } 50% { opacity:1 } }

    /* ---------- Responsive ------------------------------------------------- */
    @media (hover:none) { .lp-team-view { opacity:1; transform:none; } }
    @media (max-width:1199.98px) { .lp-team-grid { grid-template-columns:repeat(3,minmax(0,1fr)); } }
    @media (max-width:991.98px) { .lp-team-grid { grid-template-columns:repeat(2,minmax(0,1fr)); } }
    @media (max-width:575.98px) {
        .lp-team-grid { grid-template-columns:1fr; gap:14px; }
        .pt-search { flex:1 1 100%; max-width:none; }
        .pt-sort { flex:1 1 100%; min-width:0; }
        .pt-sort .form-select { width:100%; }
        .pt-results { margin-left:0; }
        .pt-page .lp-panel-head .lp-count { display:none; }
        .roster-content { max-height:88vh; }
        .roster-header { padding:14px 16px; }
        .roster-kicker { font-size:9px; }
        .roster-summary { padding:9px 16px; gap:6px 10px; }
        .roster-body { padding:6px 16px; }
        .roster-footer { flex-direction:column; align-items:flex-start; padding:11px 16px; }
    }
    @media (max-width:400px) { .lp-team-name { font-size:.8rem; } }
    @media (prefers-reduced-motion: reduce) {
        .roster-modal.show, .roster-content, .roster-skeleton .sk-badge, .roster-skeleton .sk-line, .roster-skeleton .sk-pill { animation:none !important; }
        .lp-team-card, .lp-team-go, .lp-team-view, .pt-player, .close-roster, .roster-done, .roster-retry { transition:none !important; }
    }
</style>

<?php
/* ---------------------------------------------------------------------------
   Approved teams + public roster size in one query.

   COUNT(p.id) mirrors app/organizer/action/getRoster.php (eligible players
   only), so the number printed on a card always matches the roster modal.
   The eligibility filter sits in the JOIN, so a team without eligible players
   is still listed with a zero instead of disappearing.
   --------------------------------------------------------------------------- */
$teams        = [];
$teamLeagueId = (string) $id;
$teamSql = "SELECT t.id, t.team_name, t.team_image, COUNT(p.id) AS eligible_players
            FROM tbl_team t
            LEFT JOIN tbl_player p
                   ON CAST(p.team_id AS UNSIGNED) = t.id
                  AND p.eligibility_status = 'Eligible'
            WHERE t.league_id = ?
              AND LOWER(t.status) = 'approved'
            GROUP BY t.id, t.team_name, t.team_image
            ORDER BY t.team_name ASC";
$teamStmt = $conn->prepare($teamSql);
if ($teamStmt) {
    $teamStmt->bind_param('s', $teamLeagueId);
    $teamStmt->execute();
    $teamResult = $teamStmt->get_result();
    while ($teamRow = $teamResult->fetch_assoc()) {
        $teams[] = [
            'id'      => (int) $teamRow['id'],
            'name'    => (string) $teamRow['team_name'],
            'image'   => (string) $teamRow['team_image'],
            'players' => (int) $teamRow['eligible_players'],
        ];
    }
    $teamStmt->close();
}

$teamCount   = count($teams);
$playerTotal = 0;
foreach ($teams as $team) {
    $playerTotal += $team['players'];
}
$averageRoster = $teamCount > 0 ? (int) round($playerTotal / $teamCount) : 0;

/* Team crest: Cloudinary URL, local upload, or a branded initials tile.
   Returns '' when the team has no usable image so the card can fall back to
   its monogram instead of the generic "no image" artwork. */
$teamCrest = static function (string $raw): string {
    $raw = trim($raw);
    if ($raw === '') {
        return '';
    }
    if (StorageService::isCloudinary($raw)) {
        return $raw;
    }
    $file = basename($raw);
    if ($file !== '' && is_file(__DIR__ . '/assets/uploads/' . $file)) {
        return 'assets/uploads/' . rawurlencode($file);
    }
    return '';
};

/* Two-letter monogram used when a team has no crest. */
$teamInitials = static function (string $name): string {
    $words = preg_split('/\s+/u', trim($name), -1, PREG_SPLIT_NO_EMPTY) ?: [];
    if (!$words) {
        return 'T';
    }
    if (count($words) === 1) {
        return mb_strtoupper(mb_substr($words[0], 0, 2));
    }
    return mb_strtoupper(mb_substr($words[0], 0, 1) . mb_substr($words[1], 0, 1));
};

/* League roster limit — context for the summary tiles. */
$rosterBounds  = normalizeRosterBounds($league_row['min_player'] ?? 0, $league_row['max_player'] ?? 0);
$rosterSummary = rosterSizeLabel($rosterBounds['min'], $rosterBounds['max']);
?>
<div class="container-fluid lp-page pt-page">
    

    <section class="lp-panel">
        <div class="lp-panel-head">
            <div class="pt-panel-heading">
                <h2 class="lp-panel-title"><span class="lp-panel-icon"><i class="bi bi-people-fill"></i></span>Approved teams</h2>
                
            </div>
            <?php if ($teamCount > 0): ?>
                <span class="lp-count"><i class="bi bi-flag"></i><?= $teamCount ?> team<?= $teamCount === 1 ? '' : 's' ?></span>
            <?php endif; ?>
        </div>

        <?php if ($teamCount === 0): ?>
            <div class="lp-empty">
                <i class="bi bi-people"></i>
                <h5>No participants yet</h5>
                <p>Approved teams appear here once the organizer validates their registration.</p>
            </div>
        <?php else: ?>
            <div class="pt-toolbar">
                <div class="pt-search" id="ptSearchWrap">
                    <i class="bi bi-search" aria-hidden="true"></i>
                    <input type="search" id="ptTeamSearch" placeholder="Search a team name" autocomplete="off" aria-label="Search approved teams">
                    <button type="button" class="pt-search-clear" id="ptSearchClear" aria-label="Clear search"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
                </div>
                <div class="pt-sort">
                    <i class="bi bi-sort-down" aria-hidden="true"></i>
                    <select id="ptTeamSort" class="form-select" aria-label="Sort approved teams">
                        <option value="name-asc">Team name (A–Z)</option>
                        <option value="name-desc">Team name (Z–A)</option>
                        <option value="players-desc">Most players first</option>
                        <option value="players-asc">Fewest players first</option>
                    </select>
                </div>
                
            </div>

            <div class="lp-team-grid" id="ptTeamGrid">
                <?php foreach ($teams as $team):
                    $teamNameSafe  = htmlspecialchars($team['name'], ENT_QUOTES, 'UTF-8');
                    $crestUrl      = $teamCrest($team['image']);
                    $hasCrest      = $crestUrl !== '';
                    $crestSafe     = htmlspecialchars($crestUrl, ENT_QUOTES, 'UTF-8');
                    $monogramSafe  = htmlspecialchars($teamInitials($team['name']), ENT_QUOTES, 'UTF-8');
                    $playerLabel   = $team['players'] . ' player' . ($team['players'] === 1 ? '' : 's');
                ?>
                    <button type="button" class="lp-team-card"
                            data-team-id="<?= $team['id'] ?>"
                            data-team-name="<?= $teamNameSafe ?>"
                            data-team-image="<?= $hasCrest ? $crestSafe : '' ?>"
                            data-team-initials="<?= $monogramSafe ?>"
                            data-players="<?= $team['players'] ?>"
                            title="View the roster of <?= $teamNameSafe ?>"
                            aria-haspopup="dialog">
                        <span class="lp-team-media<?= $hasCrest ? '' : ' is-fallback' ?>">
                            <?php if ($hasCrest): ?>
                                <img src="<?= $crestSafe ?>" alt="" loading="lazy" onerror="this.style.display='none';this.parentNode.classList.add('is-fallback');">
                            <?php endif; ?>
                            <span class="lp-team-monogram" aria-hidden="true"><?= $monogramSafe ?></span>
                            <span class="lp-team-flag"><i class="bi bi-patch-check-fill" aria-hidden="true"></i>Approved</span>
                            <span class="lp-team-view"><i class="bi bi-list-ul" aria-hidden="true"></i>View roster</span>
                        </span>
                        <span class="lp-team-body">
                            <span class="lp-team-copy">
                                <span class="lp-team-name"><?= $teamNameSafe ?></span>
                                <span class="lp-team-meta"><i class="bi bi-person-fill" aria-hidden="true"></i><?= htmlspecialchars($playerLabel, ENT_QUOTES, 'UTF-8') ?></span>
                            </span>
                            <span class="lp-team-go" aria-hidden="true"><i class="bi bi-arrow-up-right"></i></span>
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="lp-empty pt-empty" id="ptNoMatch" hidden>
                <i class="bi bi-search"></i>
                <h5>No teams match your search</h5>
                <p>Try a different team name, or reset the search to see all approved teams.</p>
                <button type="button" class="pt-reset" id="ptResetSearch"><i class="bi bi-arrow-counterclockwise" aria-hidden="true"></i>Reset search</button>
            </div>
        <?php endif; ?>
    </section>
</div>
<!-- Roster modal -->
<div id="rosterModal" class="roster-modal" role="dialog" aria-modal="true" aria-hidden="true" aria-labelledby="rosterTeamName">
    <div class="roster-content" id="rosterContent" tabindex="-1">
        <div class="roster-header">
            <span class="roster-crest" id="rosterCrest" aria-hidden="true"><i class="bi bi-people-fill"></i></span>
            <div class="roster-heading">
                
                <h5 id="rosterTeamName">Team</h5>
                <p class="roster-sub" id="rosterSubtitle">Loading official roster…</p>
            </div>
            <button type="button" class="close-roster" id="rosterClose" aria-label="Close roster"><i class="bi bi-x-lg" aria-hidden="true"></i></button>
        </div>
    
        <div class="roster-body" id="rosterBody" aria-live="polite">
            <div class="roster-loading" aria-hidden="true">
                <div class="roster-skeleton">
                    <span class="sk-badge"></span>
                    <span class="sk-stack"><span class="sk-line"></span><span class="sk-line is-short"></span></span>
                    <span class="sk-pill"></span>
                </div>
            </div>
            <span class="visually-hidden">Loading roster…</span>
        </div>
        
    </div>
</div>

<script>
(function () {
    'use strict';

    var grid = document.getElementById('ptTeamGrid');
    var searchWrap = document.getElementById('ptSearchWrap');
    var searchInput = document.getElementById('ptTeamSearch');
    var searchClear = document.getElementById('ptSearchClear');
    var sortSelect = document.getElementById('ptTeamSort');
    var resultLabel = document.getElementById('ptResultCount');
    var noMatch = document.getElementById('ptNoMatch');
    var resetSearch = document.getElementById('ptResetSearch');

    var cards = grid ? Array.prototype.slice.call(grid.querySelectorAll('.lp-team-card')) : [];

    /* ---------- Helpers ------------------------------------------------ */
    function escapeHtml(value) {
        return String(value === null || value === undefined ? '' : value).replace(/[&<>"']/g, function (character) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[character];
        });
    }

    function normalize(value) {
        return String(value || '').toLowerCase().trim();
    }

    function plural(count, singular, pluralWord) {
        return count + ' ' + (count === 1 ? singular : pluralWord);
    }

    /* ---------- Search + sort ------------------------------------------ */
    function applyFilter() {
        if (!grid) return;

        var term = normalize(searchInput ? searchInput.value : '');
        var visible = 0;

        cards.forEach(function (card) {
            var matches = term === '' || normalize(card.getAttribute('data-team-name')).indexOf(term) !== -1;
            card.hidden = !matches;
            if (matches) visible += 1;
        });

        if (searchWrap) searchWrap.classList.toggle('has-value', term !== '');
        if (noMatch) noMatch.hidden = visible !== 0;
        if (resultLabel) resultLabel.textContent = plural(visible, 'team', 'teams') + (term !== '' ? ' found' : '');
    }

    function sortCards() {
        if (!grid || !sortSelect) return;

        var mode = sortSelect.value;

        cards.slice().sort(function (a, b) {
            var nameA = normalize(a.getAttribute('data-team-name'));
            var nameB = normalize(b.getAttribute('data-team-name'));
            var playersA = parseInt(a.getAttribute('data-players'), 10) || 0;
            var playersB = parseInt(b.getAttribute('data-players'), 10) || 0;

            if (mode === 'name-desc') return nameB.localeCompare(nameA);
            if (mode === 'players-desc') return (playersB - playersA) || nameA.localeCompare(nameB);
            if (mode === 'players-asc') return (playersA - playersB) || nameA.localeCompare(nameB);
            return nameA.localeCompare(nameB);
        }).forEach(function (card) {
            grid.appendChild(card);
        });
    }

    function clearSearch() {
        if (searchInput) {
            searchInput.value = '';
        }
        applyFilter();
        if (searchInput) {
            searchInput.focus();
        }
    }

    if (searchInput) searchInput.addEventListener('input', applyFilter);
    if (searchClear) searchClear.addEventListener('click', clearSearch);
    if (resetSearch) resetSearch.addEventListener('click', clearSearch);
    if (sortSelect) sortSelect.addEventListener('change', sortCards);
    applyFilter();

    /* ---------- Roster modal ------------------------------------------- */
    var modal = document.getElementById('rosterModal');
    if (!modal) return;

    var modalBody = document.getElementById('rosterBody');
    var modalCrest = document.getElementById('rosterCrest');
    var modalTitle = document.getElementById('rosterTeamName');
    var modalSubtitle = document.getElementById('rosterSubtitle');
    var modalClose = document.getElementById('rosterClose');
    var modalDone = document.getElementById('rosterDone');
    var modalFooter = document.getElementById('rosterFooter');
    var modalContent = document.getElementById('rosterContent');
    var rosterSummary = document.getElementById('rosterSummary');
    var rosterSummaryChips = document.getElementById('rosterSummaryChips');

    var activeTeamId = 0;
    var activeTeamName = '';
    var requestToken = 0;
    var lastFocused = null;
    var previousOverflow = '';
    var previousHtmlOverflow = '';

    function jerseyNumber(value) {
        var number = parseInt(value, 10);
        return isNaN(number) ? Number.MAX_SAFE_INTEGER : number;
    }

    function lockScroll(lock) {
        if (lock) {
            previousOverflow = document.body.style.overflow;
            previousHtmlOverflow = document.documentElement.style.overflow;
            document.body.style.overflow = 'hidden';
            document.documentElement.style.overflow = 'hidden';
        } else {
            document.body.style.overflow = previousOverflow;
            document.documentElement.style.overflow = previousHtmlOverflow;
        }
    }

    function setCrest(imageUrl, initials) {
        if (!modalCrest) return;

        modalCrest.classList.toggle('is-fallback', !imageUrl);
        modalCrest.innerHTML = '';

        if (!imageUrl) {
            modalCrest.innerHTML = '<span class="roster-crest-initials">' + escapeHtml(initials) + '</span>';
            return;
        }

        var image = document.createElement('img');
        image.src = imageUrl;
        image.alt = '';
        image.onerror = function () {
            modalCrest.classList.add('is-fallback');
            modalCrest.innerHTML = '<span class="roster-crest-initials">' + escapeHtml(initials) + '</span>';
        };
        modalCrest.appendChild(image);
    }

    function setRosterStatus(text) {
        if (!modalSubtitle) return;
        modalSubtitle.textContent = text;
        modalSubtitle.hidden = text === '';
    }

    function renderSkeleton() {
        var row = '<div class="roster-skeleton" aria-hidden="true">'
            + '<span class="sk-badge"></span>'
            + '<span class="sk-stack"><span class="sk-line"></span><span class="sk-line is-short"></span></span>'
            + '<span class="sk-pill"></span>'
            + '</div>';

        modalBody.innerHTML = '<div class="roster-loading">' + row + row + row + row + '</div>'
            + '<span class="visually-hidden">Loading roster…</span>';
        modalBody.setAttribute('aria-busy', 'true');

        if (rosterSummary) rosterSummary.hidden = true;
        if (rosterSummaryChips) rosterSummaryChips.innerHTML = '';
        if (modalFooter) modalFooter.hidden = true;
    }

    function showState(kind, title, message) {
        var isError = kind === 'error';
        modalBody.innerHTML = '<div class="roster-state' + (isError ? ' is-error' : '') + '">'
            + '<i class="bi ' + (isError ? 'bi-exclamation-triangle' : 'bi-person-x') + '" aria-hidden="true"></i>'
            + '<strong>' + escapeHtml(title) + '</strong>'
            + '<span>' + escapeHtml(message) + '</span>'
            + (isError ? '<button type="button" class="roster-retry" data-roster-retry="1"><i class="bi bi-arrow-clockwise" aria-hidden="true"></i>Try again</button>' : '')
            + '</div>';
        modalBody.setAttribute('aria-busy', 'false');

        if (rosterSummary) rosterSummary.hidden = true;

        if (modalFooter) {
            modalFooter.hidden = isError;
        }
    }

    /* ---------- Roster rendering --------------------------------------- */
    function renderPositionSummary(players) {
        if (!rosterSummary || !rosterSummaryChips) return;

        var counts = {};
        players.forEach(function (player) {
            var role = String(player.position || '').trim();
            var key = role !== '' ? role : 'Player';
            counts[key] = (counts[key] || 0) + 1;
        });

        var roles = Object.keys(counts).sort(function (a, b) {
            return (counts[b] - counts[a]) || a.localeCompare(b);
        });
        var visible = roles.slice(0, 6);
        var chips = visible.map(function (role) {
            return '<span class="roster-summary-chip">' + escapeHtml(role) + '<strong>' + counts[role] + '</strong></span>';
        });

        if (roles.length > visible.length) {
            chips.push('<span class="roster-summary-chip is-more">+' + (roles.length - visible.length) + '</span>');
        }

        rosterSummaryChips.innerHTML = chips.join('');
        rosterSummary.hidden = false;
    }

    function renderRoster(players) {
        if (!players.length) {
            setRosterStatus('No eligible players listed');
            showState('empty', 'No players listed', 'This team does not have any eligible players on its public roster yet.');
            return;
        }

        setRosterStatus('');
        renderPositionSummary(players);

        var sorted = players.slice().sort(function (a, b) {
            return (jerseyNumber(a.jersey_number) - jerseyNumber(b.jersey_number))
                || String(a.player_name || '').localeCompare(String(b.player_name || ''));
        });

        var html = '';
        sorted.forEach(function (player) {
            var jerseyRaw = player.jersey_number === null || player.jersey_number === undefined
                ? '' : String(player.jersey_number).trim();
            var jersey = jerseyRaw === '' ? '–' : escapeHtml(jerseyRaw);
            var role = String(player.position || '').trim();
            var gender = String(player.gender || '').trim();
            var name = String(player.player_name || 'Unnamed player');

            html += '<div class="pt-player" role="listitem">'
                + '<span class="pt-player-jersey"><span class="visually-hidden">Jersey </span>#' + jersey + '</span>'
                + '<span class="pt-player-copy">'
                + '<span class="pt-player-name" title="' + escapeHtml(name) + '">' + escapeHtml(name) + '</span>'
                + (gender !== '' ? '<span class="pt-player-meta">' + escapeHtml(gender) + '</span>' : '')
                + '</span>'
                + '<span class="player-position">' + escapeHtml(role !== '' ? role : 'Player') + '</span>'
                + '</div>';
        });

        modalBody.innerHTML = '<div class="roster-list" role="list">' + html + '</div>';
        modalBody.setAttribute('aria-busy', 'false');

        if (modalFooter) modalFooter.hidden = false;
    }

    /* ---------- Roster loading ----------------------------------------- */
    function loadRoster(teamId) {
        var token = ++requestToken;
        renderSkeleton();
        setRosterStatus('Loading official roster…');

        fetch('app/organizer/action/getRoster.php', {
            method: 'POST',
            cache: 'no-store',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: 'team_id=' + encodeURIComponent(teamId)
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (token !== requestToken) return;
            if (!data || data.status !== 200) {
                showState('error', 'Roster unavailable', (data && data.msg) || 'The roster could not be loaded right now.');
                setRosterStatus('Roster unavailable');
                return;
            }
            renderRoster(Array.isArray(data.players) ? data.players : []);
        }).catch(function () {
            if (token !== requestToken) return;
            showState('error', 'Could not load the roster', 'Please check your connection and try again.');
            setRosterStatus('Roster unavailable');
        });
    }

    function openRoster(card) {
        activeTeamId = parseInt(card.getAttribute('data-team-id'), 10) || 0;
        activeTeamName = card.getAttribute('data-team-name') || 'Team';
        lastFocused = document.activeElement;

        modalTitle.textContent = activeTeamName;
        setRosterStatus('Loading official roster…');
        setCrest(card.getAttribute('data-team-image') || '', card.getAttribute('data-team-initials') || '');
        modalBody.innerHTML = '';
        modalBody.scrollTop = 0;
        modalBody.setAttribute('aria-busy', 'true');

        if (rosterSummary) rosterSummary.hidden = true;
        if (modalFooter) modalFooter.hidden = true;

        modal.classList.add('show');
        modal.setAttribute('aria-hidden', 'false');
        lockScroll(true);
        if (modalContent) {
            modalContent.focus();
        } else if (modalClose) {
            modalClose.focus();
        }

        loadRoster(activeTeamId);
    }

    function closeRoster() {
        if (!modal.classList.contains('show')) return;

        requestToken += 1;
        modal.classList.remove('show');
        lockScroll(false);

        if (lastFocused && typeof lastFocused.focus === 'function') {
            lastFocused.focus();
        }
        lastFocused = null;
        modal.setAttribute('aria-hidden', 'true');
    }

    function trapFocus(event) {
        var focusable = Array.prototype.filter.call(
            modal.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex="-1"])'),
            function (element) {
                return !element.disabled && element.getClientRects().length > 0;
            }
        );

        if (!focusable.length) return;

        var first = focusable[0];
        var last = focusable[focusable.length - 1];

        if (document.activeElement === modalContent) {
            event.preventDefault();
            (event.shiftKey ? last : first).focus();
            return;
        }

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    /* ---------- Wiring -------------------------------------------------- */
    cards.forEach(function (card) {
        card.addEventListener('click', function () {
            openRoster(card);
        });
    });

    if (modalClose) modalClose.addEventListener('click', closeRoster);
    if (modalDone) modalDone.addEventListener('click', closeRoster);

    modal.addEventListener('click', function (event) {
        if (event.target === modal) {
            closeRoster();
        }
    });

    modalBody.addEventListener('click', function (event) {
        var retry = event.target.closest ? event.target.closest('[data-roster-retry]') : null;
        if (retry) {
            loadRoster(activeTeamId);
        }
    });

    document.addEventListener('keydown', function (event) {
        if (!modal.classList.contains('show')) return;

        if (event.key === 'Escape') {
            event.preventDefault();
            closeRoster();
        } else if (event.key === 'Tab') {
            trapFocus(event);
        }
    });
})();
</script>
<?php include 'includes/footer.php'; ?>
