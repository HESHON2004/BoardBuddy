<?php
include 'db.php';

// Example user ID from session (replace with actual session)
$user_id = 1;

// Handle booking cancellation
if(isset($_GET['cancel']) && !empty($_GET['cancel'])) {
    $booking_id = intval($_GET['cancel']);

    // Fetch booking info first
    $stmt = $conn->prepare("SELECT * FROM bookings WHERE id=? AND user_id=?");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if($result->num_rows > 0) {
        // Delete payment record if exists
        $conn->query("DELETE FROM payments WHERE booking_id=$booking_id");

        // Delete booking record
        $conn->query("DELETE FROM bookings WHERE id=$booking_id");

        $message = "✅ Booking canceled and payment refunded successfully.";
    } else {
        $message = "❌ Booking not found or not yours.";
    }
}

// Fetch all bookings for the user
$sql = "SELECT b.*, p.title, p.location, p.price, p.billing_cycle
        FROM bookings b
        JOIN properties p ON b.property_id = p.id
        WHERE b.user_id = ?
        ORDER BY b.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Bookings - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { font-family: 'Segoe UI', sans-serif; background: white; margin: 0; }
.container { max-width: 1000px; margin-top: 50px; }
.booking-card { background: #fff; padding: 25px; border-radius: 12px; margin-bottom: 20px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
.btn-edit, .btn-cancel { font-weight: bold; border-radius: 6px; }
.btn-edit { background: #3498db; color: #fff; }
.btn-edit:hover { background: #2980b9; }
.btn-cancel { background: #e74c3c; color: #fff; }
.btn-cancel:hover { background: #c0392b; }
.alert { margin-top: 20px; }
</style>
</head>
<body>

<div class="container">
    <h2 class="mb-4 text-center">My Bookings</h2>

    <?php if(!empty($message)) echo "<div class='alert alert-info'>$message</div>"; ?>

    <?php
    if($result->num_rows > 0){
        while($booking = $result->fetch_assoc()){
            echo '<div class="booking-card">';
            echo '<h4>'.$booking['title'].'</h4>';
            echo '<p><strong>Location:</strong> '.$booking['location'].'</p>';
            echo '<p><strong>Dates:</strong> '.$booking['start_date'].' to '.$booking['end_date'].'</p>';
            echo '<p><strong>Total Price:</strong> Rs '.$booking['total_price'].' / '.$booking['billing_cycle'].'</p>';
            echo '<p><strong>Payment Method:</strong> '.$booking['payment_method'].'</p>';
            echo '<a href="edit_info.php?id='.$booking['id'].'" class="btn btn-edit me-2">Edit</a>';
            echo '<a href="?cancel='.$booking['id'].'" class="btn btn-cancel" onclick="return confirm(\'Are you sure you want to cancel this booking?\')">Cancel & Refund</a>';
            echo '</div>';
        }
    } else {
        echo "<p class='text-center'>You have no bookings.</p>";
    }
    ?>
</div>



<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
