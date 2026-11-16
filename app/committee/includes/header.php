<?php
$lmsBase = rtrim(str_replace('\\', '/', substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']))), '/');
$lmsBase = substr($lmsBase, 0, strrpos($lmsBase, '/')); // includes -> committee
$lmsBase = substr($lmsBase, 0, strrpos($lmsBase, '/')); // committee -> app
$lmsBase = substr($lmsBase, 0, strrpos($lmsBase, '/')); // app -> lms
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>COMMITTEE</title>
  <link rel="icon" type="image/png" href="<?= htmlspecialchars($lmsBase) ?>/assets/images/favicon-circle.png">
  <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($lmsBase) ?>/assets/images/favicon-circle.png">
  <!-- LMS floating toast notifications (shared component) -->
  <link rel="stylesheet" href="<?= htmlspecialchars($lmsBase) ?>/assets/css/lms-toast.css">
  <!-- LMS modal dialogs (shared component) -->
  <link rel="stylesheet" href="<?= htmlspecialchars($lmsBase) ?>/assets/css/lms-modal.css">
  <!-- Bootstrap CSS -->
  <link href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css" rel="stylesheet">
  <!-- DataTables CSS -->
  <link rel="stylesheet" type="text/css" href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables/1.13.8/dataTables.bootstrap4.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css">
  <!-- SB Admin 2 CSS -->
  <link href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/sb-admin-2/4.1.4/sb-admin-2.min.css" rel="stylesheet">
  <!-- Select2 CSS -->
  <link href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/select2/4.1.0-rc.0/select2.min.css" rel="stylesheet">
  <link href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/select2-bootstrap4-theme/1.5.2/select2-bootstrap4.min.css" rel="stylesheet">
  <!-- DataTables Buttons CSS -->
  <link rel="stylesheet" type="text/css" href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-buttons/2.4.2/buttons.bootstrap4.min.css">
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

    /* Shared Page Header — gradient banner used on all committee pages */
    .page-header {
      background: linear-gradient(135deg, #09173c 0%, #1a3a7c 100%);
      border-radius: 10px;
      padding: 24px 28px;
      margin-bottom: 20px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      box-shadow: 0 4px 16px rgba(9,23,60,0.12);
      flex-wrap: wrap;
      gap: 12px;
    }
    .page-header-icon {
      width: 52px; height: 52px; border-radius: 10px;
      background: rgba(255,255,255,0.12);
      display: flex; align-items: center; justify-content: center;
      font-size: 1.6rem; color: #fff;
    }
    .page-header-title {
      color: #fff; font-weight: 600; margin: 0;
      font-size: 1.4rem; letter-spacing: 0.5px;
    }
    .page-header-sub {
      color: rgba(255,255,255,0.6); font-size: 12px; margin-top: 2px;
    }
    .page-header-btn {
      background: rgba(255,255,255,0.15); color: #fff;
      border: 1.5px solid rgba(255,255,255,0.3);
      border-radius: 6px; padding: 9px 22px;
      font-size: 13px; font-weight: 500; letter-spacing: 0.3px;
      transition: all 0.15s ease;
    }
    .page-header-btn:hover {
      background: rgba(255,255,255,0.25); color: #fff;
      transform: translateY(-1px);
    }
  </style>
</head>
