<?php
session_start();
require_once 'config.php';

// 1. Authentication & Chat Session Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];

// Retrieve chat_id from GET parameter
if (!isset($_GET['chat_id']) || !is_numeric($_GET['chat_id'])) {
    // Invalid chat_id provided
    // Optionally set a flash message for index.php
    $_SESSION['error_message'] = "Invalid chat specified.";
    header("Location: index.php");
    exit;
}
$chat_id = (int)$_GET['chat_id'];

// Establish database connection
$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    error_log("chat.php - Connection failed: " . $conn->connect_error);
    // Show a user-friendly error page or redirect with error
    die("Database connection error. Please try again later.");
}

// Verify user is a participant of this chat and fetch their anonymous name
$stmt_verify_participant = $conn->prepare(
    "SELECT anonymous_name FROM chat_participants WHERE chat_id = ? AND user_id = ?"
);
if (!$stmt_verify_participant) {
    error_log("chat.php - Prepare statement (verify participant) failed: " . $conn->error);
    $conn->close();
    die("Error verifying chat participation. Please try again.");
}
$stmt_verify_participant->bind_param("ii", $chat_id, $user_id);
$stmt_verify_participant->execute();
$result_participant = $stmt_verify_participant->get_result();

if ($participant_data = $result_participant->fetch_assoc()) {
    $fetched_anonymous_name = $participant_data['anonymous_name'];
    $_SESSION['current_chat_id'] = $chat_id; // Confirm/set current chat ID
    $_SESSION['anonymous_name'] = $fetched_anonymous_name; // Confirm/set anonymous name for this chat
} else {
    // User is not a participant of this chat, or chat does not exist
    $stmt_verify_participant->close();
    $conn->close();
    $_SESSION['error_message'] = "You are not authorized to access this chat, or the chat does not exist.";
    // Unset potentially misleading session variables if they were for a different chat
    unset($_SESSION['current_chat_id']);
    unset($_SESSION['anonymous_name']);
    header("Location: index.php");
    exit;
}
$stmt_verify_participant->close();
// Keep $conn open for message sending/fetching which will happen via AJAX calls to other scripts

// (The actual database connection for message handling will be in send_message.php and get_messages.php)
// It's okay to close this connection if chat.php itself doesn't make more DB calls after this initial setup.
$conn->close(); 

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Anonymous Chat</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 0; display: flex; flex-direction: column; height: 100vh; background-color: #f4f7f6; }
        .header { background-color: #007bff; color: white; padding: 10px 20px; display: flex; justify-content: space-between; align-items: center; }
        .header span { font-size: 1.1em; }
        .header a { color: white; background-color: #dc3545; padding: 8px 15px; text-decoration: none; border-radius: 4px; }
        .header a:hover { background-color: #c82333; }
        #chatbox { flex-grow: 1; overflow-y: auto; padding: 20px; background-color: #fff; border-bottom: 1px solid #ddd; }
        #chatbox .message { margin-bottom: 10px; padding: 8px 12px; border-radius: 15px; line-height: 1.4; max-width: 70%; word-wrap: break-word; }
        #chatbox .message strong { display: block; margin-bottom: 2px; font-size: 0.9em; color: #555; }
        #chatbox .my-message { background-color: #dcf8c6; margin-left: auto; border-bottom-right-radius: 5px; }
        #chatbox .other-message { background-color: #e9e9eb; margin-right: auto; border-bottom-left-radius: 5px; }
        #chatbox .system-message { font-style: italic; color: #888; text-align: center; margin-bottom: 10px; font-size: 0.9em; }
        .message-form { display: flex; padding: 10px; background-color: #f8f9fa; border-top: 1px solid #ddd; }
        #message_text { flex-grow: 1; padding: 10px; border: 1px solid #ccc; border-radius: 20px; margin-right: 10px; }
        #send_button { background-color: #007bff; color: white; padding: 10px 20px; border: none; border-radius: 20px; cursor: pointer; }
        #send_button:hover { background-color: #0056b3; }
        #loadingSpinner {
            border: 3px solid #f3f3f3; border-top: 3px solid #007bff; border-radius: 50%;
            width: 20px; height: 20px; animation: spin 1s linear infinite;
            margin: 0 auto; display: none; /* Initially hidden */
        }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="header">
        <span>Chatting as: <strong><?php echo htmlspecialchars($_SESSION['anonymous_name']); ?></strong></span>
        <a href="leave_chat.php">Leave Chat</a>
    </div>

    <div id="chatbox">
        <!-- Messages will be loaded here -->
        <div id="loadingSpinner"></div>
    </div>

    <form id="messageForm" class="message-form">
        <input type="text" id="message_text" autocomplete="off" placeholder="Type your message..." autofocus>
        <button type="submit" id="send_button">Send</button>
    </form>

    <script>
        const chatBox = document.getElementById('chatbox');
        const messageForm = document.getElementById('messageForm');
        const messageInput = document.getElementById('message_text');
        const sendButton = document.getElementById('send_button');
        const loadingSpinner = document.getElementById('loadingSpinner');

        const currentChatId = <?php echo json_encode($_SESSION['current_chat_id']); ?>;
        const currentUserAnonymousName = <?php echo json_encode($_SESSION['anonymous_name']); ?>;
        let lastMessageId = 0;
        let pollingInterval;
        let isFetching = false; // To prevent multiple fetch calls overlapping

        function displayMessage(message) {
            const messageDiv = document.createElement('div');
            messageDiv.classList.add('message');
            
            const nameStrong = document.createElement('strong');
            nameStrong.textContent = message.sender_anonymous_name + ':';
            
            const textSpan = document.createElement('span');
            textSpan.textContent = ' ' + message.message_text; // Add space after name

            messageDiv.appendChild(nameStrong);
            messageDiv.appendChild(textSpan);

            if (message.sender_anonymous_name === currentUserAnonymousName) {
                messageDiv.classList.add('my-message');
            } else {
                messageDiv.classList.add('other-message');
            }
            chatBox.appendChild(messageDiv);
        }

        function scrollChatToBottom() {
            chatBox.scrollTop = chatBox.scrollHeight;
        }

        async function fetchMessages() {
            if (isFetching) return;
            isFetching = true;
            if(loadingSpinner && lastMessageId === 0) loadingSpinner.style.display = 'block';

            try {
                const response = await fetch(`get_messages.php?chat_id=${currentChatId}&last_message_id=${lastMessageId}`);
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const messages = await response.json();

                if (messages.length > 0) {
                    messages.forEach(msg => {
                        displayMessage(msg);
                        lastMessageId = Math.max(lastMessageId, parseInt(msg.id));
                    });
                    scrollChatToBottom();
                }
            } catch (error) {
                console.error('Error fetching messages:', error);
                // Optionally display an error in the chatbox or stop polling
                // clearInterval(pollingInterval); // Consider stopping polling on repeated errors
            } finally {
                isFetching = false;
                if(loadingSpinner) loadingSpinner.style.display = 'none';
            }
        }

        async function sendMessage() {
            const messageText = messageInput.value.trim();
            if (messageText === '') return;

            sendButton.disabled = true;

            try {
                const formData = new FormData();
                formData.append('chat_id', currentChatId);
                formData.append('message_text', messageText);

                const response = await fetch('send_message.php', {
                    method: 'POST',
                    body: formData
                });

                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                
                const result = await response.json();

                if (result.status === 'success') {
                    messageInput.value = '';
                    // The new message will be picked up by the next fetchMessages call
                    // For instant display, can call fetchMessages() or optimistically display:
                    // displayMessage({ sender_anonymous_name: currentUserAnonymousName, message_text: messageText });
                    // scrollChatToBottom();
                    // Let's rely on polling for simplicity here, but optimistic update is better UX.
                    await fetchMessages(); // Fetch immediately after sending for quicker update
                } else {
                    console.error('Error sending message:', result.message);
                    alert('Error sending message: ' + result.message);
                }
            } catch (error) {
                console.error('Error sending message:', error);
                alert('Failed to send message. Please check your connection and try again.');
            } finally {
                sendButton.disabled = false;
                messageInput.focus();
            }
        }

        messageForm.addEventListener('submit', function(event) {
            event.preventDefault();
            sendMessage();
        });

        // Initial fetch and start polling
        document.addEventListener('DOMContentLoaded', () => {
            fetchMessages(); // Fetch initial messages
            pollingInterval = setInterval(fetchMessages, 3000); // Poll every 3 seconds
        });

        // Stop polling when page is not visible to save resources
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                clearInterval(pollingInterval);
            } else {
                // When tab becomes visible again, fetch immediately and restart polling
                fetchMessages();
                pollingInterval = setInterval(fetchMessages, 3000);
            }
        });

    </script>
</body>
</html>
