<?php
require_once __DIR__ . '/includes/header.php';
?>

<h1>Welcome to Viatik Clone!</h1>

<p>This is a PHP-based ride-sharing application.</p>

<?php if (is_logged_in()): ?>
    <p>Hello, <?php echo html_escape($_SESSION['username']); ?>! What would you like to do?</p>
    <ul>
        <li><a href="<?php echo html_escape($base_url); ?>create_trip.php">Offer a new ride</a></li>
        <li><a href="<?php echo html_escape($base_url); ?>find_trip.php">Find a ride</a></li>
        <li><a href="<?php echo html_escape($base_url); ?>my_trips.php">View your trips</a></li>
    </ul>
<?php else: ?>
    <p>Please <a href="<?php echo html_escape($base_url); ?>login.php">login</a> or <a href="<?php echo html_escape($base_url); ?>register.php">register</a> to continue.</p>
<?php endif; ?>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
