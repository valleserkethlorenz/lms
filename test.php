<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sports League</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        * {
            font-family: 'Poppins', sans-serif;
        }

        body {
            overflow-x: hidden;
        }

        /* NAVBAR */
        .navbar {
            background: #08143b;
            padding: 15px 0;
        }

        .navbar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .navbar-brand img {
            width: 55px;
            height: 55px;
            object-fit: cover;
        }

        .brand-text h5 {
            margin: 0;
            color: white;
            font-weight: 700;
            font-size: 20px;
        }

        .brand-text p {
            margin: 0;
            color: white;
            font-size: 14px;
        }

        .nav-link {
            color: white !important;
            margin: 0 10px;
            font-weight: 500;
        }

        .nav-link.active {
            color: #1f6fff !important;
        }

        .login-btn {
            background: #1f6fff;
            color: white;
            border-radius: 40px;
            padding: 10px 35px;
            font-weight: 600;
        }

        .login-btn:hover {
            background: #1256d1;
            color: white;
        }

        /* HERO SECTION */
        .hero {
            min-height: 100vh;
            background:
                linear-gradient(to right, rgba(3, 12, 39, 0.95), rgba(3, 12, 39, 0.65)),
                url('https://images.unsplash.com/photo-1574629810360-7efbbe195018?q=80&w=1920&auto=format&fit=crop') center/cover no-repeat;

            display: flex;
            align-items: center;
        }

        .hero-content {
            max-width: 650px;
        }

        .hero h1 {
            color: white;
            font-size: 80px;
            font-weight: 800;
            line-height: 1.05;
        }

        .hero h1 span {
            color: #1f6fff;
        }

        .league-btn {
            margin-top: 25px;
            background: #1f6fff;
            color: white;
            padding: 16px 35px;
            border-radius: 18px;
            font-size: 22px;
            font-weight: 600;
            border: none;
        }

        .league-btn:hover {
            background: #1256d1;
        }

        /* RESPONSIVE */
        @media(max-width: 992px) {

            .hero {
                text-align: center;
            }

            .hero h1 {
                font-size: 55px;
            }

            .navbar-brand img {
                width: 45px;
                height: 45px;
            }

            .brand-text h5 {
                font-size: 16px;
            }

            .brand-text p {
                font-size: 12px;
            }
        }

        @media(max-width: 576px) {

            .hero h1 {
                font-size: 42px;
            }

            .league-btn {
                font-size: 18px;
                padding: 14px 28px;
            }
        }
    </style>
</head>

<body>

    <!-- NAVBAR -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">

            <!-- LOGO -->
            <a class="navbar-brand" href="#">
                <img src="logo.png" class="rounded-circle" alt="Logo">

                <div class="brand-text">
                    <h5>City Sport and Development Program</h5>
                    <p>League Management System</p>
                </div>
            </a>

            <!-- MOBILE TOGGLE -->
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <!-- NAV LINKS -->
            <div class="collapse navbar-collapse justify-content-center" id="navbarNav">
                <ul class="navbar-nav">

                    <li class="nav-item">
                        <a class="nav-link active" href="#">Home</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#">Leagues</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#">Gallery</a>
                    </li>

                    <li class="nav-item">
                        <a class="nav-link" href="#">About</a>
                    </li>

                </ul>
            </div>

            <!-- LOGIN BUTTON -->
            <a href="#" class="btn login-btn">Login</a>

        </div>
    </nav>

    <!-- HERO SECTION -->
    <section class="hero">
        <div class="container">

            <div class="hero-content">

                <h1>
                    Connecting <br>
                    Communities, <br>
                    Fostering Future <br>
                    <span>Champions.</span>
                </h1>

                <button class="league-btn">
                    View Leagues
                    <i class="bi bi-trophy ms-2"></i>
                </button>

            </div>

        </div>
    </section>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

</body>

</html>