<?php
// =============================================================================
// admin/reservations/edit_reservation.php - Admin Edit Reservation Page
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

// Initialize database connection and model
$database = new Database();
$db_link = $database->getConnection();
$reservationModel = new Reservation($db_link);

// Get reservation ID from the URL
$reserveId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$reserveId) {
    $_SESSION['error_message'] = "Invalid Reservation ID.";
    header("Location: manage_reservations.php");
    exit;
}

// --- Handle Form Submission ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and retrieve form data
    $data = [
        'ROOMID' => filter_input(INPUT_POST, 'room_id', FILTER_VALIDATE_INT),
        'ARRIVAL' => filter_input(INPUT_POST, 'arrival_date', FILTER_SANITIZE_STRING),
        'DEPARTURE' => filter_input(INPUT_POST, 'departure_date', FILTER_SANITIZE_STRING),
        'REMARKS' => filter_input(INPUT_POST, 'remarks', FILTER_SANITIZE_STRING),
        'PRORPOSE' => filter_input(INPUT_POST, 'purpose', FILTER_SANITIZE_STRING)
    ];

    // Basic server-side validation
    if (empty($data['ARRIVAL']) || empty($data['DEPARTURE'])) {
        $_SESSION['error_message'] = "Arrival and Departure dates are required.";
    } elseif (strtotime($data['DEPARTURE']) <= strtotime($data['ARRIVAL'])) {
        $_SESSION['error_message'] = "Departure date must be after the arrival date.";
    } elseif (!$data['ROOMID']) {
        $_SESSION['error_message'] = "Please select a valid room.";
    } else {
        // Attempt to update the reservation
        if ($reservationModel->updateReservation($reserveId, $data)) {
            $_SESSION['success_message'] = "Reservation updated successfully!";
            header("Location: view_reservation.php?id=" . $reserveId);
            exit;
        } else {
            $_SESSION['error_message'] = "Failed to update the reservation. The room might not be available for the selected dates, or an error occurred.";
        }
    }
}

// --- Fetch Data for the Form ---

// Get the current reservation details
$reservation = $reservationModel->getById($reserveId);

if (!$reservation) {
    $_SESSION['error_message'] = "Reservation not found.";
    header("Location: manage_reservations.php");
    exit;
}

// Get all rooms for the dropdown
$rooms = [];
$sql = "SELECT ROOMID, CONCAT(ROOM, ' - #', ROOMNUM, ' (Price: ', PRICE, '/night)') AS room_details FROM tblroom ORDER BY ROOM, ROOMNUM";
$result = $db_link->query($sql);
if ($result) {
    $rooms = $result->fetch_all(MYSQLI_ASSOC);
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Reservation - Admin Panel - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1a365d;
            --primary-main: #2d3748;
            --secondary-dark: #b7791f;
            --secondary-main: #d69e2e;
            --gradient-primary: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-main) 100%);
            --gradient-secondary: linear-gradient(135deg, var(--secondary-dark) 0%, var(--secondary-main) 100%);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --radius-lg: 0.75rem;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
        }
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        .sidebar {
            width: 280px;
            background: var(--gradient-primary);
            color: white;
            padding: 2rem 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: transform 0.3s ease;
        }
        .sidebar-header {
            padding: 0 1.5rem 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 2rem;
        }
        .sidebar-header h3 {
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .sidebar-header p {
            opacity: 0.8;
            margin: 0;
            font-size: 0.9rem;
        }
        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 0.5rem;
        }
        .sidebar-menu a {
            color: white;
            text-decoration: none;
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-left: 3px solid transparent;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background: rgba(255, 255, 255, 0.1);
            border-left-color: var(--secondary-main);
        }
        .sidebar-menu i {
            margin-right: 1rem;
            font-size: 1.2rem;
        }
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }
        .content-header {
            background: white;
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
        }
        .content-header h1 {
            font-size: 2rem;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
        }
        .form-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            padding: 2rem;
        }
        .form-label {
            font-weight: 600;
            color: var(--primary-dark);
        }
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1rem;
            left: 1rem;
            z-index: 1001;
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 0.75rem;
            border-radius: 0.5rem;
            font-size: 1.5rem;
            cursor: pointer;
        }
        .alert {
            border-radius: var(--radius-lg);
            border: none;
            margin-bottom: 1.5rem;
        }
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.active {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
                padding: 1rem;
                padding-top: 4rem;
            }
            .mobile-menu-toggle {
                display: block;
            }
        }
        .form-control:read-only {
            background-color: #f8f9fa;
            cursor: not-allowed;
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>
    
    <div class="dashboard-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Admin Panel</h3>
                <p>Monbela Hotel</p>
            </div>
            <ul class="sidebar-menu">
                <li><a href="../admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li><a href="../Guest/Manage_guest.php"><i class="bi bi-people"></i> Manage Guests</a></li>
                <li><a href="../room/manage_rooms.php"><i class="bi bi-door-closed"></i> Manage Rooms</a></li>
                <li><a href="manage_reservations.php" class="active"><i class="bi bi-calendar-check"></i> Manage Reservations</a></li>
                <li><a href="pending_reservations.php"><i class="bi bi-clock-history"></i> Pending Reservations</a></li>
                <li><a href="../../public/admin_logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <h1>Edit Reservation #<?= htmlspecialchars($reservation['RESERVEID']) ?></h1>
                <p>Modify the details for this reservation.</p>
            </div>

            <?php if (isset($_SESSION['error_message'])): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <?= htmlspecialchars($_SESSION['error_message']) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php unset($_SESSION['error_message']); ?>
            <?php endif; ?>

            <div class="form-container">
                <form action="edit_reservation.php?id=<?= $reserveId ?>" method="POST">
                    <div class="row g-3">
                        <!-- Guest Information (Read-only) -->
                        <div class="col-md-6">
                            <label for="guest_name" class="form-label">Guest Name</label>
                            <input type="text" class="form-control" id="guest_name" 
                                   value="<?= htmlspecialchars(($reservation['G_FNAME'] ?? '') . ' ' . ($reservation['G_LNAME'] ?? '')) ?>" readonly>
                        </div>
                        
                        <!-- Room Selection -->
                        <div class="col-md-6">
                            <label for="room_id" class="form-label">Room <span class="text-danger">*</span></label>
                            <select class="form-select" id="room_id" name="room_id" required>
                                <option value="">Select a Room...</option>
                                <?php foreach ($rooms as $room): ?>
                                    <option value="<?= $room['ROOMID'] ?>" 
                                        <?= ($reservation['ROOMID'] == $room['ROOMID']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($room['room_details']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Dates -->
                        <div class="col-md-6">
                            <label for="arrival_date" class="form-label">Check-in Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="arrival_date" name="arrival_date" 
                                   value="<?= htmlspecialchars($reservation['ARRIVAL']) ?>" required min="<?= date('Y-m-d') ?>">
                        </div>
                        <div class="col-md-6">
                            <label for="departure_date" class="form-label">Check-out Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" id="departure_date" name="departure_date" 
                                   value="<?= htmlspecialchars($reservation['DEPARTURE']) ?>" required min="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                        </div>
                        
                        <!-- Purpose and Remarks -->
                        <div class="col-md-6">
                            <label for="purpose" class="form-label">Purpose</label>
                            <select class="form-select" id="purpose" name="purpose">
                                <option value="Leisure" <?= ($reservation['PRORPOSE'] ?? 'Leisure') === 'Leisure' ? 'selected' : '' ?>>Leisure</option>
                                <option value="Business" <?= ($reservation['PRORPOSE'] ?? '') === 'Business' ? 'selected' : '' ?>>Business</option>
                                <option value="Event" <?= ($reservation['PRORPOSE'] ?? '') === 'Event' ? 'selected' : '' ?>>Event</option>
                                <option value="Online Booking" <?= ($reservation['PRORPOSE'] ?? '') === 'Online Booking' ? 'selected' : '' ?>>Online Booking</option>
                            </select>
                        </div>
                        <div class="col-md-6">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3"><?= htmlspecialchars($reservation['REMARKS'] ?? '') ?></textarea>
                        </div>

                        <!-- Form Actions -->
                        <div class="col-12 mt-4">
                            <button type="submit" class="btn btn-primary">
                                <i class="bi bi-check-circle"></i> Save Changes
                            </button>
                            <a href="view_reservation.php?id=<?= $reserveId ?>" class="btn btn-secondary">
                                <i class="bi bi-arrow-left"></i> Back to View
                            </a>
                            <a href="manage_reservations.php" class="btn btn-outline-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        // Date validation
        document.addEventListener('DOMContentLoaded', function() {
            const arrivalDate = document.getElementById('arrival_date');
            const departureDate = document.getElementById('departure_date');
            
            arrivalDate.addEventListener('change', function() {
                const minDeparture = new Date(this.value);
                minDeparture.setDate(minDeparture.getDate() + 1);
                departureDate.min = minDeparture.toISOString().split('T')[0];
                
                if (departureDate.value && new Date(departureDate.value) <= new Date(this.value)) {
                    departureDate.value = '';
                }
            });
            
            departureDate.addEventListener('change', function() {
                if (this.value && new Date(this.value) <= new Date(arrivalDate.value)) {
                    alert('Check-out date must be after check-in date');
                    this.value = '';
                }
            });
        });
    </script>
</body>
</html> 