<?php
session_start();
// Enhanced Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Content-Security-Policy: default-src \'self\'; script-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net; style-src \'self\' \'unsafe-inline\' https://cdn.jsdelivr.net https://fonts.googleapis.com; font-src \'self\' https://fonts.gstatic.com; img-src \'self\' data: https:;');

// Regenerate session ID periodically
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 300) { // 5 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Generate CSRF token with expiration
if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) ||
    time() - $_SESSION['csrf_token_time'] > 3600) { // 1 hour expiry
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    $_SESSION['csrf_token_time'] = time();
}

require __DIR__ . '/../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Admin;

// Enhanced Authentication Check
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// Check session timeout (30 minutes of inactivity)
if (isset($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > 1800) {
    session_destroy();
    header("Location: ../public/login.php?timeout=1");
    exit;
}
$_SESSION['last_activity'] = time();

$admin = new Admin();

// Get filter parameters
$dateFrom = $_GET['date_from'] ?? date('Y-m-d', strtotime('-30 days'));
$dateTo = $_GET['date_to'] ?? date('Y-m-d');
$statsFilter = $_GET['stats_filter'] ?? 'all';

// Enhanced stats with caching
$cacheKey = "dashboard_stats_{$dateFrom}_{$dateTo}_{$statsFilter}";
if (!isset($_SESSION['stats_cache'][$cacheKey]) ||
    time() - ($_SESSION['stats_cache'][$cacheKey]['time'] ?? 0) > 300) { // 5 min cache
    
    $stats = $admin->getDashboardStats($dateFrom, $dateTo, $statsFilter);
    $recentActivity = $admin->getRecentActivity(10);
    $roomOccupancy = $admin->getRoomOccupancyRate();
    $revenueStats = $admin->getRevenueStats($dateFrom, $dateTo);
    
    $_SESSION['stats_cache'][$cacheKey] = [
        'stats' => $stats,
        'activity' => $recentActivity,
        'occupancy' => $roomOccupancy,
        'revenue' => $revenueStats,
        'time' => time()
    ];
}

$stats = $_SESSION['stats_cache'][$cacheKey]['stats'];
$recentActivity = $_SESSION['stats_cache'][$cacheKey]['activity'];
$roomOccupancy = $_SESSION['stats_cache'][$cacheKey]['occupancy'];
$revenueStats = $_SESSION['stats_cache'][$cacheKey]['revenue'];

// Pagination for guests with enhanced filtering
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
$perPage = (int)($_GET['per_page'] ?? 10);
$search = trim($_GET['search'] ?? '');
$sortBy = $_GET['sort'] ?? 'GUESTID';
$sortOrder = $_GET['order'] ?? 'DESC';

// Validate sort parameters
$allowedSortFields = ['GUESTID', 'G_FNAME', 'G_LNAME', 'G_CITY', 'created_at'];
if (!in_array($sortBy, $allowedSortFields)) {
    $sortBy = 'GUESTID';
}
$sortOrder = strtoupper($sortOrder) === 'ASC' ? 'ASC' : 'DESC';

$guests = $admin->getAllGuests($page, $perPage, $search, $sortBy, $sortOrder);
$totalGuests = $admin->countGuests($search);
$totalPages = ceil($totalGuests / $perPage);

// Messages with sanitization
$successMsg = htmlspecialchars($_GET['success'] ?? '', ENT_QUOTES, 'UTF-8');
$errorMsg = htmlspecialchars($_GET['error'] ?? '', ENT_QUOTES, 'UTF-8');

// Get theme preference
$theme = $_COOKIE['admin_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Monbela Hotel</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Modern Design System */
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #3b82f6;
            --secondary: #64748b;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
            --header-height: 70px;
            --radius: 12px;
            --radius-sm: 8px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--bg-primary);
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            overflow-y: auto;
            transition: var(--transition);
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 24px;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.25rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar-brand i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .user-profile {
            padding: 24px;
            text-align: center;
            background: var(--bg-secondary);
            margin: 16px;
            border-radius: var(--radius);
        }
        
        .user-avatar-lg {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .nav-section {
            padding: 8px 16px;
        }
        
        .nav-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 8px 12px;
            letter-spacing: 0.05em;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            position: relative;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .nav-link:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .nav-link.active {
            background: var(--primary);
            color: white;
        }
        
        .nav-link i {
            font-size: 1.125rem;
            width: 24px;
        }
        
        .nav-badge {
            margin-left: auto;
            background: var(--bg-tertiary);
            color: var(--text-primary);
            padding: 2px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .nav-link.active .nav-badge {
            background: rgba(255, 255, 255, 0.2);
            color: white;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 24px;
            transition: var(--transition);
        }
        
        .top-bar {
            background: var(--bg-primary);
            padding: 20px 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-primary);
        }
        
        .page-subtitle {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin: 4px 0 0;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .theme-toggle {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-primary);
        }
        
        .theme-toggle:hover {
            background: var(--bg-tertiary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .user-avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .user-avatar:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .filter-panel {
            background: var(--bg-primary);
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            margin-bottom: 24px;
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-size: 0.875rem;
        }
        
        .form-control,
        .form-select {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 10px 16px;
            font-size: 0.875rem;
            transition: var(--transition);
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.875rem;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        
        .stat-card {
            background: var(--bg-primary);
            padding: 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            position: relative;
            overflow: hidden;
            transition: var(--transition);
            border: 1px solid transparent;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: var(--shadow-xl);
            border-color: var(--primary);
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: linear-gradient(90deg, var(--primary) 0%, var(--primary-light) 100%);
        }
        
        .stat-content {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }
        
        .stat-info h3 {
            font-size: 0.875rem;
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .stat-number {
            font-size: 2rem;
            font-weight: 800;
            color: var(--text-primary);
            line-height: 1;
            margin-bottom: 8px;
        }
        
        .stat-change {
            display: flex;
            align-items: center;
            gap: 6px;
            margin-top: 12px;
        }
        
        .change-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .change-badge.positive {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }
        
        .change-badge.negative {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .stat-icon {
            width: 56px;
            height: 56px;
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        .stat-icon.guest {
            background: rgba(37, 99, 235, 0.1);
            color: var(--primary);
        }
        
        .stat-icon.reservation {
            background: rgba(139, 92, 246, 0.1);
            color: #8b5cf6;
        }
        
        .stat-icon.pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .stat-icon.room {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }
        
        .quick-actions {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 16px;
            margin-bottom: 32px;
        }
        
        .quick-action-btn {
            display: flex;
            align-items: center;
            gap: 16px;
            padding: 20px;
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            text-decoration: none;
            color: var(--text-primary);
            transition: var(--transition);
            box-shadow: var(--shadow-sm);
            border: 2px solid transparent;
        }
        
        .quick-action-btn:hover {
            background: var(--bg-primary);
            border-color: var(--primary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
            color: var(--text-primary);
        }
        
        .quick-action-btn i {
            width: 48px;
            height: 48px;
            background: var(--bg-tertiary);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.25rem;
            color: var(--primary);
            flex-shrink: 0;
        }
        
        .quick-action-btn h5 {
            font-size: 1rem;
            font-weight: 600;
            margin: 0 0 4px;
        }
        
        .quick-action-btn p {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin: 0;
        }
        
        .table-container {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
        }
        
        .card-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 24px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .card-header h3 {
            font-size: 1.125rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .card-body {
            padding: 0;
        }
        
        .modern-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .modern-table thead th {
            background: var(--bg-secondary);
            color: var(--text-secondary);
            font-weight: 600;
            font-size: 0.875rem;
            padding: 12px 24px;
            text-align: left;
            border-bottom: 1px solid var(--border-color);
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
        
        .modern-table tbody td {
            padding: 16px 24px;
            border-bottom: 1px solid var(--border-color);
            color: var(--text-primary);
        }
        
        .modern-table tbody tr:hover {
            background: var(--bg-secondary);
        }
        
        .modern-table tbody tr:last-child td {
            border-bottom: none;
        }
        
        .sortable {
            cursor: pointer;
            user-select: none;
            position: relative;
            padding-right: 30px !important;
        }
        
        .sortable:hover {
            color: var(--primary);
        }
        
        .sortable::after {
            content: '\F282';
            font-family: 'bootstrap-icons';
            position: absolute;
            right: 8px;
            opacity: 0.3;
        }
        
        .sortable.asc::after {
            content: '\F286';
            opacity: 1;
            color: var(--primary);
        }
        
        .sortable.desc::after {
            content: '\F289';
            opacity: 1;
            color: var(--primary);
        }
        
        .search-container {
            position: relative;
        }
        
        .search-input {
            padding: 10px 16px 10px 40px;
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            font-size: 0.875rem;
            background: var(--bg-primary);
            color: var(--text-primary);
            width: 250px;
            transition: var(--transition);
        }
        
        .search-input:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        .search-icon {
            position: absolute;
            left: 16px;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-muted);
        }
        
        .status-badge {
            display: inline-flex;
            align-items: center;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .status-active {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
        }
        
        .status-inactive {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
        }
        
        .status-pending {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
        }
        
        .action-buttons {
            display: flex;
            gap: 4px;
        }
        
        .btn-icon {
            width: 32px;
            height: 32px;
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: var(--transition);
            cursor: pointer;
            border: none;
            background: var(--bg-secondary);
            color: var(--text-secondary);
        }
        
        .btn-icon:hover {
            background: var(--primary);
            color: white;
            transform: scale(1.1);
        }
        
        .pagination-container {
            display: flex;
            justify-content: center;
            padding: 24px;
            border-top: 1px solid var(--border-color);
        }
        
        .pagination {
            display: flex;
            gap: 4px;
        }
        
        .page-link {
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-sm);
            text-decoration: none;
            color: var(--text-secondary);
            font-weight: 500;
            transition: var(--transition);
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
        }
        
        .page-link:hover {
            background: var(--bg-tertiary);
            color: var(--primary);
            border-color: var(--primary);
        }
        
        .page-link.active {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }
        
        .recent-card {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            height: 100%;
        }
        
        .activity-timeline {
            padding-left: 24px;
            position: relative;
        }
        
        .activity-item {
            position: relative;
            padding-bottom: 20px;
        }
        
        .activity-item::before {
            content: '';
            position: absolute;
            left: -17px;
            top: 20px;
            bottom: -20px;
            width: 2px;
            background: var(--border-color);
        }
        
        .activity-item:last-child::before {
            display: none;
        }
        
        .activity-dot {
            position: absolute;
            left: -20px;
            top: 4px;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--primary);
            border: 2px solid var(--bg-primary);
            box-shadow: 0 0 0 4px var(--bg-secondary);
        }
        
        .activity-text {
            font-size: 0.875rem;
            color: var(--text-primary);
            margin-bottom: 4px;
        }
        
        .alert-message {
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInDown 0.3s ease;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .mobile-menu-btn {
            display: none;
        }
        
        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }
        
        .empty-state i {
            font-size: 3rem;
            color: var(--text-muted);
            margin-bottom: 16px;
        }
        
        .empty-state h4 {
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: var(--text-primary);
        }
        
        .empty-state p {
            color: var(--text-muted);
            margin-bottom: 24px;
        }
        
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 100%;
            }
            
            .mobile-menu-btn {
                display: flex;
                width: 44px;
                height: 44px;
                align-items: center;
                justify-content: center;
                background: var(--primary);
                color: white;
                border: none;
                border-radius: var(--radius);
                font-size: 1.25rem;
                cursor: pointer;
                transition: var(--transition);
            }
            
            .mobile-menu-btn:hover {
                background: var(--primary-dark);
            }
            
            .sidebar {
                transform: translateX(-100%);
                box-shadow: var(--shadow-xl);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .quick-actions {
                grid-template-columns: 1fr;
            }
            
            .page-title {
                font-size: 1.5rem;
            }
            
            .filter-panel {
                padding: 16px;
            }
            
            .card-header {
                flex-direction: column;
                gap: 16px;
                align-items: stretch;
            }
            
            .search-input {
                width: 100%;
            }
            
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
    </style>
</head>
<body>
    <!-- App Container -->
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <a href="admin_dashboard.php" class="sidebar-brand">
                <i class="bi bi-building"></i>
                <span>Monbela Hotel</span>
            </a>
            
            <!-- User Profile -->
            <div class="user-profile">
                <div class="user-avatar-lg">
                    <?= strtoupper(substr($_SESSION['fullname'] ?? 'A', 0, 1)) ?>
                </div>
                <h5 style="margin: 0 0 4px; font-size: 1rem; font-weight: 600;">
                    <?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?>
                </h5>
                <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">
                    Administrator
                </span>
            </div>
            
            <!-- Navigation -->
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="admin_dashboard.php" class="nav-link active">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Guest Management</div>
                    <a href="Guest/Manage_guest.php" class="nav-link">
                        <i class="bi bi-people"></i>
                        <span>All Guests</span>
                        <span class="nav-badge"><?= number_format($stats['total_guests'] ?? 0) ?></span>
                    </a>
                    <a href="Guest/Add_guest.php" class="nav-link">
                        <i class="bi bi-person-plus"></i>
                        <span>Add New Guest</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Room Management</div>
                    <a href="room/manage_rooms.php" class="nav-link">
                        <i class="bi bi-door-closed"></i>
                        <span>All Rooms</span>
                        <span class="nav-badge"><?= number_format($stats['total_rooms'] ?? 0) ?></span>
                    </a>
                    <a href="room/add_room.php" class="nav-link">
                        <i class="bi bi-plus-square"></i>
                        <span>Add New Room</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Reservations</div>
                    <a href="reservations/manage_reservations.php" class="nav-link">
                        <i class="bi bi-calendar-check"></i>
                        <span>All Reservations</span>
                        <?php if (($stats['total_reservations'] ?? 0) > 0): ?>
                            <span class="nav-badge"><?= number_format($stats['total_reservations']) ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="reservations/pending_reservations.php" class="nav-link">
                        <i class="bi bi-clock-history"></i>
                        <span>Pending</span>
                        <?php if (($stats['pending_reservations'] ?? 0) > 0): ?>
                            <span class="nav-badge" style="background: rgba(245, 158, 11, 0.2); color: var(--warning);">
                                <?= number_format($stats['pending_reservations']) ?>
                            </span>
                        <?php endif; ?>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="manage_admins.php" class="nav-link">
                        <i class="bi bi-shield-lock"></i>
                        <span>Manage Admins</span>
                    </a>
                    <a href="admin_logout.php?token=<?= $_SESSION['csrf_token'] ?>" class="nav-link">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="d-flex align-items-center gap-3">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="page-title">Dashboard Overview</h1>
                        <p class="page-subtitle">Welcome back, <?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?></p>
                    </div>
                </div>
                <div class="user-menu">
                    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                        <i class="bi bi-sun-fill" id="themeIcon"></i>
                    </button>
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['fullname'] ?? 'A', 0, 1)) ?>
                    </div>
                </div>
            </div>
            
            <!-- Messages -->
            <?php if ($successMsg): ?>
                <div class="alert-message alert-success" role="alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= $successMsg ?></span>
                    <button class="btn-close ms-auto" onclick="this.parentElement.remove()"></button>
                </div>
            <?php endif; ?>
            
            <?php if ($errorMsg): ?>
                <div class="alert-message alert-error" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= $errorMsg ?></span>
                    <button class="btn-close ms-auto" onclick="this.parentElement.remove()"></button>
                </div>
            <?php endif; ?>
            
            <!-- Filter Panel -->
            <div class="filter-panel">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date From</label>
                        <input type="date" name="date_from" class="form-control"
                            value="<?= htmlspecialchars($dateFrom) ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Date To</label>
                        <input type="date" name="date_to" class="form-control"
                            value="<?= htmlspecialchars($dateTo) ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Stats Filter</label>
                        <select name="stats_filter" class="form-select">
                            <option value="all" <?= $statsFilter === 'all' ? 'selected' : '' ?>>All Time</option>
                            <option value="today" <?= $statsFilter === 'today' ? 'selected' : '' ?>>Today</option>
                            <option value="week" <?= $statsFilter === 'week' ? 'selected' : '' ?>>This Week</option>
                            <option value="month" <?= $statsFilter === 'month' ? 'selected' : '' ?>>This Month</option>
                        </select>
                    </div>
                    <div class="col-md-3 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-funnel"></i> Apply Filter
                        </button>
                    </div>
                </form>
            </div>
            
            <!-- Statistics Cards -->
            <div class="stats-grid">
                <div class="stat-card" style="animation: fadeInUp 0.5s ease forwards; animation-delay: 0.1s;">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Total Guests</h3>
                            <div class="stat-number"><?= number_format($stats['total_guests'] ?? 0) ?></div>
                            <div class="stat-change">
                                <?php
                                $guestChange = $stats['guest_change_percent'] ?? 0;
                                $changeClass = $guestChange >= 0 ? 'positive' : 'negative';
                                ?>
                                <span class="change-badge <?= $changeClass ?>">
                                    <i class="bi bi-arrow-<?= $guestChange >= 0 ? 'up' : 'down' ?>"></i>
                                    <?= abs($guestChange) ?>%
                                </span>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">vs last period</span>
                            </div>
                        </div>
                        <div class="stat-icon guest">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" style="animation: fadeInUp 0.5s ease forwards; animation-delay: 0.2s;">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Total Reservations</h3>
                            <div class="stat-number"><?= number_format($stats['total_reservations'] ?? 0) ?></div>
                            <div class="stat-change">
                                <?php
                                $resChange = $stats['reservation_change_percent'] ?? 0;
                                $changeClass = $resChange >= 0 ? 'positive' : 'negative';
                                ?>
                                <span class="change-badge <?= $changeClass ?>">
                                    <i class="bi bi-arrow-<?= $resChange >= 0 ? 'up' : 'down' ?>"></i>
                                    <?= abs($resChange) ?>%
                                </span>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">vs last period</span>
                            </div>
                        </div>
                        <div class="stat-icon reservation">
                            <i class="bi bi-calendar-check-fill"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" style="animation: fadeInUp 0.5s ease forwards; animation-delay: 0.3s;">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Occupancy Rate</h3>
                            <div class="stat-number"><?= round($roomOccupancy ?? 0) ?>%</div>
                            <div class="progress" style="height: 8px; margin-top: 12px; background: var(--bg-tertiary); border-radius: 4px;">
                                <div class="progress-bar" style="background: var(--warning); width: <?= $roomOccupancy ?>%; border-radius: 4px;"></div>
                            </div>
                        </div>
                        <div class="stat-icon pending">
                            <i class="bi bi-house-door-fill"></i>
                        </div>
                    </div>
                </div>
                
                <div class="stat-card" style="animation: fadeInUp 0.5s ease forwards; animation-delay: 0.4s;">
                    <div class="stat-content">
                        <div class="stat-info">
                            <h3>Revenue</h3>
                            <div class="stat-number">₱ <?= number_format($revenueStats['total'] ?? 0, 2) ?></div>
                            <div class="stat-change">
                                <?php
                                $revChange = $revenueStats['change_percent'] ?? 0;
                                $changeClass = $revChange >= 0 ? 'positive' : 'negative';
                                ?>
                                <span class="change-badge <?= $changeClass ?>">
                                    <i class="bi bi-arrow-<?= $revChange >= 0 ? 'up' : 'down' ?>"></i>
                                    <?= abs($revChange) ?>%
                                </span>
                                <span style="font-size: 0.75rem; color: var(--text-muted);">vs last period</span>
                            </div>
                        </div>
                        <div class="stat-icon room">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Quick Actions -->
            <div class="quick-actions">
                <a href="Guest/Add_guest.php" class="quick-action-btn">
                    <i class="bi bi-person-plus"></i>
                    <div>
                        <h5>Add New Guest</h5>
                        <p>Register a new guest</p>
                    </div>
                </a>
                <a href="room/add_room.php" class="quick-action-btn">
                    <i class="bi bi-plus-square"></i>
                    <div>
                        <h5>Add New Room</h5>
                        <p>Create room listing</p>
                    </div>
                </a>
                <a href="reservations/add_reservation.php" class="quick-action-btn">
                    <i class="bi bi-calendar-plus"></i>
                    <div>
                        <h5>New Reservation</h5>
                        <p>Book a room</p>
                    </div>
                </a>
            </div>
            
            <!-- Recent Activity and Guests -->
            <div class="row">
                <!-- Recent Activity -->
                <div class="col-lg-4 mb-4">
                    <div class="recent-card">
                        <div class="card-header">
                            <h3><i class="bi bi-activity"></i> Recent Activity</h3>
                        </div>
                        <div class="card-body" style="padding: 24px;">
                            <div class="activity-timeline">
                                <?php foreach ($recentActivity as $activity): ?>
                                    <div class="activity-item">
                                        <div class="activity-dot"></div>
                                        <div class="activity-content">
                                            <p class="activity-text"><?= htmlspecialchars($activity['description']) ?></p>
                                            <small class="text-muted"><?= $activity['time_ago'] ?></small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Guests Table -->
                <div class="col-lg-8 mb-4">
                    <div class="table-container">
                        <div class="card-header">
                            <h3><i class="bi bi-people-fill"></i> Recent Guests</h3>
                            <div class="d-flex gap-2">
                                <div class="search-container">
                                    <form method="GET" id="searchForm">
                                        <input type="hidden" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                                        <input type="hidden" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                                        <input type="search" name="search" class="search-input"
                                            placeholder="Search guests..."
                                            value="<?= htmlspecialchars($search) ?>"
                                            id="searchInput">
                                        <i class="bi bi-search search-icon"></i>
                                    </form>
                                </div>
                                <select class="form-select" style="width: auto;" onchange="changePerPage(this.value)">
                                    <option value="10" <?= $perPage == 10 ? 'selected' : '' ?>>10 per page</option>
                                    <option value="25" <?= $perPage == 25 ? 'selected' : '' ?>>25 per page</option>
                                    <option value="50" <?= $perPage == 50 ? 'selected' : '' ?>>50 per page</option>
                                </select>
                            </div>
                        </div>
                        <div class="card-body">
                            <?php if (empty($guests)): ?>
                                <div class="empty-state">
                                    <i class="bi bi-people"></i>
                                    <h4>No guests found</h4>
                                    <p>Try adjusting your search or add new guests</p>
                                    <a href="Guest/Add_guest.php" class="btn btn-primary">
                                        <i class="bi bi-person-plus"></i> Add Guest
                                    </a>
                                </div>
                            <?php else: ?>
                                <div class="table-responsive">
                                    <table class="modern-table">
                                        <thead>
                                            <tr>
                                                <th class="sortable <?= $sortBy === 'GUESTID' ? strtolower($sortOrder) : '' ?>"
                                                    onclick="sortTable('GUESTID')">ID</th>
                                                <th class="sortable <?= $sortBy === 'G_FNAME' ? strtolower($sortOrder) : '' ?>"
                                                    onclick="sortTable('G_FNAME')">Name</th>
                                                <th>Phone</th>
                                                <th class="sortable <?= $sortBy === 'G_CITY' ? strtolower($sortOrder) : '' ?>"
                                                    onclick="sortTable('G_CITY')">City</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody id="guestTableBody">
                                            <?php foreach ($guests as $guest): ?>
                                                <tr data-id="<?= $guest['GUESTID'] ?>">
                                                    <td><strong>#<?= htmlspecialchars($guest['GUESTID']) ?></strong></td>
                                                    <td>
                                                        <div class="d-flex align-items-center gap-2">
                                                            <div style="width: 32px; height: 32px; background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%); border-radius: 50%; display: flex; align-items: center; justify-content: center; color: white; font-weight: 600; font-size: 0.875rem;">
                                                                <?= strtoupper(substr($guest['G_FNAME'], 0, 1)) ?>
                                                            </div>
                                                            <div>
                                                                <?= htmlspecialchars($guest['G_FNAME'] . ' ' . $guest['G_LNAME']) ?>
                                                            </div>
                                                        </div>
                                                    </td>
                                                    <td><?= htmlspecialchars($guest['G_PHONE']) ?></td>
                                                    <td><?= htmlspecialchars($guest['G_CITY'] ?? 'N/A') ?></td>
                                                    <td>
                                                        <div class="action-buttons">
                                                            <a href="Guest/View_guest.php?id=<?= $guest['GUESTID'] ?>"
                                                               class="btn-icon" title="View Details">
                                                                <i class="bi bi-eye"></i>
                                                            </a>
                                                            <a href="Guest/Edit_guest.php?id=<?= $guest['GUESTID'] ?>&token=<?= $_SESSION['csrf_token'] ?>"
                                                               class="btn-icon" title="Edit">
                                                                <i class="bi bi-pencil"></i>
                                                            </a>
                                                        </div>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                
                                <?php if ($totalPages > 1): ?>
                                    <div class="pagination-container">
                                        <div class="pagination">
                                            <?php if ($page > 1): ?>
                                                <a href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>"
                                                   class="page-link">
                                                    <i class="bi bi-chevron-left"></i>
                                                </a>
                                            <?php endif; ?>
                                            
                                            <?php                                            $startPage = max(1, $page - 2);
                                            $endPage = min($totalPages, $page + 2);
                                            
                                            if ($startPage > 1): ?>
                                                <a href="?page=1&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>"
                                                   class="page-link">1</a>
                                                <?php if ($startPage > 2): ?>
                                                    <span style="padding: 0 8px; color: var(--text-muted);">...</span>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <?php for ($i = $startPage; $i <= $endPage; $i++): ?>
                                                <a href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>"
                                                   class="page-link <?= $i === $page ? 'active' : '' ?>">
                                                    <?= $i ?>
                                                </a>
                                            <?php endfor; ?>
                                            
                                            <?php if ($endPage < $totalPages): ?>
                                                <?php if ($endPage < $totalPages - 1): ?>
                                                    <span style="padding: 0 8px; color: var(--text-muted);">...</span>
                                                <?php endif; ?>
                                                <a href="?page=<?= $totalPages ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>"
                                                   class="page-link"><?= $totalPages ?></a>
                                            <?php endif; ?>
                                            
                                            <?php if ($page < $totalPages): ?>
                                                <a href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&per_page=<?= $perPage ?>&sort=<?= $sortBy ?>&order=<?= $sortOrder ?>"
                                                   class="page-link">
                                                    <i class="bi bi-chevron-right"></i>
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize theme
            const theme = getCookie('admin_theme') || 'light';
            setTheme(theme);
            
            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            
            mobileMenuBtn?.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
            
            // Close sidebar on outside click (mobile)
            document.addEventListener('click', (event) => {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
                        sidebar.classList.remove('active');
                    }
                }
            });
            
            // Theme toggle
            const themeToggle = document.getElementById('themeToggle');
            themeToggle?.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                setTheme(newTheme);
            });
            
            // Search debouncing
            let searchTimeout;
            const searchInput = document.getElementById('searchInput');
            searchInput?.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    document.getElementById('searchForm').submit();
                }, 500);
            });
            
            // Auto-hide alerts
            setTimeout(() => {
                document.querySelectorAll('.alert-message').forEach(alert => {
                    alert.style.animation = 'fadeOut 0.3s forwards';
                    setTimeout(() => alert.remove(), 300);
                });
            }, 5000);
        });
        
        // Theme management
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            setCookie('admin_theme', theme, 365);
            
            const themeIcon = document.getElementById('themeIcon');
            if (themeIcon) {
                themeIcon.className = theme === 'dark' ? 'bi bi-moon-fill' : 'bi bi-sun-fill';
            }
        }
        
        // Cookie helpers
        function setCookie(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
        }
        
        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i].trim();
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }
        
        // Table sorting
        function sortTable(field) {
            const currentUrl = new URL(window.location.href);
            const currentSort = currentUrl.searchParams.get('sort');
            const currentOrder = currentUrl.searchParams.get('order') || 'DESC';
            
            let newOrder = 'ASC';
            if (currentSort === field) {
                newOrder = currentOrder === 'ASC' ? 'DESC' : 'ASC';
            }
            
            currentUrl.searchParams.set('sort', field);
            currentUrl.searchParams.set('order', newOrder);
            window.location.href = currentUrl.toString();
        }
        
        // Change items per page
        function changePerPage(value) {
            const currentUrl = new URL(window.location.href);
            currentUrl.searchParams.set('per_page', value);
            currentUrl.searchParams.set('page', '1'); // Reset to first page
            window.location.href = currentUrl.toString();
        }
        
        // Fade out animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>