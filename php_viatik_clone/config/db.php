<?php
// Database configuration - REPLACE WITH YOUR ACTUAL CREDENTIALS
define('DB_HOST', 'localhost');
define('DB_NAME', 'viatik_db'); // Replace with your database name
define('DB_USER', 'root');      // Replace with your database username
define('DB_PASS', '');          // Replace with your database password

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // For development, you might want to see the error.
    // For production, log this error and show a generic message.
    error_log("Database Connection Error: " . $e->getMessage());
    die("Could not connect to the database. Please try again later."); // Generic message for users
}

// Note: The user will need to create the database 'viatik_db' (or their chosen name)
// and run the schema from 'database_schema.sql'
?>
