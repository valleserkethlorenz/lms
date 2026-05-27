<script src="vendor/bootstrap/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery/jquery-3.3.1.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="vendor/js/sb-admin-2.min.js"></script>
<script src="vendor/js/select2.min.js"></script>
<!-- database tables-->
<script src="vendor/datatables/js/jquery.dataTables.min.js"></script>
<script src=" vendor/datatables/js/dataTables.bootstrap4.min.js"></script>
<script src="vendor/datatables/js/dataTables.buttons.min.js"></script>
<script src="vendor/datatables/js/buttons.bootstrap4.min.js"></script>
<script src="vendor/datatables/js/data-table.js"></script>
<script src="vendor/datatables/js/jszip.min.js"></script>
<script src="vendor/datatables/js/pdfmake.min.js"></script>
<script src="vendor/datatables/js/vfs_fonts.js"></script>
<script src="vendor/datatables/js/buttons.html5.min.js"></script>
<script src="vendor/datatables/js/buttons.print.min.js"></script>
<script src="vendor/datatables/js/buttons.colVis.min.js"></script>
<script src="vendor/datatables/js/dataTables.rowGroup.min.js"></script>
<script src="vendor/datatables/js/dataTables.select.min.js"></script>
<script src="vendor/datatables/js/dataTables.fixedHeader.min.js"></script>
<script src="vendor/datatables/js/buttons.print.min.js"></script>
<script src="vendor/datatables/js/buttons.colVis.min.js"></script>
<script src="vendor/datatables/js/dataTables.rowGroup.min.js"></script>
<script src="vendor/datatables/js/dataTables.select.min.js"></script>
<script src="vendor/datatables/js/dataTables.fixedHeader.min.js"></script>
<script src="vendor/sweetalert/sweetalert2@11.js"></script>
<script src="vendor/js/calendar.js"></script>
<script>
    $(document).on('submit', '#registerTeam', function(e) {
        e.preventDefault();


        var formData = new FormData(this);
        formData.append('register', true);

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
    $(document).on('submit', '#signUp', function(e) {
        e.preventDefault();
        var formData = new FormData(this);
        formData.append("signUp", true);
        $.ajax({
            url: "includes/login_action.php",
            type: "post",
            data: formData,
            processData: false,
            contentType: false,
            success: function(data) {
                var res = jQuery.parseJSON(data);
                if (res.status == 401) {
                    Swal.fire({
                        icon: 'warning',
                        title: res.msg,
                        // text: 'Something Went Wrong.',
                        timer: 10000
                    })
                } else if (res.status == 201) {
                    Swal.fire({
                        icon: 'success',
                        title: 'SUCCESS',
                        text: res.msg,
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

    const Toast = Swal.mixin({
        toast: true,
        position: "top-end",
        iconColor: 'white',
        showConfirmButton: false,
        customClass: {
            popup: 'colored-toast',
        },
        timer: 1500,
        timerProgressBar: true,
        didOpen: (toast) => {
            toast.onmouseenter = Swal.stopTimer;
            toast.onmouseleave = Swal.resumeTimer;
        }
    });

    function isMobileDevice() {
        const ua = navigator.userAgent;
        return /Android|iPhone|iPad|iPod|Mobile/i.test(ua);
    }
    $(document).on('submit', '#loginForm', function(e) {
        e.preventDefault();


        var formData = new FormData(this);
        formData.append('loginBtn', true);

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
    $(document).on('submit', '#loginModal', function(e) {
        e.preventDefault();


        var formData = new FormData(this);
        formData.append('loginModal', true);

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
    document.getElementById('day').textContent = day;
    document.getElementById('suffix').textContent = suffix;
    document.getElementById('month').textContent = month;
    document.getElementById('year').textContent = year;

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
    document.getElementById("datetime").innerText = getCurrentDateTimeGMT8();

    // Update the date and time every second
    setInterval(() => {
        document.getElementById("datetime").innerText = getCurrentDateTimeGMT8();
    }, 1000);
</script>