<?php
$lmsBase = rtrim(str_replace('\\', '/', substr(__DIR__, strlen($_SERVER['DOCUMENT_ROOT']))), '/');
$lmsBase = substr($lmsBase, 0, strrpos($lmsBase, '/')); // go up from /includes to /lms
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

    <title>LEAGUE MANAGEMENT SYSTEM </title>
    <link rel="icon" type="image/png" sizes="32x32" href="<?= htmlspecialchars($lmsBase) ?>/assets/images/favicon-circle.png">
    <link rel="shortcut icon" type="image/png" href="<?= htmlspecialchars($lmsBase) ?>/assets/images/favicon-circle.png">
    <!-- LMS floating toast notifications (shared component) -->
    <link rel="stylesheet" href="<?= htmlspecialchars($lmsBase) ?>/assets/css/lms-toast.css">
    <!-- LMS modal dialogs (shared component) -->
    <link rel="stylesheet" href="<?= htmlspecialchars($lmsBase) ?>/assets/css/lms-modal.css">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" type="text/css" href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/bootstrap/5.3.3/css/bootstrap.min.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" type="text/css" href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables/1.13.8/dataTables.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-buttons/2.4.2/buttons.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-select/1.7.0/select.bootstrap4.min.css">
    <link rel="stylesheet" type="text/css" href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-fixedheader/3.4.0/fixedHeader.bootstrap4.min.css">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/bootstrap-icons/1.11.3/bootstrap-icons.min.css">
    <!-- SB Admin 2 CSS -->
    <link href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/sb-admin-2/4.1.4/sb-admin-2.min.css" rel="stylesheet">
    <!-- Select2 CSS -->
    <link href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/select2/4.1.0-rc.0/select2.min.css" rel="stylesheet">
    <link href="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/select2-bootstrap4-theme/1.5.2/select2-bootstrap4.min.css" rel="stylesheet">
    
    <!-- Poppins Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        /* ------------------------------------------------------------------
           Canonical page canvas.
           Every page paints the same background as the public league views
           (team_information.php, participant.php, schedule.php, standing.php).
           Page-specific <style> blocks below may still override it, but new
           pages should not re-declare a background of their own.
           ------------------------------------------------------------------ */
        body {
            background-color: #ffffff;
        }

        .modal-header {
            background-color: #09173c;
            color: white;
        }

        .brand-text h5 {
            margin: 0;
            color: white;
            font-weight: 700;
            font-size: 15px;
        }

        .brand-text p {
            margin: 0;
            color: white;
            font-size: 12px;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .navbar-brand img {
            width: 40px;
            height: 40px;
            object-fit: cover;
            border-radius: 50%;
            overflow: hidden;
            box-shadow: 0 4px 12px rgba(0,0,0,0.18);
        }

        /*
           Floating confirmation toasts are styled by the shared component in
           assets/css/lms-toast.css (linked in the head above), so no toast
           colours are declared here anymore.
        */

        * {
            font-family: 'Poppins', sans-serif;
        }

        /* Prevent navigation buttons/links from being dragged or selected */
        .nav-link, .navbar-nav a, .sidelist a, .nav-item, .back-btn {
            -webkit-user-drag: none;
            -webkit-touch-callout: none;
            -webkit-user-select: none;
            -moz-user-select: none;
            -ms-user-select: none;
            user-select: none;
            touch-action: manipulation;
        }

        /* Reserve vertical scrollbar space to avoid layout shift when pages toggle scroll */
        html {
            overflow-y: scroll;
            scrollbar-gutter: stable;
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Hide WebKit scrollbars while preserving scrolling */
        html::-webkit-scrollbar { display: none; }

        /* Also hide scrollbars on any scrollable elements to avoid local shifts */
        *::-webkit-scrollbar { display: none; }
        * {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Force black text in all text fields */
        input, textarea, select,
        .form-control, .form-select,
        .form-control:focus, .form-select:focus,
        .form-control-plaintext,
        .input-group .form-control {
            color: #000 !important;
        }
    </style>

    <script>
        document.addEventListener('DOMContentLoaded', function(){
            document.querySelectorAll('.navbar-nav a, .nav-link, .sidelist a, .back-btn').forEach(function(el){
                try { el.setAttribute('draggable','false'); } catch(e){}
                el.addEventListener('dragstart', function(e){ e.preventDefault(); });
            });
        });
    </script>

    <!-- Google reCAPTCHA v2 -->
    <script>
        var recaptchaIds = {};
        function onRecaptchaLoad() {
            console.log('reCAPTCHA API loaded');
            // Dispatch custom event so other scripts know reCAPTCHA is ready
            window.dispatchEvent(new Event('recaptcha-ready'));
        }
    </script>
    <script src="https://www.google.com/recaptcha/api.js?onload=onRecaptchaLoad&render=explicit" async defer></script>

    <?php if (!empty($lmsPageCss)): ?>
        <?php foreach ((array) $lmsPageCss as $lmsCssFile): ?>
            <link rel="stylesheet" href="<?= htmlspecialchars($lmsBase) ?>/<?= htmlspecialchars(ltrim((string) $lmsCssFile, '/'), ENT_QUOTES, 'UTF-8') ?>">
        <?php endforeach; ?>
    <?php endif; ?>
</head>
