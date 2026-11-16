<!-- jQuery -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/jquery/3.7.1/jquery.min.js"></script>
<!-- Bootstrap Bundle JS -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/bootstrap/5.3.3/js/bootstrap.bundle.min.js"></script>
<!-- jQuery Easing -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/jquery-easing/1.4.1/jquery.easing.min.js"></script>
<!-- SB Admin 2 JS -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/sb-admin-2/4.1.4/sb-admin-2.min.js"></script>
<!-- Select2 JS -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/select2/4.1.0-rc.0/select2.min.js"></script>
<!-- DataTables JS -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables/1.13.8/jquery.dataTables.min.js"></script>
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables/1.13.8/dataTables.bootstrap4.min.js"></script>
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-buttons/2.4.2/dataTables.buttons.min.js"></script>
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-buttons/2.4.2/buttons.bootstrap4.min.js"></script>
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-buttons/2.4.2/buttons.html5.min.js"></script>
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-buttons/2.4.2/buttons.print.min.js"></script>
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-buttons/2.4.2/buttons.colVis.min.js"></script>
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-select/1.7.0/dataTables.select.min.js"></script>
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-fixedheader/3.4.0/dataTables.fixedHeader.min.js"></script>
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/datatables-rowgroup/1.4.1/dataTables.rowGroup.min.js"></script>
<!-- JSZip for DataTables export -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/jszip/3.10.1/jszip.min.js"></script>
<!-- pdfmake for DataTables export -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/pdfmake/0.2.7/pdfmake.min.js"></script>
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/pdfmake/0.2.7/vfs_fonts.js"></script>
<!-- SweetAlert2 -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/sweetalert2/11.26.25/sweetalert2.all.min.js"></script>
<!-- LMS floating toast notifications (shared component) -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/js/lms-toast.js"></script>
<!-- LMS modal dialogs (shared component) -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/js/lms-dialog.js"></script>
<!-- Alertify JS -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/alertifyjs/1.13.1/alertify.min.js"></script>
<!-- CanvasJS for charts -->
<script src="<?= htmlspecialchars($lmsBase) ?>/assets/vendor/canvasjs/3.18.0/canvasjs.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll("input").forEach(function(input) {
            input.setAttribute("autocomplete", "off");
        });
    });
</script>
<script>
    $(document).on('submit', '.logoutForm', function(e) {
        e.preventDefault();

        LmsDialog.confirm({
            title: 'Log out of your account?',
            text: 'You will need to sign in again to continue managing your leagues.',
            confirmButtonText: 'Yes, log out',
            cancelButtonText: 'Stay signed in'
        }).then((confirmed) => {
            if (!confirmed) return;
            $.ajax({
                url: "action/logout.php",
                type: "POST",
                data: {
                    logout: true,
                    csrf_token: <?= json_encode(csrfToken()) ?>
                },
                dataType: "json",
                success: function(res) {
                    if (res.status === 201) {
                        Toast.fire({
                            icon: 'success',
                            title: 'Logged out',
                            timer: 1200
                        }).then(() => {
                            window.location.href = "../../index.php";
                        });
                    } else {
                        Toast.fire({
                            icon: 'error',
                            title: res.msg || 'Please try again.'
                        });
                    }
                },
                error: function(xhr) {
                    console.log(xhr.responseText);
                }
            });
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
    const suffixEl = document.getElementById('suffix');
    const monthEl = document.getElementById('month');
    const yearEl = document.getElementById('year');
    if (suffixEl) suffixEl.textContent = suffix;
    if (monthEl) monthEl.textContent = month;
    if (yearEl) yearEl.textContent = year;
</script>
<script>
    // Function to get the current date and time in GMT+8
    function getCurrentDateTimeGMT8() {
        let date = new Date();
        let localTime = date.getTime();
        let localOffset = date.getTimezoneOffset() * 60000;
        let utc = localTime + localOffset;
        let offset = 8; // Offset for GMT+8
        let gmt8 = new Date(utc + (3600000 * offset));

        let year = gmt8.getFullYear();
        let monthNames = ["Jan", "Feb", "Mar", "Apr", "May", "Jun", "Jul", "Aug", "Sep", "Oct", "Nov", "Dec"];
        let month = monthNames[gmt8.getMonth()];
        let dayNames = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
        let dayName = dayNames[gmt8.getDay()];
        let day = ("0" + gmt8.getDate()).slice(-2);
        let hours = gmt8.getHours();
        let minutes = ("0" + gmt8.getMinutes()).slice(-2);
        let seconds = ("0" + gmt8.getSeconds()).slice(-2);
        let ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // the hour '0' should be '12'
        hours = ("0" + hours).slice(-2); // Optional: format hours with leading zero

        return `${month} ${day}, ${year} |  ${dayName} ${hours}:${minutes}:${seconds} ${ampm}`;
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
<script>
    function togglePasswordVisibility1() {
        var passwordInput1 = document.getElementById("pass");
        var showPasswordCheckbox1 = document.getElementById("password");

        if (showPasswordCheckbox1.checked) {
            passwordInput1.type = "text";
        } else {
            passwordInput1.type = "password";
        }
    }

    function togglePasswordVisibility2() {
        var passwordInput2 = document.getElementById("pass2");
        var showPasswordCheckbox2 = document.getElementById("password2");

        if (showPasswordCheckbox2.checked) {
            passwordInput2.type = "text";
        } else {
            passwordInput2.type = "password";
        }
    }
</script>
<?php include __DIR__ . '/../../../includes/notificationPopover.php'; ?>
