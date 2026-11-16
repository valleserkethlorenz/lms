<?php
$lmsBase = rtrim(str_replace('\\', '/', substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']))), '/');
$lmsBase = substr($lmsBase, 0, strrpos($lmsBase, '/')); // includes -> tr
$lmsBase = substr($lmsBase, 0, strrpos($lmsBase, '/')); // tr -> app
$lmsBase = substr($lmsBase, 0, strrpos($lmsBase, '/')); // app -> lms
?>
<!DOCTYPE html>
<html lang="en">

<head>

  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no, viewport-fit=cover">
  <meta name="theme-color" content="#09173c">
  <meta name="description" content="">
  <meta name="author" content="">
  <title> TEAM REPRESENTATIVE </title>
  <link rel="icon" type="image/png" href="<?= htmlspecialchars($lmsBase) ?>/assets/images/favicon-circle.png">
  <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($lmsBase) ?>/assets/images/favicon-circle.png">
  <!-- LMS floating toast notifications (shared component) -->
  <link rel="stylesheet" href="<?= htmlspecialchars($lmsBase) ?>/assets/css/lms-toast.css">
  <!-- LMS modal dialogs (shared component) -->
  <link rel="stylesheet" href="<?= htmlspecialchars($lmsBase) ?>/assets/css/lms-modal.css">

  <!-- Bootstrap CSS -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
  <!-- DataTables CSS -->
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/1.13.8/css/dataTables.bootstrap4.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/buttons/2.4.2/css/buttons.bootstrap4.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/select/1.7.0/css/select.bootstrap4.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.datatables.net/fixedheader/3.4.1/css/fixedHeader.bootstrap4.min.css">
  <!-- Alertify CSS -->
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/alertify.min.css">
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/alertifyjs@1.13.1/build/css/themes/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- SB Admin 2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/css/sb-admin-2.min.css" rel="stylesheet">
  <!-- Select2 CSS -->
  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/@ttskch/select2-bootstrap4-theme@1.5.2/dist/select2-bootstrap4.min.css" rel="stylesheet">
  <!-- Poppins Font -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet">
  <style>
    * { font-family: 'Poppins', sans-serif; }
    body { background-color: #ffffff; font-size: 15px; font-weight: 400; }
    h1, .h1 { font-size: 1.7rem; font-weight: 500; }
    h2, .h2 { font-size: 1.5rem; font-weight: 500; }
    h3, .h3 { font-size: 1.3rem; font-weight: 500; }
    h4, .h4 { font-size: 1.15rem; font-weight: 500; }
    h5, .h5 { font-size: 1rem; font-weight: 500; }
    h6, .h6 { font-size: 0.95rem; font-weight: 500; }
    .form-label { color: #495057; font-weight: 500; font-size: 13px; text-transform: none; }
    .fw-bold, .font-weight-bold, b, strong { font-weight: 500 !important; }
    .cards { border-radius: 10px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); }
    .card-headers { border-radius: 8px; }
    .table { font-size: 15px; }
    .btn { font-size: 14px; font-weight: 400; }
    .badge { font-weight: 500; font-size: 12px; }
    .navbar h3, .navbar .h3 { letter-spacing: 1px; font-weight: 500; font-size: 1rem; }
    .card-header h4, .card-header .h4 { letter-spacing: 0.5px; font-weight: 500; font-size: 0.95rem; }
    .sidebar-heading { font-size: 11px; font-weight: 500; letter-spacing: 0.5px; }

    /* Force black text in all text fields */
    input, textarea, select,
    .form-control, .form-select,
    .form-control:focus, .form-select:focus,
    .form-control-plaintext,
    .input-group .form-control {
      color: #000 !important;
    }

    /* ---------- Shared section headings -------------------------------- */
    .section-head {
      display: flex; align-items: center; justify-content: space-between;
      gap: 16px; margin: 0 2px 14px; width: 100%;
    }
    .section-head > div { min-width: 0; }
    .section-head.is-flush { margin: 0; }
    .section-eyebrow {
      display: block; font-size: 10px; font-weight: 600;
      letter-spacing: .16em; text-transform: uppercase; color: #75809a;
    }
    .section-title,
    .activity-title {
      display: flex; align-items: center; gap: 9px;
      color: #09173c; font-size: 18px; font-weight: 600;
      line-height: 1.25; letter-spacing: -.025em;
    }
    .section-title { margin: 5px 0 0; }
    .activity-title { margin: 0; }
    .section-title::before,
    .activity-title::before {
      content: ""; width: 3px; height: 16px; border-radius: 2px;
      background: #2f6fed; flex-shrink: 0;
    }
    .section-meta {
      display: inline-flex; align-items: center; gap: 6px;
      color: #6f7a8d; font-size: 11.5px; font-weight: 500; white-space: nowrap;
    }
    .section-live-dot {
      width: 6px; height: 6px; border-radius: 50%; background: #34d399;
      box-shadow: 0 0 0 3px rgba(52,211,153,.16);
      animation: sectionPulse 2.4s ease-in-out infinite;
    }

    /* ---------- Shared hero deck ----------------------------------------
       One banner design for every team representative page. Pages render it
       through includes/page-hero.php so the markup can never drift again. */
    .page-header {
      position: relative; isolation: isolate; overflow: hidden;
      display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between;
      gap: 24px; padding: 28px 32px; margin-bottom: 18px;
      border-radius: 14px; color: #fff;
      background: radial-gradient(140% 190% at 100% 0%, #1d4390 0%, #0c2050 46%, #071129 100%);
      box-shadow: 0 22px 44px -30px rgba(7,17,41,.9);
      animation: heroIn .5s ease-out both;
    }
    .page-header::before,
    .page-header::after {
      content: ""; position: absolute; z-index: -1; border-radius: 50%;
      border: 1px solid rgba(255,255,255,.10); pointer-events: none;
    }
    .page-header::before { top: -170px; right: -110px; width: 420px; height: 420px; }
    .page-header::after  { bottom: -240px; right: 80px; width: 360px; height: 360px; border-color: rgba(255,255,255,.06); }
    .page-header-court {
      position: absolute; z-index: -1; top: -24px; bottom: -24px; left: 58%;
      width: 1px; background: rgba(255,255,255,.08);
    }
    .page-header-copy {
      position: relative; z-index: 1; display: flex; align-items: center;
      gap: 16px; flex: 1 1 320px; min-width: 0; max-width: 64ch;
    }
    .page-header-text { min-width: 0; }
    .page-header-icon {
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
      width: 50px; height: 50px; border-radius: 12px;
      background: rgba(255,255,255,.10); border: 1px solid rgba(255,255,255,.14);
      font-size: 1.35rem; color: #fff;
    }
    .page-header .section-eyebrow {
      display: inline-flex; align-items: center; gap: 9px;
      margin: 0 0 10px; font-size: 9.5px; font-weight: 600;
      letter-spacing: .2em; text-transform: uppercase; color: rgba(255,255,255,.55);
    }
    .page-header .section-eyebrow::before {
      content: ""; width: 20px; height: 1px; background: rgba(255,255,255,.45);
    }
    .page-header-title {
      margin: 0 0 6px; color: #fff;
      font-size: clamp(1.5rem, 2.3vw, 2rem); font-weight: 600;
      line-height: 1.16; letter-spacing: -.015em;
    }
    .page-header-title span { color: #93b8ff; }
    .page-header-sub {
      margin: 0; max-width: 56ch;
      color: rgba(255,255,255,.66); font-size: 13px; line-height: 1.6;
    }
    .page-header-side {
      position: relative; z-index: 1; display: flex;
      align-items: stretch; gap: 10px; flex-wrap: wrap;
    }
    .page-header-actions {
      position: relative; z-index: 1; display: flex;
      align-items: center; gap: 10px; flex-wrap: wrap;
    }
    .page-header-btn {
      display: inline-flex; align-items: center; justify-content: center; gap: 6px;
      padding: 10px 18px; border: 1px solid #fff; border-radius: 8px;
      background: #fff; color: #09173c;
      font-size: 12px; font-weight: 600; letter-spacing: .01em;
      line-height: 1.2; white-space: nowrap; text-decoration: none !important; cursor: pointer;
      transition: background .15s ease, color .15s ease, transform .15s ease;
    }
    .page-header-btn:hover { background: #e9effa; border-color: #e9effa; color: #09173c; transform: translateY(-1px); }
    .page-header-btn:focus-visible { outline: 2px solid #93b8ff; outline-offset: 3px; }
    @keyframes heroIn { from { opacity: 0; transform: translateY(16px); } to { opacity: 1; transform: translateY(0); } }
    @keyframes sectionPulse { 0%,100% { opacity: 1; } 50% { opacity: .4; } }

    @media (max-width: 991.98px) {
      .page-header { padding: 24px 26px; }
    }
    @media (max-width: 767.98px) {
      .page-header { padding: 22px 20px; border-radius: 12px; gap: 18px; }
      .page-header-copy { flex: 1 1 100%; }
      .page-header-title { font-size: 1.35rem; }
      .page-header-side,
      .page-header-actions { width: 100%; }
      .page-header-actions .page-header-btn { flex: 1 1 100%; }
      .section-head { flex-direction: column; align-items: flex-start; gap: 8px; }
      .section-meta { white-space: normal; }
    }
    @media (max-width: 575.98px) {
      .page-header-copy { align-items: flex-start; gap: 12px; }
      .page-header-icon { width: 42px; height: 42px; font-size: 1.15rem; }
    }
    @media (prefers-reduced-motion: reduce) {
      .page-header { animation: none; }
      .section-live-dot { animation: none; }
    }

    /* TR-specific: registration status badges */
    .badge.registration-status-approved { background:#e8f7ee !important; color:#18794e !important; border-color:#a9dfbf !important; }
    .badge.registration-status-pending { background:#fff4d6 !important; color:#8a5a00 !important; border-color:#f1d17b !important; }
    .badge.registration-status-rejected { background:#fde8ec !important; color:#b42335 !important; border-color:#f3b7c1 !important; }
    .badge.registration-status-cancelled { background:#eef1f4 !important; color:#4b5563 !important; border-color:#cbd2da !important; }
    .badge.registration-status-correction { background:#f1eafe !important; color:#6941c6 !important; border-color:#d4c2f4 !important; }
    .badge.registration-status-expired { background:#f3f4f6 !important; color:#374151 !important; border-color:#9ca3af !important; }
    .badge.registration-status-default { background:#eaf2fb !important; color:#245b91 !important; border-color:#bfd5ea !important; }
  </style>
</head>
