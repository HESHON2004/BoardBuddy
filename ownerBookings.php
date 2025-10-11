<?php
session_start();
include 'db.php';
require_once __DIR__ . '/include/notify.php';

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Owner') {
    header("Location: userManagement/signin.php");
    exit;
}

$owner_id = (int) $_SESSION['user_id'];
$message = "";

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && !empty($_POST['booking_id'])) {
    $booking_id = (int) $_POST['booking_id'];
    $action = $_POST['action'];

    $stmt = $conn->prepare("
        SELECT b.property_id, b.user_id, b.total_price, b.status, p.title 
        FROM bookings b 
        JOIN properties p ON b.property_id = p.id 
        WHERE b.id = ? AND p.user_id = ?
    ");
    $stmt->bind_param("ii", $booking_id, $owner_id);
    $stmt->execute();
    $res = $stmt->get_result();

    if ($res && $res->num_rows > 0) {
        $row = $res->fetch_assoc();
        $new_status = $action === 'approve' ? 'Approved' : 'Rejected';

        $update = $conn->prepare("UPDATE bookings SET status=? WHERE id=?");
        $update->bind_param("si", $new_status, $booking_id);
        $update->execute();
        $update->close();

        $notif_msg = $action === 'approve' 
            ? "Your booking for '{$row['title']}' has been approved." 
            : "Your booking for '{$row['title']}' has been rejected.";
        $link = "my_bookings.php";
        $type = "booking"; // Booking-related notification
        createNotification($conn, (int)$row['user_id'], "Booking Update", $notif_msg, $link, $type);

        $message = "Booking $new_status successfully.";
    } else {
        $message = "Booking not found or you don't have permission.";
    }
}

// Fetch owner properties with booking counts
$sql = "
SELECT p.id, p.title, p.location, p.price, p.billing_cycle,
       (SELECT COUNT(*) FROM bookings b WHERE b.property_id=p.id AND b.status='Pending') AS pending_requests
FROM properties p
WHERE p.user_id=?
ORDER BY p.created_at DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$properties = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Bookings - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles/navigation.css">
<style>
.container-main { max-width: 1200px; margin: 30px auto; }
.property-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.property-card { border-radius: 12px; overflow: hidden; background:#fff; border:1px solid #eee; box-shadow:0 6px 18px rgba(0,0,0,0.05); cursor:pointer; transition:0.3s; position:relative; }
.property-card:hover { transform:translateY(-3px); box-shadow:0 10px 20px rgba(0,0,0,0.15);}
.property-thumb { width:100%; height:180px; object-fit:cover; background:#f2f2f2; }
.card-body { padding: 12px; }
.pending-badge { position:absolute; top:10px; right:10px; background:#dc3545; color:#fff; border-radius:50%; width:35px; height:35px; display:flex; align-items:center; justify-content:center; font-weight:bold; font-size:0.9rem; }
.booking-list { padding:10px 15px; background:#f9f9f9; border-top:1px solid #eee; display:none; }
.booking-item { border-bottom:1px solid #ddd; padding:8px 0; }
.booking-item:last-child { border-bottom:none; }

/* Buttons with hover effect only */
.btn-approve, .btn-reject {
    background: #f0f0f0;
    color: #333;
    border: 1px solid #ccc;
    padding: 5px 12px;
    border-radius:6px;
    cursor:pointer;
    margin-right:5px;
    transition: all 0.3s ease;
}
.btn-approve:hover { background:#198754; color:white; border-color:#198754; }
.btn-reject:hover { background:#dc3545; color:white; border-color:#dc3545; }

.status-badge { padding: 5px 10px; border-radius: 6px; color: #fff; font-weight: bold; font-size: 0.85rem; }
.status-pending { background: #ffc107; }
.status-approved { background: #198754; }
.status-rejected { background: #dc3545; }
.status-cancelled { background: #6c757d; }
</style>
</head>
<body>
<?php include 'navigation/ownerNav.php'; ?>

<div class="container-main">
  <h2 class="mb-4">Bookings</h2>
  <?php if (!empty($message)): ?>
    <div class="alert alert-info"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <?php if ($properties->num_rows > 0): ?>
    <div class="property-grid">
      <?php while($p = $properties->fetch_assoc()):
        // Fetch first photo
        $photo_sql = "SELECT photo_path FROM property_photos WHERE property_id=? LIMIT 1";
        $photo_stmt = $conn->prepare($photo_sql);
        $photo_stmt->bind_param("i", $p['id']);
        $photo_stmt->execute();
        $res_photo = $photo_stmt->get_result();
        $photo = $res_photo && $res_photo->num_rows > 0 ? 'uploads/'.$res_photo->fetch_assoc()['photo_path'] : 'https://via.placeholder.com/600x400?text=No+Image';
        $photo_stmt->close();

        // Fetch all bookings except cancelled
        $bstmt = $conn->prepare("
          SELECT b.id, u.first_name, u.last_name, b.start_date, b.end_date, b.total_price, b.status
          FROM bookings b
          JOIN users u ON b.user_id=u.id
          WHERE b.property_id=? AND b.status IN ('Pending','Approved','Rejected')
          ORDER BY b.created_at DESC
        ");
        $bstmt->bind_param("i",$p['id']);
        $bstmt->execute();
        $bookings = $bstmt->get_result();
      ?>
      <div class="property-card">
        <img src="<?php echo htmlspecialchars($photo); ?>" class="property-thumb" alt="Property Image">
        <?php if($p['pending_requests'] > 0): ?>
          <div class="pending-badge"><?php echo $p['pending_requests']; ?></div>
        <?php endif; ?>
        <div class="card-body">
          <h5><?php echo htmlspecialchars($p['title']); ?></h5>
          <p class="text-muted mb-1"><?php echo htmlspecialchars($p['location']); ?></p>
          <p class="fw-bold mb-0">Rs <?php echo number_format($p['price']); ?> / <?php echo strtolower(str_replace("Per ","",$p['billing_cycle'])); ?></p>
        </div>

        <div class="booking-list">
          <?php if($bookings->num_rows > 0): ?>
            <?php while($b = $bookings->fetch_assoc()): ?>
              <div class="booking-item">
                <p><strong><?php echo htmlspecialchars($b['first_name'].' '.$b['last_name']); ?></strong></p>
                <p>From: <?php echo htmlspecialchars($b['start_date']); ?> To: <?php echo htmlspecialchars($b['end_date']); ?></p>
                <p>Total: Rs <?php echo number_format($b['total_price']); ?></p>
                <p>
                  <?php
                  $status_class = match($b['status']) {
                      'Pending' => 'status-pending',
                      'Approved' => 'status-approved',
                      'Rejected' => 'status-rejected',
                      'Cancelled' => 'status-cancelled',
                      default => 'status-pending'
                  };
                  echo "<span class='status-badge $status_class'>{$b['status']}</span>";
                  ?>
                </p>
                <?php if($b['status'] === 'Pending'): ?>
                  <form method="post" style="display:inline-block;">
                    <input type="hidden" name="booking_id" value="<?php echo $b['id']; ?>">
                    <button type="submit" name="action" value="approve" class="btn-approve">Approve</button>
                    <button type="submit" name="action" value="disapprove" class="btn-reject">Reject</button>
                  </form>
                <?php else: ?>
                  <span class="text-muted">Processed</span>
                <?php endif; ?>
              </div>
            <?php endwhile; ?>
          <?php else: ?>
            <p class="text-muted">No bookings.</p>
          <?php endif; ?>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-secondary">You have not added any properties yet.</div>
  <?php endif; ?>
</div>

<script>
document.querySelectorAll('.property-card').forEach(card => {
  card.addEventListener('click', function(e){
    if(e.target.tagName === 'BUTTON') return;
    const bookingList = this.querySelector('.booking-list');
    bookingList.style.display = (bookingList.style.display === 'block') ? 'none' : 'block';
  });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
