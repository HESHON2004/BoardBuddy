<?php
session_start();
include 'db.php';
require_once __DIR__ . '/include/notify.php'; // adjust the path if needed

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: userManagement/signin.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$user_name = $_SESSION['user_name'] ?? "A user";

// Check if property ID is provided
if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("❌ No property ID provided.");
}

$property_id = intval($_GET['id']);

// 🔍 Fetch property info
$sql = "SELECT * FROM properties WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $property_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    die("❌ Property not found.");
}

$property = $result->fetch_assoc();

// 💬 Handle form submission
$message = "";
// 💬 Handle form submission
if (isset($_POST['book'])) {
    $start_date = $_POST['start_date'];
    $end_date = $_POST['end_date'];
    $payment_method = $_POST['payment_method'];

    // Validate dates
    if (strtotime($end_date) < strtotime($start_date)) {
        $message = "❌ End date cannot be before start date.";
    } else {
        $days = (strtotime($end_date) - strtotime($start_date)) / (60 * 60 * 24);
        if ($days < 1) $days = 1;
        $total_price = $property['price'] * $days;

        //  Insert booking with Pending status
        $insert_sql = "INSERT INTO bookings (property_id, user_id, start_date, end_date, total_price, payment_method, status) 
                       VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iissds", $property_id, $user_id, $start_date, $end_date, $total_price, $payment_method);

        if ($insert_stmt->execute()) {
            $booking_id = $conn->insert_id;

            // Notify owner
            $owner_stmt = $conn->prepare("SELECT user_id FROM properties WHERE id = ?");
            $owner_stmt->bind_param("i", $property_id);
            $owner_stmt->execute();
            $owner_res = $owner_stmt->get_result();
            $owner_row = $owner_res->fetch_assoc();
            $owner_id = (int)$owner_row['user_id'];

            // Notification link should point to owner bookings page
            $notif_message = "$user_name booked your property \"{$property['title']}\".";
            $notif_link = "ownerBookings.php"; // owner page to approve/disapprove
            createNotification($conn, $owner_id, "New Booking", $notif_message, $notif_link, "booking");

            $message = "✅ Booking request sent! Total: Rs " . number_format($total_price, 2) . ". Waiting for owner approval.";

            // 💳 Optional: redirect to card payment if method is card
            if ($payment_method == 'Card') {
                header("Location: payment.php?booking_id=$booking_id");
                exit();
            }
        } else {
            $message = "❌ Booking failed: " . $conn->error;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Book <?php echo htmlspecialchars($property['title']); ?> - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles/navigation.css">
<style>
body { font-family: 'Segoe UI', sans-serif; background: white; margin: 0; }
.container { max-width: 700px; margin-top: 50px; }
.booking-card { background: #fff; padding: 35px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
h3 { font-weight: bold; margin-bottom: 20px; }
.property-info p { font-size: 16px; margin-bottom: 10px; }
.alert { margin-top: 15px; }
.btn-book { background: #e67e22; color: #fff; font-weight: bold; border-radius: 8px; transition: 0.3s; }
.btn-book:hover { background: #cf711f; }
</style>
</head>
<body>

<!-- Navbar -->
<?php include 'navigation/userNav.php'; ?>

<!-- Booking Form -->
<div class="container">
    <div class="booking-card">
        <h3>Book: <?php echo htmlspecialchars($property['title']); ?></h3>
        <div class="property-info">
            <p><strong>Location:</strong> <?php echo htmlspecialchars($property['location']); ?></p>
            <p><strong>Price:</strong> Rs <?php echo $property['price']; ?> / <?php echo strtolower(str_replace("Per ", "", $property['billing_cycle'])); ?></p>
        </div>

        <?php if (!empty($message)) echo "<div class='alert alert-info'>$message</div>"; ?>

        <form method="post">
            <div class="mb-3">
                <label for="start_date" class="form-label">Start Date</label>
                <input type="date" class="form-control" id="start_date" name="start_date" required>
            </div>
            <div class="mb-3">
                <label for="end_date" class="form-label">End Date</label>
                <input type="date" class="form-control" id="end_date" name="end_date" required>
            </div>
            <div class="mb-3">
                <label for="payment_method" class="form-label">Payment Method</label>
                <select class="form-select" id="payment_method" name="payment_method" required>
                    <?php
                    $owner_payment_option = $property['payment_option']; // "Cash" or "Card"
                    if ($owner_payment_option === 'Cash') {
                        echo '<option value="Cash" selected>Cash</option>';
                    } elseif ($owner_payment_option === 'Card') {
                        echo '<option value="Card" selected>Card</option>';
                    } else {
                        // fallback if none set
                        echo '<option value="Cash" selected>Cash on Arrival</option>';
                    }
                    ?>
                </select>
            </div>
            <button type="submit" name="book" class="btn btn-book w-100">Book Now</button>
        </form>
    </div>
</div>

<!-- Footer -->
<?php include 'navigation/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
