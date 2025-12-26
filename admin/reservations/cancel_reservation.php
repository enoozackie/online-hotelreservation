<?php
// =============================================================================
// admin/reservations/reject_reservation.php - Rejects a Pending Reservation
// =============================================================================

session_start();

// Prevent caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require __DIR__ . '/../../vendor/autoload.php';

use Lourdian\MonbelaHotel\Core\Database;
use Lourdian\MonbelaHotel\Model\Reservation;

// Restrict Access to Admins Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// Check for a valid reservation ID
if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error_message'] = "Invalid Reservation ID provided.";
    header("Location: manage_reservations.php");
    exit;
}

 $reservationId = (int)$_GET['id'];

 $database = new Database();
 $db_link = $database->getConnection();
 $reservationModel = new Reservation($db_link);

// Use the updateStatus method to change the status to 'Cancelled'
// This represents a rejection of the pending request.
 $result = $reservationModel->updateStatus($reservationId, 'Cancelled');

if ($result) {
    $_SESSION['success_message'] = "Reservation ID {$reservationId} has been successfully rejected.";
} else {
    $_SESSION['error_message'] = "Failed to reject the reservation. The reservation may not exist or there was a database error.";
}

// Redirect back to the management page
header("Location: manage_reservations.php");
exit;