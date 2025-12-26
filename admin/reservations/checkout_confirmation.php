<?php
// =============================================================================
// admin/reservations/checkout_confirmation.php - Checkout Confirmation Page
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

// Restrict Access to Admins Only
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

// Initialize models
try {
    $reservationModel = new Reservation();
} catch (Exception $e) {
    $_SESSION['error_message'] = "System error: Unable to initialize models.";
    header("Location: manage_reservations.php");
    exit;
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

// Get reservation details
$reservation = $reservationModel->getById($reservationId);

if (!$reservation) {
    $_SESSION['error_message'] = "Reservation not found.";
    header("Location: manage_reservations.php");
    exit;
}

// Check if already checked out
if ($reservation['STATUS'] === 'Checked Out' || $reservation['STATUS'] === 'Completed') {
    $_SESSION['error_message'] = "This reservation has already been checked out.";
    header("Location: view_reservation.php?id=" . $reservationId);
    exit;
}

// Check if reservation is confirmed
if ($reservation['STATUS'] !== 'Confirmed') {
    $_SESSION['error_message'] = "Only confirmed reservations can be checked out.";
    header("Location: view_reservation.php?id=" . $reservationId);
    exit;
}

// Calculate stay details
$currentDate = new DateTime();
$arrivalDate = new DateTime($reservation['ARRIVAL']);
$departureDate = new DateTime($reservation['DEPARTURE']);
$actualStayDuration = $arrivalDate->diff($currentDate)->days;
$plannedStayDuration = $arrivalDate->diff($departureDate)->days;

// Check if early checkout
$isEarlyCheckout = $currentDate < $departureDate;
$isLateCheckout = $currentDate > $departureDate;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_checkout'])) {
    // Process the checkout
    header("Location: checkout_reservation.php?id=" . $reservationId);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout Confirmation - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-dark: #0f2027;
            --primary-main: #203a43;
            --primary-light: #2c5364;
            --accent-gold: #d4af37;
            --accent-teal: #00d4ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
        }

        body {
            background: linear-gradient(135deg, #e0e7ff 0%, #f3f4f6 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .checkout-card {
            background: white;
            border-radius: 1.5rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
            animation: slideUp 0.5s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .checkout-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--primary-light));
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .checkout-header h2 {
            font-size: 1.75rem;
            margin-bottom: 0.5rem;
        }

        .checkout-header p {
            opacity: 0.9;
            margin: 0;
        }

        .checkout-body {
            padding: 2rem;
        }

        .reservation-summary {
            background: #f9fafb;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .summary-item:last-child {
            border-bottom: none;
        }

        .summary-label {
            color: #6b7280;
            font-weight: 500;
        }

        .summary-value {
            font-weight: 600;
            color: var(--primary-dark);
        }

        .alert-custom {
            border-radius: 0.75rem;
            padding: 1rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-warning {
            background: #fef3c7;
            color: #92400e;
            border: 1px solid #fbbf24;
        }

        .alert-info {
            background: #dbeafe;
            color: #1e40af;
            border: 1px solid #3b82f6;
        }

        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }

        .btn-checkout {
            background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
            color: white;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            flex: 1;
        }

        .btn-checkout:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 15px -3px rgba(20, 184, 166, 0.3);
        }

        .btn-cancel {
            background: #f3f4f6;
            color: #4b5563;
            border: none;
            padding: 0.875rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            flex: 1;
            text-decoration: none;
            text-align: center;
        }

        .btn-cancel:hover {
            background: #e5e7eb;
            color: #374151;
        }

        .status-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-size: 0.875rem;
            font-weight: 600;
        }

        .badge-early {
            background: #fef3c7;
            color: #92400e;
        }

        .badge-late {
            background: #fee2e2;
            color: #991b1b;
        }

        .badge-ontime {
            background: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body>
    <div class="checkout-card">
        <div class="checkout-header">
            <h2>
                <i class="bi bi-box-arrow-right"></i>
                Checkout Confirmation
            </h2>
            <p>Please confirm the checkout details</p>
        </div>
        
        <div class="checkout-body">
            <?php if ($isEarlyCheckout): ?>
                <div class="alert-custom alert-warning">
                    <i class="bi bi-exclamation-triangle"></i>
                    <div>
                        <strong>Early Checkout</strong><br>
                        <small>Guest is checking out before the planned departure date.</small>
                    </div>
                </div>
            <?php elseif ($isLateCheckout): ?>
                <div class="alert-custom alert-warning">
                    <i class="bi bi-exclamation-circle"></i>
                    <div>
                        <strong>Late Checkout</strong><br>
                        <small>Guest is checking out after the planned departure date.</small>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert-custom alert-info">
                    <i class="bi bi-info-circle"></i>
                    <div>
                        <strong>Standard Checkout</strong><br>
                        <small>Guest is checking out as scheduled.</small>
                    </div>
                </div>
            <?php endif; ?>
            
            <div class="reservation-summary">
                <div class="summary-item">
                    <span class="summary-label">Guest Name</span>
                    <span class="summary-value">
                        <?= htmlspecialchars($reservation['G_FNAME'] . ' ' . $reservation['G_LNAME']) ?>
                    </span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Confirmation Code</span>
                    <span class="summary-value"><?= htmlspecialchars($reservation['CONFIRMATIONCODE']) ?></span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Room</span>
                    <span class="summary-value">
                        <?= htmlspecialchars($reservation['ROOM']) ?>
                        <?php if ($reservation['ROOMNUM']): ?>
                            (#<?= htmlspecialchars($reservation['ROOMNUM']) ?>)
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Check-in Date</span>
                    <span class="summary-value">
                        <?= date('M j, Y', strtotime($reservation['ARRIVAL'])) ?>
                    </span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Planned Check-out</span>
                    <span class="summary-value">
                        <?= date('M j, Y', strtotime($reservation['DEPARTURE'])) ?>
                    </span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Actual Check-out</span>
                    <span class="summary-value">
                        <?= date('M j, Y') ?>
                        <?php if ($isEarlyCheckout): ?>
                            <span class="status-badge badge-early">Early</span>
                        <?php elseif ($isLateCheckout): ?>
                            <span class="status-badge badge-late">Late</span>
                        <?php else: ?>
                            <span class="status-badge badge-ontime">On Time</span>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Stay Duration</span>
                    <span class="summary-value">
                        <?= $actualStayDuration ?> nights
                        <?php if ($actualStayDuration !== $plannedStayDuration): ?>
                            <small class="text-muted">(planned: <?= $plannedStayDuration ?> nights)</small>
                        <?php endif; ?>
                    </span>
                </div>
                
                <div class="summary-item">
                    <span class="summary-label">Total Amount</span>
                    <span class="summary-value" style="font-size: 1.25rem; color: var(--accent-gold);">
                        $<?= number_format($reservation['RPRICE'], 2) ?>
                    </span>
                </div>
            </div>
            
            <form method="POST" onsubmit="return confirm('Are you sure you want to proceed with the checkout?');">
                <div class="action-buttons">
                    <a href="view_reservation.php?id=<?= $reservationId ?>" class="btn-cancel">
                        Cancel
                    </a>
                    <button type="submit" name="confirm_checkout" class="btn-checkout">
                        <i class="bi bi-check2-circle"></i>
                        Confirm Checkout
                    </button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>