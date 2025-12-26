<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Room;

// Initialize Room model
 $roomModel = new Room();

// Get room ID from POST
 $roomId = $_POST['id'] ?? null;
if (!$roomId) {
    $_SESSION['error'] = "Room ID is required.";
    header("Location: manage_rooms.php");
    exit;
}

try {
    // Get room data first to delete the image file
    $room = $roomModel->getRoomById($roomId);
    if (!$room) {
        throw new Exception("Room not found.");
    }

    // Check if we should force delete (delete with reservations)
    $forceDelete = isset($_POST['force_delete']) && $_POST['force_delete'] === 'true';
    
    if ($forceDelete) {
        // Use the new method to delete the room and all associated reservations
        $roomModel->deleteRoomWithReservations($roomId);
        $_SESSION['success'] = "Room and all its associated reservations have been deleted successfully!";
    } else {
        // Try to delete just the room using the updated, safer method
        $roomModel->deleteRoom($roomId);
        $_SESSION['success'] = "Room deleted successfully!";
    }
    
    // Delete the room image file if it exists (this happens after the DB deletion)
    if (!empty($room['ROOMIMAGE'])) {
        $imagePath = __DIR__ . '/../../photos/' . $room['ROOMIMAGE'];
        if (file_exists($imagePath)) {
            unlink($imagePath);
        }
    }
    
} catch (Exception $e) {
    // Catch the specific error from our updated deleteRoom method
    $_SESSION['error'] = $e->getMessage();
    
    // If it's a foreign key constraint error, offer the option to force delete
    if (strpos($e->getMessage(), "reservation(s) associated with this room") !== false) {
        // Store room info in session to display the force delete option
        $_SESSION['force_delete_room_id'] = $roomId;
        $_SESSION['force_delete_room_name'] = $room['ROOM'] ?? 'Unknown Room';
    }
}

header("Location: manage_rooms.php");
exit;
?>