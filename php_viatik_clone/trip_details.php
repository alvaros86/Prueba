<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

$trip_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$trip = null;
$driver = null;
$errors = [];
$booking_errors = [];
$booking_success = '';

if (!$trip_id) {
    $_SESSION['error_message'] = "Invalid trip ID specified.";
    redirect('find_trip.php');
}

try {
    // Fetch trip details
    $stmt = $pdo->prepare(
        "SELECT t.*, u.username AS driver_username, u.email AS driver_email 
         FROM trips t 
         JOIN users u ON t.driver_id = u.id 
         WHERE t.id = :trip_id"
    );
    $stmt->execute([':trip_id' => $trip_id]);
    $trip = $stmt->fetch();

    if (!$trip) {
        $_SESSION['error_message'] = "Trip not found.";
        redirect('find_trip.php');
    }

    // Handle booking submission
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['book_seat'])) {
        if (!is_logged_in()) {
            $_SESSION['error_message'] = "You must be logged in to book a ride.";
            redirect('login.php?redirect_to=trip_details.php?id=' . $trip_id);
        }

        $passenger_id = $_SESSION['user_id'];
        $seats_to_book = 1; // For now, assume 1 seat per booking

        // Check if user is the driver
        if ($trip['driver_id'] == $passenger_id) {
            $booking_errors[] = "You cannot book your own trip.";
        }
        // Check if seats are available
        elseif ($trip['available_seats'] < $seats_to_book) {
            $booking_errors[] = "Sorry, no more seats available for this trip.";
        }
        // Check if already booked
        else {
            $check_booking_stmt = $pdo->prepare("SELECT id FROM bookings WHERE trip_id = :trip_id AND passenger_id = :passenger_id AND status = 'confirmed'");
            $check_booking_stmt->execute([':trip_id' => $trip_id, ':passenger_id' => $passenger_id]);
            if ($check_booking_stmt->fetch()) {
                $booking_errors[] = "You have already booked this trip.";
            }
        }
        
        if (empty($booking_errors)) {
            $pdo->beginTransaction();
            try {
                // Insert booking
                $insert_booking_sql = "INSERT INTO bookings (trip_id, passenger_id, seats_booked, status) VALUES (:trip_id, :passenger_id, :seats_booked, 'confirmed')";
                $insert_stmt = $pdo->prepare($insert_booking_sql);
                $insert_stmt->execute([
                    ':trip_id' => $trip_id,
                    ':passenger_id' => $passenger_id,
                    ':seats_booked' => $seats_to_book
                ]);

                // Decrement available seats
                $update_trip_sql = "UPDATE trips SET available_seats = available_seats - :seats_booked WHERE id = :trip_id";
                $update_stmt = $pdo->prepare($update_trip_sql);
                $update_stmt->execute([
                    ':seats_booked' => $seats_to_book,
                    ':trip_id' => $trip_id
                ]);
                
                $pdo->commit();
                $_SESSION['success_message'] = "Booking successful! Your seat is confirmed.";
                redirect('my_trips.php'); // Or back to this page with a success message

            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Booking Error: " . $e->getMessage());
                $booking_errors[] = "An error occurred while processing your booking. Please try again. " . $e->getMessage();
            }
        }
        // After processing POST, re-fetch trip data if booking was attempted to show updated seat count
        if(!empty($booking_errors) || !empty($booking_success)) { // Or if a booking was successful before redirect
             $stmt->execute([':trip_id' => $trip_id]); // Re-fetch
             $trip = $stmt->fetch();
        }
    }

} catch (PDOException $e) {
    error_log("Trip Details Error: " . $e->getMessage());
    $errors[] = "An error occurred while fetching trip details.";
    // No redirect here, show error on page if trip data couldn't be fetched initially but $trip_id was valid.
}


require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo html_escape($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($trip): ?>
        <h2>Trip Details</h2>
        <h3>From <?php echo html_escape($trip['origin']); ?> to <?php echo html_escape($trip['destination']); ?></h3>
        
        <p><strong>Driver:</strong> <?php echo html_escape($trip['driver_username']); ?></p>
        <p><strong>Driver Email (Contact for details):</strong> <?php echo html_escape($trip['driver_email']); ?></p>
        <p><strong>Departure Time:</strong> <?php echo html_escape(date('D, M j, Y, g:i A', strtotime($trip['departure_time']))); ?></p>
        <p><strong>Available Seats:</strong> <?php echo html_escape($trip['available_seats']); ?></p>
        <p><strong>Fare per Seat:</strong> $<?php echo html_escape(number_format($trip['fare_per_seat'], 2)); ?></p>
        <p><strong>Status:</strong> <?php echo html_escape(ucfirst($trip['status'])); ?></p>
        <?php if (!empty($trip['trip_notes'])): ?>
            <p><strong>Notes:</strong> <?php echo nl2br(html_escape($trip['trip_notes'])); ?></p>
        <?php endif; ?>

        <?php if (!empty($booking_errors)): ?>
            <div class="alert alert-danger" style="margin-top:15px;">
                <h4>Booking Failed:</h4>
                <?php foreach ($booking_errors as $error): ?>
                    <p><?php echo html_escape($error); ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php if ($booking_success): ?>
             <div class="alert alert-success" style="margin-top:15px;">
                <p><?php echo html_escape($booking_success); ?></p>
            </div>
        <?php endif; ?>
        

        <?php if (is_logged_in() && $trip['driver_id'] != $_SESSION['user_id'] && $trip['available_seats'] > 0 && $trip['status'] == 'scheduled' && strtotime($trip['departure_time']) > time()): ?>
            <form action="trip_details.php?id=<?php echo html_escape($trip_id); ?>" method="post" style="margin-top: 20px;">
                <input type="hidden" name="trip_id" value="<?php echo html_escape($trip_id); ?>">
                <!-- Can add input for number of seats if allowing multiple seats booking -->
                <button type="submit" name="book_seat" class="btn btn-success">Book 1 Seat</button>
            </form>
        <?php elseif (is_logged_in() && $trip['driver_id'] == $_SESSION['user_id']): ?>
            <p style="margin-top:15px;"><em>This is your trip. You can manage it from "My Trips".</em></p>
        <?php elseif ($trip['available_seats'] <= 0): ?>
            <p style="margin-top:15px;"><em>This trip is fully booked.</em></p>
        <?php elseif ($trip['status'] != 'scheduled' || strtotime($trip['departure_time']) <= time()): ?>
             <p style="margin-top:15px;"><em>This trip is no longer available for booking.</em></p>
        <?php elseif (!is_logged_in()): ?>
             <p style="margin-top:15px;"><a href="login.php?redirect_to=<?php echo urlencode('trip_details.php?id='.$trip_id); ?>">Login to book this trip</a></p>
        <?php endif; ?>

    <?php elseif (empty($errors)) : // Only show this if no other errors are set and trip is still null (should have been redirected) ?>
        <p>Trip details could not be loaded.</p>
    <?php endif; ?>
    
    <p style="margin-top: 20px;"><a href="find_trip.php">Back to Search Results</a></p>
</div>

<style> /* Consider moving to a CSS file */
.alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
.alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
.alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
.btn-success { background-color: #28a745; color: white; border: none; padding: 10px 15px; border-radius: 5px; cursor: pointer; }
.btn-success:hover { background-color: #218838; }
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
