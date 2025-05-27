<?php
require_once __DIR__ . '/functions.php';
start_secure_session(); // Start session on every page that includes header

// Determine base path for links if app is in a subdirectory
$base_path = rtrim(str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']), '/');
if ($base_path === '/php_viatik_clone') { // Common case if accessed directly in its folder
    $base_url = '/php_viatik_clone/';
} else {
    $base_url = '/'; // Adjust if needed, or make it configurable
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Viatik Clone</title>
    <!-- Link to a future CSS file -->
    <link rel="stylesheet" href="<?php echo html_escape($base_url); ?>assets/css/style.css">
    <!-- Link to manifest.json for PWA -->
    <link rel="manifest" href="<?php echo html_escape($base_url); ?>manifest.json">
    <meta name="theme-color" content="#3367D6"> <!-- Example theme color -->
</head>
<body>
    <header>
        <nav>
            <a href="<?php echo html_escape($base_url); ?>index.php">Home</a>
            <?php if (is_logged_in()): ?>
                <a href="<?php echo html_escape($base_url); ?>create_trip.php">Offer Ride</a>
                <a href="<?php echo html_escape($base_url); ?>find_trip.php">Find Ride</a>
                <a href="<?php echo html_escape($base_url); ?>my_trips.php">My Trips</a> 
                <a href="<?php echo html_escape($base_url); ?>logout.php">Logout (<?php echo html_escape($_SESSION['username'] ?? ''); ?>)</a>
            <?php else: ?>
                <a href="<?php echo html_escape($base_url); ?>register.php">Register</a>
                <a href="<?php echo html_escape($base_url); ?>login.php">Login</a>
            <?php endif; ?>
        </nav>
    </header>
    <main>
