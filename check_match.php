<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'User not authenticated.']);
    exit;
}

$user_id = $_SESSION['user_id'];

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    error_log("check_match.php - Connection failed: " . $conn->connect_error);
    echo json_encode(['status' => 'error', 'message' => 'Database connection error.']);
    exit;
}

// Check the pending_chats table for the current user
$stmt_check_match = $conn->prepare(
    "SELECT matched_chat_id, assigned_anonymous_name 
     FROM pending_chats 
     WHERE user_id = ?"
);
if (!$stmt_check_match) {
    error_log("check_match.php - Prepare statement failed: " . $conn->error);
    echo json_encode(['status' => 'error', 'message' => 'Database query error (prepare).']);
    $conn->close();
    exit;
}

$stmt_check_match->bind_param("i", $user_id);
$stmt_check_match->execute();
$result_match = $stmt_check_match->get_result();

if ($pending_entry = $result_match->fetch_assoc()) {
    if ($pending_entry['matched_chat_id'] !== null && $pending_entry['assigned_anonymous_name'] !== null) {
        // Match found!
        $matched_chat_id = $pending_entry['matched_chat_id'];
        $assigned_anonymous_name = $pending_entry['assigned_anonymous_name'];

        // Store in session
        $_SESSION['current_chat_id'] = $matched_chat_id;
        $_SESSION['anonymous_name'] = $assigned_anonymous_name;

        // Remove user's record from pending_chats
        $stmt_delete_pending = $conn->prepare("DELETE FROM pending_chats WHERE user_id = ?");
        if (!$stmt_delete_pending) {
            error_log("check_match.php - Prepare statement (delete) failed: " . $conn->error);
            // Proceed to inform user of match anyway, cleanup can be attempted later or might self-resolve
            echo json_encode(['status' => 'matched', 'chat_id' => $matched_chat_id, 'message' => 'Match found, but cleanup failed.']);
            $conn->close();
            exit;
        }
        $stmt_delete_pending->bind_param("i", $user_id);
        $stmt_delete_pending->execute();
        $stmt_delete_pending->close();
        
        echo json_encode(['status' => 'matched', 'chat_id' => $matched_chat_id]);
    } else {
        // Still waiting, no match_id assigned yet
        echo json_encode(['status' => 'waiting']);
    }
} else {
    // User is not in pending_chats. This could mean they were matched by another user
    // and their record was already processed by index.php, or they never initiated a search.
    // If $_SESSION['current_chat_id'] is set, they are already matched.
    if (isset($_SESSION['current_chat_id'])) {
         echo json_encode(['status' => 'matched', 'chat_id' => $_SESSION['current_chat_id']]);
    } else {
        // This state might occur if user cancelled or there was an issue.
        // Or if they open index.php and then clear their own pending_chat entry somehow and then polling continues.
        echo json_encode(['status' => 'idle_or_error', 'message' => 'Not actively waiting or status unclear.']);
    }
}

$stmt_check_match->close();
$conn->close();
?>
