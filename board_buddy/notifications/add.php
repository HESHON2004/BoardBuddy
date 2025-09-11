<?php
header('Content-Type: application/json');

$data = json_decode(file_get_contents("php://input"), true);

$host = "localhost";
$user = "root";
$pass = "";
$db   = "board_buddy";

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die(json_encode(["error" => "DB connection failed"]));
}

$title = $data['title'];
$message = $data['message'];
$user_type = $data['user_type'];

$sql = "INSERT INTO notifications (title, message, user_type) VALUES (?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("sss", $title, $message, $user_type);

if ($stmt->execute()) {
    echo json_encode(["success" => true, "id" => $stmt->insert_id]);
} else {
    echo json_encode(["success" => false]);
}

$conn->close();

