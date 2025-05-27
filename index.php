<?php
session_start();
require_once 'config.php';

// Authentication Check
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_email = $_SESSION['email']; // Assuming email is stored in session from login

$conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);
if ($conn->connect_error) {
    // Log error and show a user-friendly message
    error_log("Connection failed: " . $conn->connect_error);
    die("An error occurred. Please try again later.");
}

$status_message = '';
$is_waiting = false;

// Anonymous names pool
$anonymous_names_pool = ["CuriousCat", "WiseOwl", "SilentFox", "HappyPanda", "WittyBadger", "CleverCoyote", "GentleGiraffe", "BraveLion", "SwiftSparrow", "EagerBeaver"];

function assign_anonymous_names(array $pool): array {
    if (count($pool) < 2) {
        return ["UserAlpha", "UserBeta"]; // Fallback
    }
    $keys = array_rand($pool, 2);
    return [$pool[$keys[0]], $pool[$keys[1]]];
}

// Check if user is already in an active chat (e.g., from a previous unfinished session or if redirected back)
// This logic might need refinement depending on how chat sessions are fully managed
$stmt_check_active_chat = $conn->prepare(
    "SELECT cp.chat_id, cp.anonymous_name 
     FROM chat_participants cp
     JOIN chats c ON cp.chat_id = c.id -- Potentially join with chats if chats can be 'active' or 'closed'
     WHERE cp.user_id = ? 
     -- Add a condition here if chats can be marked as 'closed' or 'ended'
     -- For now, any participation means they might be in a chat.
     -- To be more robust, we'd need a way to know if that chat is *currently* active.
     -- Let's assume for now if they are in chat_participants, they might be in a chat.
     -- A better check would be against pending_chats only for the "Find Chat" process initiation
     -- and then a redirect from check_match.php if already matched.
     ORDER BY c.created_at DESC LIMIT 1" 
);
// For now, we'll rely on the pending_chats check primarily for new chat initiation.
// If $_SESSION['current_chat_id'] is set, they are in a chat.

if (isset($_SESSION['current_chat_id'])) {
    header("Location: chat.php?chat_id=" . $_SESSION['current_chat_id']);
    exit;
}


// Handle "Find Chat" POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['action']) && $_POST['action'] == 'find_chat') {
    // 1. Check if user is already waiting in pending_chats
    $stmt_check_pending = $conn->prepare("SELECT user_id FROM pending_chats WHERE user_id = ?");
    $stmt_check_pending->bind_param("i", $user_id);
    $stmt_check_pending->execute();
    $result_check_pending = $stmt_check_pending->get_result();
    $stmt_check_pending->close();

    if ($result_check_pending->num_rows > 0) {
        $is_waiting = true;
        $status_message = "You are already waiting for a partner. The page will check for a match.";
    } else {
        // 2. Look for a partner in pending_chats
        $stmt_find_partner = $conn->prepare(
            "SELECT user_id, requested_at FROM pending_chats 
             WHERE user_id != ? AND matched_chat_id IS NULL 
             ORDER BY requested_at ASC LIMIT 1"
        );
        $stmt_find_partner->bind_param("i", $user_id);
        $stmt_find_partner->execute();
        $result_find_partner = $stmt_find_partner->get_result();
        $stmt_find_partner->close();

        if ($partner = $result_find_partner->fetch_assoc()) {
            // Partner found!
            $partner_user_id = $partner['user_id'];
            list($anon_name_current_user, $anon_name_partner) = assign_anonymous_names($anonymous_names_pool);

            $conn->begin_transaction();
            try {
                // a. Create new chat
                $stmt_create_chat = $conn->prepare("INSERT INTO chats (created_at) VALUES (NOW())");
                $stmt_create_chat->execute();
                $new_chat_id = $conn->insert_id;
                $stmt_create_chat->close();

                if (!$new_chat_id) {
                    throw new Exception("Failed to create chat.");
                }

                // b. Insert current user into chat_participants
                $stmt_add_current_user = $conn->prepare("INSERT INTO chat_participants (chat_id, user_id, anonymous_name) VALUES (?, ?, ?)");
                $stmt_add_current_user->bind_param("iis", $new_chat_id, $user_id, $anon_name_current_user);
                $stmt_add_current_user->execute();
                $stmt_add_current_user->close();

                // c. Insert partner into chat_participants
                $stmt_add_partner = $conn->prepare("INSERT INTO chat_participants (chat_id, user_id, anonymous_name) VALUES (?, ?, ?)");
                $stmt_add_partner->bind_param("iis", $new_chat_id, $partner_user_id, $anon_name_partner);
                $stmt_add_partner->execute();
                $stmt_add_partner->close();

                // d. Update partner's pending_chats entry with matched_chat_id and their anonymous_name
                $stmt_update_partner_pending = $conn->prepare(
                    "UPDATE pending_chats SET matched_chat_id = ?, assigned_anonymous_name = ? 
                     WHERE user_id = ?"
                );
                $stmt_update_partner_pending->bind_param("isi", $new_chat_id, $anon_name_partner, $partner_user_id);
                $stmt_update_partner_pending->execute();
                $stmt_update_partner_pending->close();
                
                // e. Remove current user from pending_chats (if they were there, though logic implies they weren't to reach here)
                //    More importantly, the partner was found in pending_chats, their record is updated, not deleted yet.
                //    The partner's check_match.php call will handle their removal.

                $conn->commit();

                // Store chat info in session for current user and redirect
                $_SESSION['current_chat_id'] = $new_chat_id;
                $_SESSION['anonymous_name'] = $anon_name_current_user;
                header("Location: chat.php?chat_id=" . $new_chat_id);
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $status_message = "Error finding chat: " . $e->getMessage();
                error_log("Pairing Error: " . $e->getMessage());
            }
        } else {
            // No partner found, add current user to pending_chats
            $stmt_add_to_pending = $conn->prepare("INSERT INTO pending_chats (user_id) VALUES (?) ON DUPLICATE KEY UPDATE requested_at = NOW()");
            // Using ON DUPLICATE KEY UPDATE to handle rare case of race condition / re-click
            $stmt_add_to_pending->bind_param("i", $user_id);
            if ($stmt_add_to_pending->execute()) {
                $is_waiting = true;
                $status_message = "Waiting for a chat partner... We'll check for a match automatically.";
            } else {
                $status_message = "Error joining the chat queue: " . $conn->error;
                error_log("Error adding user to pending_chats: " . $conn->error);
            }
            $stmt_add_to_pending->close();
        }
    }
} else {
    // Not a POST request, check if user is already waiting from a previous session/refresh
    $stmt_check_waiting = $conn->prepare("SELECT user_id, matched_chat_id, assigned_anonymous_name FROM pending_chats WHERE user_id = ?");
    $stmt_check_waiting->bind_param("i", $user_id);
    $stmt_check_waiting->execute();
    $result_waiting = $stmt_check_waiting->get_result();
    if ($waiting_user = $result_waiting->fetch_assoc()) {
        if ($waiting_user['matched_chat_id']) {
            // Matched while they were away or on refresh!
            $_SESSION['current_chat_id'] = $waiting_user['matched_chat_id'];
            $_SESSION['anonymous_name'] = $waiting_user['assigned_anonymous_name'];

            // Clean up pending_chats for this user
            $stmt_delete_pending = $conn->prepare("DELETE FROM pending_chats WHERE user_id = ?");
            $stmt_delete_pending->bind_param("i", $user_id);
            $stmt_delete_pending->execute();
            $stmt_delete_pending->close();

            header("Location: chat.php?chat_id=" . $waiting_user['matched_chat_id']);
            exit;
        } else {
            $is_waiting = true;
            $status_message = "You are currently in the queue. Waiting for a partner...";
        }
    }
    $stmt_check_waiting->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chat Lobby</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 600px; margin: auto; background-color: #f4f7f6; color: #333; }
        .container { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .header { margin-bottom: 20px; padding-bottom: 10px; border-bottom: 1px solid #eee; }
        .header h2 { margin: 0; }
        .header a { float: right; text-decoration: none; color: #007bff; }
        .header a:hover { text-decoration: underline; }
        .status-message { padding: 10px; background-color: #e6f7ff; border: 1px solid #b3e0ff; border-radius: 4px; margin-bottom: 20px; color: #005f80; }
        .error-message { background-color: #ffebee; border-color: #ffcdd2; color: #c62828; }
        .find-chat-btn {
            background-color: #28a745; color: white; padding: 10px 20px; border: none;
            border-radius: 5px; cursor: pointer; font-size: 16px; display: block; width: 100%;
            box-sizing: border-box; text-align: center;
        }
        .find-chat-btn:hover { background-color: #218838; }
        .waiting-message { font-style: italic; color: #555; text-align: center; }
        #loadingSpinner {
            border: 4px solid #f3f3f3; /* Light grey */
            border-top: 4px solid #3498db; /* Blue */
            border-radius: 50%;
            width: 30px;
            height: 30px;
            animation: spin 1s linear infinite;
            margin: 20px auto;
            display: none; /* Hidden by default */
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <a href="logout.php">Logout</a>
            <h2>Welcome, <?php echo htmlspecialchars($user_email); ?>!</h2>
        </div>

        <?php if ($status_message): ?>
            <p class="status-message <?php echo (strpos(strtolower($status_message), 'error') !== false) ? 'error-message' : ''; ?>">
                <?php echo htmlspecialchars($status_message); ?>
            </p>
        <?php endif; ?>

        <?php if ($is_waiting): ?>
            <p class="waiting-message">Waiting for a partner... The page will check for a match.</p>
            <div id="loadingSpinner" style="display: block;"></div>
            <!-- JavaScript for polling will be added here -->
        <?php else: ?>
            <form action="index.php" method="post">
                <input type="hidden" name="action" value="find_chat">
                <button type="submit" class="find-chat-btn">Find Chat Partner</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if ($is_waiting): ?>
    <script>
        let pollingInterval;
        const spinner = document.getElementById('loadingSpinner');

        function checkMatch() {
            if (spinner) spinner.style.display = 'block';
            fetch('check_match.php')
                .then(response => response.json())
                .then(data => {
                    if (data.status === 'matched') {
                        if (pollingInterval) clearInterval(pollingInterval);
                        if (spinner) spinner.style.display = 'none';
                        // Store session info on client side? No, server handles session via check_match.php
                        window.location.href = 'chat.php?chat_id=' + data.chat_id;
                    } else if (data.status === 'waiting') {
                        // Continue polling
                        console.log('Still waiting...');
                    } else if (data.status === 'error') {
                        console.error('Error checking match:', data.message);
                        if (pollingInterval) clearInterval(pollingInterval);
                        if (spinner) spinner.style.display = 'none';
                        // Optionally display an error message to the user on the page
                        const container = document.querySelector('.container');
                        if(container && !document.querySelector('.error-message.polling-error')) {
                            const errorP = document.createElement('p');
                            errorP.className = 'status-message error-message polling-error';
                            errorP.textContent = 'Error checking for match: ' + (data.message || 'Please refresh.');
                            container.appendChild(errorP);
                        }
                    }
                })
                .catch(error => {
                    console.error('Fetch error:', error);
                    if (pollingInterval) clearInterval(pollingInterval); // Stop polling on network error
                    if (spinner) spinner.style.display = 'none';
                     const container = document.querySelector('.container');
                     if(container && !document.querySelector('.error-message.polling-error')) {
                        const errorP = document.createElement('p');
                        errorP.className = 'status-message error-message polling-error';
                        errorP.textContent = 'Network error while checking for match. Please refresh.';
                        container.appendChild(errorP);
                    }
                });
        }

        // Start polling if the user is waiting
        pollingInterval = setInterval(checkMatch, 5000); // Poll every 5 seconds
        // Optional: immediate check on page load for faster matching if already processed
        document.addEventListener('DOMContentLoaded', () => {
            checkMatch();
        });
    </script>
    <?php endif; ?>
</body>
</html>
