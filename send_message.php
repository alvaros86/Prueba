<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

// --- Authentication & Input Validation ---

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

if (!isset($_POST['chat_id']) || !isset($_POST['message_text'])) {
    echo json_encode(['status' => 'error', 'message' => 'Missing chat_id or message_text']);
    exit;
}

$chat_id = $_POST['chat_id'];
$message_text = trim($_POST['message_text']);
$user_id = $_SESSION['user_id'];

// Validate chat_id
if (!is_numeric($chat_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid chat ID format']);
    exit;
}
$chat_id = (int)$chat_id;

// Security check: Ensure chat_id from POST matches chat_id in session
if (!isset($_SESSION['current_chat_id']) || $chat_id !== $_SESSION['current_chat_id']) {
    echo json_encode(['status' => 'error', 'message' => 'Chat ID mismatch or not set in session.']);
    exit;
}

// Validate message_text
if (empty($message_text)) {
    echo json_encode(['status' => 'error', 'message' => 'Message cannot be empty']);
    exit;
}

// --- Database Operations ---
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    error_log("send_message.php - Connection failed: " . $conn->connect_error);
    echo json_encode(['status' => 'error', 'message' => 'Database connection error. Please try again later.']);
    exit;
}

// Get participant_id
$participant_id = null;
$stmt_get_participant = $conn->prepare(
    "SELECT id FROM chat_participants WHERE user_id = ? AND chat_id = ?"
);
if (!$stmt_get_participant) {
    error_log("send_message.php - Prepare statement (get participant) failed: " . $conn->error);
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'Database error processing your request.']);
    exit;
}

$stmt_get_participant->bind_param("ii", $user_id, $chat_id);
$stmt_get_participant->execute();
$result_participant = $stmt_get_participant->get_result();

if ($row = $result_participant->fetch_assoc()) {
    $participant_id = $row['id'];
}
$stmt_get_participant->close();

if ($participant_id === null) {
    echo json_encode(['status' => 'error', 'message' => 'Error sending message: Participant not found in chat.']);
    $conn->close();
    exit;
}

// Store Message
$stmt_insert_message = $conn->prepare(
    "INSERT INTO messages (chat_id, participant_id, message_text) VALUES (?, ?, ?)"
);
if (!$stmt_insert_message) {
    error_log("send_message.php - Prepare statement (insert message) failed: " . $conn->error);
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'Database error processing your message.']);
    exit;
}

$stmt_insert_message->bind_param("iis", $chat_id, $participant_id, $message_text);

if ($stmt_insert_message->execute()) {
    echo json_encode(['status' => 'success', 'message' => 'Message sent']);
} else {
    error_log("send_message.php - Execute statement (insert message) failed: " . $stmt_insert_message->error);
    echo json_encode(['status' => 'error', 'message' => 'Failed to send message']);
}

$stmt_insert_message->close();
$conn->close();
?>
