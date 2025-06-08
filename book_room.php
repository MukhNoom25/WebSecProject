<?php
// Secure session cookie settings 
ini_set('session.cookie_secure', 1);   // Only send cookie over HTTPS
ini_set('session.cookie_httponly', 1); // Prevent JavaScript access to session cookie

session_start();
include 'db.php';

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Enforce HTTPS
if (empty($_SERVER['HTTPS']) || $_SERVER['HTTPS'] === "off") {
    header("Location: https://" . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']);
    exit();
}

// Session Timeout: 8 minutes
$timeout_duration = 480;
if (isset($_SESSION['LAST_ACTIVITY']) && (time() - $_SESSION['LAST_ACTIVITY']) > $timeout_duration) {
    session_unset();
    session_destroy();
    // Redirect to conference room details page after session timeout
    header("Location: conference_room_details.php?timeout=1");
    exit;
}
$_SESSION['LAST_ACTIVITY'] = time();
?>
<!DOCTYPE html>
<html>
<head>
    <title>Railway Conference Room Booking</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* menu bar styles */
        .menu-bar {
            display: flex;
            background-color: #004080;
            padding: 10px 20px;
            gap: 15px;
            font-family: Arial, sans-serif;
        }

        .menu-bar a {
            color: #ffffff;
            text-decoration: none;
            padding: 8px 12px;
            border-radius: 4px;
            font-weight: 600;
            transition: background-color 0.3s ease;
        }

        .menu-bar a:hover {
            background-color: #0066cc;
        }

        .menu-bar a.active {
            background-color: #003366;
        }

        .time-container {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .time-select {
            flex: 1;
        }
        .info-box {
            background-color: #f0f8ff;
            padding: 12px;
            margin-top: 10px;
            border-left: 4px solid #004080;
            border-radius: 4px;
            display: none;
        }
        #availabilityMessage {
            padding: 10px;
            margin: 10px 0;
            border-radius: 4px;
        }
        .available {
            color: green;
            background-color: #e8f5e9;
        }
        .unavailable {
            color: red;
            background-color: #ffebee;
        }
        .error {
            color: red;
            background: #ffe0e0;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
        .success {
            color: green;
            background: #e0ffe0;
            padding: 10px;
            margin-bottom: 10px;
            border-radius: 5px;
        }
    </style>
    <script>
    async function showRoomInfo(roomId) {
        const div = document.getElementById('roomInfo');
        if (!roomId) {
            div.innerHTML = "";
            div.style.display = "none";
            return;
        }

        try {
            const res = await fetch('fetch_rooms.php?id=' + roomId);
            if (!res.ok) throw new Error('Network response was not ok');
            
            const data = await res.json();
            div.innerHTML = `<strong>Capacity:</strong> ${data.capacity} persons<br>
                             <strong>Services:</strong> ${data.services}`;
            div.style.display = "block";
        } catch (error) {
            console.error('Error fetching room info:', error);
            div.innerHTML = "Error loading room details";
            div.style.display = "block";
        }
    }

    async function checkAvailability() { //ajax
        const date = document.getElementById('date').value;
        const startTime = document.getElementById('start_time').value;
        const endTime = document.getElementById('end_time').value;
        const roomId = document.getElementById('room_id').value;
        const availabilityMessage = document.getElementById('availabilityMessage');
        
        if (!date || !startTime || !endTime) {
            availabilityMessage.innerHTML = "";
            return;
        }
        
        if (startTime >= endTime) {
            availabilityMessage.innerHTML = '<span class="unavailable">End time must be after start time</span>';
            return;
        }

        try {
            const response = await fetch(`check_availability.php?date=${date}&start_time=${startTime}&end_time=${endTime}&room_id=${roomId}`);
            
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            
            const data = await response.json();
            
            if (data.error) {
                availabilityMessage.innerHTML = `<span class="unavailable">${data.message || 'Error checking availability'}</span>`;
            } else {
                availabilityMessage.innerHTML = data.available 
                    ? '<span class="available">Room is available for booking</span>'
                    : '<span class="unavailable">Room is already booked during this time</span>';
            }
        } catch (error) {
            console.error('Error:', error);
            availabilityMessage.innerHTML = '<span class="unavailable">Error checking availability. Please try again.</span>';
        }
    }

    function validateForm() {
        const startTime = document.getElementById('start_time').value; // ajax
        const endTime = document.getElementById('end_time').value;
        
        if (startTime && endTime && startTime >= endTime) {
            alert('End time must be after start time');
            return false;
        }
        return true;
    }
    </script>
</head>
<body>
<div class="container">

    <nav class="menu-bar">
        <a href="user_profile.php">User Profile</a>
        <a href="index.php">Homepage</a>
        <a href="room_details.php" class="active">Room Details</a>
        <a href="view_bookings.php">View Bookings</a>
        <a href="feedback.php">Feedback</a>
        <a href="logout.php">Logout</a>
    </nav>

    <h2>Railway Conference Room Booking</h2>

    <?php if (isset($_GET['timeout']) && $_GET['timeout'] == 1): ?>
        <div class="error">Your session expired due to inactivity. Please rebook.</div>
    <?php endif; ?>

    <form method="POST" class="booking-form" onsubmit="return validateForm()">
        <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"> <!-- csrf protection -->

        <label for="name">Full Name</label>
        <input type="text" name="name" id="name" placeholder="Enter your full name" required>

        <label for="email">Email Address</label>
        <input type="email" name="email" id="email" placeholder="example@domain.com" required>

        <label for="date">Date</label>
        <input type="date" name="date" id="date" min="<?php echo date('Y-m-d'); ?>" required onchange="checkAvailability()">

        <label>Time Slot</label>
        <div class="time-container">
            <select name="start_time" id="start_time" class="time-select" onchange="checkAvailability()" required>
                <option value="">Start Time</option>
                <?php
                for ($hour = 8; $hour <= 20; $hour++) {
                    foreach (['00', '30'] as $minute) {
                        $time = sprintf("%02d:%s", $hour, $minute);
                        $display = date("g:i A", strtotime($time));
                        echo "<option value='$time'>$display</option>";
                    }
                }
                ?>
            </select>
            <span>to</span>
            <select name="end_time" id="end_time" class="time-select" onchange="checkAvailability()" required>
                <option value="">End Time</option>
                <?php
                for ($hour = 8; $hour <= 20; $hour++) {
                    foreach (['00', '30'] as $minute) {
                        $time = sprintf("%02d:%s", $hour, $minute);
                        $display = date("g:i A", strtotime($time));
                        echo "<option value='$time'>$display</option>";
                    }
                }
                ?>
            </select>
        </div>

        <div id="availabilityMessage"></div>

        <label for="room_id">Select Room</label>
        <select name="room_id" id="room_id" onchange="showRoomInfo(this.value)" required>
            <option value="">-- Choose a Room --</option>
            <?php
            $rooms = $conn->query("SELECT * FROM rooms");
            while ($r = $rooms->fetch_assoc()) {
                echo "<option value='" . (int)$r['id'] . "'>" . htmlspecialchars($r['room_name']) . "</option>";
            }
            ?>
        </select>

        <div id="roomInfo" class="info-box"></div>

        <button type="submit" name="book">Book Now</button>
    </form>

    <?php
    if (isset($_POST['book'])) {
        // CSRF check
        if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {  //csrf token check
            die('<div class="error">CSRF validation failed.</div>');
        }

        // Sanitize and validate inputs
        $name = htmlspecialchars(trim($_POST['name']));
        $email_raw = trim($_POST['email']);
        $email = filter_var($email_raw, FILTER_SANITIZE_EMAIL);
        $room_id = intval($_POST['room_id']);
        $date = $_POST['date'];
        $start_time = $_POST['start_time'];
        $end_time = $_POST['end_time'];

        $errors = [];

        // Validate email properly
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email address.";
        }

        // Validate date (YYYY-MM-DD)
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
            $errors[] = "Invalid date format.";
        }

        // Validate time format HH:MM (24-hour, 08:00 to 20:30, increments of 30 minutes)
        $time_pattern = '/^(0[8-9]|1\d|20):(00|30)$/';
        if (!preg_match($time_pattern, $start_time) || !preg_match($time_pattern, $end_time)) {
            $errors[] = "Invalid start or end time format.";
        }

        // Validate start_time < end_time
        if ($start_time >= $end_time) {
            $errors[] = "End time must be after start time.";
        }

        // Check if room exists
        $room_check = $conn->prepare("SELECT COUNT(*) FROM rooms WHERE id = ?");
        $room_check->bind_param("i", $room_id);
        $room_check->execute();
        $room_check->bind_result($room_exists);
        $room_check->fetch();
        $room_check->close();

        if (!$room_exists) {
            $errors[] = "Selected room does not exist.";
        }

        if (!empty($errors)) {
            foreach ($errors as $error) {
                echo "<div class='error'>" . htmlspecialchars($error) . "</div>";
            }
        } else {
            // Check booking overlap
            $check = $conn->prepare("SELECT id FROM bookings WHERE room = ? AND date = ? 
                                    AND ((start_time < ? AND end_time > ?) 
                                    OR (start_time < ? AND end_time > ?)
                                    OR (start_time >= ? AND end_time <= ?))");
            $check->bind_param("isssssss", $room_id, $date, $end_time, $start_time,
                              $end_time, $start_time, $start_time, $end_time);
            $check->execute();
            $check->store_result();

            if ($check->num_rows > 0) {
                echo "<div class='error'>This room is already booked during the selected time slot.</div>";
            } else {
                $stmt = $conn->prepare("INSERT INTO bookings (name, email, room, date, start_time, end_time) 
                                       VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssisss", $name, $email, $room_id, $date, $start_time, $end_time);

                if ($stmt->execute()) {
                    echo "<div class='success'>Booking successful!</div>";
                    // Reset CSRF token after successful booking to avoid reuse
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
} else {
echo "<div class='error'>Error: Booking failed. Please try again later.</div>";
}
}
$check->close();
}
}
?>

</div>
 </body>
  </html> ```
