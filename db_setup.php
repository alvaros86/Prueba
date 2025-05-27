<?php
// Include database configuration
require_once 'config.php';

// --- Database Setup Instructions ---
echo "-----------------------------------------------------------------\n";
echo "Anonymous Chat System - Database Setup Script\n";
echo "-----------------------------------------------------------------\n";
echo "IMPORTANT:\n";
echo "1. Ensure you have a MySQL/MariaDB server running.\n";
echo "2. Update 'config.php' with your actual database credentials.\n";
echo "   - DB_SERVER: Your database server (e.g., 'localhost')\n";
echo "   - DB_USERNAME: Your database username (e.g., 'root')\n";
echo "   - DB_PASSWORD: Your database password\n";
echo "   - DB_NAME: The name for the chat database (e.g., 'anonymous_chat_db')\n";
echo "3. Run this script from your command line: php db_setup.php\n";
echo "   Or, if you have a web server configured, access it via your browser.\n";
echo "-----------------------------------------------------------------\n\n";

// Establish connection to MySQL server
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error . "\n");
}
echo "Successfully connected to MySQL server.\n";

// Attempt to create the database
$sqlCreateDB = "CREATE DATABASE IF NOT EXISTS " . DB_NAME;
if ($conn->query($sqlCreateDB) === TRUE) {
    echo "Database '" . DB_NAME . "' created successfully or already exists.\n";
} else {
    die("Error creating database: " . $conn->error . "\n");
}

// Select the database
$conn->select_db(DB_NAME);
if ($conn->error) {
    die("Error selecting database '" . DB_NAME . "': " . $conn->error . "\n");
}
echo "Database '" . DB_NAME . "' selected.\n";

// SQL to create tables
$sqlUsers = "
CREATE TABLE IF NOT EXISTS users (
    id INT PRIMARY KEY AUTO_INCREMENT,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sqlChats = "
CREATE TABLE IF NOT EXISTS chats (
    id INT PRIMARY KEY AUTO_INCREMENT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";

$sqlChatParticipants = "
CREATE TABLE IF NOT EXISTS chat_participants (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chat_id INT NOT NULL,
    user_id INT NOT NULL,
    anonymous_name VARCHAR(50) NOT NULL,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

$sqlMessages = "
CREATE TABLE IF NOT EXISTS messages (
    id INT PRIMARY KEY AUTO_INCREMENT,
    chat_id INT NOT NULL,
    participant_id INT NOT NULL,
    message_text TEXT NOT NULL,
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,
    FOREIGN KEY (participant_id) REFERENCES chat_participants(id) ON DELETE CASCADE
)";

// Execute create table statements
$tables = [
    'users' => $sqlUsers,
    'chats' => $sqlChats,
    'chat_participants' => $sqlChatParticipants,
    'messages' => $sqlMessages,
];

// SQL to create pending_chats table
$sqlPendingChats = "
CREATE TABLE IF NOT EXISTS pending_chats (
    user_id INT PRIMARY KEY,
    requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    matched_chat_id INT NULL,
    assigned_anonymous_name VARCHAR(50) NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
)";

// Add to the tables array
$tables['pending_chats'] = $sqlPendingChats;

foreach ($tables as $tableName => $sql) {
    if ($conn->query($sql) === TRUE) {
        echo "Table '$tableName' created successfully or already exists.\n";
    } else {
        echo "Error creating table '$tableName': " . $conn->error . "\n";
    }
}

echo "\n-----------------------------------------------------------------\n";
echo "Database setup process complete.\n";
echo "If all steps were successful, your database and tables are ready.\n";
echo "-----------------------------------------------------------------\n";

$conn->close();

echo "\n--- SQLite Alternative Instructions ---\n";
echo "For a simpler setup, you can use SQLite.\n";
echo "1. You won't need 'config.php' in the same way for credentials.\n";
echo "2. The PHP script would connect to a SQLite database file, e.g.:\n";
echo "   `\$db = new SQLite3('anonymous_chat.sqlite');`\n";
echo "3. The SQL CREATE TABLE statements are slightly different:\n\n";

echo "CREATE TABLE users (\n";
echo "    id INTEGER PRIMARY KEY AUTOINCREMENT,\n";
echo "    email TEXT UNIQUE NOT NULL,\n";
echo "    password_hash TEXT NOT NULL,\n";
echo "    created_at TEXT DEFAULT CURRENT_TIMESTAMP\n";
echo ");\n\n";

echo "CREATE TABLE chats (\n";
echo "    id INTEGER PRIMARY KEY AUTOINCREMENT,\n";
echo "    created_at TEXT DEFAULT CURRENT_TIMESTAMP\n";
echo ");\n\n";

echo "CREATE TABLE chat_participants (\n";
echo "    id INTEGER PRIMARY KEY AUTOINCREMENT,\n";
echo "    chat_id INTEGER NOT NULL,\n";
echo "    user_id INTEGER NOT NULL,\n";
echo "    anonymous_name TEXT NOT NULL,\n";
echo "    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,\n";
echo "    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n";
echo ");\n\n";

echo "CREATE TABLE pending_chats (\n";
echo "    user_id INTEGER PRIMARY KEY,\n";
echo "    requested_at TEXT DEFAULT CURRENT_TIMESTAMP,\n";
echo "    matched_chat_id INTEGER NULL,\n";
echo "    assigned_anonymous_name TEXT NULL,\n";
echo "    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE\n";
echo ");\n\n";

echo "CREATE TABLE messages (\n";
echo "    id INTEGER PRIMARY KEY AUTOINCREMENT,\n";
echo "    chat_id INTEGER NOT NULL,\n";
echo "    participant_id INTEGER NOT NULL,\n";
echo "    message_text TEXT NOT NULL,\n";
echo "    sent_at TEXT DEFAULT CURRENT_TIMESTAMP,\n";
echo "    FOREIGN KEY (chat_id) REFERENCES chats(id) ON DELETE CASCADE,\n";
echo "    FOREIGN KEY (participant_id) REFERENCES chat_participants(id) ON DELETE CASCADE\n";
echo ");\n\n";
echo "You would execute these queries using the SQLite3 object in PHP.\n";
echo "----------------------------------------\n";

?>
