<?php
session_start(); // Optional: if you plan to use sessions for login later

require_once 'config.php';

$errors = [];
$success_message = '';
$email = ''; // To repopulate the email field on error

// Check if the form is submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get form data
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // --- Validation ---
    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long.";
    }

    if (empty($confirm_password)) {
        $errors[] = "Confirm password is required.";
    } elseif ($password !== $confirm_password) {
        $errors[] = "Passwords do not match.";
    }

    // --- If validation passes, proceed to database interaction ---
    if (empty($errors)) {
        // Establish database connection
        $conn = new mysqli(DB_SERVER, DB_USERNAME, DB_PASSWORD, DB_NAME);

        // Check connection
        if ($conn->connect_error) {
            $errors[] = "Connection failed: " . $conn->connect_error;
        } else {
            // Check if email already exists
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            if (!$stmt) {
                $errors[] = "Database error (prepare select): " . $conn->error;
            } else {
                $stmt->bind_param("s", $email);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows > 0) {
                    $errors[] = "This email address is already registered.";
                } else {
                    // Email is new, hash the password
                    $password_hash = password_hash($password, PASSWORD_DEFAULT);

                    // Prepare and execute INSERT statement
                    $stmt_insert = $conn->prepare("INSERT INTO users (email, password_hash) VALUES (?, ?)");
                    if (!$stmt_insert){
                        $errors[] = "Database error (prepare insert): " . $conn->error;
                    } else {
                        $stmt_insert->bind_param("ss", $email, $password_hash);

                        if ($stmt_insert->execute()) {
                            $success_message = "Registration successful. You can now <a href='login.php'>log in</a>.";
                            $email = ''; // Clear email field on success
                        } else {
                            $errors[] = "Registration failed. Please try again later. Error: " . $stmt_insert->error;
                        }
                        $stmt_insert->close();
                    }
                }
                $stmt->close();
            }
            $conn->close();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Registration</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; max-width: 500px; margin: auto; }
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
            background-color: #5cb85c;
            color: white;
            padding: 10px 15px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        input[type="submit"]:hover { background-color: #4cae4c; }
        .error-messages { color: red; margin-bottom: 15px; border: 1px solid red; padding: 10px; border-radius: 4px; background: #ffebeb;}
        .error-messages ul { padding-left: 20px; margin: 0; }
        .success-message { color: green; margin-bottom: 15px; border: 1px solid green; padding: 10px; border-radius: 4px; background: #e6ffe6;}
    </style>
</head>
<body>
    <div class="container">
        <h2>Register New Account</h2>

        <?php if (!empty($errors)): ?>
            <div class="error-messages">
                <strong>Please correct the following errors:</strong>
                <ul>
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                <?php echo $success_message; // HTML is allowed here for the login link ?>
            </div>
        <?php endif; ?>

        <?php if (empty($success_message)): // Hide form on success ?>
        <form action="register.php" method="post">
            <div>
                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" required>
            </div>
            <div>
                <label for="password">Password (min 8 characters):</label>
                <input type="password" id="password" name="password" required>
            </div>
            <div>
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
            </div>
            <div>
                <input type="submit" value="Register">
            </div>
        </form>
        <p>Already have an account? <a href="login.php">Login here</a></p>
        <?php endif; ?>
    </div>
</body>
</html>
