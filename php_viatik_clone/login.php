<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// If already logged in, redirect to homepage
if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$login_identifier = ''; // Can be username or email

// Check for success message from registration
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']); // Clear message after displaying
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $login_identifier = trim($_POST['login_identifier'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($login_identifier)) {
        $errors[] = "Username or Email is required.";
    }
    if (empty($password)) {
        $errors[] = "Password is required.";
    }

    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("SELECT id, username, password_hash FROM users WHERE username = :login_identifier OR email = :login_identifier LIMIT 1");
            $stmt->execute(['login_identifier' => $login_identifier]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                // Password is correct, start session
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                // Regenerate session ID for security
                session_regenerate_id(true); 
                redirect('index.php');
            } else {
                $errors[] = "Invalid username/email or password.";
            }
        } catch (PDOException $e) {
            error_log("Login Error: " . $e->getMessage());
            $errors[] = "An error occurred during login. Please try again.";
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <h2>Login</h2>

    <?php if ($success_message): ?>
        <div class="alert alert-success">
            <p><?php echo html_escape($success_message); ?></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo html_escape($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form action="login.php" method="post">
        <div>
            <label for="login_identifier">Username or Email:</label>
            <input type="text" id="login_identifier" name="login_identifier" value="<?php echo html_escape($login_identifier); ?>" required>
        </div>
        <div>
            <label for="password">Password:</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">Login</button>
    </form>
    <p>Don't have an account? <a href="register.php">Register here</a>.</p>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
