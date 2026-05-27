<?php
$url = $_GET['page'] ?? null;
session_name('lms');
session_start();
if (isset($_SESSION['loggin']) && $_SESSION['loggin'] === true) {
    switch ($_SESSION['lms_role']) {
        case 'admin':
            redirect("app/admin/dashboard.php", "Welcome Back Admin!");
            break;

        default:
            redirect("index.php", "");
            break;
    }
}
include 'includes/header.php';
include 'includes/bar.php'; ?>
<style>
    body {
        background-color: white;
        margin: 0;
        padding: 0;
        min-height: 100vh;
        display: flex;
        justify-content: center;
        align-items: center;
        overflow-x: hidden;
    }

    .container {
        background-color: transparent;
        padding: 10px 20px 10px 20px;
        border-radius: 10px;
        max-width: 400px;
        width: 100%;
        text-align: center;
        animation: fadeInUp 0.8s ease-out forwards;
    }

    .logo-header {
        display: flex;
        flex-direction: column;
        align-items: center;
        margin-bottom: 20px;
    }

    .logo-header img {
        max-width: 250px;
        margin-bottom: 15px;
    }

    .logo-header h5 {
        color: #865103;
        font-weight: bold;
    }

    .card {
        border-radius: 8px;
        box-shadow: 2px 7px 8px 1px rgba(0, 0, 0, 0.5);
        margin: 0;
        border: 1.5px solid black;
    }

    .card-header,
    .card-footer {
        border: none;
        background-color: transparent;
    }

    .form-label {
        font-size: 15px;
        color: black;
        text-align: left;
        display: block;
        margin-bottom: 8px;
    }

    .form-control {
        border-radius: 4px;
        color: black;
    }

    .btn-primary {
        border: none;
        width: 100%;
        font-size: 16px;
        font-weight: bold;
        color: #000;
        border-radius: 5px;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: background-color 0.3s, box-shadow 0.3s;
        letter-spacing: 3px;
    }

    .btn-primary:hover {
        background-color: #3e378a;
        box-shadow: 0 6px 8px rgba(0, 0, 0, 0.2);
    }

    .btn-primary:active {
        background-color: #3e378a;
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        transform: translateY(2px);
    }

    .form-group span {
        color: black;
    }

    .btn-forgot-password {
        color: #000;
        background-color: #865103;
        border: none;
        border-radius: 4px;
        padding: 10px 20px;
        text-decoration: none;
        display: inline-block;
        font-weight: bold;
        text-align: center;
    }

    .btn-forgot-password:hover {
        background-color: #865103;
    }

    .card-footer a {
        display: block;
        margin-top: 10px;
    }





    #result {
        color: red;
    }

    .logo {
        width: 330px;
        height: 100px;
        display: block;
        margin: -55px auto 1px auto;

    }


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
</style>

<body>
    <div class="container">
        <div class="card">
            <!-- <img src="" alt="Logo" class="logo"> -->
            <div class="card-header ">
                <h5 class="card-title" style="font-weight: bold; color: black;letter-spacing:2px; margin-top:20px;">CITY SPORT AND DEVELOPMENT</h5>
            </div>
            <form id="loginForm">
                <div class="card-body" style="margin-top:-20px;margin-bottom:-20px;">
                    <div class="mb-3">
                        <label for="id" class="form-label">Email Address:</label>
                        <input type="text" class="form-control" id="email" name="email" required>
                    </div>
                    <div class="mb-3 text-left">
                        <label for="password" class="form-label">Password:</label>
                        <input type="password" class="form-control" id="password" name="password" required>
                        <span class="txt1" style="color:black; font-size:15px;">Show Password</span>
                        <input type="checkbox" id="showpass" onclick="viewPassword()">
                    </div>
                </div>
                <div class="card-footer mb-3">
                    <input type="hidden" class="form-control" value="<?php echo $url; ?>" name="url">
                    <button type="submit" class="btn btn-primary">LOGIN</button>
                </div>
            </form>
            <div class="d-flex justify-content-center m-2">
                <a class="signin" style="text-decoration:underline; color:black; cursor:pointer;" data-bs-toggle="modal" data-bs-target="#signUp">
                    Don't have account? Create an account
                </a>
            </div>
        </div>
    </div>
    <?php
    include 'includes/footer.php';
    include 'components/modal/loginModal.php';
    ?>
</body>