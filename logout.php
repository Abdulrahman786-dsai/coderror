<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Log logout if user logged in
if (isset($_SESSION['user_id'])) {
    try {
        // Optional: log logout event
        // $pdo->prepare("INSERT INTO login_history (user_id, fullname, email, ip_address, status) VALUES (?,?,?,?,?)")
        //     ->execute([$_SESSION['user_id'], $_SESSION['name'] ?? '', $_SESSION['email'] ?? '', $_SERVER['REMOTE_ADDR'] ?? '', 'logout']);
    } catch (PDOException $e) {
        // Silent fail
    }
}

$_SESSION = [];
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}
session_destroy();
header("Location: login.php?msg=" . urlencode("You have been logged out successfully.") . "&status=success");
exit;
?>
