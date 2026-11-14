<?php
$url = $_GET['page'] ?? null;
?>
<?php include 'includes/header.php'; ?>
<style>
    :root{--primary:#09173c}
    body {
        /* Same canvas as every other page (see includes/header.php) */
        background: #ffffff;
        margin: 0;
        padding: 20px;
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow-x: hidden;
        font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
    }

    .container {
        background-color: #ffffff;
        padding: 0;
        border-radius: 10px;
        max-width: 420px;
        width: 100%;
        text-align: center;
        box-shadow: 0 10px 30px rgba(17,23,60,0.12);
        overflow: hidden;
    }

    .panel-top{background:var(--primary);padding:22px 18px 18px 18px;}
    .panel-top .logo-circle{width:64px;height:64px;border-radius:50%;background:#fff;margin:0 auto;display:flex;align-items:center;justify-content:center;padding:6px;box-shadow:0 6px 18px rgba(9,23,60,0.18)}
    .panel-top .logo-circle img{max-width:100%;max-height:100%;display:block}
    .panel-top h5{color:#fff;margin:10px 0 0;font-size:16px;letter-spacing:1px}

    .card-body{padding:22px}

    .form-label{font-size:13px;color:#334155;text-align:left;display:block;margin-bottom:6px}

    .input-group .form-control{border-left:0}
    .input-group .input-group-text{background:#fff;border-right:0;color:#475569;display:flex;align-items:center;justify-content:center}
    .input-group {display:flex;align-items:stretch}

    .password-input-group {
        position: relative;
        display: flex;
        align-items: center;
        flex: 1;
        width: 100%;
    }

    .password-input-group .form-control {
        padding-right: 40px;
    }

    .password-toggle-icon {
        position: absolute;
        right: 10px;
        cursor: pointer;
        color: #475569;
        background: none;
        border: none;
        padding: 4px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        z-index: 10;
    }

    .password-toggle-icon:hover {
        color: var(--primary);
    }

    .btn-primary{background:var(--primary);border:none;color:#fff;width:100%;font-weight:600;padding:12px;border-radius:8px}
    .btn-primary:hover{background:#07305a}

    .login-secondary-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:8px;margin-top:8px}
    .login-secondary-link{display:flex;align-items:center;justify-content:center;gap:7px;min-width:0;min-height:42px;padding:8px 10px;border:1px solid #dfe6ef;border-radius:8px;background:#f8fafc;color:#53617a;font-size:12px;font-weight:600;line-height:1.2;text-align:center;text-decoration:none}
    .login-secondary-link:hover{border-color:#cbd9ee;background:#eef4ff;color:var(--primary);text-decoration:none}
    .login-secondary-link i{flex:0 0 auto;font-size:13px}

    #result{color:#dc2626;margin-top:8px}

    @media (max-width:576px){.container{margin:10px}}
    


    @keyframes fadeInUp {
        0% {
            opacity: 0;
            transform: translateY(75px);
        }


        100% {
            opacity: 1;
            transform: translateY(0);
        }
    }
    .back-btn{
        position:fixed;
        top:20px;
        left:20px;
        z-index:1200;
        background:rgba(255,255,255,0.95);
        color:var(--primary);
        padding:8px 12px;
        border-radius:8px;
        box-shadow:0 6px 18px rgba(9,23,60,0.12);
        text-decoration:none;
        font-weight:600;
        display:inline-flex;
        align-items:center;
        gap:8px;
    }
    /* Captcha Card */
    .captcha-card{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;background:linear-gradient(135deg,#f0f4ff 0%,#e0e7ff 100%);border:1.5px solid #c7d2fe;border-radius:10px;margin:12px 0;box-shadow:0 2px 8px rgba(79,70,229,.08)}
    .captcha-card-icon{width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,#6366f1,#4f46e5);display:flex;align-items:center;justify-content:center;color:#fff;font-size:17px;flex-shrink:0;box-shadow:0 4px 10px rgba(99,102,241,.3)}
    .captcha-card-body{flex:1;min-width:0}
    .captcha-card-label{display:block;font-size:12.5px;font-weight:700;color:#312e81;margin:0 0 2px;letter-spacing:.3px}
    .captcha-card-hint{font-size:11px;color:#4338ca;margin:0 0 8px;font-weight:500}
    .captcha-widget{display:flex;justify-content:center;width:100%}
    #recaptcha-login{width:304px;transform:scale(.88);transform-origin:top center}
    @media (max-width:576px){
        .captcha-card{flex-direction:column;align-items:center}
        .captcha-card-body{width:100%;text-align:center}
        #recaptcha-login{transform:scale(.82)}
    }
    @media (max-width:360px){#recaptcha-login{transform:scale(.72)}}
    @media (max-width:576px){.back-btn{top:12px;left:12px;padding:6px 10px}}
</style>

<body>
    <a href="javascript:history.back()" class="back-btn" aria-label="Go back">&larr; Back</a>
    <div class="container">
        <div class="panel-top">
            <div class="logo-circle" aria-hidden="true">
                <img src="assets\images\logo.jpg" alt="CSDP Logo" width="55" height="55" class="d-inline-block align-middle m-2 rounded-circle bg-white" style="filter: drop-shadow(1px 1px 4px #ffffff);">
            </div>
            <h5>City Sport & Development</h5>
        </div>
        <div class="card-body">
            <form id="loginForm">
                <div class="mb-3">
                    <label for="id" class="form-label">Email Address</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-person-fill"></i></span>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="you@example.com">
                    </div>
                </div>
                <div class="mb-3">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-group">
                        <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                        <div class="password-input-group w-100">
                            <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                            <button type="button" class="password-toggle-icon" id="toggleShow" aria-label="Toggle password visibility">
                                <i class="bi bi-eye-fill"></i>
                            </button>
                        </div>
                    </div>
                </div>

                <input type="hidden" class="form-control" value="<?php echo $url; ?>" name="url">
                <div class="captcha-card">
                    <div class="captcha-card-icon">
                        <i class="bi bi-shield-lock"></i>
                    </div>
                    <div class="captcha-card-body">
                        <label class="captcha-card-label">Security Verification</label>
                        <p class="captcha-card-hint">Please verify you're not a robot</p>
                        <div class="captcha-widget">
                            <div id="recaptcha-login"></div>
                        </div>
                    </div>
                </div>
                <button type="submit" class="btn btn-primary">Login</button>
            </form>

            <div class="login-secondary-actions">
                <a class="login-secondary-link" data-bs-toggle="modal" data-bs-target="#signUp" href="#"><i class="bi bi-person-plus"></i><span>Create account</span></a>
                <a class="login-secondary-link" href="forgot-password.php"><i class="bi bi-key"></i><span>Forgot password</span></a>
            </div>
            <div id="result"></div>
        </div>
    </div>
    <?php
    include 'includes/footer.php';
    include 'components/modal/loginModal.php';
    ?>
    <script>
        // Render reCAPTCHA on main login form
        function renderRecaptchaLogin() {
            if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.render === 'function') {
                try {
                    recaptchaIds.login = grecaptcha.render('recaptcha-login', {
                        sitekey: '6LdzCKMtAAAAALZOBzK__d-Ow6iuZAWNZtjBMUDT',
                        theme: 'light'
                    });
                    console.log('reCAPTCHA login rendered, ID:', recaptchaIds.login);
                } catch(e) {
                    console.error('reCAPTCHA render error:', e);
                }
            }
        }

        // Try to render immediately or wait for the API
        if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.render === 'function') {
            renderRecaptchaLogin();
        } else {
            window.addEventListener('recaptcha-ready', renderRecaptchaLogin);
        }

        // Toggle password visibility
        document.getElementById('toggleShow').addEventListener('click', function(e){
            e.preventDefault();
            var pw = document.getElementById('password');
            var icon = this.querySelector('i');
            if (pw.type === 'password') { 
                pw.type = 'text'; 
                icon.classList.remove('bi-eye-fill');
                icon.classList.add('bi-eye-slash-fill');
            }
            else { 
                pw.type = 'password'; 
                icon.classList.remove('bi-eye-slash-fill');
                icon.classList.add('bi-eye-fill');
            }
        });

        // Debug: Check if form and jQuery are available
        console.log('Form element:', document.getElementById('loginForm'));
        console.log('jQuery available:', typeof jQuery !== 'undefined');
    </script>
</body>
