<?php
/**
 * ============================================================
 * Coderror — Offer Letter & Certificate Verification
 * File: verify-document.php
 * ============================================================
 */

session_start();

require_once __DIR__ . '/config/db.php';

/*
|--------------------------------------------------------------------------
| HELPERS
|--------------------------------------------------------------------------
*/

if (!function_exists('e')) {
    function e($value)
    {
        return htmlspecialchars(
            (string)($value ?? ''),
            ENT_QUOTES,
            'UTF-8'
        );
    }
}

/*
|--------------------------------------------------------------------------
| PAGE VARIABLES
|--------------------------------------------------------------------------
*/

$document_type = strtolower(
    trim($_POST['document_type'] ?? $_GET['type'] ?? 'offer')
);
$document_number = trim($_POST['document_number'] ?? '');

$error_message = '';
$success_message = '';
$verified = false;
$document = null;

/*
|--------------------------------------------------------------------------
| VALID DOCUMENT TYPES
|--------------------------------------------------------------------------
*/

if (!in_array($document_type, ['offer', 'certificate'], true)) {
    $document_type = 'offer';
}

/*
|--------------------------------------------------------------------------
| FIND POSSIBLE CERTIFICATE NUMBER COLUMN
|--------------------------------------------------------------------------
|
| Your current dashboard confirms offer_letter_no.
| Certificate number column was not visible in the supplied code.
|
| This checks common names automatically.
|--------------------------------------------------------------------------
*/

$certificate_column = null;

try {

    $columns_stmt = $pdo->query("SHOW COLUMNS FROM internship_students");

    $columns = $columns_stmt->fetchAll(PDO::FETCH_ASSOC);

    $available_columns = [];

    foreach ($columns as $column) {
        if (isset($column['Field'])) {
            $available_columns[] = $column['Field'];
        }
    }

    /*
     * Common certificate-number column names.
     */
    $possible_certificate_columns = [
        'certificate_no',
        'certificate_number',
        'certificate_id',
        'certificate_code',
        'certificate_serial_no',
        'certificate_serial',
        'cert_no'
    ];

    foreach ($possible_certificate_columns as $possible_column) {

        if (in_array($possible_column, $available_columns, true)) {
            $certificate_column = $possible_column;
            break;
        }
    }

} catch (PDOException $e) {

    error_log(
        "Verification column check error: " .
        $e->getMessage()
    );

    $error_message = "Verification service is temporarily unavailable.";
}

/*
|--------------------------------------------------------------------------
| DOCUMENT VERIFICATION
|--------------------------------------------------------------------------
*/

if (
    $_SERVER['REQUEST_METHOD'] === 'POST' &&
    $document_number !== '' &&
    $error_message === ''
) {

    /*
     * Basic input length protection.
     */
    if (strlen($document_number) > 100) {

        $error_message = "Please enter a valid document number.";

    } else {

        try {

            /*
            |--------------------------------------------------------------------------
            | OFFER LETTER VERIFICATION
            |--------------------------------------------------------------------------
            */

            if ($document_type === 'offer') {

                /*
                 * Offer Letter must:
                 * - exist
                 * - application be approved
                 * - offer letter be approved
                 * - offer letter number match
                 */

                $stmt = $pdo->prepare("
                    SELECT *
                    FROM internship_students
                    WHERE LOWER(TRIM(offer_letter_no)) = LOWER(TRIM(?))
                      AND LOWER(TRIM(offer_letter_status)) = 'approved'
                    ORDER BY id DESC
                    LIMIT 1
                ");

                $stmt->execute([
                    $document_number
                ]);

                $document = $stmt->fetch(PDO::FETCH_ASSOC);

            }

            /*
            |--------------------------------------------------------------------------
            | CERTIFICATE VERIFICATION
            |--------------------------------------------------------------------------
            */

            else {

                /*
                 * We need a certificate number column.
                 */

                if ($certificate_column === null) {

                    $error_message =
                        "Certificate verification is not configured yet. " .
                        "Please add a certificate number column to the " .
                        "internship_students table.";

                } else {

                    /*
                     * Column name is selected only from SHOW COLUMNS,
                     * therefore it is safe to insert into this SQL statement.
                     */

                    $sql = "
                        SELECT *
                        FROM internship_students
                        WHERE `$certificate_column` = ?
                          AND LOWER(TRIM(application_status)) = 'approved'
                          AND LOWER(TRIM(certificate_status)) = 'approved'
                        ORDER BY id DESC
                        LIMIT 1
                    ";

                    $stmt = $pdo->prepare($sql);

                    $stmt->execute([
                        $document_number
                    ]);

                    $document = $stmt->fetch(PDO::FETCH_ASSOC);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | VERIFIED
            |--------------------------------------------------------------------------
            */

            if ($document) {

                $verified = true;

                $success_message =
                    $document_type === 'offer'
                    ? 'Offer Letter verified successfully.'
                    : 'Certificate verified successfully.';
            }

            /*
            |--------------------------------------------------------------------------
            | NOT FOUND
            |--------------------------------------------------------------------------
            */

            elseif ($error_message === '') {

                $error_message =
                    $document_type === 'offer'
                    ? 'Offer Letter not found or not approved.'
                    : 'Certificate not found or not approved.';
            }

        } catch (PDOException $e) {

            error_log(
                "Document verification error: " .
                $e->getMessage()
            );

            $error_message =
                "Unable to verify this document. Please try again later.";
        }
    }
}

/*
|--------------------------------------------------------------------------
| DISPLAY HELPERS
|--------------------------------------------------------------------------
*/

$student_name = '';
$student_id = '';
$branch = '';
$email = '';
$start_date = '';
$end_date = '';
$document_no = '';
$application_status = '';
$document_status = '';

if ($verified && $document) {

    /*
     * Student name
     */
    $student_name =
        $document['fullname']
        ?? $document['full_name']
        ?? $document['name']
        ?? $document['student_name']
        ?? '';

    /*
     * Student ID
     */
    $student_id =
        $document['student_id']
        ?? $document['studentid']
        ?? $document['registration_no']
        ?? '';

    /*
     * Internship branch
     */
    $branch =
        $document['internship_branch']
        ?? $document['branch']
        ?? $document['program']
        ?? $document['course']
        ?? $document['domain']
        ?? $document['internship_domain']
        ?? '';

    /*
     * Email
     */
    $email =
        $document['email']
        ?? '';

    /*
     * Start date
     */
    $start_date =
        $document['internship_start_date']
        ?? $document['start_date']
        ?? $document['joining_date']
        ?? $document['internship_start']
        ?? '';

    /*
     * End date
     */
    $end_date =
        $document['internship_end_date']
        ?? $document['end_date']
        ?? $document['completion_date']
        ?? $document['internship_end']
        ?? '';

    /*
     * Document number
     */
    if ($document_type === 'offer') {

        $document_no =
            $document['offer_letter_no']
            ?? $document_number;

        $document_status =
            $document['offer_letter_status']
            ?? 'Approved';

    } else {

        if ($certificate_column !== null) {

            $document_no =
                $document[$certificate_column]
                ?? $document_number;

        } else {

            $document_no = $document_number;
        }

        $document_status =
            $document['certificate_status']
            ?? 'Approved';
    }

    $application_status =
        $document['application_status']
        ?? 'Approved';
}

/*
|--------------------------------------------------------------------------
| FORMAT DATE
|--------------------------------------------------------------------------
*/

function formatVerificationDate($date)
{
    if (empty($date)) {
        return '';
    }

    $timestamp = strtotime($date);

    if (!$timestamp) {
        return e($date);
    }

    return date('d M Y', $timestamp);
}

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
        Coderror — Document Verification
    </title>

    <link
        href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;600;700&family=Poppins:wght@300;400;500;600;700&display=swap"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
    >

    <style>

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --gold: #D4AF37;
            --gold-light: #F5D76E;
            --gold-dark: #B8860B;
            --black: #050505;
            --card: #111111;
            --green: #35c759;
            --red: #ff5c5c;
            --blue: #2563eb;
        }

        body {
            min-height: 100vh;
            font-family: 'Poppins', sans-serif;
            color: #fff;

            background:
                radial-gradient(
                    circle at top,
                    #202020 0%,
                    #0b0b0b 45%,
                    #000 100%
                );

            padding: 30px 15px;
        }

        .page {
            width: 100%;
            max-width: 760px;
            margin: auto;
        }

        /*
        |--------------------------------------------------------------------------
        | HEADER
        |--------------------------------------------------------------------------
        */

        .header {
            text-align: center;
            margin-bottom: 25px;
        }

        .logo {
            width: 72px;
            height: 72px;
            margin: 0 auto 15px;

            filter:
                drop-shadow(
                    0 0 15px
                    rgba(212,175,55,.45)
                );
        }

        .header h1 {
            font-family: 'Cinzel', serif;
            font-size: 30px;
            letter-spacing: 3px;

            background:
                linear-gradient(
                    135deg,
                    var(--gold-light),
                    var(--gold),
                    var(--gold-dark)
                );

            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .header p {
            margin-top: 7px;
            color: rgba(212,175,55,.65);
            font-size: 12px;
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        /*
        |--------------------------------------------------------------------------
        | MAIN CARD
        |--------------------------------------------------------------------------
        */

        .card {
            position: relative;

            padding: 35px;

            background:
                rgba(17,17,17,.94);

            border:
                1px solid
                rgba(212,175,55,.28);

            border-radius: 20px;

            box-shadow:
                0 0 45px
                rgba(212,175,55,.10);

            backdrop-filter: blur(15px);
        }

        .card::before,
        .card::after {
            content: '';

            position: absolute;

            width: 28px;
            height: 28px;

            border: 2px solid var(--gold);
        }

        .card::before {
            top: 10px;
            left: 10px;

            border-right: none;
            border-bottom: none;
        }

        .card::after {
            right: 10px;
            bottom: 10px;

            border-left: none;
            border-top: none;
        }

        .verify-title {
            text-align: center;
            margin-bottom: 25px;
        }

        .verify-title i {
            font-size: 30px;
            color: var(--gold);
            margin-bottom: 10px;
        }

        .verify-title h2 {
            color: var(--gold-light);
            font-size: 21px;
        }

        .verify-title p {
            color: rgba(255,255,255,.55);
            font-size: 13px;
            margin-top: 5px;
        }

        /*
        |--------------------------------------------------------------------------
        | TABS
        |--------------------------------------------------------------------------
        */

        .tabs {
            display: grid;
            grid-template-columns: 1fr 1fr;

            gap: 10px;
            margin-bottom: 25px;
        }

        .tab {
            position: relative;

            display: flex;
            align-items: center;
            justify-content: center;

            gap: 8px;

            padding: 13px;

            border-radius: 10px;

            color: rgba(255,255,255,.55);

            background:
                rgba(255,255,255,.04);

            border:
                1px solid
                rgba(255,255,255,.08);

            text-decoration: none;

            font-size: 12px;
            font-weight: 600;

            transition: .25s ease;
        }

        .tab:hover {
            border-color: rgba(212,175,55,.45);
            color: var(--gold-light);
        }

        .tab.active {
            color: #111;

            background:
                linear-gradient(
                    135deg,
                    var(--gold-light),
                    var(--gold),
                    var(--gold-dark)
                );

            border-color: var(--gold);
        }

        /*
        |--------------------------------------------------------------------------
        | FORM
        |--------------------------------------------------------------------------
        */

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;

            margin-bottom: 8px;

            color: var(--gold-light);

            font-size: 12px;
            font-weight: 600;

            letter-spacing: 1px;
            text-transform: uppercase;
        }

        .input-wrap {
            position: relative;
        }

        .input-wrap i {
            position: absolute;

            left: 15px;
            top: 50%;

            transform: translateY(-50%);

            color: var(--gold);

            font-size: 15px;
        }

        .input-wrap input {
            width: 100%;

            padding: 14px 45px;

            color: #fff;

            background:
                rgba(0,0,0,.55);

            border:
                1px solid
                rgba(212,175,55,.25);

            border-radius: 10px;

            outline: none;

            font-family: 'Poppins', sans-serif;
            font-size: 14px;

            transition: .25s ease;
        }

        .input-wrap input:focus {
            border-color: var(--gold);

            box-shadow:
                0 0 15px
                rgba(212,175,55,.12);
        }

        .input-wrap input::placeholder {
            color: rgba(255,255,255,.30);
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFY BUTTON
        |--------------------------------------------------------------------------
        */

        .verify-btn {
            width: 100%;

            display: flex;
            align-items: center;
            justify-content: center;

            gap: 10px;

            padding: 15px;

            border: none;
            border-radius: 10px;

            color: #080808;

            background:
                linear-gradient(
                    135deg,
                    var(--gold-light),
                    var(--gold),
                    var(--gold-dark)
                );

            font-family: 'Poppins', sans-serif;

            font-size: 13px;
            font-weight: 700;

            letter-spacing: 2px;
            text-transform: uppercase;

            cursor: pointer;

            transition: .25s ease;
        }

        .verify-btn:hover {
            transform: translateY(-2px);

            box-shadow:
                0 12px 30px
                rgba(212,175,55,.28);
        }

        /*
        |--------------------------------------------------------------------------
        | ALERTS
        |--------------------------------------------------------------------------
        */

        .alert {
            display: flex;
            align-items: center;

            gap: 10px;

            padding: 13px 15px;

            margin-bottom: 20px;

            border-radius: 10px;

            font-size: 13px;
        }

        .alert.error {
            color: #ff9a9a;

            background:
                rgba(255,60,60,.09);

            border:
                1px solid
                rgba(255,60,60,.25);
        }

        .alert.success {
            color: #8af5a2;

            background:
                rgba(53,199,89,.09);

            border:
                1px solid
                rgba(53,199,89,.30);
        }

        /*
        |--------------------------------------------------------------------------
        | VERIFIED RESULT
        |--------------------------------------------------------------------------
        */

        .verified-box {
            margin-top: 30px;

            padding: 25px;

            border-radius: 15px;

            background:
                linear-gradient(
                    135deg,
                    rgba(53,199,89,.10),
                    rgba(0,0,0,.30)
                );

            border:
                1px solid
                rgba(53,199,89,.35);
        }

        .verified-head {
            text-align: center;

            padding-bottom: 20px;

            margin-bottom: 20px;

            border-bottom:
                1px solid
                rgba(53,199,89,.18);
        }

        .verified-icon {
            width: 65px;
            height: 65px;

            margin: auto;

            display: flex;
            align-items: center;
            justify-content: center;

            border-radius: 50%;

            color: #fff;

            background:
                linear-gradient(
                    135deg,
                    #35c759,
                    #16833a
                );

            box-shadow:
                0 0 25px
                rgba(53,199,89,.30);
        }

        .verified-icon i {
            font-size: 28px;
        }

        .verified-head h3 {
            margin-top: 13px;

            color: #65e27f;

            font-size: 20px;

            letter-spacing: 2px;
        }

        .verified-head p {
            margin-top: 4px;

            color: rgba(255,255,255,.50);

            font-size: 11px;

            text-transform: uppercase;

            letter-spacing: 1.5px;
        }

        /*
        |--------------------------------------------------------------------------
        | DETAILS
        |--------------------------------------------------------------------------
        */

        .details {
            display: grid;

            grid-template-columns:
                repeat(2, 1fr);

            gap: 10px;
        }

        .detail {
            padding: 13px;

            border-radius: 9px;

            background:
                rgba(0,0,0,.28);

            border:
                1px solid
                rgba(255,255,255,.07);
        }

        .detail.full {
            grid-column: 1 / -1;
        }

        .detail-label {
            display: block;

            margin-bottom: 5px;

            color: rgba(255,255,255,.38);

            font-size: 9px;

            letter-spacing: 1px;

            text-transform: uppercase;
        }

        .detail-value {
            color: rgba(255,255,255,.90);

            font-size: 13px;

            font-weight: 600;

            word-break: break-word;
        }

        .verified-status {
            display: inline-flex;
            align-items: center;

            gap: 6px;

            padding: 5px 10px;

            border-radius: 20px;

            color: #65e27f;

            background:
                rgba(53,199,89,.10);

            border:
                1px solid
                rgba(53,199,89,.25);

            font-size: 10px;
            font-weight: 700;

            letter-spacing: 1px;
        }

        /*
        |--------------------------------------------------------------------------
        | FOOTER
        |--------------------------------------------------------------------------
        */

        .footer {
            margin-top: 25px;

            text-align: center;

            color: rgba(255,255,255,.35);

            font-size: 11px;

            line-height: 1.7;
        }

        .footer strong {
            color: var(--gold);
        }

        .back-btn {
            display: inline-flex;

            align-items: center;
            justify-content: center;

            gap: 7px;

            margin-top: 15px;

            padding: 10px 17px;

            color: var(--gold);

            border:
                1px solid
                rgba(212,175,55,.35);

            border-radius: 8px;

            text-decoration: none;

            font-size: 11px;

            transition: .25s ease;
        }

        .back-btn:hover {
            color: #000;

            background: var(--gold);
        }

        /*
        |--------------------------------------------------------------------------
        | MOBILE
        |--------------------------------------------------------------------------
        */

        @media (max-width: 600px) {

            body {
                padding: 18px 12px;
            }

            .card {
                padding: 25px 18px;
            }

            .header h1 {
                font-size: 24px;
            }

            .tabs {
                grid-template-columns: 1fr;
            }

            .details {
                grid-template-columns: 1fr;
            }

            .detail.full {
                grid-column: auto;
            }

            .verified-box {
                padding: 18px;
            }
        }

    </style>

</head>

<body>

<div class="page">

    <!-- =====================================================
         HEADER
    ====================================================== -->

    <div class="header">

        <svg
            class="logo"
            viewBox="0 0 100 100"
            xmlns="http://www.w3.org/2000/svg"
        >

            <defs>

                <linearGradient
                    id="goldGradient"
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
                stroke="url(#goldGradient)"
                stroke-width="2.5"
            />

            <text
                x="50"
                y="62"
                text-anchor="middle"
                font-family="Courier New, monospace"
                font-size="32"
                font-weight="bold"
                fill="url(#goldGradient)"
            >&lt;/&gt;</text>

            <circle
                cx="75"
                cy="25"
                r="5"
                fill="#FF4444"
                stroke="#000"
                stroke-width="1.5"
            />

        </svg>

        <h1>CODERROR</h1>

        <p>Official Document Verification</p>

    </div>


    <!-- =====================================================
         MAIN
    ====================================================== -->

    <div class="card">

        <div class="verify-title">

            <i class="fas fa-shield-check"></i>

            <h2>Verify Internship Document</h2>

            <p>
                Verify the authenticity of a Coderror
                Offer Letter or Certificate.
            </p>

        </div>


        <!-- =================================================
             DOCUMENT TYPE
        ================================================== -->

        <div class="tabs">

            <a
                href="verify-document.php?type=offer"
                class="tab <?php echo $document_type === 'offer' ? 'active' : ''; ?>"
            >
                <i class="fas fa-file-contract"></i>
                Offer Letter
            </a>

            <a
                href="verify-document.php?type=certificate"
                class="tab <?php echo $document_type === 'certificate' ? 'active' : ''; ?>"
            >
                <i class="fas fa-certificate"></i>
                Certificate
            </a>

        </div>


        <!-- =================================================
             ALERTS
        ================================================== -->

        <?php if ($error_message): ?>

            <div class="alert error">

                <i class="fas fa-circle-exclamation"></i>

                <span>
                    <?php echo e($error_message); ?>
                </span>

            </div>

        <?php endif; ?>


        <?php if ($success_message): ?>

            <div class="alert success">

                <i class="fas fa-circle-check"></i>

                <span>
                    <?php echo e($success_message); ?>
                </span>

            </div>

        <?php endif; ?>


        <!-- =================================================
             VERIFICATION FORM
        ================================================== -->

        <form
            method="POST"
            action="verify-document.php"
        >

            <input
                type="hidden"
                name="document_type"
                value="<?php echo e($document_type); ?>"
            >

            <div class="form-group">

                <label for="document_number">
                    <?php
                    echo $document_type === 'offer'
                        ? 'Offer Letter Number'
                        : 'Certificate Number';
                    ?>
                </label>

                <div class="input-wrap">

                    <i class="fas fa-fingerprint"></i>

                    <input
                        type="text"
                        id="document_number"
                        name="document_number"
                        value="<?php echo e($document_number); ?>"
                        placeholder="<?php
                            echo $document_type === 'offer'
                                ? 'Enter offer letter number'
                                : 'Enter certificate number';
                        ?>"
                        maxlength="100"
                        autocomplete="off"
                        required
                    >

                </div>

            </div>


            <button
                type="submit"
                class="verify-btn"
            >

                <i class="fas fa-shield-check"></i>

                Verify Document

            </button>

        </form>


        <!-- =================================================
             VERIFIED DOCUMENT
        ================================================== -->

        <?php if ($verified && $document): ?>

            <div class="verified-box">

                <div class="verified-head">

                    <div class="verified-icon">

                        <i class="fas fa-check"></i>

                    </div>

                    <h3>DOCUMENT VERIFIED</h3>

                    <p>
                        This document has been verified
                        against Coderror records.
                    </p>

                </div>


                <div class="details">

                    <!-- DOCUMENT TYPE -->

                    <div class="detail">

                        <span class="detail-label">
                            Document Type
                        </span>

                        <span class="detail-value">

                            <?php
                            echo $document_type === 'offer'
                                ? 'Offer Letter'
                                : 'Certificate';
                            ?>

                        </span>

                    </div>


                    <!-- DOCUMENT NUMBER -->

                    <div class="detail">

                        <span class="detail-label">
                            Document Number
                        </span>

                        <span class="detail-value">
                            <?php echo e($document_no); ?>
                        </span>

                    </div>


                    <!-- STUDENT NAME -->

                    <?php if ($student_name !== ''): ?>

                        <div class="detail full">

                            <span class="detail-label">
                                Student Name
                            </span>

                            <span class="detail-value">
                                <?php echo e($student_name); ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <!-- STUDENT ID -->

                    <?php if ($student_id !== ''): ?>

                        <div class="detail">

                            <span class="detail-label">
                                Student ID
                            </span>

                            <span class="detail-value">
                                <?php echo e($student_id); ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <!-- INTERNSHIP BRANCH -->

                    <?php if ($branch !== ''): ?>

                        <div class="detail">

                            <span class="detail-label">
                                Internship Branch
                            </span>

                            <span class="detail-value">
                                <?php echo e($branch); ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <!-- START DATE -->

                    <?php if ($start_date !== ''): ?>

                        <div class="detail">

                            <span class="detail-label">
                                Internship Start
                            </span>

                            <span class="detail-value">
                                <?php
                                echo formatVerificationDate(
                                    $start_date
                                );
                                ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <!-- END DATE -->

                    <?php if ($end_date !== ''): ?>

                        <div class="detail">

                            <span class="detail-label">
                                Internship End
                            </span>

                            <span class="detail-value">
                                <?php
                                echo formatVerificationDate(
                                    $end_date
                                );
                                ?>
                            </span>

                        </div>

                    <?php endif; ?>


                    <!-- COMPANY -->

                    <div class="detail">

                        <span class="detail-label">
                            Company
                        </span>

                        <span class="detail-value">
                            Coderror
                        </span>

                    </div>


                    <!-- STATUS -->

                    <div class="detail">

                        <span class="detail-label">
                            Verification Status
                        </span>

                        <span class="detail-value">

                            <span class="verified-status">

                                <i class="fas fa-check-circle"></i>

                                VERIFIED

                            </span>

                        </span>

                    </div>

                </div>

            </div>

        <?php endif; ?>


        <!-- =================================================
             FOOTER
        ================================================== -->

        <div class="footer">

            <strong>Coderror</strong>

            <br>

            Official Internship Document Verification Portal

            <br>

            This verification page confirms records
            maintained by Coderror.

            <br>

            <a
                href="index.php"
                class="back-btn"
            >

                <i class="fas fa-home"></i>

                Back to Home

            </a>

        </div>

    </div>

</div>

<script>

    /*
     * Prevent accidental double submission.
     */

    document
        .querySelector('form')
        .addEventListener('submit', function () {

            const button =
                this.querySelector('button[type="submit"]');

            if (button) {

                button.innerHTML =
                    '<i class="fas fa-spinner fa-spin"></i> Verifying...';

                button.disabled = true;
            }

        });

</script>

</body>

</html>

