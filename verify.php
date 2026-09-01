<?php
session_start();
require_once __DIR__ . '/config/db.php';
// PHPMailer must be installed/uploaded so this file exists.
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit();
}

$email = $_SESSION['verification_email'] ?? '';
$user_id = (int)($_SESSION['verification_user_id'] ?? 0);

if ($email === '' || $user_id <= 0) {
    header("Location: register.php");
    exit();
}

$error_msg = "";
$success_msg = "";

/*
|--------------------------------------------------------------------------
| GMAIL SMTP CONFIGURATION
|--------------------------------------------------------------------------
| 1. Replace YOUR_GMAIL@gmail.com with the Gmail address that will SEND OTPs.
| 2. Replace YOUR_16_DIGIT_APP_PASSWORD with the Google App Password.
|    Do NOT use your normal Gmail password.
| 3. Keep port 587 + STARTTLS. If your host blocks 587, use 465 + SMTPS.
*/
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'coderrorinternship@gmail.com');
define('SMTP_PASSWORD', 'mhsmlxwkxzyjokkc');
define('SMTP_FROM_EMAIL', 'coderrorinternship@gmail.com');
define('SMTP_FROM_NAME', 'Coderror');

function sendVerificationOTP(string $email, string $otp): bool
{
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = Coderror;
        $mail->Password   = mhsmlxwkxzyjokkc;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Your new Coderror verification code';

        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:25px;color:#222'>
                <h2>Coderror Email Verification</h2>
                <p>Your new verification code is:</p>
                <div style='font-size:34px;font-weight:700;letter-spacing:8px;padding:18px 0'>
                    {$safeOtp}
                </div>
                <p>This code will expire in <strong>10 minutes</strong>.</p>
            </div>
        ";

        $mail->AltBody = "Your Coderror verification OTP is {$otp}. It expires in 10 minutes.";

        return $mail->send();
    } catch (Exception $e) {
        error_log("Resend OTP error: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token. Please refresh and try again.";
    } else {
        $otp = trim($_POST['otp'] ?? '');

        if (!preg_match('/^[0-9]{6}$/', $otp)) {
            $error_msg = "Please enter a valid 6-digit OTP.";
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT id, fullname, login_id, role, status,
                           email_verified, verification_otp_hash, otp_expires_at
                    FROM users
                    WHERE id = ? AND login_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$user_id, $email]);
                $user = $stmt->fetch();

                if (!$user) {
                    $error_msg = "Verification session is invalid. Please register again.";
                } elseif ((int)$user['email_verified'] === 1) {
                    unset($_SESSION['verification_user_id'], $_SESSION['verification_email']);
                    header("Location: login.php?msg=" . urlencode("Email already verified. Please login.") . "&status=success");
                    exit();
                } elseif (
                    empty($user['otp_expires_at']) ||
                    strtotime($user['otp_expires_at']) < time()
                ) {
                    $error_msg = "OTP has expired. Please use Resend OTP.";
                } elseif (
                    empty($user['verification_otp_hash']) ||
                    !password_verify($otp, $user['verification_otp_hash'])
                ) {
                    $error_msg = "Incorrect OTP. Please try again.";
                } else {
                    // Mark email as verified
                    $stmt = $pdo->prepare("
                        UPDATE users
                        SET email_verified = 1,
                            verification_otp_hash = NULL,
                            otp_expires_at = NULL
                        WHERE id = ?
                    ");
                    $stmt->execute([$user['id']]);

                    // Clear verification session
                    unset($_SESSION['verification_user_id'], $_SESSION['verification_email']);

                    // Automatically login after successful verification
                    session_regenerate_id(true);
                    $_SESSION['logged_in'] = true;
                    $_SESSION['user_id']   = $user['id'];
                    $_SESSION['name']      = $user['fullname'];
                    $_SESSION['fullname']  = $user['fullname'];
                    $_SESSION['email']     = $user['login_id'];
                    $_SESSION['login_id']  = $user['login_id'];
                    $_SESSION['role']      = $user['role'];

                    header("Location: index.php?msg=" . urlencode("Email verified successfully. Welcome to Coderror, " . $user['fullname'] . "!") . "&status=success");
                    exit();
                }
            } catch (PDOException $e) {
                error_log("Verify error: " . $e->getMessage());
                $error_msg = "Verification failed. Please try again.";
            }
        }
    }
}

if (isset($_GET['resend']) && $_GET['resend'] === '1') {
    try {
        $stmt = $pdo->prepare("
            SELECT id, email_verified
            FROM users
            WHERE id = ? AND login_id = ?
            LIMIT 1
        ");
        $stmt->execute([$user_id, $email]);
        $user = $stmt->fetch();

        if (!$user) {
            $error_msg = "User not found.";
        } elseif ((int)$user['email_verified'] === 1) {
            header("Location: login.php?msg=" . urlencode("Email already verified. Please login.") . "&status=success");
            exit();
        } else {
            $otp = (string) random_int(100000, 999999);
            $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
            $otp_expires = date('Y-m-d H:i:s', time() + 600);

            $stmt = $pdo->prepare("
                UPDATE users
                SET verification_otp_hash = ?, otp_expires_at = ?
                WHERE id = ?
            ");
            $stmt->execute([$otp_hash, $otp_expires, $user_id]);

            if (sendVerificationOTP($email, $otp)) {
                $success_msg = "A new OTP has been sent to your email.";
            } else {
                $error_msg = "Could not send OTP. Please check your Gmail App Password and SMTP settings.";
            }
        }
    } catch (PDOException $e) {
        error_log("Resend OTP DB error: " . $e->getMessage());
        $error_msg = "Could not resend OTP. Please try again.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Verify Email — Coderror</title>
<link rel="stylesheet" href="style.css">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@500;700&family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<style>
.auth-wrap { min-height:100vh; display:flex; align-items:center; justify-content:center;
             padding:100px 20px 50px; position:relative; z-index:1; }
.auth-box { background:rgba(20,20,20,0.9); backdrop-filter:blur(20px);
            border:1px solid rgba(212,175,55,0.3); border-radius:20px;
            padding:45px 40px; width:100%; max-width:430px;
            box-shadow:0 0 40px rgba(212,175,55,0.15); position:relative; }
.auth-box::before,.auth-box::after { content:''; position:absolute; width:22px; height:22px; border:2px solid #D4AF37; }
.auth-box::before { top:10px; left:10px; border-right:none; border-bottom:none; }
.auth-box::after  { bottom:10px; right:10px; border-left:none; border-top:none; }
.auth-head { text-align:center; margin-bottom:30px; }
.auth-head svg { width:65px; height:65px; filter:drop-shadow(0 0 12px rgba(212,175,55,0.5)); margin-bottom:12px; }
.auth-head h1 { font-family:'Cinzel',serif; font-size:26px;
                background:linear-gradient(135deg,#F5D76E,#D4AF37,#B8860B);
                -webkit-background-clip:text; -webkit-text-fill-color:transparent; letter-spacing:3px; }
.auth-head p { color:rgba(212,175,55,0.6); font-size:11px; letter-spacing:3px; text-transform:uppercase; margin-top:5px; }
.tab-row { display:flex; margin-bottom:28px; border-bottom:1px solid rgba(212,175,55,0.3); }
.tab-row a { flex:1; text-align:center; padding:11px 0; color:rgba(212,175,55,0.5);
             text-decoration:none; font-size:13px; font-weight:500; letter-spacing:2px;
             text-transform:uppercase; transition:all 0.3s; }
.tab-row a.active { color:#D4AF37; border-bottom:2px solid #D4AF37; }
.inp-grp { position:relative; margin-bottom:18px; }
.inp-grp i { position:absolute; left:15px; top:50%; transform:translateY(-50%); color:#D4AF37; font-size:14px; }
.inp-grp input { width:100%; padding:13px 45px; background:rgba(0,0,0,0.5);
                 border:1px solid rgba(212,175,55,0.3); border-radius:10px;
                 color:#F5D76E; font-size:22px; letter-spacing:8px; text-align:center;
                 outline:none; transition:all 0.3s; box-sizing:border-box; }
.inp-grp input::placeholder { color:rgba(212,175,55,0.4); letter-spacing:3px; font-size:14px; }
.inp-grp input:focus { border-color:#D4AF37; box-shadow:0 0 12px rgba(212,175,55,0.2); }
.alert { padding:10px; border-radius:8px; margin-bottom:15px; font-size:13px; text-align:center; }
.alert.ok { background:rgba(76,175,80,0.15); border:1px solid rgba(76,175,80,0.4); color:#81C784; }
.alert.err { background:rgba(244,67,54,0.15); border:1px solid rgba(244,67,54,0.4); color:#E57373; }
.email-show { color:#F5D76E; text-align:center; font-size:14px; margin-bottom:22px; word-break:break-all; }
.btn-verify { width:100%; padding:15px; background:linear-gradient(135deg,#D4AF37,#B8860B);
              color:#000; border:none; border-radius:10px; font-size:15px; font-weight:700;
              letter-spacing:3px; text-transform:uppercase; cursor:pointer;
              transition:all 0.3s; display:flex; align-items:center; justify-content:center; gap:10px; }
.btn-verify:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(212,175,55,0.5); }
.resend { text-align:center; margin-top:20px; color:rgba(212,175,55,0.6); font-size:13px; }
.resend a { color:#D4AF37; text-decoration:none; font-weight:500; }
</style>
</head>
<body>
<div class="bg-symbols" id="bgSymbols"></div>

<nav class="navbar">
  <a href="login.php" class="nav-logo">
    <svg viewBox="0 0 100 100">
      <defs><linearGradient id="g1" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" style="stop-color:#F5D76E"/>
        <stop offset="50%" style="stop-color:#D4AF37"/>
        <stop offset="100%" style="stop-color:#B8860B"/></linearGradient></defs>
      <polygon points="50,5 90,27.5 90,72.5 50,95 10,72.5 10,27.5" fill="none" stroke="url(#g1)" stroke-width="3"/>
      <text x="50" y="62" text-anchor="middle" font-family="Courier New" font-size="32" font-weight="bold" fill="url(#g1)">&lt;/&gt;</text>
      <circle cx="75" cy="25" r="5" fill="#FF4444" stroke="#000" stroke-width="1.5"/>
    </svg>
    <span class="nav-logo-text">Coderror</span>
  </a>
  <ul class="nav-links" id="navLinks"></ul>
  <button class="hamburger" id="hamburger"><i class="fas fa-bars"></i></button>
</nav>

<div class="auth-wrap">
  <div class="auth-box">
    <div class="auth-head">
      <svg viewBox="0 0 100 100">
        <defs><linearGradient id="g2" x1="0%" y1="0%" x2="100%" y2="100%">
          <stop offset="0%" style="stop-color:#F5D76E"/>
          <stop offset="50%" style="stop-color:#D4AF37"/>
          <stop offset="100%" style="stop-color:#B8860B"/></linearGradient></defs>
        <polygon points="50,5 90,27.5 90,72.5 50,95 10,72.5 10,27.5" fill="none" stroke="url(#g2)" stroke-width="2.5"/>
        <text x="50" y="62" text-anchor="middle" font-family="Courier New" font-size="32" font-weight="bold" fill="url(#g2)">&lt;/&gt;</text>
        <circle cx="75" cy="25" r="5" fill="#FF4444" stroke="#000" stroke-width="1.5"/>
      </svg>
      <h1>Verify Email</h1>
      <p>Check your inbox</p>
    </div>

    <div class="tab-row">
      <a href="login.php">Login</a>
      <a href="register.php" class="active">Register</a>
    </div>

    <?php if ($error_msg): ?>
      <div class="alert err"><?php echo e($error_msg); ?></div>
    <?php endif; ?>

    <?php if ($success_msg): ?>
      <div class="alert ok"><?php echo e($success_msg); ?></div>
    <?php endif; ?>

    <div class="email-show">
      OTP sent to<br>
      <strong><?php echo e($email); ?></strong>
    </div>

    <form method="POST" action="verify.php" id="verifyForm">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

      <div class="inp-grp">
        <i class="fas fa-key"></i>
        <input
          type="text"
          name="otp"
          placeholder="6-DIGIT OTP"
          maxlength="6"
          pattern="[0-9]{6}"
          inputmode="numeric"
          autocomplete="one-time-code"
          required
          autofocus
        >
      </div>

      <button type="submit" class="btn-verify" id="verifyBtn">
        <i class="fas fa-check-circle"></i>
        <span>VERIFY EMAIL</span>
      </button>
    </form>

    <div class="resend">
      Didn't receive the code?
      <a href="verify.php?resend=1">Resend OTP</a>
    </div>

    <p class="resend">
      <a href="register.php">Use another email</a>
    </p>
  </div>
</div>

<script src="script.js"></script>
<script>
document.getElementById('verifyForm').addEventListener('submit', function() {
    const btn = document.getElementById('verifyBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>VERIFYING...</span>';
    btn.style.opacity = '0.7';
    btn.style.pointerEvents = 'none';
});
</script>
</body>
</html>