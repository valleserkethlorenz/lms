<?php
session_start();
require_once __DIR__.'/config/dbconnect.php';
require_once __DIR__.'/includes/storageService.php';
require_once __DIR__.'/config/leagueStatus.php';

$publicStats=['upcoming'=>0,'open'=>0,'ongoing'=>0,'completed'=>0];
$statusResult=$conn->query("SELECT id,start_date,end_date,regis_start,regis_end,registration_window_open,max_team FROM tbl_league WHERE publication_status='Published' AND (is_archived=0 OR is_archived IS NULL)");
if($statusResult){
    while($league=$statusResult->fetch_assoc()){
        $status=strtolower(getLeagueStatus($league));
        if(isset($publicStats[$status])) $publicStats[$status]++;
    }
}

$featuredLeagues=[];
$featuredResult=$conn->query("SELECT id,league_name,sport,division,category,start_date,end_date,regis_start,regis_end,registration_window_open,max_team,league_image FROM tbl_league WHERE publication_status='Published' AND (is_archived=0 OR is_archived IS NULL) ORDER BY CASE WHEN end_date>=CURDATE() THEN 0 ELSE 1 END,start_date ASC LIMIT 3");
if($featuredResult)while($league=$featuredResult->fetch_assoc()){$league['live_status']=getLeagueStatus($league);$featuredLeagues[]=$league;}

$heroGallery=[];
$galleryResult=$conn->query("SELECT gi.filename,gp.title FROM tbl_gallery_images gi INNER JOIN tbl_gallery_posts gp ON gp.id=gi.post_id WHERE gi.is_archived=0 AND gp.is_archived=0 ORDER BY gp.created_at DESC,gi.display_order,gi.id DESC LIMIT 8");
if($galleryResult)while($image=$galleryResult->fetch_assoc()){
    $fn=(string)$image['filename'];
    if($fn==='')continue;
    if(StorageService::isCloudinary($fn)){$heroGallery[]=['filename'=>$fn,'title'=>$image['title']];}
    elseif(is_file(__DIR__.'/assets/uploads/'.basename($fn))){$heroGallery[]=['filename'=>basename($fn),'title'=>$image['title']];}
}

include 'includes/header.php';
include 'includes/bar.php';
?>
<style>
  /* ===== Page ===== */
  .home-page {
    overflow: hidden;
    /* Same canvas as every other page (see includes/header.php) */
    background: #ffffff;
  }

  /* ===== Hero Section ===== */
  .home-hero {
    position: relative;
    min-height: 800px;
    padding: 135px 0 90px;
    color: #fff;
    background:
      linear-gradient(105deg, rgba(4,18,48,.97) 0%, rgba(7,31,69,.90) 47%, rgba(7,31,69,.35) 100%),
      url(assets/images/landing_img.jpg) center 28% / cover no-repeat;
  }

  .home-hero::after {
    content: "";
    position: absolute;
    inset: auto 0 0;
    height: 110px;
    background: linear-gradient(to top, #f7f9fc, transparent);
    pointer-events: none;
  }

  .home-hero .container {
    position: relative;
    z-index: 2;
    width: min(92vw, 1680px);
    max-width: none;
  }

  /* Hero Orbs */
  .hero-orb {
    position: absolute;
    border-radius: 50%;
    filter: blur(2px);
    background: rgba(62, 142, 255, .15);
    border: 1px solid rgba(255, 255, 255, .08);
  }
  .hero-orb.one { width: 310px; height: 310px; right: 10%; top: 130px; }
  .hero-orb.two { width: 130px; height: 130px; right: 35%; bottom: 100px; }

  /* Hero Content */
  .eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    padding: 7px 12px;
    border: 1px solid rgba(255, 255, 255, .25);
    border-radius: 999px;
    background: rgba(255, 255, 255, .09);
    color: #dcecff;
    font-size: .70rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 1.1px;
    backdrop-filter: blur(7px);
  }

  .home-hero h1 {
    max-width: 700px;
    margin: 17px 0 14px;
    color: #fff;
    font-size: clamp(2.25rem, 4.5vw, 4.15rem);
    font-weight: 800;
    line-height: 1.06;
    letter-spacing: -1.5px !important;
  }
  .home-hero h1 span { color: #8cc8ff; }

  .hero-copy {
    max-width: 600px;
    color: #d4e1f1;
    font-size: 1rem;
    line-height: 1.65;
  }

  .hero-actions {
    display: flex;
    flex-wrap: wrap;
    gap: 12px;
    margin-top: 22px;
  }
  .hero-actions .btn {
    min-height: 52px;
    padding: 13px 22px !important;
    border-radius: 10px !important;
    display: inline-flex;
    align-items: center;
    gap: 8px;
  }

  .hero-primary {
    background: #fff !important;
    border-color: #fff !important;
    color: #123a63 !important;
  }
  .hero-primary:hover { background: #e9f3fc !important; }

  .hero-secondary {
    background: rgba(255, 255, 255, .07) !important;
    border: 1px solid rgba(255, 255, 255, .45) !important;
    color: #fff !important;
  }
  .hero-secondary:hover { background: rgba(255, 255, 255, .15) !important; }

  .hero-note {
    display: flex;
    flex-wrap: wrap;
    gap: 18px;
    margin-top: 22px;
    color: #c6d8eb;
    font-size: .82rem;
  }
  .hero-note span { display: inline-flex; align-items: center; gap: 7px; }
  .hero-note i { color: #78c4ff; }

  /* Hero Layout */
  .hero-layout {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(440px, .9fr);
    align-items: center;
    gap: clamp(30px, 4vw, 66px);
  }

  /* ===== Hero Gallery ===== */
  .hero-gallery {
    position: relative;
    width: 100%;
    max-width: 550px;
    justify-self: end;
    overflow: hidden;
    border: 1px solid rgba(181, 222, 255, .45);
    border-radius: 22px;
    background: #0b2346;
    box-shadow: 0 22px 50px rgba(0, 0, 0, .28);
    isolation: isolate;
  }

  .hero-gallery-media { position: relative; height: 410px; }

  .hero-gallery-slide {
    position: absolute;
    inset: 0;
    opacity: 0;
    transition: opacity .65s ease;
    pointer-events: none;
  }
  .hero-gallery-slide.active { opacity: 1; pointer-events: auto; }
  .hero-gallery-slide img { width: 100%; height: 100%; object-fit: cover; }
  .hero-gallery-slide::after {
    content: "";
    position: absolute;
    inset: 0;
    background:
      linear-gradient(180deg, rgba(5,18,43,.78) 0%, rgba(5,18,43,.02) 40%),
      linear-gradient(0deg, rgba(5,18,43,.88) 0%, rgba(5,18,43,.02) 40%);
  }

  .hero-gallery-caption {
    position: absolute;
    right: 130px;
    top: 20px;
    left: 22px;
    z-index: 1;
  }
  .hero-gallery-caption span {
    display: block;
    margin-bottom: 5px;
    color: #9ed5ff;
    font-size: .68rem;
    font-weight: 800;
    letter-spacing: 1.2px;
    text-transform: uppercase;
  }
  .hero-gallery-caption strong {
    display: block;
    color: #fff;
    font-size: 1.05rem;
    line-height: 1.35;
  }

  .hero-gallery-footer {
    position: absolute;
    right: 16px;
    bottom: 16px;
    left: 16px;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
  }

  .hero-gallery-counter {
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(5, 18, 43, .72);
    color: #eaf6ff;
    font-size: .73rem;
    font-weight: 700;
    backdrop-filter: blur(7px);
  }

  .hero-gallery-controls { display: flex; gap: 7px; }
  .hero-gallery-controls button {
    width: 35px;
    height: 35px;
    border: 1px solid rgba(255, 255, 255, .42);
    border-radius: 50%;
    background: rgba(5, 18, 43, .68);
    color: #fff;
    transition: background .2s, transform .2s;
  }
  .hero-gallery-controls button:hover {
    background: #2477b7;
    transform: translateY(-1px);
  }

  .hero-gallery-link {
    position: absolute;
    z-index: 3;
    top: 15px;
    right: 15px;
    padding: 7px 10px;
    border-radius: 999px;
    background: rgba(5, 18, 43, .7);
    color: #fff;
    font-size: .72rem;
    font-weight: 700;
    text-decoration: none;
    backdrop-filter: blur(7px);
  }
  .hero-gallery-link:hover { color: #fff; background: #2477b7; }
  /* ===== Stats Section ===== */
  .stats-wrap {
    position: relative;
    z-index: 4;
    margin-top: -64px;
  }

  .stats-panel {
    padding: 18px;
    background: #fff;
    border: 1px solid #dde5ef;
    border-radius: 18px;
    box-shadow: 0 18px 45px rgba(9, 23, 60, .13);
  }

  .stat-item {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 15px 18px;
    border-right: 1px solid #e7ecf3;
  }
  .stat-item.last { border-right: 0; }

  .stat-icon {
    width: 48px;
    height: 48px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 13px;
    background: #eaf2f9;
    color: #123a63;
    font-size: 1.25rem;
  }

  .stat-value {
    color: #09173c;
    font-size: 1.75rem;
    font-weight: 800;
    line-height: 1;
  }

  .stat-label {
    margin-top: 4px;
    color: #6b7789;
    font-size: .8rem;
    font-weight: 600;
  }

  /* ===== Shared Section Styles ===== */
  .home-section { padding: 92px 0; }

  .section-kicker {
    color: #2563a6;
    font-size: .78rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1.4px;
  }

  .section-title {
    margin: 8px 0 12px;
    color: #101828;
    font-size: clamp(1.85rem, 3.5vw, 2.7rem);
    font-weight: 800;
  }

  .section-copy { max-width: 650px; color: #667085; }

  /* ===== About Section ===== */
  .about-panel {
    overflow: hidden;
    border: 1px solid #dce5ee;
    border-radius: 22px;
    background: #fff;
    box-shadow: 0 14px 35px rgba(16, 24, 40, .08);
  }

  .about-copy { padding: 42px; }
  .about-copy p { color: #667085; line-height: 1.75; }

  .about-focus {
    height: 100%;
    padding: 42px;
    background: linear-gradient(145deg, #09173c, #174f83);
    color: #fff;
  }
  .about-focus i { display: block; margin-bottom: 18px; color: #8cc8ff; font-size: 2.4rem; }
  .about-focus h3 { color: #fff; font-weight: 800; }
  .about-focus p { margin: 0; color: #d5e5f4; line-height: 1.7; }

  /* ===== Featured Leagues Section ===== */
  .featured-card {
    height: 100%;
    overflow: hidden;
    border: 1px solid #dfe6ef !important;
    border-radius: 16px !important;
    background: #fff;
    box-shadow: 0 7px 22px rgba(16, 24, 40, .07) !important;
    transition: transform .2s, box-shadow .2s;
  }
  .featured-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 16px 32px rgba(16, 24, 40, .12) !important;
  }

  .featured-image {
    position: relative;
    height: 190px;
    overflow: hidden;
    background: #dfe8f2;
  }
  .featured-image img {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform .35s;
  }
  .featured-card:hover img { transform: scale(1.04); }

  .status-pill {
    position: absolute;
    left: 14px;
    top: 14px;
    padding: 6px 10px;
    border-radius: 999px;
    background: rgba(9, 23, 60, .88);
    color: #fff;
    font-size: .72rem;
    font-weight: 700;
    backdrop-filter: blur(6px);
  }

  .status-pill.league-status-open { background: #28c76f; }
  .status-pill.league-status-ongoing { background: #0069f7; }
  .status-pill.league-status-upcoming { background: #6f42c1; }
  .status-pill.league-status-ended { background: #6c757d; }

  .featured-body { padding: 20px; }

  .featured-sport {
    color: #2b69a4;
    font-size: .74rem;
    font-weight: 800;
    text-transform: uppercase;
    letter-spacing: 1px;
  }

  .featured-title {
    margin: 6px 0 14px;
    color: #172033;
    font-size: 1.2rem;
    font-weight: 750;
  }

  .featured-meta {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
    color: #657186;
    font-size: .78rem;
  }
  .featured-meta span { display: flex; align-items: center; gap: 7px; }
  .featured-meta i { color: #2b69a4; }

  .featured-footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 20px;
    padding-top: 15px;
    border-top: 1px solid #edf0f4;
  }
  .featured-footer small { color: #758195; }

  .empty-featured {
    padding: 50px 24px;
    text-align: center;
    border: 1px dashed #bfcbd9;
    border-radius: 16px;
    background: #fff;
    color: #667085;
  }

  /* ===== How It Works / Steps Section ===== */
  .how-section { background: #eef3f8; }

  .step-card {
    position: relative;
    height: 100%;
    padding: 26px;
    border: 1px solid #dce5ee;
    border-radius: 16px;
    background: #fff;
    transition: transform .2s, box-shadow .2s;
  }
  .step-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 28px rgba(16, 24, 40, .09);
  }

  .step-number {
    width: 42px;
    height: 42px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 22px;
    border-radius: 12px;
    background: #123a63;
    color: #fff;
    font-size: 1rem;
    font-weight: 800;
    box-shadow: 0 8px 16px rgba(18, 58, 99, .18);
  }

  .step-card h5 { font-weight: 750; color: #091123; }
  .step-card p { margin: 0; color: #474c58; font-size: .9rem; }
  /* ===== CTA Section ===== */
  .home-cta { padding: 80px 0; }

  .cta-panel {
    position: relative;
    overflow: hidden;
    padding: 48px;
    border-radius: 22px;
    background: linear-gradient(135deg, #09173c, #174f83);
    color: #fff;
    box-shadow: 0 20px 45px rgba(9, 23, 60, .2);
  }
  .cta-panel::after {
    content: "";
    position: absolute;
    width: 260px;
    height: 260px;
    right: -70px;
    top: -100px;
    border-radius: 50%;
    background: rgba(255, 255, 255, .08);
  }
  .cta-panel h2 { color: #fff; font-weight: 800; }
  .cta-panel p { max-width: 650px; margin-bottom: 0; color: #d5e5f4; }

  /* ===== Footer ===== */
  .home-footer-note {
    padding: 24px 0 36px;
    color: #7a8494;
    font-size: .82rem;
    text-align: center;
  }

  .contact-list {
    display: flex;
    flex-wrap: wrap;
    justify-content: center;
    gap: 10px 24px;
    margin-top: 9px;
  }
  .contact-list a { color: #596579; text-decoration: none; }
  .contact-list a:hover { color: #174f83; text-decoration: underline; }

  /* ===== Responsive: Tablet ===== */
  @media (max-width: 991.98px) {
    .home-hero { min-height: auto; padding: 90px 0 105px; }
    .hero-layout { grid-template-columns: 1fr; gap: 38px; }
    .hero-gallery { max-width: 680px; justify-self: start; }
    .hero-gallery-media { height: 330px; }
    .stats-wrap { margin-top: -48px; }
    .stat-item { border-right: 0; border-bottom: 1px solid #e7ecf3; }
    .stats-panel .row > [class*="col-"]:last-child .stat-item { border-bottom: 0; }
    .home-section { padding: 70px 0; }
  }

  /* ===== Responsive: Mobile ===== */
  @media (max-width: 575.98px) {
    .home-hero h1 { letter-spacing: -1px !important; }
    .hero-actions .btn { width: 100%; justify-content: center; }
    .stats-panel { padding: 8px; }
    .stat-item { padding: 14px 12px; }
    .featured-meta { grid-template-columns: 1fr; }
    .cta-panel,
    .about-copy,
    .about-focus { padding: 30px 24px; }
    .home-section { padding: 58px 0; }
  }
</style>

<body id="page-top" class="home-page">
  <main>
    <section class="home-hero">
      <span class="hero-orb one" aria-hidden="true"></span><span class="hero-orb two" aria-hidden="true"></span>
      <div class="container"><div class="hero-layout">
        <div class="hero-content"><div class="eyebrow"><i class="bi bi-geo-alt-fill"></i>Panabo City, Davao del Norte</div>
          <h1>Building unity, health, and <span>sportsmanship through sports.</span></h1>
          <p class="hero-copy">The City Sports Development Program connects sports-active individuals, enthusiasts, communities, and schools through accessible, organized sports activities in Panabo City.</p>
          <div class="hero-actions">
            <a class="btn hero-primary" href="leagues.php">Explore Leagues <i class="bi bi-arrow-right"></i></a>
            <a class="btn hero-secondary" href="login.php"><i class="bi bi-person-plus"></i>Register a Team</a>
          </div>
          <div class="hero-note"><span><i class="bi bi-check-circle-fill"></i>Accessible community sports</span><span><i class="bi bi-check-circle-fill"></i>Health and camaraderie</span><span><i class="bi bi-check-circle-fill"></i>Discipline and fair play</span></div>
        </div>
        <?php if($heroGallery): ?><aside class="hero-gallery" aria-label="Latest community gallery photos"><a class="hero-gallery-link" href="gallery.php">View gallery <i class="bi bi-arrow-up-right"></i></a><div class="hero-gallery-media"><?php foreach($heroGallery as $index=>$image): $hgSrc=StorageService::isCloudinary($image['filename'])?$image['filename']:'assets/uploads/'.rawurlencode($image['filename']); ?><article class="hero-gallery-slide<?= $index===0?' active':'' ?>" aria-hidden="<?= $index===0?'false':'true' ?>"><img src="<?= htmlspecialchars($hgSrc) ?>" alt="<?= htmlspecialchars($image['title']) ?>" <?= $index===0?'loading="eager"':'loading="lazy"' ?>><div class="hero-gallery-caption"><strong><?= htmlspecialchars($image['title']) ?></strong></div></article><?php endforeach; ?></div><div class="hero-gallery-footer"><span class="hero-gallery-counter"><span data-hero-gallery-current>1</span> / <?= count($heroGallery) ?></span><div class="hero-gallery-controls"><button type="button" data-hero-gallery-previous aria-label="Previous photo"><i class="bi bi-chevron-left"></i></button><button type="button" data-hero-gallery-next aria-label="Next photo"><i class="bi bi-chevron-right"></i></button></div></div></aside><?php endif; ?>
      </div></div>
    </section>

    <section class="stats-wrap" aria-label="League system statistics">
      <div class="container"><div class="stats-panel"><div class="row g-0">
        <div class="col-lg-3 col-sm-6"><div class="stat-item"><div class="stat-icon"><i class="bi bi-calendar-plus"></i></div><div><div class="stat-value"><?= number_format($publicStats['upcoming']) ?></div><div class="stat-label">Upcoming leagues</div></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="stat-item"><div class="stat-icon"><i class="bi bi-unlock"></i></div><div><div class="stat-value"><?= number_format($publicStats['open']) ?></div><div class="stat-label">Open leagues</div></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="stat-item"><div class="stat-icon"><i class="bi bi-play-circle"></i></div><div><div class="stat-value"><?= number_format($publicStats['ongoing']) ?></div><div class="stat-label">Ongoing leagues</div></div></div></div>
        <div class="col-lg-3 col-sm-6"><div class="stat-item last"><div class="stat-icon"><i class="bi bi-check2-circle"></i></div><div><div class="stat-value"><?= number_format($publicStats['completed']) ?></div><div class="stat-label">Completed leagues</div></div></div></div>
      </div></div></div>
    </section>

    <section class="home-section" aria-labelledby="about-csdp-title">
      <div class="container"><div class="about-panel"><div class="row g-0 align-items-stretch">
        <div class="col-lg-7"><div class="about-copy"><div class="section-kicker">About the program</div><h2 class="section-title" id="about-csdp-title">City Sports Development Program</h2><p>The City Sports Development Program is implemented under the Office of the City Mayor. It aims to unite sports-active individuals, enthusiasts, communities, and school organizations by supporting them and initiating opportunities for meaningful interaction through sports.</p><p class="mb-0">Its activities promote health, camaraderie, sportsmanship, and discipline while helping local participants engage in properly coordinated sports events and competitions.</p></div></div>
        <div class="col-lg-5"><div class="about-focus"><i class="bi bi-universal-access-circle"></i><div class="section-kicker text-info">Major final output</div><h3>Accessible Sports Development</h3><p>An inclusive, community-driven effort to give Panabo City residents equal opportunities to participate in sports programs, training, and competitions—supporting health, development, and talent discovery across the barangays.</p></div></div>
      </div></div></div>
    </section>

    <section class="home-section">
      <div class="container">
        <div class="d-flex flex-wrap justify-content-between align-items-end gap-3 mb-4"><div><div class="section-kicker">League spotlight</div><h2 class="section-title">Featured local leagues</h2><p class="section-copy mb-0">See what is open, upcoming, or currently underway in the community.</p></div><a href="leagues.php" class="btn btn-outline-primary">View all leagues <i class="bi bi-arrow-right ms-1"></i></a></div>
        <?php if($featuredLeagues): ?><div class="row g-4"><?php foreach($featuredLeagues as $league):
            $fiVal=trim((string)($league['league_image']??''));
            $featuredImage='assets/images/default-league.svg';
            if(StorageService::isCloudinary($fiVal)){
                $featuredImage=$fiVal;
            }else{
                $filename=basename($fiVal);
                if($filename!==''&&is_file(__DIR__.'/assets/uploads/'.$filename)){
                    $featuredImage='assets/uploads/'.rawurlencode($filename);
                }
            }
        ?>
          <div class="col-lg-4 col-md-6"><article class="featured-card">
            <div class="featured-image"><img src="<?= htmlspecialchars($featuredImage,ENT_QUOTES,'UTF-8') ?>" alt="<?= htmlspecialchars($league['league_name']) ?>" loading="lazy" onerror="this.onerror=null;this.src='assets/images/default-league.svg';"><span class="status-pill <?= htmlspecialchars(getLeagueStatusClass($league['live_status'])) ?>"><?= htmlspecialchars(getLeagueStatusLabel($league['live_status'])) ?></span></div>
            <div class="featured-body"><div class="featured-sport"><?= htmlspecialchars($league['sport']) ?></div><h3 class="featured-title"><?= htmlspecialchars($league['league_name']) ?></h3>
              <div class="featured-meta"><span><i class="bi bi-diagram-3"></i><?= htmlspecialchars($league['division']) ?></span><span><i class="bi bi-people"></i><?= htmlspecialchars($league['category']) ?></span><span><i class="bi bi-calendar3"></i><?= date('M d, Y',strtotime($league['start_date'])) ?></span><span><i class="bi bi-flag"></i><?= date('M d, Y',strtotime($league['end_date'])) ?></span></div>
              <div class="featured-footer"><small>Up to <?= (int)$league['max_team'] ?> teams</small><a href="team_information.php?id=<?= (int)$league['id'] ?>" class="btn btn-sm btn-primary">View League</a></div>
            </div>
          </article></div>
        <?php endforeach; ?></div><?php else: ?><div class="empty-featured"><i class="bi bi-calendar2-event fs-2 d-block mb-2"></i>No published leagues are available yet. Please check back soon.</div><?php endif; ?>
      </div>
    </section>

    <section class="home-section how-section"><div class="container"><div class="text-center mx-auto mb-5" style="max-width:720px"><div class="section-kicker">Service procedure</div><h2 class="section-title">How a sports activity is conducted</h2><p class="section-copy mx-auto">From the first advisory through final documentation, each event follows a coordinated process designed for safe, fair, and organized participation.</p></div>
      <div class="row g-4">
        <div class="col-lg-4 col-md-6"><div class="step-card"><div class="step-number">01</div><h5>Official Advisory</h5><p>Announcements, reminders, guidelines, and updates help coordinate the safe and smooth participation of everyone concerned.</p></div></div>
        <div class="col-lg-4 col-md-6"><div class="step-card"><div class="step-number">02</div><h5>Registration Process</h5><p>Participants submit requirements within the designated period for documentation and eligibility review before the games.</p></div></div>
        <div class="col-lg-4 col-md-6"><div class="step-card"><div class="step-number">03</div><h5>Commencement Ceremony</h5><p>The opening program sets the tone for fair play, unity, and sportsmanship through formal remarks and activities.</p></div></div>
        <div class="col-lg-4 col-md-6"><div class="step-card"><div class="step-number">04</div><h5>Conduct of Sports Activity</h5><p>Scheduled games are organized, officiated, and implemented according to the applicable rules and program flow.</p></div></div>
        <div class="col-lg-4 col-md-6"><div class="step-card"><div class="step-number">05</div><h5>Awarding and Closing</h5><p>Winners are recognized, awards are distributed, and the activity concludes with appreciation for all participants.</p></div></div>
        <div class="col-lg-4 col-md-6"><div class="step-card"><div class="step-number">06</div><h5>Reports and Documentation</h5><p>Required records, results, and activity reports are prepared to document the event, its outcomes, and evaluation.</p></div></div>
      </div>
    </div></section>

    <section class="home-cta"><div class="container"><div class="cta-panel d-flex flex-wrap justify-content-between align-items-center gap-4"><div><h2 class="mb-2">Ready to represent your team?</h2><p>Join a published league and manage your registration, roster, and documents in one place.</p></div><a href="login.php" class="btn btn-light position-relative" style="z-index:2">Get started <i class="bi bi-arrow-right ms-1"></i></a></div></div></section>
  </main>
  <div class="home-footer-note"><div class="container"><strong>City Sports Development Program · Panabo City</strong><div class="contact-list"><a href="tel:+639460469934"><i class="bi bi-telephone me-1"></i>0946-046-9934</a><a href="mailto:citysportspanabo@gmail.com"><i class="bi bi-envelope me-1"></i>citysportspanabo@gmail.com</a><a href="https://www.facebook.com/" target="_blank" rel="noopener"><i class="bi bi-facebook me-1"></i>City Sports Development Program - CSDP- Panabo City</a></div></div></div>
  <?php include 'includes/footer.php'; ?>
  <?php if(count($heroGallery)>1): ?><script>
    (()=>{const gallery=document.querySelector('.hero-gallery');if(!gallery)return;const slides=[...gallery.querySelectorAll('.hero-gallery-slide')],current=gallery.querySelector('[data-hero-gallery-current]');let index=0,timer;const show=next=>{slides[index].classList.remove('active');slides[index].setAttribute('aria-hidden','true');index=(next+slides.length)%slides.length;slides[index].classList.add('active');slides[index].setAttribute('aria-hidden','false');current.textContent=index+1;};const start=()=>{clearInterval(timer);timer=setInterval(()=>show(index+1),5000);};gallery.querySelector('[data-hero-gallery-previous]').addEventListener('click',()=>{show(index-1);start();});gallery.querySelector('[data-hero-gallery-next]').addEventListener('click',()=>{show(index+1);start();});gallery.addEventListener('mouseenter',()=>clearInterval(timer));gallery.addEventListener('mouseleave',start);start();})();
  </script><?php endif; ?>
</body>
</html>
