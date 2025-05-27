<?php
// 1. Start the session
// This is necessary to access and manipulate the current session.
session_start();

// 2. Unset all session variables
// Setting $_SESSION to an empty array is a common and effective way.
$_SESSION = array();

// 3. Optionally, clear the session cookie
// This is an extra step for thorough cleanup, especially if session.use_cookies is enabled.
// It ensures the client-side cookie is also invalidated.
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, // Set expiration date in the past
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// 4. Destroy the session
// This invalidates the session on the server side.
session_destroy();

// 5. Redirect the user to the login page
// After logout, the user should be taken to a public page, typically the login page.
header("Location: login.php");
exit; // Ensure no further script execution after redirection
?>
