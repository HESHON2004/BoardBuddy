<?php
session_start();
include 'db.php';

// Require owner logged in
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Owner') {
    header("Location: userManagement/signin.php");
    exit;
}

$user_id = (int) $_SESSION['user_id'];

// Check property ID
if (!isset($_GET['id']) || empty($_GET['id'])) die("❌ No property ID provided.");
$id = intval($_GET['id']);

// Fetch property
$sql = "SELECT * FROM properties WHERE id = ? AND user_id = ?";
$stmt = $conn->prepare($sql);
if (!$stmt) die("Database error: " . $conn->error);
$stmt->bind_param("ii", $id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows === 0) die("❌ Property not found.");
$property = $result->fetch_assoc();
$facilities = !empty($property['facilities']) ? explode(",", $property['facilities']) : [];

$message = "";
if (isset($_POST['update_property'])) {
    $title = trim($_POST['title']);
    $price = trim($_POST['price']);
    $billing_cycle = $_POST['billing_cycle'];
    $payment_option = $_POST['payment_option'];
    $location = trim($_POST['location']);
    $description = trim($_POST['description']);
    $facilities_arr = isset($_POST['facilities']) ? $_POST['facilities'] : [];
    $facilities_str = implode(",", $facilities_arr);

    // Update property
    $update_sql = "UPDATE properties 
                   SET title=?, price=?, billing_cycle=?, payment_option=?, location=?, description=?, facilities=? 
                   WHERE id=? AND user_id=?";
    $update_stmt = $conn->prepare($update_sql);
    if (!$update_stmt) die("Update prepare error: " . $conn->error);
    $update_stmt->bind_param("sdssssssi", $title, $price, $billing_cycle, $payment_option, $location, $description, $facilities_str, $id, $user_id);

    if ($update_stmt->execute()) {

        // ✅ Delete selected photos
        if (!empty($_POST['delete_photos'])) {
            foreach ($_POST['delete_photos'] as $photo_id) {
                $photo_id = intval($photo_id);

                // Get filename before deleting
                $get_sql = "SELECT photo_path FROM property_photos WHERE id=? AND property_id=?";
                $get_stmt = $conn->prepare($get_sql);
                $get_stmt->bind_param("ii", $photo_id, $id);
                $get_stmt->execute();
                $get_res = $get_stmt->get_result();
                if ($get_res->num_rows > 0) {
                    $row = $get_res->fetch_assoc();
                    $filepath = "uploads/" . $row['photo_path'];
                    if (file_exists($filepath)) {
                        unlink($filepath); // delete from folder
                    }
                }
                $get_stmt->close();

                // Delete from DB
                $del_sql = "DELETE FROM property_photos WHERE id=? AND property_id=?";
                $del_stmt = $conn->prepare($del_sql);
                $del_stmt->bind_param("ii", $photo_id, $id);
                $del_stmt->execute();
                $del_stmt->close();
            }
        }

        // ✅ Upload new photos
        if (!empty($_FILES['photos']['name'][0])) {
            foreach ($_FILES['photos']['tmp_name'] as $index => $tmp_name) {
                if (!empty($_FILES['photos']['name'][$index])) {
                    $filename = time() . "_" . basename($_FILES['photos']['name'][$index]);
                    $target   = "uploads/" . $filename;

                    if (move_uploaded_file($tmp_name, $target)) {
                        $photo_sql = "INSERT INTO property_photos (property_id, photo_path) VALUES (?, ?)";
                        $photo_stmt = $conn->prepare($photo_sql);
                        if ($photo_stmt) {
                            // Store only filename
                            $photo_stmt->bind_param("is", $id, $filename);
                            $photo_stmt->execute();
                            $photo_stmt->close();
                        }
                    }
                }
            }
        }

        // ✅ Log activity
        $action = "Updated property '{$property['title']}' (ID: $id)";
        $log_stmt = $conn->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
        $log_stmt->bind_param("is", $user_id, $action);
        $log_stmt->execute();
        $log_stmt->close();

        // Redirect
        header("Location: myProperties.php?updated=1");
        exit();
    } else {
        $message = "❌ Error: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Edit Property - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles/navigation.css">
<style>
body { font-family: 'Segoe UI', sans-serif; background: #f5f5f5; }
.container { max-width: 850px; margin-top: 40px; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 6px 20px rgba(0,0,0,0.1); }
h2 { margin-bottom: 30px; text-align: center; }
.btn-update { background-color: #f97316; color: #fff; font-weight: bold; border: none; transition: 0.3s; }
.btn-update:hover { background-color: #ea580c; color: #fff; transform: translateY(-1px); }
.property-thumb { max-height: 150px; object-fit: cover; border-radius: 8px; margin-bottom: 8px; }
.photo-box { display: inline-block; margin: 8px; text-align: center; }
</style>
</head>
<body>
<?php include 'navigation/ownerNav.php'; ?>

<div class="container">
    <h2>Edit Property</h2>
    <?php if(!empty($message)) echo "<div class='alert alert-info'>$message</div>"; ?>

    <form method="post" enctype="multipart/form-data">
        <!-- Current images with checkboxes -->
        <div class="mb-3">
            <label class="form-label">Current Photos</label><br>
            <?php
            $photo_sql = "SELECT id, photo_path FROM property_photos WHERE property_id = ?";
            $photo_stmt = $conn->prepare($photo_sql);
            if ($photo_stmt) {
                $photo_stmt->bind_param("i", $id);
                $photo_stmt->execute();
                $photo_res = $photo_stmt->get_result();
                if ($photo_res && $photo_res->num_rows > 0) {
                    while ($photo_row = $photo_res->fetch_assoc()) {
                        $photo_id = $photo_row['id'];
                        $photo_path = "uploads/" . $photo_row['photo_path'];
                        echo "<div class='photo-box'>
                                <img src='" . htmlspecialchars($photo_path) . "' class='property-thumb'><br>
                                <input type='checkbox' name='delete_photos[]' value='$photo_id'> Delete
                              </div>";
                    }
                } else {
                    echo "<p class='text-muted'>No photos uploaded.</p>";
                }
                $photo_stmt->close();
            }
            ?>
        </div>

        <div class="mb-3">
            <label class="form-label">Title</label>
            <input type="text" name="title" class="form-control" value="<?php echo htmlspecialchars($property['title']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Price (Rs.)</label>
            <input type="number" name="price" class="form-control" value="<?php echo htmlspecialchars($property['price']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Billing Cycle</label>
            <select name="billing_cycle" class="form-control" required>
                <?php
                $cycles = ["Per Month", "Per Week", "Per Day"];
                foreach($cycles as $cycle) {
                    $selected = $property['billing_cycle'] === $cycle ? "selected" : "";
                    echo "<option value='$cycle' $selected>$cycle</option>";
                }
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Payment Option</label>
            <select name="payment_option" class="form-control" required>
                <?php
                $options = ["Cash", "Card"];
                foreach ($options as $opt) {
                    $selected = $property['payment_option'] === $opt ? "selected" : "";
                    echo "<option value='$opt' $selected>$opt</option>";
                }
                ?>
            </select>
        </div>
        <div class="mb-3">
            <label class="form-label">Location</label>
            <input type="text" name="location" class="form-control" value="<?php echo htmlspecialchars($property['location']); ?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Description</label>
            <textarea name="description" rows="4" class="form-control" required><?php echo htmlspecialchars($property['description']); ?></textarea>
        </div>
        <div class="mb-3">
            <label class="form-label">Facilities (comma separated)</label>
            <input type="text" name="facilities[]" class="form-control" value="<?php echo htmlspecialchars(implode(",", $facilities)); ?>" placeholder="e.g. WiFi, AC, Parking">
        </div>
        <div class="mb-3">
            <label class="form-label">Upload New Photos</label>
            <input type="file" name="photos[]" class="form-control" multiple>
        </div>

        <button type="submit" name="update_property" class="btn btn-update w-100">Update Property</button>
    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
