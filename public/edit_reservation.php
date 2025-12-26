<?php
// =============================================================================
// public/edit_reservation.php - Edit an Existing Reservation (NEW DESIGN)
// =============================================================================

session_start();

// Prevent caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require __DIR__ . '/../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Guest;
use Lourdian\MonbelaHotel\Model\Room;
use Lourdian\MonbelaHotel\Model\Booking;

// Restrict Access
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'guest') {
    header("Location: login.php");
    exit;
}

// Initialize models
 $guest = new Guest();
 $room = new Room();
 $booking = new Booking();
 $errors = [];

// Get reservation ID from URL
 $reservationId = (int)($_GET['id'] ?? 0);

// --- HELPER FUNCTION (same as in my_reservations.php) ---
function canEditReservation($reservation) { 
    // Can edit if status is pending and check-in date is at least 3 days away
    $status = strtolower($reservation['STATUS'] ?? '');
    $checkin = new DateTime($reservation['ARRIVAL'] ?? 'now');
    $today = new DateTime('today');
    $interval = $today->diff($checkin);
    
    return $status === 'pending' && $interval->days >= 3;
}

// --- FETCH RESERVATION ---
 $reservationDetails = null;
if ($reservationId > 0) {
    try {
        // Use getByIdAndGuestId to ensure the reservation belongs to this guest
        $reservationDetails = $booking->getByIdAndGuestId($reservationId, $_SESSION['id']);
        
        // If that doesn't work, try the regular getById
        if (!$reservationDetails) {
            $reservationDetails = $booking->getById($reservationId);
            
            // Verify it belongs to this guest
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

// Fetch guest data
 $guestData = $guest->getById($_SESSION['id']);

// --- VALIDATION ---
if (!$reservationDetails) {
    $_SESSION['error_message'] = 'Reservation not found or you do not have permission to edit it.';
    header("Location: my_reservations.php");
    exit;
}

// Check if the reservation belongs to the logged-in user
if ($reservationDetails['GUESTID'] != $_SESSION['id']) {
    $_SESSION['error_message'] = 'You do not have permission to edit this reservation.';
    header("Location: my_reservations.php");
    exit;
}

// Check if the reservation can be edited
if (!canEditReservation($reservationDetails)) {
    $_SESSION['error_message'] = 'This reservation cannot be edited because it is too close to the check-in date or has been cancelled.';
    header("Location: my_reservations.php");
    exit;
}

// Get rooms & accommodations
 $allRooms = $room->getAll();
 $allAccommodations = $room->getAllAccommodations();

// --- HANDLE FORM SUBMISSION ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'ROOMID'   => $_POST['ROOMID'] ?? '',
        'ARRIVAL'  => $_POST['ARRIVAL'] ?? '',
        'DEPARTURE'=> $_POST['DEPARTURE'] ?? '',
        'PRORPOSE' => $_POST['PRORPOSE'] ?? '',
        'REMARKS'  => $_POST['REMARKS'] ?? ''
    ];

    // Validation
    if (empty($data['ROOMID'])) {
        $errors['ROOMID'] = 'Please select a room';
    }
    if (empty($data['ARRIVAL'])) {
        $errors['ARRIVAL'] = 'Check-in date is required';
    }
    if (empty($data['DEPARTURE'])) {
        $errors['DEPARTURE'] = 'Check-out date is required';
    }
    if (empty($data['PRORPOSE'])) {
        $errors['PRORPOSE'] = 'Booking reason is required';
    }

    // Date validation
    if (!empty($data['ARRIVAL']) && !empty($data['DEPARTURE'])) {
        $checkin = new DateTime($data['ARRIVAL']);
        $checkout = new DateTime($data['DEPARTURE']);
        $today = new DateTime('today');
        
        if ($checkout <= $checkin) {
            $errors['DEPARTURE'] = 'Check-out date must be after check-in date';
        }
        
        // Check if new check-in date is at least 3 days from today
        $interval = $today->diff($checkin);
        if ($interval->days < 3) {
            $errors['ARRIVAL'] = 'Check-in date must be at least 3 days from today';
        }
    }

    if (empty($errors)) {
        try {
            $result = $booking->updateReservation($reservationId, $data);
            if ($result) {
                $_SESSION['success_message'] = 'Reservation updated successfully!';
                header("Location: my_reservations.php");
                exit;
            } else {
                $errors['general'] = 'Failed to update reservation. The room may not be available for the selected dates.';
            }
        } catch (Exception $e) {
            $errors['general'] = 'An error occurred: ' . $e->getMessage();
            error_log("Reservation update error: " . $e->getMessage());
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Edit Reservation - Monbela Hotel</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
<style>
:root { 
    --primary: #8b5cf6; 
    --primary-dark: #7c3aed; 
    --secondary: #ec4899; 
    --danger: #ef4444; 
    --light: #faf5ff; 
    --dark: #4c1d95; 
    --gray: #6b7280; 
    --border: #e5e7eb; 
    --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); 
    --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
}

* { margin: 0; padding: 0; box-sizing: border-box; }

body { 
    font-family: 'Poppins', sans-serif; 
    background: linear-gradient(135deg, #faf5ff 0%, #ede9fe 100%); 
    min-height: 100vh; 
    color: var(--dark); 
}

/* Header */
.header { 
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); 
    color: white; 
    padding: 1.5rem 0; 
    box-shadow: var(--shadow); 
    position: sticky; 
    top: 0; 
    z-index: 1000; 
}

.header .container { 
    display: flex; 
    justify-content: space-between; 
    align-items: center; 
    max-width: 1200px; 
    margin: 0 auto; 
    padding: 0 1rem; 
}

.brand { 
    display: flex; 
    align-items: center; 
    font-size: 1.5rem; 
    font-weight: 700; 
    text-decoration: none; 
    color: white; 
}

.brand i { 
    margin-right: 0.75rem; 
    font-size: 2rem; 
}

.nav-links { 
    display: flex; 
    list-style: none; 
    gap: 2rem; 
    margin: 0; 
    padding: 0; 
}

.nav-links a { 
    color: white; 
    text-decoration: none; 
    font-weight: 500; 
    transition: all 0.3s ease; 
    position: relative; 
}

.nav-links a::after {
    content: '';
    position: absolute;
    bottom: -5px;
    left: 0;
    width: 0;
    height: 2px;
    background: var(--secondary);
    transition: width 0.3s ease;
}

.nav-links a:hover::after {
    width: 100%;
}

.nav-links a:hover { 
    color: var(--secondary); 
}

.nav-links a.active { 
    color: var(--secondary); 
}

.nav-links a.active::after { 
    width: 100%; 
}

.mobile-toggle { 
    display: none; 
    background: none; 
    border: none; 
    color: white; 
    font-size: 1.5rem; 
    cursor: pointer; 
}

/* Main Container */
.container { 
    max-width: 1000px; 
    margin: 2rem auto; 
    padding: 0 1rem; 
}

/* Breadcrumb */
.breadcrumb { 
    background: white; 
    padding: 1rem 1.5rem; 
    border-radius: 12px; 
    box-shadow: var(--shadow); 
    margin-bottom: 2rem; 
    display: flex; 
    align-items: center; 
}

.breadcrumb a { 
    color: var(--gray); 
    text-decoration: none; 
    transition: color 0.3s ease; 
}

.breadcrumb a:hover { 
    color: var(--primary); 
}

.breadcrumb .separator { 
    margin: 0 0.75rem; 
    color: var(--gray); 
}

.breadcrumb .current { 
    color: var(--primary); 
    font-weight: 600; 
}

/* Progress Indicator */
.progress-container {
    display: flex;
    justify-content: space-between;
    margin-bottom: 3rem;
    position: relative;
}

.progress-container::before {
    content: '';
    position: absolute;
    top: 25px;
    left: 0;
    right: 0;
    height: 2px;
    background: var(--border);
    z-index: 0;
}

.progress-step {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    z-index: 1;
    flex: 1;
}

.step-number {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: white;
    border: 2px solid var(--border);
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    color: var(--gray);
    transition: all 0.3s ease;
    margin-bottom: 0.5rem;
}

.progress-step.active .step-number {
    background: var(--primary);
    border-color: var(--primary);
    color: white;
    transform: scale(1.1);
}

.progress-step.completed .step-number {
    background: var(--secondary);
    border-color: var(--secondary);
    color: white;
}

.step-title {
    font-size: 0.875rem;
    color: var(--gray);
    text-align: center;
}

.progress-step.active .step-title {
    color: var(--primary);
    font-weight: 600;
}

.progress-step.completed .step-title {
    color: var(--secondary);
    font-weight: 600;
}

/* Card Styles */
.card { 
    background: white; 
    border-radius: 16px; 
    box-shadow: var(--shadow-lg); 
    overflow: hidden; 
    margin-bottom: 2rem; 
    border: none; 
    animation: fadeInUp 0.5s ease; 
}

@keyframes fadeInUp { 
    from { 
        opacity: 0; 
        transform: translateY(20px); 
    } 
    to { 
        opacity: 1; 
        transform: translateY(0); 
    } 
}

.card-header { 
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); 
    color: white; 
    padding: 1.5rem 2rem; 
    border: none; 
}

.card-header h2 { 
    margin: 0; 
    font-weight: 600; 
    display: flex; 
    align-items: center; 
}

.card-header h2 i { 
    margin-right: 0.75rem; 
    font-size: 1.5rem; 
}

.card-body { 
    padding: 2rem; 
}

/* Current Reservation Info */
.current-info { 
    background: linear-gradient(135deg, #faf5ff 0%, #ede9fe 100%); 
    border-left: 4px solid var(--primary); 
    padding: 1.5rem; 
    border-radius: 8px; 
    margin-bottom: 2rem; 
}

.current-info h4 { 
    color: var(--primary-dark); 
    margin-bottom: 1rem; 
    display: flex; 
    align-items: center; 
}

.current-info h4 i { 
    margin-right: 0.5rem; 
}

.current-info .info-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); 
    gap: 1rem; 
}

.current-info .info-item { 
    display: flex; 
    align-items: center; 
    color: var(--gray); 
}

.current-info .info-item i { 
    margin-right: 0.5rem; 
    color: var(--primary); 
}

/* Alert Styles */
.alert { 
    border-radius: 12px; 
    border: none; 
    padding: 1rem 1.5rem; 
    margin-bottom: 1.5rem; 
    display: flex; 
    align-items: center; 
}

.alert-danger { 
    background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); 
    color: var(--danger); 
    border-left: 4px solid var(--danger); 
}

.alert i { 
    margin-right: 0.75rem; 
    font-size: 1.25rem; 
}

/* Form Styles */
.form-group { 
    margin-bottom: 1.5rem; 
    position: relative;
}

.form-label { 
    font-weight: 600; 
    color: var(--dark); 
    margin-bottom: 0.5rem; 
    display: flex; 
    align-items: center;
}

.form-label i {
    margin-right: 0.5rem;
    color: var(--primary);
}

.form-control { 
    border: 2px solid var(--border); 
    border-radius: 10px; 
    padding: 0.875rem 1rem; 
    font-size: 1rem; 
    transition: all 0.3s ease; 
    width: 100%; 
}

.form-control:focus { 
    border-color: var(--primary); 
    box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1); 
    outline: none; 
    transform: translateY(-2px);
}

.form-control.is-invalid { 
    border-color: var(--danger); 
}

.invalid-feedback { 
    color: var(--danger); 
    font-size: 0.875rem; 
    margin-top: 0.5rem; 
    display: flex; 
    align-items: center; 
}

.invalid-feedback i { 
    margin-right: 0.25rem; 
}

select.form-control { 
    cursor: pointer; 
}

select.form-control:disabled { 
    background: #f9fafb; 
    cursor: not-allowed; 
    opacity: 0.7; 
}

textarea.form-control { 
    resize: vertical; 
    min-height: 100px; 
}

/* Tooltip Styles */
.tooltip-icon {
    display: inline-block;
    margin-left: 0.5rem;
    color: var(--gray);
    cursor: help;
    font-size: 0.875rem;
}

.tooltip-icon:hover {
    color: var(--primary);
}

/* Button Styles */
.btn { 
    padding: 0.875rem 2rem; 
    border-radius: 10px; 
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

.btn-primary { 
    background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); 
    color: white; 
    box-shadow: 0 4px 6px rgba(139, 92, 246, 0.25); 
}

.btn-primary:hover { 
    transform: translateY(-2px); 
    box-shadow: 0 6px 12px rgba(139, 92, 246, 0.35); 
    color: white; 
}

.btn-secondary { 
    background: white; 
    color: var(--gray); 
    border: 2px solid var(--border); 
}

.btn-secondary:hover { 
    background: var(--light); 
    border-color: var(--primary); 
    color: var(--primary); 
}

.btn-group { 
    display: flex; 
    gap: 1rem; 
    justify-content: flex-end; 
    margin-top: 2rem; 
    padding-top: 2rem; 
    border-top: 2px solid var(--border); 
}

/* Responsive Design */
@media (max-width: 768px) { 
    .nav-links { 
        display: none; 
        position: absolute; 
        top: 100%; 
        left: 0; 
        right: 0; 
        background: var(--primary-dark); 
        flex-direction: column; 
        padding: 1rem 0; 
        box-shadow: var(--shadow); 
    } 
    
    .nav-links.active { 
        display: flex; 
    } 
    
    .mobile-toggle { 
        display: block; 
    } 
    
    .btn-group { 
        flex-direction: column; 
    } 
    
    .btn { 
        width: 100%; 
        justify-content: center; 
    } 
    
    .current-info .info-grid { 
        grid-template-columns: 1fr; 
    }
    
    .progress-container {
        margin-bottom: 2rem;
    }
    
    .step-title {
        font-size: 0.75rem;
    }
}

/* Animation for form elements */
.form-group { 
    animation: fadeIn 0.5s ease forwards; 
    opacity: 0; 
}

.form-group:nth-child(1) { animation-delay: 0.1s; }
.form-group:nth-child(2) { animation-delay: 0.2s; }
.form-group:nth-child(3) { animation-delay: 0.3s; }
.form-group:nth-child(4) { animation-delay: 0.4s; }
.form-group:nth-child(5) { animation-delay: 0.5s; }

@keyframes fadeIn { 
    to { 
        opacity: 1; 
    } 
}

/* Room selection enhancement */
select.form-control option:disabled { 
    color: var(--gray); 
}

/* Loading state */
.loading { 
    position: relative; 
}

.loading::after { 
    content: ''; 
    position: absolute; 
    top: 50%; 
    left: 50%; 
    width: 20px; 
    height: 20px; 
    margin: -10px 0 0 -10px; 
    border: 2px solid transparent; 
    border-top: 2px solid white; 
    border-radius: 50%; 
    animation: spin 1s linear infinite; 
}

@keyframes spin { 
    0% { transform: rotate(0deg); } 
    100% { transform: rotate(360deg); } 
}

/* Tooltip positioning */
.tooltip {
    position: relative;
}

.tooltip .tooltiptext {
    visibility: hidden;
    width: 200px;
    background-color: var(--dark);
    color: white;
    text-align: center;
    border-radius: 6px;
    padding: 0.5rem;
    position: absolute;
    z-index: 1;
    bottom: 125%;
    left: 50%;
    margin-left: -100px;
    opacity: 0;
    transition: opacity 0.3s;
    font-size: 0.875rem;
}

.tooltip .tooltiptext::after {
    content: "";
    position: absolute;
    top: 100%;
    left: 50%;
    margin-left: -5px;
    border-width: 5px;
    border-style: solid;
    border-color: var(--dark) transparent transparent transparent;
}

.tooltip:hover .tooltiptext {
    visibility: visible;
    opacity: 1;
}
</style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <a href="homepage.php" class="brand">
                <i class="bi bi-building"></i>
                Monbela Hotel
            </a>
            <button class="mobile-toggle" onclick="toggleMenu()">
                <i class="bi bi-list"></i>
            </button>
            <ul class="nav-links" id="navLinks">
                <li><a href="homepage.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li><a href="booking.php"><i class="bi bi-calendar-plus"></i> Book Now</a></li>
                <li><a href="guest_profile.php"><i class="bi bi-person-circle"></i> Profile</a></li>
                <li><a href="my_reservations.php" class="active"><i class="bi bi-calendar-check"></i> My Reservations</a></li>
                <li><a href="guest_profile.php?logout=1"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="homepage.php">Home</a>
            <span class="separator">/</span>
            <a href="my_reservations.php">My Reservations</a>
            <span class="separator">/</span>
            <span class="current">Edit Reservation</span>
        </div>

        <!-- Progress Indicator -->
        <div class="progress-container">
            <div class="progress-step completed">
                <div class="step-number"><i class="bi bi-check"></i></div>
                <div class="step-title">Select Dates</div>
            </div>
            <div class="progress-step active">
                <div class="step-number">2</div>
                <div class="step-title">Choose Room</div>
            </div>
            <div class="progress-step">
                <div class="step-number">3</div>
                <div class="step-title">Confirm</div>
            </div>
        </div>

        <!-- Edit Form Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-pencil-square"></i> Edit Reservation</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?= htmlspecialchars($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <!-- Current Reservation Info -->
                <div class="current-info">
                    <h4><i class="bi bi-info-circle-fill"></i> Current Booking Details</h4>
                    <div class="info-grid">
                        <div class="info-item">
                            <i class="bi bi-door-open-fill"></i>
                            <span><?= htmlspecialchars($reservationDetails['ROOM']) ?> - Room #<?= htmlspecialchars($reservationDetails['ROOMNUM']) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="bi bi-calendar-range-fill"></i>
                            <span><?= date('M j, Y', strtotime($reservationDetails['ARRIVAL'])) ?> to <?= date('M j, Y', strtotime($reservationDetails['DEPARTURE'])) ?></span>
                        </div>
                        <div class="info-item">
                            <i class="bi bi-tag-fill"></i>
                            <span>Confirmation: <?= htmlspecialchars($reservationDetails['CONFIRMATIONCODE']) ?></span>
                        </div>
                    </div>
                </div>

                <form action="edit_reservation.php?id=<?= $reservationId ?>" method="POST">
                    <div class="form-group">
                        <label for="ROOMID" class="form-label">
                            <i class="bi bi-door-open"></i>
                            Select Room
                            <span class="tooltip">
                                <i class="bi bi-info-circle tooltip-icon"></i>
                                <span class="tooltiptext">Choose your preferred room type. Prices shown are per night.</span>
                            </span>
                        </label>
                        <select name="ROOMID" id="ROOMID" class="form-control <?= !empty($errors['ROOMID']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Select a Room --</option>
                            <?php foreach ($allRooms as $roomItem): ?>
                                <option value="<?= $roomItem['ROOMID'] ?>" 
                                    <?= ($roomItem['ROOMID'] == $reservationDetails['ROOMID']) ? 'selected' : '' ?>
                                    <?= ($roomItem['OROOMNUM'] <= 0) ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($roomItem['ROOM']) ?> - Room #<?= htmlspecialchars($roomItem['ROOMNUM']) ?> 
                                    (₱<?= number_format($roomItem['PRICE'], 2) ?>/night)
                                    <?= ($roomItem['OROOMNUM'] <= 0) ? ' - SOLD OUT' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['ROOMID'])): ?>
                            <div class="invalid-feedback">
                                <i class="bi bi-x-circle-fill"></i>
                                <?= htmlspecialchars($errors['ROOMID']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="ARRIVAL" class="form-label">
                                    <i class="bi bi-calendar-check"></i>
                                    Check-in Date
                                    <span class="tooltip">
                                        <i class="bi bi-info-circle tooltip-icon"></i>
                                        <span class="tooltiptext">Check-in must be at least 3 days from today</span>
                                    </span>
                                </label>
                                <input type="date" name="ARRIVAL" id="ARRIVAL" 
                                    class="form-control <?= !empty($errors['ARRIVAL']) ? 'is-invalid' : '' ?>" 
                                    value="<?= htmlspecialchars($reservationDetails['ARRIVAL']) ?>" 
                                    min="<?= date('Y-m-d', strtotime('+3 days')) ?>"
                                    required>
                                <?php if (!empty($errors['ARRIVAL'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-x-circle-fill"></i>
                                        <?= htmlspecialchars($errors['ARRIVAL']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="DEPARTURE" class="form-label">
                                    <i class="bi bi-calendar-x"></i>
                                    Check-out Date
                                    <span class="tooltip">
                                        <i class="bi bi-info-circle tooltip-icon"></i>
                                        <span class="tooltiptext">Check-out must be after check-in date</span>
                                    </span>
                                </label>
                                <input type="date" name="DEPARTURE" id="DEPARTURE" 
                                    class="form-control <?= !empty($errors['DEPARTURE']) ? 'is-invalid' : '' ?>" 
                                    value="<?= htmlspecialchars($reservationDetails['DEPARTURE']) ?>" 
                                    min="<?= date('Y-m-d', strtotime('+4 days')) ?>"
                                    required>
                                <?php if (!empty($errors['DEPARTURE'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-x-circle-fill"></i>
                                        <?= htmlspecialchars($errors['DEPARTURE']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="PRORPOSE" class="form-label">
                            <i class="bi bi-chat-left-text"></i>
                            Reason for Booking
                            <span class="tooltip">
                                <i class="bi bi-info-circle tooltip-icon"></i>
                                <span class="tooltiptext">Let us know the purpose of your stay</span>
                            </span>
                        </label>
                        <select name="PRORPOSE" id="PRORPOSE" class="form-control <?= !empty($errors['PRORPOSE']) ? 'is-invalid' : '' ?>" required>
                            <option value="">-- Select a reason --</option>
                            <?php 
                            $reasons = [
                                'Business' => 'Business Trip',
                                'Vacation' => 'Vacation/Holiday',
                                'Family' => 'Family Visit',
                                'Event' => 'Special Event',
                                'Other' => 'Other'
                            ];
                            foreach($reasons as $key => $label): ?>
                                <option value="<?= $key ?>" 
                                    <?= (isset($_POST['PRORPOSE']) && $_POST['PRORPOSE'] == $key) || 
                                        (!isset($_POST['PRORPOSE']) && $reservationDetails['PRORPOSE'] == $key) ? 'selected' : '' ?>>
                                    <?= $label ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (!empty($errors['PRORPOSE'])): ?>
                            <div class="invalid-feedback">
                                <i class="bi bi-x-circle-fill"></i>
                                <?= htmlspecialchars($errors['PRORPOSE']) ?>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="form-group">
                        <label for="REMARKS" class="form-label">
                            <i class="bi bi-pencil"></i>
                            Special Requests (Optional)
                            <span class="tooltip">
                                <i class="bi bi-info-circle tooltip-icon"></i>
                                <span class="tooltiptext">Any special requests or preferences for your stay?</span>
                            </span>
                        </label>
                        <textarea name="REMARKS" id="REMARKS" class="form-control" rows="4" 
                            placeholder="Any special requests or preferences?"><?= htmlspecialchars($reservationDetails['REMARKS'] ?? '') ?></textarea>
                    </div>

                    <div class="btn-group">
                        <a href="my_reservations.php" class="btn btn-secondary">
                            <i class="bi bi-arrow-left"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle-fill"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        function toggleMenu() {
            document.getElementById('navLinks').classList.toggle('active');
        }

        // Update checkout date minimum when checkin changes
        document.getElementById('ARRIVAL').addEventListener('change', function() {
            const checkin = new Date(this.value);
            const checkout = document.getElementById('DEPARTURE');
            const minCheckout = new Date(checkin);
            minCheckout.setDate(minCheckout.getDate() + 1);
            checkout.min = minCheckout.toISOString().split('T')[0];
            
            // If current checkout is before new minimum, update it
            if (checkout.value && new Date(checkout.value) <= checkin) {
                checkout.value = minCheckout.toISOString().split('T')[0];
            }
        });

        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span></span> Updating...';
        });

        // Animate progress steps on scroll
        const observerOptions = {
            threshold: 0.5,
            rootMargin: '0px'
        };

        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.animation = 'fadeInUp 0.5s ease forwards';
                }
            });
        }, observerOptions);

        document.querySelectorAll('.progress-step').forEach(step => {
            observer.observe(step);
        });

        // Form field animations
        document.querySelectorAll('.form-control').forEach(field => {
            field.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            field.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });

        // Initialize tooltips
        document.querySelectorAll('.tooltip').forEach(tooltip => {
            tooltip.addEventListener('mouseenter', function() {
                this.querySelector('.tooltiptext').style.opacity = '1';
                this.querySelector('.tooltiptext').style.visibility = 'visible';
            });
            
            tooltip.addEventListener('mouseleave', function() {
                this.querySelector('.tooltiptext').style.opacity = '0';
                this.querySelector('.tooltiptext').style.visibility = 'hidden';
            });
        });
    </script>
</body>
</html>