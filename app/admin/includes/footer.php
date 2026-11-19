<?php $lmsBase = $lmsBase ?? ''; ?>
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
            confirmButtonText: 'Yes, log out',
            cancelButtonText: 'Stay signed in'
        }).then((confirmed) => {
            if (!confirmed) return;
            $.ajax({
                url: "action/logout.php", type: "POST", data: { logout: true, csrf_token: <?= json_encode(csrfToken()) ?> }, dataType: "json",
                success: function(res) {
                    if (res.status === 201) {
                        Toast.fire({ icon: 'success', title: 'Logged out', timer: 1200 })
                            .then(() => { window.location.href = "../../index.php"; });
                    } else {
                        Toast.fire({ icon: 'error', title: res.msg || 'Please try again.' });
                    }
                },
                error: function() {
                    Toast.fire({ icon: 'error', title: 'The server could not complete the logout request.' });
                }
            });
        });
    });
</script>
