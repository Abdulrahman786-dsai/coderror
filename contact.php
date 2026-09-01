<?php
session_start();
require_once __DIR__ . '/config/db.php';

// Handle POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: contact.html");
    exit();
}

// CSRF check (relaxed — allow from contact form)
$name     = trim($_POST['name']     ?? '');
$email    = trim($_POST['email']    ?? '');
$phone    = trim($_POST['phone']    ?? '');
$interest = trim($_POST['interest'] ?? '');
$message  = trim($_POST['message']  ?? '');

// Validation
if (empty($name) || empty($email) || empty($message)) {
    header("Location: contact.html?status=error&msg=" 
         . urlencode("Please fill in all required fields!"));
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header("Location: contact.html?status=error&msg=" 
         . urlencode("Please enter a valid email address!"));
    exit();
}
if (strlen($message) < 5) {
    header("Location: contact.html?status=error&msg=" 
         . urlencode("Message is too short!"));
    exit();
}

$ip   = $_SERVER['REMOTE_ADDR'] ?? null;
$ua   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

try {
    $stmt = $pdo->prepare("
        INSERT INTO contacts (name, email, phone, interest, message, ip_address, user_agent)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");
    $stmt->execute([$name, $email, $phone, $interest, $message, $ip, $ua]);

    // ✅ Optional: Send email notification here

    header("Location: contact.html?status=success&msg=" 
         . urlencode("Thank you, $name! Your message has been received. We will get back to you soon."));
    exit();

} catch (PDOException $e) {
    error_log("Contact form error: " . $e->getMessage());
    header("Location: contact.html?status=error&msg=" 
         . urlencode("Something went wrong. Please try again later."));
    exit();
}
?>
