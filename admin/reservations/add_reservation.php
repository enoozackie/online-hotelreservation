<?php
// =============================================================================
// add_reservation.php - With dropdown purpose and bill summary
// =============================================================================
session_start();

// Check authentication
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

require __DIR__ . '/../../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Room;
use Lourdian\MonbelaHotel\Model\Guest;
use Lourdian\MonbelaHotel\Model\Reservation;

// Initialize models
$roomModel = new Room();
$guestModel = new Guest();
$reservationModel = new Reservation();

// Get all rooms and guests for selection
$rooms = $roomModel->getAllRooms();
$guests = $guestModel->getAll(100, 0);

// Success/Error messages
$successMsg = $_SESSION['success'] ?? '';
$errorMsg = $_SESSION['error'] ?? '';
unset($_SESSION['success'], $_SESSION['error']);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("=== POST REQUEST RECEIVED ===");
    error_log("POST Data: " . print_r($_POST, true));
    
    try {
        // Validate all required fields
        if (empty($_POST['guest_id'])) {
            throw new Exception("Please select a guest");
        }
        if (empty($_POST['room_id'])) {
            throw new Exception("Please select a room");
        }
        if (empty($_POST['arrival_date'])) {
            throw new Exception("Please select arrival date");
        }
        if (empty($_POST['departure_date'])) {
            throw new Exception("Please select departure date");
        }
        if (empty($_POST['purpose'])) {
            throw new Exception("Please select booking purpose");
        }
        
        // Validate dates
        $arrivalDate = new DateTime($_POST['arrival_date']);
        $departureDate = new DateTime($_POST['departure_date']);
        $today = new DateTime();
        $today->setTime(0, 0, 0);
        
        if ($arrivalDate < $today) {
            throw new Exception("Arrival date cannot be in the past");
        }
        
        if ($departureDate <= $arrivalDate) {
            throw new Exception("Departure date must be after arrival date");
        }
        
        // Prepare reservation data
        $reservationData = [
            'GUESTID' => (int)$_POST['guest_id'],
            'ROOMID' => (int)$_POST['room_id'],
            'ARRIVAL' => $_POST['arrival_date'],
            'DEPARTURE' => $_POST['departure_date'],
            'PRORPOSE' => $_POST['purpose'],
            'REMARKS' => $_POST['special_instructions'] ?? '',
            'USERID' => $_SESSION['id']
        ];
        
        error_log("Calling createReservation with data: " . print_r($reservationData, true));
        
        // Create reservation using your model's method
        $confirmationCode = $reservationModel->createReservation($reservationData);
        
        if ($confirmationCode) {
            $_SESSION['success'] = 'Reservation added successfully! Confirmation Code: ' . $confirmationCode;
            header('Location: manage_reservations.php');
            exit;
        } else {
            throw new Exception('Failed to create reservation. Please check the error logs.');
        }
        
    } catch (Exception $e) {
        error_log("Reservation creation error: " . $e->getMessage());
        $_SESSION['error'] = 'Error: ' . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Reservation - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
    <style>
        :root {
            --primary-dark: #0f172a;
            --primary-main: #1e293b;
            --secondary-main: #f59e0b;
            --success: #10b981;
            --danger: #ef4444;
            --white: #ffffff;
            --sidebar-width: 280px;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: 'Inter', sans-serif; 
            background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);
            min-height: 100vh;
        }
        
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-main) 100%);
            color: var(--white);
            overflow-y: auto;
            z-index: 1000;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }
        
        .sidebar-header {
            padding: 2rem;
            background: rgba(255, 255, 255, 0.05);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar-header h5 {
            font-weight: 700;
            font-size: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.7);
            padding: 0.875rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: all 0.15s;
            border-left: 4px solid transparent;
        }
        
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--white);
            border-left-color: var(--secondary-main);
            background: rgba(255, 255, 255, 0.1);
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }
        
        .form-section {
            background: var(--white);
            border-radius: 1rem;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            position: relative;
            overflow: hidden;
        }
        
        .form-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(135deg, var(--secondary-main) 0%, #fbbf24 100%);
        }
        
        .form-section h4 {
            font-weight: 700;
            font-size: 1.25rem;
            color: var(--primary-dark);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        
        .room-card {
            background: var(--white);
            border: 2px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1.5rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        
        .room-card:hover {
            border-color: var(--secondary-main);
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            transform: translateY(-2px);
        }
        
        .room-card.selected {
            border-color: var(--secondary-main);
            background: linear-gradient(135deg, rgba(245, 158, 11, 0.05) 0%, rgba(245, 158, 11, 0.1) 100%);
        }
        
        .room-card.selected::after {
            content: '✓';
            position: absolute;
            top: 1rem;
            right: 1rem;
            width: 2.5rem;
            height: 2.5rem;
            background: linear-gradient(135deg, var(--secondary-main) 0%, #fbbf24 100%);
            color: var(--white);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 1.2rem;
        }
        
        .room-unavailable {
            opacity: 0.6;
            cursor: not-allowed !important;
        }
        
        .room-unavailable .room-card {
            pointer-events: none;
            background-color: #f5f5f5;
        }
        
        .room-image {
            width: 100%;
            height: 150px;
            object-fit: cover;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
        }
        
        /* Price Summary Sidebar - OLD DESIGN */
        .price-summary {
            background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-main) 100%);
            color: var(--white);
            border-radius: 1rem;
            padding: 2rem;
            position: sticky;
            top: 2rem;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }
        
        .price-summary h5 {
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .price-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.875rem 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            font-size: 0.95rem;
        }
        
        .price-item:last-of-type {
            border-bottom: 2px solid rgba(255, 255, 255, 0.2);
            margin-bottom: 1rem;
        }
        
        .price-item .label {
            color: rgba(255, 255, 255, 0.8);
        }
        
        .price-item .value {
            font-weight: 600;
            color: var(--white);
        }
        
        .price-total-section {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 1.25rem;
            margin-top: 1rem;
        }
        
        .price-total-section .total-label {
            font-size: 1.1rem;
            font-weight: 600;
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 0.5rem;
        }
        
        .price-total {
            font-size: 2.25rem;
            font-weight: 800;
            color: var(--secondary-main);
            text-shadow: 0 2px 4px rgba(0, 0, 0, 0.2);
        }
        
        .summary-icon {
            font-size: 1.25rem;
            color: var(--secondary-main);
        }
        
        /* Custom Dropdown Style */
        .custom-select-wrapper {
            position: relative;
        }
        
        .custom-select {
            appearance: none;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.75rem 2.5rem 0.75rem 1rem;
            font-size: 1rem;
            font-weight: 500;
            color: #334155;
            width: 100%;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .custom-select:focus {
            outline: none;
            border-color: var(--secondary-main);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.1);
        }
        
        .custom-select option {
            padding: 0.75rem;
        }
        
        .custom-select-wrapper::after {
            content: '▼';
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            pointer-events: none;
            color: #64748b;
            font-size: 0.75rem;
        }
        
        .btn-premium {
            font-weight: 600;
            border: none;
            border-radius: 0.75rem;
            padding: 0.875rem 2rem;
            transition: all 0.15s;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }
        
        .btn-primary-premium {
            background: linear-gradient(135deg, #8b5cf6 0%, #a78bfa 100%);
            color: var(--white);
        }
        
        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            color: var(--white);
        }
        
        .loading-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }
        
        .loading-overlay.show {
            display: flex;
        }
        
        .loading-spinner {
            width: 50px;
            height: 50px;
            border: 5px solid var(--white);
            border-top-color: var(--secondary-main);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        @media (max-width: 768px) {
            .sidebar { transform: translateX(-100%); }
            .main-content { margin-left: 0; }
            .price-summary { position: relative; margin-top: 2rem; }
        }
    </style>
</head>
<body>
    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="loading-spinner"></div>
    </div>
    
    <!-- Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h5 class="mb-0"><i class="bi bi-building-fill"></i> Monbela Hotel</h5>
            <small>Admin Dashboard</small>
        </div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="../admin_dashboard.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <a class="nav-link" href="manage_reservations.php">
                <i class="bi bi-calendar-check"></i> Manage Reservations
            </a>
            <a class="nav-link active" href="add_reservation.php">
                <i class="bi bi-calendar-plus-fill"></i> Add Reservation
            </a>
            <a class="nav-link" href="../room/manage_rooms.php">
                <i class="bi bi-door-closed"></i> Manage Rooms
            </a>
        </nav>
    </aside>
    
    <main class="main-content">
        <!-- Page Header -->
        <div class="mb-4">
            <h2><i class="bi bi-calendar-plus-fill"></i> Add New Reservation</h2>
            <p class="text-muted mb-0">Create a new hotel reservation</p>
        </div>
        
        <!-- Alerts -->
        <?php if (empty($rooms)): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <strong>No rooms available!</strong> Please add rooms first.
                <a href="../room/add_room.php" class="btn btn-sm btn-danger ms-2">Add Room</a>
            </div>
        <?php endif; ?>
        
        <?php if ($successMsg): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <?= htmlspecialchars($successMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($errorMsg): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <?= htmlspecialchars($errorMsg) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="" id="reservationForm">
            <div class="row">
                <div class="col-lg-8">
                    <!-- Guest Information -->
                    <div class="form-section">
                        <h4><i class="bi bi-person-fill"></i> Guest Information</h4>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="guest_id" class="form-label">Select Guest <span class="text-danger">*</span></label>
                                <select class="form-control" id="guest_id" name="guest_id" required>
                                    <option value="">Choose a guest...</option>
                                    <?php foreach ($guests as $guest): ?>
                                        <option value="<?= $guest['GUESTID'] ?>">
                                            <?= htmlspecialchars($guest['G_FNAME'] . ' ' . $guest['G_LNAME']) ?> 
                                            (<?= htmlspecialchars($guest['G_PHONE']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="mt-2">
                                    <a href="../Guest/Add_guest.php" class="btn btn-sm btn-secondary">
                                        <i class="bi bi-person-plus"></i> Add New Guest
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stay Details -->
                    <div class="form-section">
                        <h4><i class="bi bi-calendar-check"></i> Stay Details</h4>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="arrival_date" class="form-label">Check-in Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="arrival_date" 
                                       name="arrival_date" required min="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-md-6">
                                <label for="departure_date" class="form-label">Check-out Date <span class="text-danger">*</span></label>
                                <input type="date" class="form-control" id="departure_date" 
                                       name="departure_date" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <!-- Room Selection -->
                    <div class="form-section">
                        <h4><i class="bi bi-door-open"></i> Select Room <span class="text-danger">*</span></h4>
                        <div class="row g-3" id="roomsContainer">
                            <?php foreach ($rooms as $room): 
                                $roomId = $room['ROOMID'] ?? $room['roomid'];
                                $roomNum = $room['ROOMNUM'] ?? $room['roomnum'];
                                $roomName = $room['ROOM'] ?? $room['room'];
                                $roomDesc = $room['ROOMDESC'] ?? $room['roomdesc'] ?? '';
                                $capacity = $room['NUMPERSON'] ?? $room['numperson'] ?? 0;
                                $price = $room['PRICE'] ?? $room['price'] ?? 0;
                                $oroomnum = $room['OROOMNUM'] ?? $room['oroomnum'] ?? 1;
                                $available = $oroomnum > 0;
                                $roomImage = $room['ROOMIMAGE'] ?? $room['roomimage'] ?? '';
                            ?>
                            <div class="col-md-6 col-lg-4 <?= !$available ? 'room-unavailable' : '' ?>">
                                <div class="room-card" 
                                     data-room-id="<?= $roomId ?>" 
                                     data-price="<?= $price ?>"
                                     data-room-name="<?= htmlspecialchars($roomName) ?>"
                                     data-available="<?= $available ? '1' : '0' ?>">
                                    <?php if ($roomImage): ?>
                                        <img src="../../uploads/rooms/<?= htmlspecialchars($roomImage) ?>" 
                                             class="room-image" alt="Room" onerror="this.style.display='none'">
                                    <?php else: ?>
                                        <div class="room-image bg-secondary d-flex align-items-center justify-content-center">
                                            <i class="bi bi-image text-white" style="font-size: 3rem;"></i>
                                        </div>
                                    <?php endif; ?>
                                    <h5 class="mb-1"><?= htmlspecialchars($roomName) ?></h5>
                                    <p class="text-muted small mb-2">Room #<?= htmlspecialchars($roomNum) ?></p>
                                    <p class="mb-2"><?= htmlspecialchars(substr($roomDesc, 0, 50)) ?>...</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span style="font-size: 1.5rem; font-weight: 800; color: var(--success);">
                                            ₱<?= number_format($price, 2) ?>
                                        </span>
                                        <span class="badge bg-secondary">
                                            <i class="bi bi-people"></i> <?= $capacity ?>
                                        </span>
                                    </div>
                                    <div class="mt-2">
                                        <?php if ($available): ?>
                                            <span class="badge bg-success">Available: <?= $oroomnum ?></span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Not Available</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <input type="hidden" name="room_id" id="selected_room_id" required>
                    </div>
                    
                    <!-- Booking Purpose & Additional Info -->
                    <div class="form-section">
                        <h4><i class="bi bi-info-circle"></i> Additional Information</h4>
                        <div class="row g-3">
                            <div class="col-12">
                                <label for="purpose" class="form-label">
                                    <i class="bi bi-bookmark-fill"></i> Booking Reason <span class="text-danger">*</span>
                                </label>
                                <div class="custom-select-wrapper">
                                    <select class="custom-select" id="purpose" name="purpose" required>
                                        <option value="">Select a reason</option>
                                        <option value="Vacation">Vacation</option>
                                        <option value="Business">Business</option>
                                        <option value="Family Visit">Family Visit</option>
                                        <option value="Event">Event</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>
                            </div>
                            <div class="col-12">
                                <label for="special_instructions" class="form-label">Special Instructions / Remarks</label>
                                <textarea class="form-control" id="special_instructions" 
                                          name="special_instructions" rows="3" 
                                          placeholder="Any special requests or notes..."></textarea>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Form Actions -->
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-premium btn-primary-premium">
                            <i class="bi bi-check-circle"></i> Create Reservation
                        </button>
                        <a href="manage_reservations.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                    </div>
                </div>
                
                <!-- Price Summary Sidebar (OLD DESIGN) -->
                <div class="col-lg-4">
                    <div class="price-summary">
                        <h5><i class="bi bi-receipt summary-icon"></i> Booking Summary</h5>
                        
                        <div class="price-item">
                            <span class="label">Room Type:</span>
                            <span class="value" id="summary-room">Not selected</span>
                        </div>
                        
                        <div class="price-item">
                            <span class="label">Check-in:</span>
                            <span class="value" id="summary-checkin">-</span>
                        </div>
                        
                        <div class="price-item">
                            <span class="label">Check-out:</span>
                            <span class="value" id="summary-checkout">-</span>
                        </div>
                        
                        <div class="price-item">
                            <span class="label">Number of Nights:</span>
                            <span class="value" id="summary-nights">0</span>
                        </div>
                        
                        <div class="price-item">
                            <span class="label">Room Rate (per night):</span>
                            <span class="value" id="summary-rate">₱0.00</span>
                        </div>
                        
                        <div class="price-total-section">
                            <div class="total-label">Total Amount</div>
                            <div class="price-total" id="summary-total">₱0.00</div>
                        </div>
                        
                        <div class="mt-3 pt-3 border-top border-white border-opacity-10">
                            <small class="text-white-50 d-block">
                                <i class="bi bi-info-circle"></i> Final price will be confirmed upon booking
                            </small>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jquery@3.6.0/dist/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            console.log('Reservation form initialized');
            
            // Initialize Select2 for guest selection
            $('#guest_id').select2({
                placeholder: 'Search and select a guest',
                allowClear: true
            });
            
            // Room selection
            let selectedRoom = null;
            const roomCards = document.querySelectorAll('.room-card');
            
            roomCards.forEach(card => {
                card.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    console.log('Room card clicked:', this);
                    
                    // Check if room is in unavailable container
                    const parent = this.closest('.room-unavailable');
                    if (parent) {
                        alert('This room is not available');
                        return;
                    }
                    
                    // Check if available from data attribute
                    if (this.dataset.available === '0') {
                        alert('This room is not available');
                        return;
                    }
                    
                    // Remove previous selection
                    roomCards.forEach(c => c.classList.remove('selected'));
                    
                    // Add selection to this card
                    this.classList.add('selected');
                    
                    // Update selected room
                    selectedRoom = {
                        id: this.dataset.roomId,
                        price: parseFloat(this.dataset.price),
                        name: this.dataset.roomName
                    };
                    
                    console.log('Selected room:', selectedRoom);
                    console.log('Hidden input will be set to:', selectedRoom.id);
                    
                    // Update hidden input
                    const hiddenInput = document.getElementById('selected_room_id');
                    hiddenInput.value = selectedRoom.id;
                    
                    console.log('Hidden input value now:', hiddenInput.value);
                    
                    // Update summary
                    updateSummary();
                });
            });
            
            // Date change listeners
            document.getElementById('arrival_date').addEventListener('change', updateSummary);
            document.getElementById('departure_date').addEventListener('change', updateSummary);
            
            // Update summary function
            function updateSummary() {
                const arrivalDate = document.getElementById('arrival_date').value;
                const departureDate = document.getElementById('departure_date').value;
                
                // Update room info
                document.getElementById('summary-room').textContent = selectedRoom ? selectedRoom.name : 'Not selected';
                
                // Update dates
                document.getElementById('summary-checkin').textContent = arrivalDate || '-';
                document.getElementById('summary-checkout').textContent = departureDate || '-';
                
                // Calculate nights
                let nights = 0;
                if (arrivalDate && departureDate) {
                    const arrival = new Date(arrivalDate);
                    const departure = new Date(departureDate);
                    nights = Math.ceil((departure - arrival) / (1000 * 60 * 60 * 24));
                    nights = nights > 0 ? nights : 0;
                }
                document.getElementById('summary-nights').textContent = nights;
                
                // Update price
                const roomRate = selectedRoom ? selectedRoom.price : 0;
                document.getElementById('summary-rate').textContent = `₱${roomRate.toFixed(2)}`;
                
                // Calculate total
                const total = roomRate * nights;
                document.getElementById('summary-total').textContent = `₱${total.toFixed(2)}`;
            }
            
            // Form validation
            document.getElementById('reservationForm').addEventListener('submit', function(e) {
                console.log('Form submitting...');
                console.log('Selected room:', selectedRoom);
                console.log('Hidden input value:', document.getElementById('selected_room_id').value);
                
                if (!selectedRoom || !document.getElementById('selected_room_id').value) {
                    e.preventDefault();
                    alert('Please select a room for the reservation.');
                    
                    // Scroll to room selection
                    document.querySelector('#roomsContainer').scrollIntoView({ 
                        behavior: 'smooth', 
                        block: 'center' 
                    });
                    return false;
                }
                
                const purpose = document.getElementById('purpose').value;
                if (!purpose) {
                    e.preventDefault();
                    alert('Please select a booking reason.');
                    document.getElementById('purpose').focus();
                    return false;
                }
                
                console.log('Form validation passed, submitting...');
                document.getElementById('loadingOverlay').classList.add('show');
            });
            
            // Debug: Log initial state
            console.log('Total room cards found:', roomCards.length);
            console.log('Room cards:', roomCards);
        });
    </script>
</body>
</html>