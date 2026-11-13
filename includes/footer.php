<!-- jQuery -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<!-- Bootstrap Bundle JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery Easing -->
<script src="https://cdn.jsdelivr.net/npm/jquery-easing@0.0.1/jquery.easing.min.js"></script>
<!-- SB Admin 2 JS -->
<script src="https://cdn.jsdelivr.net/npm/startbootstrap-sb-admin-2@4.1.4/js/sb-admin-2.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- DataTables JS -->
<script src="https://cdn.datatables.net/1.13.8/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/dataTables.buttons.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.bootstrap4.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.html5.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.print.min.js"></script>
<script src="https://cdn.datatables.net/buttons/2.4.2/js/buttons.colVis.min.js"></script>
<script src="https://cdn.datatables.net/select/1.7.0/js/dataTables.select.min.js"></script>
<script src="https://cdn.datatables.net/fixedheader/3.4.1/js/dataTables.fixedHeader.min.js"></script>
<script src="https://cdn.datatables.net/rowgroup/1.4.1/js/dataTables.rowGroup.min.js"></script>
<!-- JSZip for DataTables export -->
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<!-- pdfmake for DataTables export -->
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.7/build/pdfmake.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/pdfmake@0.2.7/build/vfs_fonts.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<!-- LMS floating toast notifications (shared component) -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/js/lms-toast.js"></script>
<!-- LMS modal dialogs (shared component) -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/js/lms-dialog.js"></script>
<script>
     // The team registration form is a dedicated page now
     // (app/tr/register_team.php). Its submit handler lives in
     // app/tr/js/register-team.js and still posts to includes/registerTeam.php.
    $(document).on('submit', '#signUpForm', function(e) {
        e.preventDefault();

        // Validate reCAPTCHA for signup
        if (typeof recaptchaIds.signup !== 'undefined') {
            var captchaResponse = grecaptcha.getResponse(recaptchaIds.signup);
            if (!captchaResponse) {
                Toast.fire({
                    icon: 'warning',
                    title: 'Please complete the CAPTCHA verification'
                });
                return;
            }
        }

        var formData = new FormData(this);
        formData.append("signUp", true);

        // Add reCAPTCHA response token
        if (typeof recaptchaIds.signup !== 'undefined') {
            formData.append('g-recaptcha-response', grecaptcha.getResponse(recaptchaIds.signup));
        }

        $.ajax({
            url: "includes/login_action.php",
            type: "post",
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                var res = jQuery.parseJSON(data);
                if (res.status == 401) {
                    Toast.fire({
                        icon: 'warning',
                        title: res.msg
                    });
                    // Reset reCAPTCHA on failure
                    if (typeof recaptchaIds.signup !== 'undefined') {
                        grecaptcha.reset(recaptchaIds.signup);
                    }
                } else if (res.status == 201) {
                    Toast.fire({
                        icon: 'success',
                        title: res.msg || 'Account created successfully.',
                        timer: 2000
                    }).then(function() {
                        location.reload();
                    });
                }
            }

        });

    });
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll("input").forEach(function(input) {
            input.setAttribute("autocomplete", "off");
        });
    });

    function togglePasswordVisibility1() {
        var passwordInput1 = document.getElementById("validationServers01");
        var showPasswordCheckbox1 = document.getElementById("pass1");

        if (showPasswordCheckbox1.checked) {
            passwordInput1.type = "text";
        } else {
            passwordInput1.type = "password";
        }
    }

    function togglePasswordVisibility2() {
        var passwordInput2 = document.getElementById("validationServers02");
        var showPasswordCheckbox2 = document.getElementById("pass2");

        if (showPasswordCheckbox2.checked) {
            passwordInput2.type = "text";
        } else {
            passwordInput2.type = "password";
        }
    }

    function validateInput11() {
        const input = document.getElementById('testingss');
        let value = input.value;

        // Allow only numbers
        if (!/^\d*$/.test(value)) {
            input.value = value.replace(/\D/g, ''); // Remove non-numeric characters
            return;
        }

        // Ensure the first two characters are '0' and '9'
        if (value.length > 0 && value.length < 2) {
            if (value[0] !== '0') {
                input.value = '';
            } else {}
        } else if (value.length === 2) {
            if (value[0] !== '0' || value[1] !== '9') {
                input.value = '09';
            } else {}
        } else if (value.length > 2) {
            if (value[0] !== '0' || value[1] !== '9') {
                input.value = '09' + value.slice(2);
            } else {}
        }
    }

    function isMobileDevice() {
        const ua = navigator.userAgent;
        return /Android|iPhone|iPad|iPod|Mobile/i.test(ua);
    }
    $(document).on('submit', '#loginForm', function(e) {
        e.preventDefault();

        // Validate reCAPTCHA for login
        if (typeof recaptchaIds.login !== 'undefined') {
            var captchaResponse = grecaptcha.getResponse(recaptchaIds.login);
            if (!captchaResponse) {
                Toast.fire({
                    icon: "warning",
                    title: "Please complete the CAPTCHA verification"
                });
                return;
            }
        }

        var formData = new FormData(this);
        formData.append('loginBtn', true);

        // Add reCAPTCHA response token
        if (typeof recaptchaIds.login !== 'undefined') {
            formData.append('g-recaptcha-response', grecaptcha.getResponse(recaptchaIds.login));
        }

        $.ajax({
            url: 'includes/login_action.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',

            success: function(res) {
                if (res.status === 200 || res.status === 201) {
                    Toast.fire({
                        icon: "success",
                        title: res.msg
                    }).then(() => {
                        window.location.href = res.redirect;
                    });
                } else {
                    Toast.fire({
                        icon: "error",
                        title: res.msg
                    });
                    // Reset reCAPTCHA on failure
                    if (typeof recaptchaIds.login !== 'undefined') {
                        grecaptcha.reset(recaptchaIds.login);
                    }
                }
            },

            error: function() {
                Swal.fire({
                    icon: "error",
                    title: "Server Error",
                    text: "Please try again later."
                });
            }
        });
    });
    $(document).on('submit', '#loginModalForm', function(e) {
        e.preventDefault();

        // Validate reCAPTCHA for modal login
        if (typeof recaptchaIds.loginModal !== 'undefined') {
            var captchaResponse = grecaptcha.getResponse(recaptchaIds.loginModal);
            if (!captchaResponse) {
                Toast.fire({
                    icon: "warning",
                    title: "Please complete the CAPTCHA verification"
                });
                return;
            }
        }

        var formData = new FormData(this);
        formData.append('loginModal', true);

        // Add reCAPTCHA response token
        if (typeof recaptchaIds.loginModal !== 'undefined') {
            formData.append('g-recaptcha-response', grecaptcha.getResponse(recaptchaIds.loginModal));
        }

        $.ajax({
            url: 'includes/login_action.php',
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            dataType: 'json',

            success: function(res) {
                if (res.status === 200 || res.status === 201) {
                    Toast.fire({
                        icon: "success",
                        title: res.msg
                    }).then(() => {
                        location.reload();

                    });
                } else {
                    Toast.fire({
                        icon: "error",
                        title: res.msg
                    });
                    // Reset reCAPTCHA on failure
                    if (typeof recaptchaIds.loginModal !== 'undefined') {
                        grecaptcha.reset(recaptchaIds.loginModal);
                    }
                }
            },

            error: function(xhr) {
                if (xhr.responseJSON && xhr.responseJSON.msg) {
                    Toast.fire({
                        icon: xhr.status === 403 ? "warning" : "error",
                        title: xhr.responseJSON.msg
                    });
                } else {
                    Swal.fire({
                        icon: "error",
                        title: "Server Error",
                        text: "Please try again later."
                    });
                }
            }
        });
    });
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll("input").forEach(function(input) {
            input.setAttribute("autocomplete", "off");
        });
    });

    // Get today's date
    const today = new Date();
    const day = today.getDate();
    const month = today.toLocaleString('default', {
        month: 'long'
    });
    const year = today.getFullYear();

    // Determine the ordinal suffix
    const suffix = day % 10 === 1 && day !== 11 ? 'st' :
        day % 10 === 2 && day !== 12 ? 'nd' :
        day % 10 === 3 && day !== 13 ? 'rd' : 'th';

    // Set the content
    const dayEl = document.getElementById('day');
    const suffixEl = document.getElementById('suffix');
    const monthEl = document.getElementById('month');
    const yearEl = document.getElementById('year');
    if (dayEl) dayEl.textContent = day;
    if (suffixEl) suffixEl.textContent = suffix;
    if (monthEl) monthEl.textContent = month;
    if (yearEl) yearEl.textContent = year;

    // Function to get the current date and time in GMT+8
    function getCurrentDateTimeGMT8() {
        let date = new Date();
        let localTime = date.getTime();
        let localOffset = date.getTimezoneOffset() * 60000;
        let utc = localTime + localOffset;
        let offset = 8; // Offset for GMT+8
        let gmt8 = new Date(utc + (3600000 * offset));

        let year = gmt8.getFullYear();
        let monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        let month = monthNames[gmt8.getMonth()];
        let dayNames = ["Sunday", "Monday", "Tuesday", "Wednesday", "Thursday", "Friday", "Saturday"];
        let dayName = dayNames[gmt8.getDay()];
        let day = ("0" + gmt8.getDate()).slice(-2);
        let hours = gmt8.getHours();
        let minutes = ("0" + gmt8.getMinutes()).slice(-2);
        let seconds = ("0" + gmt8.getSeconds()).slice(-2);
        let ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        hours = ("0" + hours).slice(-2); // Optional: format hours with leading zero

        return `${dayName} | ${month} ${day}, ${year} ${hours}:${minutes}:${seconds} ${ampm}`;
    }

    // Display the current date and time in GMT+8
    const dateTimeEl = document.getElementById("datetime");
    if (dateTimeEl) {
        dateTimeEl.innerText = getCurrentDateTimeGMT8();
    }

    // Update the date and time every second
    setInterval(() => {
        if (dateTimeEl) {
            dateTimeEl.innerText = getCurrentDateTimeGMT8();
        }
    }, 1000);
</script>
