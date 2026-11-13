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
            text: 'You will need to sign in again to continue managing the league.',
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
