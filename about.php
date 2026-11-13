<?php
session_start();
require_once __DIR__.'/config/dbconnect.php';
include 'includes/header.php';
include 'includes/bar.php';
?>
<style>
  .about-page{background:#ffffff;color:#182230;min-height:100vh}
  .about-header{padding:145px 0 78px;background:linear-gradient(120deg,#071633,#123e6b);color:#fff;position:relative;overflow:hidden}
  .about-header:after{content:"";position:absolute;width:360px;height:360px;right:-90px;top:-150px;border-radius:50%;background:rgba(140,200,255,.12)}
  .about-header .container{position:relative;z-index:1}
  .about-kicker{display:inline-flex;align-items:center;gap:8px;color:#8cc8ff;font-size:.78rem;font-weight:800;text-transform:uppercase;letter-spacing:1.4px}
  .about-header h1{max-width:820px;margin:12px 0 14px;color:#fff;font-size:clamp(2.1rem,4vw,3.6rem);font-weight:800;line-height:1.1}
  .about-header p{max-width:760px;margin:0;color:#d5e4f2;font-size:1.03rem;line-height:1.75}
  .about-section{padding:78px 0}
  .section-kicker{color:#2563a6;font-size:.76rem;font-weight:800;text-transform:uppercase;letter-spacing:1.3px}
  .section-title{margin:8px 0 14px;color:#101828;font-size:clamp(1.7rem,3vw,2.5rem);font-weight:800}
  .section-copy{color:#667085;line-height:1.75}
  .mission-card{height:100%;padding:34px;border:1px solid #dce5ee;border-radius:20px;background:#fff;box-shadow:0 10px 28px rgba(16,24,40,.07)}
  .mission-icon{width:54px;height:54px;display:flex;align-items:center;justify-content:center;margin-bottom:20px;border-radius:15px;background:#e8f2fb;color:#174f83;font-size:1.45rem}
  .mission-card h3{font-size:1.2rem;font-weight:800}
  .mission-card p{margin:0;color:#667085;line-height:1.7}
  .structure-section{background:#edf3f8}
  .org-chart-frame{margin:0;padding:14px;border:1px solid #d5e1ec;border-radius:20px;background:#fff;box-shadow:0 14px 34px rgba(16,24,40,.09)}
  .org-chart-link{display:block;overflow:hidden;border-radius:13px;background:#fff;cursor:zoom-in}
  .org-chart-image{display:block;width:100%;height:auto;transition:transform .25s ease}
  .org-chart-link:hover .org-chart-image{transform:scale(1.012)}
  .org-chart-frame figcaption{display:flex;align-items:center;justify-content:center;gap:7px;padding:13px 8px 2px;color:#667085;font-size:.8rem}
  .output-panel{overflow:hidden;border-radius:22px;background:linear-gradient(135deg,#09173c,#175184);color:#fff;box-shadow:0 18px 40px rgba(9,23,60,.18)}
  .output-copy{padding:44px}
  .output-copy h2{color:#fff;font-weight:800}
  .output-copy p{margin:0;color:#d8e6f3;line-height:1.75}
  .indicator-wrap{height:100%;padding:30px;background:rgba(255,255,255,.08)}
  .indicator{padding:18px;border:1px solid rgba(255,255,255,.15);border-radius:14px;background:rgba(255,255,255,.07)}
  .indicator+.indicator{margin-top:14px}
  .indicator strong{display:block;margin-bottom:6px;color:#8cc8ff;font-size:.74rem;text-transform:uppercase;letter-spacing:1px}
  .indicator p{margin:0;color:#fff;font-size:.88rem;line-height:1.55}
  .contact-card{display:flex;align-items:flex-start;gap:14px;height:100%;padding:22px;border:1px solid #dce5ee;border-radius:15px;background:#fff;text-decoration:none;transition:transform .2s,box-shadow .2s}
  .contact-card:hover{transform:translateY(-3px);box-shadow:0 10px 24px rgba(16,24,40,.08)}
  .contact-icon{width:44px;height:44px;flex:0 0 44px;display:flex;align-items:center;justify-content:center;border-radius:12px;background:#e8f2fb;color:#174f83;font-size:1.15rem}
  .contact-card small{display:block;color:#7a8698;font-weight:700;text-transform:uppercase;letter-spacing:.8px}
  .contact-card span{display:block;margin-top:4px;color:#172033;font-size:.9rem;font-weight:700;overflow-wrap:anywhere}
  @media(max-width:767.98px){.about-header{padding:105px 0 62px}.about-section{padding:58px 0}.mission-card,.output-copy{padding:27px}.org-chart-frame{padding:7px;border-radius:14px}}
</style>

<body id="page-top" class="about-page">
  <main>
    <header class="about-header">
      <div class="container">
        <div class="about-kicker"><i class="bi bi-geo-alt-fill"></i>LGU Panabo City</div>
        <h1>City Sports Development Program</h1>
        <p>Building unity, health, and sportsmanship through accessible and community-driven sports programs, training, and competitions.</p>
      </div>
    </header>

    <section class="about-section" aria-labelledby="program-title">
      <div class="container">
        <div class="row align-items-center g-5 mb-5">
          <div class="col-lg-5"><div class="section-kicker">Who we are</div><h2 class="section-title" id="program-title">Sports that bring communities together</h2></div>
          <div class="col-lg-7"><p class="section-copy mb-3">The City Sports Development Program is established and implemented under the Office of the City Mayor. It primarily aims to unite sports-active individuals, enthusiasts, communities, and school organizations by supporting them and initiating sports activities for interaction.</p><p class="section-copy mb-0">The program also advances health-related activities that promote camaraderie, sportsmanship, and discipline among participants.</p></div>
        </div>
        <div class="row g-4">
          <div class="col-md-4"><article class="mission-card"><div class="mission-icon"><i class="bi bi-people"></i></div><h3>Unity</h3><p>Connect residents, communities, schools, athletes, and sports enthusiasts through organized participation.</p></article></div>
          <div class="col-md-4"><article class="mission-card"><div class="mission-icon"><i class="bi bi-heart-pulse"></i></div><h3>Health</h3><p>Encourage sport, fitness, and active recreation as accessible pathways to healthier communities.</p></article></div>
          <div class="col-md-4"><article class="mission-card"><div class="mission-icon"><i class="bi bi-trophy"></i></div><h3>Sportsmanship</h3><p>Develop camaraderie, discipline, fair play, and appreciation among athletes and participants.</p></article></div>
        </div>
      </div>
    </section>

    <section class="about-section structure-section" aria-labelledby="structure-title">
      <div class="container">
        <div class="text-center mx-auto mb-5" style="max-width:680px"><div class="section-kicker">Organizational structure</div><h2 class="section-title" id="structure-title">Program leadership</h2><p class="section-copy">The program operates under the City Mayor’s Office with a dedicated sports-development team.</p></div>
        <figure class="org-chart-frame">
          <a class="org-chart-link" href="https://res.cloudinary.com/u3wlf2nk/image/upload/v1789449525/org_structure.jpg" target="_blank" rel="noopener" aria-label="Open the CSDP organizational structure at full size">
            <img class="org-chart-image" src="https://res.cloudinary.com/u3wlf2nk/image/upload/v1789449525/org_structure.jpg" alt="Organizational structure of the City Sports Development Program of Panabo City" loading="lazy">
          </a>
        </figure>
      </div>
    </section>

    <section class="about-section" aria-labelledby="output-title">
      <div class="container"><div class="output-panel"><div class="row g-0 align-items-stretch">
        <div class="col-lg-7"><div class="output-copy"><div class="about-kicker">Major final output</div><h2 class="mt-2" id="output-title">Accessible Sports Development</h2><p>Accessible Sports Development for Panabo City is an inclusive initiative that provides equal opportunities for residents to participate in sports programs, training, and competitions. It aims to reduce barriers such as distance, cost, and limited facilities by strengthening grassroots programs, improving venue access, and enabling athletes from all barangays to join organized activities for health, development, and talent discovery.</p></div></div>
        <div class="col-lg-5"><div class="indicator-wrap"><div class="indicator"><strong>Success indicator 01</strong><p>Number of persons participating in sport, fitness, and active recreation, disaggregated by gender, age, and education, within the target schedule.</p></div><div class="indicator"><strong>Success indicator 02</strong><p>Number of athletes participating in national sports within the target schedule.</p></div></div></div>
      </div></div></div>
    </section>

    <section class="about-section pt-0" aria-labelledby="contact-title">
      <div class="container"><div class="text-center mx-auto mb-4" style="max-width:640px"><div class="section-kicker">Contact us</div><h2 class="section-title" id="contact-title">Connect with CSDP Panabo City</h2></div>
        <div class="row g-3 justify-content-center">
          <div class="col-lg-4 col-md-6"><a class="contact-card" href="tel:+639460469934"><div class="contact-icon"><i class="bi bi-telephone"></i></div><div><small>Mobile</small><span>0946-046-9934</span></div></a></div>
          <div class="col-lg-4 col-md-6"><a class="contact-card" href="mailto:citysportspanabo@gmail.com"><div class="contact-icon"><i class="bi bi-envelope"></i></div><div><small>Email</small><span>citysportspanabo@gmail.com</span></div></a></div>
          <div class="col-lg-4 col-md-6"><div class="contact-card"><div class="contact-icon"><i class="bi bi-facebook"></i></div><div><small>Facebook</small><span>City Sports Development Program - CSDP- Panabo City</span></div></div></div>
        </div>
        <div class="text-center mt-4"><a href="leagues.php" class="btn btn-primary px-4">Explore available leagues <i class="bi bi-arrow-right ms-1"></i></a></div>
      </div>
    </section>
  </main>
  <?php include 'includes/footer.php'; ?>
</body>
</html>
