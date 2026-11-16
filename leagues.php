<?php

session_start();
require_once __DIR__ . '/config/dbconnect.php';
require_once __DIR__ . '/config/leagueStatus.php';
require_once __DIR__ . '/includes/storageService.php';

if ((int)($_SESSION['lms_role'] ?? 0) === 2 && !empty($_SESSION['lms_id'])) {
    $query = $_SERVER['QUERY_STRING'] ?? '';
    header('Location: app/tr/browse_leagues.php'.($query !== '' ? '?'.$query : ''));
    exit;
}

$time = strtotime("-10 year", time());
$date = date("Y-m-d", $time);
$filters=['search'=>trim($_GET['search']??''),'sport'=>trim($_GET['sport']??''),'status'=>trim($_GET['status']??'')];
if(!in_array($filters['status'],['','Upcoming','Open','Ongoing','Completed'],true))$filters['status']='';
$sportOptions=[];$sportResult=$conn->query("SELECT DISTINCT sport FROM tbl_league WHERE publication_status='Published' AND (is_archived=0 OR is_archived IS NULL) AND sport<>'' ORDER BY sport");if($sportResult)while($row=$sportResult->fetch_assoc())$sportOptions[]=$row['sport'];
$where=["l.publication_status='Published'","(l.is_archived=0 OR l.is_archived IS NULL)"];$params=[];$types='';
if($filters['search']!==''){$where[]='(l.league_name LIKE ? OR l.sport LIKE ?)';$searchTerm='%'.$filters['search'].'%';$params[]=$searchTerm;$params[]=$searchTerm;$types.='ss';}
if($filters['sport']!==''){$where[]='l.sport=?';$params[]=$filters['sport'];$types.='s';}
$leagueSql="SELECT l.*,(SELECT COUNT(*) FROM tbl_team t WHERE t.league_id=l.id AND LOWER(t.status)='approved') current_teams FROM tbl_league l WHERE ".implode(' AND ',$where).' ORDER BY l.start_date DESC';
$leagueStmt=$conn->prepare($leagueSql);if($params)$leagueStmt->bind_param($types,...$params);$leagueStmt->execute();$leagueResult=$leagueStmt->get_result();$filteredLeagues=[];while($league=$leagueResult->fetch_assoc()){$league['live_status']=getLeagueStatus($league);if($filters['status']===''||$filters['status']===$league['live_status'])$filteredLeagues[]=$league;}$leagueStmt->close();
include 'includes/header.php'; ?>
<link rel="stylesheet" href="assets/vendor/font-awesome/6.4.0/css/all.min.css" />
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<style rel="stylesheet">
    .cols {
        border-radius: 15px;
        box-shadow: 0 8px 24px rgba(16, 24, 40, .07);
        border: 1px solid #e1e7ef;
        border-radius: 20px;
        min-height: 560px;
    }

    .cards {
        border-radius: 15px;
        box-shadow: 1px 5px 5px 1px rgba(0, 0, 0, 0.2);
    }
    .cards {
        border-radius: 12px;
        overflow: hidden;
        transition: transform 200ms ease, box-shadow 200ms ease;
        border: 1px solid rgba(13,110,253,0.06);
    }
    .cards:hover {
        transform: translateY(-6px);
        box-shadow: 0 10px 20px rgba(13,110,253,0.08);
    }
    .cards img {
        height: 140px;
        width: 100%;
        display: block;
        object-fit: cover;
    }
    /* make cards use column layout so actions stick to bottom */
    .cards {
        display: flex;
        flex-direction: column;
        height: 100%;
    }
    .cards .card-body {
        padding: 14px;
        background: #ffffff;
        display: flex;
        flex-direction: column;
        gap: 12px;
        flex: 1 1 auto;
    }
    .cards .card-header { padding: 0; }
    /* status badge: white pill with primary colored text (matches header buttons) */
    .league-badge-status {
        position: absolute;
        top: 12px;
        left: 12px;
        padding: 6px 10px;
        font-weight: 700;
        color: #fff;
        border-radius: 18px;
        font-size: 0.8rem;
        box-shadow: 0 3px 8px rgba(0,0,0,0.12);
    }
    .badge-slots {
        background: rgba(255,193,7,0.95);
        color: #111;
        font-weight: 600;
        border-radius: 12px;
        padding: 4px 8px;
        font-size: 0.75rem;
    }
    .cards .btn {
        border-radius: 20px;
        padding: 8px 18px;
    }
    .btn-light {
        border: 1px solid #0d6efd;
        color: #0d6efd;
        background: #fff;
    }
    .btn-primary {
        background: linear-gradient(90deg,#0d6efd,#0069f7);
        border: none;
    }

    /* ensure action area stays at bottom */
    .cards .card-body .text-center{ margin-top: auto; }

    /* status color variants */
    .status-pill.league-status-open { background: #28c76f; }
    .status-pill.league-status-ongoing { background: #0069f7; }
    .status-pill.league-status-upcoming { background: #6f42c1; }
    .status-pill.league-status-ended { background: #6c757d; }

    /* title truncation */
    .card-title-compact {
        font-weight:700;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .meta-row { font-size: 0.9rem; color: #444; display:block; }
    .meta-line { display:flex; gap:8px; align-items:center; color:#6c757d; margin-bottom:6px }
    .meta-line i { color:#6c757d }

    .btn-view { border-radius:8px; padding:8px 10px; text-transform:uppercase; font-weight:600; font-size:0.85rem }

    /* ===== Featured Card (matches index.php) ===== */
    .featured-card {
        position: relative;
        height: 100%;
        overflow: hidden;
        border: 1px solid #dfe6ef !important;
        border-radius: 16px !important;
        background: #fff;
        box-shadow: 0 6px 18px rgba(16, 24, 40, .07) !important;
        transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease;
        cursor: pointer;
    }
    .featured-card:hover {
        transform: translateY(-5px);
        border-color: #bfd0e7 !important;
        box-shadow: 0 14px 30px rgba(16, 24, 40, .13) !important;
    }
    .featured-card-link {
        position: absolute;
        inset: 0;
        z-index: 1;
        text-decoration: none;
    }
    .featured-card-link::after {
        content: "";
        position: absolute;
        inset: 0;
    }

    .featured-image {
        position: relative;
        height: 132px;
        overflow: hidden;
        background: #dfe8f2;
    }
    .featured-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
        transition: transform .35s;
    }
    .featured-card:hover .featured-image img { transform: scale(1.04); }

    .status-pill {
        position: absolute;
        left: 10px;
        top: 10px;
        padding: 6px 10px;
        border-radius: 999px;
        background: rgba(9, 23, 60, .88);
        color: #fff;
        font-size: .62rem;
        font-weight: 700;
        backdrop-filter: blur(6px);
    }

    .featured-body { padding: 15px 16px 17px; }

    .featured-sport {
        color: #2b69a4;
        font-size: .68rem;
        font-weight: 800;
        text-transform: uppercase;
        letter-spacing: .8px;
    }

    .featured-title {
        margin: 4px 0 10px;
        color: #172033;
        font-size: 1rem;
        font-weight: 750;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .featured-meta {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 8px 10px;
        color: #657186;
        font-size: .7rem;
    }
    .featured-meta span { display: flex; align-items: center; gap: 7px; min-width: 0; }
    .featured-meta i { flex: 0 0 auto; color: #2b69a4; }

    /* Exactly 4 cards per row on desktop panels (auto-fill cannot be used here: it
       counts columns from a track's definite max, which kept collapsing rows to 3). */
    .league-card-grid {
        display: grid;
        grid-template-columns: repeat(4, minmax(0, 1fr));
        align-content: start;
        gap: 18px;
        margin: 0 !important;
        padding: 0 !important; /* the .cols wrapper already provides p-4 spacing */
    }
    .league-card-grid > [class*="col-"] {
        width: 100%;
        max-width: none;
        padding: 0;
    }

    .browse-filter { padding:0;border:1px solid #d8e1eb;border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 4px 18px rgba(16,24,40,.05);transition:box-shadow .25s ease; }
    .browse-filter:hover { box-shadow:0 6px 24px rgba(16,24,40,.09); }
    .filter-body { display:flex;flex-wrap:wrap;align-items:flex-end;gap:16px;padding:18px; }
    .filter-field { flex:1 1 180px;min-width:0;margin-bottom:0; }
    .filter-field:has(#leagueSearch) { flex-grow:2; }
    .filter-field label { display:flex;align-items:center;gap:6px;margin-bottom:6px;color:#4b5c72;font-size:.72rem;font-weight:600;letter-spacing:.6px;text-transform:uppercase; }
    .filter-field label i { color:#8896a7;font-size:.8rem; }
    .filter-field .input-group { overflow:hidden;border:1.5px solid #d8e1eb;border-radius:10px;background:#f8fafc;transition:border-color .2s,box-shadow .2s; }
    .filter-field .input-group:focus-within { border-color:#1a3a7c;background:#fff;box-shadow:0 0 0 3px rgba(26,58,124,.1); }
    .filter-field .input-group-text { display:flex;min-width:38px;justify-content:center;padding:0 12px;border:0;border-right:1.5px solid #e4eaf1;background:transparent;color:#8896a7;font-size:.85rem; }
    .filter-field .input-group .form-control,.filter-field .input-group .form-select { padding:10px 12px;border:0;background:transparent;color:#1e293b!important;font-size:.82rem;box-shadow:none!important; }
    .filter-field .form-select { cursor:pointer; }
    .filter-field .form-control::placeholder { color:#a0aec0; }
    .filter-mobile-toggle { display:none;width:100%;padding:12px 18px;border:0;border-radius:6px;margin-bottom:12px;background:linear-gradient(135deg,#09173c,#1a3a7c);color:#fff;font-size:.85rem;font-weight:600;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 14px rgba(9,23,60,.15); }
    .filter-mobile-toggle:hover { color:#fff; }
    .filter-count-badge { flex:0 0 auto;padding:4px 10px;border:1px solid rgba(255,255,255,.2);border-radius:999px;background:rgba(255,255,255,.16);color:#fff;font-size:.7rem;font-weight:700; }
    .filter-count-badge.has-filters { border-color:rgba(255,193,7,.35);background:rgba(255,193,7,.25); }
    .filter-mobile-toggle .toggle-icon { transition:transform .3s; }
    .filter-mobile-toggle[aria-expanded="true"] .toggle-icon { transform:rotate(180deg); }
    .league-result-count { color:#667085;font-size:.85rem; }
    .league-result-count.has-error { color:#b42318; }
    .cols:has(.league-empty) { display:flex;flex-direction:column; }
    .league-card-grid:has(.league-empty) { flex:1;align-content:center; }
    .league-card-grid > .col-12:has(.league-empty) { grid-column:1/-1; }
    .league-empty { min-height:300px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;color:#667085; }
    @media(max-width:1199.98px){.league-card-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}
    @media(max-width:991.98px){.cols{min-height:520px}.league-card-grid{grid-template-columns:repeat(auto-fill,minmax(min(100%,210px),1fr))}.filter-mobile-toggle{display:inline-flex}.filter-collapse{display:none}.filter-collapse.show{display:block}}
    @media(max-width:575.98px){.filter-field{flex:1 1 100%}}
    @media(max-width:575.98px){.league-card-grid{grid-template-columns:minmax(0,1fr);padding:16px !important}}
    @media(max-width:767.98px){
        aside[aria-label="League filters"]{margin-bottom:8px !important}
        .filter-mobile-toggle{margin-bottom:0}
        .filter-mobile-toggle[aria-expanded="true"]{margin-bottom:8px}
        .league-result-count:empty{display:none}
        #leagueResults{padding-top:12px !important}
        #leagueResults .league-card-grid{padding-top:0 !important}
    }
    @media(max-width:575.98px){
        .public-leagues-page{padding-top:72px !important}
        .public-leagues-page{padding-right:8px !important;padding-left:8px !important}
        .public-leagues-page > .card > .card-header{padding:8px 8px 4px}
        .public-leagues-page > .card > .card-header h2{margin-bottom:0}
        .public-leagues-page > .card > .card-body{padding:8px}
        .public-leagues-page .cols{padding:12px !important}
        .league-card-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:12px;padding:0 !important}
        .featured-meta span:nth-child(n+3){grid-column:1/-1;white-space:nowrap}
    }
</style>
<?php
include 'includes/bar.php';

?>

<div class="container-fluid public-leagues-page" style="padding-top: 80px;">
    <div class="card bg-transparent border-0">
        <div class="card-header bg-transparent border-0">
            <h2 class="font-weight-bolder">Browse Leagues</h2>
        </div>
        <div class="card-body">
            <?php $activeFilterCount=(int)($filters['search']!=='')+(int)($filters['sport']!=='')+(int)($filters['status']!==''); ?>
            <aside class="mb-4" aria-label="League filters">
                <button class="filter-mobile-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
                    <i class="bi bi-funnel-fill"></i> Filters<?php if($activeFilterCount>0): ?> <span class="filter-count-badge has-filters ms-1"><?= $activeFilterCount ?> active</span><?php endif; ?>
                    <i class="bi bi-chevron-down toggle-icon ms-auto"></i>
                </button>
                <div class="filter-collapse" id="filterCollapse">
                    <form class="browse-filter" method="get" action="leagues.php">
                        <div class="filter-body">
                            <div class="filter-field">
                                <label for="leagueSearch"><i class="bi bi-search"></i>Search</label>
                                <div class="input-group"><span class="input-group-text"><i class="bi bi-search"></i></span><input id="leagueSearch" name="search" class="form-control" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Search league name..."></div>
                            </div>
                            <div class="filter-field">
                                <label for="sportFilter"><i class="bi bi-trophy"></i>Sport</label>
                                <div class="input-group"><span class="input-group-text"><i class="bi bi-trophy"></i></span><select id="sportFilter" name="sport" class="form-select"><option value="">All Sports</option><?php foreach($sportOptions as $sportOption): ?><option value="<?= htmlspecialchars($sportOption) ?>" <?= $filters['sport']===$sportOption?'selected':'' ?>><?= htmlspecialchars($sportOption) ?></option><?php endforeach; ?></select></div>
                            </div>
                            <div class="filter-field">
                                <label for="statusFilter"><i class="bi bi-activity"></i>Status</label>
                                <div class="input-group"><span class="input-group-text"><i class="bi bi-activity"></i></span><select id="statusFilter" name="status" class="form-select"><option value="">All Statuses</option><?php foreach(['Upcoming','Open','Ongoing','Completed'] as $statusOption): ?><option value="<?= htmlspecialchars($statusOption) ?>" <?= $filters['status']===$statusOption?'selected':'' ?>><?= $statusOption ?></option><?php endforeach; ?></select></div>
                            </div>
                        </div>
                    </form>
                </div>
            </aside>
            <div class="league-result-count" id="leagueResultsStatus" role="status" aria-live="polite" aria-atomic="true"></div>
            <section aria-label="Available leagues">
                    <div class="p-4 cols" id="leagueResults" aria-busy="false">
                        
                        <div class="league-card-grid">
                            <?php if($filteredLeagues): foreach ($filteredLeagues as $league_row) {
                                $start_date = date("F j, Y", strtotime($league_row['start_date']));
                                $end_date = date("F j, Y", strtotime($league_row['end_date']));
                                $status = $league_row['live_status'];
                                $currentTeams = (int)$league_row['current_teams'];
                                $slotsLeft = max(0, intval($league_row['max_team']) - intval($currentTeams));
                            ?>
                                <div class="col-xl-4 col-lg-6 col-md-6 col-sm-6">
                                    <article class="featured-card">
                                        <a href="team_information.php?id=<?= (int)$league_row['id'] ?>" class="featured-card-link" aria-label="<?= htmlspecialchars($league_row['league_name']) ?>"></a>
                                        <div class="featured-image">
                                            <?php
                                                $imageName=(string)($league_row['league_image']??'');
                                                if (StorageService::isCloudinary($imageName)) {
                                                    $imgSrc=$imageName;
                                                } else {
                                                    $imgSrc='assets/uploads/'.basename($imageName);
                                                    if ($imageName==='' || !is_file(__DIR__.'/'.$imgSrc)) {
                                                        $imgSrc = 'assets/images/default-league.svg';
                                                    }
                                                }
                                            ?>
                                            <img src="<?= htmlspecialchars($imgSrc,ENT_QUOTES,'UTF-8') ?>" alt="<?= htmlspecialchars($league_row['league_name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='assets/images/default-league.svg';">
                                            <?php
                                                $statusLabel = getLeagueStatusLabel($status);
                                                $statusClass = getLeagueStatusClass($status);
                                            ?>
                                            <span class="status-pill <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars($statusLabel) ?></span>
                                        </div>
                                        <div class="featured-body">
                                            <div class="featured-sport"><?= htmlspecialchars($league_row['sport'] ?? '') ?></div>
                                            <h3 class="featured-title"><?= htmlspecialchars($league_row['league_name']) ?></h3>
                                            <div class="featured-meta">
                                                <span><i class="bi bi-diagram-3"></i><?= htmlspecialchars($league_row['division'] ?? '') ?></span>
                                                <span><i class="bi bi-people"></i><?= htmlspecialchars($league_row['category'] ?? '') ?></span>
                                                <span><i class="bi bi-calendar3"></i><?= date('M d, Y', strtotime($league_row['start_date'])) ?></span>
                                                <span><i class="bi bi-flag"></i><?= date('M d, Y', strtotime($league_row['end_date'])) ?></span>
                                            </div>
                                        </div>
                                    </article>
                                </div>
                            <?php } else: ?><div class="col-12"><div class="league-empty"><i class="bi bi-search fs-1 mb-2"></i><h5>No matching leagues</h5><p>Try changing or resetting your filters.</p><a href="leagues.php" class="btn btn-outline-primary">Reset Filters</a></div></div><?php endif; ?>

                        </div>
                    </div>
            </section>
        </div>
    </div>
</div>
<script>
    (function () {
        var form = document.querySelector('.browse-filter');
        var search = document.getElementById('leagueSearch');
        var results = document.querySelector('.league-card-grid');
        var status = document.getElementById('leagueResultsStatus');
        var timer = null;
        var activeController = null;
        var requestId = 0;

        function applyFilters() {
            clearTimeout(timer);
            var currentRequestId = ++requestId;
            if (activeController) activeController.abort();

            var controller = new AbortController();
            activeController = controller;
            var requestUrl = new URL(form.action, window.location.href);
            requestUrl.search = new URLSearchParams(new FormData(form)).toString();
            var resultsContainer = document.getElementById('leagueResults');
            resultsContainer.setAttribute('aria-busy', 'true');
            status.classList.remove('has-error');
            status.textContent = 'Updating league results...';

            fetch(requestUrl.toString(), { signal: controller.signal })
                .then(function (response) {
                    if (!response.ok) throw new Error('League search request failed.');
                    return response.text();
                })
                .then(function (html) {
                    if (currentRequestId !== requestId) return;
                    var parsedPage = new DOMParser().parseFromString(html, 'text/html');
                    var updatedResults = parsedPage.querySelector('.league-card-grid');
                    if (!updatedResults) throw new Error('League results were not found in the response.');

                    results.innerHTML = updatedResults.innerHTML;
                    window.history.replaceState(window.history.state, '', requestUrl.pathname + requestUrl.search);
                    var count = results.querySelectorAll('.featured-card').length;
                    status.textContent = count + (count === 1 ? ' league found.' : ' leagues found.');
                })
                .catch(function (error) {
                    if (error.name === 'AbortError' || currentRequestId !== requestId) return;
                    status.classList.add('has-error');
                    status.textContent = 'Unable to update league results. Please try again.';
                })
                .finally(function () {
                    if (currentRequestId !== requestId) return;
                    resultsContainer.setAttribute('aria-busy', 'false');
                    activeController = null;
                });
        }

        form.querySelectorAll('select').forEach(function (select) {
            select.addEventListener('change', applyFilters);
        });
        search.addEventListener('input', function () {
            clearTimeout(timer);
            timer = setTimeout(applyFilters, 600);
        });
        form.addEventListener('submit', function (event) {
            event.preventDefault();
            clearTimeout(timer);
            applyFilters();
        });
    })();
</script>
<?php include 'includes/footer.php'; ?>
