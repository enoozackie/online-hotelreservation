<?php
// =============================================================================
// booking.php - Fixed Booking Page with Amenity Display
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
use Lourdian\MonbelaHotel\Core\Database;

// Restrict Access
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'guest') {
    header("Location: login.php");
    exit;
}

// Initialize database connection FIRST
 $database = new Database();
 $conn = $database->getConnection();

// Initialize models with database connection
 $guest = new Guest($conn);
 $room = new Room($conn);
 $booking = new Booking($conn);
 $errors = [];
 $success = false;

// Initialize booking cart
if (!isset($_SESSION['booking_cart'])) {
    $_SESSION['booking_cart'] = [];
}

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

// Fetch accommodations for filter dropdown
try {
    $allAccommodations = $room->getAllAccommodations();
} catch (Exception $e) {
    error_log("Error fetching accommodations: " . $e->getMessage());
    $allAccommodations = [];
}

// Initialize available rooms
 $availableRooms = [];

// Get filter parameters
 $accomId = isset($_GET['accom_id']) && $_GET['accom_id'] !== '' ? (int)$_GET['accom_id'] : null;
 $checkinFilter = $_GET['checkin'] ?? '';
 $checkoutFilter = $_GET['checkout'] ?? '';

// Handle filter submission
if (isset($_GET['filter']) || ($checkinFilter && $checkoutFilter)) {
    // Validate dates
    if ($checkinFilter && $checkoutFilter) {
        $checkinDate = new DateTime($checkinFilter);
        $checkoutDate = new DateTime($checkoutFilter);
        $today = new DateTime('today midnight');

        if ($checkinDate < $today) {
            $errors['checkin'] = 'Check-in date cannot be in past';
        }
        if ($checkoutDate <= $checkinDate) {
            $errors['checkout'] = 'Check-out date must be after check-in date';
        }
    }

    // If no errors, fetch available rooms with filters
    if (empty($errors)) {
        try {
            // Use the getAvailableRooms method from Room model with filters
            if (method_exists($room, 'getAvailableRoomsWithFilters')) {
                $availableRooms = $room->getAvailableRoomsWithFilters($accomId, $checkinFilter, $checkoutFilter);
            } else {
                // Fallback to filtering manually
                $allRooms = $accomId ? $room->getRoomsByAccommodation($accomId) : $room->getAvailableRooms();
                
                // Filter by availability if dates provided
                if ($checkinFilter && $checkoutFilter) {
                    $availableRooms = [];
                    foreach ($allRooms as $roomItem) {
                        if ($booking->isRoomAvailable($roomItem['ROOMID'], $checkinFilter, $checkoutFilter)) {
                            $availableRooms[] = $roomItem;
                        }
                    }
                } else {
                    $availableRooms = $allRooms;
                }
            }
        } catch (Exception $e) {
            error_log("Error fetching filtered rooms: " . $e->getMessage());
            $availableRooms = $room->getAvailableRooms();
        }
    } else {
        // If validation errors, still show rooms but with filter errors
        $availableRooms = $room->getAvailableRooms();
    }
} else {
    // Get all available rooms if no filter
    $availableRooms = $room->getAvailableRooms();
}

// Helper function to get room image path
function getRoomImagePath($roomImage) {
    if (empty($roomImage)) {
        return 'https://picsum.photos/seed/room' . rand(100, 999) . '/800/600.jpg';
    }
    
    $possiblePaths = [
        '../uploads/rooms/' . $roomImage,
        '../images/images/' . $roomImage,
        '../images/' . $roomImage,
        'uploads/rooms/' . $roomImage,
        'images/' . $roomImage
    ];
    
    foreach ($possiblePaths as $path) {
        if (file_exists(__DIR__ . '/' . $path)) {
            return $path;
        }
    }
    
    return $possiblePaths[0];
}

// Helper function to get amenities display names with icons
function getAmenitiesDisplayNames($amenitiesArray, $roomModel) {
    if (empty($amenitiesArray) || !is_array($amenitiesArray)) {
        return [];
    }
    
    $displayNames = [];
    foreach ($amenitiesArray as $amenity) {
        $displayNames[] = [
            'name' => $roomModel->getAmenityDisplayName($amenity),
            'icon' => getAmenityIcon($amenity)
        ];
    }
    return $displayNames;
}

// Helper function to get appropriate icon for an amenity
function getAmenityIcon($amenity) {
    $icons = [
        'wifi' => 'fa-wifi',
        'WiFi' => 'fa-wifi',
        'ac' => 'fa-snowflake',
        'Air Conditioning' => 'fa-snowflake',
        'tv' => 'fa-tv',
        'Smart TV' => 'fa-tv',
        'balcony' => 'fa-umbrella-beach',
        'Balcony' => 'fa-umbrella-beach',
        'bathtub' => 'fa-bath',
        'Bathtub' => 'fa-bath',
        'coffee' => 'fa-coffee',
        'Coffee Maker' => 'fa-coffee',
        'minibar' => 'fa-glass-martini-alt',
        'Mini Bar' => 'fa-glass-martini-alt',
        'safe' => 'fa-lock',
        'In-room Safe' => 'fa-lock',
        'workspace' => 'fa-briefcase',
        'Work Desk' => 'fa-briefcase',
        'roomservice' => 'fa-concierge-bell',
        'Room Service' => 'fa-concierge-bell',
        'oceanview' => 'fa-water',
        'Ocean View' => 'fa-water',
        'cityview' => 'fa-city',
        'City View' => 'fa-city'
    ];
    
    foreach ($icons as $key => $icon) {
        if (stripos($amenity, $key) !== false) {
            return $icon;
        }
    }
    
    return 'fa-check'; // Default icon
}

// Process form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action']) && $_POST['action'] === 'add_to_cart') {
        $data = [
            'GUESTID' => $_SESSION['id'],
            'ROOMID' => $_POST['ROOMID'] ?? '',
            'CHECKIN' => $_POST['CHECKIN'] ?? '',
            'CHECKOUT' => $_POST['CHECKOUT'] ?? '',
            'NUMPERSON' => $_POST['NUMPERSON'] ?? '',
            'SPECIALREQUESTS' => $_POST['SPECIALREQUESTS'] ?? '',
            'BOOKING_REASON' => $_POST['BOOKING_REASON'] ?? '',
            'STATUS' => '',
            'BOOKINGDATE' => date('Y-m-d H:i:s')
        ];

        // Validation
        if (empty($data['ROOMID'])) $errors['ROOMID'] = 'Please select a room';
        if (empty($data['CHECKIN'])) $errors['CHECKIN'] = 'Check-in date is required';
        if (empty($data['CHECKOUT'])) $errors['CHECKOUT'] = 'Check-out date is required';
        if (empty($data['NUMPERSON'])) $errors['NUMPERSON'] = 'Number of guests is required';
        if (empty($data['BOOKING_REASON'])) $errors['BOOKING_REASON'] = 'Booking reason is required';

        // Date validation
        if (!empty($data['CHECKIN']) && !empty($data['CHECKOUT'])) {
            $checkin = new DateTime($data['CHECKIN']);
            $checkout = new DateTime($data['CHECKOUT']);
            $today = new DateTime('today midnight');

            if ($checkin < $today) {
                $errors['CHECKIN'] = 'Check-in date cannot be in past';
            }
            if ($checkout <= $checkin) {
                $errors['CHECKOUT'] = 'Check-out date must be after check-in date';
            }
        }

        // Room capacity validation
        if (!empty($data['ROOMID'])) {
            try {
                $roomDetails = $room->getById($data['ROOMID']);
                if ($roomDetails && $data['NUMPERSON'] > $roomDetails['NUMPERSON']) {
                    $errors['NUMPERSON'] = 'Number of guests exceeds room capacity';
                }
                
                // Calculate total price and get amenities
                if ($roomDetails && !empty($data['CHECKIN']) && !empty($data['CHECKOUT'])) {
                    $checkinDate = new DateTime($data['CHECKIN']);
                    $checkoutDate = new DateTime($data['CHECKOUT']);
                    $nights = max(1, (int)ceil(($checkoutDate->getTimestamp() - $checkinDate->getTimestamp()) / (60 * 60 * 24)));
                    $data['TOTAL_PRICE'] = $roomDetails['PRICE'] * $nights;
                    $data['ROOM_NAME'] = $roomDetails['ROOM'];
                    $data['ROOM_NUM'] = $roomDetails['ROOMNUM'];
                    $data['NIGHTS'] = $nights;
                    // Get amenities for cart display
                    $data['AMENITIES'] = $roomDetails['amenities_list'] ?? [];
                }
            } catch (Exception $e) {
                $errors['ROOMID'] = 'Error fetching room details. Please try again.';
                error_log("Error fetching room details: " . $e->getMessage());
            }
        }

        // If no errors, add to cart
        if (empty($errors)) {
            $cartId = uniqid('booking_');
            $_SESSION['booking_cart'][$cartId] = $data;
            $_SESSION['success_message'] = 'Room added to your booking cart successfully!';
            header("Location: booking.php");
            exit;
        }
    }
    elseif (isset($_POST['action']) && $_POST['action'] === 'remove_from_cart') {
        $cartId = $_POST['cart_id'] ?? '';
        if (!empty($cartId) && isset($_SESSION['booking_cart'][$cartId])) {
            unset($_SESSION['booking_cart'][$cartId]);
            $_SESSION['success_message'] = 'Booking removed from cart successfully!';
        }
        header("Location: booking.php");
        exit;
    }
    elseif (isset($_POST['action']) && $_POST['action'] === 'process_cart') {
        if (empty($_SESSION['booking_cart'])) {
            $_SESSION['error_message'] = 'Your cart is empty!';
            header("Location: booking.php");
            exit;
        }
        
        $processedBookings = 0;
        $failedBookings = 0;
        $errorMessages = [];
        
        foreach ($_SESSION['booking_cart'] as $cartId => $bookingData) {
            // Ensure all required fields exist and have proper types
            $dataForModel = [
                'GUESTID' => (int)($_SESSION['id'] ?? 0),
                'ROOMID' => (int)($bookingData['ROOMID'] ?? 0),
                'ARRIVAL' => $bookingData['CHECKIN'] ?? '',
                'DEPARTURE' => $bookingData['CHECKOUT'] ?? '',
                'PRORPOSE' => $bookingData['BOOKING_REASON'] ?? 'Online Booking',
                'REMARKS' => $bookingData['SPECIALREQUESTS'] ?? '',
                'USERID' => (int)($_SESSION['id'] ?? 0)
            ];
            
            error_log("Processing booking for room " . $dataForModel['ROOMID'] . " with data: " . print_r($dataForModel, true));

            try {
                $confirmationCode = $booking->create($dataForModel);
                if ($confirmationCode) {
                    $processedBookings++;
                    error_log("Booking successful with confirmation code: {$confirmationCode}");
                } else {
                    $failedBookings++;
                    $roomName = $bookingData['ROOM_NAME'] ?? 'Unknown Room';
                    $errorMessages[] = "Failed to book {$roomName}";
                    error_log("Booking failed for room ID: " . $dataForModel['ROOMID'] . " - create() returned false");
                }
            } catch (Exception $e) {
                $failedBookings++;
                $roomName = $bookingData['ROOM_NAME'] ?? 'Unknown Room';
                $errorMessages[] = "Error booking {$roomName}: " . $e->getMessage();
                error_log('Booking creation exception for room ID ' . $dataForModel['ROOMID'] . ': ' . $e->getMessage());
                error_log('Exception trace: ' . $e->getTraceAsString());
            }
        }
        
        // Clear cart only if at least one booking succeeded
        if ($processedBookings > 0) {
            $_SESSION['booking_cart'] = [];
        }
        
        if ($failedBookings === 0) {
            $_SESSION['success_message'] = "All {$processedBookings} booking(s) created successfully!";
            header("Location: my_reservations.php");
        } else {
            if ($processedBookings > 0) {
                $_SESSION['error_message'] = "{$processedBookings} booking(s) succeeded. {$failedBookings} failed: " . implode(', ', $errorMessages);
            } else {
                $_SESSION['error_message'] = "All bookings failed: " . implode(', ', $errorMessages);
            }
            // If all failed, stay on booking page so they can try again
            if ($processedBookings === 0) {
                header("Location: booking.php");
            } else {
                header("Location: my_reservations.php");
            }
        }
        exit;
    }
}
    
// Display messages
 $success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);
 $error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);

// Get current filter values
 $currentAccommodation = $accomId ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Book a Room - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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
            --gold: #D4AF37;
            --gold-dark: #B8941F;
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

        .bg-animation span:nth-child(1) { left: 25%; width: 80px; height: 80px; animation-delay: 0s; }
        .bg-animation span:nth-child(2) { left: 10%; width: 20px; height: 20px; animation-delay: 2s; animation-duration: 12s; }
        .bg-animation span:nth-child(3) { left: 70%; width: 20px; height: 20px; animation-delay: 4s; }
        .bg-animation span:nth-child(4) { left: 40%; width: 60px; height: 60px; animation-delay: 0s; animation-duration: 18s; }
        .bg-animation span:nth-child(5) { left: 65%; width: 20px; height: 20px; animation-delay: 0s; }
        .bg-animation span:nth-child(6) { left: 75%; width: 110px; height: 110px; animation-delay: 3s; }
        .bg-animation span:nth-child(7) { left: 35%; width: 150px; height: 150px; animation-delay: 7s; }
        .bg-animation span:nth-child(8) { left: 50%; width: 25px; height: 25px; animation-delay: 15s; animation-duration: 45s; }
        .bg-animation span:nth-child(9) { left: 20%; width: 15px; height: 15px; animation-delay: 2s; animation-duration: 35s; }
        .bg-animation span:nth-child(10) { left: 85%; width: 150px; height: 150px; animation-delay: 0s; animation-duration: 11s; }

        @keyframes move {
            0% { transform: translateY(0) rotate(0deg); opacity: 1; border-radius: 0; }
            100% { transform: translateY(-1000px) rotate(720deg); opacity: 0; border-radius: 50%; }
        }

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

        .brand:hover { transform: scale(1.05); }

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

        .nav-links a:hover { color: var(--primary); }
        .nav-links a:hover::after { width: 100%; }
        .nav-links a.active { color: var(--primary); font-weight: 600; }
        .nav-links a.active::after { width: 100%; }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: var(--primary);
            font-size: 1.5rem;
            cursor: pointer;
        }

        .cart-badge { position: relative; }

        .cart-badge .badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: var(--danger);
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 1.5rem;
        }

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
        }

        .page-subtitle {
            color: var(--gray);
            margin: 0.5rem 0 0;
            font-size: 1.1rem;
        }

        .filter-bar {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-lg);
            display: flex;
            gap: 1.5rem;
            align-items: flex-end;
            flex-wrap: wrap;
        }

        .accommodation-filter, .date-filter {
            min-width: 200px;
            flex-grow: 1;
        }

        .accommodation-filter label, .date-filter label {
            display: block;
            margin-bottom: 0.75rem;
            font-weight: 600;
            color: var(--dark);
            font-size: 1.1rem;
        }

        .accommodation-filter select, .date-filter input {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            background: white;
            transition: all 0.3s ease;
            appearance: none;
        }

        .accommodation-filter select {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%236b7280' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }

        .accommodation-filter select:focus, .date-filter input:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .filter-btn, .clear-filter-btn {
            padding: 0.875rem 1.5rem;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            height: fit-content;
        }

        .filter-btn {
            background: var(--primary);
            color: white;
        }

        .filter-btn:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .clear-filter-btn {
            background: var(--gray);
            color: white;
            text-decoration: none;
        }

        .clear-filter-btn:hover {
            background: #475569;
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .rooms-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 2rem;
            margin-bottom: 3rem;
        }

        .room-card {
            background: white;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: var(--shadow);
            transition: all 0.3s ease;
            position: relative;
        }

        .room-card:hover {
            transform: translateY(-8px);
            box-shadow: var(--shadow-xl);
        }

        .room-card .room-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: rgba(255, 255, 255, 0.9);
            backdrop-filter: blur(10px);
            padding: 0.5rem 1rem;
            border-radius: 50px;
            font-weight: 600;
            color: var(--primary);
            z-index: 2;
        }

        .room-image-container {
            position: relative;
            height: 250px;
            overflow: hidden;
        }

        .room-image {
            width: 100%;
            height: 100%;
            object-fit: cover;
            transition: transform 0.5s ease;
        }

        .room-card:hover .room-image { transform: scale(1.05); }

        .room-content { padding: 1.5rem; }

        .room-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 0.75rem;
        }

        .room-features {
            display: flex;
            gap: 1.5rem;
            margin-bottom: 1rem;
            color: var(--gray);
            font-size: 0.95rem;
        }

        .room-features i {
            margin-right: 0.5rem;
            color: var(--primary);
        }

        /* Enhanced amenities section */
        .room-amenities {
            margin-bottom: 1.5rem;
        }

        .amenities-title {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .amenities-list {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin: 0;
            padding: 0;
            list-style: none;
        }

        .amenity-tag {
            background: #e0f2fe;
            color: #0369a1;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .amenity-tag i {
            font-size: 0.7rem;
        }

        .room-price {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid var(--border);
        }

        .price-amount {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
        }

        .price-period {
            font-size: 0.9rem;
            color: var(--gray);
            display: block;
        }

        .book-now-btn {
            padding: 0.75rem 1.5rem;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border: none;
            border-radius: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .book-now-btn:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .empty-state {
            background: white;
            padding: 4rem 2rem;
            border-radius: 16px;
            text-align: center;
            box-shadow: var(--shadow-lg);
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

        .cart-section {
            background: white;
            border-radius: 16px;
            padding: 2rem;
            box-shadow: var(--shadow-lg);
            margin-bottom: 2rem;
            position: sticky;
            bottom: 20px;
            z-index: 100;
        }

        .cart-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid var(--border);
        }

        .cart-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--dark);
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .cart-title i { color: var(--primary); }

        .cart-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1.25rem;
            border: 1px solid var(--border);
            border-radius: 12px;
            margin-bottom: 1rem;
            transition: all 0.3s ease;
        }

        .cart-item:hover {
            box-shadow: var(--shadow);
            transform: translateY(-2px);
        }

        .cart-item-details h4 {
            margin: 0 0 0.5rem 0;
            color: var(--dark);
            font-size: 1.1rem;
        }

        .cart-item-details p {
            margin: 0.25rem 0;
            color: var(--gray);
            font-size: 0.95rem;
        }

        .cart-item-amenities {
            margin: 0.5rem 0;
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }

        .cart-amenity-tag {
            background: #e0f2fe;
            color: #0369a1;
            padding: 0.1rem 0.5rem;
            border-radius: 12px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .cart-item-price {
            font-weight: 700;
            color: var(--primary);
            font-size: 1.1rem;
        }

        .remove-btn {
            background: var(--danger);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .remove-btn:hover {
            background: #dc2626;
            transform: translateY(-2px);
        }

        .cart-summary {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 2rem;
            padding-top: 1.5rem;
            border-top: 2px solid var(--border);
        }

        .cart-total {
            font-size: 1.25rem;
            font-weight: 700;
            color: var(--dark);
        }

        .cart-total span {
            color: var(--primary);
            font-size: 1.5rem;
        }

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
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .btn-success {
            background: linear-gradient(135deg, var(--success), #0da271);
            color: white;
        }

        .btn-success:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }

        .modal-content {
            border-radius: 16px;
            border: none;
            overflow: hidden;
        }

        .modal-header {
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 1.5rem;
            border: none;
        }

        .modal-title { font-weight: 700; }
        .btn-close { filter: brightness(0) invert(1); }
        .modal-body { padding: 2rem; }

        .room-info {
            background: #f0f9ff;
            padding: 1.5rem;
            border-radius: 12px;
            margin-bottom: 1.5rem;
            border-left: 4px solid var(--primary);
        }

        .room-info h5 {
            margin-bottom: 0.5rem;
            color: var(--dark);
            font-weight: 700;
        }

        .modal-amenities {
            margin-top: 0.5rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .modal-amenity-tag {
            background: #dbeafe;
            color: #1d4ed8;
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
        }

        .modal-amenity-tag i {
            font-size: 0.75rem;
        }

        .form-group { margin-bottom: 1.5rem; }

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--dark);
        }

        .form-control {
            width: 100%;
            padding: 0.875rem 1rem;
            border: 2px solid var(--border);
            border-radius: 12px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
        }

        .error {
            color: var(--danger);
            font-size: 0.875rem;
            margin-top: 0.25rem;
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .price-summary {
            background: #f0f9ff;
            padding: 1.5rem;
            border-radius: 12px;
            margin-top: 1.5rem;
            border-left: 4px solid var(--primary);
        }

        .price-summary h6 {
            margin-bottom: 1rem;
            color: var(--dark);
            font-weight: 700;
        }

        .price-detail {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
        }

        .price-total {
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 1.1rem;
            padding-top: 0.75rem;
            border-top: 1px solid var(--border);
            margin-top: 0.75rem;
        }

        .alert {
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .alert-success {
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        @media (max-width: 992px) {
            .rooms-grid {
                grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            }
        }

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

            .filter-bar {
                flex-direction: column;
                align-items: stretch;
            }

            .accommodation-filter, .date-filter {
                width: 100%;
            }

            .rooms-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 1rem;
            }

            .cart-section {
                position: static;
            }
        }

        .loading {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255,255,255,.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .toast-container {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 1050;
        }

        .toast {
            background: white;
            border-radius: 12px;
            box-shadow: var(--shadow-xl);
            padding: 1rem 1.5rem;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 300px;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateX(100%);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }

        .toast-success {
            border-left: 4px solid var(--success);
        }

        .toast-error {
            border-left: 4px solid var(--danger);
        }

        .toast-info {
            border-left: 4px solid var(--primary);
        }

        /* Filter status */
        .filter-status {
            background: white;
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: var(--shadow);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .filter-tags {
            display: flex;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .filter-tag {
            background: #e0f2fe;
            color: #0369a1;
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .filter-tag .remove-tag {
            background: none;
            border: none;
            color: #0369a1;
            font-size: 1rem;
            cursor: pointer;
            padding: 0;
            display: flex;
            align-items: center;
        }

        .filter-tag .remove-tag:hover {
            color: #dc2626;
        }

        .results-count {
            font-weight: 600;
            color: var(--dark);
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
                <li><a href="booking.php" class="active"><i class="bi bi-calendar-plus"></i> Home</a></li>
                <li><a href="guest_profile.php"><i class="bi bi-person-circle"></i> Profile</a></li>
                <li>
                    <a href="my_reservations.php" class="cart-badge">
                        <i class="bi bi-calendar-check"></i> My Reservations
                        <?php if (!empty($_SESSION['booking_cart'])): ?>
                            <span class="badge"><?= count($_SESSION['booking_cart']) ?></span>
                        <?php endif; ?>
                    </a>
                </li>
                <li><a href="guest_profile.php?logout=1"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header" data-aos="fade-up">
            <div>
                <h1 class="page-title">Book a Room</h1>
                <p class="page-subtitle">Find and book your perfect stay at Monbela Hotel</p>
            </div>
            <a href="my_reservations.php" class="btn btn-primary" data-aos="fade-left" data-aos-delay="200">
                <i class="bi bi-calendar-check"></i> View My Reservations
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

        <!-- Filter Status Bar -->
        <?php if ($accomId || $checkinFilter || $checkoutFilter): ?>
            <div class="filter-status" data-aos="fade-up">
                <div class="filter-tags">
                    <?php if ($accomId): ?>
                        <?php 
                            $accomName = '';
                            foreach ($allAccommodations as $accom) {
                                if ($accom['ACCOMID'] == $accomId) {
                                    $accomName = $accom['ACCOMODATION'];
                                    break;
                                }
                            }
                        ?>
                        <div class="filter-tag">
                            <span>Type: <?= htmlspecialchars($accomName) ?></span>
                            <a href="<?= removeQueryParam('accom_id') ?>" class="remove-tag">
                                <i class="bi bi-x"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($checkinFilter): ?>
                        <div class="filter-tag">
                            <span>Check-in: <?= date('M j, Y', strtotime($checkinFilter)) ?></span>
                            <a href="<?= removeQueryParam('checkin') ?>" class="remove-tag">
                                <i class="bi bi-x"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($checkoutFilter): ?>
                        <div class="filter-tag">
                            <span>Check-out: <?= date('M j, Y', strtotime($checkoutFilter)) ?></span>
                            <a href="<?= removeQueryParam('checkout') ?>" class="remove-tag">
                                <i class="bi bi-x"></i>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="results-count">
                    <?= count($availableRooms) ?> room(s) found
                </div>
            </div>
        <?php endif; ?>

        <!-- Filter Bar -->
        <div class="filter-bar" data-aos="fade-up" data-aos-delay="100">
            <form method="GET" action="booking.php" class="d-flex align-items-center gap-3 flex-wrap w-100">
                <div class="accommodation-filter">
                    <label for="accommodationFilter"><i class="bi bi-funnel"></i> Room Type</label>
                    <select name="accom_id" id="accommodationFilter" class="form-control">
                        <option value="">All Room Types</option>
                        <?php foreach ($allAccommodations as $accommodation): ?>
                            <option value="<?= $accommodation['ACCOMID'] ?>" 
                                <?= ($currentAccommodation == $accommodation['ACCOMID']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($accommodation['ACCOMODATION']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="date-filter">
                    <label for="checkin"><i class="bi bi-calendar-check"></i> Check-in Date</label>
                    <input type="date" name="checkin" id="checkin" class="form-control" 
                           value="<?= htmlspecialchars($checkinFilter) ?>"
                           min="<?= date('Y-m-d') ?>">
                    <?php if (isset($errors['checkin'])): ?>
                        <div class="error"><i class="bi bi-exclamation-circle"></i> <?= $errors['checkin'] ?></div>
                    <?php endif; ?>
                </div>
                
                <div class="date-filter">
                    <label for="checkout"><i class="bi bi-calendar-x"></i> Check-out Date</label>
                    <input type="date" name="checkout" id="checkout" class="form-control" 
                           value="<?= htmlspecialchars($checkoutFilter) ?>"
                           min="<?= $checkinFilter ? date('Y-m-d', strtotime($checkinFilter . ' +1 day')) : date('Y-m-d', strtotime('+1 day')) ?>">
                    <?php if (isset($errors['checkout'])): ?>
                        <div class="error"><i class="bi bi-exclamation-circle"></i> <?= $errors['checkout'] ?></div>
                    <?php endif; ?>
                </div>
                
                <input type="hidden" name="filter" value="1">
                <button type="submit" class="filter-btn">
                    <i class="bi bi-search"></i> Search Rooms
                </button>
                
                <?php if ($accomId || $checkinFilter || $checkoutFilter): ?>
                    <a href="booking.php" class="clear-filter-btn">
                        <i class="bi bi-x-circle"></i> Clear All Filters
                    </a>
                <?php endif; ?>
            </form>
        </div>

        <!-- Available Rooms -->
        <h2 style="margin-bottom: 1.5rem; color: var(--dark);" data-aos="fade-up" data-aos-delay="200">
            Available Rooms <?php if ($checkinFilter && $checkoutFilter): ?>
                (<?= date('M j', strtotime($checkinFilter)) ?> - <?= date('M j, Y', strtotime($checkoutFilter)) ?>)
            <?php endif; ?>
        </h2>
        
        <?php if (empty($availableRooms)): ?>
            <div class="empty-state" data-aos="fade-up">
                <i class="bi bi-house-slash"></i>
                <h3>No rooms available</h3>
                <?php if ($accomId || $checkinFilter || $checkoutFilter): ?>
                    <p>No rooms match your search criteria. Try adjusting your filters.</p>
                    <a href="booking.php" class="btn btn-primary">
                        <i class="bi bi-arrow-clockwise"></i> Show All Rooms
                    </a>
                <?php else: ?>
                    <p>Please check back later or contact us for availability.</p>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="rooms-grid">
                <?php foreach ($availableRooms as $index => $roomItem): ?>
                    <?php
                        // Get amenities for this room
                        $amenities = $roomItem['amenities_list'] ?? [];
                        $displayAmenities = getAmenitiesDisplayNames($amenities, $room);
                    ?>
                    <div class="room-card" data-aos="fade-up" data-aos-delay="<?= $index * 100 ?>">
                        <div class="room-image-container">
                            <img src="<?= getRoomImagePath($roomItem['ROOMIMAGE'] ?? '') ?>" class="room-image" alt="<?= htmlspecialchars($roomItem['ROOM']) ?>">
                            <div class="room-badge">Available</div>
                        </div>
                        <div class="room-content">
                            <h3 class="room-title"><?= htmlspecialchars($roomItem['ROOM']) ?></h3>
                            <div class="room-features">
                                <div><i class="bi bi-person"></i> <?= $roomItem['NUMPERSON'] ?? 2 ?> Guests</div>
                                <div><i class="bi bi-door-closed"></i> Room #<?= $roomItem['ROOMNUM'] ?></div>
                                <?php if ($roomItem['ACCOMODATION'] ?? ''): ?>
                                    <div><i class="bi bi-tag"></i> <?= htmlspecialchars($roomItem['ACCOMODATION']) ?></div>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Amenities Section -->
                            <?php if (!empty($displayAmenities)): ?>
                                <div class="room-amenities">
                                    <div class="amenities-title">
                                        <i class="bi bi-stars"></i> Amenities
                                    </div>
                                    <div class="amenities-list">
                                        <?php foreach ($displayAmenities as $amenity): ?>
                                            <span class="amenity-tag">
                                                <i class="fas <?= $amenity['icon'] ?>"></i> 
                                                <?= htmlspecialchars($amenity['name']) ?>
                                            </span>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                            
                            <p style="color: var(--gray); margin-bottom: 1rem; font-size: 0.95rem;">
                                <?= htmlspecialchars($roomItem['DESCRIPTION'] ?? 'Comfortable room with all amenities') ?>
                            </p>
                            <div class="room-price">
                                <div>
                                    <span class="price-amount">₱<?= number_format($roomItem['PRICE'] ?? 0, 2) ?></span>
                                    <span class="price-period">per night</span>
                                </div>
                                <button type="button" class="book-now-btn" data-bs-toggle="modal" data-bs-target="#bookingModal" 
                                        data-room-id="<?= $roomItem['ROOMID'] ?>"
                                        data-room-name="<?= htmlspecialchars($roomItem['ROOM']) ?>"
                                        data-room-price="<?= $roomItem['PRICE'] ?>"
                                        data-room-capacity="<?= $roomItem['NUMPERSON'] ?>"
                                        data-room-amenities='<?= json_encode($displayAmenities) ?>'>
                                    <i class="bi bi-calendar-plus"></i> Book Now
                                </button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Booking Cart -->
        <?php if (!empty($_SESSION['booking_cart'])): ?>
            <div class="cart-section" data-aos="fade-up">
                <div class="cart-header">
                    <h2 class="cart-title">
                        <i class="bi bi-cart3"></i> Your Booking Cart
                        <span style="font-size: 1rem; color: var(--gray);">(<?= count($_SESSION['booking_cart']) ?> items)</span>
                    </h2>
                </div>
                
                <?php foreach ($_SESSION['booking_cart'] as $cartId => $booking): ?>
                    <?php 
                        $amenities = $booking['AMENITIES'] ?? [];
                        $displayAmenities = getAmenitiesDisplayNames($amenities, $room);
                    ?>
                    <div class="cart-item">
                        <div class="cart-item-details">
                            <h4><?= htmlspecialchars($booking['ROOM_NAME'] ?? 'Room') ?> - Room #<?= htmlspecialchars($booking['ROOM_NUM'] ?? '') ?></h4>
                            <p>
                                <i class="bi bi-calendar-check"></i> Check-in: <?= date('M j, Y', strtotime($booking['CHECKIN'])) ?>
                            </p>
                            <p>
                                <i class="bi bi-calendar-x"></i> Check-out: <?= date('M j, Y', strtotime($booking['CHECKOUT'])) ?>
                            </p>
                            <p>
                                <i class="bi bi-moon"></i> <?= $booking['NIGHTS'] ?? 1 ?> night(s) | 
                                <i class="bi bi-people"></i> <?= $booking['NUMPERSON'] ?> guest(s)
                            </p>
                            
                            <?php if (!empty($displayAmenities)): ?>
                                <div class="cart-item-amenities">
                                    <?php foreach ($displayAmenities as $amenity): ?>
                                        <span class="cart-amenity-tag">
                                            <i class="fas <?= $amenity['icon'] ?>"></i> 
                                            <?= htmlspecialchars($amenity['name']) ?>
                                        </span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <p class="cart-item-price">Total: ₱<?= number_format($booking['TOTAL_PRICE'] ?? 0, 2) ?></p>
                        </div>
                        <form method="POST" style="margin: 0;">
                            <input type="hidden" name="action" value="remove_from_cart">
                            <input type="hidden" name="cart_id" value="<?= $cartId ?>">
                            <button type="submit" class="remove-btn">
                                <i class="bi bi-trash"></i> Remove
                            </button>
                        </form>
                    </div>
                <?php endforeach; ?>
                
                <div class="cart-summary">
                    <div class="cart-total">
                        Total: <span>₱<?= number_format(array_sum(array_column($_SESSION['booking_cart'], 'TOTAL_PRICE')), 2) ?></span>
                    </div>
                    <form method="POST">
                        <input type="hidden" name="action" value="process_cart">
                        <button type="submit" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Confirm All Bookings
                        </button>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Booking Modal -->
    <div class="modal fade" id="bookingModal" tabindex="-1" aria-labelledby="bookingModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bookingModalLabel">Book Room</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" id="bookingForm">
                    <input type="hidden" name="action" value="add_to_cart">
                    <input type="hidden" name="ROOMID" id="modalRoomId">
                    
                    <div class="modal-body">
                        <!-- Room Info -->
                        <div class="room-info">
                            <h5 id="modalRoomName"></h5>
                            <p style="margin: 0; color: var(--gray);">
                                <i class="bi bi-tag"></i> Price: ₱<span id="modalRoomPrice"></span>/night | 
                                <i class="bi bi-people"></i> Capacity: <span id="modalRoomCapacity"></span> guests
                            </p>
                            <!-- Amenities in Modal -->
                            <div id="modalAmenitiesContainer" class="modal-amenities" style="margin-top: 0.5rem;"></div>
                        </div>

                        <!-- Booking Details -->
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="CHECKIN" class="form-label"><i class="bi bi-calendar-check"></i> Check-in Date *</label>
                                    <input type="date" name="CHECKIN" id="CHECKIN" class="form-control" 
                                           value="<?= $checkinFilter ?>" required>
                                    <?php if (isset($errors['CHECKIN'])): ?>
                                        <div class="error"><i class="bi bi-exclamation-circle"></i> <?= $errors['CHECKIN'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="CHECKOUT" class="form-label"><i class="bi bi-calendar-x"></i> Check-out Date *</label>
                                    <input type="date" name="CHECKOUT" id="CHECKOUT" class="form-control" 
                                           value="<?= $checkoutFilter ?>" required>
                                    <?php if (isset($errors['CHECKOUT'])): ?>
                                        <div class="error"><i class="bi bi-exclamation-circle"></i> <?= $errors['CHECKOUT'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="NUMPERSON" class="form-label"><i class="bi bi-people"></i> Number of Guests *</label>
                                    <input type="number" name="NUMPERSON" id="NUMPERSON" class="form-control" min="1" required>
                                    <?php if (isset($errors['NUMPERSON'])): ?>
                                        <div class="error"><i class="bi bi-exclamation-circle"></i> <?= $errors['NUMPERSON'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="BOOKING_REASON" class="form-label"><i class="bi bi-card-text"></i> Booking Reason *</label>
                                    <select name="BOOKING_REASON" id="BOOKING_REASON" class="form-control" required>
                                        <option value="">Select a reason</option>
                                        <option value="Vacation">Vacation</option>
                                        <option value="Business">Business</option>
                                        <option value="Family Visit">Family Visit</option>
                                        <option value="Event">Event</option>
                                        <option value="Other">Other</option>
                                    </select>
                                    <?php if (isset($errors['BOOKING_REASON'])): ?>
                                        <div class="error"><i class="bi bi-exclamation-circle"></i> <?= $errors['BOOKING_REASON'] ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="SPECIALREQUESTS" class="form-label"><i class="bi bi-chat-left-text"></i> Special Requests</label>
                            <textarea name="SPECIALREQUESTS" id="SPECIALREQUESTS" class="form-control" rows="3" placeholder="Any special requests or requirements..."></textarea>
                        </div>

                        <!-- Price Calculation -->
                        <div class="price-summary">
                            <h6><i class="bi bi-calculator"></i> Price Summary</h6>
                            <div id="priceSummary" style="color: var(--gray);">
                                Select dates to see price calculation
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-cart-plus"></i> Add to Booking Cart
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Toast Container -->
    <div class="toast-container" id="toastContainer"></div>

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

        // Toast notification function
        function showToast(message, type = 'info') {
            const toastContainer = document.getElementById('toastContainer');
            const toast = document.createElement('div');
            toast.className = `toast toast-${type}`;
            
            let icon = '';
            switch(type) {
                case 'success':
                    icon = '<i class="bi bi-check-circle-fill"></i>';
                    break;
                case 'error':
                    icon = '<i class="bi bi-exclamation-triangle-fill"></i>';
                    break;
                default:
                    icon = '<i class="bi bi-info-circle-fill"></i>';
            }
            
            toast.innerHTML = `${icon} ${message}`;
            toastContainer.appendChild(toast);
            
            setTimeout(() => {
                toast.style.animation = 'slideIn 0.3s ease-out reverse';
                setTimeout(() => {
                    toastContainer.removeChild(toast);
                }, 300);
            }, 3000);
        }

        // Booking Modal
        const bookingModal = document.getElementById('bookingModal');
        if (bookingModal) {
            bookingModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const roomId = button.getAttribute('data-room-id');
                const roomName = button.getAttribute('data-room-name');
                const roomPrice = button.getAttribute('data-room-price');
                const roomCapacity = button.getAttribute('data-room-capacity');
                const amenitiesJson = button.getAttribute('data-room-amenities');
                
                document.getElementById('modalRoomId').value = roomId;
                document.getElementById('modalRoomName').textContent = roomName;
                document.getElementById('modalRoomPrice').textContent = parseFloat(roomPrice).toFixed(2);
                document.getElementById('modalRoomCapacity').textContent = roomCapacity;
                
                // Display amenities with icons
                const amenitiesContainer = document.getElementById('modalAmenitiesContainer');
                amenitiesContainer.innerHTML = '';
                
                if (amenitiesJson) {
                    try {
                        const amenities = JSON.parse(amenitiesJson);
                        if (Array.isArray(amenities) && amenities.length > 0) {
                            amenities.forEach(amenity => {
                                const tag = document.createElement('span');
                                tag.className = 'modal-amenity-tag';
                                tag.innerHTML = `<i class="fas ${amenity.icon}"></i> ${amenity.name}`;
                                amenitiesContainer.appendChild(tag);
                            });
                        }
                    } catch (e) {
                        console.error('Error parsing amenities:', e);
                    }
                }
                
                // Set min date for check-in to today
                const today = new Date().toISOString().split('T')[0];
                const checkinInput = document.getElementById('CHECKIN');
                checkinInput.min = today;
                
                // If we have filter dates, pre-fill them
                const filterCheckin = '<?= $checkinFilter ?>';
                const filterCheckout = '<?= $checkoutFilter ?>';
                
                if (filterCheckin) {
                    checkinInput.value = filterCheckin;
                }
                
                if (filterCheckout) {
                    document.getElementById('CHECKOUT').value = filterCheckout;
                }
                
                // Reset form if no filter dates
                if (!filterCheckin && !filterCheckout) {
                    checkinInput.value = '';
                    document.getElementById('CHECKOUT').value = '';
                    document.getElementById('priceSummary').innerHTML = 'Select dates to see price calculation';
                } else {
                    calculatePrice();
                }
            });
        }

        // Price calculation
        const checkinInput = document.getElementById('CHECKIN');
        const checkoutInput = document.getElementById('CHECKOUT');

        function calculatePrice() {
            if (checkinInput && checkoutInput && checkinInput.value && checkoutInput.value) {
                const checkin = new Date(checkinInput.value);
                const checkout = new Date(checkoutInput.value);
                const nights = Math.ceil((checkout - checkin) / (1000 * 60 * 60 * 24));
                
                if (nights > 0) {
                    const pricePerNight = parseFloat(document.getElementById('modalRoomPrice').textContent || 0);
                    const total = pricePerNight * nights;
                    document.getElementById('priceSummary').innerHTML = `
                        <div class="price-detail">
                            <span>${nights} night(s) × ₱${pricePerNight.toFixed(2)}</span>
                            <span>₱${(pricePerNight * nights).toFixed(2)}</span>
                        </div>
                        <div class="price-total">
                            <span>Total</span>
                            <span>₱${total.toFixed(2)}</span>
                        </div>
                    `;
                }
            }
        }

        if (checkinInput && checkoutInput) {
            checkinInput.addEventListener('change', calculatePrice);
            checkoutInput.addEventListener('change', calculatePrice);
        }

        // Set min date for checkout based on checkin
        if (checkinInput) {
            checkinInput.addEventListener('change', function() {
                if (this.value) {
                    const nextDay = new Date(this.value);
                    nextDay.setDate(nextDay.getDate() + 1);
                    checkoutInput.min = nextDay.toISOString().split('T')[0];
                    checkoutInput.disabled = false;
                    calculatePrice();
                }
            });
        }

        // Form validation
        document.getElementById('bookingForm')?.addEventListener('submit', function(e) {
            const checkin = document.getElementById('CHECKIN').value;
            const checkout = document.getElementById('CHECKOUT').value;
            const guests = document.getElementById('NUMPERSON').value;
            const roomCapacity = parseInt(document.getElementById('modalRoomCapacity').textContent || 0);

            if (checkin && checkout) {
                const checkinDate = new Date(checkin);
                const checkoutDate = new Date(checkout);
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                if (checkinDate < today) {
                    e.preventDefault();
                    showToast('Check-in date cannot be in past.', 'error');
                    return;
                }
                if (checkoutDate <= checkinDate) {
                    e.preventDefault();
                    showToast('Check-out date must be after check-in date.', 'error');
                    return;
                }
            }

            if (guests && roomCapacity && parseInt(guests) > roomCapacity) {
                e.preventDefault();
                showToast(`Number of guests cannot exceed room capacity of ${roomCapacity}.`, 'error');
                return;
            }
        });

        // Update checkout min date when checkin filter changes
        const filterCheckin = document.querySelector('input[name="checkin"]');
        const filterCheckout = document.querySelector('input[name="checkout"]');
        
        if (filterCheckin) {
            filterCheckin.addEventListener('change', function() {
                if (this.value) {
                    const nextDay = new Date(this.value);
                    nextDay.setDate(nextDay.getDate() + 1);
                    filterCheckout.min = nextDay.toISOString().split('T')[0];
                    filterCheckout.disabled = false;
                }
            });
        }
    </script>
</body>
</html>
<?php

// Helper function to remove query parameters
function removeQueryParam($param) {
    $query = $_GET;
    unset($query[$param]);
    unset($query['filter']);
    return 'booking.php' . (!empty($query) ? '?' . http_build_query($query) : '');
}
?>