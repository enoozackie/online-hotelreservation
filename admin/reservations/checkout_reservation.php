<?php
// =============================================================================
// admin/reservations/checkout_reservation.php - Handle Reservation Checkout
// =============================================================================

session_start();

// Prevent caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require __DIR__ . '/../../vendor/autoload.php';

use Lourdian\MonbelaHotel\Core\Database;
use Lourdian\MonbelaHotel\Model\Reservation;
use Lourdian\MonbelaHotel\Model\Room;

// Restrict Access to Admins Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// Initialize models
try {
    $reservationModel = new Reservation();
    $roomModel = new Room();
    $db = Database::getInstance();
} catch (Exception $e) {
    $_SESSION['error_message'] = "System error: Unable to initialize models.";
    error_log("Model initialization error: " . $e->getMessage());
    header("Location: manage_reservations.php");
    exit;
}

// Function to log checkout activity
function logCheckoutActivity($reservationId, $guestName, $roomNumber, $adminId) {
    $logFile = __DIR__ . '/../../logs/checkout_' . date('Y-m') . '.log';
    $logDir = dirname($logFile);
    
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logEntry = sprintf(
        "[%s] Checkout - Reservation: #%s, Guest: %s, Room: %s, Admin: %s, IP: %s\n",
        date('Y-m-d H:i:s'),
        $reservationId,
        $guestName,
        $roomNumber,
        $adminId,
        $_SERVER['REMOTE_ADDR'] ?? 'Unknown'
    );
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// Get and validate reservation ID
if (!isset($_GET['id'])) {
    $_SESSION['error_message'] = "No reservation ID provided.";
    header("Location: manage_reservations.php");
    exit;
}

$reservationId = trim($_GET['id']);

if (!is_numeric($reservationId) || $reservationId <= 0) {
    $_SESSION['error_message'] = "Invalid reservation ID format.";
    header("Location: manage_reservations.php");
    exit;
}

$reservationId = (int)$reservationId;

// Begin transaction
$db->beginTransaction();

try {
    // Get reservation details
    $reservation = $reservationModel->getById($reservationId);
    
    if (!$reservation) {
        throw new Exception("Reservation not found.");
    }
    
    // Check if reservation is already checked out
    if ($reservation['STATUS'] === 'Checked Out' || $reservation['STATUS'] === 'Completed') {
        throw new Exception("This reservation has already been checked out.");
    }
    
    // Check if reservation is confirmed
    if ($reservation['STATUS'] !== 'Confirmed') {
        throw new Exception("Only confirmed reservations can be checked out. Current status: " . $reservation['STATUS']);
    }
    
    // Check if the current date is appropriate for checkout
    $currentDate = new DateTime();
    $arrivalDate = new DateTime($reservation['ARRIVAL']);
    
    if ($currentDate < $arrivalDate) {
        throw new Exception("Cannot checkout before the arrival date (" . $arrivalDate->format('M j, Y') . ").");
    }
    
    // Update reservation status to Checked Out
    $updateQuery = "UPDATE tblreservation 
                   SET STATUS = 'Checked Out', 
                       CHECKOUT_DATE = NOW(),
                       CHECKOUT_BY = :admin_id
                   WHERE RESERVEID = :reservation_id";
    
    $stmt = $db->prepare($updateQuery);
    $adminId = $_SESSION['admin_id'] ?? $_SESSION['user_id'] ?? 'Unknown';
    
    $stmt->execute([
        ':admin_id' => $adminId,
        ':reservation_id' => $reservationId
    ]);
    
    if ($stmt->rowCount() === 0) {
        throw new Exception("Failed to update reservation status.");
    }
    
    // Update room status to available if room is assigned
    if (!empty($reservation['ROOMID'])) {
        $roomUpdateQuery = "UPDATE tblroom 
                           SET ROOM_STATUS = 'Available',
                               CURRENT_GUEST_ID = NULL,
                               LAST_CHECKOUT = NOW()
                           WHERE ROOMID = :room_id 
                           AND ROOM_STATUS = 'Occupied'";
        
        $roomStmt = $db->prepare($roomUpdateQuery);
        $roomStmt->execute([':room_id' => $reservation['ROOMID']]);
        
        // Log room status change
        error_log("Room " . $reservation['ROOMNUM'] . " (ID: " . $reservation['ROOMID'] . ") set to Available after checkout");
    }
    
    // Update guest checkout history (if you have a guest history table)
    $historyQuery = "INSERT INTO tblguest_history 
                    (GUESTID, ACTION_TYPE, ACTION_DATE, RESERVATION_ID, ROOM_ID, PERFORMED_BY) 
                    VALUES 
                    (:guest_id, 'CHECKOUT', NOW(), :reservation_id, :room_id, :admin_id)";
    
    try {
        $historyStmt = $db->prepare($historyQuery);
        $historyStmt->execute([
            ':guest_id' => $reservation['GUESTID'],
            ':reservation_id' => $reservationId,
            ':room_id' => $reservation['ROOMID'],
            ':admin_id' => $adminId
        ]);
    } catch (PDOException $e) {
        // Log but don't fail if history table doesn't exist
        error_log("Could not update guest history: " . $e->getMessage());
    }
    
    // Calculate and log stay statistics
    $departureDate = new DateTime($reservation['DEPARTURE']);
    $actualStayDuration = $arrivalDate->diff($currentDate)->days;
    $plannedStayDuration = $arrivalDate->diff($departureDate)->days;
    
    // Log the checkout activity
    $guestName = $reservation['G_FNAME'] . ' ' . $reservation['G_LNAME'];
    $roomNumber = $reservation['ROOMNUM'] ?? 'N/A';
    logCheckoutActivity($reservationId, $guestName, $roomNumber, $adminId);
    
    // Send checkout notification email (optional)
    if (!empty($reservation['G_EMAIL'])) {
        try {
            sendCheckoutEmail($reservation);
        } catch (Exception $e) {
            // Log but don't fail if email fails
            error_log("Failed to send checkout email: " . $e->getMessage());
        }
    }
    
    // Commit transaction
    $db->commit();
    
    // Set success message with details
    $_SESSION['success_message'] = sprintf(
        "✅ Successfully checked out reservation #%d for %s from Room %s. Stay duration: %d nights.",
        $reservationId,
        htmlspecialchars($guestName),
        htmlspecialchars($roomNumber),
        $actualStayDuration
    );
    
    // Log successful checkout
    error_log(sprintf(
        "Successful checkout - Reservation: #%d, Guest: %s, Room: %s, Duration: %d/%d nights",
        $reservationId,
        $guestName,
        $roomNumber,
        $actualStayDuration,
        $plannedStayDuration
    ));
    
} catch (Exception $e) {
    // Rollback transaction on error
    $db->rollback();
    
    $_SESSION['error_message'] = "Checkout failed: " . $e->getMessage();
    error_log("Checkout error for reservation #$reservationId: " . $e->getMessage());
}

// Redirect back to view reservation page
header("Location: view_reservation.php?id=" . $reservationId);
exit;

/**
 * Send checkout confirmation email
 * 
 * @param array $reservation Reservation details
 * @return void
 */
function sendCheckoutEmail($reservation) {
    // This is a placeholder for email functionality
    // You can implement actual email sending here using PHPMailer or similar
    
    $subject = "Thank You for Staying at Monbela Hotel";
    $message = "Dear " . $reservation['G_FNAME'] . " " . $reservation['G_LNAME'] . ",\n\n";
    $message .= "Thank you for choosing Monbela Hotel for your recent stay.\n";
    $message .= "We hope you had a pleasant experience and look forward to welcoming you again.\n\n";
    $message .= "Checkout Details:\n";
    $message .= "Confirmation Code: " . $reservation['CONFIRMATIONCODE'] . "\n";
    $message .= "Room: " . $reservation['ROOM'] . " (#" . $reservation['ROOMNUM'] . ")\n";
    $message .= "Checkout Date: " . date('F j, Y') . "\n\n";
    $message .= "Best regards,\n";
    $message .= "Monbela Hotel Team";
    
    // For now, just log the email content
    error_log("Checkout email prepared for " . $reservation['G_EMAIL'] . ": " . $subject);
}