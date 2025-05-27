<?php
// Start session if not already started
function start_secure_session() {
    if (session_status() == PHP_SESSION_NONE) {
        // Further security considerations for sessions:
        // session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'domain' => $_SERVER['HTTP_HOST'], 'secure' => isset($_SERVER['HTTPS']), 'httponly' => true, 'samesite' => 'Lax']);
        session_start();
    }
}

// Redirect to a different page
function redirect($url) {
    header("Location: " . $url);
    exit();
}

// Check if user is logged in
function is_logged_in() {
    return isset($_SESSION['user_id']);
}

// HTML escaping helper
function html_escape($string) {
    return htmlspecialchars($string ?? '', ENT_QUOTES, 'UTF-8');
}
?>
