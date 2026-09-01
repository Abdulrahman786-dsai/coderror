<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Already logged in? Redirect based on role
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {

    if (($_SESSION['role'] ?? '') === 'admin') {
        header("Location: admin/index.php");
    } else {
        header("Location: index.php");
    }

    exit();
}

$error_msg = "";
$ip        = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
$ua        = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf($_POST['csrf_token'] ?? '')) {
        $error_msg = "Invalid security token. Please refresh and try again.";
    } else {
        $login_id = trim($_POST['login_id'] ?? '');
        $password = $_POST['password'] ?? '';

        if (empty($login_id) || empty($password)) {
            $error_msg = "Email and Password are both required!";
        } else {
            try {
                $stmt = $pdo->prepare("
                    SELECT
                        id,
                        fullname,
                        login_id,
                        password,
                        role,
                        status,
                        email_verified
                    FROM users
                    WHERE login_id = ?
                    LIMIT 1
                ");
                $stmt->execute([$login_id]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {

                    // Email must be verified before login
                    if ((int)$user['email_verified'] !== 1) {
                        $_SESSION['verification_user_id'] = (int)$user['id'];
                        $_SESSION['verification_email'] = $user['login_id'];

                        $error_msg = "Please verify your email first. Check your inbox for the OTP.";

                        $pdo->prepare("
                            INSERT INTO login_history
                            (user_id, fullname, email, ip_address, user_agent, status)
                            VALUES (?, ?, ?, ?, ?, 'failed')
                        ")->execute([
                            $user['id'],
                            $user['fullname'],
                            $user['login_id'],
                            $ip,
                            $ua
                        ]);

                    } elseif ($user['status'] !== 'active') {

                        $error_msg = "Your account has been " . $user['status'] . ". Contact admin.";

                        $pdo->prepare("
                            INSERT INTO login_history
                            (user_id, fullname, email, ip_address, user_agent, status)
                            VALUES (?, ?, ?, ?, ?, 'failed')
                        ")->execute([
                            $user['id'],
                            $user['fullname'],
                            $user['login_id'],
                            $ip,
                            $ua
                        ]);

                    } else {
                        // Successful login → set session
                        session_regenerate_id(true);

                        $_SESSION['logged_in'] = true;
                        $_SESSION['user_id']   = $user['id'];
                        $_SESSION['name']      = $user['fullname'];
                        $_SESSION['fullname']  = $user['fullname'];
                        $_SESSION['email']     = $user['login_id'];
                        $_SESSION['login_id']  = $user['login_id'];
                        $_SESSION['role']      = $user['role'];

                        // Update last login
                        $pdo->prepare("UPDATE users SET updated_at = NOW() WHERE id = ?")
                            ->execute([$user['id']]);

                        // Log success
                        $pdo->prepare("
                            INSERT INTO login_history
                            (user_id, fullname, email, ip_address, user_agent, status)
                            VALUES (?, ?, ?, ?, ?, 'success')
                        ")->execute([
                            $user['id'],
                            $user['fullname'],
                            $user['login_id'],
                            $ip,
                            $ua
                        ]);

                        // Redirect based on role
                        if ($user['role'] === 'admin') {
                            header("Location: admin/index.php?msg=" . urlencode("Welcome Admin!") . "&status=success");
                        } else {
                            header("Location: index.php?msg=" . urlencode("Welcome back, " . $user['fullname'] . "!") . "&status=success");
                        }
                        exit();
                    }

                } else {
                    $error_msg = "Invalid Email or Password!";

                    $pdo->prepare("
                        INSERT INTO login_history
                        (user_id, fullname, email, ip_address, user_agent, status)
                        VALUES (NULL, NULL, ?, ?, ?, 'failed')
                    ")->execute([$login_id, $ip, $ua]);
                }
            } catch (PDOException $e) {
                error_log("Login error: " . $e->getMessage());
                $error_msg = "Login failed. Please try again later.";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign In — Coderror</title>
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
.opt-row { display:flex; justify-content:space-between; align-items:center; margin-bottom:22px; font-size:12px; }
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
.btn-signin { width:100%; padding:15px; background:linear-gradient(135deg,#D4AF37,#B8860B);
              color:#000; border:none; border-radius:10px; font-size:15px; font-weight:700;
              letter-spacing:3px; text-transform:uppercase; cursor:pointer;
              transition:all 0.3s; display:flex; align-items:center; justify-content:center; gap:10px; }
.btn-signin:hover { transform:translateY(-3px); box-shadow:0 10px 30px rgba(212,175,55,0.5); }

.google-btn {
    width:100%; padding:13px 15px; margin-top:14px;
    background:#fff; color:#222; border:1px solid #ddd; border-radius:10px;
    font-size:14px; font-weight:600; cursor:pointer; text-decoration:none;
    display:flex; align-items:center; justify-content:center; gap:10px;
    box-sizing:border-box; transition:all .25s;
}
.google-btn:hover { transform:translateY(-2px); box-shadow:0 8px 20px rgba(0,0,0,.18); }
.google-btn i { color:#4285F4; }
.oauth-divider {
    display:flex; align-items:center; gap:12px; margin:18px 0 4px;
    color:rgba(212,175,55,.55); font-size:12px;
}
.oauth-divider::before,.oauth-divider::after {
    content:""; flex:1; height:1px; background:rgba(212,175,55,.25);
}
.verify-link {
    display:inline-block;
    margin-top:10px;
    color:#D4AF37;
    text-decoration:none;
    font-size:12px;
}
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
      <h1>Welcome Back</h1>
      <p>Sign in to continue</p>
    </div>

    <div class="tab-row">
      <a href="login.php" class="active">Login</a>
      <a href="register.php">Register</a>
    </div>

    <div class="alert" id="alertBox"></div>

    <form method="POST" action="login.php" id="loginForm">
      <input type="hidden" name="csrf_token" value="<?php echo csrf_token(); ?>">

      <div class="inp-grp">
        <i class="fas fa-envelope"></i>
        <input type="email" name="login_id" placeholder="Email ID" required autofocus>
      </div>
      <div class="inp-grp">
        <i class="fas fa-lock"></i>
        <input type="password" name="password" id="pwd" placeholder="Enter Password" required>
        <i class="fas fa-eye eye" id="eyeBtn"></i>
      </div>
      <div class="opt-row">
        <label><input type="checkbox" name="remember"> Remember me</label>
        <a href="#">Forgot?</a>
      </div>

      <button type="submit" class="btn-signin" id="signInBtn">
        <i class="fas fa-sign-in-alt"></i>
        <span>SIGN IN</span>
      </button>
    </form>

    <div class="oauth-divider"><span>OR</span></div>
    <a href="google-login.php" class="google-btn">
      <i class="fab fa-google"></i>
      <span>CONTINUE WITH GOOGLE</span>
    </a>

    <p class="foot-link">
      New here? <a href="register.php">Create an account</a>
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

<?php if (!empty($_SESSION['verification_email'])): ?>
const verifyLink = document.createElement('a');
verifyLink.href = 'verify.php';
verifyLink.className = 'verify-link';
verifyLink.textContent = 'Verify email / Enter OTP';
document.getElementById('alertBox').appendChild(document.createElement('br'));
document.getElementById('alertBox').appendChild(verifyLink);
<?php endif; ?>
<?php endif; ?>

document.getElementById('loginForm').addEventListener('submit', function() {
    const btn = document.getElementById('signInBtn');
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> <span>SIGNING IN...</span>';
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