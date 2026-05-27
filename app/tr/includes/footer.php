<script src="../../vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="../../vendor/jquery/jquery.min.js"></script>
<script src="../../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="../../vendor/jquery/jquery-3.3.1.min.js"></script>
<script src="../../vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="../../vendor/js/sb-admin-2.min.js"></script>
<script src="../../vendor/js/select2.min.js"></script>
<!-- database tables-->
<script src="../../vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src=" ../../vendor/datatables/js/dataTables.bootstrap4.min.js"></script>
<script src="../../vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="../../vendor/datatables/js/buttons.bootstrap4.min.js"></script>
<script src="../../vendor/datatables/js/data-table.js"></script>
<script src="../../vendor/datatables/js/jszip.min.js"></script>
<script src="../../vendor/datatables/js/pdfmake.min.js"></script>
<script src="../../vendor/datatables/js/vfs_fonts.js"></script>
<script src="../../vendor/datatables/js/buttons.html5.min.js"></script>
<script src="../../vendor/datatables/js/buttons.print.min.js"></script>
<script src="../../vendor/datatables/js/buttons.colVis.min.js"></script>
<script src="../../vendor/datatables/js/dataTables.rowGroup.min.js"></script>
<script src="../../vendor/datatables/js/dataTables.select.min.js"></script>
<script src="../../vendor/datatables/js/dataTables.fixedHeader.min.js"></script>
<script src="../../vendor/datatables/js/buttons.print.min.js"></script>
<script src="../../vendor/datatables/js/buttons.colVis.min.js"></script>
<script src="../../vendor/datatables/js/dataTables.rowGroup.min.js"></script>
<script src="../../vendor/datatables/js/dataTables.select.min.js"></script>
<script src="../../vendor/datatables/js/dataTables.fixedHeader.min.js"></script>
<script src="../../vendor/sweetalert/sweetalert2@11.js"></script>
<script src="../../vendor/js/calendar.js"></script>
<script src="../../vendor/js/canvasjs.min.js"></script>
<script>
    document.addEventListener("DOMContentLoaded", function() {
        document.querySelectorAll("input").forEach(function(input) {
            input.setAttribute("autocomplete", "off");
        });
    });
</script>
<script>
    $(document).on('submit', '#logoutForm', function(e) {
        e.preventDefault();

        Swal.fire({
            text: "Are you sure you want to logout?",
            icon: "question",
            showCancelButton: true,
            confirmButtonColor: "#0eb018",
            cancelButtonColor: "rgb(92, 92, 92)",
            confirmButtonText: "Yes, logout!"
        }).then((result) => {

            if (!result.isConfirmed) return;

            $.ajax({
                url: "action/logout.php",
                type: "POST",
                data: {
                    logout: true
                },
                dataType: "json", // ✅ IMPORTANT
                success: function(res) {

                    console.log(res); // 👈 DEBUG

                    if (res.status === 201) {
                        let timerInterval;

                        Swal.fire({
                            icon: 'success',
                            title: 'Logging out',
                            html: 'Redirecting in <b></b> milliseconds.',
                            timer: 1500,
                            timerProgressBar: true,
                            didOpen: () => {
                                const timer = Swal.getPopup().querySelector('b');
                                const interval = setInterval(() => {
                                    timer.textContent = Swal.getTimerLeft();
                                }, 100);
                            },
                            willClose: () => {
                                window.location.href = "../../index.php";
                            }
                        }).then(() => {
                            window.location.href = "../../index.php";
                        });
                    } else {
                        Swal.fire({
                            icon: 'error',
                            text: res.msg
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
    // document.getElementById('day').textContent = day;
    document.getElementById('suffix').textContent = suffix;
    document.getElementById('month').textContent = month;
    document.getElementById('year').textContent = year;
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
    document.getElementById("datetime").innerText = getCurrentDateTimeGMT8();

    // Update the date and time every second
    setInterval(() => {
        document.getElementById("datetime").innerText = getCurrentDateTimeGMT8();
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