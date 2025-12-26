<?php
// =============================================================================
// quick_fix_rooms.php - Automatically fix rooms with "0" in ROOM field
// Place this in admin/rooms/ directory and run ONCE
// =============================================================================

session_start();
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    die("Access denied");
}

require __DIR__ . '/../../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Room;

$roomModel = new Room();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quick Fix Rooms</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3>🔧 Quick Fix Room Types</h3>
            </div>
            <div class="card-body">
                <?php
                if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['fix_rooms'])) {
                    echo '<div class="alert alert-info">Processing rooms...</div>';
                    
                    $rooms = $roomModel->getAllRooms();
                    $fixed = 0;
                    
                    foreach ($rooms as $room) {
                        $roomId = $room['ROOMID'];
                        $currentType = $room['ROOM'];
                        
                        // Check if room type is empty, 0, or just numbers
                        if (empty($currentType) || $currentType === '0' || is_numeric($currentType)) {
                            // Get accommodation name to suggest a room type
                            $accomId = $room['ACCOMID'];
                            $accommodations = $roomModel->getAllAccommodations();
                            $accomName = '';
                            
                            foreach ($accommodations as $accom) {
                                if ($accom['ACCOMID'] == $accomId) {
                                    $accomName = $accom['ACCOMODATION'];
                                    break;
                                }
                            }
                            
                            // Generate a default room type based on accommodation
                            $newRoomType = '';
                            $newDescription = '';
                            
                            if (stripos($accomName, 'bayanihan') !== false) {
                                $newRoomType = 'Bayanihan Suite';
                                $newDescription = 'Spacious group room perfect for families and gatherings, accommodating multiple guests comfortably.';
                            } elseif (stripos($accomName, 'standard') !== false) {
                                $newRoomType = 'Standard Room';
                                $newDescription = 'Comfortable accommodation with essential amenities for a pleasant stay.';
                            } elseif (stripos($accomName, 'travelers') !== false || stripos($accomName, 'traveler') !== false) {
                                $newRoomType = 'Economy Room';
                                $newDescription = 'Budget-friendly room perfect for short stays and travelers.';
                            } else {
                                // Fallback
                                $newRoomType = 'Deluxe Room';
                                $newDescription = 'Well-appointed room with modern amenities and comfortable furnishings.';
                            }
                            
                            // Update only if description is also empty or 0
                            $updateDesc = false;
                            if (empty($room['ROOMDESC']) || $room['ROOMDESC'] === '0') {
                                $updateDesc = true;
                            }
                            
                            try {
                                $updateData = [
                                    'ROOMNUM' => $room['ROOMNUM'],
                                    'ROOM' => $newRoomType,
                                    'ACCOMID' => $room['ACCOMID'],
                                    'ROOMDESC' => $updateDesc ? $newDescription : $room['ROOMDESC'],
                                    'NUMPERSON' => $room['NUMPERSON'],
                                    'PRICE' => $room['PRICE'],
                                    'ROOMIMAGE' => $room['ROOMIMAGE'],
                                    'amenities' => $room['amenities'],
                                    'ROOM_STATUS' => $room['ROOM_STATUS']
                                ];
                                
                                $roomModel->updateRoom($roomId, $updateData);
                                
                                echo "<div class='alert alert-success'>";
                                echo "✅ Fixed Room #{$room['ROOMNUM']}: ";
                                echo "Changed from '<strong>$currentType</strong>' to '<strong>$newRoomType</strong>'";
                                echo "</div>";
                                
                                $fixed++;
                            } catch (Exception $e) {
                                echo "<div class='alert alert-danger'>";
                                echo "❌ Error fixing Room #{$room['ROOMNUM']}: " . $e->getMessage();
                                echo "</div>";
                            }
                        }
                    }
                    
                    if ($fixed === 0) {
                        echo '<div class="alert alert-info">No rooms needed fixing. All room types are properly set!</div>';
                    } else {
                        echo "<div class='alert alert-success mt-3'>";
                        echo "<strong>✅ Complete!</strong> Fixed $fixed room(s).";
                        echo "</div>";
                    }
                    
                    echo '<div class="mt-4">';
                    echo '<a href="manage_rooms.php" class="btn btn-primary">View Rooms</a> ';
                    echo '<a href="simple_debug.php" class="btn btn-secondary">Run Debug</a>';
                    echo '</div>';
                    
                } else {
                    // Show form
                    $rooms = $roomModel->getAllRooms();
                    $needsFix = 0;
                    
                    echo '<h5>Rooms that need fixing:</h5>';
                    echo '<table class="table table-bordered">';
                    echo '<thead><tr><th>Room ID</th><th>Room #</th><th>Current Type</th><th>Will Change To</th></tr></thead>';
                    echo '<tbody>';
                    
                    foreach ($rooms as $room) {
                        $currentType = $room['ROOM'];
                        
                        if (empty($currentType) || $currentType === '0' || is_numeric($currentType)) {
                            $needsFix++;
                            
                            // Preview what it will change to
                            $accomId = $room['ACCOMID'];
                            $accommodations = $roomModel->getAllAccommodations();
                            $accomName = '';
                            
                            foreach ($accommodations as $accom) {
                                if ($accom['ACCOMID'] == $accomId) {
                                    $accomName = $accom['ACCOMODATION'];
                                    break;
                                }
                            }
                            
                            $newRoomType = '';
                            if (stripos($accomName, 'bayanihan') !== false) {
                                $newRoomType = 'Bayanihan Suite';
                            } elseif (stripos($accomName, 'standard') !== false) {
                                $newRoomType = 'Standard Room';
                            } elseif (stripos($accomName, 'travelers') !== false) {
                                $newRoomType = 'Economy Room';
                            } else {
                                $newRoomType = 'Deluxe Room';
                            }
                            
                            echo '<tr>';
                            echo "<td>{$room['ROOMID']}</td>";
                            echo "<td>{$room['ROOMNUM']}</td>";
                            echo "<td><span class='badge bg-danger'>$currentType</span></td>";
                            echo "<td><span class='badge bg-success'>$newRoomType</span></td>";
                            echo '</tr>';
                        }
                    }
                    
                    echo '</tbody></table>';
                    
                    if ($needsFix > 0) {
                        echo "<div class='alert alert-warning'>";
                        echo "<strong>⚠️ Found $needsFix room(s) that need fixing.</strong>";
                        echo "</div>";
                        
                        echo '<form method="POST">';
                        echo '<input type="hidden" name="fix_rooms" value="1">';
                        echo '<button type="submit" class="btn btn-primary btn-lg">🔧 Fix All Rooms</button>';
                        echo '</form>';
                    } else {
                        echo '<div class="alert alert-success">✅ All rooms are properly configured!</div>';
                        echo '<a href="manage_rooms.php" class="btn btn-primary">Back to Manage Rooms</a>';
                    }
                }
                ?>
            </div>
        </div>
    </div>
</body>
</html>