<?php
session_start();
include 'db.php';
require_once __DIR__ . '/include/notify.php';

// Check if user is logged in
$is_logged_in = isset($_SESSION['user_id']);
$user_id      = $is_logged_in ? $_SESSION['user_id'] : null;
$user_role    = $_SESSION['user_role'] ?? 'User';

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

// Fetch total bookings (approved ones only)
$total_rooms = (int)($property['total_rooms'] ?? 0);
$rooms_available = (int)($property['rooms_available'] ?? 0);

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
body { font-family: 'Segoe UI', sans-serif; background: #f8f9fa; }
.property-img { width: 100%; height: 460px; object-fit: cover; border-radius: 16px; }
.details-card { background: #fff; border-radius: 16px; box-shadow: 0 4px 20px rgba(0,0,0,0.08); padding: 25px; }
.price { font-size: 1.9rem; font-weight: 700; color: #e67e22; }
.rooms { font-size: 1.1rem; font-weight: 600; color: #28a745; margin-top: 10px; }
.facility-badge { background: #f3f3f3; color: #333; margin: 4px; padding: 7px 12px; border-radius: 20px; font-size: 0.9rem; display: inline-block; }
.btn-book { background: #e67e22; color: white; font-weight: 600; border-radius: 8px; border: none; padding: 12px; transition: 0.3s; }
.btn-book:hover { background: #cf711f; }
.btn-book:hover:not(:disabled) {
  background-color: #b91c1c;
}

/* Disabled / Fully booked state */
.btn-book.fully-booked {
  background-color: #9ca3af; /* light gray */
  cursor: not-allowed;
  opacity: 0.8;
}
.sim-card { border-radius: 14px; overflow: hidden; border: 1px solid #eee; transition: 0.3s; background: #fff; }
.sim-card:hover { transform: translateY(-5px); box-shadow: 0 8px 20px rgba(0,0,0,0.15); }
.sim-img { width: 100%; height: 180px; object-fit: cover; }
.feedback-box { background: #fff; padding: 25px; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.08); margin-top: 40px; }
.thumbnail-square {
    width: 60px;
    height: 60px;
    cursor: pointer;
    border: 2px solid #ccc;
    border-radius: 6px;
    overflow: hidden;
}
.thumbnail-square.active {
    border: 2px solid #ff9800; /* brighter orange */
    box-shadow: 0 0 8px 2px rgba(255, 152, 0, 0.4); /* more visible glow */
}

.thumbnail-square img {
    transition: transform 0.3s;
}

.thumbnail-square:hover img {
    transform: scale(1.1);
}
/* Responsive improvement */
@media (max-width: 768px) {
  .property-img { height: 300px; }
  .details-card { margin-top: 20px; }
}
.btn-outline-secondary.custom {
    padding: 12px;
    font-weight: 600;
    border-radius: 8px;
}
</style>
</head>
<body>

<?php
if ($user_role === 'Owner') {
    include 'navigation/ownerNav.php';
} else {
    include 'navigation/userNav.php';
}
?>

<div class="container my-5">
  <div class="row g-4">
    <!-- Left: Photos with thumbnails -->
    <div class="col-md-7">
      <div id="propertyCarousel" class="carousel slide mb-3" data-bs-ride="carousel">
        <div class="carousel-inner">
          <?php foreach ($photos as $index => $photo): ?>
            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
              <img src="uploads/<?php echo htmlspecialchars($photo); ?>" class="d-block w-100 property-img" alt="">
            </div>
          <?php endforeach; ?>
        </div>

        <button class="carousel-control-prev" type="button" data-bs-target="#propertyCarousel" data-bs-slide="prev">
          <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#propertyCarousel" data-bs-slide="next">
          <span class="carousel-control-next-icon"></span>
        </button>
      </div>

      <!-- ✅ Thumbnails -->
      <div class="d-flex flex-wrap gap-2">
        <?php foreach ($photos as $index => $photo): ?>
          <div class="thumbnail-square">
            <img src="uploads/<?php echo htmlspecialchars($photo); ?>" 
                style="width:100%; height:100%; object-fit:cover;" 
                data-bs-target="#propertyCarousel" 
                data-bs-slide-to="<?php echo $index; ?>"
                alt="">
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Right: Details -->
    <div class="col-md-5">
      <div class="details-card">
        <h2><?php echo htmlspecialchars($property['title']); ?></h2>
        <p class="price">Rs. <?php echo htmlspecialchars($property['price']); ?> / 
           <?php echo strtolower(str_replace('Per ', '', htmlspecialchars($property['billing_cycle']))); ?>
        </p>

        <!-- ✅ Rooms Available -->
        <p class="rooms"><i class="fa-solid fa-door-open me-2"></i>
          Rooms Available: <?php echo $rooms_available; ?> / <?php echo $total_rooms; ?>
        </p>

        <p class="mt-3"><strong>Location:</strong> <?php echo htmlspecialchars($property['location']); ?></p>
        <p><strong>Payment:</strong> <?php echo htmlspecialchars($property['payment_option']); ?></p>

        <h5 class="mt-4">Facilities</h5>
        <?php foreach ($facilities as $f): ?>
          <span class="facility-badge"><?php echo htmlspecialchars($f); ?></span>
        <?php endforeach; ?>

        <?php if (!$is_owner_viewing): ?>
          <?php if ($is_logged_in): ?>
            <?php if ($rooms_available > 0): ?>
              <a href="book_property.php?id=<?php echo $property['id']; ?>" class="btn btn-book w-100 mt-4">Book Now</a>
            <?php else: ?>
              <button class="btn btn-book w-100 mt-4 fully-booked" disabled>Fully Booked</button>
            <?php endif; ?>
            <a href="inquiry.php?property_id=<?php echo $property['id']; ?>" class="btn btn-outline-secondary custom w-100 mt-3">Send Inquiry</a>
          <?php else: ?>
            <a href="userManagement/signin.php" class="btn btn-book w-100 mt-4">Login to Book</a>
            <a href="userManagement/signin.php" class="btn btn-outline-secondary w-100 mt-3">Login to Inquire</a>
          <?php endif; ?>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Description -->
  <div class="row mt-5">
    <div class="col-12">
      <h4>Description</h4>
      <p class="bg-white p-4 rounded shadow-sm"><?php echo nl2br(htmlspecialchars($property['description'])); ?></p>
    </div>
  </div>

  <!-- (Feedback and Similar Property sections unchanged for brevity) -->
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

const carousel = document.getElementById('propertyCarousel');
const thumbnails = document.querySelectorAll('.thumbnail-square img');

thumbnails.forEach((thumb, index) => {
    thumb.addEventListener('click', () => {
        thumbnails.forEach(t => t.parentElement.classList.remove('active'));
        thumb.parentElement.classList.add('active');
    });
});

carousel.addEventListener('slid.bs.carousel', function (e) {
    const activeIndex = e.to;
    thumbnails.forEach((thumb, idx) => {
        thumb.parentElement.classList.toggle('active', idx === activeIndex);
    });
});
</script>
</body>
</html>
