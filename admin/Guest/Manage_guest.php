<?php
// =============================================================================
// Manage_guest.php - Enhanced Professional Hotel Admin Design
// =============================================================================
session_start();

// Enhanced Security Headers
header("X-Content-Type-Options: nosniff");
header("X-Frame-Options: DENY");
header("X-XSS-Protection: 1; mode=block");
header("Referrer-Policy: strict-origin-when-cross-origin");

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Rate limiting
 $rateLimitKey = 'guest_management_' . ($_SESSION['id'] ?? 'anonymous');
if (!isset($_SESSION[$rateLimitKey])) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'time' => time()];
}

if (time() - $_SESSION[$rateLimitKey]['time'] > 60) {
    $_SESSION[$rateLimitKey] = ['count' => 0, 'time' => time()];
} else {
    $_SESSION[$rateLimitKey]['count']++;
    if ($_SESSION[$rateLimitKey]['count'] > 60) { // 60 requests per minute
        http_response_code(429);
        die('Too many requests. Please try again later.');
    }
}

// Correct path to autoload.php (assuming this file is in admin/Guest directory)
require __DIR__ . '/../../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Admin;

// Check authentication
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../login.php"); // Fixed path
    exit;
}

// Initialize variables with enhanced validation
 $successMsg = filter_input(INPUT_GET, 'success', FILTER_SANITIZE_STRING) ?? '';
 $errorMsg = filter_input(INPUT_GET, 'error', FILTER_SANITIZE_STRING) ?? '';
 $admin = new Admin();

// Enhanced Pagination setup
 $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, [
    'options' => ['default' => 1, 'min_range' => 1]
]);
 $perPage = filter_input(INPUT_GET, 'perPage', FILTER_VALIDATE_INT, [
    'options' => ['default' => 15, 'min_range' => 5, 'max_range' => 100]
]) ?? 15;
 $search = trim(filter_input(INPUT_GET, 'search', FILTER_SANITIZE_STRING) ?? '');

// Advanced Filtering
 $filters = [
    'city' => filter_input(INPUT_GET, 'city', FILTER_SANITIZE_STRING),
    'nationality' => filter_input(INPUT_GET, 'nationality', FILTER_SANITIZE_STRING),
    'status' => filter_input(INPUT_GET, 'status', FILTER_SANITIZE_STRING),
    'dateFrom' => filter_input(INPUT_GET, 'dateFrom', FILTER_SANITIZE_STRING),
    'dateTo' => filter_input(INPUT_GET, 'dateTo', FILTER_SANITIZE_STRING),
];

// Enhanced Sorting setup
 $sortBy = filter_input(INPUT_GET, 'sort', FILTER_SANITIZE_STRING) ?? 'GUESTID';
 $sortOrder = filter_input(INPUT_GET, 'order', FILTER_SANITIZE_STRING) ?? 'DESC';
 $allowedSortFields = ['GUESTID', 'G_FNAME', 'G_LNAME', 'G_PHONE', 'G_UNAME', 'G_CITY', 'G_NATIONALITY', 'created_at'];
if (!in_array($sortBy, $allowedSortFields)) {
    $sortBy = 'GUESTID';
}
 $sortOrder = ($sortOrder === 'DESC') ? 'DESC' : 'ASC';

// Get guests data with filters
 $guests = $admin->getAllGuests($page, $perPage, $search, $sortBy, $sortOrder, $filters);
 $totalGuests = $admin->countGuests($search, $filters);
 $totalPages = ceil($totalGuests / $perPage);

// Get additional statistics
 $stats = [
    'totalGuests' => $totalGuests,
    'activeReservations' => $admin->getActiveReservationsCount(),
    'todayCheckins' => $admin->getTodayCheckinsCount(),
    'todayCheckouts' => $admin->getTodayCheckoutsCount(),
    'newGuestsThisMonth' => $admin->getNewGuestsThisMonth(),
];

// Get unique cities and nationalities for filters
 $cities = $admin->getUniqueCities();
 $nationalities = $admin->getUniqueNationalities();

// Build query string for pagination
 $queryParams = array_filter([
    'search' => $search,
    'sort' => $sortBy,
    'order' => $sortOrder,
    'perPage' => $perPage,
    'city' => $filters['city'],
    'nationality' => $filters['nationality'],
    'status' => $filters['status'],
    'dateFrom' => $filters['dateFrom'],
    'dateTo' => $filters['dateTo'],
]);
 $queryString = http_build_query($queryParams);

// Handle bulk actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulkAction'])) {
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        die('CSRF token validation failed');
    }
    
    $action = filter_input(INPUT_POST, 'bulkAction', FILTER_SANITIZE_STRING);
    $selectedIds = filter_input(INPUT_POST, 'selectedGuests', FILTER_DEFAULT, FILTER_REQUIRE_ARRAY) ?? [];
    
    if ($action && !empty($selectedIds)) {
        switch ($action) {
            case 'delete':
                $result = $admin->bulkDeleteGuests($selectedIds);
                break;
            case 'export':
                // Handle export
                break;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Guests - Monbela Hotel</title>
    <meta name="description" content="Manage hotel guests, view guest information, and handle reservations">
    
    <!-- Preload critical resources -->
    <link rel="preload" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" as="style">
    <link rel="preload" href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" as="style">
    
    <!-- Stylesheets -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <style>
        /* Simplified and clean CSS with white color scheme */
        :root {
            --primary-color: #ffffff;
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
            background-color: #f8f9fa;
            color: #333;
            font-size: 14px;
        }

        /* Main Layout */
        .admin-container {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar - White Theme */
        .sidebar {
            width: 250px;
            background: #ffffff;
            color: #333;
            padding: 20px 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            box-shadow: 2px 0 10px rgba(0,0,0,0.1);
            z-index: 1000;
            border-right: 1px solid var(--border-color);
        }

        .sidebar-header {
            padding: 0 20px 20px;
            border-bottom: 1px solid var(--border-color);
            margin-bottom: 20px;
        }

        .sidebar-header h3 {
            font-weight: 700;
            margin-bottom: 5px;
            font-size: 18px;
            color: var(--dark-color);
        }

        .sidebar-header p {
            opacity: 0.7;
            margin: 0;
            font-size: 12px;
            color: #666;
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
            color: #555;
            text-decoration: none;
            padding: 12px 20px;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
            border-left: 3px solid transparent;
        }

        .sidebar-menu a:hover {
            background: #f1f1f1;
            color: var(--dark-color);
            border-left-color: var(--secondary-color);
        }

        .sidebar-menu a.active {
            background: #f1f1f1;
            color: var(--secondary-color);
            border-left-color: var(--secondary-color);
            font-weight: 600;
        }

        .sidebar-menu i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        /* Main Content */
        .main-content {
            flex: 1;
            margin-left: 250px;
            padding: 20px;
            background: #f8f9fa;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        /* Buttons */
        .btn-primary {
            background: var(--secondary-color);
            border: none;
            padding: 8px 20px;
            border-radius: 6px;
            font-weight: 500;
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            background: #2980b9;
            transform: translateY(-1px);
            box-shadow: var(--shadow);
        }

        /* Cards */
        .card {
            border: none;
            border-radius: var(--radius);
            box-shadow: var(--shadow);
            margin-bottom: 20px;
            background: white;
        }

        .card-header {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 15px 20px;
            font-weight: 600;
            color: var(--dark-color);
        }

        /* Tables */
        .table {
            margin: 0;
        }

        .table thead th {
            background: #f8f9fa;
            border-bottom: 2px solid var(--border-color);
            padding: 12px 15px;
            font-weight: 600;
            color: var(--dark-color);
        }

        .table tbody tr {
            transition: background-color 0.2s ease;
        }

        .table tbody tr:hover {
            background-color: #f8f9fa;
        }

        /* Guest Avatar */
        .guest-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--secondary-color), #1abc9c);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 14px;
            margin-right: 10px;
        }

        /* Status Badges */
        .badge {
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 500;
        }

        .badge-success {
            background: #d4edda;
            color: #155724;
        }

        .badge-warning {
            background: #fff3cd;
            color: #856404;
        }

        /* Mobile Menu Toggle */
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
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        /* Responsive */
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
            }
        }

        /* Loading Overlay */
        .loading-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255,255,255,0.9);
            z-index: 9999;
            align-items: center;
            justify-content: center;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 4px solid #f3f3f3;
            border-top: 4px solid var(--secondary-color);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        /* Stats Cards */
        .stats-card {
            background: white;
            border-radius: var(--radius);
            padding: 20px;
            box-shadow: var(--shadow);
            position: relative;
            border-left: 4px solid var(--secondary-color);
        }

        .stats-value {
            font-size: 24px;
            font-weight: 700;
            color: var(--dark-color);
        }

        .stats-label {
            font-size: 14px;
            color: #6c757d;
            margin-top: 5px;
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 40px 20px;
            color: #6c757d;
        }

        .empty-state-icon {
            font-size: 48px;
            color: #dee2e6;
            margin-bottom: 20px;
        }

        /* Action Buttons */
        .action-btn {
            width: 32px;
            height: 32px;
            border-radius: 6px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border: none;
            margin: 0 2px;
            transition: all 0.2s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow);
        }

        .btn-view {
            background: #e3f2fd;
            color: var(--secondary-color);
        }

        .btn-edit {
            background: #fff3e0;
            color: #f57c00;
        }

        .btn-delete {
            background: #ffebee;
            color: #d32f2f;
        }
    </style>
</head>
<body>
    <!-- Mobile Menu Toggle -->
    <button class="mobile-menu-toggle" id="mobileMenuToggle">
        <i class="bi bi-list"></i>
    </button>

    <!-- Loading Overlay -->
    <div class="loading-overlay" id="loadingOverlay">
        <div class="spinner"></div>
    </div>

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
            
            <div style="padding: 20px; border-top: 1px solid var(--border-color); margin-top: 20px;">
                <div style="display: flex; align-items: center; margin-bottom: 15px;">
                    <div class="guest-avatar" style="margin-right: 10px;">
                        <?= strtoupper(substr($_SESSION['name'] ?? 'A', 0, 1)) ?>
                    </div>
                    <div>
                        <div style="font-weight: 600; color: var(--dark-color);"><?= htmlspecialchars($_SESSION['name'] ?? 'Admin') ?></div>
                        <small style="opacity: 0.7; color: #666;">Administrator</small>
                    </div>
                </div>
                <a href="../../admin_logout.php" class="btn btn-sm btn-outline-secondary w-100">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </aside>

        <!-- Main Content -->
        <main class="main-content">
            <!-- Page Header -->
            <div class="page-header">
                <div>
                    <h1><i class="bi bi-people me-2"></i>Guest Management</h1>
                    <p><?= number_format($totalGuests) ?> total guests</p>
                </div>
                <div>
                    <a href="Add_guest.php" class="btn btn-primary">
                        <i class="bi bi-plus-circle"></i> Add Guest
                    </a>
                </div>
            </div>

            <!-- Stats Cards -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-value"><?= number_format($stats['totalGuests']) ?></div>
                        <div class="stats-label">Total Guests</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-value"><?= number_format($stats['activeReservations'] ?? 0) ?></div>
                        <div class="stats-label">Active Reservations</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-value"><?= number_format($stats['todayCheckins'] ?? 0) ?></div>
                        <div class="stats-label">Today's Check-ins</div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stats-card">
                        <div class="stats-value"><?= number_format($stats['todayCheckouts'] ?? 0) ?></div>
                        <div class="stats-label">Today's Check-outs</div>
                    </div>
                </div>
            </div>

            <!-- Messages -->
            <?php if ($successMsg): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <?= htmlspecialchars($successMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <?php if ($errorMsg): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2"></i>
                    <?= htmlspecialchars($errorMsg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>

            <!-- Main Guest Table -->
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="bi bi-list-check"></i> Guest Directory</h5>
                    <div class="d-flex gap-2">
                        <form method="GET" class="d-flex">
                            <input type="search" 
                                   name="search" 
                                   class="form-control form-control-sm" 
                                   placeholder="Search guests..." 
                                   value="<?= htmlspecialchars($search) ?>"
                                   style="min-width: 250px;">
                        </form>
                    </div>
                </div>
                
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Guest Name</th>
                                    <th>Contact Info</th>
                                    <th>Location</th>
                                    <th>Status</th>
                                    <th class="text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($guests)): ?>
                                    <tr>
                                        <td colspan="6">
                                            <div class="empty-state">
                                                <div class="empty-state-icon">
                                                    <i class="bi bi-people"></i>
                                                </div>
                                                <h5>No Guests Found</h5>
                                                <p class="text-muted mb-3">
                                                    <?= $search ? 'Try adjusting your search' : 'Start by adding your first guest' ?>
                                                </p>
                                                <a href="Add_guest.php" class="btn btn-primary">
                                                    <i class="bi bi-person-plus"></i> Add Guest
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($guests as $guest): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-light text-dark">
                                                    #<?= str_pad($guest['GUESTID'], 5, '0', STR_PAD_LEFT) ?>
                                                </span>
                                            </td>
                                            <td>
                                                <div class="d-flex align-items-center">
                                                    <div class="guest-avatar">
                                                        <?= strtoupper(substr($guest['G_FNAME'], 0, 1) . substr($guest['G_LNAME'], 0, 1)) ?>
                                                    </div>
                                                    <div>
                                                        <div class="fw-semibold">
                                                            <?= htmlspecialchars($guest['G_FNAME'] . ' ' . $guest['G_LNAME']) ?>
                                                        </div>
                                                        <small class="text-muted">@<?= htmlspecialchars($guest['G_UNAME']) ?></small>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <div>
                                                    <i class="bi bi-phone text-muted me-1"></i>
                                                    <?= htmlspecialchars($guest['G_PHONE']) ?>
                                                </div>
                                                <small class="text-muted">
                                                    <i class="bi bi-envelope text-muted me-1"></i>
                                                    <?= htmlspecialchars($guest['G_UNAME']) ?>
                                                </small>
                                            </td>
                                            <td>
                                                <?php if (!empty($guest['G_CITY'])): ?>
                                                    <div>
                                                        <i class="bi bi-geo-alt text-muted me-1"></i>
                                                        <?= htmlspecialchars($guest['G_CITY']) ?>
                                                    </div>
                                                    <small class="text-muted">
                                                        <i class="bi bi-flag text-muted me-1"></i>
                                                        <?= htmlspecialchars($guest['G_NATIONALITY'] ?? 'Not specified') ?>
                                                    </small>
                                                <?php else: ?>
                                                    <span class="text-muted">-</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge <?= rand(0, 1) ? 'badge-success' : 'badge-warning' ?>">
                                                    <?= rand(0, 1) ? 'Active' : 'Inactive' ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <button type="button" 
                                                        class="action-btn btn-view"
                                                        onclick="viewGuest(<?= $guest['GUESTID'] ?>)">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <a href="Edit_guest.php?id=<?= $guest['GUESTID'] ?>" 
                                                   class="action-btn btn-edit">
                                                    <i class="bi bi-pencil"></i>
                                                </a>
                                                <button type="button" 
                                                        class="action-btn btn-delete"
                                                        onclick="deleteGuest(<?= $guest['GUESTID'] ?>, '<?= htmlspecialchars(addslashes($guest['G_FNAME'] . ' ' . $guest['G_LNAME'])) ?>')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Pagination -->
                <?php if ($totalPages > 1): ?>
                    <div class="card-footer">
                        <div class="d-flex justify-content-between align-items-center">
                            <div class="text-muted">
                                Showing <?= ($page - 1) * $perPage + 1 ?> to <?= min($page * $perPage, $totalGuests) ?> of <?= number_format($totalGuests) ?> guests
                            </div>
                            
                            <nav>
                                <ul class="pagination pagination-sm mb-0">
                                    <?php if ($page > 1): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page - 1 ?>&<?= $queryString ?>">
                                                <i class="bi bi-chevron-left"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                    
                                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                                        <?php if ($i == $page): ?>
                                            <li class="page-item active">
                                                <span class="page-link"><?= $i ?></span>
                                            </li>
                                        <?php elseif ($i >= $page - 2 && $i <= $page + 2): ?>
                                            <li class="page-item">
                                                <a class="page-link" href="?page=<?= $i ?>&<?= $queryString ?>"><?= $i ?></a>
                                            </li>
                                        <?php endif; ?>
                                    <?php endfor; ?>
                                    
                                    <?php if ($page < $totalPages): ?>
                                        <li class="page-item">
                                            <a class="page-link" href="?page=<?= $page + 1 ?>&<?= $queryString ?>">
                                                <i class="bi bi-chevron-right"></i>
                                            </a>
                                        </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete <strong id="deleteGuestName"></strong>?</p>
                    <p class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form id="deleteForm" method="POST" action="Delete_guest.php" style="display: inline;">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <input type="hidden" name="guest_id" id="deleteGuestId">
                        <button type="submit" class="btn btn-danger">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <script>
        // Initialize Bootstrap components
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize tooltips
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            tooltipTriggerList.map(function(tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });

        // Sidebar toggle
        document.getElementById('mobileMenuToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('active');
        });

        // Loading overlay functions
        function showLoading() {
            document.getElementById('loadingOverlay').style.display = 'flex';
        }

        function hideLoading() {
            document.getElementById('loadingOverlay').style.display = 'none';
        }

        // View guest function
        function viewGuest(guestId) {
            window.location.href = 'View_guest.php?id=' + guestId;
        }

        // Delete guest function
        function deleteGuest(guestId, guestName) {
            document.getElementById('deleteGuestName').textContent = guestName;
            document.getElementById('deleteGuestId').value = guestId;
            
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        // Auto-submit search on enter
        document.querySelector('input[name="search"]')?.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                this.form.submit();
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
</body>
</html>