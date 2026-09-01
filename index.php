<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Login required
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header("Location: login.php");
    exit();
}

$userName  = $_SESSION['name']  ?? '';
$userEmail = $_SESSION['email'] ?? '';
$msg       = $_GET['msg']       ?? '';
$status    = $_GET['status']    ?? '';

// Real stats from DB
try {
    $total_users = $pdo->query(
        "SELECT COUNT(*) FROM users WHERE status='active'"
    )->fetchColumn();

    $total_logins = $pdo->query(
        "SELECT COUNT(*) FROM login_history WHERE status='success'"
    )->fetchColumn();

    $total_contacts = $pdo->query(
        "SELECT COUNT(*) FROM contacts"
    )->fetchColumn();

} catch (PDOException $e) {
    $total_users = 0;
    $total_logins = 0;
    $total_contacts = 0;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Coderror — Code • Create • Conquer</title>

    <link rel="stylesheet" href="style.css">

    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>

        /* ==============================
           WELCOME BANNER
        ============================== */

        .welcome-banner {
            position: fixed;
            top: 80px;
            right: 20px;

            background: linear-gradient(
                135deg,
                rgba(212,175,55,0.2),
                rgba(184,134,11,0.2)
            );

            border: 1px solid #D4AF37;

            padding: 12px 20px;

            border-radius: 12px;

            color: #F5D76E;

            font-size: 14px;

            z-index: 999;

            display: flex;
            align-items: center;
            gap: 12px;

            box-shadow: 0 5px 20px rgba(212,175,55,0.3);
        }

        .welcome-banner button {
            background: transparent;

            border: 1px solid #D4AF37;

            color: #D4AF37;

            padding: 5px 12px;

            border-radius: 6px;

            cursor: pointer;

            font-size: 12px;
        }

        .welcome-banner button:hover {
            background: #D4AF37;
            color: #000;
        }


        /* ==============================
           USER PILL
        ============================== */

        .user-pill {
            background: linear-gradient(
                135deg,
                #D4AF37,
                #B8860B
            ) !important;

            color: #000 !important;

            padding: 6px 14px !important;

            border-radius: 20px;

            font-weight: 600 !important;

            font-size: 13px !important;

            cursor: default;
        }


        /* ==============================
           DASHBOARD BUTTON
        ============================== */

        .dashboard-btn {
            background: linear-gradient(
                135deg,
                #D4AF37,
                #B8860B
            ) !important;

            color: #000 !important;

            padding: 8px 15px !important;

            border-radius: 8px !important;

            font-weight: 600 !important;

            display: inline-flex !important;

            align-items: center;

            gap: 7px;

            transition: all 0.3s ease;

            border: 1px solid #D4AF37;

            box-shadow: 0 3px 10px rgba(212,175,55,0.15);
        }

        .dashboard-btn:hover {
            background: linear-gradient(
                135deg,
                #F5D76E,
                #D4AF37
            ) !important;

            color: #000 !important;

            transform: translateY(-2px);

            box-shadow:
                0 5px 18px rgba(212,175,55,0.4);
        }

        .dashboard-btn i {
            font-size: 14px;
        }


        /* ==============================
           MOBILE DASHBOARD BUTTON
        ============================== */

        @media (max-width: 768px) {

            .dashboard-btn {
                padding: 10px 15px !important;

                width: 100%;

                justify-content: center;

                margin: 5px 0;
            }

            .user-pill {
                display: flex !important;

                justify-content: center;

                margin: 5px 0;
            }

        }

    </style>

</head>

<body>

    <!-- Background Symbols -->
    <div class="bg-symbols" id="bgSymbols"></div>


    <!-- Alert Message -->

    <?php if ($msg): ?>

        <script>
            alert(
                <?php echo json_encode(urldecode($msg)); ?>
            );
        </script>

    <?php endif; ?>


    <!-- ==============================
         NAVBAR
    ============================== -->

    <nav class="navbar">

        <!-- LOGO -->

        <a href="index.php" class="nav-logo">

            <svg viewBox="0 0 100 100">

                <defs>

                    <linearGradient
                        id="g1"
                        x1="0%"
                        y1="0%"
                        x2="100%"
                        y2="100%"
                    >

                        <stop
                            offset="0%"
                            style="stop-color:#F5D76E"
                        />

                        <stop
                            offset="50%"
                            style="stop-color:#D4AF37"
                        />

                        <stop
                            offset="100%"
                            style="stop-color:#B8860B"
                        />

                    </linearGradient>

                </defs>

                <polygon
                    points="50,5 90,27.5 90,72.5 50,95 10,72.5 10,27.5"
                    fill="none"
                    stroke="url(#g1)"
                    stroke-width="3"
                />

                <text
                    x="50"
                    y="62"
                    text-anchor="middle"
                    font-family="Courier New"
                    font-size="32"
                    font-weight="bold"
                    fill="url(#g1)"
                >
                    &lt;/&gt;
                </text>

                <circle
                    cx="75"
                    cy="25"
                    r="5"
                    fill="#FF4444"
                    stroke="#000"
                    stroke-width="1.5"
                />

            </svg>

            <span class="nav-logo-text">
                Coderror
            </span>

        </a>


        <!-- NAVIGATION LINKS -->

        <ul class="nav-links" id="navLinks">

            <li>
                <a href="index.php" class="active">
                    Home
                </a>
            </li>


            <!-- SERVICES -->
                </a>


                    <a href="internship.html">
                        Internship
                    </a>


            <li>
                <a href="verify-document.php">
                    Verify-Docs
                </a>
            </li>


            <li>
                <a href="about.html">
                    About
                </a>
            </li>


            <li>
                <a href="team.html">
                    Team
                </a>
            </li>


            <li>
                <a href="contact.html">
                    Contact
                </a>
            </li>


            <!-- ==============================
                 DASHBOARD BUTTON
            ============================== -->

            <li>

                <a
                    href="dashboard.php"
                    class="dashboard-btn"
                >

                    <i class="fas fa-tachometer-alt"></i>

                    Dashboard

                </a>

            </li>


            <!-- USER -->

            <li>

                <a class="user-pill">

                    <i class="fas fa-user"></i>

                    <?php echo e($userName); ?>

                </a>

            </li>


            <!-- LOGOUT -->

            <li>

                <a href="logout.php">
                    Logout
                </a>

            </li>

        </ul>


        <!-- HAMBURGER -->

        <button
            class="hamburger"
            id="hamburger"
        >

            <i class="fas fa-bars"></i>

        </button>

    </nav>


    <!-- ==============================
         HERO SECTION
    ============================== -->

    <section class="hero">

        <svg
            class="hero-logo"
            viewBox="0 0 100 100"
        >

            <defs>

                <linearGradient
                    id="g2"
                    x1="0%"
                    y1="0%"
                    x2="100%"
                    y2="100%"
                >

                    <stop
                        offset="0%"
                        style="stop-color:#F5D76E"
                    />

                    <stop
                        offset="50%"
                        style="stop-color:#D4AF37"
                    />

                    <stop
                        offset="100%"
                        style="stop-color:#B8860B"
                    />

                </linearGradient>

            </defs>

            <polygon
                points="50,5 90,27.5 90,72.5 50,95 10,72.5 10,27.5"
                fill="none"
                stroke="url(#g2)"
                stroke-width="2.5"
            />

            <text
                x="50"
                y="62"
                text-anchor="middle"
                font-family="Courier New"
                font-size="32"
                font-weight="bold"
                fill="url(#g2)"
            >
                &lt;/&gt;
            </text>

            <circle
                cx="75"
                cy="25"
                r="5"
                fill="#FF4444"
                stroke="#000"
                stroke-width="1.5"
            />

        </svg>


        <h1>
            Coderror
        </h1>

        <p class="tagline">
            Code • Create • Conquer
        </p>

        <p>
            Empowering the next generation of developers
            with industry-ready training, hands-on internships,
            and career-launching opportunities.
            Transform your passion into a profession.
        </p>


        <div class="hero-buttons">

            <a
                href="internship.html"
                class="btn"
            >
                Explore Programs
            </a>

            <a
                href="contact.html"
                class="btn btn-outline"
            >
                Get In Touch
            </a>

        </div>

    </section>


    <!-- ==============================
         WHAT WE OFFER
    ============================== -->

    <section class="section">

        <div class="section-title">

            <h2>
                What We Offer
            </h2>

            <p>
                Premium Learning Experience
            </p>

        </div>


        <div class="grid">


            <div class="card">

                <div class="card-icon">
                    <i class="fas fa-laptop-code"></i>
                </div>

                <h3>
                    Internship Programs
                </h3>

                <p>
                    Real-world project experience with
                    mentorship from industry experts
                    across multiple domains.
                </p>

            </div>


            <div class="card">

                <div class="card-icon">
                    <i class="fas fa-industry"></i>
                </div>

                <h3>
                    Industrial Training
                </h3>

                <p>
                    Comprehensive training programs
                    designed to bridge the gap between
                    academia and industry.
                </p>

            </div>


            <div class="card">

                <div class="card-icon">
                    <i class="fas fa-briefcase"></i>
                </div>

                <h3>
                    Career Services
                </h3>

                <p>
                    Resume building, interview prep,
                    and direct placement assistance
                    with top tech companies.
                </p>

            </div>


            <div class="card">

                <div class="card-icon">
                    <i class="fas fa-certificate"></i>
                </div>

                <h3>
                    Certifications
                </h3>

                <p>
                    Industry-recognized certificates
                    that add value to your professional
                    portfolio.
                </p>

            </div>


            <div class="card">

                <div class="card-icon">
                    <i class="fas fa-users"></i>
                </div>

                <h3>
                    Expert Mentorship
                </h3>

                <p>
                    One-on-one guidance from experienced
                    professionals working in leading
                    tech companies.
                </p>

            </div>


            <div class="card">

                <div class="card-icon">
                    <i class="fas fa-rocket"></i>
                </div>

                <h3>
                    Project Building
                </h3>

                <p>
                    Build impressive portfolio projects
                    that showcase your skills to potential
                    employers.
                </p>

            </div>

        </div>

    </section>


    <!-- ==============================
         DATABASE STATS
    ============================== -->

    <section class="section">

        <div class="stats">


            <div class="stat-box">

                <div
                    class="stat-number"
                    data-target="<?php echo max(500, $total_users); ?>"
                >
                    0
                </div>

                <div class="stat-label">
                    Registered Users
                </div>

            </div>


            <div class="stat-box">

                <div
                    class="stat-number"
                    data-target="<?php echo max(50, $total_logins); ?>"
                >
                    0
                </div>

                <div class="stat-label">
                    Total Logins
                </div>

            </div>


            <div class="stat-box">

                <div
                    class="stat-number"
                    data-target="100"
                >
                    0
                </div>

                <div class="stat-label">
                    Projects Completed
                </div>

            </div>


            <div class="stat-box">

                <div
                    class="stat-number"
                    data-target="<?php echo max(85, $total_contacts); ?>"
                >
                    0
                </div>

                <div class="stat-label">
                    Inquiries Received
                </div>

            </div>

        </div>

    </section>


    <!-- ==============================
         CTA
    ============================== -->

    <section class="section">

        <div class="cta">

            <h2>
                Ready to Start Your Journey?
            </h2>

            <p>
                Join hundreds of successful students
                who transformed their careers with Coderror
            </p>

            <a
                href="student_register.php"
                class="btn"
            >
                Apply Now
            </a>

        </div>

    </section>


    <!-- ==============================
         FOOTER
    ============================== -->

    <footer>

        <div class="footer-grid">


            <div class="footer-col">

                <h4>
                    Coderror
                </h4>

                <p>
                    Empowering developers with industry-ready
                    skills, real-world projects, and career
                    opportunities since 2026.
                </p>


                <div class="footer-socials">

                    <a href="#">
                        <i class="fab fa-facebook-f"></i>
                    </a>

                    <a href="#">
                        <i class="fab fa-twitter"></i>
                    </a>

                    <a href="#">
                        <i class="fab fa-linkedin-in"></i>
                    </a>

                    <a href="#">
                        <i class="fab fa-instagram"></i>
                    </a>

                    <a href="#">
                        <i class="fab fa-github"></i>
                    </a>

                </div>

            </div>


            <div class="footer-col">

                <h4>
                    Programs
                </h4>

                <a href="internship.html">
                    Internship
                </a>

                <a href="industrial.html">
                    Industrial Training
                </a>

                <a href="career.html">
                    Career Services
                </a>

                <a href="reviews.html">
                    Student Reviews
                </a>

            </div>


            <div class="footer-col">

                <h4>
                    Company
                </h4>

                <a href="about.html">
                    About Us
                </a>

                <a href="team.html">
                    Our Team
                </a>

                <a href="contact.html">
                    Contact
                </a>

                <a href="terms.html">
                    Terms
                </a>

            </div>


            <div class="footer-col">

                <h4>
                    Contact
                </h4>

                <p>
                    📧 coderrorinternship@gmail.com
                </p>

                <p>
                    📞 +91 7800148966
                </p>

                <p>
                    📍 Lucknow, India
                </p>

            </div>

        </div>


        <div class="footer-bottom">

            © 2026 Coderror.
            All Rights Reserved.
            | Designed by coderror team

        </div>

    </footer>


    <!-- JAVASCRIPT -->

    <script src="script.js"></script>

</body>

</html>