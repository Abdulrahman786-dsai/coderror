<?php

session_start();

require_once __DIR__ . '/config/db.php';

/*
|--------------------------------------------------------------------------
| HELPER
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars(
            (string)$value,
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/*
|--------------------------------------------------------------------------
| LOGIN SECURITY
|--------------------------------------------------------------------------
*/

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    empty($_SESSION['user_id'])
) {
    header("Location: login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| APPLICATION ID
|--------------------------------------------------------------------------
*/

$id = (int)($_GET['id'] ?? 0);

if ($id <= 0) {
    die('
        <div style="
            font-family:Arial;
            padding:40px;
            text-align:center;
        ">
            <h2>Invalid Certificate Request</h2>
            <p>Application ID is missing.</p>
        </div>
    ');
}

/*
|--------------------------------------------------------------------------
| LOGGED-IN USER
|--------------------------------------------------------------------------
*/

$user_stmt = $pdo->prepare("
    SELECT
        id,
        fullname,
        login_id,
        role
    FROM users
    WHERE id = ?
    LIMIT 1
");

$user_stmt->execute([
    $_SESSION['user_id']
]);

$user = $user_stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {

    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

/*
|--------------------------------------------------------------------------
| APPLICATION
|--------------------------------------------------------------------------
*/

$stmt = $pdo->prepare("
    SELECT *
    FROM internship_students
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $id
]);

$student = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$student) {

    die('
        <div style="
            font-family:Arial;
            padding:40px;
            text-align:center;
        ">
            <h2>Certificate Not Found</h2>
            <p>The requested internship application does not exist.</p>
        </div>
    ');
}

/*
|--------------------------------------------------------------------------
| ADMIN CHECK
|--------------------------------------------------------------------------
*/

$is_admin = (
    isset($_SESSION['role']) &&
    $_SESSION['role'] === 'admin'
);

/*
|--------------------------------------------------------------------------
| USER OWNERSHIP SECURITY
|--------------------------------------------------------------------------
*/

if (!$is_admin) {

    $logged_email = strtolower(
        trim($user['login_id'])
    );

    $student_email = strtolower(
        trim($student['email'] ?? '')
    );

    if (
        empty($student_email) ||
        $logged_email !== $student_email
    ) {

        http_response_code(403);

        die('
            <div style="
                font-family:Arial;
                padding:40px;
                text-align:center;
            ">
                <h2>Access Denied</h2>
                <p>You are not authorized to view this certificate.</p>
            </div>
        ');
    }
}

/*
|--------------------------------------------------------------------------
| APPLICATION APPROVAL CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($student['application_status']) ||
    $student['application_status'] !== 'Approved'
) {

    die('
        <div style="
            font-family:Arial;
            padding:40px;
            text-align:center;
        ">
            <h2>Certificate Not Available</h2>
            <p>The internship application has not been approved yet.</p>
        </div>
    ');
}

/*
|--------------------------------------------------------------------------
| CERTIFICATE APPROVAL CHECK
|--------------------------------------------------------------------------
*/

if (
    !isset($student['certificate_status']) ||
    $student['certificate_status'] !== 'Approved'
) {

    die('
        <div style="
            font-family:Arial;
            padding:40px;
            text-align:center;
        ">
            <h2>Certificate Pending Approval</h2>

            <p>
                The application has been approved, but the
                certificate has not yet been approved by the
                administrator.
            </p>
        </div>
    ');
}

/*
|--------------------------------------------------------------------------
| STUDENT DATA
|--------------------------------------------------------------------------
*/

$student_name =
    $student['fullname']
    ?? 'Student';

$student_id =
    $student['student_id']
    ?? 'N/A';

$college =
    $student['college']
    ?? 'N/A';

$course =
    $student['course']
    ?? 'N/A';

$branch =
    $student['branch']
    ?? 'N/A';

$internship_program =
    $student['internship_program']
    ?? 'Online Internship Program';

$duration =
    $student['duration']
    ?? 'N/A';

/*
|--------------------------------------------------------------------------
| DATE FORMAT
|--------------------------------------------------------------------------
*/

function format_certificate_date($date)
{
    if (empty($date)) {
        return '';
    }

    $ts = strtotime($date);

    return $ts !== false
        ? date('d F Y', $ts)
        : '';
}

$start_date = format_certificate_date(
    $student['internship_start_date'] ?? ''
);

$end_date = format_certificate_date(
    $student['internship_end_date'] ?? ''
);

/*
|--------------------------------------------------------------------------
| CERTIFICATE NUMBER
|--------------------------------------------------------------------------
*/

if (!empty($student['certificate_no'])) {

    $certificate_no =
        $student['certificate_no'];

} elseif (!empty($student['offer_letter_no'])) {

    $certificate_no =
        'CERT-' . $student['offer_letter_no'];

} else {

    $certificate_no =
        'CERT-' .
        date('Y') .
        '-' .
        str_pad(
            (string)$student['id'],
            5,
            '0',
            STR_PAD_LEFT
        );
}

/*
|--------------------------------------------------------------------------
| ISSUE DATE
|--------------------------------------------------------------------------
*/

$issue_date = format_certificate_date(
    $student['certificate_approved_at'] ?? ''
);

if (empty($issue_date)) {
    $issue_date = date('d F Y');
}

/*
|--------------------------------------------------------------------------
| SIGNATURE / STAMP
|--------------------------------------------------------------------------
*/

$signature_file =
    __DIR__ . '/assets/signature.png';

$stamp_file =
    __DIR__ . '/assets/stamp.png';

$signature_exists =
    file_exists($signature_file);

$stamp_exists =
    file_exists($stamp_file);

// MSME badge image
$msme_file =
    __DIR__ . '/assets/msme-badge.png';

$msme_exists =
    file_exists($msme_file);

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
    CODERROR Certificate -
    <?php echo e($student_name); ?>
</title>

<link
    href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Great+Vibes&family=Poppins:wght@300;400;500;600;700;800&display=swap"
    rel="stylesheet"
>

<style>

/* =========================================================
   GLOBAL
========================================================= */

* {
    box-sizing: border-box;
}

html,
body {
    margin: 0;
    padding: 0;

    background: #111;

    font-family:
        Georgia,
        "Times New Roman",
        serif;

    color: #222;
}


/* =========================================================
   TOOLBAR
========================================================= */

.toolbar {
    width: 100%;

    padding: 15px;

    text-align: center;

    background: #050505;
}

.toolbar button {
    border: none;

    padding: 11px 22px;

    margin: 0 5px;

    border-radius: 5px;

    cursor: pointer;

    font-family: "Poppins", sans-serif;

    font-size: 13px;

    font-weight: 700;
}

.print-btn {
    color: #000;

    background: #D4AF37;
}

.close-btn {
    color: #fff;

    background: #333;
}


/* =========================================================
   CERTIFICATE
   11 x 8.5 INCH LANDSCAPE
========================================================= */

.certificate {

    position: relative;

    width: 11in;

    height: 8.5in;

    min-height: 8.5in;

    margin: 25px auto;

    padding: 16mm;

    overflow: hidden;

    background: #fff;

    box-shadow:
        0 0 35px
        rgba(0, 0, 0, 0.55);
}


/* =========================================================
   OUTER GOLD BORDER
========================================================= */

.certificate::before {

    content: "";

    position: absolute;

    inset: 7mm;

    border:
        2px solid
        #D4AF37;

    pointer-events: none;

    z-index: 1;
}


/* =========================================================
   INNER GOLD BORDER
========================================================= */

.certificate::after {

    content: "";

    position: absolute;

    inset: 10mm;

    border:
        1px solid
        rgba(184, 134, 11, 0.55);

    pointer-events: none;

    z-index: 1;
}


/* =========================================================
   INNER CONTENT
========================================================= */

.inner {

    position: relative;

    z-index: 5;

    width: 100%;

    height: 100%;

    padding:
        5mm
        10mm
        25mm;
}


/* =========================================================
   HEADER
   LOGO + COMPANY NAME + TAGLINE SIDE BY SIDE
========================================================= */

.header {

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 14px;

    text-align: left;

    padding-bottom: 3.5mm;

    margin: 0 auto;

    border-bottom:
        1px solid
        #D4AF37;
}


/* =========================================================
   COMPANY LOGO
========================================================= */

.company-logo {

    display: block;

    width: 58px;

    height: 58px;

    margin: 0;

    object-fit: contain;

    object-position: center;

    flex-shrink: 0;
}


/* =========================================================
   COMPANY NAME
========================================================= */

.logo {

    font-family:
        "Poppins",
        sans-serif;

    font-size: 27px;

    font-weight: 800;

    letter-spacing: 4px;

    color: #D4AF37;

    line-height: 1;

    white-space: nowrap;
}

.logo span {
    color: #D4AF37;
}


/* =========================================================
   TAGLINE
========================================================= */

.tagline {

    margin-top: 0;

    color: #777;

    font-family:
        "Poppins",
        sans-serif;

    font-size: 8px;

    letter-spacing: 2.5px;

    white-space: nowrap;
}


/* =========================================================
   TITLE
========================================================= */

.title {

    text-align: center;

    margin: 5mm 0 3.5mm;
}

.title h1 {

    margin: 0;

    color: #111;

    font-family:
        "Cinzel",
        Georgia,
        serif;

    font-size: 28px;

    letter-spacing: 5px;

    text-transform: uppercase;
}

.title h2 {

    margin: 4px 0 0;

    color: #B8860B;

    font-size: 10px;

    font-weight: normal;

    letter-spacing: 3px;

    text-transform: uppercase;
}

.gold-line {

    width: 95px;

    height: 3px;

    margin: 8px auto;

    background: #D4AF37;
}


/* =========================================================
   META
========================================================= */

.certificate-meta {

    display: flex;

    justify-content: space-between;

    margin-bottom: 3.5mm;

    font-family:
        "Poppins",
        sans-serif;

    font-size: 9px;

    color: #555;
}

.certificate-meta strong {

    color: #111;
}


/* =========================================================
   INTRO
========================================================= */

.intro {

    text-align: center;

    font-size: 12px;

    line-height: 1.4;

    color: #444;
}


/* =========================================================
   STUDENT NAME
========================================================= */

.student-name {

    margin: 2.5mm 0 1.5mm;

    text-align: center;

    color: #111;

    font-family:
        "Cinzel",
        Georgia,
        serif;

    font-size: 23px;

    font-weight: bold;

    letter-spacing: 1px;
}

.student-underline {

    width: 165px;

    height: 1px;

    margin: 0 auto 3mm;

    background: #D4AF37;
}


/* =========================================================
   DESCRIPTION
========================================================= */

.description {

    width: 90%;

    margin: 0 auto;

    text-align: center;

    color: #444;

    font-size: 10.5px;

    line-height: 1.5;
}


/* =========================================================
   DETAILS
========================================================= */

.details {

    width: 90%;

    margin: 3.5mm auto 0;

    border:
        1px solid
        #ddd;
}

.detail-row {

    display: flex;

    min-height: 19px;

    border-bottom:
        1px solid
        #eee;
}

.detail-row:last-child {

    border-bottom: none;
}

.detail-label {

    width: 27%;

    padding: 4px 9px;

    background: #f7f1df;

    color: #555;

    font-family:
        "Poppins",
        sans-serif;

    font-size: 7.5px;

    font-weight: 700;

    text-transform: uppercase;
}

.detail-value {

    width: 73%;

    padding: 4px 9px;

    color: #222;

    font-family:
        "Poppins",
        sans-serif;

    font-size: 8.5px;
}


/* =========================================================
   COMPLETION
========================================================= */

.completion {

    width: 90%;

    margin: 3mm auto 0;

    text-align: center;

    color: #444;

    font-size: 9px;

    line-height: 1.35;
}


/* =========================================================
   SIGNATURE AREA
   FOUR EQUAL COLUMNS
========================================================= */

.signature-area {

    position: absolute;

    left: 15mm;

    right: 15mm;

    bottom: 17mm;

    display: grid;

    grid-template-columns:
        repeat(4, minmax(0, 1fr));

    column-gap: 8mm;

    align-items: end;

    z-index: 20;
}


/* =========================================================
   SIGNATURE BOX
========================================================= */

.signature-box {

    width: 100%;

    min-width: 0;

    height: 45mm;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: flex-end;

    text-align: center;

    font-family:
        "Poppins",
        sans-serif;

    font-size: 8.5px;

    color: #333;
}

.signature-box > * {
    flex-shrink: 0;
}


/* =========================================================
   SIGNATURE IMAGE
========================================================= */

.signature-image {

    display: block;

    width: 145px;

    height: 52px;

    margin: 0 auto 2px;

    object-fit: contain;
}


/* =========================================================
   SIGNATURE PLACEHOLDER
========================================================= */

.signature-placeholder {

    height: 30px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin-bottom: 2px;

    font-family:
        "Great Vibes",
        cursive;

    font-size: 20px;

    color: #111;
}


/* =========================================================
   SIGNATURE LINE
========================================================= */

.signature-line {

    width: 120px;

    margin: 0 auto 3px;

    border-top:
        1px solid
        #222;
}

.sign-name {

    font-weight: 700;

    color: #111;

    line-height: 1.2;
}

.sign-title {

    margin-top: 2px;

    color: #777;

    font-size: 7.5px;

    line-height: 1.3;
}


/* =========================================================
   STAMP
========================================================= */

.stamp-box {

    width: 78px;

    height: 78px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin: 0 auto 2px;
}

.stamp-image {

    max-width: 78px;

    max-height: 78px;

    object-fit: contain;
}

.stamp-placeholder {

    width: 60px;

    height: 60px;

    display: flex;

    align-items: center;

    justify-content: center;

    border:
        2px dashed
        #B8860B;

    border-radius: 50%;

    color: #B8860B;

    font-family:
        "Poppins",
        sans-serif;

    font-size: 7px;

    font-weight: bold;

    text-align: center;

    text-transform: uppercase;
}


/* =========================================================
   DATE
========================================================= */

.date-value {

    width: 100%;

    height: 78px;

    display: flex;

    align-items: flex-end;

    justify-content: center;

    padding-bottom: 10px;

    font-size: 11px;

    color: #222;

    white-space: nowrap;
}


/* =========================================================
   MSME BADGE
========================================================= */

.msme-box {

    width: 100%;

    height: 78px;

    display: flex;

    align-items: center;

    justify-content: center;

    margin: 0 auto 2px;
}

.msme-image {

    width: 105px;

    height: 78px;

    object-fit: contain;

    object-position: center;
}

.msme-placeholder {

    width: 105px;

    height: 70px;

    display: flex;

    flex-direction: column;

    align-items: center;

    justify-content: center;

    border:
        1px dashed
        #B8860B;

    color: #B8860B;

    font-family:
        "Poppins",
        sans-serif;

    font-size: 7px;

    font-weight: 700;

    text-align: center;
}


/* =========================================================
   FOOTER
========================================================= */

.footer {

    position: absolute;

    left: 16mm;

    right: 16mm;

    bottom: 5mm;

    height: 6mm;

    display: flex;

    align-items: center;

    justify-content: center;

    text-align: center;

    color: #777;

    font-family:
        "Poppins",
        sans-serif;

    font-size: 7px;

    letter-spacing: 0.8px;

    z-index: 30;

    border-top:
        1px solid
        rgba(184, 134, 11, 0.35);

    padding-top: 1.5mm;
}


/* =========================================================
   PRINT
========================================================= */

@media print {

    @page {

        size: 11in 8.5in;

        margin: 0;
    }

    html,
    body {

        width: 11in;

        height: 8.5in;

        margin: 0;

        padding: 0;

        background: #fff;
    }

    .toolbar {

        display: none !important;
    }

    .certificate {

        width: 11in;

        height: 8.5in;

        min-height: 8.5in;

        margin: 0;

        padding: 16mm;

        box-shadow: none;

        page-break-after: avoid;

        page-break-before: avoid;
    }

}


/* =========================================================
   MOBILE
========================================================= */

@media screen and (max-width: 900px) {

    .company-logo {

        width: 55px;

        height: 55px;
    }

    .logo {

        font-size: 23px;

        letter-spacing: 3px;
    }

    body {

        background: #111;
    }

    .certificate {

        width: 100%;

        height: auto;

        min-height: auto;

        margin: 0;

        padding: 35px 25px;
    }

    .certificate::before {

        inset: 10px;
    }

    .certificate::after {

        inset: 15px;
    }

    .inner {

        height: auto;

        padding:
            20px
            10px
            30px;
    }


    /* Mobile header */

    .header {

        flex-wrap: wrap;

        gap: 8px;

        justify-content: center;

        text-align: center;
    }

    .tagline {

        width: 100%;

        text-align: center;
    }


    .certificate-meta {

        flex-direction: column;

        gap: 6px;
    }

    .detail-row {

        flex-direction: column;
    }

    .detail-label,
    .detail-value {

        width: 100%;
    }

    .signature-area {

        position: relative;

        left: auto;

        right: auto;

        bottom: auto;

        margin-top: 25px;

        grid-template-columns: 1fr;

        row-gap: 25px;
    }

    .signature-box {

        width: 100%;

        height: auto;

        min-height: 150px;
    }

    .footer {

        position: relative;

        left: auto;

        right: auto;

        bottom: auto;

        margin-top: 25px;

        height: auto;

        padding: 8px 0;
    }

}

</style>

</head>

<body>


<!-- =====================================================
     TOOLBAR
====================================================== -->

<div class="toolbar">

    <button
        class="print-btn"
        onclick="window.print()"
    >
        🖨 Print / Save PDF
    </button>

    <button
        class="close-btn"
        onclick="window.close()"
    >
        ✕ Close
    </button>

</div>


<!-- =====================================================
     CERTIFICATE
===================================================== -->

<div class="certificate">

    <div class="inner">


        <!-- HEADER -->

        <div class="header">

            <img
                src="assets/logo.png"
                class="company-logo"
                alt="Coderror Logo"
            >

            <div class="logo">
                <span>CODERROR</span>
            </div>

        </div>


        <!-- TITLE -->

        <div class="title">

            <h1>
                Certificate
            </h1>

            <h2>
                Of Internship Completion
            </h2>

            <div class="gold-line"></div>

        </div>


        <!-- META -->

        <div class="certificate-meta">

            <div>

                <strong>
                    Certificate No:
                </strong>

                <?php echo e($certificate_no); ?>

            </div>


            <div>

                <strong>
                    Issue Date:
                </strong>

                <?php echo e($issue_date); ?>

            </div>

        </div>


        <!-- INTRO -->

        <div class="intro">

            This certificate is proudly presented to

        </div>


        <!-- STUDENT NAME -->

        <div class="student-name">

            <?php echo e($student_name); ?>

        </div>

        <div class="student-underline"></div>


        <!-- DESCRIPTION -->

        <div class="description">

            for successfully completing the

            <strong>
                <?php echo e($internship_program); ?>
            </strong>

            internship program with

            <strong>
                CODERROR
            </strong>

            and demonstrating commitment to
            learning, practical work and
            professional development.

        </div>


        <!-- DETAILS -->

        <div class="details">


            <!-- STUDENT ID -->

            <div class="detail-row">

                <div class="detail-label">
                    Student ID
                </div>

                <div class="detail-value">
                    <?php echo e($student_id); ?>
                </div>

            </div>


            <!-- COLLEGE -->

            <div class="detail-row">

                <div class="detail-label">
                    College / University
                </div>

                <div class="detail-value">
                    <?php echo e($college); ?>
                </div>

            </div>


            <!-- COURSE -->

            <div class="detail-row">

                <div class="detail-label">
                    Course
                </div>

                <div class="detail-value">
                    <?php echo e($course); ?>
                </div>

            </div>


            <!-- BRANCH -->

            <div class="detail-row">

                <div class="detail-label">
                    Branch / Specialization
                </div>

                <div class="detail-value">
                    <?php echo e($branch); ?>
                </div>

            </div>


            <!-- PROGRAM -->

            <div class="detail-row">

                <div class="detail-label">
                    Internship Program
                </div>

                <div class="detail-value">
                    <?php echo e($internship_program); ?>
                </div>

            </div>


            <!-- DURATION -->

            <div class="detail-row">

                <div class="detail-label">
                    Duration
                </div>

                <div class="detail-value">
                    <?php echo e($duration); ?>
                </div>

            </div>


            <!-- PERIOD -->

            <div class="detail-row">

                <div class="detail-label">
                    Internship Period
                </div>

                <div class="detail-value">

                    <?php echo e(
                        $start_date ?: 'N/A'
                    ); ?>

                    &nbsp; — &nbsp;

                    <?php echo e(
                        $end_date ?: 'N/A'
                    ); ?>

                </div>

            </div>

        </div>


        <!-- COMPLETION -->

        <div class="completion">

            During the internship, the student participated
            in assigned learning activities, practical tasks,
            projects and professional development activities
            related to the selected domain.

            <br>

            We appreciate the student's efforts and wish them
            continued success in their academic and
            professional journey.

        </div>

    </div>


    <!-- =================================================
         SIGNATURE SECTION
    ================================================== -->

    <div class="signature-area">


        <!-- AUTHORIZED SIGNATURE -->

        <div class="signature-box">

            <?php if ($signature_exists): ?>

                <img
                    src="assets/signature.png"
                    class="signature-image"
                    alt="Authorized Signature"
                >

            <?php else: ?>

                <div class="signature-placeholder">
                    Abdul Rahman
                </div>

            <?php endif; ?>


            <div class="signature-line"></div>


            <div class="sign-name">
                Abdul Rahman
            </div>


            <div class="sign-title">

                Founder &amp; CEO

                <br>

                CODERROR

            </div>

        </div>


        <!-- OFFICIAL STAMP -->

        <div class="signature-box">

            <div class="stamp-box">

                <?php if ($stamp_exists): ?>

                    <img
                        src="assets/stamp.png"
                        class="stamp-image"
                        alt="Official Stamp"
                    >

                <?php else: ?>

                    <div class="stamp-placeholder">

                        Official<br>
                        Stamp

                    </div>

                <?php endif; ?>

            </div>


            <div class="sign-title">

                Official Organization Stamp

            </div>

        </div>


        <!-- MSME BADGE -->

        <div class="signature-box">

            <div class="msme-box">

                <?php if ($msme_exists): ?>

                    <img
                        src="assets/msme-badge.png"
                        class="msme-image"
                        alt="MSME Badge"
                    >

                <?php else: ?>

                    <div class="msme-placeholder">

                        MSME<br>

                        MICRO, SMALL &amp;
                        MEDIUM ENTERPRISES

                    </div>

                <?php endif; ?>

            </div>


            <div class="sign-title">

                MSME

            </div>

        </div>


        <!-- DATE -->

        <div class="signature-box">

            <div class="date-value">

                <?php echo e($issue_date); ?>

            </div>


            <div class="signature-line"></div>


            <div class="sign-name">

                Date of Issue

            </div>


            <div class="sign-title">

                Official Record

            </div>

        </div>


    </div>


</div>


</body>

</html>