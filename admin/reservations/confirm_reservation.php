<?php
// =============================================================================
// admin/reservations/confirm_reservation.php - Admin Confirm Reservation Action
// =============================================================================

session_start();

require __DIR__ . '/../../vendor/autoload.php';

use Lourdian\MonbelaHotel\Core\Database;
use Lourdian\MonbelaHotel\Model\Reservation;

// Restrict Access to Admins Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// --- Get and Validate Reservation ID ---
$reservationId = null;
if (isset($_GET['id']) && filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $reservationId = (int)$_GET['id'];
} else {
    $_SESSION['error_message'] = "Invalid Reservation ID.";
    header("Location: manage_reservations.php");
    exit;
}

// --- Process Confirmation ---
try {
    $database = new Database();
    $db_link = $database->getConnection();
    $reservationModel = new Reservation($db_link);
    
    if ($reservationModel->confirmReservation($reservationId)) {
        $_SESSION['success_message'] = "Reservation #$reservationId has been confirmed successfully.";
    } else {
        $_SESSION['error_message'] = "Failed to confirm reservation. It might already be confirmed or an error occurred.";
    }
} catch (Exception $e) {
    $_SESSION['error_message'] = "Error processing confirmation: " . $e->getMessage();
}

// --- Redirect Back to View Page ---
header("Location: view_reservation.php?id=" . $reservationId);
exit;