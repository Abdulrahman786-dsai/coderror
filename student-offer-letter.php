<?php

session_start();

require_once __DIR__ . '/config/db.php';

/* =========================================================
   HELPER
========================================================= */

function esc($value)
{
    return htmlspecialchars(
        (string)$value,
        ENT_QUOTES,
        'UTF-8'
    );
}

/* =========================================================
   LOGIN SECURITY
========================================================= */

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    empty($_SESSION['user_id'])
) {
    header("Location: login.php");
    exit();
}

/* =========================================================
   GET LOGGED-IN USER
========================================================= */

$user_stmt = $pdo->prepare("
    SELECT id, fullname, login_id, role, status
    FROM users
    WHERE id = ?
    LIMIT 1
");

$user_stmt->execute([
    $_SESSION['user_id']
]);

$user = $user_stmt->fetch();

if (!$user) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

/* =========================================================
   GET APPLICATION ID
========================================================= */

$application_id = (int)($_GET['id'] ?? 0);

/* =========================================================
   GET STUDENT APPLICATION
========================================================= */

if ($application_id > 0) {

    $stmt = $pdo->prepare("
        SELECT *
        FROM internship_students
        WHERE id = ?
        AND email = ?
        LIMIT 1
    ");

    $stmt->execute([
        $application_id,
        $user['login_id']
    ]);

} else {

    $stmt = $pdo->prepare("
        SELECT *
        FROM internship_students
        WHERE email = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $stmt->execute([
        $user['login_id']
    ]);
}

$student = $stmt->fetch();

/* =========================================================
   CHECK APPLICATION
========================================================= */

if (!$student) {

    http_response_code(404);

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Offer Letter Not Found - CODERROR</title>

        <style>

            * {
                box-sizing: border-box;
            }

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                background: #050505;
                color: #fff;
                font-family: Arial, sans-serif;
            }

            .box {
                width: 100%;
                max-width: 550px;
                padding: 40px;
                background: #151515;
                border: 1px solid #D4AF37;
                border-radius: 15px;
                text-align: center;
            }

            h2 {
                color: #D4AF37;
                margin-bottom: 15px;
            }

            p {
                color: #bbb;
                line-height: 1.7;
            }

            .btn {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 24px;
                background: #D4AF37;
                color: #000;
                text-decoration: none;
                border-radius: 7px;
                font-weight: bold;
            }

        </style>

    </head>

    <body>

        <div class="box">

            <h2>Offer Letter Not Found</h2>

            <p>
                No internship application was found for this account.
            </p>

            <a href="dashboard.php" class="btn">
                ← Back to Dashboard
            </a>

        </div>

    </body>

    </html>
    <?php

    exit();
}

/* =========================================================
   CHECK APPROVAL
========================================================= */

if (
    strtolower(
        trim($student['application_status'] ?? '')
    ) !== 'approved'
) {

    http_response_code(403);

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Offer Letter Pending - CODERROR</title>

        <style>

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                justify-content: center;
                align-items: center;
                background: #080808;
                color: #fff;
                font-family: Arial, sans-serif;
                padding: 20px;
            }

            .box {
                max-width: 550px;
                width: 100%;
                background: #151515;
                border: 1px solid #D4AF37;
                border-radius: 14px;
                padding: 40px;
                text-align: center;
            }

            h2 {
                color: #D4AF37;
            }

            p {
                color: #bbb;
                line-height: 1.7;
            }

            .btn {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 24px;
                background: #D4AF37;
                color: #000;
                text-decoration: none;
                border-radius: 7px;
                font-weight: bold;
            }

        </style>

    </head>

    <body>

        <div class="box">

            <h2>Offer Letter Pending</h2>

            <p>
                Your internship application has not been approved
                by the administrator yet.
            </p>

            <a href="dashboard.php" class="btn">
                ← Back to Dashboard
            </a>

        </div>

    </body>

    </html>
    <?php

    exit();
}

/* =========================================================
   CHECK OFFER LETTER NUMBER
========================================================= */

if (empty($student['offer_letter_no'])) {

    http_response_code(404);

    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>

        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

        <title>Offer Letter Unavailable - CODERROR</title>

        <style>

            body {
                margin: 0;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                background: #080808;
                color: #fff;
                font-family: Arial, sans-serif;
                padding: 20px;
            }

            .box {
                width: 90%;
                max-width: 550px;
                background: #151515;
                padding: 40px;
                border: 1px solid #D4AF37;
                border-radius: 14px;
                text-align: center;
            }

            h2 {
                color: #D4AF37;
            }

            p {
                color: #aaa;
            }

            .btn {
                display: inline-block;
                margin-top: 20px;
                padding: 12px 24px;
                background: #D4AF37;
                color: #000;
                text-decoration: none;
                border-radius: 7px;
                font-weight: bold;
            }

        </style>

    </head>

    <body>

        <div class="box">

            <h2>Offer Letter Not Generated</h2>

            <p>
                Your application is approved, but the offer letter
                number has not been generated yet.
            </p>

            <a href="dashboard.php" class="btn">
                ← Back to Dashboard
            </a>

        </div>

    </body>

    </html>

    <?php

    exit();
}

/* =========================================================
   OFFER DATA
========================================================= */

$offer_no = $student['offer_letter_no'];

$issue_date =
    !empty($student['offer_approved_at'])
        ? date(
            'd F Y',
            strtotime($student['offer_approved_at'])
        )
        : date('d F Y');

$start_date =
    !empty($student['internship_start_date'])
        ? date(
            'd F Y',
            strtotime($student['internship_start_date'])
        )
        : '';

$end_date =
    !empty($student['internship_end_date'])
        ? date(
            'd F Y',
            strtotime($student['internship_end_date'])
        )
        : '';

/* =========================================================
   ASSETS
========================================================= */

$signature_file = __DIR__ . '/assets/signature.png';
$stamp_file     = __DIR__ . '/assets/stamp.png';
$msme_file      = __DIR__ . '/assets/msme-badge.png';
$logo_file      = __DIR__ . '/assets/logo.png';

$signature_url = 'assets/signature.png';
$stamp_url     = 'assets/stamp.png';
$msme_url      = 'assets/msme-badge.png';
$logo_url      = 'assets/logo.png';

$has_logo      = file_exists($logo_file);
$has_signature = file_exists($signature_file);
$has_stamp     = file_exists($stamp_file);
$has_msme      = file_exists($msme_file);

?>

<!DOCTYPE html>

<html lang="en">

<head>

    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>
        Coderror Offer Letter -
        <?php echo esc($student['student_id']); ?>
    </title>

    <style>

        /* =====================================================
           RESET
        ===================================================== */

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        @page {
            size: A4 portrait;
            margin: 0;
        }

        html,
        body {
            width: 100%;
            margin: 0;
            padding: 0;
        }

        body {
            background: #151515;
            font-family: Georgia, "Times New Roman", serif;
            color: #222;
        }


        /* =====================================================
           TOOLBAR
        ===================================================== */

        .toolbar {
            width: 100%;
            padding: 12px;
            background: #050505;
            text-align: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .toolbar a,
        .toolbar button {
            display: inline-block;
            border: none;
            padding: 11px 22px;
            margin: 3px;
            background: #D4AF37;
            color: #000;
            font-weight: bold;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            font-size: 14px;
        }

        .toolbar a:hover,
        .toolbar button:hover {
            background: #F5D76E;
        }


        /* =====================================================
           A4 LETTER
        ===================================================== */

        .letter {

            width: 210mm;
            height: 297mm;

            margin: 15px auto;

            background: #fff;

            padding:
                11mm
                12mm
                9mm;

            position: relative;

            box-shadow:
                0 0 30px
                rgba(0,0,0,.45);

            overflow: hidden;
        }


        /* =====================================================
           GOLD BORDER
        ===================================================== */

        .letter::before {

            content: "";

            position: absolute;

            top: 4mm;
            left: 4mm;
            right: 4mm;
            bottom: 4mm;

            border: 1.5px solid #D4AF37;

            pointer-events: none;

            z-index: 1;
        }


        .inner {

            position: relative;

            z-index: 2;

            height: 100%;

            display: flex;

            flex-direction: column;
        }


        /* =====================================================
           HEADER
           LOGO + COMPANY NAME SIDE BY SIDE
        ===================================================== */

        .header {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 18px;

            padding:
                4px
                0
                10px;

            border-bottom:
                2px solid #D4AF37;

            flex-shrink: 0;
        }


        .header-logo {

            width: 62px;

            height: 62px;

            object-fit: contain;

            flex-shrink: 0;
        }


        .brand-area {
            text-align: left;
        }


        .logo {

            font-size: 28px;

            font-weight: bold;

            letter-spacing: 5px;

            color: #A77B16;

            line-height: 1.1;
        }


        .subtitle {

            margin-top: 5px;

            font-family: Arial, sans-serif;

            color: #555;

            font-size: 8px;

            font-weight: bold;

            letter-spacing: 2px;
        }


        /* =====================================================
           TITLE
        ===================================================== */

        .title {

            text-align: center;

            padding-top: 11px;

            padding-bottom: 7px;

            flex-shrink: 0;
        }


        .title h1 {

            font-family: Arial, sans-serif;

            color: #222;

            font-size: 22px;

            letter-spacing: 2px;

            font-weight: 700;
        }


        .title-decoration {

            display: flex;

            align-items: center;

            justify-content: center;

            gap: 8px;

            margin-top: 7px;
        }


        .title-decoration::before,
        .title-decoration::after {

            content: "";

            width: 110px;

            height: 1px;

            background: #D4AF37;
        }


        .diamond {

            width: 8px;

            height: 8px;

            background: #D4AF37;

            transform: rotate(45deg);
        }


        /* =====================================================
           META INFORMATION
        ===================================================== */

        .meta {

            display: flex;

            justify-content: space-between;

            align-items: center;

            margin-top: 3px;

            margin-bottom: 9px;

            font-size: 10px;

            border-bottom:
                1px solid #ddd;

            padding-bottom: 7px;

            flex-shrink: 0;
        }


        /* =====================================================
           MAIN CONTENT
        ===================================================== */

        .content {

            font-size: 10.5px;

            line-height: 1.42;

            flex-shrink: 0;
        }


        .content p {

            margin-bottom: 6px;
        }


        .student-name {

            font-size: 13px;

            font-weight: bold;

            margin-bottom: 3px !important;
        }


        /* =====================================================
           DETAILS TABLE
        ===================================================== */

        .details {

            margin: 9px 0;

            border:
                1px solid #cfcfcf;
        }


        .details-row {

            display: flex;

            min-height: 27px;

            border-bottom:
                1px solid #d7d7d7;
        }


        .details-row:last-child {
            border-bottom: none;
        }


        .details-label {

            width: 32%;

            padding: 6px 10px;

            background: #fafafa;

            font-weight: bold;

            font-size: 9.5px;

            display: flex;

            align-items: center;

            border-right:
                1px solid #d7d7d7;
        }


        .details-value {

            width: 68%;

            padding: 6px 10px;

            font-size: 9.5px;

            display: flex;

            align-items: center;
        }


        /* =====================================================
           FLEXIBLE SPACE
        ===================================================== */

        .bottom-spacer {

            flex-grow: 1;

            min-height: 5px;
        }


        /* =====================================================
           OFFICIAL SECTION
           Signature | Stamp | MSME | Issue Date
        ===================================================== */

        .official-section {

            display: grid;

            grid-template-columns:
                repeat(4, 1fr);

            align-items: end;

            justify-items: center;

            gap: 8px;

            margin-top: 8px;

            padding-top: 5px;

            flex-shrink: 0;

            page-break-inside: avoid;
        }


        .official-item {

            width: 100%;

            min-width: 0;

            text-align: center;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: flex-end;
        }


        /* =====================================================
           IMAGE AREA
        ===================================================== */

        .official-image-area {

            width: 100%;

            height: 105px;

            display: flex;

            align-items: center;

            justify-content: center;
        }


        /* =====================================================
           BIGGER IMAGES
        ===================================================== */

        .official-image {

            max-width: 140px;

            max-height: 100px;

            width: auto;

            height: auto;

            object-fit: contain;

            display: block;
        }


        /* =====================================================
           LABEL BELOW IMAGES
        ===================================================== */

        .official-label {

            margin-top: 5px;

            font-family: Arial, sans-serif;

            font-size: 9px;

            font-weight: 600;

            color: #333;

            line-height: 1.35;

            text-align: center;

            min-height: 25px;
        }


        /* =====================================================
           ISSUE DATE
        ===================================================== */

        .issue-date {

            height: 105px;

            display: flex;

            flex-direction: column;

            align-items: center;

            justify-content: center;

            font-size: 12px;

            font-weight: bold;

            color: #222;

            text-align: center;

            line-height: 1.5;
        }


        /* =====================================================
           FOOTER
        ===================================================== */

        .footer {

            text-align: center;

            font-size: 7px;

            color: #666;

            padding-top: 4px;

            flex-shrink: 0;
        }

        .footer strong {
            color: #333;
        }


        /* =====================================================
           PRINT SETTINGS
        ===================================================== */

        @media print {

            @page {
                size: A4 portrait;
                margin: 0;
            }

            html,
            body {

                width: 210mm;

                height: 297mm;

                margin: 0;

                padding: 0;

                background: #fff;

                -webkit-print-color-adjust: exact;

                print-color-adjust: exact;
            }


            .toolbar {
                display: none !important;
            }


            .letter {

                width: 210mm;

                height: 297mm;

                margin: 0;

                padding:
                    11mm
                    12mm
                    9mm;

                box-shadow: none;

                overflow: hidden;

                page-break-after: avoid;
            }


            .official-section {

                break-inside: avoid;

                page-break-inside: avoid;
            }


            .footer {

                break-inside: avoid;
            }

        }


        /* =====================================================
           MOBILE RESPONSIVE
        ===================================================== */

        @media screen and (max-width: 800px) {

            .letter {

                width: 100%;

                height: auto;

                min-height: 297mm;

                margin: 0;

                padding: 20px;
            }


            .header {
                gap: 10px;
            }


            .header-logo {

                width: 50px;

                height: 50px;
            }


            .logo {
                font-size: 21px;
            }


            .official-section {

                grid-template-columns:
                    repeat(2, 1fr);

                gap: 15px;
            }


            .official-image {

                max-width: 130px;

                max-height: 100px;
            }

        }

    </style>

</head>


<body>


<!-- =====================================================
     TOOLBAR
===================================================== -->

<div class="toolbar">

    <a href="dashboard.php">
        ← Dashboard
    </a>

    <button onclick="window.print()">
        🖨️ Print / Save as PDF
    </button>

</div>


<!-- =====================================================
     OFFER LETTER
===================================================== -->

<div class="letter">

    <div class="inner">


        <!-- HEADER -->

        <div class="header">

            <?php if ($has_logo): ?>

                <img
                    src="<?php echo esc($logo_url); ?>"
                    class="header-logo"
                    alt="Coderror Logo"
                >

            <?php endif; ?>


            <div class="brand-area">

                <div class="logo">
                    CODERROR
                </div>

                <div class="subtitle">
                    TECHNOLOGY • LEARNING • INNOVATION
                </div>

            </div>

        </div>


        <!-- TITLE -->

        <div class="title">

            <h1>
                INTERNSHIP OFFER LETTER
            </h1>

            <div class="title-decoration">
                <span class="diamond"></span>
            </div>

        </div>


        <!-- META INFORMATION -->

        <div class="meta">

            <div>

                <strong>
                    Offer Letter No:
                </strong>

                <?php echo esc($offer_no); ?>

            </div>


            <div>

                <strong>
                    Issue Date:
                </strong>

                <?php echo esc($issue_date); ?>

            </div>

        </div>


        <!-- MAIN CONTENT -->

        <div class="content">


            <p>
                <strong>To,</strong>
            </p>


            <p class="student-name">

                <?php
                echo esc($student['fullname']);
                ?>

            </p>


            <p>

                Email:

                <?php
                echo esc($student['email']);
                ?>

            </p>


            <p>

                Dear

                <strong>

                    <?php
                    echo esc($student['fullname']);
                    ?>

                </strong>,

            </p>


            <p>

                We are pleased to inform you that your application
                for an internship with
                <strong>CODERROR</strong>
                has been approved.

            </p>


            <p>

                Based on your application and the information
                provided by you, we are pleased to offer you an
                internship opportunity with
                <strong>CODERROR</strong>.

            </p>


            <!-- DETAILS TABLE -->

            <div class="details">


                <div class="details-row">

                    <div class="details-label">
                        Internship Program
                    </div>

                    <div class="details-value">

                        <?php
                        echo esc(
                            $student['internship_program'] ?? ''
                        );
                        ?>

                    </div>

                </div>


                <div class="details-row">

                    <div class="details-label">
                        Course
                    </div>

                    <div class="details-value">

                        <?php
                        echo esc(
                            $student['course'] ?? ''
                        );
                        ?>

                    </div>

                </div>


                <div class="details-row">

                    <div class="details-label">
                        Duration
                    </div>

                    <div class="details-value">

                        <?php
                        echo esc(
                            $student['duration'] ?? ''
                        );
                        ?>

                    </div>

                </div>


                <div class="details-row">

                    <div class="details-label">
                        Internship Mode
                    </div>

                    <div class="details-value">

                        <?php
                        echo esc(
                            $student['internship_mode']
                            ?? 'ONLINE'
                        );
                        ?>

                    </div>

                </div>


                <div class="details-row">

                    <div class="details-label">
                        Start Date
                    </div>

                    <div class="details-value">

                        <?php
                        echo esc($start_date);
                        ?>

                    </div>

                </div>


                <div class="details-row">

                    <div class="details-label">
                        End Date
                    </div>

                    <div class="details-value">

                        <?php
                        echo esc($end_date);
                        ?>

                    </div>

                </div>


            </div>


            <p>

                You are expected to maintain professional conduct
                throughout the internship and comply with all
                applicable policies and guidelines of
                <strong>CODERROR</strong>.

            </p>


            <p>

                We congratulate you on your selection and wish you
                a successful and productive internship experience.

            </p>


        </div>


        <!-- FLEXIBLE SPACE -->

        <div class="bottom-spacer"></div>


        <!-- =================================================
             OFFICIAL SECTION

             Signature | Stamp | MSME | Issue Date
        ================================================= -->

        <div class="official-section">


            <!-- SIGNATURE -->

            <div class="official-item">

                <div class="official-image-area">

                    <?php if ($has_signature): ?>

                        <img
                            src="<?php echo esc($signature_url); ?>"
                            class="official-image"
                            alt="Authorized Signature"
                        >

                    <?php endif; ?>

                </div>


                <div class="official-label">

                    Founder & CEO

                    <br>

                    Abdul Rahman

                </div>

            </div>


            <!-- OFFICIAL STAMP -->

            <div class="official-item">

                <div class="official-image-area">

                    <?php if ($has_stamp): ?>

                        <img
                            src="<?php echo esc($stamp_url); ?>"
                            class="official-image"
                            alt="Official Stamp"
                        >

                    <?php endif; ?>

                </div>


                <div class="official-label">

                    Official Stamp

                    <br>

                    CODERROR

                </div>

            </div>


            <!-- MSME -->

            <div class="official-item">

                <div class="official-image-area">

                    <?php if ($has_msme): ?>

                        <img
                            src="<?php echo esc($msme_url); ?>"
                            class="official-image"
                            alt="MSME"
                        >

                    <?php endif; ?>

                </div>


                <div class="official-label">
                    MSME Registration
                </div>

            </div>


            <!-- ISSUE DATE -->

            <div class="official-item">

                <div class="issue-date">

                    <strong>
                        Issue Date
                    </strong>

                    <br>

                    <?php echo esc($issue_date); ?>

                </div>


                <div class="official-label">
                    Document Issue Date
                </div>

            </div>


        </div>


        <!-- FOOTER -->

        <div class="footer">

            <strong>
                CODERROR
            </strong>

            • Online Internship Program •

            Offer Letter No.

            <?php
            echo esc($offer_no);
            ?>

        </div>


    </div>

</div>


</body>

</html>