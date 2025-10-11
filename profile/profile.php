<?php
session_start();
include 'db.php';

// Make sure user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: userManagement/signin.php");
    exit;
}

$userId = $_SESSION['user_id'];

// Fetch user data from database
$stmt = $conn->prepare("SELECT first_name, last_name, email, phone, role, profile_pic FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

// Default profile pic if none
$profilePic = !empty($user['profile_pic']) ? 'uploads/' . $user['profile_pic'] : 'uploads/default-profile.png';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - BoardBuddy</title>
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/profile.css">
    <link rel="stylesheet" href="styles/navigation.css">
</head>

<body>

<!-- ✅ Dynamic Navbar Based on Role -->
<?php
if ($user['role'] === 'Owner') {
    include 'navigation/ownerNav.php';
} else {
    include 'navigation/userNav.php';
}
?>

<div class="profile-container">
    <img src="<?php echo $profilePic; ?>" class="profile-pic" id="profilePic">
    <br>
    <label for="uploadPic" class="upload-btn">Upload New Picture</label>
    <input type="file" name="profile_pic" id="uploadPic" style="display:none;" accept="image/*">

    <form class="profile-form" id="profileForm" method="POST" enctype="multipart/form-data" action="userManagement/updateProfile.php">
        <div class="form-group">
            <label>First Name</label>
            <input type="text" name="first_name" value="<?php echo htmlspecialchars($user['first_name']); ?>" disabled>
        </div>
        <div class="form-group">
            <label>Last Name</label>
            <input type="text" name="last_name" value="<?php echo htmlspecialchars($user['last_name']); ?>" disabled>
        </div>
        <div class="form-group">
            <label>Phone Number</label>
            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" disabled>
        </div>
        <div class="form-group">
            <label>Role</label>
            <input type="text" value="<?php echo htmlspecialchars($user['role']); ?>" disabled>
        </div>
        <div class="form-group" style="grid-column: span 2;">
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
        </div>
    </form>

    <div class="actions" id="actionButtons">
        <button class="btn btn-edit" id="editBtn">Edit Profile</button>
        <a href="userManagement/logout.php" class="btn btn-logout">Logout</a>
    </div>
</div>

<!-- Footer -->
<?php include 'navigation/footer.php'; ?>

<script src="scripts/profile.js"></script>
</body>
</html>
