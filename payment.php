<?php
include 'db.php';

// Check booking ID
if (!isset($_GET['booking_id']) || empty($_GET['booking_id'])) {
    die("❌ No booking ID provided.");
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

if ($result->num_rows === 0) {
    die("❌ Booking not found.");
}

$booking = $result->fetch_assoc();

// Handle form submission
$message = "";
if(isset($_POST['pay'])) {
    $card_name = $_POST['card_name'];
    $card_number = $_POST['card_number'];
    $expiry = $_POST['expiry'];
    $cvv = $_POST['cvv'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];

    // Store only last 4 digits of card
    $card_last4 = substr($card_number, -4);

    // Update booking status
    $update_sql = "UPDATE bookings SET status='Confirmed' WHERE id=?";
    $update_stmt = $conn->prepare($update_sql);
    $update_stmt->bind_param("i", $booking_id);
    $update_stmt->execute();

    // Insert payment record into payments table
    $payment_sql = "INSERT INTO payments 
        (booking_id, user_id, payment_method, amount, payment_status, card_holder_name, card_last4) 
        VALUES (?, ?, ?, ?, ?, ?, ?)";
    $payment_stmt = $conn->prepare($payment_sql);

    $user_id = 1; // demo, replace with session user ID
    $payment_method = 'Card';
    $payment_status = 'Completed';
    $amount = $booking['total_price'];

    $payment_stmt->bind_param(
        "iisdsss", 
        $booking_id,
        $user_id,
        $payment_method,
        $amount,
        $payment_status,
        $card_name,
        $card_last4
    );

    $payment_stmt->execute();

    $message = "✅ Payment successful! Your booking is now confirmed.";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Payment - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
    body {
        background: #f9f9f9;
        font-family: 'Segoe UI', sans-serif;
    }
    .form-card {
        max-width: 550px;
        margin: 60px auto;
        padding: 30px;
        border: 1px solid #000;
        border-radius: 15px;
        box-shadow: 0 6px 15px rgba(0,0,0,0.1);
        background: #fff;
        position: relative;
    }
    .btn-pay {
        background: #ff6600;
        color: #fff;
        font-weight: bold;
        border-radius: 10px;
        transition: 0.3s;
    }
    .btn-pay:hover {
        background: #e65c00;
    }
    .card-logo {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 90px;
    }
    .form-card h3 {
        margin-bottom: 25px;
        color: #333;
        text-align: center;
    }
    .alert-success {
        font-weight: bold;
        text-align: center;
    }
    label {
        font-weight: 500;
    }
</style>
</head>
<body>

<!-- Navbar -->
<nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm">
  <div class="container">
    <a class="navbar-brand fw-bold text-dark" href="index.php">BoardBuddy</a>
    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
      <span class="navbar-toggler-icon"></span>
    </button>
    <div class="collapse navbar-collapse" id="navbarNav">
      <ul class="navbar-nav ms-auto">
        <li class="nav-item"><a class="nav-link" href="index.php">Home</a></li>
        <li class="nav-item"><a class="nav-link active" href="listings.php">Browse Listings</a></li>
        <li class="nav-item"><a class="nav-link" href="#">About</a></li>
        <li class="nav-item"><a class="nav-link" href="#">Contact</a></li>
      </ul>
      <a href="login.php" class="btn btn-warning ms-3">Login/Register</a>
    </div>
  </div>
</nav>

<!-- Payment Form -->
<div class="form-card">
    <img src="uploads/visa.jpg" alt="Visa/MasterCard" class="card-logo">
    <h3>Payment for <?php echo $booking['title']; ?></h3>
    <p class="text-center"><strong>Total:</strong> $<?php echo number_format($booking['total_price'],2); ?></p>

    <?php if(!empty($message)) echo "<div class='alert alert-success'>$message</div>"; ?>

    <form method="post">
        <div class="mb-3">
            <label for="card_name">Cardholder Name</label>
            <input type="text" class="form-control" id="card_name" name="card_name" placeholder="John Doe" required>
        </div>
        <div class="mb-3">
            <label for="card_number">Card Number</label>
            <input type="text" class="form-control" id="card_number" name="card_number" maxlength="16" placeholder="1234 5678 9012 3456" required>
        </div>
        <div class="mb-3 row">
            <div class="col">
                <label for="expiry">Expiry (MM/YY)</label>
                <input type="text" class="form-control" id="expiry" name="expiry" placeholder="MM/YY" required>
            </div>
            <div class="col">
                <label for="cvv">CVV</label>
                <input type="text" class="form-control" id="cvv" name="cvv" maxlength="4" placeholder="123" required>
            </div>
        </div>
        <div class="mb-3">
            <label for="email">Email</label>
            <input type="email" class="form-control" id="email" name="email" placeholder="example@email.com">
        </div>
        <div class="mb-3">
            <label for="phone">Phone</label>
            <input type="text" class="form-control" id="phone" name="phone" placeholder="+1 234 567 8901">
        </div>
        <button type="submit" name="pay" class="btn btn-pay w-100">Pay Now</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
