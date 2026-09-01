<?php
session_start();
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/config/google_oauth.php';

// Create a one-time OAuth state value to prevent CSRF.
$_SESSION['google_oauth_state'] = bin2hex(random_bytes(32));

$params = [
    'client_id' => GOOGLE_CLIENT_ID,
    'redirect_uri' => GOOGLE_REDIRECT_URI,
    'response_type' => 'code',
    'scope' => 'openid email profile',
    'access_type' => 'online',
    'state' => $_SESSION['google_oauth_state'],
    'prompt' => 'select_account'
];

header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params));
exit;
