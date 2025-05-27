<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// If already logged in, redirect to homepage
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$username = '';
$email = '';
$full_name = '';
$phone_number = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $password_confirm = $_POST['password_confirm'] ?? '';
    $full_name = trim($_POST['full_name'] ?? '');
    $phone_number = trim($_POST['phone_number'] ?? '');

    // Basic Validation
    if (empty($username)) {
        $errors[] = "Username is required.";
    } elseif (!preg_match("/^[a-zA-Z0-9_]{3,20}$/", $username)) {
        $errors[] = "Username must be 3-20 characters, letters, numbers, or underscores only.";
    }

    if (empty($email)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    if (empty($password)) {
        $errors[] = "Password is required.";
    } elseif (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters long.";
    } elseif ($password !== $password_confirm) {
        $errors[] = "Passwords do not match.";
    }
    
    if (empty($full_name)) {
        $errors[] = "Full name is required.";
    }


    // If no validation errors, check database
    if (empty($errors)) {
        try {
            // Check if username or email already exists
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = :username OR email = :email LIMIT 1");
            $stmt->execute(['username' => $username, 'email' => $email]);
            if ($stmt->fetch()) {
                $errors[] = "Username or email already taken. Please choose another.";
            } else {
                // Hash password
                $password_hashed = password_hash($password, PASSWORD_DEFAULT);

                // Insert user
                $insert_stmt = $pdo->prepare("INSERT INTO users (username, email, password_hash, full_name, phone_number) VALUES (:username, :email, :password_hash, :full_name, :phone_number)");
                $insert_stmt->execute([
                    'username' => $username,
                    'email' => $email,
                    'password_hash' => $password_hashed,
                    'full_name' => $full_name,
                    'phone_number' => $phone_number
                ]);

                // Redirect to login page with a success message (or log them in directly)
                $_SESSION['success_message'] = "Registration successful! Please login.";
                redirect('login.php');
            }
        } catch (PDOException $e) {
            error_log("Registration Error: " . $e->getMessage());
            $errors[] = "An error occurred during registration. Please try again.";
            // In a production environment, you wouldn't show detailed SQL errors to the user.
            // $errors[] = "Database error: " . $e->getMessage(); // For debugging only
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <h2>Register</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo html_escape($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="register.php" method="post">
        <div>
            <label for="username">Username:</label>
            <input type="text" id="username" name="username" value="<?php echo html_escape($username); ?>" required>
        </div>
        <div>
            <label for="email">Email:</label>
            <input type="email" id="email" name="email" value="<?php echo html_escape($email); ?>" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <div>
            <label for="password_confirm">Confirm Password:</label>
            <input type="password" id="password_confirm" name="password_confirm" required>
        </div>
        <div>
            <label for="full_name">Full Name:</label>
            <input type="text" id="full_name" name="full_name" value="<?php echo html_escape($full_name); ?>" required>
        </div>
        <div>
            <label for="phone_number">Phone Number (Optional):</label>
            <input type="tel" id="phone_number" name="phone_number" value="<?php echo html_escape($phone_number); ?>">
        </div>
        <button type="submit">Register</button>
    </form>
    <p>Already have an account? <a href="login.php">Login here</a>.</p>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
