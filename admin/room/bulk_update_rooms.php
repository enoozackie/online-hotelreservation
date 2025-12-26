<?php
// =============================================================================
// bulk_update_rooms.php - Handle bulk room operations
// =============================================================================
session_start();
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Room;

// Get JSON input
$input = json_decode(file_get_contents('php://input'), true);
$roomIds = isset($input['roomIds']) ? $input['roomIds'] : [];
$action = isset($input['action']) ? $input['action'] : '';

if (empty($roomIds) || empty($action)) {
    echo json_encode(['success' => false, 'message' => 'Invalid parameters']);
    exit;
}

$roomModel = new Room();
$success = true;
$count = 0;

if ($action === 'updateStatus') {
    $status = isset($input['status']) ? $input['status'] : '';
    if (empty($status)) {
        echo json_encode(['success' => false, 'message' => 'Status not specified']);
        exit;
    }
    
    foreach ($roomIds as $roomId) {
        if ($roomModel->updateRoomStatus(intval($roomId), $status)) {
            $count++;
        } else {
            $success = false;
        }
    }
    
    $message = $count > 0 ? "$count room(s) updated successfully" : "Failed to update rooms";
    if (!$success && $count > 0) {
        $message = "$count room(s) updated, but some failed";
    }
    
} elseif ($action === 'delete') {
    foreach ($roomIds as $roomId) {
        if ($roomModel->deleteRoom(intval($roomId))) {
            $count++;
        } else {
            $success = false;
        }
    }
    
    $message = $count > 0 ? "$count room(s) deleted successfully" : "Failed to delete rooms";
    if (!$success && $count > 0) {
        $message = "$count room(s) deleted, but some failed";
    }
    
} else {
    $success = false;
    $message = "Invalid action";
}

header('Content-Type: application/json');
echo json_encode([
    'success' => $success,
    'message' => $message,
    'count' => $count
]);