<?php
session_start();
include 'db.php';

$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    http_response_code(403);
    exit('Unauthorized');
}

$booking_id = intval($_GET['booking_id'] ?? 0); // ✅ use GET here

if ($booking_id > 0) {
    $stmt = $conn->prepare("DELETE FROM bookings WHERE id = ? AND user_id = ? AND status IN ('Rejected', 'Cancelled')");
    $stmt->bind_param("ii", $booking_id, $user_id);
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        // redirect back to page with success message
        header("Location: my_bookings.php?msg=Booking+cleared+successfully");
    } else {
        header("Location: my_bookings.php?msg=Failed+to+clear+booking");
    }

    $stmt->close();
} else {
    header("Location: my_bookings.php?msg=Invalid+booking+ID");
}
?>