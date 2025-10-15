<?php
session_start();
include 'db.php';
require_once __DIR__ . '/include/notify.php';

// Require owner logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Owner') {
    header("Location: userManagement/signin.php");
    exit;
}

$owner_id = (int) $_SESSION['user_id'];
$message = "";

// Get property_id from URL
if (!isset($_GET['property_id'])) {
    die("❌ Invalid property.");
}
$property_id = (int) $_GET['property_id'];

// Verify owner owns this property
$prop_stmt = $conn->prepare("SELECT * FROM properties WHERE id = ? AND user_id = ?");
$prop_stmt->bind_param("ii", $property_id, $owner_id);
$prop_stmt->execute();
$prop_result = $prop_stmt->get_result();
if ($prop_result->num_rows === 0) {
    die("❌ You do not have permission to view this property.");
}
$property = $prop_result->fetch_assoc();
$prop_stmt->close();

// Handle owner response submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['inquiry_id'], $_POST['response'])) {
    $inquiry_id = (int) $_POST['inquiry_id'];
    $response_text = trim($_POST['response']);

    if (!empty($response_text)) {
        $update_sql = "UPDATE inquiries SET response = ?, responded_at = NOW() WHERE id = ? AND owner_id = ?";
        $stmt = $conn->prepare($update_sql);
        if (!$stmt) die("Prepare failed: (" . $conn->errno . ") " . $conn->error);

        $stmt->bind_param("sii", $response_text, $inquiry_id, $owner_id);
        if ($stmt->execute()) {
            $message = "✅ Response sent successfully!";

            // 1️⃣ Get user details of this inquiry
            $inq_stmt = $conn->prepare("SELECT user_id, user_name FROM inquiries WHERE id = ?");
            $inq_stmt->bind_param("i", $inquiry_id);
            $inq_stmt->execute();
            $inq_res = $inq_stmt->get_result();
            $inq_data = $inq_res->fetch_assoc();
            $inq_stmt->close();

            if ($inq_data) {
                $user_id = (int)$inq_data['user_id'];
                $user_name = $inq_data['user_name'];
                $property_title = htmlspecialchars($property['title']);
                
                // Create notification for the user
                $notif_title = "Response to Your Inquiry";
                $notif_message = "Owner responded to your inquiry on \"$property_title\".";
                $link = "my_inquiries.php?inquiry_id=$inquiry_id"; // link to the property
                $type = "message"; // ✅ inquiry responses are message type
                createNotification($conn, $user_id, $notif_title, $notif_message, $link, $type);
            }

            // 3️⃣ Log activity
            $action = "Responded to inquiry ID $inquiry_id for property ID $property_id";
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
            $log_stmt->bind_param("is", $owner_id, $action);
            $log_stmt->execute();
            $log_stmt->close();
        } else {
            $message = "❌ Error: " . $conn->error;
        }
        $stmt->close();
    } else {
        $message = "❌ Response cannot be empty.";
    }
}

// Fetch inquiries for this specific property
$sql = "
    SELECT id, user_name, message, response, created_at, responded_at
    FROM inquiries
    WHERE owner_id = ? AND property_id = ?
    ORDER BY created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $owner_id, $property_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>Owner Inquiries - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles/navigation.css">
<style>
.container-main { max-width: 900px; margin: 30px auto; }
.inquiry-card { border-radius: 12px; background:#fff; padding: 15px; margin-bottom:15px; box-shadow:0 4px 12px rgba(0,0,0,0.05); }
.inquiry-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.inquiry-property { font-weight:600; font-size:1rem; }
.inquiry-user { font-size:0.9rem; color:#555; }
.inquiry-msg { margin:10px 0; }
textarea.form-control { resize:none; }
.btn-respond { background:#0d6efd; color:#fff; transition:0.3s; }
.btn-respond:hover { background:#0b5ed7; }
.alert-msg { margin-bottom: 20px; }
</style>
</head>
<body>
<?php include 'navigation/ownerNav.php'; ?>

<div class="container-main">
    <h2 class="mb-4">User Inquiries for "<?php echo htmlspecialchars($property['title']); ?>"</h2>

    <?php if (!empty($message)): ?>
        <div class="alert alert-info alert-msg"><?php echo htmlspecialchars($message); ?></div>
    <?php endif; ?>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="inquiry-card">
                <div class="inquiry-header">
                    <span class="inquiry-property"><?php echo htmlspecialchars($property['title']); ?></span>
                    <span class="inquiry-user"><?php echo htmlspecialchars($row['user_name']); ?> • <?php echo date("d M Y H:i", strtotime($row['created_at'])); ?></span>
                </div>
                <div class="inquiry-msg">
                    <strong>Message:</strong> <?php echo nl2br(htmlspecialchars($row['message'])); ?>
                </div>

                <?php if (!empty($row['response'])): ?>
                    <div class="inquiry-msg">
                        <strong>Your Response:</strong> <?php echo nl2br(htmlspecialchars($row['response'])); ?>
                        <br><small class="text-muted">Responded at: <?php echo date("d M Y H:i", strtotime($row['responded_at'])); ?></small>
                    </div>
                <?php else: ?>
                    <form method="post">
                        <input type="hidden" name="inquiry_id" value="<?php echo $row['id']; ?>">
                        <textarea name="response" class="form-control mb-2" rows="3" placeholder="Type your response here..." required></textarea>
                        <button type="submit" class="btn btn-respond">Send Response</button>
                    </form>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-secondary">No inquiries received yet for this property.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
