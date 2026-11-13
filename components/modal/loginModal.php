   <style>
       #signUp .modal-dialog{max-width:780px}
       #signUp .modal-content{border:none;border-radius:18px;overflow:hidden;box-shadow:0 24px 64px rgba(9,23,60,.24)}
       #signUp .modal-header{background:linear-gradient(135deg,#09173c 0%,#1a3a6b 100%);color:#fff;padding:26px 28px 22px;border:none;justify-content:center;flex-direction:column;align-items:center}
       #signUp .signup-logo-circle{width:62px;height:62px;border-radius:50%;background:#fff;margin:0 auto 10px;display:flex;align-items:center;justify-content:center;padding:5px;box-shadow:0 6px 20px rgba(9,23,60,.22);border:2px solid rgba(255,255,255,.18)}
       #signUp .signup-logo-circle img{width:100%;height:100%;border-radius:50%;object-fit:cover;display:block}
       #signUp .modal-title{margin:0;font-size:16px;font-weight:700;letter-spacing:.6px;text-align:center}
       #signUp .signup-subtitle{color:rgba(255,255,255,.7);font-size:12px;margin-top:4px;font-weight:400}
       #signUp .modal-body{padding:24px 30px 12px;font-family:'Poppins',sans-serif;max-height:calc(100vh - 200px);overflow-y:auto}
       #signUp .signup-section-header{display:flex;align-items:center;gap:10px;margin:18px 0 12px;padding-bottom:8px;border-bottom:2px solid #e8ecf1}
       #signUp .signup-section-header:first-child{margin-top:4px}
       #signUp .section-icon{width:34px;height:34px;border-radius:10px;display:flex;align-items:center;justify-content:center;font-size:16px;color:#fff;flex-shrink:0}
       #signUp .section-icon.personal{background:linear-gradient(135deg,#3b82f6,#1d4ed8)}
       #signUp .section-icon.security{background:linear-gradient(135deg,#10b981,#047857)}
       #signUp .signup-section-header h6{margin:0;font-size:14px;font-weight:700;color:#1e293b;letter-spacing:.3px}
       #signUp .form-label{font-size:12.5px;font-weight:600;color:#475569;margin-bottom:5px}
       #signUp .form-control,#signUp .form-select{min-height:42px;border:1.5px solid #d1d9e6;border-radius:10px;font-size:13.5px;padding:9px 14px;transition:border-color .2s,box-shadow .2s}
       #signUp .form-control:focus,#signUp .form-select:focus{border-color:#3b82f6;box-shadow:0 0 0 3px rgba(59,130,246,.1)}
       #signUp .input-group-text{background:#f1f5f9;border:1.5px solid #d1d9e6;border-right:none;border-radius:10px 0 0 10px;color:#64748b;width:40px;display:flex;align-items:center;justify-content:center;font-size:15px}
       #signUp .input-group .form-control{border-left:none;border-radius:0 10px 10px 0;padding-left:6px}
       #signUp .input-group .form-control:focus{border-left:none}
       #signUp .input-group .form-select{border-left:none;border-radius:0 10px 10px 0;padding-left:6px}
   </style>
   <style>
       #signUp .signup-pw-wrapper{position:relative;display:flex;align-items:stretch;flex:1}
       #signUp .signup-pw-wrapper .form-control{padding-right:42px;border-radius:0 10px 10px 0}
       #signUp .signup-pw-toggle{position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;padding:4px;cursor:pointer;color:#94a3b8;font-size:16px;display:flex;align-items:center;justify-content:center;z-index:10;transition:color .2s}
       #signUp .signup-pw-toggle:hover{color:#09173c}
       #signUp .pw-checklist{margin-top:8px;padding:10px 14px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;display:grid;grid-template-columns:1fr 1fr;gap:4px 16px}
       #signUp .pw-checklist .pw-check-item{font-size:11.5px;display:flex;align-items:center;gap:6px;transition:color .2s}
       #signUp .pw-checklist .pw-check-item i{font-size:13px}
       #signUp .pw-checklist .pw-check-item.met{color:#059669}
       #signUp .pw-checklist .pw-check-item.met i{color:#059669}
       #signUp .pw-checklist .pw-check-item.unmet{color:#94a3b8}
       #signUp .pw-checklist .pw-check-item.unmet i{color:#cbd5e1}
       #signUp .pw-match-indicator{margin-top:6px;font-size:12px;display:flex;align-items:center;gap:6px;padding:6px 12px;border-radius:8px;transition:all .2s}
       #signUp .pw-match-indicator.match{color:#059669;background:#ecfdf5}
       #signUp .pw-match-indicator.no-match{color:#dc2626;background:#fef2f2}
       #signUp .pw-match-indicator.hidden-indicator{display:none}
       #signUp .modal-footer{border-top:1px solid #e8ecf1;padding:14px 30px;display:flex;justify-content:flex-end;gap:10px;background:#fff}
       #signUp .btn-signup-close{border-radius:10px;font-weight:600;font-size:13.5px;padding:10px 24px;border:1.5px solid #d1d9e6;background:#fff;color:#475569;transition:all .2s}
       #signUp .btn-signup-close:hover{background:#f1f5f9;border-color:#94a3b8;color:#1e293b}
       #signUp .btn-signup-submit{border-radius:10px;font-weight:600;font-size:13.5px;padding:10px 28px;background:linear-gradient(135deg,#09173c 0%,#1a3a6b 100%);border:none;color:#fff;transition:all .2s,transform .15s;box-shadow:0 4px 14px rgba(9,23,60,.18)}
       #signUp .btn-signup-submit:hover{background:linear-gradient(135deg,#0f2a5c 0%,#1e4a7a 100%);transform:translateY(-1px);box-shadow:0 6px 20px rgba(9,23,60,.28)}
       #signUp .btn-signup-submit:active{transform:translateY(0);box-shadow:0 2px 8px rgba(9,23,60,.18)}
       #signUp .modal.show .modal-dialog{animation:signUpFadeIn .35s ease-out}
       @keyframes signUpFadeIn{from{opacity:0;transform:translateY(30px) scale(.97)}to{opacity:1;transform:translateY(0) scale(1)}}
    </style>
    <style>
        /* Signup Captcha Card Styles */
        #signUp .signup-captcha-card{display:flex;align-items:center;gap:14px;padding:16px 18px;background:linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 100%);border:1.5px solid #bae6fd;border-radius:12px;margin-top:8px;box-shadow:0 2px 8px rgba(14,165,233,.08)}
        #signUp .signup-captcha-icon{width:38px;height:38px;border-radius:10px;background:linear-gradient(135deg,#0ea5e9,#0284c7);display:flex;align-items:center;justify-content:center;color:#fff;font-size:18px;flex-shrink:0;box-shadow:0 4px 12px rgba(14,165,233,.25)}
        #signUp .signup-captcha-body{flex:1;min-width:0}
        #signUp .signup-captcha-label{display:block;font-size:13px;font-weight:700;color:#0c4a6e;margin:0 0 2px;letter-spacing:.3px}
        #signUp .signup-captcha-hint{font-size:11.5px;color:#0369a1;margin:0 0 10px;font-weight:500}
        #signUp .signup-captcha-body > div{transform:scale(.92);transform-origin:top left}
        
        /* Login Captcha Card Styles */
        #loginModal .login-captcha-card{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;background:linear-gradient(135deg,#fef3c7 0%,#fde68a 100%);border:1.5px solid #fcd34d;border-radius:10px;margin-top:16px;box-shadow:0 2px 8px rgba(245,158,11,.1)}
        #loginModal .login-captcha-icon{width:36px;height:36px;border-radius:8px;background:linear-gradient(135deg,#f59e0b,#d97706);display:flex;align-items:center;justify-content:center;color:#fff;font-size:17px;flex-shrink:0;box-shadow:0 4px 10px rgba(245,158,11,.3)}
        #loginModal .login-captcha-body{flex:1;min-width:0}
        #loginModal .login-captcha-label{display:block;font-size:12.5px;font-weight:700;color:#78350f;margin:0 0 2px;letter-spacing:.3px}
        #loginModal .login-captcha-hint{font-size:11px;color:#92400e;margin:0 0 8px;font-weight:500}
        #loginModal .login-captcha-body > div{transform:scale(.88);transform-origin:top left}
    </style>
   <div class="modal fade" id="signUp" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="signUp" aria-hidden="true">
       <div class="modal-dialog">
           <div class="modal-content">
               <div class="modal-header">
                   <div class="w-100 text-center">
                       <div class="signup-logo-circle">
                           <img src="assets/images/logo.jpg" alt="CSDP Logo">
                       </div>
                       <h5 class="modal-title">Create Team Representative Account</h5>
                       <div class="signup-subtitle">Register to manage your team and leagues</div>
                   </div>
               </div>
               <div class="modal-body">
                   <form class="row g-3 needs-validation" id="signUpForm" novalidate>

                       <!-- Personal Information Section -->
                       <div class="col-12">
                           <div class="signup-section-header">
                               <div class="section-icon personal"><i class="bi bi-person-fill"></i></div>
                               <h6>Personal Information</h6>
                           </div>
                       </div>

                       <div class="col-md-6">
                           <label for="validationServer02" class="form-label">First Name<sup class="text-danger">*</sup></label>
                           <div class="input-group">
                               <span class="input-group-text"><i class="bi bi-person"></i></span>
                               <input type="text" class="form-control" id="validationServer02" name="fname" required placeholder="Enter first name" maxlength="16" oninput="this.value = this.value.replace(/[^a-z,A-Z ]/g, '');">
                           </div>
                           <div class="invalid-feedback">Please enter first name!</div>
                       </div>

                       <div class="col-md-6">
                           <label for="validationServer03" class="form-label">Last Name<sup class="text-danger">*</sup></label>
                           <div class="input-group">
                               <span class="input-group-text"><i class="bi bi-person"></i></span>
                               <input type="text" class="form-control" id="validationServer03" name="lname" required placeholder="Enter last name" maxlength="16" oninput="this.value = this.value.replace(/[^a-z,A-Z ]/g, '');">
                           </div>
                           <div class="invalid-feedback">Please enter last name!</div>
                       </div>

                       <div class="col-md-6">
                           <label for="mname" class="form-label">Middle Initial</label>
                           <div class="input-group">
                               <span class="input-group-text"><i class="bi bi-card-text"></i></span>
                               <input name="mname" class="form-control" id="mname" type="text" oninput="this.value = this.value.replace(/[^a-z,A-Z ]/g, '');" placeholder="M.I." maxlength="2">
                           </div>
                       </div>

                       <div class="col-md-6">
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

                       <div class="col-md-6">
                           <label for="testingss" class="form-label">Phone<sup class="text-danger">*</sup></label>
                           <div class="input-group">
                               <span class="input-group-text"><i class="bi bi-phone"></i></span>
                               <input type="text" class="form-control" id="testingss" name="phone" required placeholder="09XXXXXXXXX" maxlength="11" pattern="\d{11}" oninput="validateInput11()">
                           </div>
                           <div class="invalid-feedback">Please enter a valid 11-digit phone number!</div>
                       </div>

                       <div class="col-md-6">
                           <label for="validationServer04" class="form-label">Email<sup class="text-danger">*</sup></label>
                           <div class="input-group">
                               <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                               <input type="text" class="form-control" id="validationServer04" name="email" required placeholder="example@gmail.com" pattern="^[a-zA-Z0-9]+@gmail\.com$" title="Please enter a valid email address ending with @gmail.com">
                           </div>
                           <div class="invalid-feedback">Please enter a valid @gmail.com email address!</div>
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
                            <div class="invalid-feedback">Must contain at least one number, one uppercase, one lowercase, one special character, and at least 8 characters</div>
                           </div>
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
                                    <div id="recaptcha-signup"></div>
                                </div>
                            </div>
                        </div>
                        <div class="col-12" style="margin-top:20px;">
                            <div class="d-flex justify-content-end gap-2">
                                <button type="button" class="btn btn-signup-close" data-bs-dismiss="modal">Close</button>
                                <button type="submit" class="btn btn-signup-submit" name="createUser"><i class="bi bi-person-plus-fill me-1"></i>Create Account</button>
                            </div>
                        </div>
                    </div>
                    </form>
                </div>
           </div>
       </div>
   </div>
   <style>
       #loginModal .modal-dialog { max-width: 460px; }
       #loginModal .modal-content { border:none; border-radius:16px; overflow:hidden; box-shadow:0 20px 60px rgba(9,23,60,.22); }
       #loginModal .modal-header { background:linear-gradient(135deg,#09173c 0%,#1a3a6b 100%); color:#fff; padding:28px 24px 22px; border:none; justify-content:center; }
       #loginModal .modal-header .login-logo-circle { width:68px; height:68px; border-radius:50%; background:#fff; margin:0 auto 12px; display:flex; align-items:center; justify-content:center; padding:5px; box-shadow:0 6px 20px rgba(9,23,60,.22); border:2px solid rgba(255,255,255,.18); }
       #loginModal .modal-header .login-logo-circle img { width:100%; height:100%; border-radius:50%; object-fit:cover; display:block; }
       #loginModal .modal-header .modal-title { margin:0; font-size:17px; font-weight:700; letter-spacing:.8px; }
       #loginModal .modal-body { padding:28px 32px 10px; font-family:'Poppins',sans-serif; }
       #loginModal .form-label { font-size:13px; font-weight:600; color:#334155; margin-bottom:6px; }
       #loginModal .form-control { min-height:44px; border:1.5px solid #d1d9e6; border-radius:10px; font-size:14px; padding:10px 14px; transition:border-color .2s ease,box-shadow .2s ease; }
       #loginModal .form-control:focus { border-color:#3b82f6; box-shadow:0 0 0 3px rgba(9,23,60,.08); }
       #loginModal .input-group-text { background:#f1f5f9; border:1.5px solid #d1d9e6; border-right:none; border-radius:10px 0 0 10px; color:#64748b; width:44px; display:flex; align-items:center; justify-content:center; font-size:16px; }
       #loginModal .input-group .form-control { border-left:none; border-radius:0 10px 10px 0; padding-left:6px; }
       #loginModal .input-group .form-control:focus { border-left:none; }
       #loginModal .login-pw-wrapper { position:relative; display:flex; align-items:stretch; flex:1; }
       #loginModal .login-pw-wrapper .form-control { padding-right:44px; border-radius:0 10px 10px 0; }
       #loginModal .login-pw-toggle { position:absolute; right:10px; top:50%; transform:translateY(-50%); background:none; border:none; padding:4px; cursor:pointer; color:#94a3b8; font-size:18px; display:flex; align-items:center; justify-content:center; z-index:10; transition:color .2s ease; }
       #loginModal .login-pw-toggle:hover { color:#09173c; }
       #loginModal .modal-footer { border-top:1px solid #e8ecf1; padding:16px 32px 16px; display:flex; justify-content:flex-end; gap:10px; background:#fff; }
       #loginModal .modal-footer .btn-secondary { border-radius:10px; font-weight:600; font-size:14px; padding:10px 22px; border:1.5px solid #d1d9e6; background:#fff; color:#475569; transition:all .2s ease; }
       #loginModal .modal-footer .btn-secondary:hover { background:#f1f5f9; border-color:#94a3b8; color:#1e293b; }
       #loginModal .modal-footer .btn-login-submit { border-radius:10px; font-weight:600; font-size:14px; padding:10px 28px; background:#09173c; border:none; color:#fff; letter-spacing:.3px; transition:background .2s ease,transform .15s ease,box-shadow .2s ease; box-shadow:0 4px 14px rgba(9,23,60,.18); }
       #loginModal .modal-footer .btn-login-submit:hover { background:#0f2a5c; transform:translateY(-1px); box-shadow:0 6px 20px rgba(9,23,60,.28); }
       #loginModal .modal-footer .btn-login-submit:active { transform:translateY(0); box-shadow:0 2px 8px rgba(9,23,60,.18); }
       #loginModal .login-modal-signup-link { text-align:center; padding:14px 0 4px; font-size:13px; color:#64748b; }
       #loginModal .login-modal-signup-link a { color:#09173c; font-weight:600; text-decoration:none; transition:color .2s ease; }
       #loginModal .login-modal-signup-link a:hover { color:#2f6fed; text-decoration:underline; }
   </style>
   <div class="modal fade" id="loginModal" data-bs-backdrop="static" data-bs-keyboard="false" tabindex="-1" aria-labelledby="loginModal" aria-hidden="true">
       <div class="modal-dialog modal-dialog-centered">
           <div class="modal-content">
               <div class="modal-header">
                   <div class="w-100 text-center">
                       <div class="login-logo-circle">
                           <img src="assets/images/logo.jpg" alt="CSDP Logo">
                       </div>
                       <h5 class="modal-title">Team Representative Login</h5>
                   </div>
               </div>
               <div class="modal-body">
                   <form class="needs-validation" id="loginModalForm" novalidate>
                       <div class="mb-3">
                           <label for="email" class="form-label">Email Address</label>
                           <div class="input-group">
                               <span class="input-group-text"><i class="bi bi-envelope-fill"></i></span>
                               <input type="text" class="form-control" id="email" name="email" required placeholder="you@example.com">
                           </div>
                       </div>
                       <div class="mb-3">
                           <label for="password" class="form-label">Password</label>
                           <div class="input-group">
                               <span class="input-group-text"><i class="bi bi-lock-fill"></i></span>
                               <div class="login-pw-wrapper">
                                   <input type="password" class="form-control" id="password" name="password" required placeholder="••••••••">
                                   <button type="button" class="login-pw-toggle" id="loginModalTogglePw" aria-label="Toggle password visibility">
                                       <i class="bi bi-eye-fill"></i>
                                   </button>
                               </div>
                           </div>
                        <div class="login-captcha-card">
                            <div class="login-captcha-icon">
                                <i class="bi bi-shield-lock"></i>
                            </div>
                            <div class="login-captcha-body">
                                <label class="login-captcha-label">Security Verification</label>
                                <p class="login-captcha-hint">Please verify you're not a robot</p>
                                <div id="recaptcha-login-modal"></div>
                            </div>
                        </div>
                       </div>
                   </form>
                    <div class="login-modal-signup-link">
                        Don't have an account? <a href="#" id="loginModalGoToSignUp">Create One</a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" class="btn btn-login-submit" form="loginModalForm"><i class="bi bi-box-arrow-in-right me-1"></i>Login</button>
                </div>
            </div>
        </div>
    </div>
   <script>
   (function(){
       // Track if widgets are already rendered
       var loginModalRendered = false;
       var signupRendered = false;

       // Render reCAPTCHA for login modal (only once)
       function renderRecaptchaLoginModal() {
           if (loginModalRendered) {
               // Already rendered, just reset it
               if (typeof recaptchaIds.loginModal !== 'undefined') {
                   grecaptcha.reset(recaptchaIds.loginModal);
               }
               return;
           }
           if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.render === 'function') {
               try {
                   recaptchaIds.loginModal = grecaptcha.render('recaptcha-login-modal', {
                       sitekey: '6LdzCKMtAAAAALZOBzK__d-Ow6iuZAWNZtjBMUDT',
                       theme: 'light'
                   });
                   loginModalRendered = true;
                   console.log('reCAPTCHA loginModal rendered, ID:', recaptchaIds.loginModal);
               } catch(e) {
                   console.error('reCAPTCHA loginModal render error:', e);
               }
           }
       }

       // Render reCAPTCHA for signup modal (only once)
       function renderRecaptchaSignup() {
           if (signupRendered) {
               // Already rendered, just reset it
               if (typeof recaptchaIds.signup !== 'undefined') {
                   grecaptcha.reset(recaptchaIds.signup);
               }
               return;
           }
           if (typeof grecaptcha !== 'undefined' && typeof grecaptcha.render === 'function') {
               try {
                   recaptchaIds.signup = grecaptcha.render('recaptcha-signup', {
                       sitekey: '6LdzCKMtAAAAALZOBzK__d-Ow6iuZAWNZtjBMUDT',
                       theme: 'light'
                   });
                   signupRendered = true;
                   console.log('reCAPTCHA signup rendered, ID:', recaptchaIds.signup);
               } catch(e) {
                   console.error('reCAPTCHA signup render error:', e);
               }
           }
       }

       var toggleBtn = document.getElementById('loginModalTogglePw');
       if (toggleBtn) {
           toggleBtn.addEventListener('click', function(e){
               e.preventDefault();
               var pw = document.getElementById('password');
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
       var modal = document.getElementById('loginModal');
       var pendingSignUpOpen = false;
       if (modal) {
           modal.addEventListener('show.bs.modal', function(){
               renderRecaptchaLoginModal();
           });
           modal.addEventListener('hidden.bs.modal', function(){
               var pw = document.getElementById('password');
               if (pw) pw.type = 'password';
               var icon = toggleBtn && toggleBtn.querySelector('i');
               if (icon) { icon.classList.remove('bi-eye-slash-fill'); icon.classList.add('bi-eye-fill'); }
               // Reset reCAPTCHA on modal close
               if (typeof recaptchaIds.loginModal !== 'undefined') {
                   grecaptcha.reset(recaptchaIds.loginModal);
               }
               if (pendingSignUpOpen) {
                   pendingSignUpOpen = false;
                   var signUpModal = new bootstrap.Modal(document.getElementById('signUp'));
                   signUpModal.show();
               }
           });
       }
       var goSignUp = document.getElementById('loginModalGoToSignUp');
       if (goSignUp) {
           goSignUp.addEventListener('click', function(e){
               e.preventDefault();
               pendingSignUpOpen = true;
               var bsLogin = bootstrap.Modal.getInstance(modal);
               if (bsLogin) bsLogin.hide();
           });
       }

       // Render signup reCAPTCHA when signup modal is shown
       var signUpEl = document.getElementById('signUp');
       if (signUpEl) {
           signUpEl.addEventListener('show.bs.modal', function(){
               renderRecaptchaSignup();
           });
       }
   })();
   </script>
   <script>
   (function(){
       /* Password eye-toggle for Sign-Up modal */
       function setupPwToggle(btnId, inputId) {
           var btn = document.getElementById(btnId);
           if (!btn) return;
           btn.addEventListener('click', function(e){
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
           pwMinLen: function(v){ return v.length >= 8; },
           pwUpper:  function(v){ return /[A-Z]/.test(v); },
           pwLower:  function(v){ return /[a-z]/.test(v); },
           pwNum:    function(v){ return /\d/.test(v); },
           pwSpec:   function(v){ return /[^A-Za-z0-9]/.test(v); }
       };
       if (pw1) {
           pw1.addEventListener('input', function(){
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

       /* Reset toggles & checklist when modal closes */
       var signUpEl = document.getElementById('signUp');
       if (signUpEl) {
           signUpEl.addEventListener('hidden.bs.modal', function(){
               var inputs = [document.getElementById('validationServers01'), document.getElementById('validationServers02')];
               inputs.forEach(function(inp){ if(inp) inp.type = 'password'; });
               ['signUpTogglePw1','signUpTogglePw2'].forEach(function(btnId){
                   var b = document.getElementById(btnId);
                   if(b){ var i = b.querySelector('i'); if(i){ i.classList.remove('bi-eye-slash-fill'); i.classList.add('bi-eye-fill'); }}
               });
               for (var id in checks) {
                   var el = document.getElementById(id);
                   if(!el) continue;
                   el.classList.remove('met'); el.classList.add('unmet');
                   var ic = el.querySelector('i'); if(ic) ic.className = 'bi bi-circle';
               }
               if(matchEl) matchEl.className = 'pw-match-indicator hidden-indicator';
           });
       }
   })();
   </script>
