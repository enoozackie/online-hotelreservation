<?php
// =============================================================================
// public/cancel_reservation.php - Cancels an Existing Reservation (FIXED)
// =============================================================================

session_start();

// Prevent caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require __DIR__ . '/../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Booking;

// Restrict Access
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'guest') {
    header("Location: login.php");
    exit;
}

// --- HELPER FUNCTION (same as in my_reservations.php) ---
function canCancelReservation($reservation) { 
    // Can cancel if status is pending or confirmed and check-in date is at least 2 days away
    $status = strtolower($reservation['STATUS'] ?? '');
    $checkin = new DateTime($reservation['ARRIVAL'] ?? 'now');
    $today = new DateTime('today');
    $interval = $today->diff($checkin);
    
    return ($status === 'pending' || $status === 'confirmed') && $interval->days >= 2;
}

// Initialize variables
$booking = new Booking();
$errors = [];

// Get reservation ID from URL
$reservationId = (int)($_GET['id'] ?? 0);

// Fetch reservation details to perform checks
$reservationDetails = null;
if ($reservationId > 0) {
    try {
        // Try to get by ID and guest ID first
        $reservationDetails = $booking->getByIdAndGuestId($reservationId, $_SESSION['id']);
        
        // Fallback to regular getById if needed
        if (!$reservationDetails) {
            $reservationDetails = $booking->getById($reservationId);
            
            // Verify ownership
            if ($reservationDetails && $reservationDetails['GUESTID'] != $_SESSION['id']) {
                $reservationDetails = null;
            }
        }
    } catch (Exception $e) {
        error_log("Error fetching reservation: " . $e->getMessage());
        $_SESSION['error_message'] = "Error fetching reservation: " . $e->getMessage();
        header("Location: my_reservations.php");
        exit;
    }
}

// --- VALIDATION ---
if (!$reservationDetails) {
    $_SESSION['error_message'] = 'Reservation not found or you do not have permission to cancel it.';
    header("Location: my_reservations.php");
    exit;
}

// Check if the reservation belongs to the logged-in user
if ($reservationDetails['GUESTID'] != $_SESSION['id']) {
    $_SESSION['error_message'] = 'You do not have permission to cancel this reservation.';
    header("Location: my_reservations.php");
    exit;
}

// Check if the reservation is in a cancellable state
if (!canCancelReservation($reservationDetails)) {
    $_SESSION['error_message'] = 'This reservation cannot be cancelled because it is too close to the check-in date or has already been cancelled.';
    header("Location: my_reservations.php");
    exit;
}

// --- ACTION ---
if (isset($_GET['confirm']) && $_GET['confirm'] === 'true') {
    try {
        $result = $booking->cancelReservation($reservationId);
        if ($result) {
            $_SESSION['success_message'] = 'Your reservation has been successfully cancelled.';
        } else {
            $_SESSION['error_message'] = 'Failed to cancel reservation. Please try again.';
        }
    } catch (Exception $e) {
        error_log("Cancel reservation error: " . $e->getMessage());
        $_SESSION['error_message'] = 'An error occurred: ' . $e->getMessage();
    }
    
    header("Location: my_reservations.php");
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cancel Reservation - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary-color: #1a365d; 
            --secondary-color: #d69e2e; 
            --background-light: #f8fafc; 
            --text-muted: #6c757d; 
            --border-color: #e2e8f0; 
            --shadow-md: 0 4px 6px rgba(0,0,0,0.07); 
            --radius-lg: 16px; 
        }
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%); 
            color: var(--primary-color); 
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .container { 
            max-width: 700px; 
            width: 100%;
        }
        .card { 
            background: white; 
            border-radius: var(--radius-lg); 
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }
        .card-header { 
            background: linear-gradient(135deg, var(--primary-color) 0%, #2d3748 100%);
            color: white; 
            padding: 2rem; 
            text-align: center;
        }
        .card-header h2 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        .card-header .bi {
            font-size: 2rem;
        }
        .card-body { 
            padding: 2.5rem; 
        }
        .reservation-info {
            background: #f8f9fa;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            border-left: 4px solid #dc3545;
        }
        .reservation-info h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: var(--text-muted);
            font-weight: 500;
        }
        .info-value {
            color: var(--primary-color);
            font-weight: 600;
        }
        .warning-text {
            text-align: center;
            color: #721c24;
            background: #f8d7da;
            padding: 1rem;
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
            font-weight: 500;
        }
        .btn-custom { 
            padding: 0.875rem 1.75rem; 
            border-radius: var(--radius-lg); 
            font-weight: 600; 
            text-decoration: none; 
            transition: all 0.3s ease; 
            display: inline-flex; 
            align-items: center; 
            gap: 0.5rem; 
            border: none; 
            cursor: pointer; 
            font-size: 1rem;
        }
        .btn-secondary { 
            background: var(--text-muted); 
            color: white; 
        }
        .btn-secondary:hover { 
            background: #5a6268; 
            color: white; 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        }
        .btn-danger { 
            background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);
            color: white; 
        }
        .btn-danger:hover { 
            background: linear-gradient(135deg, #c82333 0%, #bd2130 100%);
            color: white; 
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(220, 53, 69, 0.4);
        }
        .button-group {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
        }
        @media (max-width: 576px) {
            .button-group {
                flex-direction: column;
            }
            .btn-custom {
                width: 100%;
                justify-content: center;
            }
            .card-body {
                padding: 1.5rem;
            }
            .info-row {
                flex-direction: column;
                gap: 0.25rem;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h2>
                    <i class="bi bi-exclamation-triangle-fill"></i> 
                    Confirm Cancellation
                </h2>
            </div>
            <div class="card-body">
                <div class="warning-text">
                    <i class="bi bi-exclamation-circle-fill"></i>
                    Are you sure you want to cancel this reservation? This action cannot be undone.
                </div>

                <div class="reservation-info">
                    <h5>
                        <i class="bi bi-info-circle-fill"></i>
                        Reservation Details
                    </h5>
                    <div class="info-row">
                        <span class="info-label">Confirmation Code:</span>
                        <span class="info-value"><?= htmlspecialchars($reservationDetails['CONFIRMATIONCODE'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Room:</span>
                        <span class="info-value"><?= htmlspecialchars($reservationDetails['ROOM'] ?? 'N/A') ?> - Room #<?= htmlspecialchars($reservationDetails['ROOMNUM'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Check-in:</span>
                        <span class="info-value"><?= date('F j, Y', strtotime($reservationDetails['ARRIVAL'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Check-out:</span>
                        <span class="info-value"><?= date('F j, Y', strtotime($reservationDetails['DEPARTURE'])) ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Status:</span>
                        <span class="info-value"><?= htmlspecialchars($reservationDetails['STATUS'] ?? 'N/A') ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Total Price:</span>
                        <span class="info-value">₱<?= number_format($reservationDetails['RPRICE'] ?? 0, 2) ?></span>
                    </div>
                </div>
                
                <div class="button-group">
                    <a href="my_reservations.php" class="btn-custom btn-secondary">
                        <i class="bi bi-arrow-left"></i> No, Keep Reservation
                    </a>
                    <a href="?id=<?= $reservationId ?>&confirm=true" class="btn-custom btn-danger">
                        <i class="bi bi-x-circle"></i> Yes, Cancel Reservation
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>