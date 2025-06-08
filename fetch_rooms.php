<?php
include 'db.php';

// Set JSON header
header('Content-Type: application/json');

// Validate and sanitize input
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    echo json_encode(['error' => true, 'message' => 'Invalid room ID']);
    exit;
}

$room_id = (int)$_GET['id'];

// Prepare statement to prevent SQL injection
$stmt = $conn->prepare("SELECT capacity, services FROM rooms WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$stmt->bind_result($capacity, $services);

if ($stmt->fetch()) {
    echo json_encode([
        'capacity' => $capacity,
        'services' => htmlspecialchars($services)
    ]);
} else {
    echo json_encode(['error' => true, 'message' => 'Room not found']);
}

$stmt->close();
$conn->close();
