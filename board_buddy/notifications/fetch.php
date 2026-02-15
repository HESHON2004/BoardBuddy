<?php
header('Content-Type: application/json');

$host = "localhost";
$user = "root";
$pass = "";
$db   = "board_buddy";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die(json_encode(["error" => "DB connection failed"]));
}

$user_type = isset($_GET['user_type']) ? $_GET['user_type'] : 'customer';

$sql = "SELECT id, title, message, is_important, created_at 
        FROM notifications 
        WHERE user_type = ? 
        ORDER BY created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_type);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode($notifications);
$conn->close();

