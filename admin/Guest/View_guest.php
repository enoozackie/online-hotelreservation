<?php
// =============================================================================
// View_guest.php - View Guest Details with Bookings and Payments
// =============================================================================
session_start();

// Enhanced Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Correct path to autoload.php
require __DIR__ . '/../../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Admin;

// Check authentication
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit;
}

// Get guest ID from URL
$guestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$guestId) {
    header("Location: Manage_guest.php?error=Invalid guest ID");
    exit;
}

$admin = new Admin();

// Get guest details
$guest = $admin->getGuestById($guestId);
if (!$guest) {
    header("Location: Manage_guest.php?error=Guest not found");
    exit;
}

// Get guest bookings - Initialize as empty array first
$bookings = [];
try {
    $bookings = $admin->getGuestBookings($guestId);
    if (!is_array($bookings)) {
        $bookings = [];
    }
} catch (Exception $e) {
    error_log("Error fetching bookings: " . $e->getMessage());
    $bookings = [];
}

// Get guest payments - Initialize as empty array first
$payments = [];
try {
    $payments = $admin->getGuestPayments($guestId);
    if (!is_array($payments)) {
        $payments = [];
    }
} catch (Exception $e) {
    error_log("Error fetching payments: " . $e->getMessage());
    $payments = [];
}

// Calculate statistics with safe array access
$totalBookings = count($bookings);
$activeBookings = 0;

foreach ($bookings as $b) {
    $status = isset($b['STATUS']) ? $b['STATUS'] : '';
    if (in_array($status, ['Confirmed', 'Pending'])) {
        $activeBookings++;
    }
}

// Calculate total spent from payments or bookings
$totalSpent = 0;
if (!empty($payments)) {
    foreach ($payments as $payment) {
        $totalSpent += floatval($payment['SPRICE'] ?? 0);
    }
} else {
    // Fallback: calculate from confirmed bookings
    foreach ($bookings as $booking) {
        $status = isset($booking['STATUS']) ? $booking['STATUS'] : '';
        if (in_array($status, ['Confirmed', 'Checked Out', 'CheckedOut'])) {
            try {
                $arrival = isset($booking['ARRIVAL']) ? $booking['ARRIVAL'] : date('Y-m-d');
                $departure = isset($booking['DEPARTURE']) ? $booking['DEPARTURE'] : date('Y-m-d');
                $checkin = new DateTime($arrival);
                $checkout = new DateTime($departure);
                $nights = $checkin->diff($checkout)->days;
                $price = isset($booking['price']) ? floatval($booking['price']) : 0;
                $totalSpent += $price * $nights;
            } catch (Exception $e) {
                // Skip if date parsing fails
                continue;
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Guest - <?= htmlspecialchars($guest['G_FNAME'] . ' ' . $guest['G_LNAME']) ?></title>
    
    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --accent-color: #e74c3c;
            --success-color: #2ecc71;
            --warning-color: #f39c12;
            --light-color: #f8f9fa;
            --dark-color: #2c3e50;
            --border-color: #dee2e6;
            --shadow: 0 2px 10px rgba(0,0,0,0.1);
            --radius: 8px;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f5f7fa;
            color: #333;
            font-size: 14px;
        }

        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        .sidebar {
            width: 250px;
            background: linear-gradient(180deg, #2c3e50 0%, #1a2530 100%);
            color: white;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            margin-bottom: 20px;
        }

        .sidebar-header h3 {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 18px;
        }

        .sidebar-menu {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .sidebar-menu li {
            margin-bottom: 5px;
        }

        .sidebar-menu a {
            color: rgba(255,255,255,0.9);
            text-decoration: none;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover {
            background: rgba(255,255,255,0.1);
            color: white;
            border-left-color: var(--secondary-color);
        }

        .sidebar-menu a.active {
            background: rgba(255,255,255,0.15);
            color: white;
            border-left-color: var(--secondary-color);
            font-weight: 600;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            background: #f5f7fa;
        }

        .page-header {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }

        .guest-profile {
            background: white;
            border-radius: var(--radius);
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
        }

        .guest-avatar-large {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-color), #1abc9c);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 36px;
            margin-bottom: 20px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .info-item {
            display: flex;
            align-items: start;
        }

        .info-item i {
            color: var(--secondary-color);
            margin-right: 10px;
            margin-top: 3px;
        }

        .info-item-label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 3px;
        }

        .info-item-value {
            font-weight: 500;
            color: var(--dark-color);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 20px;
        }

        .stat-box {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            border-left: 4px solid var(--secondary-color);
        }

        .stat-value {
            font-size: 28px;
            font-weight: 700;
            color: var(--dark-color);
        }

        .stat-label {
            font-size: 13px;
            color: #6c757d;
            margin-top: 5px;
        }

        .card {
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 15px 20px;
            font-weight: 600;
        }

        .table {
            margin: 0;
        }

        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid var(--border-color);
            padding: 12px 15px;
            font-weight: 600;
            font-size: 13px;
        }

        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 500;
        }

        .badge-confirmed {
            background: #d4edda;
            color: #155724;
        }

        .badge-pending {
            background: #fff3cd;
            color: #856404;
        }

        .badge-cancelled {
            background: #f8d7da;
            color: #721c24;
        }

        .badge-paid {
            background: #d1ecf1;
            color: #0c5460;
        }

        .btn-primary {
            background: var(--secondary-color);
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 500;
        }

        .btn-outline-primary {
            border: 1px solid var(--secondary-color);
            color: var(--secondary-color);
        }

        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 15px;
            left: 15px;
            z-index: 1001;
            background: var(--secondary-color);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 6px;
        }

        @media (max-width: 992px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }

            .sidebar.active {
                transform: translateX(0);
            }

            .main-content {
                margin-left: 0;
                padding: 15px;
            }

            .mobile-menu-toggle {
                display: flex;
                align-items: center;
                justify-content: center;
            }
        }

        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state-icon {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="bi bi-list"></i>
    </button>

    <div class="admin-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <h3>Monbela Hotel</h3>
                <p>Administration Panel</p>
            </div>
            
            <ul class="sidebar-menu">
                <li>
                    <a href="../admin_dashboard.php">
                        <i class="bi bi-speedometer2"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="Manage_guest.php" class="active">
                        <i class="bi bi-people-fill"></i>
                        Manage Guests
                    </a>
                </li>
                <li>
                    <a href="Add_guest.php">
                        <i class="bi bi-person-plus"></i>
                        Add Guest
                    </a>
                </li>
                <li>
                    <a href="../room/manage_rooms.php">
                        <i class="bi bi-door-closed"></i>
                        Manage Rooms
                    </a>
                </li>
                <li>
                    <a href="../reservations/manage_reservations.php">
                        <i class="bi bi-calendar-check"></i>
                        Reservations
                    </a>
                </li>
                <li>
                    <a href="../payments/manage_payments.php">
                        <i class="bi bi-credit-card"></i>
                        Payments
                    </a>
                </li>
                <li>
                    <a href="../reports/reports.php">
                        <i class="bi bi-graph-up"></i>
                        Reports
                    </a>
                </li>
                <li>
                    <a href="../settings/settings.php">
                        <i class="bi bi-gear"></i>
                        Settings
                    </a>
                </li>
            </ul>
            
            <div style="padding: 20px; border-top: 1px solid rgba(255,255,255,0.1); margin-top: 20px;">
                <a href="../../public/admin_logout.php" class="btn btn-sm btn-outline-light w-100">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <a href="Manage_guest.php" class="text-decoration-none text-muted">
                            <i class="bi bi-arrow-left"></i> Back to Guests
                        </a>
                        <h1 class="mt-2"><i class="bi bi-person-circle me-2"></i>Guest Details</h1>
                    </div>
                    <div>
                        <a href="Edit_guest.php?id=<?= $guestId ?>" class="btn btn-primary">
                            <i class="bi bi-pencil"></i> Edit Guest
                        </a>
                    </div>
                </div>
            </div>

            <!-- Stats Row -->
            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-value"><?= number_format($totalBookings) ?></div>
                    <div class="stat-label">Total Bookings</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value"><?= number_format($activeBookings) ?></div>
                    <div class="stat-label">Active Bookings</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">₱<?= number_format($totalSpent, 2) ?></div>
                    <div class="stat-label">Total Spent</div>
                </div>
            </div>

            <!-- Guest Profile -->
            <div class="guest-profile">
                <div class="d-flex align-items-start">
                    <div class="guest-avatar-large">
                        <?= strtoupper(substr($guest['G_FNAME'], 0, 1) . substr($guest['G_LNAME'], 0, 1)) ?>
                    </div>
                    <div class="flex-grow-1 ms-3">
                        <h2 class="mb-1"><?= htmlspecialchars($guest['G_FNAME'] . ' ' . $guest['G_LNAME']) ?></h2>
                        <p class="text-muted mb-3">Guest ID: #<?= str_pad($guest['GUESTID'], 5, '0', STR_PAD_LEFT) ?></p>
                        
                        <div class="info-grid">
                            <div class="info-item">
                                <i class="bi bi-envelope-fill"></i>
                                <div>
                                    <div class="info-item-label">Email / Username</div>
                                    <div class="info-item-value"><?= htmlspecialchars($guest['G_UNAME']) ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="bi bi-telephone-fill"></i>
                                <div>
                                    <div class="info-item-label">Phone Number</div>
                                    <div class="info-item-value"><?= htmlspecialchars($guest['G_PHONE']) ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="bi bi-calendar-fill"></i>
                                <div>
                                    <div class="info-item-label">Date of Birth</div>
                                    <div class="info-item-value">
                                        <?= $guest['DBIRTH'] ? date('F j, Y', strtotime($guest['DBIRTH'])) : 'Not specified' ?>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="bi bi-flag-fill"></i>
                                <div>
                                    <div class="info-item-label">Nationality</div>
                                    <div class="info-item-value"><?= htmlspecialchars($guest['G_NATIONALITY'] ?? 'Not specified') ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <i class="bi bi-geo-alt-fill"></i>
                                <div>
                                    <div class="info-item-label">Address</div>
                                    <div class="info-item-value">
                                        <?= htmlspecialchars($guest['G_ADDRESS'] ?? 'Not specified') ?>
                                        <?php if (!empty($guest['G_CITY'])): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($guest['G_CITY']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                            
                            <?php if (!empty($guest['G_COMPANY'])): ?>
                            <div class="info-item">
                                <i class="bi bi-building-fill"></i>
                                <div>
                                    <div class="info-item-label">Company</div>
                                    <div class="info-item-value"><?= htmlspecialchars($guest['G_COMPANY']) ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Bookings Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-calendar-check me-2"></i>Booking History</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($bookings)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-calendar-x"></i>
                            </div>
                            <h5>No Bookings Found</h5>
                            <p class="text-muted">This guest has no booking history yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Room</th>
                                        <th>Check-in</th>
                                        <th>Check-out</th>
                                        <th>Nights</th>
                                        <th>Price</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($bookings as $booking): ?>
                                        <?php
                                        // Safe data extraction with defaults
                                        $arrival = isset($booking['ARRIVAL']) ? $booking['ARRIVAL'] : date('Y-m-d');
                                        $departure = isset($booking['DEPARTURE']) ? $booking['DEPARTURE'] : date('Y-m-d');
                                        $status = isset($booking['STATUS']) ? $booking['STATUS'] : 'Unknown';
                                        $roomName = isset($booking['room_name']) ? $booking['room_name'] : 'Unknown Room';
                                        $roomNumber = isset($booking['room_number']) ? $booking['room_number'] : 'N/A';
                                        $price = isset($booking['price']) ? floatval($booking['price']) : 0;
                                        
                                        try {
                                            $checkin = new DateTime($arrival);
                                            $checkout = new DateTime($departure);
                                            $nights = max(0, $checkin->diff($checkout)->days);
                                        } catch (Exception $e) {
                                            $nights = 0;
                                        }
                                        
                                        // Determine status badge class
                                        $statusClass = 'badge-pending';
                                        if ($status === 'Confirmed') {
                                            $statusClass = 'badge-confirmed';
                                        } elseif (in_array($status, ['Cancelled', 'Canceled'])) {
                                            $statusClass = 'badge-cancelled';
                                        } elseif (in_array($status, ['Checked Out', 'CheckedOut'])) {
                                            $statusClass = 'badge-confirmed';
                                        }
                                        ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($roomName) ?></strong>
                                                <br><small class="text-muted">Room #<?= htmlspecialchars($roomNumber) ?></small>
                                            </td>
                                            <td><?= date('M j, Y', strtotime($arrival)) ?></td>
                                            <td><?= date('M j, Y', strtotime($departure)) ?></td>
                                            <td><?= $nights ?> night<?= $nights != 1 ? 's' : '' ?></td>
                                            <td><strong>₱<?= number_format($price * $nights, 2) ?></strong></td>
                                            <td><span class="badge <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payments Section -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0"><i class="bi bi-credit-card me-2"></i>Payment History</h5>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($payments)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <i class="bi bi-credit-card"></i>
                            </div>
                            <h5>No Payments Found</h5>
                            <p class="text-muted">This guest has no payment history yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead>
                                    <tr>
                                        <th>Date</th>
                                        <th>Description</th>
                                        <th>Method</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($payments as $payment): ?>
                                        <?php
                                        // Safely get values with defaults
                                        $transDate = isset($payment['TRANSDATE']) ? $payment['TRANSDATE'] : date('Y-m-d');
                                        $description = isset($payment['PAYMENT_DESC']) ? $payment['PAYMENT_DESC'] : (isset($payment['STATUS']) ? $payment['STATUS'] : 'Room Payment');
                                        $confirmCode = isset($payment['CONFIRMATIONCODE']) ? $payment['CONFIRMATIONCODE'] : '';
                                        $method = isset($payment['PAYMENT_METHOD']) ? $payment['PAYMENT_METHOD'] : 'Cash';
                                        $amount = isset($payment['SPRICE']) ? floatval($payment['SPRICE']) : 0;
                                        ?>
                                        <tr>
                                            <td><?= date('M j, Y', strtotime($transDate)) ?></td>
                                            <td>
                                                <?= htmlspecialchars($description) ?>
                                                <?php if (!empty($confirmCode)): ?>
                                                    <br><small class="text-muted"><?= htmlspecialchars($confirmCode) ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    <?= htmlspecialchars($method) ?>
                                                </span>
                                            </td>
                                            <td><strong>₱<?= number_format($amount, 2) ?></strong></td>
                                            <td><span class="badge badge-paid">Paid</span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Sidebar toggle
        document.getElementById('mobileMenuToggle')?.addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });
    </script>
</body>
</html>