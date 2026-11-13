<?php
if (session_status() !== PHP_SESSION_ACTIVE) session_start();
require_once __DIR__.'/../../config/dbconnect.php';
require_once __DIR__.'/../../includes/storageService.php';
require_once __DIR__.'/../../config/auth_guard.php';
require_once __DIR__.'/../../config/leagueStatus.php';
requireRole([2]);
requireActiveAccount($conn);

$userId=(int)$_SESSION['lms_id'];
$full=$_SESSION['lms_username']??'';
$userType=2;
$filters=['search'=>trim($_GET['search']??''),'sport'=>trim($_GET['sport']??''),'status'=>trim($_GET['status']??'')];
if(!in_array($filters['status'],['','Upcoming','Open','Ongoing','Completed'],true))$filters['status']='';

$sportOptions=[];
$sportResult=$conn->query("SELECT DISTINCT sport FROM tbl_league WHERE publication_status='Published' AND (is_archived=0 OR is_archived IS NULL) AND sport<>'' ORDER BY sport");
if($sportResult)while($row=$sportResult->fetch_assoc())$sportOptions[]=$row['sport'];

$where=["l.publication_status='Published'","(l.is_archived=0 OR l.is_archived IS NULL)"];$params=[];$types='';
if($filters['search']!==''){$where[]='(l.league_name LIKE ? OR l.sport LIKE ?)';$term='%'.$filters['search'].'%';$params[]=$term;$params[]=$term;$types.='ss';}
if($filters['sport']!==''){$where[]='l.sport=?';$params[]=$filters['sport'];$types.='s';}
// current_teams counts APPROVED teams only: a slot belongs to an approved team,
// so pending applications never make a league look full in this directory.
$sql="SELECT l.*,(SELECT COUNT(*) FROM tbl_team t WHERE t.league_id=l.id AND LOWER(t.status)='approved') current_teams FROM tbl_league l WHERE ".implode(' AND ',$where).' ORDER BY l.start_date DESC';
$stmt=$conn->prepare($sql);if($params)$stmt->bind_param($types,...$params);$stmt->execute();$result=$stmt->get_result();$leagues=[];
while($league=$result->fetch_assoc()){$league['live_status']=getLeagueStatus($league);if($filters['status']===''||$filters['status']===$league['live_status'])$leagues[]=$league;}$stmt->close();

// Leagues where this representative already has a team — drives the "Joined" marker.
$myLeagueIds=[];
$myStmt=$conn->prepare("SELECT DISTINCT league_id FROM tbl_team WHERE user_id=? AND LOWER(status) NOT IN ('cancelled','canceled','expired')");
if($myStmt){
  $myStmt->bind_param('i',$userId);$myStmt->execute();$myRes=$myStmt->get_result();
  while($myRow=$myRes->fetch_assoc())$myLeagueIds[(int)$myRow['league_id']]=true;
  $myStmt->close();
}

include 'includes/header.php';
?>
<style>
  .browse-page{padding:20px 24px 45px}
  .browse-shell{padding:24px;border:1px solid #dce4ed;border-radius:10px;background:#fff;box-shadow:0 8px 24px rgba(16,24,40,.06)}
  .browse-filter{padding:0;border:1px solid #d8e1eb;border-radius:10px;background:#fff;overflow:hidden;box-shadow:0 4px 18px rgba(16,24,40,.05);transition:box-shadow .25s ease}
  .browse-filter:hover{box-shadow:0 6px 24px rgba(16,24,40,.09)}
  .filter-count-badge{flex:0 0 auto;background:rgba(255,255,255,.16);color:#fff;font-size:.7rem;font-weight:700;padding:4px 10px;border-radius:999px;border:1px solid rgba(255,255,255,.2);transition:background .2s}
  .filter-count-badge.has-filters{background:rgba(255,193,7,.25);border-color:rgba(255,193,7,.35)}
  .filter-body{display:flex;flex-wrap:wrap;align-items:flex-end;gap:16px;padding:18px;min-height:0}
  .filter-field{flex:1 1 180px;min-width:0;margin-bottom:0}
  .filter-field:has(#leagueSearch){flex-grow:2}
  .filter-field:last-of-type{margin-bottom:0}
  .filter-field label{font-size:.72rem;font-weight:600;color:#4b5c72;text-transform:uppercase;letter-spacing:.6px;margin-bottom:6px;display:flex;align-items:center;gap:6px}
  .filter-field label i{font-size:.8rem;color:#8896a7}
  .filter-field .input-group{border:1.5px solid #d8e1eb;border-radius:10px;overflow:hidden;transition:border-color .2s,box-shadow .2s;background:#f8fafc}
  .filter-field .input-group:focus-within{border-color:#1a3a7c;box-shadow:0 0 0 3px rgba(26,58,124,.1);background:#fff}
  .filter-field .input-group .input-group-text{background:transparent;border:none;border-right:1.5px solid #e4eaf1;color:#8896a7;font-size:.85rem;padding:0 12px;min-width:38px;justify-content:center}
  .filter-field .input-group .form-control,
  .filter-field .input-group .form-select{border:none;background:transparent;font-size:.82rem;padding:10px 12px;box-shadow:none!important;color:#1e293b!important}
  .filter-field .input-group .form-control::placeholder{color:#a0aec0}
  .filter-field .input-group .form-select{cursor:pointer;appearance:auto}
  .filter-mobile-toggle{display:none;width:100%;padding:12px 18px;background:linear-gradient(135deg,#09173c,#1a3a7c);border:none;border-radius:6px;color:#fff;font-size:.85rem;font-weight:600;margin-bottom:12px;align-items:center;justify-content:center;gap:8px;box-shadow:0 4px 14px rgba(9,23,60,.15)}
  .filter-mobile-toggle:hover{color:#fff}
  .filter-mobile-toggle .toggle-icon{transition:transform .3s}
  .filter-mobile-toggle[aria-expanded="true"] .toggle-icon{transform:rotate(180deg)}
  /* Exactly 4 cards per row on desktop (auto-fill counts from a track's definite max,
     which kept collapsing the row to 3 regardless of the minimum used). */
  .league-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:18px;align-content:start}
  .league-grid-item{min-width:0}
  .browse-empty-wrap{grid-column:1/-1}
  .league-grid:has(> .browse-empty-wrap){align-content:stretch}
  .league-card,
  .league-card:link,
  .league-card:visited{height:100%;overflow:hidden;border:1px solid #dfe6ef;border-radius:16px;background:#fff;box-shadow:0 6px 18px rgba(16,24,40,.07);transition:transform .2s ease,box-shadow .2s ease,border-color .2s ease;display:flex;flex-direction:column;text-decoration:none!important;color:#1e293b}
  .league-card:hover,
  .league-card:focus-visible{transform:translateY(-5px);border-color:#bfd0e7;box-shadow:0 14px 30px rgba(16,24,40,.13);color:#1e293b;text-decoration:none!important}
  .league-card:hover *,
  .league-card:focus-visible *{text-decoration:none!important}
  .league-card:focus-visible{outline:3px solid rgba(26,58,124,.3);outline-offset:3px}
  .league-image{position:relative;height:132px;flex:0 0 132px;overflow:hidden;background:#dfe8f2}
  .league-image img{width:100%;height:100%;object-fit:cover;transition:transform .35s ease}
  .league-card:hover .league-image img{transform:scale(1.04)}
  .league-status{position:absolute;left:10px;top:10px;padding:6px 10px;border-radius:999px;color:#fff;font-size:.62rem;font-weight:700;backdrop-filter:blur(6px)}
  .league-status-open{background:#28c76f}.league-status-ongoing{background:#0069f7}.league-status-upcoming{background:#6f42c1}.league-status-ended{background:#6c757d}
  .league-card-body{display:flex;flex-direction:column;padding:15px 16px 17px;flex:1 1 auto}
  .league-sport{font-size:.68rem;line-height:1.2;color:#2b69a4;font-weight:800;text-transform:uppercase;letter-spacing:.8px}
  .league-card h3{display:-webkit-box;overflow:hidden;margin:4px 0 10px;font-size:1rem;font-weight:750;line-height:1.35;color:#172033;-webkit-line-clamp:2;-webkit-box-orient:vertical;line-clamp:2}
  .league-meta{display:grid;grid-template-columns:repeat(auto-fit,minmax(92px,1fr));gap:8px 10px;font-size:.7rem;line-height:1.4;color:#657186}
  .league-meta span{display:inline-flex;align-items:center;gap:7px;min-width:0}
  .league-meta i{width:14px;flex:0 0 auto;color:#2b69a4}
  .league-meta .league-meta-text{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}

  .league-image::before{content:none}
  .league-card-hint{position:absolute;top:12px;right:12px;display:inline-flex;align-items:center;justify-content:center;width:26px;height:26px;border-radius:50%;background:rgba(255,255,255,.95);color:#09173c;font-size:.78rem;opacity:0;transform:translateY(-4px);transition:opacity .22s ease,transform .22s ease;box-shadow:0 4px 12px rgba(15,23,42,.25)}
  .league-card:hover .league-card-hint,
  .league-card:focus-visible .league-card-hint{opacity:1;transform:translateY(0)}
  .league-card-joined,
  .league-card-slots{position:absolute;bottom:10px;display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:999px;font-size:.6rem;font-weight:800;letter-spacing:.35px;text-transform:uppercase;line-height:1;box-shadow:0 2px 8px rgba(15,23,42,.22)}
  .league-card-joined{left:10px;background:rgba(255,255,255,.95);color:#137a4e}
  .league-card-slots{right:10px;background:rgba(9,23,60,.74);color:#fff;backdrop-filter:blur(6px)}
  .league-card-slots i{font-size:.66rem;color:#a9c6ff}
  .league-card-slots.is-full{background:rgba(180,35,53,.82)}
  .league-card-slots.is-full i{color:#ffd7dd}
  .browse-empty{grid-column:1/-1;padding:70px 20px;text-align:center;color:#6d7887}
  .browse-empty-icon{display:inline-flex;align-items:center;justify-content:center;width:64px;height:64px;margin-bottom:14px;border-radius:50%;background:#eef3fb;color:#1a3a7c;font-size:1.6rem}
  .browse-empty h5{font-weight:700;color:#152847;margin-bottom:6px}
  .browse-empty p{max-width:44ch;margin:0 auto 16px;font-size:.86rem;line-height:1.6}
  .browse-empty-btn{display:inline-flex;align-items:center;gap:7px;padding:9px 16px;border-radius:8px;background:linear-gradient(135deg,#09173c,#1a3a7c);color:#fff!important;font-size:.82rem;font-weight:600;transition:transform .2s ease,box-shadow .2s ease}
  .browse-empty-btn:hover{transform:translateY(-1px);box-shadow:0 6px 16px rgba(9,23,60,.22)}
  @media (prefers-reduced-motion:reduce){
    .league-card,.league-image img,.league-card-hint,.league-image::before,.browse-empty-btn{transition:none!important}
    .league-card:hover{transform:none}
    .league-card:hover .league-image img{transform:none}
  }
  @media(max-width:991.98px){.browse-filter{position:static}.browse-page{padding:15px}.browse-shell{padding:18px}.filter-mobile-toggle{display:inline-flex}.filter-collapse{display:none}.filter-collapse.show{display:block}}
  @media(max-width:1199.98px){.league-grid{grid-template-columns:repeat(auto-fill,minmax(min(100%,210px),1fr))}}
  @media(max-width:575.98px){.filter-field{flex:1 1 100%}.league-grid{grid-template-columns:minmax(0,1fr)}}
</style>
<body id="page-top" class="body">
<?php include 'includes/bar.php'; ?>
<div class="browse-page">
  <div class="browse-shell">
    
    <div class="row g-4 align-items-lg-stretch">
    <?php
    $activeFilterCount=0; if($filters['search']!=='')$activeFilterCount++; if($filters['sport']!=='')$activeFilterCount++; if($filters['status']!=='')$activeFilterCount++;
    ?>
    <aside class="col-12" aria-label="League filters">
      <button class="filter-mobile-toggle" type="button" data-bs-toggle="collapse" data-bs-target="#filterCollapse" aria-expanded="false" aria-controls="filterCollapse">
        <i class="bi bi-funnel-fill"></i> Filters<?php if($activeFilterCount>0): ?> <span class="filter-count-badge has-filters ms-1"><?= $activeFilterCount ?> active</span><?php endif; ?>
        <i class="bi bi-chevron-down toggle-icon ms-auto"></i>
      </button>
      <div class="filter-collapse" id="filterCollapse">
        <form class="browse-filter" method="get" action="browse_leagues.php">
          <div class="filter-body">
            <!-- Search Field -->
            <div class="filter-field">
              <label for="leagueSearch"><i class="bi bi-search"></i>Search</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-search"></i></span>
                <input id="leagueSearch" name="search" class="form-control" value="<?= htmlspecialchars($filters['search']) ?>" placeholder="Search league name…">
              </div>
            </div>
            <!-- Sport Field -->
            <div class="filter-field">
              <label for="sportFilter"><i class="bi bi-trophy"></i>Sport</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-trophy"></i></span>
                <select id="sportFilter" name="sport" class="form-select">
                  <option value="">All Sports</option>
                  <?php foreach($sportOptions as $sport): ?><option value="<?= htmlspecialchars($sport) ?>" <?= $filters['sport']===$sport?'selected':'' ?>><?= htmlspecialchars($sport) ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
            <!-- Status Field -->
            <div class="filter-field">
              <label for="statusFilter"><i class="bi bi-activity"></i>Status</label>
              <div class="input-group">
                <span class="input-group-text"><i class="bi bi-activity"></i></span>
                <select id="statusFilter" name="status" class="form-select">
                  <option value="">All Statuses</option>
                  <?php foreach(['Upcoming','Open','Ongoing','Completed'] as $status): ?><option value="<?= $status ?>" <?= $filters['status']===$status?'selected':'' ?>><?= $status ?></option><?php endforeach; ?>
                </select>
              </div>
            </div>
          </div>
        </form>
      </div>
    </aside>
    <section class="col-12" aria-label="Available leagues"><div class="league-grid">
      <?php if($leagues):foreach($leagues as $league):
        $blImg=(string)($league['league_image']??'');
        if(StorageService::isCloudinary($blImg)){$imagePath=$blImg;}else{$imagePath='../../assets/uploads/'.basename($blImg);if($blImg===''||!is_file(__DIR__.'/../../assets/uploads/'.basename($blImg)))$imagePath='../../assets/images/default-league.svg';}
        $statusClass=getLeagueStatusClass($league['live_status']);
        $blMaxTeams=(int)($league['max_team']??0);
        $blTeams=(int)($league['current_teams']??0);
        $blSlotsFull=$blMaxTeams>0&&$blTeams>=$blMaxTeams;
        $blIsJoined=isset($myLeagueIds[(int)$league['id']]);
        $blS=strtotime((string)$league['start_date']);$blE=strtotime((string)$league['end_date']);
        $blStartDate=date('M d, Y',$blS?:time());$blEndDate=date('M d, Y',$blE?:time());
        $blName=htmlspecialchars($league['league_name'],ENT_QUOTES,'UTF-8');
      ?>
        <div class="league-grid-item"><a href="../../team_information.php?id=<?= (int)$league['id'] ?>" class="league-card" aria-label="View <?= $blName ?> details"><div class="league-image"><img src="<?= htmlspecialchars($imagePath,ENT_QUOTES,'UTF-8') ?>" alt="<?= $blName ?>" loading="lazy" decoding="async" onerror="this.onerror=null;this.src='../../assets/images/default-league.svg';"><span class="league-status <?= htmlspecialchars($statusClass) ?>"><?= htmlspecialchars(getLeagueStatusLabel($league['live_status'])) ?></span><span class="league-card-hint" aria-hidden="true"><i class="bi bi-arrow-up-right"></i></span><?php if($blIsJoined): ?><span class="league-card-joined" title="You already have a team in this league"><i class="bi bi-check2-circle" aria-hidden="true"></i>Joined</span><?php endif; ?><span class="league-card-slots<?= $blSlotsFull?' is-full':'' ?>" title="<?= $blMaxTeams>0?'Teams registered: '.$blTeams.' of '.$blMaxTeams.' slots':'Teams registered: '.$blTeams ?>"><i class="bi bi-people-fill" aria-hidden="true"></i><?= $blMaxTeams>0?$blTeams.'/'.$blMaxTeams:$blTeams ?></span></div><div class="league-card-body"><div class="league-sport"><?= htmlspecialchars($league['sport'] ?? '') ?></div><h3 title="<?= $blName ?>"><?= htmlspecialchars($league['league_name']) ?></h3><div class="league-meta"><span title="Division: <?= htmlspecialchars($league['division'] ?? '') ?>"><i class="bi bi-diagram-3"></i><span class="league-meta-text"><?= htmlspecialchars($league['division'] ?? '') ?></span></span><span title="Category: <?= htmlspecialchars($league['category'] ?? '') ?>"><i class="bi bi-people"></i><span class="league-meta-text"><?= htmlspecialchars($league['category'] ?? '') ?></span></span><span title="Start date: <?= htmlspecialchars($blStartDate,ENT_QUOTES,'UTF-8') ?>"><i class="bi bi-calendar3"></i><span class="league-meta-text"><?= htmlspecialchars($blStartDate,ENT_QUOTES,'UTF-8') ?></span></span><span title="End date: <?= htmlspecialchars($blEndDate,ENT_QUOTES,'UTF-8') ?>"><i class="bi bi-flag"></i><span class="league-meta-text"><?= htmlspecialchars($blEndDate,ENT_QUOTES,'UTF-8') ?></span></span></div></div></a></div>
      <?php endforeach;else: ?>
      <div class="browse-empty-wrap">
        <div class="browse-empty">
          <span class="browse-empty-icon"><i class="bi bi-search"></i></span>
          <h5>No matching leagues</h5>
          <p>Try a different search term, or clear the filters to see every published league.</p>
          <a class="browse-empty-btn" href="browse_leagues.php"><i class="bi bi-arrow-counterclockwise"></i>Reset filters</a>
        </div>
      </div>
      <?php endif; ?>
    </div></section>
  </div></div>
</div>
<?php include 'includes/footer.php'; ?>
<script>
(function () {
  var form = document.querySelector('.browse-filter');
  var search = document.getElementById('leagueSearch');
  var timer;
  function applyFilters() {
    clearTimeout(timer);
    form.requestSubmit();
  }
  form.querySelectorAll('select').forEach(function (select) {
    select.addEventListener('change', applyFilters);
  });
  search.addEventListener('input', function () {
    clearTimeout(timer);
    timer = setTimeout(applyFilters, 600);
  });
  form.addEventListener('submit', function () {
    clearTimeout(timer);
  });
})();
</script>
</body></html>
