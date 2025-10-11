<?php
session_start();
include 'db.php';

// Require owner logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Owner') {
    header("Location: userManagement/signin.php");
    exit;
}

$owner_id = (int) $_SESSION['user_id'];
$message = "";

// Handle deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete' && !empty($_POST['property_id'])) {
    $property_id = (int) $_POST['property_id'];

    // Verify owner owns the property and get the title for logging
    $chk = $conn->prepare("SELECT title FROM properties WHERE id = ? AND user_id = ?");
    if ($chk) {
        $chk->bind_param("ii", $property_id, $owner_id);
        $chk->execute();
        $chk_res = $chk->get_result();

        if ($chk_res && $chk_res->num_rows > 0) {
            $prop = $chk_res->fetch_assoc();
            $property_title = $prop['title'];

            // Delete photos files & rows
            $pstmt = $conn->prepare("SELECT photo_path FROM property_photos WHERE property_id = ?");
            if ($pstmt) {
                $pstmt->bind_param("i", $property_id);
                $pstmt->execute();
                $presult = $pstmt->get_result();
                while ($p = $presult->fetch_assoc()) {
                    $fp = __DIR__ . '/uploads/' . $p['photo_path'];
                    if (is_file($fp)) @unlink($fp);
                }
                $pstmt->close();
            }

            $delPhotos = $conn->prepare("DELETE FROM property_photos WHERE property_id = ?");
            if ($delPhotos) { 
                $delPhotos->bind_param("i", $property_id); 
                $delPhotos->execute(); 
                $delPhotos->close(); 
            }

            // Delete payments for bookings
            $bsel = $conn->prepare("SELECT id FROM bookings WHERE property_id = ?");
            if ($bsel) {
                $bsel->bind_param("i", $property_id);
                $bsel->execute();
                $bres = $bsel->get_result();
                while ($b = $bres->fetch_assoc()) {
                    $booking_id = (int)$b['id'];
                    $pdel = $conn->prepare("DELETE FROM payments WHERE booking_id = ?");
                    if ($pdel) { 
                        $pdel->bind_param("i", $booking_id); 
                        $pdel->execute(); 
                        $pdel->close(); 
                    }
                }
                $bsel->close();
            }

            // Delete bookings
            $delBookings = $conn->prepare("DELETE FROM bookings WHERE property_id = ?");
            if ($delBookings) { 
                $delBookings->bind_param("i", $property_id); 
                $delBookings->execute(); 
                $delBookings->close(); 
            }

            // Delete property
            $delProp = $conn->prepare("DELETE FROM properties WHERE id = ?");
            if ($delProp) { 
                $delProp->bind_param("i", $property_id); 
                $delProp->execute(); 
                $delProp->close(); 
            }

            // Log activity after deletion
            $action = "Deleted property '{$property_title}' (ID: $property_id)";
            $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
            if ($log_stmt) {
                $log_stmt->bind_param("is", $owner_id, $action);
                $log_stmt->execute();
                $log_stmt->close();
            }

            $message = "Property deleted successfully.";
        } else {
            $message = "You do not have permission to delete that property.";
        }

        $chk->close();
    } else {
        $message = "Database error: " . $conn->error;
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?message=" . urlencode($message));
    exit;
}

if (isset($_GET['message'])) {
    $message = $_GET['message'];
}

// Fetch properties
$sql = "
  SELECT p.id, p.title, p.location, p.price
  FROM properties p
  WHERE p.user_id = ?
  ORDER BY p.created_at DESC
";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Database prepare error: " . $conn->error);
$stmt->bind_param("i", $owner_id);
$stmt->execute();
$result = $stmt->get_result();
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>My Properties - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles/navigation.css">
<style>
.container-main { max-width: 1200px; margin: 30px auto; }
.property-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px; }
.property-card { border-radius: 12px; overflow: hidden; background:#fff; border:1px solid #eee; box-shadow:0 6px 18px rgba(0,0,0,0.05); display:flex; flex-direction:column; }
.property-thumb { width:100%; height:160px; object-fit:cover; background:#f2f2f2; }
.card-body { padding: 14px; flex:1; }
.card-footer { padding:10px 0; display:flex; justify-content:center; gap:10px; flex-wrap:wrap; }
.btn-sm-wide { padding:6px 12px; font-size:0.9rem; border-radius:8px; text-decoration:none; display:inline-block; text-align:center; border:1px solid #ccc; background:transparent; color:#333; transition:0.3s; }
.btn-sm-wide:hover { color:white; }
.btn-edit:hover { background:#0d6efd; border-color:#0d6efd; }
.btn-delete:hover { background:#dc3545; border-color:#dc3545; }
.btn-view:hover { background:#198754; border-color:#198754; }
.btn-inq:hover { background:#6f42c1; border-color:#6f42c1; }
.msg { margin-bottom: 16px; }
.carousel-indicators [data-bs-target] { background-color: #f97316; }
</style>
</head>
<body>
<?php include 'navigation/ownerNav.php'; ?>

<div class="container-main">
  <h2 class="mb-4">My Properties</h2>

  <?php if (!empty($message)): ?>
    <div class="alert alert-info msg"><?php echo htmlspecialchars($message); ?></div>
  <?php endif; ?>

  <?php if ($result && $result->num_rows > 0): ?>
    <div class="property-grid">
      <?php while ($row = $result->fetch_assoc()):
        // Fetch all photos for this property
        $photos_sql = "SELECT photo_path FROM property_photos WHERE property_id = ?";
        $photos_stmt = $conn->prepare($photos_sql);
        $photos = [];
        if ($photos_stmt) {
            $photos_stmt->bind_param("i", $row['id']);
            $photos_stmt->execute();
            $res_photos = $photos_stmt->get_result();
            while ($p = $res_photos->fetch_assoc()) {
                $photos[] = 'uploads/' . $p['photo_path'];
            }
            $photos_stmt->close();
        }
        if (empty($photos)) $photos[] = 'https://via.placeholder.com/600x400?text=No+Image';
      ?>
      <div class="property-card">
        <!-- Carousel -->
        <div id="carousel<?php echo $row['id']; ?>" class="carousel slide" data-bs-ride="carousel">
          <?php if(count($photos) > 1): ?>
          <div class="carousel-indicators">
            <?php foreach ($photos as $index => $p): ?>
              <button type="button" data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide-to="<?php echo $index; ?>" <?php echo $index === 0 ? 'class="active" aria-current="true"' : ''; ?> aria-label="Slide <?php echo $index+1; ?>"></button>
            <?php endforeach; ?>
          </div>
          <?php endif; ?>

          <div class="carousel-inner">
            <?php foreach ($photos as $index => $p): ?>
            <div class="carousel-item <?php echo $index === 0 ? 'active' : ''; ?>">
              <img src="<?php echo htmlspecialchars($p); ?>" class="d-block w-100 property-thumb" alt="Property Image">
            </div>
            <?php endforeach; ?>
          </div>

          <?php if(count($photos) > 1): ?>
          <button class="carousel-control-prev" type="button" data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide="prev">
            <span class="carousel-control-prev-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Previous</span>
          </button>
          <button class="carousel-control-next" type="button" data-bs-target="#carousel<?php echo $row['id']; ?>" data-bs-slide="next">
            <span class="carousel-control-next-icon" aria-hidden="true"></span>
            <span class="visually-hidden">Next</span>
          </button>
          <?php endif; ?>
        </div>

        <div class="card-body">
          <h5 class="mb-1" style="font-size:1.05rem;"><?php echo htmlspecialchars($row['title']); ?></h5>
          <p class="text-muted mb-1" style="font-size:0.9rem;"><?php echo htmlspecialchars($row['location']); ?></p>
          <p class="fw-bold mb-0">Rs. <?php echo number_format($row['price']); ?></p>
        </div>

        <div class="card-footer">
          <a href="edit_property.php?id=<?php echo $row['id']; ?>" class="btn-sm-wide btn-edit">Edit</a>

          <form method="post" style="margin:0;">
            <input type="hidden" name="property_id" value="<?php echo $row['id']; ?>">
            <input type="hidden" name="action" value="delete">
            <button type="submit" class="btn-sm-wide btn-delete" onclick="return confirm('Delete this property?');">Delete</button>
          </form>

          <a href="property.php?id=<?php echo $row['id']; ?>" class="btn-sm-wide btn-view">View</a>
          <a href="owner_inquiries.php?property_id=<?php echo $row['id']; ?>" class="btn-sm-wide btn-inq">Inquiries</a>
        </div>
      </div>
      <?php endwhile; ?>
    </div>
  <?php else: ?>
    <div class="alert alert-secondary">You have not added any properties yet.</div>
  <?php endif; ?>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
