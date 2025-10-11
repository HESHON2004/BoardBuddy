<?php
include 'db.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$message = "";

// Ensure only logged-in owners can add properties
if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'Owner') {
    header("Location: userManagement/signin.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $location = $_POST['location'];
    $billing_cycle = $_POST['billing_cycle'] ?? '';
    $payment_option = $_POST['payment'] ?? '';
    $price = $_POST['price'];

    $ownerId = $_SESSION['user_id']; // ✅ Link property to logged-in owner

    // Combine selected facilities and 'other'
    $facilities = !empty($_POST['facilities']) ? implode(',', $_POST['facilities']) : '';
    if (!empty($_POST['facility_other'])) {
        $facilities .= ($facilities ? ',' : '') . $_POST['facility_other'];
    }

    // Handle photo upload
    $uploaded_photos = [];
    if (!empty($_FILES['photos']['name'][0])) {
        $total_files = count($_FILES['photos']['name']);
        for ($i = 0; $i < $total_files; $i++) {
            $tmp_name = $_FILES['photos']['tmp_name'][$i];
            $filename = time() . '_' . $_FILES['photos']['name'][$i];
            $destination = 'uploads/' . $filename;

            if (move_uploaded_file($tmp_name, $destination)) {
                $uploaded_photos[] = $filename;
            }
        }
    }

    // ✅ Insert into properties table with user_id
    $stmt = $conn->prepare("INSERT INTO properties 
        (title, description, location, billing_cycle, payment_option, price, facilities, user_id, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())");
    $stmt->bind_param("sssssisi", $title, $description, $location, $billing_cycle, $payment_option, $price, $facilities, $ownerId);

    if ($stmt->execute()) {
        $property_id = $stmt->insert_id;

        // Insert photos into property_photos table
        foreach ($uploaded_photos as $photo) {
            $photo_stmt = $conn->prepare("INSERT INTO property_photos (property_id, photo_path) VALUES (?, ?)");
            $photo_stmt->bind_param("is", $property_id, $photo);
            $photo_stmt->execute();
        }

        // ✅ Log activity only once
        $log = $conn->prepare("INSERT INTO activity_log (user_id, action) VALUES (?, ?)");
        $action = 'Added a new property';
        $log->bind_param("is", $ownerId, $action);
        $log->execute();

        // ✅ Redirect to prevent duplicate submissions
        header("Location: ownerHome.php?success=1");
        exit;
    } else {
        $message = "❌ Error: " . $stmt->error;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>List Property - BoardBuddy</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="styles/navigation.css">
<style>
body { font-family: 'Segoe UI', sans-serif; background: white; margin: 0; }
.container { max-width: 1200px; margin-top: 50px; }
.form-section { background: #fff; padding: 40px; border-radius: 12px; box-shadow: 0 5px 20px rgba(0,0,0,0.1); }
.form-section h2 { text-align: center; margin-bottom: 30px; font-size: 32px; font-weight: bold; }
input, textarea, select { width: 100%; padding: 10px; border-radius: 6px; border: 1px solid #ccc; margin-bottom: 15px; }
textarea { resize: vertical; height: 120px; }
.photo-upload { border: 2px dashed #aaa; padding: 25px; text-align: center; border-radius: 6px; cursor: pointer; background: #fafafa; }
.list-btn { background: #e67e22; color: #fff; border: none; padding: 15px; border-radius: 8px; width: 100%; font-size: 16px; font-weight: bold; margin-top: 20px; transition: 0.3s; }
.list-btn:hover { background: #cf711f; }
.message { text-align: center; font-weight: bold; margin-bottom: 20px; font-size: 16px; color: green; }
</style>
</head>
<body>

<!-- Navbar -->
<?php include 'navigation/ownerNav.php'; ?>

<!-- Form Section -->
<div class="container">
    <div class="form-section">
        <h2>List Your Property</h2>
        <?php if($message) echo "<div class='message'>$message</div>"; ?>
        <form action="" method="POST" enctype="multipart/form-data">
            <div class="row">
                <div class="col-md-6">
                    <label>Property Title</label>
                    <input type="text" name="title" placeholder="Enter property title" required>

                    <label>Description</label>
                    <textarea name="description" placeholder="Describe your property" required></textarea>

                    <label>Photos</label>
                    <div class="photo-upload">
                        <input type="file" name="photos[]" multiple accept="image/*">
                    </div>

                    <label>Location</label>
                    <input type="text" name="location" placeholder="Enter property location" required>
                </div>

                <div class="col-md-6">
                    <label>Facilities (Optional)</label>
                    <input type="text" name="facility_other" placeholder="E.g., Wifi, Kitchen, Parking">

                    <label>Billing Cycle</label>
                    <select name="billing_cycle">
                        <option value="Per Month">Per Month</option>
                        <option value="Per Week">Per Week</option>
                        <option value="Per Night">Per Night</option>
                    </select>

                    <label>Payment Option</label>
                    <select name="payment">
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                    </select>

                    <label>Price (Rs.)</label>
                    <input type="number" name="price" placeholder="Enter price" required>
                </div>
            </div>

            <button type="submit" class="list-btn">List Property</button>
        </form>
    </div>
</div>

<!-- Footer -->
<?php include 'navigation/footer.php';?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
