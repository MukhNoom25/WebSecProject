<?php include 'db.php'; ?>
<!DOCTYPE html>
<html>
<head>
    <title>Conference Room Details</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Navigation Menu Styles */
        .navbar {
            background-color: #003366;
            overflow: hidden;
            padding: 0 20px;
        }
        .navbar a {
            float: left;
            display: block;
            color: white;
            text-align: center;
            padding: 14px 16px;
            text-decoration: none;
            font-size: 16px;
            transition: background-color 0.3s;
        }
        .navbar a:hover {
            background-color: #004080;
        }
        .navbar a.active {
            background-color: #004080;
            font-weight: bold;
        }
        .navbar-right {
            float: right;
        }
        
        /* Content Styles */
        .container {
            max-width: 1000px;
            margin: 20px auto;
            padding: 20px;
            background-color: #fff;
            border-radius: 8px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        h2 {
            color: #003366;
            text-align: center;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background-color: #003366;
            color: white;
        }
        tr:nth-child(even) {
            background-color: #f2f2f2;
        }
        .status-available {
            color: green;
            font-weight: bold;
        }
        .status-booked {
            color: red;
            font-weight: bold;
        }
        .date-form {
            margin: 20px 0;
            padding: 15px;
            background-color: #f5f5f5;
            border-radius: 5px;
        }
        .date-form label {
            font-weight: bold;
            margin-right: 10px;
        }
        .date-form input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .date-form button {
            padding: 8px 15px;
            background-color: #003366;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .date-form button:hover {
            background-color: #004080;
        }
    </style>
</head>
<body>
    <!-- Navigation Menu -->
    <div class="navbar">
        <a href="user_profile.php">User Profile</a>
        <a href="homepage.php">Homepage</a>
        <a href="book_room.php">Room Booking</a>
        <a href="view_bookings.php">View Bookings</a>
        <a href="feedback.php">Feedback</a>
        <div class="navbar-right">
            <a href="logout.php">Logout</a>
        </div>
    </div>

    <div class="container">
        <h2>Conference Room Details</h2>
        
        <?php
        // Get selected date if provided
        $selected_date = $_GET['date'] ?? date('Y-m-d');
        ?>
        
        <div class="date-form">
            <form method="GET" action="">
                <label for="date">View availability for date:</label>
                <input type="date" name="date" id="date" value="<?php echo $selected_date; ?>" required>
                <button type="submit">Show</button>
            </form>
        </div>
        
        <?php
        $result = $conn->query("SELECT * FROM rooms");
        if ($result->num_rows > 0) {
            echo '<table>';
            echo '<tr>
                    <th>Room Name</th>
                    <th>Capacity</th>
                    <th>Services</th>
                    <th>Availability Status</th>
                    <th>Booked Time Slots</th>
                  </tr>';

            while ($row = $result->fetch_assoc()) {
                // Check availability for the selected date
                $room_id = $row['id'];
                $bookings = $conn->prepare("SELECT start_time, end_time FROM bookings 
                                           WHERE room = ? AND date = ? 
                                           ORDER BY start_time");
                $bookings->bind_param("is", $room_id, $selected_date);
                $bookings->execute();
                $booking_result = $bookings->get_result();
                
                $booked_slots = [];
                $is_available = true;
                
                while ($booking = $booking_result->fetch_assoc()) {
                    $booked_slots[] = date("g:i A", strtotime($booking['start_time'])) . " - " . 
                                     date("g:i A", strtotime($booking['end_time']));
                    $is_available = false;
                }
                
                echo "<tr>
                        <td>{$row['room_name']}</td>
                        <td>{$row['capacity']} persons</td>
                        <td>{$row['services']}</td>
                        <td class='" . ($is_available ? 'status-available' : 'status-booked') . "'>" . 
                            ($is_available ? 'Available' : 'Booked') . "</td>
                        <td>" . implode("<br>", $booked_slots) . "</td>
                      </tr>";
            }

            echo "</table>";
        } else {
            echo "<p style='color:#003366;'>No room data found.</p>";
        }
        ?>
    </div>
</body>
</html>