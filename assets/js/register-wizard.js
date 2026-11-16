/**
 * Registration wizard
 * ===================
 * Step 1 verifies the email address with a 6-digit code; Step 2 is the
 * application (account details). The server only creates the account when
 * the session carries a proof that this email was verified, so this script
 * is a UX layer, not the security boundary.
 */
(function () {
    'use strict';

    var form = document.getElementById('signUpForm');
    if (!form) return;

    var step1 = document.getElementById('signupStepVerify');
    var step2 = document.getElementById('signupStepApplication');
    var stepper = document.getElementById('signupStepper');
    var emailInput = document.getElementById('signupEmail');
    var sendBtn = document.getElementById('signupSendCode');
    var codeInput = document.getElementById('signupCode');
    var verifyBtn = document.getElementById('signupVerifyCode');
    var codeHint = document.getElementById('signupCodeHint');
    var verifiedEmailEl = document.getElementById('signupVerifiedEmail');
    var changeEmailBtn = document.getElementById('signupChangeEmail');
    var csrfInput = form.querySelector('input[name="csrf_token"]');
    var cooldownTimer = null;
    var lastSentEmail = '';

    if (!step1 || !step2 || !stepper || !emailInput || !sendBtn || !codeInput || !verifyBtn) return;

    window.signupEmailVerified = false;

    function toast(icon, title) {
        if (typeof Toast !== 'undefined' && Toast && typeof Toast.fire === 'function') {
            Toast.fire({ icon: icon, title: title });
        } else {
            console.log('[' + icon + '] ' + title);
        }
    }

    function csrfToken() {
        return csrfInput ? csrfInput.value : '';
    }

    function setStep(step) {
        step1.classList.toggle('d-none', step !== 1);
        step2.classList.toggle('d-none', step !== 2);

        var items = stepper.querySelectorAll('.signup-stepper-item');
        for (var i = 0; i < items.length; i++) {
            var item = items[i];
            var itemStep = parseInt(item.getAttribute('data-step'), 10);
            item.classList.toggle('is-active', itemStep === step);
            item.classList.toggle('is-complete', itemStep < step);
            if (itemStep === step) {
                item.setAttribute('aria-current', 'step');
            } else {
                item.removeAttribute('aria-current');
            }
        }

        if (step === 2 && typeof window.renderSignupRecaptcha === 'function') {
            window.renderSignupRecaptcha();
        }
    }

    function setCodeState(enabled) {
        codeInput.disabled = !enabled;
        codeInput.placeholder = enabled ? 'Enter the 6-digit code' : 'Send a code first';
        codeInput.value = '';
        verifyBtn.disabled = true;
    }

    function startCooldown(seconds) {
        if (cooldownTimer) clearInterval(cooldownTimer);
        var remaining = Math.max(0, parseInt(seconds, 10) || 60);
        sendBtn.disabled = true;
        sendBtn.textContent = 'Resend in ' + remaining + 's';
        cooldownTimer = setInterval(function () {
            remaining--;
            if (remaining <= 0) {
                clearInterval(cooldownTimer);
                cooldownTimer = null;
                sendBtn.disabled = false;
                sendBtn.textContent = 'Resend code';
                return;
            }
            sendBtn.textContent = 'Resend in ' + remaining + 's';
        }, 1000);
    }

    function resetVerification() {
        window.signupEmailVerified = false;
        lastSentEmail = '';
        setStep(1);
        setCodeState(false);
        codeHint.textContent = 'The code expires shortly after it is sent.';
        if (cooldownTimer) {
            clearInterval(cooldownTimer);
            cooldownTimer = null;
        }
        sendBtn.disabled = false;
        sendBtn.textContent = 'Send code';
    }

    function requestJson(data, done) {
        if (typeof jQuery === 'undefined') {
            done({ msg: 'The page is still loading. Please try again.' }, null);
            return;
        }
        jQuery.ajax({
            url: 'includes/login_action.php',
            type: 'POST',
            dataType: 'json',
            data: data,
            success: function (res) {
                done(null, res);
            },
            error: function (xhr) {
                var res = {};
                try {
                    res = JSON.parse(xhr.responseText || '{}');
                } catch (e) {
                    res = {};
                }
                if (!res || !res.msg) {
                    res = { msg: 'Something went wrong. Please try again.' };
                }
                done(res, null);
            }
        });
    }

    function sendCode() {
        if (!emailInput.checkValidity()) {
            emailInput.reportValidity();
            return;
        }
        var email = emailInput.value.trim().toLowerCase();
        if (!email) {
            toast('warning', 'Enter your email address first.');
            return;
        }
        if (!csrfToken()) {
            toast('error', 'Your session expired. Please refresh the page.');
            return;
        }

        sendBtn.disabled = true;
        sendBtn.textContent = 'Sending...';

        requestJson({ sendEmailCode: 1, email: email, csrf_token: csrfToken() }, function (err, res) {
            if (err) {
                sendBtn.disabled = false;
                sendBtn.textContent = 'Send code';
                toast('warning', err.msg || 'We could not send the code.');
                if (err.retry_after) startCooldown(err.retry_after);
                return;
            }
            if (res.status === 201) {
                lastSentEmail = email;
                setCodeState(true);
                codeInput.focus();
                startCooldown(res.cooldown || 60);
                codeHint.textContent = 'The code expires in ' + Math.max(1, Math.round((res.expires_in || 600) / 60)) + ' minutes.';
                toast('success', res.msg || 'Verification code sent.');
                return;
            }
            sendBtn.disabled = false;
            sendBtn.textContent = 'Send code';
            toast('warning', res.msg || 'We could not send the code.');
            if (res.retry_after) startCooldown(res.retry_after);
        });
    }

    function verifyCode() {
        var email = emailInput.value.trim().toLowerCase();
        var code = (codeInput.value || '').replace(/\D/g, '');
        if (!email || code.length !== 6) {
            toast('warning', 'Enter the 6-digit code from your email.');
            return;
        }
        if (!csrfToken()) {
            toast('error', 'Your session expired. Please refresh the page.');
            return;
        }

        verifyBtn.disabled = true;
        verifyBtn.textContent = 'Verifying...';

        requestJson({ verifyEmailCode: 1, email: email, code: code, csrf_token: csrfToken() }, function (err, res) {
            verifyBtn.textContent = 'Verify & continue';
            if (err) {
                verifyBtn.disabled = false;
                toast('warning', err.msg || 'We could not verify the code.');
                return;
            }
            if (res.status === 201) {
                window.signupEmailVerified = true;
                verifiedEmailEl.textContent = email;
                setStep(2);
                var first = document.getElementById('validationServer02');
                if (first) first.focus();
                toast('success', res.msg || 'Email verified.');
                return;
            }
            verifyBtn.disabled = false;
            toast('warning', res.msg || 'We could not verify the code.');
            if (res.reason === 'expired' || res.reason === 'too_many' || res.reason === 'no_code') {
                setCodeState(false);
            }
        });
    }

    sendBtn.addEventListener('click', sendCode);
    verifyBtn.addEventListener('click', verifyCode);

    codeInput.addEventListener('input', function () {
        this.value = this.value.replace(/\D/g, '').slice(0, 6);
        verifyBtn.disabled = this.value.length !== 6;
    });

    codeInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            if (!verifyBtn.disabled) verifyCode();
        }
    });

    emailInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            sendCode();
        }
    });

    emailInput.addEventListener('input', function () {
        if (lastSentEmail === '') return;
        var current = emailInput.value.trim().toLowerCase();
        if (current === lastSentEmail) return;

        // The code was sent to the previous address; start over for the new one.
        lastSentEmail = '';
        setCodeState(false);
        if (cooldownTimer) {
            clearInterval(cooldownTimer);
            cooldownTimer = null;
        }
        sendBtn.disabled = false;
        sendBtn.textContent = 'Send code';
        codeHint.textContent = 'Send a code to the new address.';
    });

    if (changeEmailBtn) {
        changeEmailBtn.addEventListener('click', function () {
            resetVerification();
            emailInput.focus();
        });
    }

    setStep(1);
})();
