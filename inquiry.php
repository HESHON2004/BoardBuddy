<?php
session_start();
require 'db.php';
require_once __DIR__ . '/include/notify.php'; // adjust path if needed

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: userManagement/signin.php");
    exit;
}

if (!isset($_GET['property_id'])) {
    die("❌ Invalid property.");
}

$property_id = intval($_GET['property_id']);
$user_id     = $_SESSION['user_id'];
$user_name   = $_SESSION['user_name'] ?? ""; // assuming you stored full name in loginHandler

$message = "";
$feedback = "";

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $message_text = trim($_POST['message']);

    if (!empty($message_text)) {
        // 1️⃣ Get property owner + title
        $property_stmt = $conn->prepare("SELECT user_id, title FROM properties WHERE id = ?");
        $property_stmt->bind_param("i", $property_id);
        $property_stmt->execute();
        $property_res = $property_stmt->get_result();

        if ($property_res && $property_res->num_rows > 0) {
            $property_row = $property_res->fetch_assoc();
            $owner_id = (int) $property_row['user_id'];
            $property_title = htmlspecialchars($property_row['title']);

            // 2️⃣ Insert inquiry
            $stmt = $conn->prepare("INSERT INTO inquiries (property_id, owner_id, user_id, user_name, message) VALUES (?, ?, ?, ?, ?)");
            $stmt->bind_param("iiiss", $property_id, $owner_id, $user_id, $user_name, $message_text);

            if ($stmt->execute()) {
                $inquiry_id = $stmt->insert_id; // ✅ get the new inquiry ID
                $feedback = "<div class='alert alert-success'>✅ Your inquiry has been sent successfully!</div>";

                // Log activity
                $action = "Sent inquiry for property ID $property_id";
                $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
                $log_stmt->bind_param("is", $user_id, $action);
                $log_stmt->execute();
                $log_stmt->close();

                // 🔔 Notify the property owner
                $notif_message = "$user_name sent you an inquiry about \"$property_title\".";
                $link = "property.php?id=$property_id&highlight_inquiry=$inquiry_id";
                createNotification($conn, $owner_id, "New Inquiry", $notif_message, $link);
            } else {
                $feedback = "<div class='alert alert-danger'>❌ Failed to send inquiry. Please try again.</div>";
            }

            $stmt->close();
        } else {
            $feedback = "<div class='alert alert-danger'>❌ Invalid property.</div>";
        }

        $property_stmt->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Send Inquiry - BoardBuddy</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
  <link rel="stylesheet" href="styles/navigation.css">
  <style>
    body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
    .inquiry-box { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 2px 12px rgba(0,0,0,0.1); margin-top: 50px; }
    .btn-send { background: #e67e22; color: white; font-weight: bold; border-radius: 8px; transition: 0.3s; }
    .btn-send:hover { background: #cf711f; color: white; }
  </style>
</head>
<body>

<?php include 'navigation/userNav.php'; ?>

<div class="container">
  <div class="row justify-content-center">
    <div class="col-md-8 col-lg-6">
      <div class="inquiry-box">
        <h3 class="mb-4"><i class="fa-regular fa-envelope"></i> Send Inquiry</h3>
        
        <?php echo $feedback; ?>

        <form method="post">
          <!-- Prefilled name -->
          <div class="mb-3">
            <label class="form-label">Your Name</label>
            <input type="text" class="form-control" value="<?php echo htmlspecialchars($user_name); ?>" readonly>
          </div>

          <!-- Inquiry message -->
          <div class="mb-3">
            <label class="form-label">Your Message</label>
            <textarea name="message" class="form-control" rows="5" placeholder="Write your inquiry..." required><?php echo htmlspecialchars($message); ?></textarea>
          </div>

          <button type="submit" class="btn btn-send w-100">Send Inquiry</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
