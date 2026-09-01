<?php

session_start();

require_once __DIR__ . '/config/db.php';

/* =========================================================
   HELPER
========================================================= */

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

/* =========================================================
   LOGIN SECURITY
========================================================= */

if (
    !isset($_SESSION['logged_in']) ||
    $_SESSION['logged_in'] !== true ||
    empty($_SESSION['user_id'])
) {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

/* =========================================================
   USER
========================================================= */

$stmt = $pdo->prepare("
    SELECT
        id,
        fullname,
        login_id,
        created_at,
        role,
        status
    FROM users
    WHERE id = ?
    LIMIT 1
");

$stmt->execute([
    $_SESSION['user_id']
]);

$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    session_unset();
    session_destroy();

    header("Location: login.php");
    exit();
}

/* =========================================================
   LOGIN COUNT
========================================================= */

$login_count_stmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM login_history
    WHERE user_id = ?
    AND status = 'success'
");

$login_count_stmt->execute([
    $_SESSION['user_id']
]);

$login_count = (int)$login_count_stmt->fetchColumn();

/* =========================================================
   STUDENT APPLICATION
========================================================= */

$application = null;

try {

    $application_stmt = $pdo->prepare("
        SELECT *
        FROM internship_students
        WHERE email = ?
        ORDER BY id DESC
        LIMIT 1
    ");

    $application_stmt->execute([
        $user['login_id']
    ]);

    $application = $application_stmt->fetch(PDO::FETCH_ASSOC);

} catch (PDOException $e) {

    error_log(
        "Dashboard application error: " .
        $e->getMessage()
    );

    $application = null;
}

/* =========================================================
   APPLICATION STATUS
========================================================= */

$is_approved = (
    $application &&
    isset($application['application_status']) &&
    strtolower(trim($application['application_status'])) === 'approved'
);

/* =========================================================
   OFFER LETTER APPROVAL
   COMPLETELY SEPARATE
========================================================= */

$offer_approved = (
    $application &&
    isset($application['offer_letter_status']) &&
    strtolower(trim($application['offer_letter_status'])) === 'approved'
);

/* =========================================================
   OFFER LETTER AVAILABLE
========================================================= */

$offer_available = (
    $offer_approved &&
    !empty($application['offer_letter_no'])
);

/* =========================================================
   CERTIFICATE APPROVAL
   COMPLETELY SEPARATE
========================================================= */

$certificate_approved = (
    $application &&
    isset($application['certificate_status']) &&
    strtolower(trim($application['certificate_status'])) === 'approved'
);

/* =========================================================
   CERTIFICATE PENDING
========================================================= */

$certificate_pending = (
    $application &&
    !$certificate_approved
);

?>

<!DOCTYPE html>
<html lang="en">

<head>

<meta charset="UTF-8">

<meta
    name="viewport"
    content="width=device-width, initial-scale=1.0"
>

<title>Coderror - Student Dashboard</title>

<link
    href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap"
    rel="stylesheet"
>

<link
    rel="stylesheet"
    href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css"
>

<style>

/* =========================================================
   RESET
========================================================= */

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

/* =========================================================
   ROOT
========================================================= */

:root {
    --gold-primary: #D4AF37;
    --gold-light: #F5D76E;
    --gold-dark: #B8860B;

    --blue: #2563eb;
    --blue-dark: #1746a2;

    --green: #35c759;
    --green-dark: #16833a;

    --dark: #050505;
    --card: #151515;
}

/* =========================================================
   BODY
========================================================= */

body {

    min-height: 100vh;

    padding: 30px 15px;

    font-family: 'Poppins', sans-serif;

    color: var(--gold-primary);

    background:
        radial-gradient(
            ellipse at top,
            #1b1b1b 0%,
            #0b0b0b 50%,
            #000 100%
        );
}

/* =========================================================
   MAIN CARD
========================================================= */

.welcome-card {

    width: 100%;

    max-width: 680px;

    margin: 0 auto;

    padding: 45px 40px;

    position: relative;

    border-radius: 20px;

    border: 1px solid rgba(212,175,55,.30);

    background: rgba(20,20,20,.95);

    box-shadow:
        0 0 45px rgba(212,175,55,.12);

    backdrop-filter: blur(20px);
}

/* =========================================================
   CORNER DESIGN
========================================================= */

.welcome-card::before,
.welcome-card::after {

    content: '';

    position: absolute;

    width: 30px;

    height: 30px;

    border: 2px solid var(--gold-primary);
}

.welcome-card::before {

    top: 10px;
    left: 10px;

    border-right: none;
    border-bottom: none;
}

.welcome-card::after {

    right: 10px;
    bottom: 10px;

    border-left: none;
    border-top: none;
}

/* =========================================================
   LOGO
========================================================= */

.logo-small {

    display: block;

    width: 68px;

    height: 68px;

    margin: 0 auto 18px;

    filter:
        drop-shadow(
            0 0 10px
            rgba(212,175,55,.5)
        );
}

/* =========================================================
   HEADING
========================================================= */

h1 {

    font-family: 'Cinzel', serif;

    font-size: 31px;

    letter-spacing: 3px;

    text-align: center;

    background:
        linear-gradient(
            135deg,
            #F5D76E,
            #D4AF37,
            #B8860B
        );

    -webkit-background-clip: text;

    -webkit-text-fill-color: transparent;
}

.subtitle {

    margin-top: 7px;

    margin-bottom: 28px;

    text-align: center;

    color: rgba(212,175,55,.60);

    font-size: 12px;

    letter-spacing: 3px;

    text-transform: uppercase;
}

/* =========================================================
   USER INFO
========================================================= */

.user-info {

    padding: 20px;

    margin: 20px 0;

    border-radius: 10px;

    border: 1px solid rgba(212,175,55,.20);

    background: rgba(0,0,0,.50);
}

.user-info p {

    display: flex;

    align-items: center;

    gap: 9px;

    margin-bottom: 12px;

    color: rgba(212,175,55,.70);

    font-size: 13px;
}

.user-info p:last-child {

    margin-bottom: 0;
}

.user-info i {

    width: 20px;

    color: var(--gold-primary);
}

.user-info strong {

    margin-left: auto;

    max-width: 65%;

    color: var(--gold-light);

    text-align: right;

    word-break: break-word;
}

/* =========================================================
   APPLICATION BOX
========================================================= */

.application-box {

    margin-top: 22px;

    padding: 24px;

    border-radius: 14px;

    border: 1px solid rgba(212,175,55,.35);

    background:
        linear-gradient(
            135deg,
            rgba(212,175,55,.12),
            rgba(0,0,0,.45)
        );
}

.application-box h2 {

    margin-bottom: 10px;

    color: var(--gold-light);

    font-size: 19px;
}

.application-box p {

    margin-bottom: 18px;

    color: rgba(255,255,255,.65);

    font-size: 13px;

    line-height: 1.6;
}

/* =========================================================
   STATUS
========================================================= */

.status {

    display: inline-flex;

    align-items: center;

    gap: 7px;

    padding: 6px 14px;

    margin-bottom: 14px;

    border-radius: 20px;

    font-size: 11px;

    font-weight: 600;

    letter-spacing: 1px;
}

.status-approved {

    color: #65e27f;

    background: rgba(40,167,69,.15);

    border: 1px solid rgba(40,167,69,.40);
}

.status-pending {

    color: #ffd35a;

    background: rgba(255,193,7,.12);

    border: 1px solid rgba(255,193,7,.35);
}

.status-certificate {

    color: #ffcf54;

    background: rgba(212,175,55,.10);

    border: 1px solid rgba(212,175,55,.30);
}

/* =========================================================
   DOCUMENT STATUS CARDS
========================================================= */

.approval-status-row {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 10px;

    margin: 18px 0;
}

.approval-status {

    padding: 12px;

    border-radius: 10px;

    background: rgba(0,0,0,.35);

    border: 1px solid rgba(255,255,255,.08);

    text-align: center;

    font-size: 10px;

    font-weight: 600;

    letter-spacing: .6px;

    text-transform: uppercase;
}

.approval-status i {

    display: block;

    margin-bottom: 6px;

    font-size: 18px;
}

.approval-approved {

    color: #65e27f;

    border-color: rgba(40,167,69,.35);

    background: rgba(40,167,69,.08);
}

.approval-pending {

    color: #ffd35a;

    border-color: rgba(255,193,7,.25);

    background: rgba(255,193,7,.06);
}

/* =========================================================
   DOCUMENT BUTTONS
========================================================= */

.document-buttons {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 12px;

    margin-top: 15px;
}

.document-btn {

    min-height: 62px;

    display: flex;

    align-items: center;

    justify-content: center;

    gap: 9px;

    padding: 12px 14px;

    border-radius: 10px;

    text-decoration: none;

    font-size: 11px;

    font-weight: 700;

    letter-spacing: .5px;

    text-align: center;

    text-transform: uppercase;

    transition: .25s ease;
}

.document-btn i {

    font-size: 17px;
}

/* =========================================================
   OFFER LETTER BUTTON
========================================================= */

.offer-btn {

    color: #fff;

    background:
        linear-gradient(
            135deg,
            var(--blue),
            var(--blue-dark)
        );

    border: 1px solid rgba(77,163,255,.45);

    cursor: pointer;
}

.offer-btn:hover {

    transform: translateY(-3px);

    box-shadow:
        0 10px 25px
        rgba(37,99,235,.30);
}

/* =========================================================
   CERTIFICATE BUTTON
========================================================= */

.certificate-btn {

    color: #111;

    background:
        linear-gradient(
            135deg,
            #F5D76E,
            #D4AF37,
            #B8860B
        );

    border: 1px solid rgba(212,175,55,.60);
}

.certificate-btn:hover {

    transform: translateY(-3px);

    box-shadow:
        0 10px 25px
        rgba(212,175,55,.30);
}

/* =========================================================
   DISABLED
========================================================= */

.document-disabled {

    color: #777;

    background: rgba(255,255,255,.04);

    border: 1px solid rgba(255,255,255,.08);

    opacity: .55;

    cursor: not-allowed;
}

/* =========================================================
   DOCUMENT META
========================================================= */

.document-meta {

    display: grid;

    grid-template-columns: 1fr 1fr;

    gap: 10px;

    margin-top: 15px;
}

.meta-item {

    padding: 10px;

    text-align: left;

    border-radius: 8px;

    background: rgba(0,0,0,.30);

    border: 1px solid rgba(212,175,55,.12);
}

.meta-label {

    display: block;

    margin-bottom: 3px;

    color: rgba(255,255,255,.40);

    font-size: 8px;

    letter-spacing: 1px;

    text-transform: uppercase;
}

.meta-value {

    color: rgba(255,255,255,.82);

    font-size: 11px;

    font-weight: 600;
}

/* =========================================================
   GENERAL BUTTONS
========================================================= */

.btn-group {

    display: flex;

    justify-content: center;

    gap: 10px;

    margin-top: 25px;

    flex-wrap: wrap;
}

.btn {

    display: inline-flex;

    align-items: center;

    justify-content: center;

    gap: 7px;

    padding: 12px 20px;

    color: #000;

    text-decoration: none;

    border-radius: 9px;

    font-size: 11px;

    font-weight: 600;

    letter-spacing: 1px;

    text-transform: uppercase;

    background:
        linear-gradient(
            135deg,
            #D4AF37,
            #B8860B
        );

    transition: .25s ease;
}

.btn:hover {

    transform: translateY(-2px);

    box-shadow:
        0 10px 25px
        rgba(212,175,55,.25);
}

.btn-secondary {

    color: var(--gold-primary);

    background: transparent;

    border: 1px solid var(--gold-primary);
}

.btn-secondary:hover {

    color: #000;

    background: var(--gold-primary);
}

/* =========================================================
   NO APPLICATION
========================================================= */

.no-application {

    margin-top: 20px;

    padding: 18px;

    border-radius: 10px;

    color: rgba(255,255,255,.60);

    background: rgba(255,255,255,.04);

    border: 1px solid rgba(255,255,255,.08);

    font-size: 13px;

    text-align: center;
}

/* =========================================================
   MOBILE
========================================================= */

@media(max-width:600px) {

    body {

        padding: 15px;
    }

    .welcome-card {

        padding: 35px 20px;
    }

    h1 {

        font-size: 25px;
    }

    .document-buttons {

        grid-template-columns: 1fr;
    }

    .approval-status-row {

        grid-template-columns: 1fr;
    }

    .document-meta {

        grid-template-columns: 1fr;
    }

    .btn {

        width: 100%;
    }
}

</style>

</head>

<body>

<div class="welcome-card">

<!-- =====================================================
     LOGO
===================================================== -->

<svg
    class="logo-small"
    viewBox="0 0 100 100"
    xmlns="http://www.w3.org/2000/svg"
>

<defs>

<linearGradient
    id="goldGrad"
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
    stroke="url(#goldGrad)"
    stroke-width="2.5"
/>

<text
    x="50"
    y="62"
    text-anchor="middle"
    font-family="Courier New,monospace"
    font-size="32"
    font-weight="bold"
    fill="url(#goldGrad)"
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

<!-- =====================================================
     TITLE
===================================================== -->

<h1>
Welcome Aboard
</h1>

<p class="subtitle">
Coderror • Student Dashboard
</p>

<!-- =====================================================
     USER INFORMATION
===================================================== -->

<div class="user-info">

<p>
<i class="fas fa-user"></i>

Name:

<strong>
<?php echo e($user['fullname']); ?>
</strong>

</p>

<p>
<i class="fas fa-envelope"></i>

Login:

<strong>
<?php echo e($user['login_id']); ?>
</strong>

</p>

<p>
<i class="fas fa-id-badge"></i>

User ID:

<strong>
#<?php echo e($_SESSION['user_id']); ?>
</strong>

</p>

<p>
<i class="fas fa-calendar"></i>

Member since:

<strong>

<?php

echo !empty($user['created_at'])
    ? date(
        'M Y',
        strtotime($user['created_at'])
    )
    : '-';

?>

</strong>

</p>

<p>
<i class="fas fa-history"></i>

Total logins:

<strong>
<?php echo $login_count; ?>
</strong>

</p>

<p>
<i class="fas fa-user-shield"></i>

Role:

<strong>
<?php echo ucfirst(e($user['role'])); ?>
</strong>

</p>

</div>

<!-- =====================================================
     APPLICATION
===================================================== -->

<?php if ($application): ?>

<div class="application-box">

<!-- =====================================================
     APPLICATION APPROVED
===================================================== -->

<?php if ($is_approved): ?>

<span class="status status-approved">

<i class="fas fa-check-circle"></i>

APPLICATION APPROVED

</span>

<h2>

<i class="fas fa-award"></i>

Internship Documents

</h2>

<p>

Your internship application has been approved.
Offer Letter and Certificate are controlled
separately by the administrator.

</p>

<!-- =====================================================
     SEPARATE APPROVAL STATUS
===================================================== -->

<div class="approval-status-row">

<!-- OFFER LETTER STATUS -->

<?php if ($offer_approved): ?>

<div class="approval-status approval-approved">

<i class="fas fa-file-contract"></i>

Offer Letter Approved

</div>

<?php else: ?>

<div class="approval-status approval-pending">

<i class="fas fa-clock"></i>

Offer Letter Pending

</div>

<?php endif; ?>


<!-- CERTIFICATE STATUS -->

<?php if ($certificate_approved): ?>

<div class="approval-status approval-approved">

<i class="fas fa-certificate"></i>

Certificate Approved

</div>

<?php else: ?>

<div class="approval-status approval-pending">

<i class="fas fa-clock"></i>

Certificate Pending

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     DOCUMENT BUTTONS
===================================================== -->

<div class="document-buttons">

<!-- =====================================================
     OFFER LETTER
===================================================== -->

<?php if ($offer_available): ?>

<a
    href="student-offer-letter.php?id=<?php echo (int)$application['id']; ?>"
    class="document-btn offer-btn"
    target="_blank"
    rel="noopener noreferrer"
>

<i class="fas fa-file-contract"></i>

<span>
View Offer Letter
</span>

</a>

<?php else: ?>

<span class="document-btn document-disabled">

<i class="fas fa-lock"></i>

<span>

<?php

if ($offer_approved && empty($application['offer_letter_no'])) {

    echo "Offer Letter Number Missing";

} else {

    echo "Offer Letter Pending Approval";

}

?>

</span>

</span>

<?php endif; ?>


<!-- =====================================================
     CERTIFICATE
===================================================== -->

<?php if ($certificate_approved): ?>

<a
    href="certificate.php?id=<?php echo (int)$application['id']; ?>"
    class="document-btn certificate-btn"
    target="_blank"
    rel="noopener noreferrer"
>

<i class="fas fa-certificate"></i>

<span>
View Certificate
</span>

</a>

<?php else: ?>

<span class="document-btn document-disabled">

<i class="fas fa-lock"></i>

<span>
Certificate Pending Approval
</span>

</span>

<?php endif; ?>

</div>


<!-- =====================================================
     CERTIFICATE PENDING MESSAGE
===================================================== -->

<?php if (!$certificate_approved): ?>

<div
    class="status status-certificate"
    style="margin-top:15px;margin-bottom:0;"
>

<i class="fas fa-clock"></i>

CERTIFICATE PENDING ADMIN APPROVAL

</div>

<?php endif; ?>


<!-- =====================================================
     OFFER LETTER PENDING MESSAGE
===================================================== -->

<?php if (!$offer_approved): ?>

<div
    class="status status-certificate"
    style="margin-top:10px;margin-bottom:0;"
>

<i class="fas fa-clock"></i>

OFFER LETTER PENDING ADMIN APPROVAL

</div>

<?php endif; ?>


<!-- =====================================================
     DOCUMENT INFORMATION
===================================================== -->

<div class="document-meta">

<?php if (!empty($application['student_id'])): ?>

<div class="meta-item">

<span class="meta-label">
Student ID
</span>

<span class="meta-value">

<?php echo e($application['student_id']); ?>

</span>

</div>

<?php endif; ?>


<?php if (!empty($application['offer_letter_no'])): ?>

<div class="meta-item">

<span class="meta-label">
Offer Letter No.
</span>

<span class="meta-value">

<?php echo e($application['offer_letter_no']); ?>

</span>

</div>

<?php endif; ?>

</div>


<!-- =====================================================
     APPLICATION NOT APPROVED
===================================================== -->

<?php else: ?>

<span class="status status-pending">

<i class="fas fa-clock"></i>

<?php

echo e(
    $application['application_status'] ?? 'Pending'
);

?>

</span>

<h2>

<i class="fas fa-file-contract"></i>

Internship Application

</h2>

<p>

Your internship application is currently being
processed. Offer Letter and Certificate will
be available according to their separate
approval status after application approval.

</p>

<div class="document-buttons">

<span class="document-btn document-disabled">

<i class="fas fa-lock"></i>

Offer Letter

</span>

<span class="document-btn document-disabled">

<i class="fas fa-lock"></i>

Certificate

</span>

</div>

<?php endif; ?>

</div>

<?php else: ?>

<!-- =====================================================
     NO APPLICATION
===================================================== -->

<div class="no-application">

<i class="fas fa-info-circle"></i>

No internship application was found
for your account.

</div>

<?php endif; ?>


<!-- =====================================================
     GENERAL BUTTONS
===================================================== -->

<div class="btn-group">

<a
    href="index.php"
    class="btn"
>

<i class="fas fa-home"></i>

Home

</a>
<a
    href="https://docs.google.com/forms/d/e/1FAIpQLSflNTULvrrR_cmD_2ityAueU3zI1AWSG3pIRyGc5rB69CBopQ/viewform?usp=header"
    class="btn"
>
    <i class="fas fa-upload"></i>
    Submit Task
</a>

<?php if ($user['role'] === 'admin'): ?>

<a
    href="admin/index.php"
    class="btn"
>

<i class="fas fa-user-shield"></i>

Admin Panel

</a>

<?php endif; ?>


<a
    href="logout.php"
    class="btn btn-secondary"
>

<i class="fas fa-sign-out-alt"></i>

Logout

</a>

</div>

</div>

</body>

</html>