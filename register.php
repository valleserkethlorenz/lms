<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
require_once __DIR__ . '/config/auth_guard.php';

// Already signed in? Send the user straight to their workspace.
if (function_exists('currentUserId') && currentUserId() > 0) {
    $roleDashboards = [
        1 => 'app/organizer/dashboard.php',
        2 => 'app/tr/dashboard.php',
        3 => 'app/admin/dashboard.php',
        4 => 'app/committee/dashboard.php',
    ];
    $role = function_exists('currentRole') ? currentRole() : 0;
    header('Location: ' . ($roleDashboards[$role] ?? 'index.php'));
    exit;
}

$redirect = isset($_GET['redirect']) && is_string($_GET['redirect']) ? trim($_GET['redirect']) : '';
$loginHref = 'login.php' . ($redirect !== '' ? '?redirect=' . urlencode($redirect) : '');

// Authentication pages must always render the current markup/styles.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

$lmsPageCss = ['assets/css/auth-layout.css'];
include __DIR__ . '/includes/header.php';
?>
<style>
    /* The registration card follows the same flat card design as the
       login panel. The page-level split keeps both auth pages aligned. */

    /* Match the login page's two-panel proportions. Kept in the page itself
       as well as in auth-layout.css so a stale cached stylesheet cannot
       restore the old asymmetric register split. */
    @media (min-width: 1101px) {
        .auth-split.auth-split--register {
            grid-template-columns: minmax(0, 1.15fr) minmax(440px, 1fr) !important;
        }
    }
    @media (max-width: 1100px) {
        .auth-split.auth-split--register {
            grid-template-columns: minmax(0, 1fr) !important;
        }
    }
    .auth-split.auth-split--register > .auth-brand-panel,
    .auth-split.auth-split--register > .auth-form-panel {
        min-width: 0;
    }

    .signup-card{width:100%;max-width:620px;margin:0 auto;text-align:left}
    .signup-card .modal-content{width:100%;border:none;border-radius:18px;overflow:hidden;box-shadow:0 24px 64px rgba(9,23,60,.18)}
    .signup-card .modal-header{background:linear-gradient(135deg,#09173c 0%,#1a3a6b 100%);color:#fff;padding:26px 28px 22px;border:none;justify-content:center;flex-direction:column;align-items:center}
    .signup-card .signup-logo-circle{width:62px;height:62px;border-radius:50%;background:#fff;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;padding:5px;box-shadow:0 6px 20px rgba(9,23,60,.22);border:2px solid rgba(255,255,255,.18)}
    .signup-card .signup-logo-circle img{width:100%;height:100%;border-radius:50%;object-fit:cover;display:block}
    .signup-card .modal-title{margin:0;font-size:16px;font-weight:700;letter-spacing:.6px;text-align:center}
    .signup-card .signup-subtitle{color:rgba(255,255,255,.7);font-size:12px;margin-top:4px;font-weight:400}
    .signup-card .modal-body{padding:24px 30px 12px;font-family:'Poppins',sans-serif;max-height:none;overflow:visible}
    .signup-card .signup-section-header{display:flex;align-items:center;gap:10px;margin:18px 0 12px;padding-bottom:8px;border-bottom:2px solid #e8ecf1}
    .signup-card .signup-section-header:first-child{margin-top:4px}
    .signup-card .section-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;flex-shrink:0}
    .signup-card .section-icon.personal{background:linear-gradient(135deg,#3b82f6,#1d4ed8)}
    .signup-card .section-icon.security{background:linear-gradient(135deg,#10b981,#047857)}
    .signup-card .signup-section-header h6{margin:0;font-size:14px;font-weight:700;color:#1e293b;letter-spacing:.3px}
    .signup-card .form-label{font-size:12.5px;font-weight:600;color:#475569;margin-bottom:5px}
    .signup-card .form-control,.signup-card .form-select{min-height:42px;border:1.5px solid #d1d9e6;border-radius:10px;font-size:13.5px;padding:9px 14px;transition:border-color .2s,box-shadow .2s}
    .signup-card .form-control:focus,.signup-card .form-select:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
    .signup-card .input-group-text{background:#f1f5f9;border:1.5px solid #d1d9e6;border-right:none;border-radius:10px 0 0 10px;color:#64748b;width:40px;display:flex;align-items:center;justify-content:center;font-size:15px}
    .signup-card .input-group .form-control{border-left:none;border-radius:0 10px 10px 0;padding-left:6px}
    .signup-card .input-group .form-control:focus{border-left:none}
    .signup-card .input-group .form-select{border-left:none;border-radius:0 10px 10px 0;padding-left:6px}
    .signup-card .signup-pw-wrapper{position:relative;display:flex;align-items:stretch;flex:1}
    .signup-card .signup-pw-wrapper .form-control{padding-right:42px;border-radius:0 10px 10px 0}
    .signup-card .signup-pw-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;padding:4px;cursor:pointer;color:#94a3b8;font-size:16px;display:flex;align-items:center;justify-content:center;z-index:10;transition:color .2s}
    .signup-card .signup-pw-toggle:hover{color:#09173c}
    .signup-card .pw-checklist{margin-top:8px;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;display:grid;grid-template-columns:1fr 1fr;gap:4px 16px}
    .signup-card .pw-checklist .pw-check-item{font-size:11.5px;display:flex;align-items:center;gap:6px;transition:color .2s}
    .signup-card .pw-checklist .pw-check-item i{font-size:13px}
    .signup-card .pw-checklist .pw-check-item.met{color:#059669}
    .signup-card .pw-checklist .pw-check-item.met i{color:#059669}
    .signup-card .pw-checklist .pw-check-item.unmet{color:#94a3b8}
    .signup-card .pw-checklist .pw-check-item.unmet i{color:#cbd5e1}
    .signup-card .pw-match-indicator{margin-top:6px;font-size:12px;display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;transition:all .2s}
    .signup-card .pw-match-indicator.match{color:#059669;background:#ecfdf5}
    .signup-card .pw-match-indicator.no-match{color:#dc2626;background:#fef2f2}
    .signup-card .pw-match-indicator.hidden-indicator{display:none}
    .signup-card .btn-signup-close{border-radius:10px;font-weight:600;font-size:13.5px;padding:10px 24px;border:1.5px solid #d1d9e6;background:#fff;color:#475569;transition:all .2s;text-decoration:none;display:inline-flex;align-items:center;justify-content:center}
    .signup-card .btn-signup-close:hover{background:#f1f5f9;border-color:#94a3b8;color:#1e293b;text-decoration:none}
    .signup-card .btn-signup-submit{border-radius:10px;font-weight:600;font-size:13.5px;padding:10px 28px;background:linear-gradient(135deg,#09173c 0%,#1a3a6b 100%);border:none;color:#fff;transition:all .2s,transform .15s;box-shadow:0 4px 14px rgba(9,23,60,.18)}
    .signup-card .btn-signup-submit:hover{background:linear-gradient(135deg,#0f2a5c 0%,#1e4a7a 100%);transform:translateY(-1px);box-shadow:0 6px 20px rgba(9,23,60,.28)}
    .signup-card .btn-signup-submit:active{transform:translateY(0);box-shadow:0 2px 8px rgba(9,23,60,.18)}

    /* Signup Captcha Card Styles */
    .signup-card .signup-captcha-card{display:flex;align-items:center;gap:14px;padding:16px 18px;background:linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 100%);border:1.5px solid #bae6fd;border-radius:12px;margin-top:8px;box-shadow:0 2px 8px rgba(14,165,233,.08)}
    .signup-card .signup-captcha-icon{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#0ea5e9,#0284c7);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0;box-shadow:0 4px 12px rgba(14,165,233,.25)}
    .signup-card .signup-captcha-body{flex:1;min-width:0}
    .signup-card .signup-captcha-label{display:block;font-size:13px;font-weight:700;color:#0c4a6e;margin:0 0 2px;letter-spacing:.3px}
    .signup-card .signup-captcha-hint{font-size:11.5px;color:#0369a1;margin:0 0 10px;font-weight:500}
    .signup-card .signup-captcha-widget{display:flex;justify-content:center;width:100%}

    @media(max-width:575.98px){
        .signup-card{max-width:none}
        .signup-card .modal-content{border-radius:14px}
        .signup-card .modal-header{padding:16px 18px 14px}
        .signup-card .signup-logo-circle{width:48px;height:48px;margin-bottom:6px}
        .signup-card .modal-title{font-size:15px;line-height:1.3}
        .signup-card .signup-subtitle{font-size:11px}
        .signup-card .modal-body{padding:16px 16px 20px}
        .signup-card .btn-signup-close,.signup-card .btn-signup-submit{padding:9px 16px;font-size:13px}
        .signup-card .signup-captcha-card{flex-direction:column;align-items:center;gap:10px;padding:14px}
        .signup-card .signup-captcha-body{width:100%;text-align:center}
    }
    @media(max-width:480px){
        .signup-card .pw-checklist{grid-template-columns:1fr}
        .signup-card .signup-form-actions{flex-direction:column-reverse;align-items:stretch}
        .signup-card .signup-form-actions .btn{width:100%}
    }

    /* ── Two-step registration wizard ───────────────────────────── */
    .signup-card .signup-stepper{display:flex;align-items:center;gap:10px;list-style:none;margin:0 0 16px;padding:0}
    .signup-card .signup-stepper-item{display:flex;align-items:center;gap:8px;font-size:12.5px;font-weight:600;color:#94a3b8;white-space:nowrap}
    .signup-card .signup-stepper-dot{width:26px;height:26px;border-radius:50%;display:inline-flex;align-items:center;justify-content:center;background:#e2e8f0;color:#64748b;font-size:12px;font-weight:700;flex:0 0 auto}
    .signup-card .signup-stepper-item.is-active{color:#09173c}
    .signup-card .signup-stepper-item.is-active .signup-stepper-dot{background:#f59e0b;color:#fff;box-shadow:0 0 0 4px rgba(245,158,11,.16)}
    .signup-card .signup-stepper-item.is-complete{color:#09173c}
    .signup-card .signup-stepper-item.is-complete .signup-stepper-dot{background:#16a34a;color:#fff}
    .signup-card .signup-stepper-line{flex:1;height:2px;background:#e2e8f0;border-radius:2px}
    .signup-card .signup-step-pane{width:100%}
    .signup-card .signup-verify-wrap{max-width:560px;margin:0 auto;padding:2px 0 4px}
    .signup-card .signup-verify-head{display:flex;align-items:flex-start;gap:14px;margin-bottom:18px}
    .signup-card .signup-verify-icon{width:54px;height:54px;border-radius:14px;background:#eef2f7;color:#09173c;display:flex;align-items:center;justify-content:center;font-size:23px;flex:0 0 auto}
    .signup-card .signup-verify-title{margin:0 0 6px;font-size:22px;line-height:1.2;font-weight:700;color:#09173c;letter-spacing:-.2px}
    .signup-card .signup-verify-copy{margin:0;font-size:13.5px;line-height:1.6;color:#475569}
    .signup-card .signup-verify-field{margin-bottom:14px}
    .signup-card .signup-email-row{display:flex;gap:8px;align-items:stretch}
    .signup-card .signup-email-row .form-control{flex:1 1 auto;min-width:0}
    .signup-card .btn-signup-code{border-radius:10px;font-weight:600;font-size:13px;padding:0 18px;border:1.5px solid #09173c;background:#09173c;color:#fff;white-space:nowrap;transition:all .2s;min-height:42px}
    .signup-card .btn-signup-code:hover:not(:disabled){background:#07305a;border-color:#07305a;color:#fff}
    .signup-card .btn-signup-code:disabled{opacity:.65;cursor:not-allowed}
    .signup-card .signup-code-hint{margin:7px 0 0;font-size:11.5px;color:#64748b}
    .signup-card .btn-signup-verify{width:100%;margin-top:4px;border:none;border-radius:10px;padding:12px 20px;font-weight:600;font-size:14px;color:#fff;background:linear-gradient(135deg,#09173c 0%,#1a3a6b 100%);box-shadow:0 4px 14px rgba(9,23,60,.18);transition:all .2s}
    .signup-card .btn-signup-verify:hover:not(:disabled){transform:translateY(-1px);box-shadow:0 6px 20px rgba(9,23,60,.26)}
    .signup-card .btn-signup-verify:disabled{background:#cbd5e1;color:#64748b;box-shadow:none;cursor:not-allowed;transform:none}
    .signup-card .signup-alt-link{margin-top:14px;text-align:center;font-size:12.5px;color:#64748b}
    .signup-card .signup-alt-link a{color:#09173c;font-weight:600;text-decoration:none}
    .signup-card .signup-alt-link a:hover{text-decoration:underline}
    .signup-card .signup-verified-chip{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:0 0 4px;padding:10px 14px;border:1px solid #bbf7d0;border-radius:10px;background:#f0fdf4;color:#166534;font-size:12.5px;font-weight:600}
    .signup-card .signup-verified-chip .btn-link{padding:0;font-size:12px;font-weight:600;color:#09173c;text-decoration:none}
    .signup-card .signup-verified-chip .btn-link:hover{text-decoration:underline}
    @media(max-width:575.98px){
        .signup-card .signup-verify-title{font-size:19px}
        .signup-card .signup-verify-icon{width:46px;height:46px;font-size:20px}
        .signup-card .signup-email-row{flex-direction:column}
        .signup-card .btn-signup-code{min-height:42px;padding:10px 16px}
    }
    /* ── Login-consistent card styling ─────────────────────────────
       Keep the register wizard visually identical to the login panel:
       flat navy header, white card, same labels, inputs and buttons.
       This block intentionally comes last so it overrides the earlier
       wizard-specific styling. */
    .signup-card .modal-content{
        border-radius:10px;
        box-shadow:0 10px 30px rgba(17,23,60,.12);
    }
    .signup-card .modal-header{
        background:#09173c;
        padding:22px 18px 18px;
    }
    .signup-card .signup-logo-circle{
        width:64px;height:64px;margin:0 auto 10px;padding:6px;
        border:0;box-shadow:0 6px 18px rgba(9,23,60,.18);
    }
    .signup-card .modal-title{
        font-size:16px;font-weight:400;letter-spacing:1px;
    }
    .signup-card .modal-body{
        padding:22px;
    }
    .signup-card .signup-section-header{
        margin:18px 0 12px;padding-bottom:8px;border-bottom:1px solid #e8ecf1;
    }
    .signup-card .section-icon,
    .signup-card .section-icon.personal,
    .signup-card .section-icon.security{
        width:32px;height:32px;border-radius:8px;
        background:#eef2f7;color:#09173c;font-size:15px;
    }
    .signup-card .signup-section-header h6{
        font-size:14px;font-weight:600;color:#09173c;letter-spacing:.2px;
    }
    .signup-card .form-label{
        font-size:13px;font-weight:400;color:#334155;margin-bottom:6px;
    }
    .signup-card .form-control,
    .signup-card .form-select{
        min-height:38px;border:1px solid #d1d3e2;border-radius:5.6px;
        font-size:1rem;padding:.375rem .75rem;
    }
    .signup-card .form-control:focus,
    .signup-card .form-select:focus{
        border-color:#86b7fe;box-shadow:0 0 0 .25rem rgba(13,110,253,.25);
    }
    .signup-card .input-group-text{
        width:auto;background:#fff;border:1px solid #d1d3e2;border-right:0;
        border-radius:5.6px 0 0 5.6px;color:#475569;font-size:1rem;
        padding:.375rem .75rem;
    }
    .signup-card .input-group .form-control,
    .signup-card .input-group .form-select{
        border-left:0;border-radius:0 5.6px 5.6px 0;padding-left:.75rem;
    }
    .signup-card .signup-pw-wrapper .form-control{
        padding-right:42px;border-radius:0 5.6px 5.6px 0;
    }
    .signup-card .signup-pw-toggle{
        color:#475569;font-size:18px;
    }
    .signup-card .signup-pw-toggle:hover{color:#09173c;}
    .signup-card .pw-checklist{
        border-radius:8px;
    }
    .signup-card .pw-checklist .pw-check-item{font-size:12px;}
    .signup-card .btn-signup-close{
        border-radius:8px;font-size:12px;font-weight:600;
        padding:8px 14px;border:1px solid #dfe6ef;background:#f8fafc;color:#53617a;
        min-height:42px;
    }
    .signup-card .btn-signup-close:hover{
        background:#eef4ff;border-color:#cbd9ee;color:#09173c;
    }
    .signup-card .btn-signup-submit,
    .signup-card .btn-signup-verify{
        border-radius:8px;font-size:1rem;font-weight:600;
        padding:12px;background:#09173c;border:none;color:#fff;
        box-shadow:none;
    }
    .signup-card .btn-signup-submit:hover,
    .signup-card .btn-signup-verify:hover:not(:disabled){
        background:#07305a;border-color:#07305a;transform:none;box-shadow:none;
    }
    .signup-card .btn-signup-submit:active{transform:none;box-shadow:none;}
    .signup-card .btn-signup-verify:disabled{background:#e2e8f0;color:#64748b;box-shadow:none;}
    .signup-card .signup-captcha-card{
        gap:12px;padding:14px 16px;align-items:flex-start;
        background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;
        box-shadow:none;
    }
    .signup-card .signup-captcha-icon{
        width:36px;height:36px;font-size:17px;
        background:#09173c;color:#fff;border-radius:8px;box-shadow:none;
    }
    .signup-card .signup-captcha-label{font-size:12.5px;color:#334155;}
    .signup-card .signup-captcha-hint{font-size:11px;color:#64748b;}
    .signup-card .signup-stepper-item.is-active .signup-stepper-dot{
        background:#09173c;color:#fff;box-shadow:none;
    }
    .signup-card .signup-stepper-item.is-complete .signup-stepper-dot{
        background:#09173c;color:#fff;
    }
    .signup-card .signup-stepper-item{font-size:12px;}
    .signup-card .signup-verify-wrap{max-width:none;margin:0;padding:0;}
    .signup-card .signup-verify-head{gap:12px;margin-bottom:16px;}
    .signup-card .signup-verify-icon{
        width:46px;height:46px;border-radius:8px;background:#eef2f7;
        color:#09173c;font-size:18px;
    }
    .signup-card .signup-verify-title{
        font-size:16px;line-height:1.3;font-weight:600;letter-spacing:0;
        color:#09173c;margin:0 0 4px;
    }
    .signup-card .signup-verify-copy{
        font-size:13px;line-height:1.55;color:#64748b;
    }
    .signup-card .signup-code-hint{font-size:11px;color:#64748b;}
    .signup-card .btn-signup-code{
        border-radius:8px;font-size:12px;font-weight:600;
        padding:8px 14px;min-height:42px;
        border:1px solid #09173c;background:#09173c;color:#fff;
        white-space:nowrap;
    }
    .signup-card .btn-signup-code:hover:not(:disabled){
        background:#07305a;border-color:#07305a;color:#fff;
    }
    .signup-card .signup-verified-chip{
        background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;
        color:#334155;font-size:12px;padding:8px 12px;
    }
    .signup-card .signup-verified-chip .bi{color:#09173c;}
    .signup-card .signup-verified-chip .btn-link{font-size:12px;}
    .signup-card .signup-alt-link{font-size:12px;}
</style>

<body class="auth-page">
    <div class="auth-split auth-split--register">
        <?php
        include __DIR__ . '/components/auth/brand-panel.php';
        ?>

        <main class="auth-form-panel">
            <div class="signup-card">
                <div class="modal-content">
                    <div class="modal-header">
                        <div class="w-100 text-center">
                            <div class="signup-logo-circle">
                                <img src="<?= htmlspecialchars(($lmsBase ?? '') . '/assets/images/logo.jpg', ENT_QUOTES, 'UTF-8') ?>" alt="CSDP Logo">
                            </div>
                            <h5 class="modal-title">Register as Team Representative</h5>
                        </div>
                    </div>
                    <div class="modal-body">
                        <form class="row g-3 needs-validation" id="signUpForm" novalidate>
                            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') ?>">
                            <input type="hidden" name="redirect" value="<?= htmlspecialchars($redirect, ENT_QUOTES, 'UTF-8') ?>">

                            <!-- Step indicator -->
                            <div class="col-12">
                                <ol class="signup-stepper" id="signupStepper" aria-label="Registration progress">
                                    <li class="signup-stepper-item is-active" data-step="1" aria-current="step">
                                        <span class="signup-stepper-dot">1</span>
                                        <span class="signup-stepper-label">Verify email</span>
                                    </li>
                                    <li class="signup-stepper-line" aria-hidden="true"></li>
                                    <li class="signup-stepper-item" data-step="2">
                                        <span class="signup-stepper-dot">2</span>
                                        <span class="signup-stepper-label">Application</span>
                                    </li>
                                </ol>
                            </div>

                            <!-- Step 1: verify the email address -->
                            <div class="col-12 signup-step-pane" id="signupStepVerify">
                                <div class="signup-verify-wrap">
                                    <div class="signup-verify-head">
                                        <div class="signup-verify-icon"><i class="bi bi-envelope" aria-hidden="true"></i></div>
                                        <div>
                                            <h2 class="signup-verify-title">Verify your email</h2>
                                            <p class="signup-verify-copy">We'll send a 6-digit code to confirm your email before you set up your Team Representative account.</p>
                                        </div>
                                    </div>

                                    <div class="signup-verify-field">
                                        <label for="signupEmail" class="form-label">Email address<sup class="text-danger">*</sup></label>
                                        <div class="signup-email-row">
                                            <input type="text" class="form-control" id="signupEmail" name="email" required placeholder="you@example.com" pattern="^[a-zA-Z0-9]+@gmail\.com$" title="Please enter a valid email address ending with @gmail.com" autocomplete="email">
                                            <button type="button" class="btn btn-signup-code" id="signupSendCode">Send code</button>
                                        </div>
                                        <div class="invalid-feedback">Please enter a valid @gmail.com email address!</div>
                                    </div>

                                    <div class="signup-verify-field">
                                        <label for="signupCode" class="form-label">Verification code</label>
                                        <input type="text" class="form-control" id="signupCode" name="verification_code" inputmode="numeric" autocomplete="one-time-code" maxlength="6" pattern="\d{6}" placeholder="Send a code first" disabled>
                                        <p class="signup-code-hint" id="signupCodeHint" aria-live="polite">The code expires shortly after it is sent.</p>
                                    </div>

                                    <button type="button" class="btn btn-signup-verify" id="signupVerifyCode" disabled>Verify &amp; continue</button>

                                    <div class="signup-alt-link">
                                        Already have an account?
                                        <a href="<?= htmlspecialchars($loginHref, ENT_QUOTES, 'UTF-8') ?>">Sign in</a>
                                    </div>
                                </div>
                            </div>

                            <!-- Step 2: the application / account details -->
                            <div class="col-12 signup-step-pane d-none" id="signupStepApplication">
                                <div class="row g-3">
                                    <div class="col-12">
                                        <div class="signup-verified-chip">
                                            <span><i class="bi bi-patch-check-fill" aria-hidden="true"></i> Verified: <span id="signupVerifiedEmail"></span></span>
                                            <button type="button" class="btn btn-link" id="signupChangeEmail">Change</button>
                                        </div>
                                    </div>

                            <!-- Personal Information Section -->
                            <div class="col-12">
                                <div class="signup-section-header">
                                    <div class="section-icon personal"><i class="bi bi-person-fill"></i></div>
                                    <h6>Personal Information</h6>
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="validationServer02" class="form-label">First Name<sup class="text-danger">*</sup></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="validationServer02" name="fname" required placeholder="Enter first name" maxlength="16" oninput="this.value = this.value.replace(/[^a-z,A-Z ]/g, '');">
                                </div>
                                <div class="invalid-feedback">Please enter first name!</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="validationServer03" class="form-label">Last Name<sup class="text-danger">*</sup></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-person"></i></span>
                                    <input type="text" class="form-control" id="validationServer03" name="lname" required placeholder="Enter last name" maxlength="16" oninput="this.value = this.value.replace(/[^a-z,A-Z ]/g, '');">
                                </div>
                                <div class="invalid-feedback">Please enter last name!</div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="mname" class="form-label">Middle Initial</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                                    <input name="mname" class="form-control" id="mname" type="text" oninput="this.value = this.value.replace(/[^a-z,A-Z ]/g, '');" placeholder="M.I." maxlength="2">
                                </div>
                            </div>

                            <div class="col-12 col-md-6">
                                <label for="signupSuffix" class="form-label">Suffix</label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-tag"></i></span>
                                    <select class="form-select" id="signupSuffix" name="suffix">
                                        <option selected disabled>Select</option>
                                        <option value="Jr.">Jr.</option>
                                        <option value="Sr.">Sr.</option>
                                        <option value="I">I</option>
                                        <option value="II">II</option>
                                        <option value="III">III</option>
                                        <option value="IV">IV</option>
                                    </select>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="testingss" class="form-label">Phone<sup class="text-danger">*</sup></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-phone"></i></span>
                                    <input type="text" class="form-control" id="testingss" name="phone" required placeholder="09XXXXXXXXX" maxlength="11" pattern="\d{11}" oninput="validateInput11()">
                                </div>
                                <div class="invalid-feedback">Please enter a valid 11-digit phone number!</div>
                            </div>

                            <!-- Security Information Section -->
                            <div class="col-12" style="margin-top:6px;">
                                <div class="signup-section-header">
                                    <div class="section-icon security"><i class="bi bi-shield-lock-fill"></i></div>
                                    <h6>Security Information</h6>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="validationServers01" class="form-label">Password<sup class="text-danger">*</sup></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                                    <div class="signup-pw-wrapper">
                                        <input type="password" class="form-control" id="validationServers01" name="pass1" required placeholder="Create a strong password" minlength="8" maxlength="64" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}">
                                        <button type="button" class="signup-pw-toggle" id="signUpTogglePw1" aria-label="Toggle password visibility">
                                            <i class="bi bi-eye-fill"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="invalid-feedback">Must contain at least one number, one uppercase, one lowercase, one special character, and at least 8 characters</div>
                                <div class="pw-checklist" id="signUpPwChecklist">
                                    <div class="pw-check-item unmet" id="pwMinLen"><i class="bi bi-circle"></i> At least 8 characters</div>
                                    <div class="pw-check-item unmet" id="pwUpper"><i class="bi bi-circle"></i> Uppercase letter</div>
                                    <div class="pw-check-item unmet" id="pwLower"><i class="bi bi-circle"></i> Lowercase letter</div>
                                    <div class="pw-check-item unmet" id="pwNum"><i class="bi bi-circle"></i> Number</div>
                                    <div class="pw-check-item unmet" id="pwSpec"><i class="bi bi-circle"></i> Special character</div>
                                </div>
                            </div>

                            <div class="col-12">
                                <label for="validationServers02" class="form-label">Confirm Password<sup class="text-danger">*</sup></label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                                    <div class="signup-pw-wrapper">
                                        <input type="password" class="form-control" id="validationServers02" name="pass2" required placeholder="Re-enter your password" minlength="8" maxlength="64" pattern="(?=.*\d)(?=.*[a-z])(?=.*[A-Z])(?=.*[^A-Za-z0-9]).{8,}">
                                        <button type="button" class="signup-pw-toggle" id="signUpTogglePw2" aria-label="Toggle password visibility">
                                            <i class="bi bi-eye-fill"></i>
                                        </button>
                                    </div>
                                </div>
                                <div class="invalid-feedback">Must contain at least one number, one uppercase, one lowercase, one special character, and at least 8 characters</div>
                                <div class="pw-match-indicator hidden-indicator" id="signUpPwMatch">
                                    <i class="bi bi-circle"></i> <span>Passwords do not match</span>
                                </div>
                            </div>

                            <div class="col-12" style="margin-top:16px;">
                                <div class="signup-captcha-card">
                                    <div class="signup-captcha-icon">
                                        <i class="bi bi-shield-check"></i>
                                    </div>
                                    <div class="signup-captcha-body">
                                        <label class="signup-captcha-label">Security Verification</label>
                                        <p class="signup-captcha-hint">Please verify you're not a robot</p>
                                        <div class="signup-captcha-widget">
                                            <div id="recaptcha-signup"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12" style="margin-top:20px;">
                                <div class="d-flex justify-content-end gap-2 signup-form-actions">
                                    <a href="<?= htmlspecialchars($loginHref, ENT_QUOTES, 'UTF-8') ?>" class="btn btn-signup-close">Cancel</a>
                                    <button type="submit" class="btn btn-signup-submit" name="createUser"><i class="bi bi-person-plus-fill me-1"></i>Create Account</button>
                                </div>
                            </div>
                                </div><!-- /step 2 row -->
                            </div><!-- /signupStepApplication -->
                        </form>
                    </div>
                </div>
            </div>

            
        </main>
    </div>

    <?php include __DIR__ . '/includes/footer.php'; ?>
    <script src="<?= htmlspecialchars(($lmsBase ?? '') . '/assets/js/register-wizard.js', ENT_QUOTES, 'UTF-8') ?>"></script>

    <script>
    (function () {
        function getRecaptchaSize() {
            return window.matchMedia && window.matchMedia('(max-width: 575.98px)').matches ? 'compact' : 'normal';
        }

        var signupRendered = false;
        function renderRecaptchaSignup() {
            if (signupRendered) return;
            if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.render === 'function') {
                try {
                    recaptchaIds.signup = grecaptcha.render('recaptcha-signup', {
                        sitekey: '6LdzCKMtAAAAALZOBzK__d-Ow6iuZAWNZtjBMUDT',
                        theme: 'light',
                        size: getRecaptchaSize()
                    });
                    signupRendered = true;
                } catch (e) {
                    console.error('reCAPTCHA signup render error:', e);
                }
            }
        }

        // The widget is rendered when Step 2 (the application) is shown, so it
        // is laid out inside a visible container. Expose the renderer to the
        // wizard script (assets/js/register-wizard.js).
        window.renderSignupRecaptcha = renderRecaptchaSignup;
        window.addEventListener('recaptcha-ready', function () {
            if (window.signupEmailVerified) renderRecaptchaSignup();
        });

        /* Password eye-toggle for the registration form */
        function setupPwToggle(btnId, inputId) {
            var btn = document.getElementById(btnId);
            if (!btn) return;
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var pw = document.getElementById(inputId);
                var icon = this.querySelector('i');
                if (pw.type === 'password') {
                    pw.type = 'text';
                    icon.classList.remove('bi-eye-fill');
                    icon.classList.add('bi-eye-slash-fill');
                } else {
                    pw.type = 'password';
                    icon.classList.remove('bi-eye-slash-fill');
                    icon.classList.add('bi-eye-fill');
                }
            });
        }
        setupPwToggle('signUpTogglePw1', 'validationServers01');
        setupPwToggle('signUpTogglePw2', 'validationServers02');

        /* Live password strength checklist */
        var pw1 = document.getElementById('validationServers01');
        var checks = {
            pwMinLen: function (v) { return v.length >= 8; },
            pwUpper:  function (v) { return /[A-Z]/.test(v); },
            pwLower:  function (v) { return /[a-z]/.test(v); },
            pwNum:    function (v) { return /\d/.test(v); },
            pwSpec:   function (v) { return /[^A-Za-z0-9]/.test(v); }
        };
        if (pw1) {
            pw1.addEventListener('input', function () {
                var v = this.value;
                for (var id in checks) {
                    var el = document.getElementById(id);
                    if (!el) continue;
                    var met = checks[id](v);
                    el.classList.toggle('met', met);
                    el.classList.toggle('unmet', !met);
                    var icon = el.querySelector('i');
                    if (icon) {
                        icon.className = met ? 'bi bi-check-circle-fill' : 'bi bi-circle';
                    }
                }
            });
        }

        /* Live password match indicator */
        var pw2 = document.getElementById('validationServers02');
        var matchEl = document.getElementById('signUpPwMatch');
        function checkMatch() {
            if (!pw1 || !pw2 || !matchEl) return;
            var v1 = pw1.value, v2 = pw2.value;
            if (!v2) {
                matchEl.className = 'pw-match-indicator hidden-indicator';
                return;
            }
            var span = matchEl.querySelector('span');
            var icon = matchEl.querySelector('i');
            if (v1 === v2) {
                matchEl.className = 'pw-match-indicator match';
                if (span) span.textContent = 'Passwords match';
                if (icon) icon.className = 'bi bi-check-circle-fill';
            } else {
                matchEl.className = 'pw-match-indicator no-match';
                if (span) span.textContent = 'Passwords do not match';
                if (icon) icon.className = 'bi bi-x-circle-fill';
            }
        }
        if (pw1) pw1.addEventListener('input', checkMatch);
        if (pw2) pw2.addEventListener('input', checkMatch);
    })();
    </script>
</body>
</html>




