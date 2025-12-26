<?php
// =============================================================================
// admin/reservations/view_reservation.php - Admin View Reservation Page (FIXED)
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
    // FIXED: Initialize database connection first, then pass it to Reservation
    $database = new Database();
    $db_link = $database->getConnection();
    
    // Instantiate the Reservation model with the connection
    $reservationModel = new Reservation($db_link);
    
} catch (Exception $e) {
    die("Error initializing models: " . htmlspecialchars($e->getMessage()));
}

// --- Get and Validate Reservation ID ---
$reservationId = null;
$reservation = null;
$error_message = '';

if (isset($_GET['id'])) {
    $id = trim($_GET['id']);
    if (is_numeric($id) && $id > 0) {
        $reservationId = (int)$id;
        $reservation = $reservationModel->getById($reservationId);
        
        if (!$reservation) {
            $error_message = "Reservation with ID " . htmlspecialchars($reservationId) . " not found.";
            error_log("Reservation not found for ID: " . $reservationId);
        }
    } else {
        $error_message = "Invalid reservation ID format.";
    }
} else {
    $error_message = "Reservation ID is required.";
}

// Get checkout history if reservation exists
$checkoutHistory = null;
if ($reservation && $reservation['STATUS'] === 'Checked Out') {
    try {
        // FIXED: Use the $db_link connection instead of $db
        $stmt = $db_link->prepare("
            SELECT CHECKOUT_DATE, CHECKOUT_BY
            FROM tblreservation
            WHERE RESERVEID = :id AND CHECKOUT_DATE IS NOT NULL
        ");
        $stmt->execute([':id' => $reservationId]);
        $checkoutHistory = $stmt->fetch();
    } catch (Exception $e) {
        error_log("Failed to fetch checkout history: " . $e->getMessage());
    }
}

// Check for payment image - ENHANCED VERSION
$paymentImagePath = null;
$paymentImageUrl = null;

if ($reservation) {
    $uploadDir = __DIR__ . '/../../uploads/payment/';
    $webPath = '../../uploads/payment/';
    
    // Debug: Log the directory we're checking
    error_log("Looking for payment files in: " . $uploadDir);
    error_log("Reservation ID: " . $reservation['RESERVEID']);
    error_log("Confirmation Code: " . $reservation['CONFIRMATIONCODE']);
    
    // Method 1: Look for files with various naming patterns
    if (is_dir($uploadDir)) {
        $files = scandir($uploadDir);
        foreach ($files as $file) {
            // Skip directories
            if ($file == '.' || $file == '..') continue;
            
            // Check various naming patterns
            $searchPatterns = [
                'payment_' . $reservation['RESERVEID'] . '_',
                'payment_' . $reservation['CONFIRMATIONCODE'] . '_',
                $reservation['CONFIRMATIONCODE'] . '.',
                $reservation['RESERVEID'] . '.',
                'payment_' . $reservation['RESERVEID'] . '.',
                'payment_' . $reservation['CONFIRMATIONCODE'] . '.'
            ];
            
            foreach ($searchPatterns as $pattern) {
                if (stripos($file, $pattern) !== false) {
                    // Check if it's an image file
                    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'])) {
                        $paymentImagePath = $uploadDir . $file;
                        $paymentImageUrl = $webPath . $file;
                        error_log("Found payment file: " . $file);
                        break 2; // Break both loops
                    }
                }
            }
        }
    }
    
    // Method 2: If no file found, try glob patterns
    if (!$paymentImageUrl) {
        $patterns = [
            'payment_' . $reservation['RESERVEID'] . '_*',
            'payment_' . $reservation['CONFIRMATIONCODE'] . '_*',
            $reservation['CONFIRMATIONCODE'] . '*',
            $reservation['RESERVEID'] . '*'
        ];
        
        foreach ($patterns as $pattern) {
            $files = glob($uploadDir . $pattern . '.{jpg,jpeg,png,gif,webp,pdf}', GLOB_BRACE);
            if (!empty($files)) {
                $paymentImagePath = $files[0];
                $paymentImageUrl = $webPath . basename($files[0]);
                error_log("Found payment file via glob: " . basename($files[0]));
                break;
            }
        }
    }
}

// Display messages from previous page
if (isset($_SESSION['success_message'])) {
    $success_message = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['error_message'])) {
    $error_message = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}
?>
<!-- Rest of your HTML remains the same -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Reservation - Admin Panel - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #0f2027;
            --primary-main: #203a43;
            --primary-light: #2c5364;
            --accent-gold: #d4af37;
            --accent-gold-light: #f4d03f;
            --accent-teal: #00d4ff;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            --dark: #1f2937;
            --light: #f8fafc;
            --gradient-primary: linear-gradient(135deg, #0f2027 0%, #203a43 50%, #2c5364 100%);
            --gradient-gold: linear-gradient(135deg, #d4af37 0%, #f4d03f 100%);
            --gradient-overlay: linear-gradient(135deg, rgba(15, 32, 39, 0.95) 0%, rgba(44, 83, 100, 0.95) 100%);
            --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            --shadow-glow: 0 0 20px rgba(212, 175, 55, 0.3);
            --radius-sm: 0.5rem;
            --radius-md: 0.75rem;
            --radius-lg: 1rem;
            --radius-xl: 1.5rem;
        }
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #e0e7ff 0%, #f3f4f6 50%, #e5e7eb 100%);
            min-height: 100vh;
            color: var(--dark);
            position: relative;
            overflow-x: hidden;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image:
                radial-gradient(circle at 20% 50%, rgba(212, 175, 55, 0.08) 0%, transparent 50%),
                radial-gradient(circle at 80% 80%, rgba(0, 212, 255, 0.08) 0%, transparent 50%);
            pointer-events: none;
            z-index: 0;
        }
        .dashboard-container {
            display: flex;
            min-height: 100vh;
            position: relative;
            z-index: 1;
        }
        /* ===================== SIDEBAR ===================== */
        .sidebar {
            width: 280px;
            background: var(--gradient-primary);
            color: white;
            padding: 0;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            z-index: 1000;
            transition: all 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        }
        .sidebar::-webkit-scrollbar {
            width: 6px;
        }
        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
        }
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(212, 175, 55, 0.5);
            border-radius: 3px;
        }
        .sidebar-header {
            padding: 2rem 1.5rem;
            background: rgba(0, 0, 0, 0.2);
            border-bottom: 2px solid var(--accent-gold);
            position: relative;
            overflow: hidden;
        }
        .sidebar-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.1) 0%, transparent 70%);
            animation: pulse 4s ease-in-out infinite;
        }
        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 0.5; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }
        .sidebar-header h3 {
            font-family: 'Playfair Display', serif;
            font-weight: 700;
            font-size: 1.5rem;
            margin-bottom: 0.5rem;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            position: relative;
            z-index: 1;
        }
        .sidebar-header p {
            opacity: 0.9;
            margin: 0;
            font-size: 0.875rem;
            position: relative;
            z-index: 1;
            font-weight: 300;
            letter-spacing: 1px;
        }
        .sidebar-menu {
            list-style: none;
            padding: 1.5rem 0;
            margin: 0;
        }
        .sidebar-menu li {
            margin-bottom: 0.25rem;
            padding: 0 0.75rem;
        }
        .sidebar-menu a {
            color: rgba(255, 255, 255, 0.85);
            text-decoration: none;
            padding: 1rem 1.25rem;
            display: flex;
            align-items: center;
            transition: all 0.3s ease;
            border-radius: var(--radius-md);
            position: relative;
            overflow: hidden;
            font-weight: 500;
            font-size: 0.95rem;
        }
        .sidebar-menu a::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            height: 100%;
            width: 4px;
            background: var(--accent-gold);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }
        .sidebar-menu a:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
            box-shadow: var(--shadow-md);
        }
        .sidebar-menu a:hover::before,
        .sidebar-menu a.active::before {
            transform: scaleY(1);
        }
        .sidebar-menu a.active {
            background: rgba(212, 175, 55, 0.2);
            color: var(--accent-gold-light);
            font-weight: 600;
        }
        .sidebar-menu i {
            margin-right: 1rem;
            font-size: 1.3rem;
            width: 24px;
            text-align: center;
        }
        /* ===================== MAIN CONTENT ===================== */
        .main-content {
            flex: 1;
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
        }
        /* ===================== HEADER ===================== */
        .content-header {
            background: white;
            border-radius: var(--radius-xl);
            padding: 2rem 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-xl);
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1.5rem;
            position: relative;
            overflow: hidden;
            border: 1px solid rgba(212, 175, 55, 0.1);
        }
        .content-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 4px;
            background: var(--gradient-gold);
        }
        .header-content h1 {
            font-family: 'Playfair Display', serif;
            font-size: 2.25rem;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
            font-weight: 700;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }
        .header-content h1 i {
            color: var(--accent-gold);
            font-size: 2rem;
        }
        .breadcrumb {
            background: transparent;
            padding: 0;
            margin: 0;
            font-size: 0.9rem;
        }
        .breadcrumb-item {
            color: #6b7280;
        }
        .breadcrumb-item.active {
            color: var(--primary-main);
            font-weight: 600;
        }
        .breadcrumb-item + .breadcrumb-item::before {
            color: var(--accent-gold);
        }
        /* ===================== ALERT STYLES ===================== */
        .alert {
            border-radius: var(--radius-lg);
            border: none;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            animation: slideIn 0.5s ease;
            box-shadow: var(--shadow-lg);
        }
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .alert i {
            font-size: 1.5rem;
        }
        .alert-success {
            background: linear-gradient(135deg, rgba(16, 185, 129, 0.1) 0%, rgba(16, 185, 129, 0.05) 100%);
            color: var(--success);
            border-left: 4px solid var(--success);
        }
        .alert-danger {
            background: linear-gradient(135deg, rgba(239, 68, 68, 0.1) 0%, rgba(239, 68, 68, 0.05) 100%);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }
        /* ===================== DETAILS CARD ===================== */
        .details-card {
            background: white;
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-xl);
            overflow: hidden;
            animation: fadeInUp 0.6s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        .details-header {
            background: var(--gradient-primary);
            color: white;
            padding: 2rem 2.5rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 1rem;
            position: relative;
            overflow: hidden;
        }
        .details-header::before {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 400px;
            height: 400px;
            background: radial-gradient(circle, rgba(212, 175, 55, 0.15) 0%, transparent 70%);
            border-radius: 50%;
        }
        .details-header-left {
            display: flex;
            align-items: center;
            gap: 1rem;
            position: relative;
            z-index: 1;
        }
        .details-header-icon {
            width: 60px;
            height: 60px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: var(--radius-md);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            border: 2px solid rgba(212, 175, 55, 0.3);
        }
        .details-header h2 {
            margin: 0;
            font-weight: 700;
            font-size: 1.5rem;
            font-family: 'Playfair Display', serif;
        }
        .details-header .status-badge {
            position: relative;
            z-index: 1;
        }
        /* ===================== STATUS BADGES ===================== */
        .status-badge {
            padding: 0.65rem 1.25rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-md);
            transition: all 0.3s ease;
        }
        .status-badge i {
            font-size: 0.65rem;
            animation: pulse-dot 2s ease-in-out infinite;
        }
        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50% { opacity: 0.7; transform: scale(1.2); }
        }
        .status-pending {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            color: #92400e;
            border: 2px solid #fbbf24;
        }
        .status-confirmed {
            background: linear-gradient(135deg, #d1fae5 0%, #a7f3d0 100%);
            color: #065f46;
            border: 2px solid #10b981;
        }
        .status-cancelled {
            background: linear-gradient(135deg, #fee2e2 0%, #fecaca 100%);
            color: #991b1b;
            border: 2px solid #ef4444;
        }
        /* Add new status for checked out */
        .status-checked-out,
        .status-completed {
            background: linear-gradient(135deg, #e0e7ff 0%, #c7d2fe 100%);
            color: #4338ca;
            border: 2px solid #6366f1;
        }
        /* ===================== DETAILS BODY ===================== */
        .details-body {
            padding: 2.5rem;
        }
        .detail-section {
            margin-bottom: 2.5rem;
            padding: 2rem;
            background: linear-gradient(135deg, #f9fafb 0%, #ffffff 100%);
            border-radius: var(--radius-lg);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
            position: relative;
        }
        .detail-section::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-gold);
            border-radius: 4px 0 0 4px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .detail-section:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }
        .detail-section:hover::before {
            opacity: 1;
        }
        .detail-section:last-child {
            margin-bottom: 0;
        }
        .detail-section h3 {
            color: var(--primary-dark);
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #e5e7eb;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            font-family: 'Playfair Display', serif;
        }
        .detail-section h3 i {
            width: 40px;
            height: 40px;
            background: var(--gradient-gold);
            border-radius: var(--radius-sm);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.1rem;
        }
        .detail-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }
        .detail-item {
            display: flex;
            flex-direction: column;
            padding: 1rem;
            background: white;
            border-radius: var(--radius-md);
            border: 1px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .detail-item:hover {
            box-shadow: var(--shadow-md);
            border-color: var(--accent-gold);
            transform: translateY(-2px);
        }
        .detail-label {
            font-weight: 600;
            color: #6b7280;
            font-size: 0.85rem;
            margin-bottom: 0.5rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        .detail-label i {
            color: var(--accent-gold);
            font-size: 0.9rem;
        }
        .detail-value {
            color: var(--dark);
            font-size: 1.05rem;
            font-weight: 500;
        }
        .detail-value strong {
            color: var(--primary-dark);
            font-weight: 700;
        }
        .confirmation-code {
            background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
            padding: 1.25rem;
            border-radius: var(--radius-md);
            font-family: 'Courier New', monospace;
            font-size: 1.2rem;
            font-weight: 700;
            letter-spacing: 2px;
            text-align: center;
            border: 2px dashed var(--accent-gold);
            color: var(--primary-dark);
            box-shadow: inset 0 2px 4px rgba(0, 0, 0, 0.1);
        }
        /* ===================== CHECKOUT HISTORY ===================== */
        .checkout-history {
            background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%);
            border: 1px solid #0ea5e9;
            padding: 1rem 1.5rem;
            border-radius: var(--radius-md);
            margin-top: 1rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .checkout-history i {
            font-size: 1.5rem;
            color: #0ea5e9;
        }
        .checkout-history-info {
            flex: 1;
        }
        .checkout-history-info .label {
            font-size: 0.875rem;
            color: #64748b;
            margin-bottom: 0.25rem;
        }
        .checkout-history-info .value {
            font-weight: 600;
            color: #0c4a6e;
        }
        /* ===================== PAYMENT SECTION ===================== */
        .payment-viewer {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: white;
            border-radius: var(--radius-lg);
            border: 2px solid #e5e7eb;
            transition: all 0.3s ease;
        }
        .payment-viewer:hover {
            border-color: var(--accent-gold);
            box-shadow: var(--shadow-lg);
        }
        .payment-image-container {
            position: relative;
            width: 100%;
            max-width: 600px;
            margin: 0 auto;
            border-radius: var(--radius-md);
            overflow: hidden;
            box-shadow: var(--shadow-xl);
            background: #f9fafb;
        }
        .payment-image {
            width: 100%;
            height: auto;
            display: block;
            cursor: zoom-in;
            transition: transform 0.3s ease;
        }
        .payment-image:hover {
            transform: scale(1.02);
        }
        .payment-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }
        .no-payment {
            text-align: center;
            padding: 3rem 2rem;
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border-radius: var(--radius-lg);
            border: 2px dashed #fbbf24;
        }
        .no-payment i {
            font-size: 4rem;
            color: #f59e0b;
            margin-bottom: 1rem;
        }
        .no-payment h4 {
            color: #92400e;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        .no-payment p {
            color: #78350f;
            margin: 0;
        }
        /* Debug info */
        .debug-info {
            margin-top: 1rem;
            padding: 1rem;
            background: #f3f4f6;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            color: #6b7280;
            font-family: monospace;
        }
        /* ===================== LIGHTBOX ===================== */
        .lightbox {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.95);
            backdrop-filter: blur(10px);
            animation: fadeIn 0.3s ease;
        }
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        .lightbox.active {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .lightbox-content {
            max-width: 90%;
            max-height: 90%;
            position: relative;
            animation: zoomIn 0.3s ease;
        }
        @keyframes zoomIn {
            from { transform: scale(0.8); opacity: 0; }
            to { transform: scale(1); opacity: 1; }
        }
        .lightbox-image {
            max-width: 100%;
            max-height: 90vh;
            display: block;
            border-radius: var(--radius-lg);
            box-shadow: 0 0 50px rgba(212, 175, 55, 0.5);
        }
        .lightbox-close {
            position: absolute;
            top: -3rem;
            right: 0;
            background: var(--gradient-gold);
            color: white;
            border: none;
            width: 50px;
            height: 50px;
            border-radius: 50%;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-xl);
        }
        .lightbox-close:hover {
            transform: rotate(90deg) scale(1.1);
            box-shadow: var(--shadow-glow);
        }
        .lightbox-controls {
            position: absolute;
            bottom: -4rem;
            left: 50%;
            transform: translateX(-50%);
            display: flex;
            gap: 1rem;
        }
        .lightbox-btn {
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            transition: all 0.3s ease;
            box-shadow: var(--shadow-lg);
        }
        .lightbox-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
        }
        /* ===================== ACTION BUTTONS ===================== */
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding: 2rem 2.5rem;
            background: linear-gradient(135deg, #f9fafb 0%, #f3f4f6 100%);
            border-top: 2px solid #e5e7eb;
            flex-wrap: wrap;
        }
        .btn-custom {
            padding: 0.875rem 2rem;
            border-radius: 50px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.75rem;
            border: none;
            cursor: pointer;
            font-size: 0.95rem;
            position: relative;
            overflow: hidden;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            box-shadow: var(--shadow-md);
        }
        .btn-custom::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            background: rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }
        .btn-custom:hover::before {
            width: 300px;
            height: 300px;
        }
        .btn-custom i {
            font-size: 1.1rem;
            position: relative;
            z-index: 1;
        }
        .btn-custom span {
            position: relative;
            z-index: 1;
        }
        .btn-custom:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow-xl);
        }
        .btn-custom:active {
            transform: translateY(-1px);
        }
        .btn-primary {
            background: var(--gradient-primary);
            color: white;
        }
        .btn-success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            color: white;
        }
        .btn-danger {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6b7280 0%, #4b5563 100%);
            color: white;
        }
        .btn-warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            color: white;
        }
        .btn-print {
            background: linear-gradient(135deg, #8b5cf6 0%, #7c3aed 100%);
            color: white;
        }
        .btn-info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
        }
        /* Add these new styles for checkout button */
        .btn-checkout {
            background: linear-gradient(135deg, #14b8a6 0%, #0d9488 100%);
            color: white;
        }
        .btn-checkout:hover:not(:disabled) {
            background: linear-gradient(135deg, #0d9488 0%, #0f766e 100%);
        }
        .btn-checkout-confirmation {
            background: linear-gradient(135deg, #06b6d4 0%, #0891b2 100%);
            color: white;
        }
        .btn-completed {
            background: linear-gradient(135deg, #e5e7eb 0%, #d1d5db 100%);
            color: #4b5563;
            cursor: default !important;
            pointer-events: none;
        }
        /* ===================== MOBILE MENU ===================== */
        .mobile-menu-toggle {
            display: none;
            position: fixed;
            top: 1.5rem;
            left: 1.5rem;
            z-index: 1001;
            background: var(--gradient-primary);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: var(--radius-md);
            font-size: 1.5rem;
            cursor: pointer;
            box-shadow: var(--shadow-xl);
            transition: all 0.3s ease;
        }
        .mobile-menu-toggle:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-glow);
        }
        /* ===================== ADDITIONAL ENHANCEMENTS ===================== */
        .info-badge {
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            background: linear-gradient(135deg, #dbeafe 0%, #bfdbfe 100%);
            color: #1e40af;
            border: 1px solid #3b82f6;
        }
        .price-highlight {
            font-size: 1.75rem;
            font-weight: 800;
            background: var(--gradient-gold);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .date-display {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1rem;
            background: linear-gradient(135deg, #ede9fe 0%, #ddd6fe 100%);
            border-radius: var(--radius-md);
            border-left: 3px solid #8b5cf6;
        }
        .date-display i {
            color: #8b5cf6;
        }
        /* Tooltip styles */
        .custom-tooltip {
            position: absolute;
            background: var(--primary-dark);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            pointer-events: none;
            opacity: 0;
            transition: opacity 0.3s ease;
            white-space: nowrap;
        }
        /* ===================== PRINT STYLES ===================== */
        @media print {
            .sidebar,
            .mobile-menu-toggle,
            .action-buttons,
            .btn-custom,
            .payment-actions,
            .lightbox {
                display: none !important;
            }
            .main-content {
                margin-left: 0;
                padding: 0;
            }
            .details-card {
                box-shadow: none;
                border: 1px solid #ddd;
            }
            .payment-image {
                max-width: 100%;
                page-break-inside: avoid;
            }
        }
        /* ===================== RESPONSIVE ===================== */
        @media (max-width: 992px) {
            .detail-grid {
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            }
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
                padding-top: 5rem;
            }
            .mobile-menu-toggle {
                display: block;
            }
            .content-header {
                padding: 1.5rem;
                flex-direction: column;
                align-items: flex-start;
            }
            .header-content h1 {
                font-size: 1.75rem;
            }
            .details-body {
                padding: 1.5rem;
            }
            .detail-section {
                padding: 1.5rem;
            }
            .detail-grid {
                grid-template-columns: 1fr;
            }
            .action-buttons {
                padding: 1.5rem;
                flex-direction: column;
            }
            .btn-custom {
                width: 100%;
                justify-content: center;
            }
            .lightbox-controls {
                flex-direction: column;
                width: 100%;
            }
            .lightbox-btn {
                width: 100%;
                justify-content: center;
            }
        }
        @media (max-width: 576px) {
            .header-content h1 {
                font-size: 1.5rem;
            }
            .details-header {
                padding: 1.5rem;
            }
            .details-header-icon {
                width: 50px;
                height: 50px;
                font-size: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <button class="mobile-menu-toggle" onclick="toggleSidebar()">
        <i class="bi bi-list"></i>
    </button>
    <!-- Lightbox for payment image -->
    <div class="lightbox" id="paymentLightbox">
        <div class="lightbox-content">
            <button class="lightbox-close" onclick="closeLightbox()">
                <i class="bi bi-x-lg"></i>
            </button>
            <img src="" alt="Payment Proof" class="lightbox-image" id="lightboxImage">
            <div class="lightbox-controls">
                <button class="lightbox-btn" onclick="downloadPayment()">
                    <i class="bi bi-download"></i>
                    <span>Download</span>
                </button>
                <button class="lightbox-btn" onclick="printPayment()">
                    <i class="bi bi-printer"></i>
                    <span>Print</span>
                </button>
            </div>
        </div>
    </div>
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
                        <span>Dashboard</span>
                    </a>
                </li>
                <li>
                    <a href="../Guest/Manage_guest.php">
                        <i class="bi bi-people"></i>
                        <span>Manage Guests</span>
                    </a>
                </li>
                <li>
                    <a href="../room/manage_rooms.php">
                        <i class="bi bi-door-closed"></i>
                        <span>Manage Rooms</span>
                    </a>
                </li>
                <li>
                    <a href="manage_reservations.php" class="active">
                        <i class="bi bi-calendar-check"></i>
                        <span>Manage Reservations</span>
                    </a>
                </li>
                <li>
                    <a href="../../public/admin_logout.php">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </li>
            </ul>
        </aside>
        <!-- Main Content -->
        <main class="main-content">
            <div class="content-header">
                <div class="header-content">
                    <h1>
                        <i class="bi bi-file-text"></i>
                        Reservation Details
                    </h1>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item"><a href="../admin_dashboard.php">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="manage_reservations.php">Reservations</a></li>
                            <li class="breadcrumb-item active">View #<?= htmlspecialchars($reservationId) ?></li>
                        </ol>
                    </nav>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <button onclick="window.print()" class="btn-custom btn-print">
                        <i class="bi bi-printer"></i>
                        <span>Print</span>
                    </button>
                    <a href="manage_reservations.php" class="btn-custom btn-secondary">
                        <i class="bi bi-arrow-left"></i>
                        <span>Back</span>
                    </a>
                </div>
            </div>
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill"></i>
                    <span><?= htmlspecialchars($success_message) ?></span>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            <?php if ($error_message): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= $error_message ?></span>
                </div>
            <?php else: ?>
                <div class="details-card">
                    <div class="details-header">
                        <div class="details-header-left">
                            <div class="details-header-icon">
                                <i class="bi bi-bookmark-star"></i>
                            </div>
                            <h2>Reservation #<?= htmlspecialchars($reservation['RESERVEID']) ?></h2>
                        </div>
                        <span class="status-badge status-<?= strtolower(str_replace(' ', '-', $reservation['STATUS'])) ?>">
                            <i class="bi bi-circle-fill"></i>
                            <?= htmlspecialchars($reservation['STATUS']) ?>
                        </span>
                    </div>
                    <div class="details-body">
                        <!-- Guest Details -->
                        <div class="detail-section">
                            <h3>
                                <i class="bi bi-person-badge"></i>
                                Guest Information
                            </h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-person"></i>
                                        Guest Name
                                    </span>
                                    <span class="detail-value">
                                        <strong><?= htmlspecialchars($reservation['G_FNAME'] . ' ' . $reservation['G_LNAME']) ?></strong>
                                    </span>
                                </div>
                                <?php if (!empty($reservation['G_EMAIL'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-envelope"></i>
                                        Email Address
                                    </span>
                                    <span class="detail-value">
                                        <a href="mailto:<?= htmlspecialchars($reservation['G_EMAIL']) ?>" style="color: var(--accent-gold); text-decoration: none;">
                                            <?= htmlspecialchars($reservation['G_EMAIL']) ?>
                                        </a>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($reservation['GUESTID'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-hash"></i>
                                        Guest ID
                                    </span>
                                    <span class="detail-value">#<?= htmlspecialchars($reservation['GUESTID']) ?></span>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <!-- Reservation Details -->
                        <div class="detail-section">
                            <h3>
                                <i class="bi bi-calendar-event"></i>
                                Reservation Information
                            </h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-key"></i>
                                        Confirmation Code
                                    </span>
                                    <div class="confirmation-code">
                                        <?= htmlspecialchars($reservation['CONFIRMATIONCODE']) ?>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-calendar-plus"></i>
                                        Booking Date
                                    </span>
                                    <div class="date-display">
                                        <i class="bi bi-clock"></i>
                                        <span class="detail-value">
                                            <?= date('M j, Y \a\t h:i A', strtotime($reservation['BOOKDATE'])) ?>
                                        </span>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-credit-card"></i>
                                        Transaction Date
                                    </span>
                                    <div class="date-display">
                                        <i class="bi bi-calendar-check"></i>
                                        <span class="detail-value">
                                            <?= date('M j, Y', strtotime($reservation['TRANSDATE'])) ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Room Details -->
                        <div class="detail-section">
                            <h3>
                                <i class="bi bi-door-open"></i>
                                Room Details
                            </h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-house"></i>
                                        Room Type
                                    </span>
                                    <span class="detail-value info-badge">
                                        <i class="bi bi-star-fill"></i>
                                        <?= htmlspecialchars($reservation['ROOM'] ?? 'N/A') ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-door-open-fill"></i>
                                        Room Number
                                    </span>
                                    <span class="detail-value">
                                        <?= !empty($reservation['ROOMNUM']) ? '<strong>#' . htmlspecialchars($reservation['ROOMNUM']) . '</strong>' : 'Not Assigned' ?>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-hash"></i>
                                        Room ID
                                    </span>
                                    <span class="detail-value"><?= htmlspecialchars($reservation['ROOMID']) ?></span>
                                </div>
                            </div>
                        </div>
                        <!-- Stay Details -->
                        <div class="detail-section">
                            <h3>
                                <i class="bi bi-clock-history"></i>
                                Stay Information
                            </h3>
                            <div class="detail-grid">
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-box-arrow-in-right"></i>
                                        Check-in
                                    </span>
                                    <div class="date-display">
                                        <i class="bi bi-calendar-check-fill"></i>
                                        <span class="detail-value">
                                            <strong><?= date('M j, Y', strtotime($reservation['ARRIVAL'])) ?></strong>
                                            <br>
                                            <small style="color: #6b7280;"><?= date('h:i A', strtotime($reservation['ARRIVAL'])) ?></small>
                                        </span>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-box-arrow-right"></i>
                                        Check-out
                                    </span>
                                    <div class="date-display">
                                        <i class="bi bi-calendar-x-fill"></i>
                                        <span class="detail-value">
                                            <strong><?= date('M j, Y', strtotime($reservation['DEPARTURE'])) ?></strong>
                                            <br>
                                            <small style="color: #6b7280;"><?= date('h:i A', strtotime($reservation['DEPARTURE'])) ?></small>
                                        </span>
                                    </div>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-moon-stars"></i>
                                        Duration
                                    </span>
                                    <span class="detail-value">
                                        <?php
                                        $arrival = new DateTime($reservation['ARRIVAL']);
                                        $departure = new DateTime($reservation['DEPARTURE']);
                                        $nights = $arrival->diff($departure)->days;
                                        ?>
                                        <strong class="info-badge">
                                            <i class="bi bi-calendar3"></i>
                                            <?= $nights ?> Night<?= $nights > 1 ? 's' : '' ?>
                                        </strong>
                                    </span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-currency-dollar"></i>
                                        Total Price
                                    </span>
                                    <span class="detail-value price-highlight">
                                        $<?= number_format($reservation['RPRICE'], 2) ?>
                                    </span>
                                </div>
                            </div>
                            <!-- Show checkout history if checked out -->
                            <?php if ($checkoutHistory): ?>
                                <div class="checkout-history">
                                    <i class="bi bi-info-circle-fill"></i>
                                    <div class="checkout-history-info">
                                        <div class="label">Checkout Information</div>
                                        <div class="value">
                                            Checked out on <?= date('M j, Y \a\t h:i A', strtotime($checkoutHistory['CHECKOUT_DATE'])) ?>
                                            by <?= htmlspecialchars($checkoutHistory['CHECKOUT_BY'] ?? 'System') ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- Payment Information -->
                        <div class="detail-section">
                            <h3>
                                <i class="bi bi-credit-card-2-front"></i>
                                Payment Information
                            </h3>
                            <?php if ($paymentImageUrl): ?>
                                <div class="payment-viewer">
                                    <div class="payment-image-container">
                                        <img src="<?= htmlspecialchars($paymentImageUrl) ?>"
                                             alt="Payment Proof"
                                             class="payment-image"
                                             onclick="openLightbox('<?= htmlspecialchars($paymentImageUrl) ?>')">
                                    </div>
                                    <div class="payment-actions">
                                        <button class="btn-custom btn-info" onclick="openLightbox('<?= htmlspecialchars($paymentImageUrl) ?>')">
                                            <i class="bi bi-zoom-in"></i>
                                            <span>View Full Size</span>
                                        </button>
                                        <a href="<?= htmlspecialchars($paymentImageUrl) ?>" download class="btn-custom btn-success">
                                            <i class="bi bi-download"></i>
                                            <span>Download</span>
                                        </a>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="no-payment">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <h4>No Payment Proof Available</h4>
                                    <p>No payment receipt has been uploaded for this reservation.</p>
                                    <?php if (isset($_SESSION['debug']) && $_SESSION['debug']): ?>
                                    <div class="debug-info">
                                        <strong>Debug Info:</strong><br>
                                        Looking in: <?= htmlspecialchars($uploadDir ?? 'Not set') ?><br>
                                        Reservation ID: <?= htmlspecialchars($reservation['RESERVEID']) ?><br>
                                        Confirmation Code: <?= htmlspecialchars($reservation['CONFIRMATIONCODE']) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <!-- Additional Information -->
                        <?php if (!empty($reservation['PRORPOSE']) || !empty($reservation['REMARKS'])): ?>
                        <div class="detail-section">
                            <h3>
                                <i class="bi bi-chat-left-text"></i>
                                Additional Information
                            </h3>
                            <div class="detail-grid">
                                <?php if (!empty($reservation['PRORPOSE'])): ?>
                                <div class="detail-item">
                                    <span class="detail-label">
                                        <i class="bi bi-flag"></i>
                                        Purpose of Stay
                                    </span>
                                    <span class="detail-value info-badge">
                                        <i class="bi bi-tag-fill"></i>
                                        <?= htmlspecialchars($reservation['PRORPOSE']) ?>
                                    </span>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($reservation['REMARKS'])): ?>
                                <div class="detail-item" style="grid-column: 1 / -1;">
                                    <span class="detail-label">
                                        <i class="bi bi-chat-square-text"></i>
                                        Special Requests / Remarks
                                    </span>
                                    <div style="padding: 1rem; background: white; border-radius: var(--radius-md); border-left: 3px solid var(--accent-gold); margin-top: 0.5rem;">
                                        <?= nl2br(htmlspecialchars($reservation['REMARKS'])) ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <!-- Updated Action Buttons with Enhanced Checkout -->
                    <div class="action-buttons">
                        <a href="edit_reservation.php?id=<?= $reservation['RESERVEID'] ?>" class="btn-custom btn-warning">
                            <i class="bi bi-pencil-square"></i>
                            <span>Edit</span>
                        </a>
                        <?php if ($reservation['STATUS'] === 'Pending'): ?>
                            <a href="confirm_reservation.php?id=<?= $reservation['RESERVEID'] ?>" class="btn-custom btn-success">
                                <i class="bi bi-check-circle-fill"></i>
                                <span>Confirm</span>
                            </a>
                        <?php endif; ?>
                        <?php if ($reservation['STATUS'] === 'Confirmed'): ?>
                            <?php
                            // Check if the current date is within the reservation period or after check-in
                            $currentDate = new DateTime();
                            $arrivalDate = new DateTime($reservation['ARRIVAL']);
                            $departureDate = new DateTime($reservation['DEPARTURE']);
                            // Show checkout button if current date is on or after arrival date
                            if ($currentDate >= $arrivalDate):
                            ?>
                                <a href="checkout_reservation.php?id=<?= $reservation['RESERVEID'] ?>"
                                   class="btn-custom btn-checkout"
                                   onclick="return confirm('⚠️ Proceed with checkout?\n\nGuest: <?= htmlspecialchars($reservation['G_FNAME'] . ' ' . $reservation['G_LNAME']) ?>\nRoom: <?= htmlspecialchars($reservation['ROOM']) ?>\n\nThis will mark the reservation as completed.');">
                                    <i class="bi bi-box-arrow-right"></i>
                                    <span>Check Out</span>
                                </a>
                            <?php else: ?>
                                <!-- Show disabled checkout button if before arrival date -->
                                <button class="btn-custom btn-checkout" disabled style="opacity: 0.6; cursor: not-allowed;"
                                        title="Check-out available from <?= date('M j, Y', strtotime($reservation['ARRIVAL'])) ?>">
                                    <i class="bi bi-box-arrow-right"></i>
                                    <span>Check Out</span>
                                </button>
                            <?php endif; ?>
                        <?php endif; ?>

                        <?php if (in_array($reservation['STATUS'], ['Pending', 'Confirmed'])): ?>
                            <a href="cancel_reservation.php?id=<?= $reservation['RESERVEID'] ?>" class="btn-custom btn-danger"
                               onclick="return confirm('⚠️ Are you sure you want to cancel this reservation?\n\nReservation #<?= $reservation['RESERVEID'] ?>\nGuest: <?= htmlspecialchars($reservation['G_FNAME'] . ' ' . $reservation['G_LNAME']) ?>\n\nThis action cannot be undone.');">
                                <i class="bi bi-x-circle-fill"></i>
                                <span>Cancel</span>
                            </a>
                        <?php endif; ?>

                        <?php if ($reservation['STATUS'] === 'Checked Out'): ?>
                            <!-- Show completed status -->
                            <span class="btn-custom btn-completed" style="cursor: default;">
                                <i class="bi bi-check-all"></i>
                                <span>Completed</span>
                            </span>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle Sidebar
        function toggleSidebar() {
            document.getElementById('sidebar').classList.toggle('active');
        }

        // Close sidebar on click outside (mobile)
        document.addEventListener('click', function(event) {
            const sidebar = document.getElementById('sidebar');
            const toggle = document.querySelector('.mobile-menu-toggle');
            
            if (window.innerWidth <= 768) {
                if (!sidebar.contains(event.target) && !toggle.contains(event.target)) {
                    sidebar.classList.remove('active');
                }
            }
        });

        // Auto-dismiss alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-dismissible');
            alerts.forEach(alert => {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);

        // Lightbox Functions
        function openLightbox(imageSrc) {
            const lightbox = document.getElementById('paymentLightbox');
            const lightboxImage = document.getElementById('lightboxImage');
            lightboxImage.src = imageSrc;
            lightbox.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            const lightbox = document.getElementById('paymentLightbox');
            lightbox.classList.remove('active');
            document.body.style.overflow = '';
        }

        function downloadPayment() {
            const imageSrc = document.getElementById('lightboxImage').src;
            const link = document.createElement('a');
            link.href = imageSrc;
            link.download = 'payment_proof_' + '<?= htmlspecialchars($reservation['CONFIRMATIONCODE']) ?>';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }

        function printPayment() {
            const imageSrc = document.getElementById('lightboxImage').src;
            const printWindow = window.open('', '', 'width=800,height=600');
            printWindow.document.write('<html><head><title>Payment Proof</title>');
            printWindow.document.write('<style>body { margin: 0; display: flex; justify-content: center; align-items: center; min-height: 100vh; } img { max-width: 100%; height: auto; }</style>');
            printWindow.document.write('</head><body>');
            printWindow.document.write('<img src="' + imageSrc + '" />');
            printWindow.document.write('</body></html>');
            printWindow.document.close();
            printWindow.focus();
            
            // Wait for image to load before printing
            printWindow.onload = function() {
                printWindow.print();
                printWindow.close();
            };
        }

        // Close lightbox on click outside
        document.getElementById('paymentLightbox').addEventListener('click', function(e) {
            if (e.target === this) {
                closeLightbox();
            }
        });

        // Close lightbox on ESC key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeLightbox();
            }
        });

        // Add keyboard navigation for lightbox
        document.addEventListener('keydown', function(e) {
            const lightbox = document.getElementById('paymentLightbox');
            if (lightbox.classList.contains('active')) {
                if (e.key === 'ArrowDown' || e.key === 's') {
                    e.preventDefault();
                    downloadPayment();
                } else if (e.key === 'p') {
                    e.preventDefault();
                    printPayment();
                }
            }
        });

        // Add loading animation for images
        document.querySelectorAll('.payment-image').forEach(img => {
            img.addEventListener('load', function() {
                this.style.opacity = '1';
            });
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.3s ease';
        });

        // Add tooltip for disabled checkout button
        const disabledCheckoutBtn = document.querySelector('.btn-checkout[disabled]');
        if (disabledCheckoutBtn) {
            disabledCheckoutBtn.addEventListener('mouseenter', function(e) {
                // Create tooltip
                const tooltip = document.createElement('div');
                tooltip.className = 'custom-tooltip';
                tooltip.textContent = this.getAttribute('title');
                tooltip.style.cssText = `
                    position: absolute;
                    background: var(--primary-dark);
                    color: white;
                    padding: 0.5rem 1rem;
                    border-radius: 0.5rem;
                    font-size: 0.875rem;
                    box-shadow: var(--shadow-lg);
                    z-index: 1000;
                    pointer-events: none;
                    opacity: 0;
                    transition: opacity 0.3s ease;
                    white-space: nowrap;
                `;
                document.body.appendChild(tooltip);
                
                // Position tooltip
                const rect = this.getBoundingClientRect();
                tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
                tooltip.style.top = rect.top - tooltip.offsetHeight - 10 + 'px';
                
                // Show tooltip
                setTimeout(() => {
                    tooltip.style.opacity = '1';
                }, 10);
                
                // Remove tooltip on mouseleave
                this.addEventListener('mouseleave', function() {
                    tooltip.style.opacity = '0';
                    setTimeout(() => {
                        tooltip.remove();
                    }, 300);
                }, { once: true });
            });
        }

        // Add print-specific styles dynamically
        function addPrintStyles() {
            const style = document.createElement('style');
            style.textContent = `
                @media print {
                    @page {
                        margin: 0.5in;
                        size: letter portrait;
                    }
                    
                    body * {
                        visibility: hidden;
                    }
                    
                    .main-content,
                    .main-content * {
                        visibility: visible;
                    }
                    
                    .main-content {
                        position: absolute;
                        left: 0;
                        top: 0;
                        width: 100%;
                    }
                    
                    .details-card {
                        page-break-inside: avoid;
                    }
                    
                    .detail-section {
                        page-break-inside: avoid;
                    }
                    
                    .payment-image-container img {
                        max-height: 400px;
                        width: auto;
                        margin: 0 auto;
                        display: block;
                    }
                }
            `;
            document.head.appendChild(style);
        }

        // Initialize print styles
        addPrintStyles();

        // Add smooth scroll behavior
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });

        // Add animation on scroll
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };

        const observer = new IntersectionObserver(function(entries) {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.style.opacity = '1';
                    entry.target.style.transform = 'translateY(0)';
                    observer.unobserve(entry.target);
                }
            });
        }, observerOptions);

        // Observe detail sections
        document.querySelectorAll('.detail-section').forEach(section => {
            section.style.opacity = '0';
            section.style.transform = 'translateY(20px)';
            section.style.transition = 'all 0.6s ease';
            observer.observe(section);
        });

        // Add confirmation animation for action buttons
        document.querySelectorAll('.btn-custom').forEach(btn => {
            if (!btn.hasAttribute('disabled') && btn.tagName === 'A') {
                btn.addEventListener('click', function(e) {
                    // Don't add effect if it has onclick attribute (confirmation dialog)
                    if (this.hasAttribute('onclick')) {
                        return;
                    }
                    
                    // Add ripple effect
                    const ripple = document.createElement('span');
                    ripple.className = 'ripple';
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.7);
                        transform: scale(0);
                        animation: ripple-effect 0.6s ease-out;
                    `;
                    
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    ripple.style.width = ripple.style.height = size + 'px';
                    ripple.style.left = (e.clientX - rect.left - size / 2) + 'px';
                    ripple.style.top = (e.clientY - rect.top - size / 2) + 'px';
                    
                    this.appendChild(ripple);
                    
                    setTimeout(() => {
                        ripple.remove();
                    }, 600);
                });
            }
        });

        // Add ripple animation keyframes
        const rippleStyle = document.createElement('style');
        rippleStyle.textContent = `
            @keyframes ripple-effect {
                to {
                    transform: scale(2);
                    opacity: 0;
                }
            }
            
            .ripple {
                pointer-events: none;
            }
        `;
        document.head.appendChild(rippleStyle);

        // Handle window resize
        let resizeTimer;
        window.addEventListener('resize', function() {
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(function() {
                // Close mobile menu on desktop resize
                if (window.innerWidth > 768) {
                    document.getElementById('sidebar').classList.remove('active');
                }
            }, 250);
        });

        // Add page load animation
        window.addEventListener('load', function() {
            document.body.style.opacity = '0';
            document.body.style.transition = 'opacity 0.5s ease';
            setTimeout(() => {
                document.body.style.opacity = '1';
            }, 100);
        });

        // Enhanced error logging for debugging
        <?php if (isset($_SESSION['debug']) && $_SESSION['debug']): ?>
        console.group('Reservation Details Debug');
        console.log('Reservation ID:', '<?= $reservation['RESERVEID'] ?>');
        console.log('Confirmation Code:', '<?= $reservation['CONFIRMATIONCODE'] ?>');
        console.log('Status:', '<?= $reservation['STATUS'] ?>');
        console.log('Payment Image URL:', '<?= $paymentImageUrl ?? "null" ?>');
        console.log('Payment Image Path:', '<?= $paymentImagePath ?? "null" ?>');
        console.log('Current Date:', new Date().toISOString());
        console.log('Arrival Date:', '<?= $reservation['ARRIVAL'] ?>');
        console.log('Departure Date:', '<?= $reservation['DEPARTURE'] ?>');
        <?php if ($checkoutHistory): ?>
        console.log('Checkout Date:', '<?= $checkoutHistory['CHECKOUT_DATE'] ?? "null" ?>');
        console.log('Checkout By:', '<?= $checkoutHistory['CHECKOUT_BY'] ?? "null" ?>');
        <?php endif; ?>
        console.groupEnd();
        <?php endif; ?>

        // Add checkout countdown timer for today's checkouts
        <?php if ($reservation['STATUS'] === 'Confirmed'): ?>
        <?php
            $currentDate = new DateTime();
            $departureDate = new DateTime($reservation['DEPARTURE']);
            $isCheckoutDay = $currentDate->format('Y-m-d') === $departureDate->format('Y-m-d');
        ?>
        <?php if ($isCheckoutDay): ?>
        // Show checkout reminder
        const checkoutReminder = document.createElement('div');
        checkoutReminder.className = 'checkout-reminder';
        checkoutReminder.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: linear-gradient(135deg, #f59e0b, #d97706);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 1rem;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            z-index: 999;
            animation: slideIn 0.5s ease;
        `;
        checkoutReminder.innerHTML = `
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <i class="bi bi-clock-fill" style="font-size: 1.5rem;"></i>
                <div>
                    <strong>Checkout Day!</strong><br>
                    <small>This guest is scheduled to check out today.</small>
                </div>
            </div>
        `;
        document.body.appendChild(checkoutReminder);
        
        // Remove reminder after 10 seconds
        setTimeout(() => {
            checkoutReminder.style.animation = 'slideOut 0.5s ease';
            setTimeout(() => {
                checkoutReminder.remove();
            }, 500);
        }, 10000);
        <?php endif; ?>
        <?php endif; ?>
    </script>
</body>
</html>