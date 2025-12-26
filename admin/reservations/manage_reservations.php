<?php
// =============================================================================
// admin/reservations/manage_reservations.php - Admin Manage Reservations Page
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

try {
    // Initialize database connection
    $database = new Database();
    $db_link = $database->getConnection();

    // Instantiate the Reservation model
    $reservationModel = new Reservation($db_link);

    // Get all reservations
    $reservations = $reservationModel->getAll();

    // Count pending reservations for badge
    $pendingCount = 0;
    foreach ($reservations as $res) {
        if (isset($res['STATUS']) && $res['STATUS'] === 'Pending') {
            $pendingCount++;
        }
    }
} catch (Exception $e) {
    $error_message = "Database error: " . $e->getMessage();
    $reservations = [];
    $pendingCount = 0;
}

// Display success message if exists
$success_message = '';
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Display error message if exists
$error_message = isset($error_message) ? $error_message : '';
if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Reservations - Admin Panel - Monbela Hotel</title>
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
            position: relative;
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
            width: 20px;
            text-align: center;
        }
        .badge-notification {
            position: absolute;
            right: 1.5rem;
            background: #dc3545;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.7rem;
            font-weight: bold;
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
        .content-header p {
            color: #6c757d;
            margin: 0;
        }
        .reservations-container {
            background: white;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-lg);
            overflow: hidden;
        }
        .reservations-header {
            background: var(--gradient-primary);
            color: white;
            padding: 1.5rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .reservations-header h2 {
            margin: 0;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .table-responsive {
            padding: 1.5rem;
        }
        .table {
            margin-bottom: 0;
        }
        .table thead th {
            border-bottom: 2px solid #dee2e6;
            font-weight: 600;
            color: var(--primary-dark);
            vertical-align: middle;
        }
        .table tbody tr:hover {
            background-color: rgba(0, 0, 0, 0.02);
        }
        .status-badge {
            padding: 0.4rem 0.8rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            display: inline-flex;
            align-items: center;
            gap: 0.3rem;
        }
        .status-pending {
            background: rgba(255, 193, 7, 0.1);
            color: #ffc107;
            border: 1px solid rgba(255, 193, 7, 0.3);
        }
        .status-confirmed {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border: 1px solid rgba(40, 167, 69, 0.3);
        }
        .status-cancelled {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border: 1px solid rgba(220, 53, 69, 0.3);
        }
        .action-buttons {
            display: flex;
            gap: 0.25rem;
            flex-wrap: nowrap;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.8rem;
            width: 36px;
            height: 36px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
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
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: #28a745;
            border-left: 4px solid #28a745;
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: #dc3545;
            border-left: 4px solid #dc3545;
        }
        .stats-card {
            background: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: var(--shadow-lg);
            margin-bottom: 1.5rem;
        }
        .stats-card h5 {
            color: var(--primary-dark);
            margin-bottom: 1rem;
        }
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0;
            border-bottom: 1px solid #e9ecef;
        }
        .stat-item:last-child {
            border-bottom: none;
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
            .action-buttons {
                flex-wrap: wrap;
            }
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
                <li>
                    <a href="../admin_dashboard.php">
                        <i class="bi bi-speedometer2"></i>
                        Dashboard
                    </a>
                </li>
                <li>
                    <a href="../Guest/Manage_guest.php">
                        <i class="bi bi-people"></i>
                        Manage Guests
                    </a>
                </li>
                <li>
                    <a href="../room/manage_rooms.php">
                        <i class="bi bi-door-closed"></i>
                        Manage Rooms
                    </a>
                </li>
                <li>
                    <a href="manage_reservations.php" class="active">
                        <i class="bi bi-calendar-check"></i>
                        Manage Reservations
                    </a>
                </li>
                <li>
                    <a href="pending_reservations.php">
                        <i class="bi bi-clock-history"></i>
                        Pending Reservations
                        <?php if ($pendingCount > 0): ?>
                            <span class="badge-notification"><?= $pendingCount ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li>
                    <a href="../../public/admin_logout.php">
                        <i class="bi bi-box-arrow-right"></i>
                        Logout
                    </a>
                </li>
            </ul>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div>
                    <h1><i class="bi bi-calendar-check"></i> Manage Reservations</h1>
                    <p>View and manage all hotel reservations</p>
                </div>
                <a href="pending_reservations.php" class="btn btn-warning">
                    <i class="bi bi-clock-history"></i> Pending (<?= $pendingCount ?>)
                </a>
            </div>

            <?php if ($success_message): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($success_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($error_message): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($error_message) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Quick Stats -->
            <div class="row mb-3">
                <div class="col-md-3">
                    <div class="stats-card">
                        <h5><i class="bi bi-info-circle"></i> Total Reservations</h5>
                        <div class="stat-item">
                            <span>All Reservations:</span>
                            <strong><?= count($reservations) ?></strong>
                        </div>
                        <div class="stat-item">
                            <span>Pending:</span>
                            <span class="text-warning"><?= $pendingCount ?></span>
                        </div>
                    </div>
                </div>
                <div class="col-md-9">
                    <div class="stats-card">
                        <h5><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
                        <div class="d-flex gap-2">
                            <a href="pending_reservations.php" class="btn btn-warning btn-sm">
                                <i class="bi bi-clock-history"></i> Review Pending
                            </a>
                            <button class="btn btn-info btn-sm" onclick="filterTable('Pending')">
                                <i class="bi bi-filter"></i> Show Pending Only
                            </button>
                            <button class="btn btn-secondary btn-sm" onclick="filterTable('all')">
                                <i class="bi bi-list"></i> Show All
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <div class="reservations-container">
                <div class="reservations-header">
                    <h2><i class="bi bi-calendar-week"></i> All Reservations</h2>
                    <div class="d-flex align-items-center gap-2">
                        <input type="text" id="searchInput" class="form-control form-control-sm" 
                               placeholder="Search reservations..." style="width: 200px;">
                    </div>
                </div>

                <div class="table-responsive">
                    <?php if (empty($reservations)): ?>
                        <div class="text-center p-4">
                            <i class="bi bi-calendar-x" style="font-size: 3rem; color: #6c757d;"></i>
                            <h4 class="mt-3">No Reservations Found</h4>
                            <p>There are currently no reservations in the system.</p>
                        </div>
                    <?php else: ?>
                        <table class="table table-striped table-hover" id="reservationsTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Guest</th>
                                    <th>Room</th>
                                    <th>Check-in</th>
                                    <th>Check-out</th>
                                    <th>Price</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reservations as $reservation): ?>
                                    <tr class="reservation-row" data-status="<?= strtolower($reservation['STATUS'] ?? 'unknown') ?>">
                                        <td><strong>#<?= htmlspecialchars($reservation['RESERVEID'] ?? 'N/A') ?></strong></td>
                                        <td><?= htmlspecialchars(($reservation['G_FNAME'] ?? '') . ' ' . ($reservation['G_LNAME'] ?? '')) ?></td>
                                        <td>
                                            <?php if (!empty($reservation['ROOMNUM'])): ?>
                                                <span class="badge bg-primary">#<?= htmlspecialchars($reservation['ROOMNUM']) ?></span>
                                            <?php else: ?>
                                                <span class="text-muted">N/A</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($reservation['ARRIVAL'])): ?>
                                                <?= date('M j, Y', strtotime($reservation['ARRIVAL'])) ?>
                                                <small class="text-muted d-block"><?= date('h:i A', strtotime($reservation['ARRIVAL'])) ?></small>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if (!empty($reservation['DEPARTURE'])): ?>
                                                <?= date('M j, Y', strtotime($reservation['DEPARTURE'])) ?>
                                                <small class="text-muted d-block"><?= date('h:i A', strtotime($reservation['DEPARTURE'])) ?></small>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </td>
                                        <td><strong>$<?= isset($reservation['RPRICE']) ? number_format($reservation['RPRICE'], 2) : '0.00' ?></strong></td>
                                        <td>
                                            <span class="status-badge status-<?= strtolower($reservation['STATUS'] ?? 'unknown') ?>">
                                                <i class="bi bi-circle-fill" style="font-size: 0.6rem;"></i>
                                                <?= htmlspecialchars($reservation['STATUS'] ?? 'Unknown') ?>
                                            </span>
                                        </td>
                                        <td class="action-buttons">
                                            <a href="view_reservation.php?id=<?= $reservation['RESERVEID'] ?>" class="btn btn-sm btn-info" title="View Details">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="edit_reservation.php?id=<?= $reservation['RESERVEID'] ?>" class="btn btn-sm btn-primary" title="Edit">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            
                                            <?php
                                            $status = $reservation['STATUS'] ?? null;
                                            if ($status === 'Pending'): ?>
                                                <a href="confirm_reservation.php?id=<?= $reservation['RESERVEID'] ?>" class="btn btn-sm btn-success" title="Accept Reservation" 
                                                   onclick="return confirm('Are you sure you want to accept reservation #<?= $reservation['RESERVEID'] ?>?');">
                                                    <i class="bi bi-check"></i>
                                                </a>
                                                <a href="cancel_reservation.php?id=<?= $reservation['RESERVEID'] ?>" class="btn btn-sm btn-danger" title="Reject Reservation"
                                                   onclick="return confirm('Are you sure you want to reject reservation #<?= $reservation['RESERVEID'] ?>?');">
                                                    <i class="bi bi-x"></i>
                                                </a>
                                            <?php elseif ($status === 'Confirmed'): ?>
                                                <a href="cancel_reservation.php?id=<?= $reservation['RESERVEID'] ?>" class="btn btn-sm btn-warning" title="Cancel Reservation"
                                                   onclick="return confirm('Are you sure you want to cancel confirmed reservation #<?= $reservation['RESERVEID'] ?>?');">
                                                    <i class="bi bi-x-circle"></i>
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }
        
        // Filter table by status
        function filterTable(status) {
            const rows = document.querySelectorAll('.reservation-row');
            rows.forEach(row => {
                if (status === 'all' || row.getAttribute('data-status') === status.toLowerCase()) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        }
        
        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('.reservation-row');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
        
        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>