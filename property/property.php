<?php
session_start();
include 'db.php';
require_once __DIR__ . '/include/notify.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_id      = $is_logged_in ? $_SESSION['user_id'] : null;
$user_role    = $_SESSION['user_role'] ?? 'User'; // 'User' or 'Owner'

// Check property ID
if (!isset($_GET['id']) || empty($_GET['id'])) die("❌ No property ID provided.");
$id = intval($_GET['id']);

// Check for highlight parameter (for notification redirection)
$highlight_feedback = isset($_GET['highlight_feedback']) ? intval($_GET['highlight_feedback']) : null;

// Fetch property with photos
$sql = "SELECT p.*, GROUP_CONCAT(ph.photo_path) AS photos
        FROM properties p
        LEFT JOIN property_photos ph ON p.id = ph.property_id
        WHERE p.id = ?
        GROUP BY p.id";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("❌ Property not found.");
$property = $result->fetch_assoc();
$photos = !empty($property['photos']) ? explode(",", $property['photos']) : ['default.jpg'];
$facilities = !empty($property['facilities']) ? explode(",", $property['facilities']) : [];

// Check if owner is viewing their own property
$is_owner_viewing = ($user_role === 'Owner' && $property['user_id'] == $user_id);

// Handle feedback submission (only for users)
$feedback_message = "";
if ($is_logged_in && !$is_owner_viewing && isset($_POST['submit_feedback'])) {
    $comment = trim($_POST['comment']);
    if (!empty($comment)) {
        $insert_sql = "INSERT INTO feedback (property_id, user_id, comment) VALUES (?, ?, ?)";
        $insert_stmt = $conn->prepare($insert_sql);
        $insert_stmt->bind_param("iis", $id, $user_id, $comment);

        if ($insert_stmt->execute()) {
            $feedback_id = $insert_stmt->insert_id;
            $feedback_message = "✅ Thank you! Your feedback has been added.";

            // Log activity
            $action = "Added feedback on property ID $id";
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
            $log_stmt->bind_param("is", $user_id, $action);
            $log_stmt->execute();
            $log_stmt->close();

            // 🔔 Notify the property owner
            $owner_stmt = $conn->prepare("SELECT user_id FROM properties WHERE id = ?");
            $owner_stmt->bind_param("i", $id);
            $owner_stmt->execute();
            $owner_res = $owner_stmt->get_result();
            if ($owner_res && $owner_res->num_rows > 0) {
                $owner_row = $owner_res->fetch_assoc();
                $owner_id = (int) $owner_row['user_id'];

                $user_name = $_SESSION['user_name'] ?? "A user";
                $property_title = $property['title'];

                $notif_message = "$user_name left feedback on your property: \"$property_title\".";
                $link = "property.php?id=$id&highlight_feedback=$feedback_id"; // ✅ highlight specific feedback
                $type = "message"; // Feedback notifications
                createNotification($conn, $owner_id, "New Feedback", $notif_message, $link, $type);
            }
            $owner_stmt->close();
        } else {
            $feedback_message = "❌ Error: " . $conn->error;
        }
    } else {
        $feedback_message = "⚠️ Please enter feedback before submitting.";
    }
}

// Fetch feedback
$feedback_sql = "
  SELECT f.id, f.comment, f.created_at, u.first_name, u.last_name
  FROM feedback f
  JOIN users u ON f.user_id = u.id
  WHERE f.property_id = ?
  ORDER BY f.created_at DESC
";
$feedback_stmt = $conn->prepare($feedback_sql);
$feedback_stmt->bind_param("i", $id);
$feedback_stmt->execute();
$feedback_result = $feedback_stmt->get_result();

// Fetch similar properties
$sim_sql = "SELECT p.*, ph.photo_path FROM properties p 
            LEFT JOIN property_photos ph ON p.id = ph.property_id
            WHERE p.id != ? 
            GROUP BY p.id LIMIT 3";
$sim_stmt = $conn->prepare($sim_sql);
$sim_stmt->bind_param("i", $id);
$sim_stmt->execute();
$sim_result = $sim_stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title><?php echo htmlspecialchars($property['title']); ?> - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
<link rel="stylesheet" href="styles/navigation.css">
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
.property-img { width: 100%; height: 450px; object-fit: cover; border-radius: 12px; }
.price { font-size: 1.8rem; font-weight: bold; color: #e67e22; }
.btn-book { background: transparent; color: #e67e22; font-weight: bold; border-radius: 8px; border: 2px solid #e67e22; transition: 0.3s; }
.btn-book:hover { background: #e67e22; color: white; }
.facility-badge { background: #f0f0f0; color: #333; margin: 3px; padding: 6px 10px; border-radius: 20px; display: inline-block; font-size: 0.9rem; }
.sim-card { border-radius: 12px; overflow: hidden; transition: 0.3s; border: 1px solid #ddd; }
.sim-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.sim-img { width: 100%; height: 180px; object-fit: cover; }
.carousel-indicators [data-bs-target] { width: 60px; height: 60px; border-radius: 8px; overflow: hidden; border: 2px solid #e67e22; cursor: pointer; }
.carousel-indicators img { width: 100%; height: 100%; object-fit: cover; }
.feedback-box { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); margin-top: 30px; }
.feedback-item { border-bottom: 1px solid #eee; padding: 15px 0; transition: background-color 0.4s ease; }
.feedback-item:last-child { border-bottom: none; }
.feedback-user { font-weight: bold; color: #e67e22; }
.feedback-date { font-size: 0.9rem; color: #777; }

/* Highlighted feedback animation */
.highlight-feedback {
  background-color: #fff8e1;
  border: 2px solid #ff9800;
  box-shadow: 0 0 12px rgba(255, 152, 0, 0.5);
  animation: glow 1.8s ease-in-out;
}
@keyframes glow {
  0% { box-shadow: 0 0 20px rgba(255, 152, 0, 0.8); }
  100% { box-shadow: 0 0 0 rgba(255, 152, 0, 0); }
}
</style>
</head>
<body>

<!-- Navbar -->
<?php
if ($user_role === 'Owner') {
    include 'navigation/ownerNav.php';
} else {
    include 'navigation/userNav.php';
}
?>

<!-- Property Content -->
<div class="container my-5">
  <div class="row g-4">
    <!-- Carousel -->
    <div class="col-md-7">
      <div id="propertyCarousel" class="carousel slide" data-bs-ride="carousel">
        <div class="carousel-inner">
          <?php foreach($photos as $index => $photo): ?>
            <div class="carousel-item <?php echo $index===0?'active':'';?>">
              <img src="uploads/<?php echo htmlspecialchars($photo);?>" class="d-block w-100 property-img" alt="Photo <?php echo $index+1;?>">
            </div>
          <?php endforeach; ?>
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#propertyCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#propertyCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon"></span>
        </button>
        <div class="carousel-indicators mt-3">
          <?php foreach($photos as $index => $photo): ?>
            <button type="button" data-bs-target="#propertyCarousel" data-bs-slide-to="<?php echo $index;?>" class="<?php echo $index===0?'active':'';?>">
              <img src="uploads/<?php echo htmlspecialchars($photo);?>" alt="">
            </button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Details -->
    <div class="col-md-5">
      <h2><?php echo htmlspecialchars($property['title']);?></h2>
      <p class="price">Rs. <?php echo htmlspecialchars($property['price']);?> / <?php echo strtolower(str_replace("Per ","",htmlspecialchars($property['billing_cycle'])));?></p>
      <p><strong>Payment:</strong> <?php echo htmlspecialchars($property['payment_option']);?></p>
      <p><strong>Location:</strong> <?php echo htmlspecialchars($property['location']);?></p>

      <h5 class="mt-3">Facilities</h5>
      <?php foreach($facilities as $f): ?>
        <span class="facility-badge"><?php echo htmlspecialchars($f);?></span>
      <?php endforeach; ?>

      <!-- Only for users -->
      <?php if (!$is_owner_viewing): ?>
        <?php if($is_logged_in): ?>
          <a href="book_property.php?id=<?php echo $property['id'];?>" class="btn btn-book btn-lg mt-4 w-100">Book Now</a>
          <a href="inquiry.php?property_id=<?php echo $property['id'];?>" class="btn btn-book btn-lg mt-3 w-100">Send Inquiry</a>
        <?php else: ?>
          <a href="userManagement/signin.php" class="btn btn-book btn-lg mt-4 w-100">Login to Book</a>
          <a href="userManagement/signin.php" class="btn btn-book btn-lg mt-3 w-100">Login to Send Inquiry</a>
        <?php endif; ?>
      <?php endif; ?>
    </div>
  </div>

  <!-- Description -->
  <div class="row mt-5">
    <div class="col-12">
      <h4>Description</h4>
      <p><?php echo nl2br(htmlspecialchars($property['description']));?></p>
    </div>
  </div>

  <!-- Feedback Form -->
  <?php if (!$is_owner_viewing): ?>
    <div class="feedback-box mt-5">
      <h4 class="mb-3">Leave Your Feedback</h4>
      <?php if(!empty($feedback_message)) echo "<div class='alert alert-info'>$feedback_message</div>"; ?>
      <?php if($is_logged_in): ?>
        <form method="post">
          <div class="mb-3">
            <textarea class="form-control" name="comment" rows="3" placeholder="Write your feedback..." required></textarea>
          </div>
          <button type="submit" name="submit_feedback" class="btn btn-warning">Submit Feedback</button>
        </form>
      <?php else: ?>
        <p class="text-muted">⚠️ You must <a href="userManagement/signin.php">login</a> to leave feedback.</p>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- Feedback List -->
  <div class="feedback-box mt-4">
    <h4 class="mb-3">User Feedback</h4>
    <?php if($feedback_result->num_rows > 0): ?>
      <?php while($fb = $feedback_result->fetch_assoc()): ?>
        <div id="feedback-<?php echo $fb['id']; ?>" 
             class="feedback-item <?php echo ($highlight_feedback === (int)$fb['id']) ? 'highlight-feedback' : ''; ?>">
          <div class="d-flex justify-content-between">
            <span class="feedback-user">
              <?php echo htmlspecialchars($fb['first_name'] . ' ' . $fb['last_name']); ?>
            </span>
            <span class="feedback-date">
              <?php echo date("M d, Y", strtotime($fb['created_at'])); ?>
            </span>
          </div>
          <p class="mt-2"><?php echo nl2br(htmlspecialchars($fb['comment'])); ?></p>
        </div>
      <?php endwhile; ?>
    <?php else: ?>
      <p class="text-muted">No feedback yet. Be the first to leave one!</p>
    <?php endif; ?>
  </div>

  <!-- Similar Properties -->
  <div class="row mt-5">
    <div class="col-12">
      <h4>Similar Properties</h4>
      <div class="row g-4">
        <?php while($sim = $sim_result->fetch_assoc()):
          $sim_photo = !empty($sim['photo_path']) ? 'uploads/'.htmlspecialchars($sim['photo_path']) : 'uploads/default.jpg'; ?>
          <div class="col-md-4">
            <div class="sim-card">
              <img src="<?php echo $sim_photo;?>" class="sim-img" alt="<?php echo htmlspecialchars($sim['title']);?>">
              <div class="p-3">
                <h5><?php echo htmlspecialchars($sim['title']);?></h5>
                <p class="price">Rs. <?php echo htmlspecialchars($sim['price']);?></p>
                <a href="property.php?id=<?php echo $sim['id'];?>" class="btn btn-book w-100 mt-2">View Property</a>
              </div>
            </div>
          </div>
        <?php endwhile;?>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>

<!-- Smooth scroll to highlighted feedback -->
<script>
document.addEventListener("DOMContentLoaded", function () {
  const urlParams = new URLSearchParams(window.location.search);
  const highlightId = urlParams.get('highlight_feedback');

  if (highlightId) {
    const feedbackItem = document.getElementById('feedback-' + highlightId);
    if (feedbackItem) {
      feedbackItem.classList.add('highlight-feedback');
      feedbackItem.scrollIntoView({ behavior: "smooth", block: "center" });

      // Remove highlight on any click outside the item
      document.addEventListener("click", function removeHighlight(e) {
        if (!feedbackItem.contains(e.target)) {
          feedbackItem.classList.remove('highlight-feedback');
          document.removeEventListener("click", removeHighlight);
        }
      });
    }
  }
});
</script>
</body>
</html>