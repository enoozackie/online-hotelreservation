<?php

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

// Initialize database connection
 $database = new Lourdian\MonbelaHotel\Core\Database();
 $conn = $database->getConnection();

// Initialize models with database connection
 $guest = new Guest($conn);
 $room = new Room($conn);
 $booking = new Booking($conn);

// Fetch guest data
try {
    $guestData = $guest->getById($_SESSION['id']);
    if (!$guestData) {
        session_destroy();
        header("Location: login.php");
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching guest data: " . $e->getMessage());
    session_destroy();
    header("Location: login.php");
    exit;
}

// Get guest reservations
 $reservations = [];
try {
    $reservations = $booking->getByGuestId($_SESSION['id']);
} catch (Exception $e) {
    error_log("Error fetching reservations: " . $e->getMessage());
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'cancel') {
        $reservationId = $_POST['reservation_id'] ?? '';
        if (!empty($reservationId)) {
            try {
                // Find reservation from list we already have
                $reservation = null;
                foreach ($reservations as $res) {
                    if ($res['RESERVEID'] == $reservationId) {
                        $reservation = $res;
                        break;
                    }
                }
                
                if ($reservation) {
                    // Check if cancellation is still allowed (within 12 hours of booking)
                    $bookingTime = new DateTime($reservation['BOOKDATE']);
                    $currentTime = new DateTime();
                    $interval = $currentTime->diff($bookingTime);
                    $totalMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
                    $hoursPassed = $totalMinutes / 60;
                    
                    if ($hoursPassed < 12) {
                        if ($booking->cancelReservation($reservationId)) {
                            $_SESSION['success_message'] = 'Reservation cancelled successfully!';
                        } else {
                            $_SESSION['error_message'] = 'Failed to cancel reservation. Please try again.';
                        }
                    } else {
                        $_SESSION['error_message'] = 'Cancellation is only allowed within 12 hours of booking. Your reservation was made more than 12 hours ago.';
                    }
                } else {
                    $_SESSION['error_message'] = 'Reservation not found.';
                }
            } catch (Exception $e) {
                error_log("Error cancelling reservation: " . $e->getMessage());
                $_SESSION['error_message'] = 'An error occurred while cancelling reservation.';
            }
        }
        header("Location: my_reservations.php");
        exit;
    }
    
    if (isset($_POST['action']) && $_POST['action'] === 'upload_payment') {
        $reservationId = $_POST['reservation_id'] ?? '';
        if (!empty($reservationId) && isset($_FILES['payment_proof'])) {
            try {
                $file = $_FILES['payment_proof'];
                
                // Validate file
                $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'application/pdf'];
                $maxSize = 5 * 1024 * 1024; // 5MB
                
                if ($file['error'] !== UPLOAD_ERR_OK) {
                    $_SESSION['error_message'] = 'Error uploading file. Please try again.';
                } elseif (!in_array($file['type'], $allowedTypes)) {
                    $_SESSION['error_message'] = 'Invalid file type. Only JPG, PNG, GIF, and PDF files are allowed.';
                } elseif ($file['size'] > $maxSize) {
                    $_SESSION['error_message'] = 'File is too large. Maximum size is 5MB.';
                } else {
                    // Create upload directory if it doesn't exist
                    $uploadDir = __DIR__ . '/../uploads/payment/';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0755, true);
                    }
                    
                    // Generate unique filename
                    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                    $filename = 'payment_' . $reservationId . '_' . time() . '.' . $extension;
                    $uploadPath = $uploadDir . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                        // Update database with payment proof filename
                        $stmt = $conn->prepare("UPDATE tblreservation SET payment_proof = ? WHERE RESERVEID = ?");
                        $stmt->execute([$filename, $reservationId]);
                        
                        $_SESSION['success_message'] = 'Payment proof uploaded successfully!';
                    } else {
                        $_SESSION['error_message'] = 'Failed to upload file. Please try again.';
                    }
                }
            } catch (Exception $e) {
                error_log("Error uploading payment: " . $e->getMessage());
                $_SESSION['error_message'] = 'An error occurred while uploading payment proof.';
            }
        }
        header("Location: my_reservations.php");
        exit;
    }
}

// Display messages
 $success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
 $error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Reservations - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <style>
        :root {
            --primary: #2563eb;
            --primary-dark: #1d4ed8;
            --secondary: #7c3aed;
            --accent: #06b6d4;
            --success: #10b981;
            --danger: #ef4444;
            --warning: #f59e0b;
            --light: #f8fafc;
            --dark: #1e293b;
            --gray: #64748b;
            --border: #e5e7eb;
            --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
            min-height: 100vh;
            color: var(--dark);
            position: relative;
            overflow-x: hidden;
        }

        /* Animated Background */
        .bg-animation {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            opacity: 0.4;
            overflow: hidden;
        }

        .bg-animation span {
            position: absolute;
            display: block;
            width: 20px;
            height: 20px;
            background: rgba(99, 102, 241, 0.2);
            animation: move 25s linear infinite;
            bottom: -150px;
        }

        .bg-animation span:nth-child(1) {
            left: 25%;
            width: 80px;
            height: 80px;
            animation-delay: 0s;
        }

        .bg-animation span:nth-child(2) {
            left: 10%;
            width: 20px;
            height: 20px;
            animation-delay: 2s;
            animation-duration: 12s;
        }

        .bg-animation span:nth-child(3) {
            left: 70%;
            width: 20px;
            height: 20px;
            animation-delay: 4s;
        }

        .bg-animation span:nth-child(4) {
            left: 40%;
            width: 60px;
            height: 60px;
            animation-delay: 0s;
            animation-duration: 18s;
        }

        .bg-animation span:nth-child(5) {
            left: 65%;
            width: 20px;
            height: 20px;
            animation-delay: 0s;
        }

        .bg-animation span:nth-child(6) {
            left: 75%;
            width: 110px;
            height: 110px;
            animation-delay: 3s;
        }

        .bg-animation span:nth-child(7) {
            left: 35%;
            width: 150px;
            height: 150px;
            animation-delay: 7s;
        }

        .bg-animation span:nth-child(8) {
            left: 50%;
            width: 25px;
            height: 25px;
            animation-delay: 15s;
            animation-duration: 45s;
        }

        .bg-animation span:nth-child(9) {
            left: 20%;
            width: 15px;
            height: 15px;
            animation-delay: 2s;
            animation-duration: 35s;
        }

        .bg-animation span:nth-child(10) {
            left: 85%;
            width: 150px;
            height: 150px;
            animation-delay: 0s;
            animation-duration: 11s;
        }

        @keyframes move {
            0% {
                transform: translateY(0) rotate(0deg);
                opacity: 1;
                border-radius: 0;
            }
            100% {
                transform: translateY(-1000px) rotate(720deg);
                opacity: 0;
                border-radius: 50%;
            }
        }

        /* Header */
        .header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            color: var(--dark);
            padding: 1rem 0;
            box-shadow: var(--shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid var(--border);
        }

        .header .container {
            display: flex;
            justify-content: space-between;
            align-items: center;
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 1.5rem;
        }

        .brand {
            display: flex;
            align-items: center;
            font-size: 1.75rem;
            font-weight: 800;
            text-decoration: none;
            color: var(--primary);
            transition: all 0.3s ease;
        }

        .brand:hover {
            transform: scale(1.05);
        }

        .brand i {
            margin-right: 0.75rem;
            font-size: 2.25rem;
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            margin: 0;
            padding: 0;
        }

        .nav-links a {
            color: var(--dark);
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            position: relative;
            padding: 0.5rem 0;
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .nav-links a:hover {
            color: var(--primary);
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-links a.active {
            color: var(--primary);
            font-weight: 600;
        }

        .nav-links a.active::after {
            width: 100%;
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.5rem;
            cursor: pointer;
        }

        /* Main Container */
        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

        /* Page Header */
        .page-header {
            background: white;
            border-radius: 16px;
            padding: 2.5rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: relative;
            overflow: hidden;
        }

        .page-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 5px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            position: relative;
        }

        .page-subtitle {
            color: var(--gray);
            margin: 0.5rem 0 0;
            font-size: 1.1rem;
        }

        /* Reservations List */
        .reservations-container {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .reservations-container::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
        }

        .reservation-card {
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
        }

        .reservation-card:last-child {
            margin-bottom: 0;
        }

        .reservation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--primary);
            transform: scaleY(0);
            transition: transform 0.3s ease;
        }

        .reservation-card:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-5px);
        }

        .reservation-card:hover::before {
            transform: scaleY(1);
        }

        .reservation-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border);
            flex-wrap: wrap;
            gap: 1rem;
        }

        .reservation-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin: 0;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .reservation-title i {
            color: var(--primary);
        }

        .reservation-status {
            padding: 0.5rem 1.25rem;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .status-pending {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
        }

        .status-confirmed {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
        }

        .status-cancelled {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
        }

        .reservation-details {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .detail-item {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            padding: 1rem;
            border-radius: 12px;
            background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f5 100%);
            transition: all 0.3s ease;
            border: 1px solid rgba(229, 231, 235, 0.5);
        }

        .detail-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            border-color: var(--primary);
        }

        .detail-item i {
            color: var(--primary);
            font-size: 1.25rem;
            margin-top: 0.25rem;
        }

        .detail-text {
            display: flex;
            flex-direction: column;
        }

        .detail-label {
            font-size: 0.875rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
            font-weight: 600;
        }

        .detail-value {
            font-size: 1.125rem;
            font-weight: 600;
            color: var(--dark);
        }

        .reservation-actions {
            display: flex;
            gap: 1rem;
            margin-top: 1.5rem;
            flex-wrap: wrap;
        }

        .cancellation-timer {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
            border-radius: 12px;
            font-size: 0.95rem;
            margin-top: 1.5rem;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .cancellation-timer i {
            color: #92400e;
            font-size: 1.25rem;
        }

        .cancellation-expired {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 1rem 1.25rem;
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border-radius: 12px;
            font-size: 0.95rem;
            margin-top: 1.5rem;
            font-weight: 500;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .cancellation-expired i {
            color: #991b1b;
            font-size: 1.25rem;
        }

        /* Empty State */
        .empty-state {
            background: white;
            padding: 4rem 2rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: var(--shadow-lg);
            position: relative;
            overflow: hidden;
        }

        .empty-state::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 3px;
            background: linear-gradient(90deg, var(--primary), var(--secondary), var(--accent));
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--gray);
            margin-bottom: 1.5rem;
        }

        .empty-state h3 {
            color: var(--dark);
            margin-bottom: 1rem;
            font-size: 1.75rem;
        }

        .empty-state p {
            color: var(--gray);
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        /* Buttons */
        .btn {
            padding: 0.875rem 2rem;
            border-radius: 12px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
        }

        .btn::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 0;
            height: 0;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            transform: translate(-50%, -50%);
            transition: width 0.6s ease, height 0.6s ease;
        }

        .btn:active::before {
            width: 300px;
            height: 300px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            box-shadow: 0 4px 15px rgba(37, 99, 235, 0.2);
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(37, 99, 235, 0.3);
        }

        .btn-danger {
            background: linear-gradient(135deg, var(--danger), #dc2626);
            color: white;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
        }

        .btn-danger:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(239, 68, 68, 0.3);
        }

        .btn-secondary {
            background: linear-gradient(135deg, var(--gray), #475569);
            color: white;
            box-shadow: 0 4px 15px rgba(100, 116, 139, 0.2);
        }

        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(100, 116, 139, 0.3);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #059669);
            color: white;
            box-shadow: 0 4px 15px rgba(16, 185, 129, 0.2);
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.3);
        }

        .btn-info {
            background: linear-gradient(135deg, #0891b2, #0e7490);
            color: white;
            box-shadow: 0 4px 15px rgba(8, 145, 178, 0.2);
        }

        .btn-info:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(8, 145, 178, 0.3);
        }

        .btn-sm {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        .payment-upload-section {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f0f9ff, #e0f2fe);
            border: 1px solid #bfdbfe;
            border-radius: 12px;
            position: relative;
            overflow: hidden;
        }

        .payment-upload-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--accent);
        }

        .payment-status {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            padding: 0.75rem 1.25rem;
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border-radius: 12px;
            font-size: 0.95rem;
            margin-top: 1.5rem;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .payment-status i {
            color: #065f46;
            font-size: 1.25rem;
        }

        .payment-pending {
            background: linear-gradient(135deg, #fef3c7, #fde68a);
            color: #92400e;
        }

        .payment-pending i {
            color: #92400e;
        }

        .file-upload-wrapper {
            display: flex;
            gap: 1rem;
            align-items: center;
            margin-top: 1rem;
            flex-wrap: wrap;
        }

        .file-upload-wrapper input[type="file"] {
            flex: 1;
            padding: 0.75rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 0.875rem;
            transition: all 0.3s ease;
            min-width: 200px;
        }

        .file-upload-wrapper input[type="file"]:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .payment-preview {
            display: flex;
            align-items: flex-start;
            gap: 1.5rem;
            margin-top: 1.5rem;
            padding: 1.5rem;
            background: linear-gradient(135deg, #f8fafc, #f1f5f9);
            border-radius: 12px;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .payment-preview::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--success);
        }

        .thumbnail-container {
            position: relative;
            cursor: pointer;
            transition: transform 0.3s ease;
            border-radius: 12px;
            overflow: hidden;
            border: 3px solid var(--primary);
            box-shadow: var(--shadow);
            flex-shrink: 0;
        }

        .thumbnail-container img {
            width: 120px;
            height: 120px;
            object-fit: cover;
            display: block;
            transition: transform 0.3s ease;
        }

        .thumbnail-container::after {
            content: '\F341';
            font-family: 'bootstrap-icons';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(37, 99, 235, 0.95);
            color: white;
            font-size: 2.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .thumbnail-container:hover {
            transform: scale(1.05);
        }

        .thumbnail-container:hover::after {
            opacity: 1;
        }

        .pdf-thumbnail {
            width: 120px;
            height: 120px;
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s ease;
            border: 3px solid #ef4444;
            box-shadow: var(--shadow);
            flex-shrink: 0;
        }

        .pdf-thumbnail:hover {
            transform: scale(1.05);
        }

        .pdf-thumbnail i {
            font-size: 3.5rem;
            color: #ef4444;
        }

        .file-info {
            flex: 1;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .file-name {
            font-weight: 600;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 1rem;
            word-break: break-all;
        }

        .file-name i {
            color: var(--primary);
            font-size: 1.1rem;
        }

        .file-size {
            font-size: 0.875rem;
            color: var(--gray);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .file-size i {
            color: var(--gray);
        }

        .file-actions {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
            margin-top: 0.75rem;
        }

        /* Payment Instructions */
        .payment-instructions {
            background: linear-gradient(135deg, #f8f9fa, #f1f3f5);
            border: 1px solid #dee2e6;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            position: relative;
            overflow: hidden;
        }

        .payment-instructions::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: var(--warning);
        }

        .payment-instructions .card {
            border-radius: 12px;
            overflow: hidden;
            height: 100%;
            box-shadow: var(--shadow);
            transition: transform 0.3s ease;
        }

        .payment-instructions .card:hover {
            transform: translateY(-3px);
        }

        .payment-instructions .card-title {
            color: var(--primary);
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .payment-instructions .alert-info {
            background-color: #e7f1ff;
            border-color: #b3d4ff;
            color: #084298;
            border-radius: 8px;
        }

        .payment-instructions .alert-warning {
            background-color: #fff3cd;
            border-color: #ffecb5;
            color: #664d03;
            border-radius: 8px;
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
            overflow: hidden;
        }

        .alert::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
        }

        .alert-success {
            background: linear-gradient(135deg, #d1fae5, #a7f3d0);
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-success::before {
            background: var(--success);
        }

        .alert-danger {
            background: linear-gradient(135deg, #fee2e2, #fecaca);
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-danger::before {
            background: var(--danger);
        }

        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1050;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.9);
            overflow: auto;
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .modal.show {
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 1;
        }

        .modal-content {
            background-color: white;
            margin: auto;
            padding: 0;
            border-radius: 16px;
            box-shadow: var(--shadow-xl);
            width: 90%;
            max-width: 1000px;
            max-height: 90vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transform: scale(0.9);
            transition: transform 0.3s ease;
        }

        .modal.show .modal-content {
            transform: scale(1);
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid var(--border);
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: white;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin: 0;
            color: var(--dark);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray);
            transition: all 0.3s ease;
            width: 40px;
            height: 40px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
        }

        .modal-close:hover {
            color: var(--dark);
            background: #f1f5f9;
            transform: scale(1.1);
        }

        .modal-body {
            padding: 0;
            overflow: auto;
            flex: 1;
            display: flex;
            justify-content: center;
            align-items: center;
            background: #000;
        }

        .modal-body img {
            max-width: 100%;
            max-height: 80vh;
            object-fit: contain;
            cursor: zoom-in;
            transition: transform 0.3s ease;
        }

        .modal-body img.fullscreen {
            cursor: zoom-out;
            transform: scale(1.5);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .nav-links {
                display: none;
                position: absolute;
                top: 100%;
                left: 0;
                right: 0;
                background: white;
                flex-direction: column;
                padding: 1rem 0;
                box-shadow: var(--shadow-lg);
            }

            .nav-links.active {
                display: flex;
            }

            .mobile-toggle {
                display: block;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .reservation-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .reservation-actions {
                flex-direction: column;
            }

            .payment-preview {
                flex-direction: column;
                align-items: flex-start;
            }

            .file-actions {
                flex-direction: column;
                width: 100%;
            }

            .file-actions .btn {
                width: 100%;
                justify-content: center;
            }

            .modal-content {
                width: 95%;
                margin: 1rem;
            }

            .thumbnail-container img,
            .pdf-thumbnail {
                width: 100px;
                height: 100px;
            }
        }
    </style>
</head>
<body>
    <!-- Animated Background -->
    <div class="bg-animation">
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
        <span></span>
    </div>

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
                <li><a href="booking.php"><i class="bi bi-calendar-plus"></i> Home</a></li>
                <li><a href="guest_profile.php"><i class="bi bi-person-circle"></i> Profile</a></li>
                <li><a href="my_reservations.php" class="active"><i class="bi bi-calendar-check"></i> My Reservations</a></li>
                <li><a href="guest_profile.php?logout=1"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header" data-aos="fade-up">
            <div>
                <h1 class="page-title">My Reservations</h1>
                <p class="page-subtitle">View and manage your hotel reservations</p>
            </div>
            <a href="booking.php" class="btn btn-primary" data-aos="fade-left" data-aos-delay="200">
                <i class="bi bi-calendar-plus"></i> Book a Room
            </a>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success" data-aos="fade-down">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error_message): ?>
            <div class="alert alert-danger" data-aos="fade-down">
                <i class="bi bi-exclamation-triangle-fill"></i> <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Reservations List -->
        <div class="reservations-container" data-aos="fade-up" data-aos-delay="100">
            <?php if (empty($reservations)): ?>
                <div class="empty-state">
                    <i class="bi bi-calendar-x"></i>
                    <h3>No reservations found</h3>
                    <p>You haven't made any reservations yet. Book a room to get started!</p>
                    <a href="booking.php" class="btn btn-primary">
                        <i class="bi bi-calendar-plus"></i> Book a Room
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($reservations as $index => $reservation): ?>
                    <?php
                    // Calculate time since booking (12-hour cancellation window)
                    $bookingTime = new DateTime($reservation['BOOKDATE']);
                    $currentTime = new DateTime();
                    $interval = $currentTime->diff($bookingTime);
                    $totalMinutes = ($interval->days * 24 * 60) + ($interval->h * 60) + $interval->i;
                    $hoursPassed = $totalMinutes / 60;
                    $canCancel = $hoursPassed < 12;
                    $minutesLeft = max(0, (12 * 60) - $totalMinutes);
                    $hoursLeft = floor($minutesLeft / 60);
                    $minutesRemaining = $minutesLeft % 60;
                    
                    // Get file info if payment proof exists
                    $paymentFilePath = '';
                    $fileSize = 0;
                    if (!empty($reservation['payment_proof'])) {
                        $paymentFilePath = __DIR__ . '/../uploads/payment/' . $reservation['payment_proof'];
                        if (file_exists($paymentFilePath)) {
                            $fileSize = filesize($paymentFilePath);
                        }
                    }
                    ?>
                    <div class="reservation-card" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                        <div class="reservation-header">
                            <h3 class="reservation-title">
                                <i class="bi bi-door-closed"></i>
                                <?= htmlspecialchars($reservation['ROOM'] ?? 'Room') ?> - Room #<?= htmlspecialchars($reservation['ROOMNUM'] ?? '') ?>
                            </h3>
                            <span class="reservation-status status-<?= strtolower($reservation['STATUS'] ?? 'pending') ?>">
                                <i class="bi bi-circle-fill"></i>
                                <?= htmlspecialchars($reservation['STATUS'] ?? 'Pending') ?>
                            </span>
                        </div>
                        
                        <div class="reservation-details">
                            <div class="detail-item">
                                <i class="bi bi-calendar-check"></i>
                                <div class="detail-text">
                                    <div class="detail-label">Check-in</div>
                                    <div class="detail-value"><?= date('M j, Y', strtotime($reservation['ARRIVAL'])) ?></div>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-calendar-x"></i>
                                <div class="detail-text">
                                    <div class="detail-label">Check-out</div>
                                    <div class="detail-value"><?= date('M j, Y', strtotime($reservation['DEPARTURE'])) ?></div>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-tag"></i>
                                <div class="detail-text">
                                    <div class="detail-label">Confirmation Code</div>
                                    <div class="detail-value"><?= htmlspecialchars($reservation['CONFIRMATIONCODE'] ?? '') ?></div>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-currency-dollar"></i>
                                <div class="detail-text">
                                    <div class="detail-label">Total Price</div>
                                    <div class="detail-value">₱<?= number_format($reservation['RPRICE'] ?? 0, 2) ?></div>
                                </div>
                            </div>
                            <div class="detail-item">
                                <i class="bi bi-clock"></i>
                                <div class="detail-text">
                                    <div class="detail-label">Booked On</div>
                                    <div class="detail-value"><?= date('M j, Y g:i A', strtotime($reservation['BOOKDATE'])) ?></div>
                                </div>
                            </div>
                        </div>
                        
                        <?php if (strtolower($reservation['STATUS'] ?? 'pending') !== 'cancelled'): ?>
                            <!-- Payment Upload Section -->
                            <?php if (!empty($reservation['payment_proof'])): ?>
                                <div class="payment-status">
                                    <i class="bi bi-check-circle-fill"></i>
                                    <span>Payment proof uploaded successfully</span>
                                </div>
                                
                                <div class="payment-preview">
                                    <?php
                                    $fileExtension = strtolower(pathinfo($reservation['payment_proof'], PATHINFO_EXTENSION));
                                    $isImage = in_array($fileExtension, ['jpg', 'jpeg', 'png', 'gif']);
                                    $fileUrl = 'uploads/payment/' . htmlspecialchars($reservation['payment_proof']);
                                    ?>
                                    
                                    <?php if ($isImage): ?>
                                        <div class="thumbnail-container" onclick="viewPaymentProof('<?= htmlspecialchars($reservation['payment_proof']) ?>', 'image')">
                                            <img src="<?= $fileUrl ?>" alt="Payment Proof Thumbnail">
                                        </div>
                                    <?php else: ?>
                                        <div class="pdf-thumbnail" onclick="viewPaymentProof('<?= htmlspecialchars($reservation['payment_proof']) ?>', 'pdf')">
                                            <i class="bi bi-file-earmark-pdf"></i>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="file-info">
                                        <div class="file-name">
                                            <i class="bi bi-paperclip"></i>
                                            <?= htmlspecialchars($reservation['payment_proof']) ?>
                                        </div>
                                        <?php if (file_exists($paymentFilePath)): ?>
                                            <div class="file-size">
                                                <i class="bi bi-hdd"></i>
                                                <?= round($fileSize / 1024, 2) ?> KB
                                            </div>
                                        <?php endif; ?>
                                        
                                        <!-- Action Buttons -->
                                        <div class="file-actions">
                                            <button class="btn btn-primary btn-sm" onclick="viewPaymentProof('<?= htmlspecialchars($reservation['payment_proof']) ?>', '<?= $isImage ? 'image' : 'pdf' ?>')">
                                                <i class="bi bi-eye-fill"></i> View Payment Proof
                                            </button>
                                            <a href="<?= $fileUrl ?>" class="btn btn-success btn-sm" download>
                                                <i class="bi bi-download"></i> Download
                                            </a>
                                            <a href="<?= $fileUrl ?>" class="btn btn-info btn-sm" target="_blank">
                                                <i class="bi bi-box-arrow-up-right"></i> Open in New Tab
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="payment-upload-section">
                                    <h4 style="font-size: 1.25rem; margin-bottom: 1rem; color: var(--dark); display: flex; align-items: center; gap: 0.75rem;">
                                        <i class="bi bi-upload"></i> Upload Payment Proof
                                    </h4>
                                    <p style="font-size: 0.95rem; color: var(--gray); margin-bottom: 1rem;">
                                        Please upload your payment receipt or proof of payment (JPG, PNG, GIF, or PDF - Max 5MB)<br>
                                        <strong>Note: Only one payment proof can be uploaded per reservation.</strong>
                                    </p>
                                    
                                    <!-- GCash Payment Instructions -->
                                    <div class="payment-instructions mb-3">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <div class="card border-primary">
                                                    <div class="card-body">
                                                        <h5 class="card-title"><i class="bi bi-qr-code"></i> GCash Payment</h5>
                                                        <p class="card-text">Scan this QR code to pay via GCash:</p>
                                                        <div class="text-center mb-3">
                                                            <!-- Replace this with your actual GCash QR code image -->
                                                            <img src="../uploads/gcashQR/QR.jpg" alt="GCash QR Code" class="img-fluid" style="max-width: 200px; border: 2px solid #e6e6e6; border-radius: 10px; padding: 10px;">
                                                            <p class="text-muted mt-2" style="font-size: 0.9rem;">QR Code for GCash Payment</p>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="card border-success">
                                                    <div class="card-body">
                                                        <h5 class="card-title"><i class="bi bi-phone"></i> Manual GCash Transfer</h5>
                                                        <p class="card-text">You can also send payment to:</p>
                                                        <div class="alert alert-info">
                                                            <div class="d-flex align-items-center mb-2">
                                                                <i class="bi bi-telephone-fill me-2"></i>
                                                                <strong>GCash Number:</strong>
                                                            </div>
                                                            <h3 class="text-center text-primary">0917 123 4567</h3>
                                                            <div class="d-flex align-items-center mt-2">
                                                                <i class="bi bi-person-fill me-2"></i>
                                                                <strong>Account Name:</strong> MONBELA HOTEL
                                                            </div>
                                                        </div>
                                                        <div class="alert alert-warning">
                                                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                                                            <small>
                                                                <strong>Important:</strong> Use your reservation code <strong><?= htmlspecialchars($reservation['CONFIRMATIONCODE'] ?? '') ?></strong> as reference/note when sending payment.
                                                            </small>
                                                        </div>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <form method="POST" enctype="multipart/form-data" style="margin: 0;">
                                        <input type="hidden" name="action" value="upload_payment">
                                        <input type="hidden" name="reservation_id" value="<?= $reservation['RESERVEID'] ?>">
                                        <div class="file-upload-wrapper">
                                            <input type="file" name="payment_proof" accept="image/*,.pdf" required>
                                            <button type="submit" class="btn btn-success btn-sm">
                                                <i class="bi bi-upload"></i> Upload
                                            </button>
                                        </div>
                                    </form>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Cancellation Timer and Actions -->
                            <?php if ($canCancel): ?>
                                <div class="cancellation-timer">
                                    <i class="bi bi-clock-history"></i>
                                    <span>You have <?= $hoursLeft ?>h <?= $minutesRemaining ?>m left to cancel this reservation</span>
                                </div>
                            <?php else: ?>
                                <div class="cancellation-expired">
                                    <i class="bi bi-x-circle"></i>
                                    <span>Cancellation period has expired (12-hour limit)</span>
                                </div>
                            <?php endif; ?>
                            
                            <div class="reservation-actions">
                                <?php if ($canCancel): ?>
                                    <form method="POST" style="margin: 0;">
                                        <input type="hidden" name="action" value="cancel">
                                        <input type="hidden" name="reservation_id" value="<?= $reservation['RESERVEID'] ?>">
                                        <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to cancel this reservation?')">
                                            <i class="bi bi-x-circle"></i> Cancel Reservation
                                        </button>
                                    </form>
                                <?php else: ?>
                                    <button class="btn btn-secondary" disabled>
                                        <i class="bi bi-x-circle"></i> Cancellation Expired
                                    </button>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Payment Proof Modal -->
    <div id="paymentProofModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Payment Proof</h5>
                <button class="modal-close" onclick="closePaymentProofModal()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <!-- Content will be dynamically loaded here -->
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        // Initialize AOS
        AOS.init({
            duration: 800,
            easing: 'ease-in-out',
            once: true
        });

        // Mobile menu toggle
        function toggleMenu() {
            document.getElementById('navLinks').classList.toggle('active');
        }
        
        // Update countdown timers every minute
        setInterval(function() {
            location.reload();
        }, 60000);
        
        // Enhanced view payment proof function
        function viewPaymentProof(filename, type) {
            const modal = document.getElementById('paymentProofModal');
            const modalBody = document.getElementById('modalBody');
            
            if (type === 'image') {
                modalBody.innerHTML = `
                    <img src="uploads/payment/${filename}" alt="Payment Proof" onclick="this.classList.toggle('fullscreen')">
                `;
            } else {
                modalBody.innerHTML = `
                    <div style="text-align: center; padding: 3rem; background: white;">
                        <i class="bi bi-file-earmark-pdf" style="font-size: 5rem; color: #ef4444; margin-bottom: 1.5rem;"></i>
                        <h3 style="color: var(--dark); margin-bottom: 1rem;">PDF Document</h3>
                        <p style="color: var(--gray); margin-bottom: 2rem; max-width: 400px; margin-left: auto; margin-right: auto;">
                            This is a PDF file. You can download it or open it in a new tab to view the full document.
                        </p>
                        <div style="display: flex; gap: 1rem; justify-content: center; flex-wrap: wrap;">
                            <a href="uploads/payment/${filename}" class="btn btn-success" download>
                                <i class="bi bi-download"></i> Download PDF
                            </a>
                            <a href="uploads/payment/${filename}" class="btn btn-info" target="_blank">
                                <i class="bi bi-box-arrow-up-right"></i> Open in New Tab
                            </a>
                        </div>
                    </div>
                `;
            }
            
            modal.classList.add('show');
            document.body.style.overflow = 'hidden'; // Prevent background scrolling
        }
        
        // Enhanced close modal function
        function closePaymentProofModal() {
            document.getElementById('paymentProofModal').classList.remove('show');
            document.body.style.overflow = ''; // Restore scrolling
        }
        
        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('paymentProofModal');
            if (event.target === modal) {
                closePaymentProofModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closePaymentProofModal();
            }
        });
    </script>
</body>
</html>