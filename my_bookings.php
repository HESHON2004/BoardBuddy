<?php
session_start();
include 'db.php';

// Logged-in user
$user_id = $_SESSION['user_id'] ?? 0;
if (!$user_id) {
    header("Location: userManagement/signin.php");
    exit;
}

$msg = $_GET['msg'] ?? '';

// Fetch bookings for user and a single property photo
$sql = "
SELECT 
  b.id as booking_id,
  b.start_date,
  b.end_date,
  b.total_price,
  b.payment_method,
  b.status,
  p.id AS property_id,
  p.title,
  p.location,
  p.price,
  p.billing_cycle,
  (SELECT ph.photo_path FROM property_photos ph WHERE ph.property_id = p.id LIMIT 1) AS photo
FROM bookings b
JOIN properties p ON b.property_id = p.id
WHERE b.user_id = ?
ORDER BY b.id DESC
";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>My Bookings - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles/navigation.css">
<style>
/* Layout */
.container-main { max-width: 1200px; margin: 30px auto; }
.bookings-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 20px; }

/* Card */
.booking-card {
  border-radius: 12px;
  overflow: hidden;
  background:#fff;
  border:1px solid #eee;
  box-shadow:0 6px 18px rgba(0,0,0,0.04);
  display:flex;
  flex-direction:column;
  height:100%;
}
.booking-thumb {
  width:100%;
  height:180px;
  object-fit:cover;
  background:#f2f2f2;
  display:block;
}
.card-body { padding: 14px; flex:1; display:flex; flex-direction:column; }
.card-title { font-size:1.05rem; margin-bottom:6px; font-weight:600; }
.card-sub { color:#6b7280; font-size:0.9rem; margin-bottom:10px; }
.meta-row { display:flex; gap:10px; flex-wrap:wrap; margin-top:auto; align-items:center; }

/* Status badge */
.status-badge { padding: 6px 10px; border-radius: 8px; color: #fff; font-weight: 700; font-size: 0.85rem; display:inline-block; }
.status-pending { background: #f59e0b; }
.status-approved { background: #16a34a; }
.status-rejected { background: #dc2626; }
.status-cancelled { background: #6b7280; }

/* Buttons */
.btn-sm-outline {
  padding:6px 10px;
  border-radius:8px;
  border:1px solid #d1d5db;
  background:#fff;
  color:#111827;
  text-decoration:none;
  display:inline-block;
  font-size:0.9rem;
}
.btn-sm-outline:hover { background:#f3f4f6; border-color:#c7cbd0; }

/* Action group bottom */
.card-actions {
  padding: 12px 14px;
  border-top: 1px solid #f1f1f1;
  display:flex;
  gap:8px;
  justify-content:flex-end;
  background:#fafafa;
}

/* small helpers */
.price { font-weight:700; color:#e67e22; }
.small-muted { color:#6b7280; font-size:0.92rem; }
</style>
</head>
<body>
<?php include 'navigation/userNav.php'; ?>

<div class="container-main">
    <h2 class="mb-4">My Bookings</h2>

    <?php if (!empty($msg)): ?>
      <div class="alert alert-info"><?php echo htmlspecialchars($msg); ?></div>
    <?php endif; ?>

    <?php if($result->num_rows > 0): ?>
      <div class="bookings-grid">
        <?php while($row = $result->fetch_assoc()): 
            $photo = $row['photo'] ? 'uploads/' . htmlspecialchars($row['photo']) : 'https://via.placeholder.com/600x400?text=No+Image';
            $status = $row['status'];
            $status_class = match($status) {
                'Pending' => 'status-pending',
                'Approved' => 'status-approved',
                'Rejected' => 'status-rejected',
                'Cancelled' => 'status-cancelled',
                default => 'status-pending'
            };
        ?>
          <div class="booking-card">
            <img src="<?php echo $photo; ?>" alt="Property" class="booking-thumb">

            <div class="card-body">
              <div>
                <div class="card-title"><?php echo htmlspecialchars($row['title']); ?></div>
                <div class="card-sub"><?php echo htmlspecialchars($row['location']); ?></div>
                <div class="small-muted">Period: <?php echo htmlspecialchars($row['start_date'] . ' → ' . $row['end_date']); ?></div>
                <div style="margin-top:8px;">
                  <span class="price">Rs <?php echo number_format($row['total_price'], 2); ?></span>
                  <span class="small-muted"> · <?php echo htmlspecialchars($row['payment_method']); ?></span>
                </div>
              </div>

              <div class="meta-row" style="margin-top:12px">
                <span class="status-badge <?php echo $status_class; ?>"><?php echo htmlspecialchars($status); ?></span>
                <div class="small-muted" style="margin-left:auto">Billing: <?php echo strtolower(str_replace('Per ','',htmlspecialchars($row['billing_cycle']))); ?></div>
              </div>
            </div>

            <div class="card-actions">
              <?php if($status === 'Approved'): ?>
                <a class="btn-sm-outline" href="edit_info.php?booking_id=<?php echo $row['booking_id']; ?>">Edit</a>
                <a class="btn-sm-outline" href="request_refund.php?booking_id=<?php echo $row['booking_id']; ?>">Request Refund</a>
                <a class="btn-sm-outline" href="cancel_booking.php?booking_id=<?php echo $row['booking_id']; ?>" onclick="return confirm('Cancel this booking?');">Cancel</a>
              <?php elseif($status === 'Pending'): ?>
                <span class="small-muted" style="align-self:center">Waiting for owner approval</span>
              <?php elseif(in_array($status, ['Rejected','Cancelled'])): ?>
                <a class="btn-sm-outline" href="clear_booking.php?booking_id=<?php echo $row['booking_id']; ?>" onclick="return confirm('Remove this record from your bookings?');">Clear</a>
              <?php else: ?>
                <span class="small-muted">—</span>
              <?php endif; ?>
            </div>
          </div>
        <?php endwhile; ?>
      </div>
    <?php else: ?>
      <div class="alert alert-secondary">No bookings found.</div>
    <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>