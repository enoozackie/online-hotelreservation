<?php
// =============================================================================
// public/debug_booking.php - Temporary Debug Page
// Place this file in your public folder and access it to see detailed errors
// =============================================================================

session_start();
require __DIR__ . '/../vendor/autoload.php';

use Lourdian\MonbelaHotel\Model\Booking;
use Lourdian\MonbelaHotel\Model\Room;
use Lourdian\MonbelaHotel\Core\Database;

// Check if user is logged in
if (!isset($_SESSION['id'])) {
    die("Please log in first");
}

// Initialize database
$database = new Database();
$conn = $database->getConnection();
$booking = new Booking($conn);
$room = new Room($conn);

echo "<h1>Booking Debug Page</h1>";
echo "<hr>";

// Test 1: Check database connection
echo "<h2>1. Database Connection</h2>";
if ($conn) {
    echo "✓ Database connected successfully<br>";
    echo "Connection ID: " . $conn->thread_id . "<br>";
} else {
    echo "✗ Database connection failed<br>";
    die();
}

// Test 2: Check session data
echo "<hr><h2>2. Session Data</h2>";
echo "Guest ID: " . ($_SESSION['id'] ?? 'NOT SET') . "<br>";
echo "Role: " . ($_SESSION['role'] ?? 'NOT SET') . "<br>";

// Test 3: Check if there's a cart
echo "<hr><h2>3. Booking Cart</h2>";
if (empty($_SESSION['booking_cart'])) {
    echo "Cart is empty. Please add a room to your cart first.<br>";
    echo '<a href="booking.php">Go to booking page</a>';
} else {
    echo "Cart items: " . count($_SESSION['booking_cart']) . "<br><br>";
    
    foreach ($_SESSION['booking_cart'] as $cartId => $bookingData) {
        echo "<strong>Cart Item: {$cartId}</strong><br>";
        echo "<pre>" . print_r($bookingData, true) . "</pre>";
        
        // Test 4: Check if room exists
        echo "<h3>4. Room Check</h3>";
        $roomId = (int)$bookingData['ROOMID'];
        $roomDetails = $room->getById($roomId);
        
        if ($roomDetails) {
            echo "✓ Room found<br>";
            echo "Room Name: " . $roomDetails['ROOM'] . "<br>";
            echo "Room Price: ₱" . $roomDetails['PRICE'] . "<br>";
            echo "Available Rooms: " . $roomDetails['OROOMNUM'] . "<br>";
        } else {
            echo "✗ Room not found with ID: {$roomId}<br>";
            continue;
        }
        
        // Test 5: Check room availability
        echo "<h3>5. Availability Check</h3>";
        $arrival = $bookingData['CHECKIN'];
        $departure = $bookingData['CHECKOUT'];
        
        echo "Check-in: {$arrival}<br>";
        echo "Check-out: {$departure}<br>";
        
        $isAvailable = $booking->isRoomAvailable($roomId, $arrival, $departure);
        echo $isAvailable ? "✓ Room is available<br>" : "✗ Room is NOT available<br>";
        
        // Test 6: Try to create booking
        echo "<h3>6. Attempting to Create Booking</h3>";
        
        $dataForModel = [
            'GUESTID' => (int)$_SESSION['id'],
            'ROOMID' => $roomId,
            'ARRIVAL' => $arrival,
            'DEPARTURE' => $departure,
            'PRORPOSE' => $bookingData['BOOKING_REASON'] ?? 'Online Booking',
            'REMARKS' => $bookingData['SPECIALREQUESTS'] ?? '',
            'USERID' => (int)$_SESSION['id']
        ];
        
        echo "Data to be inserted:<br>";
        echo "<pre>" . print_r($dataForModel, true) . "</pre>";
        
        // First, let's test the SQL manually
        echo "<h3>6a. Testing SQL Manually</h3>";
        $testSql = "INSERT INTO tblreservation
                    (CONFIRMATIONCODE, TRANSDATE, ROOMID, ARRIVAL, DEPARTURE, RPRICE, GUESTID, PRORPOSE, STATUS, BOOKDATE, REMARKS, USERID)
                    VALUES ('TEST-123', CURDATE(), ?, ?, ?, ?, ?, ?, 'Pending', NOW(), ?, ?)";
        
        $testStmt = $conn->prepare($testSql);
        if (!$testStmt) {
            echo "✗ Prepare failed: " . $conn->error . "<br>";
        } else {
            echo "✓ SQL prepared successfully<br>";
            
            $testRoomId = $roomId;
            $testArrival = $arrival . ' 14:00:00';
            $testDeparture = $departure . ' 12:00:00';
            $testPrice = 12.00;
            $testGuestId = (int)$_SESSION['id'];
            $testPurpose = "Vacation";
            $testRemarks = "";
            $testUserId = (int)$_SESSION['id'];
            
            echo "Binding: RoomID($testRoomId), Arrival($testArrival), Departure($testDeparture), Price($testPrice), GuestID($testGuestId), Purpose($testPurpose), Remarks($testRemarks), UserID($testUserId)<br>";
            
            $testStmt->bind_param(
                "issdissi",
                $testRoomId,
                $testArrival,
                $testDeparture,
                $testPrice,
                $testGuestId,
                $testPurpose,
                $testRemarks,
                $testUserId
            );
            
            if (!$testStmt->execute()) {
                echo "✗ <strong style='color: red;'>Execute failed: " . $testStmt->error . "</strong><br>";
            } else {
                echo "✓ <strong style='color: green;'>Test insert successful! ID: " . $conn->insert_id . "</strong><br>";
                // Delete the test record
                $conn->query("DELETE FROM tblreservation WHERE CONFIRMATIONCODE = 'TEST-123'");
                echo "✓ Test record cleaned up<br>";
            }
            $testStmt->close();
        }
        
        echo "<h3>6b. Now Testing with Booking Model</h3>";
        
        // Enable error display temporarily
        error_reporting(E_ALL);
        ini_set('display_errors', 1);
        
        // Check if Room model exists and has updateAvailability method
        echo "Checking Room model...<br>";
        $roomModel = new Room($conn);
        echo "✓ Room model instantiated<br>";
        
        if (method_exists($roomModel, 'updateAvailability')) {
            echo "✓ updateAvailability method exists<br>";
            // Try calling it with 0 change to test
            try {
                $testResult = $roomModel->updateAvailability($roomId, 0);
                echo "✓ updateAvailability callable, returned: " . ($testResult ? "true" : "false") . "<br>";
            } catch (Exception $e) {
                echo "✗ updateAvailability threw exception: " . $e->getMessage() . "<br>";
            }
        } else {
            echo "✗ <strong style='color: red;'>updateAvailability method NOT FOUND in Room model!</strong><br>";
            echo "This is likely why the booking is failing.<br>";
        }
        
        echo "<br>Now attempting booking...<br>";
        
        // Check what's in the Booking.php file
        $bookingFilePath = __DIR__ . '/../src/Model/Booking.php';
        if (file_exists($bookingFilePath)) {
            $bookingContent = file_get_contents($bookingFilePath);
            if (strpos($bookingContent, 'updateAvailability') !== false) {
                echo "⚠️ <strong style='color: orange;'>WARNING: Booking.php still contains 'updateAvailability' - old version is still loaded!</strong><br>";
                echo "You need to replace the Booking.php file with the new version.<br><br>";
            } else {
                echo "✓ Booking.php looks correct (no updateAvailability found)<br>";
            }
        }
        
        try {
            $confirmationCode = $booking->create($dataForModel);
            
            if ($confirmationCode) {
                echo "✓ <strong style='color: green;'>Booking created successfully!</strong><br>";
                echo "Confirmation Code: {$confirmationCode}<br>";
            } else {
                echo "✗ <strong style='color: red;'>Booking failed - create() returned false</strong><br>";
                echo "Check the error log for details.<br>";
                echo "Last MySQL Error: " . $conn->error . "<br>";
                
                // Try to read the actual error log
                $errorLog = ini_get('error_log');
                if (file_exists($errorLog)) {
                    $logContent = file_get_contents($errorLog);
                    $lines = explode("\n", $logContent);
                    $recentLines = array_slice($lines, -20); // Last 20 lines
                    echo "<br><strong>Recent Error Log Entries:</strong><br>";
                    echo "<pre style='background: #f5f5f5; padding: 10px; max-height: 300px; overflow: auto;'>";
                    foreach ($recentLines as $line) {
                        if (stripos($line, 'booking') !== false || stripos($line, 'error') !== false) {
                            echo htmlspecialchars($line) . "\n";
                        }
                    }
                    echo "</pre>";
                }
            }
        } catch (Exception $e) {
            echo "✗ <strong style='color: red;'>Exception thrown:</strong><br>";
            echo "Message: " . $e->getMessage() . "<br>";
            echo "File: " . $e->getFile() . " (Line " . $e->getLine() . ")<br>";
            echo "<pre>" . $e->getTraceAsString() . "</pre>";
        }
        
        echo "<hr>";
    }
}

// Test 7: Check database table structure
echo "<h2>7. Database Table Check</h2>";
$result = $conn->query("DESCRIBE tblreservation");
if ($result) {
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";
    while ($row = $result->fetch_assoc()) {
        echo "<tr>";
        echo "<td>" . $row['Field'] . "</td>";
        echo "<td>" . $row['Type'] . "</td>";
        echo "<td>" . $row['Null'] . "</td>";
        echo "<td>" . $row['Key'] . "</td>";
        echo "<td>" . ($row['Default'] ?? 'NULL') . "</td>";
        echo "</tr>";
    }
    echo "</table>";
} else {
    echo "✗ Could not describe tblreservation table<br>";
}

// Test 8: Check for recent errors
echo "<hr><h2>8. Recent PHP Errors</h2>";
echo "Check your PHP error log at:<br>";
echo "<code>" . ini_get('error_log') . "</code><br>";
echo "or check phpinfo for error_log location<br>";
echo '<a href="?phpinfo=1">View phpinfo</a><br>';

if (isset($_GET['phpinfo'])) {
    phpinfo();
}

echo "<hr>";
echo '<a href="booking.php">← Back to Booking</a> | ';
echo '<a href="my_reservations.php">View My Reservations</a>';
?>