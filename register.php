<?php
session_start();
require_once __DIR__ . '/config/db.php';

// PHPMailer
// PHPMailer must be installed/uploaded so this file exists.
require_once __DIR__ . '/vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Already logged in? Redirect home
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    header("Location: index.php");
    exit();
}

$error_msg   = "";
$success_msg = "";

/*
|--------------------------------------------------------------------------
| SMTP CONFIG
|--------------------------------------------------------------------------
| Change these values to your real SMTP details.
| For Gmail use an App Password, NOT your normal Gmail password.
*/
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
        $mail->Username   = SMTP_USERNAME;
        $mail->Password   = SMTP_PASSWORD;
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = SMTP_PORT;
        $mail->CharSet    = 'UTF-8';

        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($email);

        $mail->isHTML(true);
        $mail->Subject = 'Verify your Coderror account';

        $safeOtp = htmlspecialchars($otp, ENT_QUOTES, 'UTF-8');

        $mail->Body = "
            <div style='font-family:Arial,sans-serif;max-width:560px;margin:auto;padding:25px;color:#222'>
                <h2 style='margin-bottom:10px'>Verify your Coderror account</h2>
                <p>Use the following 6-digit code to verify your email address:</p>
                <div style='font-size:34px;font-weight:700;letter-spacing:8px;padding:18px 0'>
                    {$safeOtp}
                </div>
                <p>This OTP will expire in <strong>10 minutes</strong>.</p>
                <p>If you did not create this account, you can safely ignore this email.</p>
            </div>
        ";

        $mail->AltBody = "Your Coderror verification OTP is {$otp}. It expires in 10 minutes.";

        return $mail->send();
    } catch (Exception $e) {
        error_log("OTP email error: " . $mail->ErrorInfo);
        return false;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF check
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token. Please refresh and try again.";
    } else {
        $fullname  = trim($_POST['fullname']  ?? '');
        $login_id  = trim($_POST['login_id']  ?? '');
        $phone     = trim($_POST['phone']     ?? '');
        $password  = $_POST['password']       ?? '';
        $referral  = trim($_POST['referral']  ?? '');

        // Email verification requires an email address in login_id.
        if (empty($fullname) || empty($login_id) || empty($password)) {
            $error_msg = "All required fields must be filled!";
        } elseif (strlen($fullname) < 2) {
            $error_msg = "Please enter your full name.";
        } elseif (strlen($password) < 6) {
            $error_msg = "Password must be at least 6 characters long!";
        } elseif (!filter_var($login_id, FILTER_VALIDATE_EMAIL)) {
            $error_msg = "Please enter a valid Email ID.";
        } else {
            try {
                // Check existing account
                $stmt = $pdo->prepare("
                    SELECT id, email_verified
                    FROM users
                    WHERE login_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$login_id]);
                $existing = $stmt->fetch();

                // Already verified
                if ($existing && (int)$existing['email_verified'] === 1) {
                    $error_msg = "This Email is already registered. Please login.";
                } else {
                    // Generate 6-digit OTP
                    $otp = (string) random_int(100000, 999999);
                    $otp_hash = password_hash($otp, PASSWORD_DEFAULT);
                    $otp_expires = date('Y-m-d H:i:s', time() + 600);
                    $hashed = password_hash($password, PASSWORD_DEFAULT);

                    if ($existing) {
                        // Re-register an unverified account: update it and send a fresh OTP
                        $stmt = $pdo->prepare("
                            UPDATE users
                            SET fullname = ?,
                                password = ?,
                                phone = ?,
                                referral_code = ?,
                                email_verified = 0,
                                verification_otp_hash = ?,
                                otp_expires_at = ?
                            WHERE id = ?
                        ");

                        $stmt->execute([
                            $fullname,
                            $hashed,
                            $phone,
                            $referral,
                            $otp_hash,
                            $otp_expires,
                            $existing['id']
                        ]);

                        $user_id = (int)$existing['id'];
                    } else {
                        // New account
                        $stmt = $pdo->prepare("
                            INSERT INTO users
                            (
                                fullname,
                                login_id,
                                password,
                                phone,
                                referral_code,
                                email_verified,
                                verification_otp_hash,
                                otp_expires_at
                            )
                            VALUES (?, ?, ?, ?, ?, 0, ?, ?)
                        ");

                        $stmt->execute([
                            $fullname,
                            $login_id,
                            $hashed,
                            $phone,
                            $referral,
                            $otp_hash,
                            $otp_expires
                        ]);

                        $user_id = (int)$pdo->lastInsertId();
                    }

                    // Send OTP before allowing the user to continue
                    if (!sendVerificationOTP($login_id, $otp)) {
                        $error_msg = "Account saved, but the verification email could not be sent. Please check your Gmail App Password and SMTP settings.";
                    } else {
                        // Store only the email/user ID needed by verify.php
                        $_SESSION['verification_user_id'] = $user_id;
                        $_SESSION['verification_email'] = $login_id;

                        header("Location: verify.php");
                        exit();
                    }
                }
            } catch (PDOException $e) {
                error_log("Register error: " . $e->getMessage());
                $error_msg = "Something went wrong. Please try again.";
            }
        }
    }
}

$url_msg    = $_GET['msg']    ?? '';
$url_status = $_GET['status'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Register — Coderror</title>
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
                 color:#F5D76E; font-size:14px; outline:none; transition:all 0.3s; box-sizing:border-box; }
.inp-grp input::placeholder { color:rgba(212,175,55,0.4); }
.inp-grp input:focus { border-color:#D4AF37; box-shadow:0 0 12px rgba(212,175,55,0.2); }
.inp-grp .eye { position:absolute; right:15px; top:50%; transform:translateY(-50%);
                color:rgba(212,175,55,0.6); cursor:pointer; transition:color 0.3s; }
.inp-grp .eye:hover { color:#D4AF37; }
.opt-row { margin-bottom:22px; font-size:12px; }
.opt-row label { display:flex; align-items:center; color:rgba(212,175,55,0.7); cursor:pointer; }
.opt-row input[type=checkbox] { appearance:none; width:14px; height:14px;
             border:1px solid #D4AF37; border-radius:3px; margin-right:8px;
             cursor:pointer; position:relative; }
.opt-row input[type=checkbox]:checked { background:#D4AF37; }
.opt-row a { color:#D4AF37; text-decoration:none; }
.alert { padding:10px; border-radius:8px; margin-bottom:15px; font-size:13px; text-align:center; display:none; }
.alert.ok { display:block; background:rgba(76,175,80,0.15); border:1px solid rgba(76,175,80,0.4); color:#81C784; }
.alert.err { display:block; background:rgba(244,67,54,0.15); border:1px solid rgba(244,67,54,0.4); color:#E57373; }
.foot-link { text-align:center; margin-top:22px; color:rgba(212,175,55,0.6); font-size:13px; }
.foot-link a { color:#D4AF37; text-decoration:none; font-weight:500; }
.btn-register { width:100%; padding:15px; background:linear-gradient(135deg,#D4AF37,#B8860B);
                color:#000; border:none; border-radius:10px; font-size:15px; font-weight:700;
                letter-spacing:3px; text-transform:uppercase; cursor:pointer;
                transition:all 0.3s; display:flex; align-items:center; justify-content:center; gap:10px; }
.btn-register:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(212,175,55,0.5); }
</style>
</head>
<body>
<div class="bg-symbols" id="bgSymbols"></div>

<nav class="navbar">
  <a href="register.php" class="nav-logo">
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
      <h1>Join Coderror</h1>
      <p>Create your account</p>
    </div>

    <div class="tab-row">
      <a href="login.php">Login</a>
      <a href="register.php" class="active">Register</a>
    </div>

    <div class="alert" id="alertBox"></div>

    <form method="POST" action="register.php" id="regForm">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

      <div class="inp-grp">
        <i class="fas fa-user"></i>
        <input type="text" name="fullname" placeholder="Full Name" required value="<?php echo e($_POST['fullname'] ?? ''); ?>">
      </div>
      <div class="inp-grp">
        <i class="fas fa-envelope"></i>
        <input type="email" name="login_id" placeholder="Email ID" required value="<?php echo e($_POST['login_id'] ?? ''); ?>">
      </div>
      <div class="inp-grp">
        <i class="fas fa-phone"></i>
        <input type="tel" name="phone" placeholder="Phone (optional)" value="<?php echo e($_POST['phone'] ?? ''); ?>">
      </div>
      <div class="inp-grp">
        <i class="fas fa-lock"></i>
        <input type="password" name="password" id="pwd" placeholder="Password (min 6 chars)" required minlength="6">
        <i class="fas fa-eye eye" id="eyeBtn"></i>
      </div>
      <div class="inp-grp">
        <i class="fas fa-gift"></i>
        <input type="text" name="referral" placeholder="Referral Code (optional)" value="<?php echo e($_POST['referral'] ?? ''); ?>">
      </div>

      <button type="submit" class="btn-register" id="regBtn">
        <i class="fas fa-user-plus"></i>
        <span>CREATE ACCOUNT</span>
      </button>
    </form>

    <p class="foot-link">
      Already have an account? <a href="login.php">Sign in</a>
    </p>
  </div>
</div>

<script src="script.js"></script>
<script>
const urlParams = new URLSearchParams(window.location.search);
const msg = urlParams.get('msg');
const status = urlParams.get('status');

if (msg) {
    const box = document.getElementById('alertBox');
    box.textContent = msg;
    box.classList.add(status === 'success' ? 'ok' : 'err');
    box.style.display = 'block';
    setTimeout(() => { box.style.display = 'none'; }, 5000);
}

<?php if ($error_msg): ?>
document.getElementById('alertBox').textContent = <?php echo json_encode($error_msg); ?>;
document.getElementById('alertBox').classList.add('err');
document.getElementById('alertBox').style.display = 'block';
<?php endif; ?>

document.getElementById('regForm').addEventListener('submit', function() {
    const btn = document.getElementById('regBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>SENDING OTP...</span>';
    btn.style.opacity = '0.7';
    btn.style.pointerEvents = 'none';
});

document.getElementById('eyeBtn').addEventListener('click', function() {
    const input = document.getElementById('pwd');
    if (input.type === 'password') {
        input.type = 'text';
        this.classList.replace('fa-eye', 'fa-eye-slash');
    } else {
        input.type = 'password';
        this.classList.replace('fa-eye-slash', 'fa-eye');
    }
});
</script>
</body>
</html>