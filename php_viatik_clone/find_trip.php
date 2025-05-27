<?php
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

start_secure_session();

$search_origin = trim($_GET['origin'] ?? '');
$search_destination = trim($_GET['destination'] ?? '');

$trips = [];
$errors = [];

try {
    $sql = "SELECT t.id, t.origin, t.destination, t.departure_time, t.available_seats, t.fare_per_seat, u.username AS driver_username
            FROM trips t
            JOIN users u ON t.driver_id = u.id
            WHERE t.status = 'scheduled' AND t.departure_time > NOW()"; // Only show future scheduled trips

    $params = [];
    if (!empty($search_origin)) {
        $sql .= " AND t.origin LIKE :origin";
        $params[':origin'] = '%' . $search_origin . '%';
    }
    if (!empty($search_destination)) {
        $sql .= " AND t.destination LIKE :destination";
        $params[':destination'] = '%' . $search_destination . '%';
    }
    $sql .= " ORDER BY t.departure_time ASC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $trips = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log("Find Trip Error: " . $e->getMessage());
    $errors[] = "An error occurred while fetching trips. Please try again.";
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container">
    <h2>Find a Ride</h2>

    <form action="find_trip.php" method="get" class="search-form">
        <div>
            <label for="origin">Origin:</label>
            <input type="text" id="origin" name="origin" value="<?php echo html_escape($search_origin); ?>" placeholder="e.g., City A">
        </div>
        <div>
            <label for="destination">Destination:</label>
            <input type="text" id="destination" name="destination" value="<?php echo html_escape($search_destination); ?>" placeholder="e.g., City B">
        </div>
        <button type="submit">Search Trips</button>
    </form>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <p><?php echo html_escape($error); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success_message'])): ?>
        <div class="alert alert-success">
            <p><?php echo html_escape($_SESSION['success_message']); ?></p>
        </div>
        <?php unset($_SESSION['success_message']); ?>
    <?php endif; ?>
     <?php if (isset($_SESSION['error_message'])): ?>
        <div class="alert alert-danger">
            <p><?php echo html_escape($_SESSION['error_message']); ?></p>
        </div>
        <?php unset($_SESSION['error_message']); ?>
    <?php endif; ?>

    <h3>Available Trips</h3>
    <?php if (empty($trips) && empty($errors)): ?>
        <p>No trips found matching your criteria. Try broadening your search or check back later!</p>
    <?php elseif (!empty($trips)): ?>
        <div class="trip-list">
            <?php foreach ($trips as $trip): ?>
                <div class="trip-item">
                    <h4>From <?php echo html_escape($trip['origin']); ?> to <?php echo html_escape($trip['destination']); ?></h4>
                    <p><strong>Driver:</strong> <?php echo html_escape($trip['driver_username']); ?></p>
                    <p><strong>Departure:</strong> <?php echo html_escape(date('D, M j, Y, g:i A', strtotime($trip['departure_time']))); ?></p>
                    <p><strong>Seats Available:</strong> <?php echo html_escape($trip['available_seats']); ?></p>
                    <p><strong>Fare:</strong> $<?php echo html_escape(number_format($trip['fare_per_seat'], 2)); // Basic currency formatting ?></p>
                    <a href="trip_details.php?id=<?php echo html_escape($trip['id']); ?>" class="btn">View Details & Book</a>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

</div>

<style>
/* Basic styling for trip items - consider moving to a CSS file */
.search-form div { margin-bottom: 10px; }
.trip-list { margin-top: 20px; }
.trip-item { border: 1px solid #ddd; padding: 15px; margin-bottom: 15px; border-radius: 5px; }
.trip-item h4 { margin-top: 0; }
.trip-item .btn { 
    display: inline-block; 
    padding: 8px 12px; 
    background-color: #007bff; 
    color: white; 
    text-decoration: none; 
    border-radius: 4px;
    margin-top: 10px;
}
.trip-item .btn:hover { background-color: #0056b3; }
.alert { padding: 15px; margin-bottom: 20px; border: 1px solid transparent; border-radius: 4px; }
.alert-danger { color: #721c24; background-color: #f8d7da; border-color: #f5c6cb; }
.alert-success { color: #155724; background-color: #d4edda; border-color: #c3e6cb; }
</style>

<?php
require_once __DIR__ . '/includes/footer.php';
?>
