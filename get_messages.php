<?php
header('Content-Type: application/json');
session_start();
require_once 'config.php';

// --- Authentication & Input Validation ---

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "GET") {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    exit;
}

// Validate chat_id
if (!isset($_GET['chat_id']) || !is_numeric($_GET['chat_id'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid or missing chat_id']);
    exit;
}
$chat_id = (int)$_GET['chat_id'];

// Security check: Ensure chat_id from GET matches chat_id in session
if (!isset($_SESSION['current_chat_id']) || $chat_id !== $_SESSION['current_chat_id']) {
    echo json_encode(['status' => 'error', 'message' => 'Chat ID mismatch or not set in session.']);
    exit;
}

// Validate last_message_id (optional)
$last_message_id = 0; // Default
if (isset($_GET['last_message_id'])) {
    if (is_numeric($_GET['last_message_id'])) {
        $last_message_id = (int)$_GET['last_message_id'];
        if ($last_message_id < 0) $last_message_id = 0; // Ensure non-negative
    } else {
        // Optional: could return an error if last_message_id is present but invalid
        // For now, just defaulting to 0 if invalid format.
        $last_message_id = 0;
    }
}

// --- Database Operations ---
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    error_log("get_messages.php - Connection failed: " . $conn->connect_error);
    echo json_encode(['status' => 'error', 'message' => 'Database connection error.']);
    exit;
}

// Verify User is Participant (Security Check)
$user_id = $_SESSION['user_id'];
$stmt_verify = $conn->prepare("SELECT id FROM chat_participants WHERE chat_id = ? AND user_id = ?");
if (!$stmt_verify) {
    error_log("get_messages.php - Prepare statement (verify participant) failed: " . $conn->error);
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'Database error (verify).']);
    exit;
}
$stmt_verify->bind_param("ii", $chat_id, $user_id);
$stmt_verify->execute();
$result_verify = $stmt_verify->get_result();
if ($result_verify->num_rows === 0) {
    $stmt_verify->close();
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'Access denied to this chat.']);
    exit;
}
$stmt_verify->close();

// Fetch Messages
$messages = [];
$sql = "SELECT m.id, m.message_text, m.sent_at, cp.anonymous_name 
        FROM messages m 
        JOIN chat_participants cp ON m.participant_id = cp.id 
        WHERE m.chat_id = ? AND m.id > ? 
        ORDER BY m.sent_at ASC, m.id ASC"; // Order by sent_at first, then ID for tie-breaking

$stmt_fetch = $conn->prepare($sql);
if (!$stmt_fetch) {
    error_log("get_messages.php - Prepare statement (fetch messages) failed: " . $conn->error);
    $conn->close();
    echo json_encode(['status' => 'error', 'message' => 'Database error retrieving messages (prepare).']);
    exit;
}

$stmt_fetch->bind_param("ii", $chat_id, $last_message_id);

if ($stmt_fetch->execute()) {
    $result_messages = $stmt_fetch->get_result();
    while ($row = $result_messages->fetch_assoc()) {
        // Ensure sent_at is formatted consistently if needed, e.g. ISO 8601 for JS Date objects
        // For now, using the database's default string format for TIMESTAMP
        $messages[] = [
            'id' => $row['id'],
            'anonymous_name' => $row['anonymous_name'],
            'message_text' => $row['message_text'],
            'sent_at' => $row['sent_at']
        ];
    }
    echo json_encode(['status' => 'success', 'messages' => $messages]);
} else {
    error_log("get_messages.php - Execute statement (fetch messages) failed: " . $stmt_fetch->error);
    echo json_encode(['status' => 'error', 'message' => 'Failed to retrieve messages.']);
}

$stmt_fetch->close();
$conn->close();
?>
