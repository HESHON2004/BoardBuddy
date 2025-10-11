<?php
include 'db.php';

if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    die("❌ Booking ID not provided.");
}

$booking_id = intval($_GET['booking_id']);

// Fetch booking info
$sql = "SELECT b.*, p.title, p.price, p.billing_cycle
        FROM bookings b
        JOIN properties p ON b.property_id = p.id
        WHERE b.id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$result = $stmt->get_result();

if($result->num_rows === 0){
    die("❌ Booking not found.");
}

$booking = $result->fetch_assoc();

$message = "";

// Handle refund request submission
if(isset($_POST['refund'])){
    $reason = $_POST['reason'];

    // Insert into a refund_requests table
    $refund_stmt = $conn->prepare("INSERT INTO refund_requests (booking_id, user_id, reason, status, created_at) VALUES (?, ?, ?, 'Pending', NOW())");
    $user_id = 1; // Replace with logged-in user
    $refund_stmt->bind_param("iis", $booking_id, $user_id, $reason);

    if($refund_stmt->execute()){
        $message = "✅ Refund request submitted successfully! Admin will process it shortly.";
    } else {
        $message = "❌ Failed to submit refund request: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Request Refund - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container my-5" style="max-width:600px;">
    <h3>Request Refund for: <?php echo $booking['title']; ?></h3>
    <p><strong>Total Paid:</strong> Rs. <?php echo number_format($booking['total_price'],2); ?></p>
    <p><strong>Payment Method:</strong> <?php echo $booking['payment_method']; ?></p>

    <?php if($message) echo "<div class='alert alert-info'>$message</div>"; ?>

    <form method="post">
        <div class="mb-3">
            <label>Reason for Refund</label>
            <textarea class="form-control" name="reason" placeholder="Explain why you want a refund" required></textarea>
        </div>
        <button type="submit" name="refund" class="btn btn-info w-100">Submit Refund Request</button>
    </form>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
