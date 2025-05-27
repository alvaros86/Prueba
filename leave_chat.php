<?php
session_start();

// 1. Unset specific session variables related to the chat
unset($_SESSION['current_chat_id']);
unset($_SESSION['anonymous_name']);

// Optional: If you had a temporary flag for being in a chat queue or process
// unset($_SESSION['is_waiting_for_chat']); 

// 2. (Future consideration) Update chat status in the database
// For example, mark the chat as 'ended_by_user' or log the leave event.
// This would require database connection and queries.
// For this version, we are keeping it simple with session cleanup only.

// 3. Redirect to the main page (lobby)
header("Location: index.php");
exit; // Ensure no further script execution after redirection
?>
