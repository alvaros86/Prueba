<?php
session_start();
require_once 'config.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}
$user_id = $_SESSION['user_id']; // Not directly used for fetching general chats, but good for context

// Establish database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    error_log("conversations.php - Connection failed: " . $conn->connect_error);
    die("Database connection error. Please try again later.");
}

$current_chat_messages = null;
$current_chat_participants = null;
$view_chat_id = null;
$available_chats = [];

// Handling Specific Chat View
if (isset($_GET['view_chat_id'])) {
    if (!is_numeric($_GET['view_chat_id'])) {
        // Handle invalid chat ID - maybe redirect or show error
        $_SESSION['error_message_conv'] = "Invalid chat ID specified.";
        header("Location: conversations.php"); // Redirect back to list
        exit;
    }
    $view_chat_id = (int)$_GET['view_chat_id'];

    // Security Check: Ensure the current user was a participant in the chat they are trying to view.
    // This prevents users from viewing chats they were not part of.
    $stmt_check_participation = $conn->prepare(
        "SELECT 1 FROM chat_participants WHERE chat_id = ? AND user_id = ?"
    );
    if (!$stmt_check_participation) {
        error_log("conversations.php - Prepare (check participation) failed: " . $conn->error);
        die("Error verifying chat access.");
    }
    $stmt_check_participation->bind_param("ii", $view_chat_id, $user_id);
    $stmt_check_participation->execute();
    $result_participation = $stmt_check_participation->get_result();
    if ($result_participation->num_rows === 0) {
        $_SESSION['error_message_conv'] = "You are not authorized to view this chat.";
        $stmt_check_participation->close();
        header("Location: conversations.php");
        exit;
    }
    $stmt_check_participation->close();


    // Fetch messages for this chat_id
    $stmt_messages = $conn->prepare(
        "SELECT m.id, m.message_text, m.sent_at, cp.anonymous_name 
         FROM messages m 
         JOIN chat_participants cp ON m.participant_id = cp.id 
         WHERE m.chat_id = ? 
         ORDER BY m.sent_at ASC, m.id ASC"
    );
    if (!$stmt_messages) {
        error_log("conversations.php - Prepare (messages) failed: " . $conn->error);
        die("Error fetching messages.");
    }
    $stmt_messages->bind_param("i", $view_chat_id);
    $stmt_messages->execute();
    $result_messages = $stmt_messages->get_result();
    $current_chat_messages = [];
    while ($row = $result_messages->fetch_assoc()) {
        $current_chat_messages[] = $row;
    }
    $stmt_messages->close();

    // Fetch participants for this chat_id
    $stmt_participants = $conn->prepare(
        "SELECT anonymous_name FROM chat_participants WHERE chat_id = ?"
    );
    if (!$stmt_participants) {
        error_log("conversations.php - Prepare (participants) failed: " . $conn->error);
        die("Error fetching participants.");
    }
    $stmt_participants->bind_param("i", $view_chat_id);
    $stmt_participants->execute();
    $result_participants = $stmt_participants->get_result();
    $current_chat_participants = [];
    while ($row = $result_participants->fetch_assoc()) {
        $current_chat_participants[] = $row['anonymous_name'];
    }
    $stmt_participants->close();

} else {
    // Main listing page: Fetch chats the current user participated in
    $stmt_available_chats = $conn->prepare(
        "SELECT DISTINCT c.id, c.created_at, 
         (SELECT GROUP_CONCAT(cp_inner.anonymous_name SEPARATOR ', ') 
          FROM chat_participants cp_inner 
          WHERE cp_inner.chat_id = c.id) AS participants
         FROM chats c
         JOIN chat_participants cp ON c.id = cp.chat_id
         WHERE cp.user_id = ?
         ORDER BY c.created_at DESC LIMIT 50"
    );
    if (!$stmt_available_chats) {
        error_log("conversations.php - Prepare (available_chats) failed: " . $conn->error);
        die("Error fetching available chats.");
    }
    $stmt_available_chats->bind_param("i", $user_id);
    $stmt_available_chats->execute();
    $result_available_chats = $stmt_available_chats->get_result();
    while ($row = $result_available_chats->fetch_assoc()) {
        $available_chats[] = $row;
    }
    $stmt_available_chats->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $view_chat_id ? "Conversation Details" : "Past Conversations"; ?></title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 800px; margin: auto; background-color: #f4f7f6; }
        .container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        nav { margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        nav a { margin-right: 15px; text-decoration: none; color: #007bff; }
        nav a:hover { text-decoration: underline; }
        .chat-log div { margin-bottom: 8px; padding: 5px; border-bottom: 1px solid #f0f0f0; }
        .chat-log div:last-child { border-bottom: none; }
        .chat-log strong { color: #333; }
        .chat-log small { color: #777; font-size: 0.85em; }
        ul { list-style: none; padding: 0; }
        ul li { margin-bottom: 10px; background-color: #e9ecef; padding: 10px; border-radius: 4px; }
        ul li a { text-decoration: none; color: #0056b3; font-weight: bold; }
        ul li a:hover { color: #003d80; }
        .participants-list { font-style: italic; color: #555; margin-bottom: 15px; }
        .error-message { color: red; background-color: #ffebee; border: 1px solid #ffcdd2; padding: 10px; border-radius: 4px; margin-bottom: 15px;}
    </style>
</head>
<body>
    <div class="container">
        <nav>
            <a href="index.php">Chat Lobby</a>
            <?php if ($view_chat_id): ?>
                <a href="conversations.php">Back to Conversations List</a>
            <?php endif; ?>
            <a href="logout.php">Logout</a>
        </nav>

        <?php if (isset($_SESSION['error_message_conv'])): ?>
            <p class="error-message"><?php echo htmlspecialchars($_SESSION['error_message_conv']); ?></p>
            <?php unset($_SESSION['error_message_conv']); ?>
        <?php endif; ?>

        <?php if ($view_chat_id && $current_chat_messages !== null): ?>
            <h2>Conversation Details (Chat ID: <?php echo htmlspecialchars($view_chat_id); ?>)</h2>
            
            <?php if ($current_chat_participants): ?>
                <p class="participants-list">Participants: <?php echo htmlspecialchars(implode(', ', $current_chat_participants)); ?></p>
            <?php endif; ?>

            <div class="chat-log">
                <?php if (empty($current_chat_messages)): ?>
                    <p>No messages in this conversation.</p>
                <?php else: ?>
                    <?php foreach ($current_chat_messages as $message): ?>
                        <div>
                            <strong><?php echo htmlspecialchars($message['anonymous_name']); ?>:</strong>
                            <?php echo htmlspecialchars($message['message_text']); ?>
                            <small>(<?php echo htmlspecialchars($message['sent_at']); ?>)</small>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

        <?php else: ?>
            <h2>Past Conversations</h2>
            <?php if (!empty($available_chats)): ?>
                <ul>
                    <?php foreach ($available_chats as $chat): ?>
                        <li>
                            <a href="conversations.php?view_chat_id=<?php echo htmlspecialchars($chat['id']); ?>">
                                Chat from <?php echo htmlspecialchars(date("M d, Y H:i", strtotime($chat['created_at']))); ?>
                                <?php if (!empty($chat['participants'])): ?>
                                    <br><small>With: <?php echo htmlspecialchars($chat['participants']); ?></small>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p>No past conversations found where you participated.</p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</body>
</html>
