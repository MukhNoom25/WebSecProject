<?php
include 'db.php';

header('Content-Type: application/json');

// Get and sanitize GET parameters
$date = $_GET['date'] ?? '';
$start_time = $_GET['start_time'] ?? '';
$end_time = $_GET['end_time'] ?? '';
$room_id = $_GET['room_id'] ?? '';

// Input validation
if (
    empty($date) || empty($start_time) || empty($end_time) || empty($room_id) ||
    !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || // Valid date
    !preg_match('/^(0[8-9]|1[0-9]|20):(00|30)$/', $start_time) || // Time format: 08:00 - 20:30
    !preg_match('/^(0[8-9]|1[0-9]|20):(00|30)$/', $end_time) ||
    !ctype_digit($room_id)
) {
    echo json_encode([
        'error' => true,
        'message' => 'Invalid parameters. Make sure all fields are filled and valid.'
    ]);
    exit;
}

// Ensure start_time < end_time
if ($start_time >= $end_time) {
    echo json_encode(['error' => true, 'message' => 'End time must be after start time']);
    exit;
}

$room_id = (int)$room_id;

// Check if the room exists
$stmt = $conn->prepare("SELECT COUNT(*) FROM rooms WHERE id = ?");
$stmt->bind_param("i", $room_id);
$stmt->execute();
$stmt->bind_result($room_exists);
$stmt->fetch();
$stmt->close();

if (!$room_exists) {
    echo json_encode(['error' => true, 'message' => 'Room does not exist']);
    exit;
}

// Check for overlapping bookings
$stmt = $conn->prepare("
    SELECT id FROM bookings 
    WHERE room = ? AND date = ? AND (
        (start_time < ? AND end_time > ?) OR
        (start_time < ? AND end_time > ?) OR
        (start_time >= ? AND end_time <= ?)
    )
");
$stmt->bind_param("isssssss", $room_id, $date, $end_time, $start_time, $end_time, $start_time, $start_time, $end_time);
$stmt->execute();
$stmt->store_result();

$is_available = $stmt->num_rows === 0;
$stmt->close();
$conn->close();

// Response
echo json_encode([
    'error' => false,
    'available' => $is_available
]);
