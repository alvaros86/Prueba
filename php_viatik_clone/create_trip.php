<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

// If not logged in, redirect to login page
if (!is_logged_in()) {
    $_SESSION['error_message'] = "You must be logged in to offer a ride.";
    redirect('login.php');
}

$errors = [];
$origin = '';
$destination = '';
$departure_time_str = '';
$available_seats = '';
$fare_per_seat = '';
$trip_notes = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $origin = trim($_POST['origin'] ?? '');
    $destination = trim($_POST['destination'] ?? '');
    $departure_time_str = trim($_POST['departure_time'] ?? '');
    $available_seats = filter_input(INPUT_POST, 'available_seats', FILTER_VALIDATE_INT);
    $fare_per_seat = filter_input(INPUT_POST, 'fare_per_seat', FILTER_VALIDATE_FLOAT);
    $trip_notes = trim($_POST['trip_notes'] ?? '');
    $driver_id = $_SESSION['user_id'];

    // Basic Validation
    if (empty($origin)) {
        $errors[] = "Origin is required.";
    }
    if (empty($destination)) {
        $errors[] = "Destination is required.";
    }
    if (empty($departure_time_str)) {
        $errors[] = "Departure time is required.";
    } else {
        // Validate date format and ensure it's in the future
        $departure_timestamp = strtotime($departure_time_str);
        if ($departure_timestamp === false) {
            $errors[] = "Invalid departure time format. Use YYYY-MM-DD HH:MM:SS or similar.";
        } elseif ($departure_timestamp <= time()) {
            $errors[] = "Departure time must be in the future.";
        }
    }
    if ($available_seats === false || $available_seats <= 0) {
        $errors[] = "Available seats must be a positive number.";
    }
    if ($fare_per_seat === false || $fare_per_seat < 0) { // Allow 0 for free rides
        $errors[] = "Fare per seat must be a valid number (0 or more).";
    }
    
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO trips (driver_id, origin, destination, departure_time, available_seats, fare_per_seat, trip_notes, status) 
                    VALUES (:driver_id, :origin, :destination, :departure_time, :available_seats, :fare_per_seat, :trip_notes, :status)";
            $stmt = $pdo->prepare($sql);
            
            $formatted_departure_time = date('Y-m-d H:i:s', $departure_timestamp);

            $stmt->execute([
                ':driver_id' => $driver_id,
                ':origin' => $origin,
                ':destination' => $destination,
                ':departure_time' => $formatted_departure_time,
                ':available_seats' => $available_seats,
                ':fare_per_seat' => $fare_per_seat,
                ':trip_notes' => $trip_notes,
                ':status' => 'scheduled' 
            ]);

            $_SESSION['success_message'] = "Trip offered successfully!";
            redirect('my_trips.php'); // Redirect to a page where user can see their offered trips

        } catch (PDOException $e) {
            error_log("Create Trip Error: " . $e->getMessage());
            $errors[] = "An error occurred while offering the trip. Please try again. " . $e->getMessage();
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <h2>Offer a New Ride</h2>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo html_escape($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <?php
    // Display general error message from session if any (e.g., from redirect)
    if (isset($_SESSION['error_message'])) {
        echo '<div class="alert alert-danger"><p>' . html_escape($_SESSION['error_message']) . '</p></div>';
        unset($_SESSION['error_message']);
    }
    ?>

    <form action="create_trip.php" method="post">
        <div>
            <label for="origin">Origin:</label>
            <input type="text" id="origin" name="origin" value="<?php echo html_escape($origin); ?>" required>
        </div>
        <div>
            <label for="destination">Destination:</label>
            <input type="text" id="destination" name="destination" value="<?php echo html_escape($destination); ?>" required>
        </div>
        <div>
            <label for="departure_time">Departure Time:</label>
            <input type="datetime-local" id="departure_time" name="departure_time" value="<?php echo html_escape(str_replace(' ', 'T', $departure_time_str)); ?>" required>
            <small>Example: 2024-12-31T14:30</small>
        </div>
        <div>
            <label for="available_seats">Available Seats:</label>
            <input type="number" id="available_seats" name="available_seats" min="1" value="<?php echo html_escape($available_seats); ?>" required>
        </div>
        <div>
            <label for="fare_per_seat">Fare per Seat (<?php /* TODO: Add currency symbol */ ?>):</label>
            <input type="number" id="fare_per_seat" name="fare_per_seat" min="0" step="0.01" value="<?php echo html_escape($fare_per_seat); ?>" required>
        </div>
        <div>
            <label for="trip_notes">Trip Notes (Optional):</label>
            <textarea id="trip_notes" name="trip_notes" rows="3"><?php echo html_escape($trip_notes); ?></textarea>
        </div>
        <button type="submit">Offer Ride</button>
    </form>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
