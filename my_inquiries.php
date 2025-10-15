<?php
session_start();
require 'db.php';

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: userManagement/signin.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];
$message = "";

// Fetch all inquiries submitted by this user, along with property info and owner responses
$sql = "
    SELECT i.id, i.property_id, i.message, i.response, i.created_at, i.responded_at, p.title AS property_title
    FROM inquiries i
    JOIN properties p ON i.property_id = p.id
    WHERE i.user_id = ?
    ORDER BY i.created_at DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>

<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Inquiries - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles/navigation.css">
<style>
.container-main { max-width: 900px; margin: 30px auto; }
.inquiry-card { 
    border-radius: 12px; 
    background:#fff; 
    padding: 15px; 
    margin-bottom:15px; 
    box-shadow:0 4px 12px rgba(0,0,0,0.05); 
    transition: box-shadow 0.3s, background-color 0.3s;
}
.inquiry-header { 
    display:flex; 
    justify-content:space-between; 
    align-items:center; 
    margin-bottom:10px; 
}
.inquiry-property { 
    font-weight:600; 
    font-size:1rem; 
}
.inquiry-msg { 
    margin:10px 0; 
}
.text-response { 
    background: #f0f0f0; 
    padding: 10px; 
    border-radius: 8px; 
}
.alert-msg { 
    margin-bottom: 20px; 
}
.inquiry-card.highlighted {
    background-color: #fff3cd;
    box-shadow: 0 0 10px rgba(255, 193, 7, 0.6);
}
</style>
</head>
<body>
<?php include 'navigation/userNav.php'; ?>

<div class="container-main">
    <h2 class="mb-4">My Inquiries & Responses</h2>

    <?php if ($result && $result->num_rows > 0): ?>
        <?php while ($row = $result->fetch_assoc()): ?>
            <div class="inquiry-card" id="inquiry-<?php echo $row['id']; ?>">
                <div class="inquiry-header">
                    <span class="inquiry-property"><?php echo htmlspecialchars($row['property_title']); ?></span>
                    <span class="text-muted"><?php echo date("d M Y H:i", strtotime($row['created_at'])); ?></span>
                </div>

                <div class="inquiry-msg">
                    <strong>Your Inquiry:</strong>
                    <p><?php echo nl2br(htmlspecialchars($row['message'])); ?></p>
                </div>

                <?php if (!empty($row['response'])): ?>
                    <div class="inquiry-msg text-response">
                        <strong>Owner Response:</strong>
                        <p><?php echo nl2br(htmlspecialchars($row['response'])); ?></p>
                        <small class="text-muted">Responded at: <?php echo date("d M Y H:i", strtotime($row['responded_at'])); ?></small>
                    </div>
                <?php else: ?>
                    <div class="inquiry-msg text-muted">Awaiting response from the owner.</div>
                <?php endif; ?>
            </div>
        <?php endwhile; ?>
    <?php else: ?>
        <div class="alert alert-secondary">You have not sent any inquiries yet.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    // Get inquiry_id from URL if available
    const params = new URLSearchParams(window.location.search);
    const inquiryId = params.get("inquiry_id");

    if (inquiryId) {
        const target = document.getElementById("inquiry-" + inquiryId);
        if (target) {
            // Scroll smoothly to the inquiry card
            target.scrollIntoView({ behavior: "smooth", block: "center" });

            // Add temporary highlight
            target.classList.add("highlighted");
            setTimeout(() => target.classList.remove("highlighted"), 3000);
        }
    }
});
</script>

</body>
</html>
