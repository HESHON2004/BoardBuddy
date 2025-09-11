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

$id = $data['id'];

$sql = "DELETE FROM notifications WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);

if ($stmt->execute()) {
    echo json_encode(["success" => true]);
} else {
    echo json_encode(["success" => false]);
}

$conn->close();

