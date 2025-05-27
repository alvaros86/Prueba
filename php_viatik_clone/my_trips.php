<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

if (!is_logged_in()) {
    $_SESSION['error_message'] = "You must be logged in to view your trips.";
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$offered_trips = [];
$booked_trips = [];
$errors = [];

// Action handling (e.g., cancel trip or booking)
$action_message = '';
$action_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['cancel_offered_trip']) && isset($_POST['trip_id'])) {
        $trip_to_cancel_id = filter_input(INPUT_POST, 'trip_id', FILTER_VALIDATE_INT);
        if ($trip_to_cancel_id) {
            try {
                $pdo->beginTransaction();
                // Check if user is the driver and trip is 'scheduled'
                $stmt = $pdo->prepare("SELECT id FROM trips WHERE id = :trip_id AND driver_id = :driver_id AND status = 'scheduled'");
                $stmt->execute([':trip_id' => $trip_to_cancel_id, ':driver_id' => $user_id]);
                if ($stmt->fetch()) {
                    // Set trip status to 'cancelled'
                    $update_trip_stmt = $pdo->prepare("UPDATE trips SET status = 'cancelled' WHERE id = :trip_id");
                    $update_trip_stmt->execute([':trip_id' => $trip_to_cancel_id]);

                    // Set related bookings to 'cancelled_by_driver'
                    $update_bookings_stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled_by_driver' WHERE trip_id = :trip_id AND status = 'confirmed'");
                    $update_bookings_stmt->execute([':trip_id' => $trip_to_cancel_id]);
                    
                    $pdo->commit();
                    $_SESSION['success_message'] = 'Trip successfully cancelled.';
                } else {
                    $pdo->rollBack();
                    $_SESSION['error_message'] = 'Could not cancel trip. It might not be yours or is not active.';
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Cancel Offered Trip Error: " . $e->getMessage());
                $_SESSION['error_message'] = 'Error cancelling trip: ' . $e->getMessage();
            }
            redirect('my_trips.php'); // Refresh page
        }
    } elseif (isset($_POST['cancel_booking']) && isset($_POST['booking_id'])) {
        $booking_to_cancel_id = filter_input(INPUT_POST, 'booking_id', FILTER_VALIDATE_INT);
        if ($booking_to_cancel_id) {
            try {
                $pdo->beginTransaction();
                // Check if user is the passenger and booking is 'confirmed'
                $stmt = $pdo->prepare("SELECT b.id, b.trip_id, b.seats_booked FROM bookings b WHERE b.id = :booking_id AND b.passenger_id = :passenger_id AND b.status = 'confirmed'");
                $stmt->execute([':booking_id' => $booking_to_cancel_id, ':passenger_id' => $user_id]);
                $booking = $stmt->fetch();

                if ($booking) {
                    // Set booking status to 'cancelled_by_passenger'
                    $update_booking_stmt = $pdo->prepare("UPDATE bookings SET status = 'cancelled_by_passenger' WHERE id = :booking_id");
                    $update_booking_stmt->execute([':booking_id' => $booking_to_cancel_id]);

                    // Increment available seats on the trip
                    $update_trip_stmt = $pdo->prepare("UPDATE trips SET available_seats = available_seats + :seats_booked WHERE id = :trip_id");
                    $update_trip_stmt->execute([':seats_booked' => $booking['seats_booked'], ':trip_id' => $booking['trip_id']]);
                    
                    $pdo->commit();
                    $_SESSION['success_message'] = 'Booking successfully cancelled.';
                } else {
                    $pdo->rollBack();
                    $_SESSION['error_message'] = 'Could not cancel booking. It might not be yours or is not active.';
                }
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Cancel Booking Error: " . $e->getMessage());
                $_SESSION['error_message'] = 'Error cancelling booking: ' . $e->getMessage();
            }
            redirect('my_trips.php'); // Refresh page
        }
    }
}


// Fetch offered trips
try {
    $stmt_offered = $pdo->prepare("SELECT * FROM trips WHERE driver_id = :user_id ORDER BY departure_time DESC");
    $stmt_offered->execute([':user_id' => $user_id]);
    $offered_trips = $stmt_offered->fetchAll();
} catch (PDOException $e) {
    error_log("My Offered Trips Error: " . $e->getMessage());
    $errors[] = "An error occurred while fetching your offered trips.";
}

// Fetch booked trips
try {
    $stmt_booked = $pdo->prepare(
        "SELECT b.*, t.origin, t.destination, t.departure_time, u.username AS driver_username 
         FROM bookings b
         JOIN trips t ON b.trip_id = t.id
         JOIN users u ON t.driver_id = u.id
         WHERE b.passenger_id = :user_id
         ORDER BY t.departure_time DESC"
    );
    $stmt_booked->execute([':user_id' => $user_id]);
    $booked_trips = $stmt_booked->fetchAll();
} catch (PDOException $e) {
    error_log("My Booked Trips Error: " . $e->getMessage());
    $errors[] = "An error occurred while fetching your booked trips.";
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <h2>My Trips</h2>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success"><p><?php echo html_escape($_SESSION['success_message']); ?></p></div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger"><p><?php echo html_escape($_SESSION['error_message']); ?></p></div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>
    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?><p><?php echo html_escape($error); ?></p><?php endforeach; ?>
        </div>
    <?php endif; ?>

    <section id="offered-trips">
        <h3>Trips I'm Offering</h3>
        <?php if (empty($offered_trips)): ?>
            <p>You haven't offered any trips yet. <a href="create_trip.php">Offer one now!</a></p>
        <?php else: ?>
            <div class="trip-list">
                <?php foreach ($offered_trips as $trip): ?>
                    <div class="trip-item offered-trip-item status-<?php echo html_escape($trip['status']); ?>">
                        <h4>From <?php echo html_escape($trip['origin']); ?> to <?php echo html_escape($trip['destination']); ?></h4>
                        <p><strong>Departure:</strong> <?php echo html_escape(date('D, M j, Y, g:i A', strtotime($trip['departure_time']))); ?></p>
                        <p><strong>Seats Available:</strong> <?php echo html_escape($trip['available_seats']); ?></p>
                        <p><strong>Fare:</strong> $<?php echo html_escape(number_format($trip['fare_per_seat'], 2)); ?></p>
                        <p><strong>Status:</strong> <?php echo html_escape(ucfirst($trip['status'])); ?></p>
                        <a href="trip_details.php?id=<?php echo html_escape($trip['id']); ?>" class="btn btn-info">View Details</a>
                        <?php if ($trip['status'] === 'scheduled' && strtotime($trip['departure_time']) > time()): ?>
                            <form action="my_trips.php" method="post" style="display: inline;">
                                <input type="hidden" name="trip_id" value="<?php echo html_escape($trip['id']); ?>">
                                <button type="submit" name="cancel_offered_trip" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this trip? This will also cancel active bookings.');">Cancel Trip</button>
                            </form>
                        <?php endif; ?>
                        <!-- TODO: Add link to view passengers for this trip -->
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section id="booked-trips" style="margin-top: 30px;">
        <h3>My Booked Rides</h3>
        <?php if (empty($booked_trips)): ?>
            <p>You haven't booked any trips yet. <a href="find_trip.php">Find one now!</a></p>
        <?php else: ?>
            <div class="trip-list">
                <?php foreach ($booked_trips as $booking): ?>
                    <div class="trip-item booked-trip-item status-<?php echo html_escape($booking['status']); ?>">
                        <h4>From <?php echo html_escape($booking['origin']); ?> to <?php echo html_escape($booking['destination']); ?></h4>
                        <p><strong>Driver:</strong> <?php echo html_escape($booking['driver_username']); ?></p>
                        <p><strong>Departure:</strong> <?php echo html_escape(date('D, M j, Y, g:i A', strtotime($booking['departure_time']))); ?></p>
                        <p><strong>Seats Booked:</strong> <?php echo html_escape($booking['seats_booked']); ?></p>
                        <p><strong>Booking Status:</strong> <?php echo html_escape(ucfirst(str_replace('_', ' ', $booking['status']))); ?></p>
                        <a href="trip_details.php?id=<?php echo html_escape($booking['trip_id']); ?>" class="btn btn-info">View Trip Details</a>
                        <?php if ($booking['status'] === 'confirmed' && strtotime($booking['departure_time']) > time()): ?>
                             <form action="my_trips.php" method="post" style="display: inline;">
                                <input type="hidden" name="booking_id" value="<?php echo html_escape($booking['id']); ?>">
                                <button type="submit" name="cancel_booking" class="btn btn-warning" onclick="return confirm('Are you sure you want to cancel this booking?');">Cancel Booking</button>
                            </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<style> /* Consider moving to a CSS file */
.alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
.alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
.alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
.trip-list { margin-top: 10px; }
.trip-item { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
.trip-item h4 { margin-top: 0; }
.status-scheduled { border-left: 5px solid #007bff; }
.status-completed { border-left: 5px solid #28a745; }
.status-cancelled, .status-cancelled_by_driver, .status-cancelled_by_passenger { border-left: 5px solid #dc3545; }
.status-confirmed { /* For bookings */ border-left: 5px solid #17a2b8; }

.btn { display: inline-block; padding: 8px 12px; color: white; text-decoration: none; border-radius: 4px; margin-top: 5px; margin-right: 5px; border: none; cursor: pointer; }
.btn-info { background-color: #17a2b8; } .btn-info:hover { background-color: #138496; }
.btn-danger { background-color: #dc3545; } .btn-danger:hover { background-color: #c82333; }
.btn-warning { background-color: #ffc107; color: #212529; } .btn-warning:hover { background-color: #e0a800; }
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
