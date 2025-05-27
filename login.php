<?php
session_start();

// If user is already logged in, redirect to a main page (e.g., index.php or lobby.php)
if (isset($_SESSION['user_id'])) {
    header("Location: index.php"); // Assuming index.php will be the main/lobby page
    exit;
}

require_once 'config.php';

$errors = [];
$email = ''; // To repopulate the email field on error

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);
    $password = $_POST['password'];

    // Validation
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        // This specific error might not be shown if we use the generic one below,
        // but it's good for backend validation logging if needed.
        $errors[] = "Invalid email format.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    // If basic validation passes (fields are not empty)
    if (empty($errors)) {
        $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

        if ($conn->connect_error) {
            // For the user, show a generic error. Log the specific error for the admin.
            $errors[] = "Login failed. Please try again later.";
            // error_log("Database connection failed: " . $conn->connect_error); // Example logging
        } else {
            $stmt = $conn->prepare("SELECT id, email, password_hash FROM users WHERE email = ?");
            if (!$stmt) {
                $errors[] = "Login failed. Please try again later.";
                // error_log("Prepare statement failed: " . $conn->error);
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    if (password_verify($password, $user['password_hash'])) {
                        // Password is correct, start session
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['email'] = $user['email'];

                        // Regenerate session ID for security
                        session_regenerate_id(true);

                        header("Location: index.php"); // Redirect to main application page
                        exit;
                    } else {
                        // Incorrect password
                        $errors[] = "Invalid email or password.";
                    }
                } else {
                    // User not found
                    $errors[] = "Invalid email or password.";
                }
                $stmt->close();
            }
            $conn->close();
        }
    } elseif (in_array("Email is required.", $errors) || in_array("Password is required.", $errors)) {
        // If errors were due to empty fields, use a specific message
        // This overrides the more generic "Invalid email or password" for this specific case
        $errors = ["Please fill in all required fields."];
    } else {
        // For other validation errors like invalid email format, stick to generic
        $errors = ["Invalid email or password."];
    }


}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Login</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 400px; margin: auto; }
        .container { background: #f4f4f4; padding: 20px; border-radius: 5px; }
        label { display: block; margin-bottom: 5px; }
        input[type="email"], input[type="password"] {
            width: calc(100% - 22px);
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        input[type="submit"] {
            background-color: #007bff;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover { background-color: #0056b3; }
        .error-messages { color: red; margin-bottom: 15px; border: 1px solid red; padding: 10px; border-radius: 4px; background: #ffebeb;}
        .error-messages ul { padding-left: 20px; margin: 0; }
    </style>
</head>
<body>
    <div class="container">
        <h2>Login</h2>

        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form action="login.php" method="post">
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div>
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <input type="submit" value="Login">
            </div>
        </form>
        <p>Don't have an account? <a href="register.php">Register here</a></p>
    </div>
</body>
</html>
