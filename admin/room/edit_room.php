<?php
session_start();
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}
require __DIR__ . '/../../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Room;

// Initialize Room model
$roomModel = new Room();
$accommodations = $roomModel->getAllAccommodations();

// Get room ID from URL
$roomId = $_GET['id'] ?? null;
if (!$roomId) {
    $_SESSION['error'] = "Room ID is required.";
    header("Location: manage_rooms.php");
    exit;
}

// Get existing room data
$room = $roomModel->getRoomById($roomId);
if (!$room) {
    $_SESSION['error'] = "Room not found.";
    header("Location: manage_rooms.php");
    exit;
}

// Variables for errors and success
$errors = [];
$success = '';

// Define the upload directory path
$uploadDir = __DIR__ . '/../../uploads/rooms/';
$webPath = '../../uploads/rooms/';

// Create directory if it doesn't exist
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Predefined amenities list
$availableAmenities = [
    'wifi' => 'Free WiFi',
    'ac' => 'Air Conditioning',
    'tv' => 'Smart TV',
    'minibar' => 'Mini Bar',
    'safe' => 'In-room Safe',
    'balcony' => 'Balcony',
    'bathtub' => 'Bathtub',
    'workspace' => 'Work Desk',
    'coffee' => 'Coffee Maker',
    'roomservice' => '24/7 Room Service',
    'oceanview' => 'Ocean View',
    'cityview' => 'City View'
];

// Parse existing amenities if stored as JSON
$currentAmenities = [];
if (isset($room['amenities'])) {
    if (is_string($room['amenities'])) {
        $currentAmenities = json_decode($room['amenities'], true) ?: [];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $ROOMNUM = trim($_POST['ROOMNUM'] ?? '');
    $ROOM = trim($_POST['ROOM'] ?? '');
    $ROOMDESC = trim($_POST['ROOMDESC'] ?? '');
    $NUMPERSON = trim($_POST['NUMPERSON'] ?? '');
    $PRICE = trim($_POST['PRICE'] ?? '');
    $ACCOMID = trim($_POST['ACCOMID'] ?? '');
    $OROOMNUM = trim($_POST['OROOMNUM'] ?? '');
    $ROOM_STATUS = trim($_POST['ROOM_STATUS'] ?? 'Available');
    $amenities = $_POST['amenities'] ?? [];
    
    // --- Validation ---
    if (empty($ROOMNUM)) $errors['ROOMNUM'] = "Room number is required.";
    else if ($ROOMNUM !== $room['ROOMNUM'] && $roomModel->isRoomNumberExists($ROOMNUM)) {
        $errors['ROOMNUM'] = "Room number already exists.";
    }
    
    if (empty($ROOM)) $errors['ROOM'] = "Room name is required.";
    if (empty($NUMPERSON) || !is_numeric($NUMPERSON) || $NUMPERSON < 1) {
        $errors['NUMPERSON'] = "Capacity must be a positive number.";
    }
    if (empty($PRICE) || !is_numeric($PRICE) || $PRICE < 0) {
        $errors['PRICE'] = "Price must be a valid positive number.";
    }
    if (empty($ACCOMID)) $errors['ACCOMID'] = "Accommodation type is required.";
    if (empty($OROOMNUM) || !is_numeric($OROOMNUM) || $OROOMNUM < 0) {
        $errors['OROOMNUM'] = "Available rooms must be a non-negative number.";
    }
    
    // Validate ACCOMID exists
    if (!empty($ACCOMID)) {
        $accomExists = false;
        foreach ($accommodations as $accom) {
            if ($accom['ACCOMID'] == $ACCOMID) {
                $accomExists = true;
                break;
            }
        }
        if (!$accomExists) {
            $errors['ACCOMID'] = "Selected accommodation type does not exist.";
        }
    }
    
    // --- Handle multiple file uploads ---
    $uploadedImages = [];
    $existingImages = [];
    
    // Get existing images if any
    if (!empty($room['ROOMIMAGE'])) {
        $existingImages[] = $room['ROOMIMAGE'];
    }
    if (isset($room['additional_images'])) {
        $additional = json_decode($room['additional_images'], true) ?: [];
        $existingImages = array_merge($existingImages, $additional);
    }
    
    if (isset($_FILES['ROOMIMAGES']) && !empty($_FILES['ROOMIMAGES']['name'][0])) {
        $fileCount = count($_FILES['ROOMIMAGES']['name']);
        
        for ($i = 0; $i < $fileCount; $i++) {
            if ($_FILES['ROOMIMAGES']['error'][$i] === UPLOAD_ERR_OK) {
                $fileTmpPath = $_FILES['ROOMIMAGES']['tmp_name'][$i];
                $fileName = basename($_FILES['ROOMIMAGES']['name'][$i]);
                $fileSize = $_FILES['ROOMIMAGES']['size'][$i];
                $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
                $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
                
                if (!in_array($fileExtension, $allowedExtensions)) {
                    $errors['ROOMIMAGES'] = "Invalid image format in one or more files.";
                    break;
                } elseif ($fileSize > 5 * 1024 * 1024) { // 5MB
                    $errors['ROOMIMAGES'] = "One or more files exceed 5MB limit.";
                    break;
                } else {
                    $newFileName = uniqid('room_', true) . '.' . $fileExtension;
                    $dest_path = $uploadDir . $newFileName;
                    
                    if (move_uploaded_file($fileTmpPath, $dest_path)) {
                        $uploadedImages[] = $newFileName;
                    } else {
                        $errors['ROOMIMAGES'] = "Error uploading one or more files.";
                        break;
                    }
                }
            }
        }
    }
    
    // --- Update database if no errors ---
    if (empty($errors)) {
        try {
            // Prepare amenities JSON
            $amenitiesJson = !empty($amenities) ? json_encode($amenities) : null;
            
            // Handle images - keep existing if no new ones uploaded
            if (!empty($uploadedImages)) {
                // Delete old images if replacing
                if (isset($_POST['replace_images']) && $_POST['replace_images'] === '1') {
                    foreach ($existingImages as $oldImage) {
                        $oldPath = $uploadDir . $oldImage;
                        if (file_exists($oldPath)) {
                            @unlink($oldPath);
                        }
                    }
                    $primaryImage = $uploadedImages[0];
                    $additionalImages = count($uploadedImages) > 1 ? json_encode(array_slice($uploadedImages, 1)) : null;
                } else {
                    // Add to existing images
                    $allImages = array_merge($existingImages, $uploadedImages);
                    $primaryImage = $allImages[0];
                    $additionalImages = count($allImages) > 1 ? json_encode(array_slice($allImages, 1)) : null;
                }
            } else {
                // Keep existing images
                $primaryImage = $room['ROOMIMAGE'];
                $additionalImages = $room['additional_images'] ?? null;
            }
            
            $roomData = [
                'ROOMNUM' => $ROOMNUM,
                'ROOM' => $ROOM,
                'ACCOMID' => $ACCOMID,
                'ROOMDESC' => $ROOMDESC,
                'NUMPERSON' => $NUMPERSON,
                'PRICE' => $PRICE,
                'ROOMIMAGE' => $primaryImage,
                'OROOMNUM' => $OROOMNUM,
                'ROOM_STATUS' => $ROOM_STATUS
            ];
            
            $roomModel->updateRoom($roomId, $roomData);
            
            $success = "Room updated successfully!";
            
            // Refresh room data
            $room = $roomModel->getRoomById($roomId);
            
        } catch (Exception $e) {
            // Clean up uploaded files on error
            foreach ($uploadedImages as $image) {
                @unlink($uploadDir . $image);
            }
            $errors['db_error'] = "Error updating room: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Room - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* =============================================================================
           ENHANCED: Professional Hotel Admin Theme for Edit Room
           ============================================================================= */
        :root {
            /* Enhanced Color System */
            --primary-dark: #0f172a;
            --primary-main: #1e293b;
            --primary-light: #334155;
            --secondary-main: #f59e0b;
            --secondary-dark: #d97706;
            --secondary-light: #fbbf24;
            --accent: #8b5cf6;
            --accent-light: #a78bfa;
            --success: #10b981;
            --success-light: #34d399;
            --danger: #ef4444;
            --danger-light: #f87171;
            --warning: #f59e0b;
            --info: #3b82f6;
            --neutral-100: #f1f5f9;
            --neutral-200: #e2e8f0;
            --neutral-300: #cbd5e1;
            --neutral-400: #94a3b8;
            --neutral-500: #64748b;
            --neutral-600: #475569;
            --neutral-700: #334155;
            --neutral-800: #1e293b;
            --neutral-900: #0f172a;
            --white: #ffffff;
            --gradient-primary: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-main) 100%);
            --gradient-secondary: linear-gradient(135deg, var(--secondary-dark) 0%, var(--secondary-main) 100%);
            --gradient-card: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            
            /* Shadows */
            --shadow-sm: 0 1px 3px 0 rgba(0, 0, 0, 0.1), 0 1px 2px 0 rgba(0, 0, 0, 0.06);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            
            /* Layout Variables */
            --sidebar-width: 280px;
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --radius-xl: 1rem;
            --transition-base: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            --transition-fast: all 0.15s cubic-bezier(0.4, 0, 0.2, 1);
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #f8fafc 0%, #e0e7ff 100%);
            min-height: 100vh;
            color: var(--neutral-700);
        }

        /* Enhanced Sidebar */
        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--gradient-primary);
            color: var(--white);
            overflow-y: auto;
            z-index: 1000;
            box-shadow: var(--shadow-xl);
            transition: var(--transition-base);
        }

        .sidebar-header {
            padding: 2rem;
            background: rgba(255, 255, 255, 0.05);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .sidebar-header h5 {
            font-weight: 700;
            font-size: 1.5rem;
            letter-spacing: -0.02em;
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .sidebar-header h5 i {
            font-size: 1.75rem;
            color: var(--secondary-main);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.7);
            padding: 0.875rem 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
            transition: var(--transition-fast);
            border-left: 4px solid transparent;
            position: relative;
            overflow: hidden;
        }

        .sidebar .nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 0;
            height: 100%;
            background: rgba(255, 255, 255, 0.1);
            transition: var(--transition-base);
        }

        .sidebar .nav-link:hover::before,
        .sidebar .nav-link.active::before {
            width: 100%;
        }

        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: var(--white);
            border-left-color: var(--secondary-main);
            background: rgba(255, 255, 255, 0.05);
        }

        .nav-section {
            margin-top: 1.5rem;
            padding-top: 1.5rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        .nav-section-title {
            padding: 0.5rem 2rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            color: rgba(255, 255, 255, 0.5);
            font-weight: 600;
        }

        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
        }

        /* Enhanced Page Header */
        .page-header {
            background: var(--white);
            border-radius: var(--radius-xl);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-md);
            border: 1px solid var(--neutral-200);
        }

        /* Enhanced Cards */
        .card-premium {
            background: var(--white);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            transition: var(--transition-base);
        }

        .card-premium:hover {
            box-shadow: var(--shadow-xl);
            transform: translateY(-2px);
        }

        /* Enhanced Form Sections */
        .form-section {
            background: linear-gradient(135deg, var(--neutral-100) 0%, #ffffff 100%);
            border: 1px solid var(--neutral-200);
            border-radius: var(--radius-lg);
            padding: 2rem;
            margin-bottom: 2rem;
            position: relative;
            overflow: hidden;
        }

        .form-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 4px;
            height: 100%;
            background: var(--gradient-secondary);
        }

        .form-section h6 {
            color: var(--primary-dark);
            font-weight: 700;
            margin-bottom: 1.5rem;
            font-size: 1.1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-section h6 i {
            color: var(--secondary-main);
        }

        /* Enhanced Form Controls */
        .form-control-premium,
        .form-select {
            border: 2px solid var(--neutral-200);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            transition: var(--transition-base);
            font-size: 0.95rem;
        }

        .form-control-premium:focus,
        .form-select:focus {
            border-color: var(--secondary-main);
            box-shadow: 0 0 0 3px rgba(245, 158, 11, 0.2);
            transform: translateY(-1px);
        }

        .form-label-premium {
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }

        .required-field::after {
            content: " *";
            color: var(--danger);
        }

        /* Amenities Checkboxes */
        .amenities-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .amenity-item {
            background: var(--white);
            border: 2px solid var(--neutral-200);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            transition: var(--transition-fast);
            cursor: pointer;
        }

        .amenity-item:hover {
            border-color: var(--secondary-light);
            background: var(--neutral-100);
        }

        .amenity-item input[type="checkbox"] {
            margin-right: 0.5rem;
        }

        .amenity-item input[type="checkbox"]:checked + label {
            color: var(--secondary-main);
            font-weight: 600;
        }

        .amenity-item:has(input[type="checkbox"]:checked) {
            border-color: var(--secondary-main);
            background: rgba(245, 158, 11, 0.1);
        }

        /* Enhanced Image Upload */
        .current-images-section {
            background: var(--neutral-100);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .current-images-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1rem;
        }

        .current-image-item {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: var(--white);
            box-shadow: var(--shadow-md);
        }

        .current-image-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .current-image-badge {
            position: absolute;
            bottom: 0.5rem;
            left: 0.5rem;
            background: var(--secondary-main);
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
        }

        .image-upload-container {
            border: 3px dashed var(--neutral-300);
            border-radius: var(--radius-lg);
            padding: 2rem;
            text-align: center;
            background: var(--neutral-100);
            transition: var(--transition-base);
            cursor: pointer;
            position: relative;
            overflow: hidden;
        }

        .image-upload-container:hover {
            border-color: var(--secondary-main);
            background: rgba(245, 158, 11, 0.05);
        }

        .image-upload-container.dragover {
            border-color: var(--secondary-main);
            background: rgba(245, 158, 11, 0.1);
            transform: scale(1.02);
        }

        .upload-icon {
            font-size: 3rem;
            color: var(--neutral-400);
            margin-bottom: 1rem;
        }

        .upload-text {
            color: var(--neutral-600);
            font-weight: 600;
            margin-bottom: 0.5rem;
        }

        .upload-hint {
            color: var(--neutral-500);
            font-size: 0.875rem;
        }

        /* Image Preview Grid */
        .image-preview-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 1rem;
            margin-top: 1.5rem;
        }

        .image-preview-item {
            position: relative;
            border-radius: var(--radius-lg);
            overflow: hidden;
            background: var(--neutral-100);
            box-shadow: var(--shadow-md);
            transition: var(--transition-base);
        }

        .image-preview-item:hover {
            transform: scale(1.05);
            box-shadow: var(--shadow-lg);
        }

        .image-preview-item img {
            width: 100%;
            height: 150px;
            object-fit: cover;
        }

        .image-preview-item .remove-btn {
            position: absolute;
            top: 0.5rem;
            right: 0.5rem;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            opacity: 0;
            transition: var(--transition-fast);
        }

        .image-preview-item:hover .remove-btn {
            opacity: 1;
        }

        .image-preview-item .primary-badge {
            position: absolute;
            bottom: 0.5rem;
            left: 0.5rem;
            background: var(--secondary-main);
            color: white;
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
            border-radius: var(--radius-sm);
            font-weight: 600;
        }

        /* Enhanced Buttons */
        .btn-premium {
            font-weight: 600;
            border: none;
            border-radius: var(--radius-md);
            padding: 0.75rem 1.5rem;
            transition: var(--transition-fast);
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            position: relative;
            overflow: hidden;
            box-shadow: var(--shadow-md);
        }

        .btn-primary-premium {
            background: var(--gradient-secondary);
            color: var(--white);
        }

        .btn-primary-premium:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-xl);
            color: var(--white);
        }

        /* Enhanced Alerts */
        .alert-premium {
            border: none;
            border-radius: var(--radius-lg);
            padding: 1rem 1.5rem;
            box-shadow: var(--shadow-md);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.3s ease-out;
        }

        @keyframes slideIn {
            from {
                transform: translateY(-20px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        .alert-success-premium {
            background: linear-gradient(135deg, var(--success) 0%, var(--success-light) 100%);
            color: var(--white);
        }

        .alert-danger-premium {
            background: linear-gradient(135deg, var(--danger) 0%, var(--danger-light) 100%);
            color: var(--white);
        }

        /* Progress Steps */
        .form-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 2rem;
            position: relative;
        }

        .form-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--neutral-200);
            z-index: -1;
        }

        .step {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 0.5rem;
            position: relative;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: var(--white);
            border: 3px solid var(--neutral-300);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--neutral-500);
            transition: var(--transition-base);
        }

        .step.active .step-circle {
            border-color: var(--secondary-main);
            color: var(--secondary-main);
            background: rgba(245, 158, 11, 0.1);
        }

        .step.completed .step-circle {
            background: var(--success);
            border-color: var(--success);
            color: var(--white);
        }

        .step-label {
            font-size: 0.875rem;
            color: var(--neutral-500);
            font-weight: 600;
        }

        .step.active .step-label {
            color: var(--secondary-main);
        }

        /* Room Preview Card */
        .room-preview {
            background: var(--white);
            border: 2px solid var(--neutral-200);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-top: 1rem;
        }

        .room-preview h6 {
            color: var(--primary-dark);
            font-weight: 700;
            margin-bottom: 1rem;
        }

        .preview-item {
            display: flex;
            justify-content: space-between;
            padding: 0.5rem 0;
            border-bottom: 1px solid var(--neutral-100);
        }

        .preview-item:last-child {
            border-bottom: none;
        }

        .preview-label {
            color: var(--neutral-600);
            font-weight: 500;
        }

        .preview-value {
            color: var(--primary-dark);
            font-weight: 600;
        }

        /* Status Badge */
        .status-select {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }

        .status-option {
            flex: 1;
            padding: 0.75rem;
            border: 2px solid var(--neutral-200);
            border-radius: var(--radius-md);
            text-align: center;
            cursor: pointer;
            transition: var(--transition-fast);
        }

        .status-option:hover {
            border-color: var(--secondary-light);
        }

        .status-option input[type="radio"] {
            display: none;
        }

        .status-option input[type="radio"]:checked + .status-label {
            color: var(--white);
        }

        .status-option input[type="radio"]:checked ~ .status-icon {
            color: var(--white);
        }

        .status-option:has(input[type="radio"]:checked) {
            border-color: var(--secondary-main);
            background: var(--gradient-secondary);
            color: var(--white);
        }

        .status-icon {
            font-size: 1.5rem;
            margin-bottom: 0.25rem;
            display: block;
        }

        .status-label {
            font-weight: 600;
            font-size: 0.875rem;
        }

        /* Image Replace Options */
        .image-options {
            margin-bottom: 1rem;
        }

        .image-option {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            
            .main-content {
                margin-left: 0;
                padding: 1rem;
            }
            
            .amenities-grid {
                grid-template-columns: 1fr;
            }
            
            .form-steps {
                display: none;
            }
        }

        /* Loading Spinner */
        .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 0.3rem;
        }

        /* Tooltips */
        .tooltip-icon {
            color: var(--neutral-500);
            cursor: help;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <!-- Enhanced Sidebar -->
    <aside class="sidebar">
        <div class="sidebar-header">
            <h5 class="mb-0"><i class="bi bi-building-fill"></i> Monbela Hotel</h5>
            <small>Admin Dashboard</small>
        </div>
        <nav class="nav flex-column mt-3">
            <a class="nav-link" href="../admin_dashboard.php">
                <i class="bi bi-speedometer2"></i> Dashboard
            </a>
            <div class="nav-section">
                <div class="nav-section-title">Guest Management</div>
                <a class="nav-link" href="../Guest/Manage_guest.php">
                    <i class="bi bi-people-fill"></i> Manage Guests
                </a>
                <a class="nav-link" href="../Guest/Add_guest.php">
                    <i class="bi bi-person-plus-fill"></i> Add Guest
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Room Management</div>
                <a class="nav-link active" href="manage_rooms.php">
                    <i class="bi bi-door-closed-fill"></i> Manage Rooms
                </a>
                <a class="nav-link" href="add_room.php">
                    <i class="bi bi-plus-square-fill"></i> Add Room
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">Reservations</div>
                <a class="nav-link" href="../reservations/manage_reservations.php">
                    <i class="bi bi-calendar-check"></i> Manage Reservations
                </a>
            </div>
            <div class="nav-section">
                <div class="nav-section-title">System</div>
                <a class="nav-link" href="../manage_admins.php">
                    <i class="bi bi-shield-lock-fill"></i> Manage Admins
                </a>
                <a class="nav-link" href="../../public/admin_logout.php">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </nav>
    </aside>

    <!-- Enhanced Main Content -->
    <main class="main-content">
        <div class="page-header">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h2><i class="bi bi-pencil-square me-2"></i>Edit Room</h2>
                    <p class="mb-0 text-muted">Update room details for: <strong><?= htmlspecialchars($room['ROOMNUM']) ?></strong></p>
                </div>
                <a href="manage_rooms.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Rooms
                </a>
            </div>
        </div>

        <!-- Form Progress Steps -->
        <div class="form-steps mb-4">
            <div class="step completed">
                <div class="step-circle"><i class="bi bi-check"></i></div>
                <div class="step-label">Basic Info</div>
            </div>
            <div class="step active">
                <div class="step-circle">2</div>
                <div class="step-label">Pricing</div>
            </div>
            <div class="step">
                <div class="step-circle">3</div>
                <div class="step-label">Amenities</div>
            </div>
            <div class="step">
                <div class="step-circle">4</div>
                <div class="step-label">Images</div>
            </div>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger-premium alert-dismissible fade show" role="alert">
                <i class="bi bi-exclamation-circle-fill"></i>
                <strong>Please correct the following errors:</strong>
                <ul class="mb-0 mt-2">
                    <?php foreach ($errors as $error): ?>
                        <li><?= htmlspecialchars($error) ?></li>
                    <?php endforeach; ?>
                </ul>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success-premium alert-dismissible fade show" role="alert">
                <i class="bi bi-check-circle-fill"></i>
                <strong>Success!</strong> <?= htmlspecialchars($success) ?>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="" method="POST" enctype="multipart/form-data" id="editRoomForm" novalidate>
            <div class="card-premium">
                <div class="card-body p-0">
                    <!-- Step 1: Basic Information -->
                    <div class="form-section">
                        <h6><i class="bi bi-info-circle-fill"></i> Basic Information</h6>
                        <div class="row">
                            <div class="col-md-4 mb-3">
                                <label for="ROOMNUM" class="form-label-premium required-field">
                                    Room Number
                                    <i class="bi bi-question-circle tooltip-icon" data-bs-toggle="tooltip" data-bs-placement="top" title="Unique identifier for the room"></i>
                                </label>
                                <input type="text" 
                                       class="form-control-premium <?= isset($errors['ROOMNUM']) ? 'is-invalid' : '' ?>" 
                                       id="ROOMNUM" 
                                       name="ROOMNUM" 
                                       value="<?= htmlspecialchars($room['ROOMNUM'] ?? '') ?>" 
                                       placeholder="e.g., 101, A-205" 
                                       required>
                                <div class="invalid-feedback"><?= $errors['ROOMNUM'] ?? '' ?></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ROOM" class="form-label-premium required-field">Room Type/Name</label>
                                <input type="text" 
                                       class="form-control-premium <?= isset($errors['ROOM']) ? 'is-invalid' : '' ?>" 
                                       id="ROOM" 
                                       name="ROOM" 
                                       value="<?= htmlspecialchars($room['ROOM'] ?? '') ?>" 
                                       placeholder="e.g., Deluxe Suite" 
                                       required>
                                <div class="invalid-feedback"><?= $errors['ROOM'] ?? '' ?></div>
                            </div>
                            <div class="col-md-4 mb-3">
                                <label for="ACCOMID" class="form-label-premium required-field">Accommodation Category</label>
                                <select class="form-select <?= isset($errors['ACCOMID']) ? 'is-invalid' : '' ?>" 
                                        id="ACCOMID" 
                                        name="ACCOMID" 
                                        required>
                                    <option value="">-- Select Category --</option>
                                    <?php foreach ($accommodations as $accom): ?>
                                        <option value="<?= $accom['ACCOMID'] ?>"
                                                <?= ($room['ACCOMID'] == $accom['ACCOMID']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($accom['ACCOMODATION']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="invalid-feedback"><?= $errors['ACCOMID'] ?? '' ?></div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="ROOMDESC" class="form-label-premium">Room Description</label>
                            <textarea class="form-control-premium" 
                                      id="ROOMDESC" 
                                      name="ROOMDESC" 
                                      rows="4" 
                                      placeholder="Describe the room's features, view, special amenities..."><?= htmlspecialchars($room['ROOMDESC'] ?? '') ?></textarea>
                            <div class="form-text">
                                <span id="descCharCount"><?= strlen($room['ROOMDESC'] ?? '') ?></span>/500 characters
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Pricing & Capacity -->
                    <div class="form-section">
                        <h6><i class="bi bi-currency-dollar"></i> Pricing & Capacity</h6>
                        <div class="row">
                            <div class="col-md-3 mb-3">
                                <label for="NUMPERSON" class="form-label-premium required-field">
                                    Maximum Guests
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-people-fill"></i></span>
                                    <input type="number" 
                                           class="form-control <?= isset($errors['NUMPERSON']) ? 'is-invalid' : '' ?>" 
                                           id="NUMPERSON" 
                                           name="NUMPERSON" 
                                           value="<?= htmlspecialchars($room['NUMPERSON'] ?? '') ?>" 
                                           min="1" 
                                           max="10"
                                           required>
                                    <div class="invalid-feedback"><?= $errors['NUMPERSON'] ?? '' ?></div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="PRICE" class="form-label-premium required-field">
                                    Price per Night
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text">₱</span>
                                    <input type="number" 
                                           class="form-control <?= isset($errors['PRICE']) ? 'is-invalid' : '' ?>" 
                                           id="PRICE" 
                                           name="PRICE" 
                                           value="<?= htmlspecialchars($room['PRICE'] ?? '') ?>" 
                                           min="0" 
                                           step="0.01" 
                                           placeholder="0.00" 
                                           required>
                                    <div class="invalid-feedback"><?= $errors['PRICE'] ?? '' ?></div>
                                </div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label for="OROOMNUM" class="form-label-premium required-field">
                                    Available Units
                                </label>
                                <div class="input-group">
                                    <span class="input-group-text"><i class="bi bi-stack"></i></span>
                                    <input type="number" 
                                           class="form-control <?= isset($errors['OROOMNUM']) ? 'is-invalid' : '' ?>" 
                                           id="OROOMNUM" 
                                           name="OROOMNUM" 
                                           value="<?= htmlspecialchars($room['OROOMNUM'] ?? '') ?>" 
                                           min="0" 
                                           required>
                                    <div class="invalid-feedback"><?= $errors['OROOMNUM'] ?? '' ?></div>
                                </div>
                                <div class="form-text">Number of units of this room type</div>
                            </div>
                            <div class="col-md-3 mb-3">
                                <label class="form-label-premium">Room Status</label>
                                <div class="status-select">
                                    <div class="status-option">
                                        <input type="radio" 
                                               id="status_available" 
                                               name="ROOM_STATUS" 
                                               value="Available" 
                                               <?= (($room['ROOM_STATUS'] ?? 'Available') === 'Available') ? 'checked' : '' ?>>
                                        <i class="bi bi-check-circle-fill status-icon"></i>
                                        <label for="status_available" class="status-label">Available</label>
                                    </div>
                                    <div class="status-option">
                                        <input type="radio" 
                                               id="status_maintenance" 
                                               name="ROOM_STATUS" 
                                               value="Maintenance"
                                               <?= (($room['ROOM_STATUS'] ?? '') === 'Maintenance') ? 'checked' : '' ?>>
                                        <i class="bi bi-tools status-icon"></i>
                                        <label for="status_maintenance" class="status-label">Maintenance</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Step 3: Amenities -->
                    <div class="form-section">
                        <h6><i class="bi bi-stars"></i> Room Amenities</h6>
                        <p class="text-muted mb-3">Select all amenities available in this room</p>
                        <div class="amenities-grid">
                            <?php foreach ($availableAmenities as $key => $amenity): ?>
                                <div class="amenity-item">
                                    <input type="checkbox" 
                                           id="amenity_<?= $key ?>" 
                                           name="amenities[]" 
                                           value="<?= $key ?>"
                                           <?= in_array($key, $currentAmenities) ? 'checked' : '' ?>>
                                    <label for="amenity_<?= $key ?>" style="cursor: pointer; margin: 0;">
                                        <?= htmlspecialchars($amenity) ?>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- Step 4: Room Images -->
                    <div class="form-section">
                        <h6><i class="bi bi-camera-fill"></i> Room Images</h6>
                        
                        <!-- Current Images -->
                        <?php if (!empty($room['ROOMIMAGE'])): 
                            $currentImages = [];
                            $currentImages[] = $room['ROOMIMAGE'];
                            
                            // Check multiple possible paths
                            $possiblePaths = [
                                '../../uploads/rooms/',
                                '../../images/',
                                '../../photos/'
                            ];
                            
                            $validImages = [];
                            foreach ($currentImages as $img) {
                                foreach ($possiblePaths as $path) {
                                    if (file_exists(__DIR__ . '/' . $path . $img)) {
                                        $validImages[] = ['file' => $img, 'path' => $path];
                                        break;
                                    }
                                }
                            }
                        ?>
                            <?php if (!empty($validImages)): ?>
                                <div class="current-images-section">
                                    <label class="form-label-premium">Current Images</label>
                                    <div class="current-images-grid">
                                        <?php foreach ($validImages as $index => $image): ?>
                                            <div class="current-image-item">
                                                <img src="<?= htmlspecialchars($image['path'] . $image['file']) ?>" 
                                                     alt="Room image">
                                                <?php if ($index === 0): ?>
                                                    <span class="current-image-badge">Primary</span>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                    
                                    <div class="image-options mt-3">
                                        <div class="image-option">
                                            <input type="radio" id="keep_images" name="image_action" value="keep" checked>
                                            <label for="keep_images">Keep current images and add new ones</label>
                                        </div>
                                        <div class="image-option">
                                            <input type="radio" id="replace_images" name="replace_images" value="1">
                                            <label for="replace_images">Replace all current images</label>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <p class="text-muted mb-3">Upload new images for the room (up to 5 images)</p>
                        
                        <div class="image-upload-container" id="dropZone">
                            <input type="file" 
                                   class="d-none" 
                                   id="ROOMIMAGES" 
                                   name="ROOMIMAGES[]" 
                                   accept="image/jpeg,image/png,image/jpg,image/webp" 
                                   multiple>
                            <i class="bi bi-cloud-upload-fill upload-icon"></i>
                            <div class="upload-text">Drop images here or click to browse</div>
                            <div class="upload-hint">JPG, PNG, WEBP (Max 5MB each, up to 5 images)</div>
                        </div>
                        
                        <div class="image-preview-grid" id="imagePreviewGrid"></div>
                    </div>

                    <!-- Room Preview -->
                    <div class="form-section">
                        <h6><i class="bi bi-eye-fill"></i> Room Preview</h6>
                        <div class="room-preview" id="roomPreview">
                            <div class="preview-item">
                                <span class="preview-label">Room Number:</span>
                                <span class="preview-value" id="previewRoomNum"><?= htmlspecialchars($room['ROOMNUM'] ?? '-') ?></span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Room Type:</span>
                                <span class="preview-value" id="previewRoomType"><?= htmlspecialchars($room['ROOM'] ?? '-') ?></span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Price per Night:</span>
                                <span class="preview-value" id="previewPrice">₱<?= number_format($room['PRICE'] ?? 0, 2) ?></span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Max Guests:</span>
                                <span class="preview-value" id="previewCapacity"><?= htmlspecialchars($room['NUMPERSON'] ?? '-') ?></span>
                            </div>
                            <div class="preview-item">
                                <span class="preview-label">Amenities:</span>
                                <span class="preview-value" id="previewAmenities">
                                    <?php 
                                    if (!empty($currentAmenities)) {
                                        $amenityNames = array_map(function($key) use ($availableAmenities) {
                                            return $availableAmenities[$key] ?? $key;
                                        }, $currentAmenities);
                                        echo htmlspecialchars(implode(', ', $amenityNames));
                                    } else {
                                        echo 'None selected';
                                    }
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>

                    <!-- Submit Buttons -->
                    <div class="p-3 bg-light border-top d-flex gap-2 justify-content-between">
                        <div>
                            <button type="button" class="btn btn-outline-secondary" onclick="compareChanges()">
                                <i class="bi bi-arrow-left-right"></i> Compare Changes
                            </button>
                        </div>
                        <div class="d-flex gap-2">
                            <a href="manage_rooms.php" class="btn btn-secondary">
                                <i class="bi bi-x-circle"></i> Cancel
                            </a>
                            <button type="reset" class="btn btn-outline-secondary" onclick="resetForm()">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset Changes
                            </button>
                            <button type="submit" class="btn btn-premium btn-primary-premium">
                                <i class="bi bi-check-circle-fill"></i> Update Room
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </form>
    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Store original values for comparison
        const originalValues = {
            roomNum: '<?= addslashes($room['ROOMNUM'] ?? '') ?>',
            roomType: '<?= addslashes($room['ROOM'] ?? '') ?>',
            price: '<?= $room['PRICE'] ?? 0 ?>',
            capacity: '<?= $room['NUMPERSON'] ?? 0 ?>',
            description: '<?= addslashes($room['ROOMDESC'] ?? '') ?>',
            amenities: <?= json_encode($currentAmenities) ?>
        };

        // Character counter for description
        const descTextarea = document.getElementById('ROOMDESC');
        const charCounter = document.getElementById('descCharCount');
        
        descTextarea.addEventListener('input', function() {
            const length = this.value.length;
            charCounter.textContent = length;
            if (length > 500) {
                this.value = this.value.substring(0, 500);
                charCounter.textContent = 500;
            }
        });

        // Enhanced image upload with drag & drop
        const dropZone = document.getElementById('dropZone');
        const fileInput = document.getElementById('ROOMIMAGES');
        const previewGrid = document.getElementById('imagePreviewGrid');
        let uploadedFiles = [];

        dropZone.addEventListener('click', () => fileInput.click());

        dropZone.addEventListener('dragover', (e) => {
            e.preventDefault();
            dropZone.classList.add('dragover');
        });

        dropZone.addEventListener('dragleave', () => {
            dropZone.classList.remove('dragover');
        });

        dropZone.addEventListener('drop', (e) => {
            e.preventDefault();
            dropZone.classList.remove('dragover');
            handleFiles(e.dataTransfer.files);
        });

        fileInput.addEventListener('change', (e) => {
            handleFiles(e.target.files);
        });

        function handleFiles(files) {
            const maxFiles = 5;
            const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
            
            Array.from(files).forEach(file => {
                if (uploadedFiles.length >= maxFiles) {
                    alert(`Maximum ${maxFiles} images allowed`);
                    return;
                }
                
                if (!validTypes.includes(file.type)) {
                    alert(`Invalid file type: ${file.name}`);
                    return;
                }
                
                if (file.size > 5 * 1024 * 1024) {
                    alert(`File too large: ${file.name}`);
                    return;
                }
                
                uploadedFiles.push(file);
                displayPreview(file, uploadedFiles.length - 1);
            });
            
            updateFormSteps(4);
        }

        function displayPreview(file, index) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const div = document.createElement('div');
                div.className = 'image-preview-item';
                div.innerHTML = `
                    <img src="${e.target.result}" alt="Preview">
                    ${index === 0 ? '<span class="primary-badge">Primary</span>' : ''}
                    <button type="button" class="remove-btn" onclick="removeImage(${index})">
                        <i class="bi bi-x"></i>
                    </button>
                `;
                previewGrid.appendChild(div);
            };
            reader.readAsDataURL(file);
        }

        function removeImage(index) {
            uploadedFiles.splice(index, 1);
            refreshPreviews();
        }

        function refreshPreviews() {
            previewGrid.innerHTML = '';
            uploadedFiles.forEach((file, index) => displayPreview(file, index));
        }

        // Real-time preview update
        function updatePreview() {
            document.getElementById('previewRoomNum').textContent = document.getElementById('ROOMNUM').value || '-';
            document.getElementById('previewRoomType').textContent = document.getElementById('ROOM').value || '-';
            
            const price = document.getElementById('PRICE').value;
            document.getElementById('previewPrice').textContent = price ? `₱${parseFloat(price).toFixed(2)}` : '-';
            
            document.getElementById('previewCapacity').textContent = document.getElementById('NUMPERSON').value || '-';
            
            const checkedAmenities = document.querySelectorAll('input[name="amenities[]"]:checked');
            const amenityNames = Array.from(checkedAmenities).map(cb => {
                return cb.nextElementSibling.textContent.trim();
            });
            document.getElementById('previewAmenities').textContent = amenityNames.length > 0 ? amenityNames.join(', ') : 'None selected';
        }

        // Add event listeners for real-time preview
        document.getElementById('ROOMNUM').addEventListener('input', updatePreview);
        document.getElementById('ROOM').addEventListener('input', updatePreview);
        document.getElementById('PRICE').addEventListener('input', updatePreview);
        document.getElementById('NUMPERSON').addEventListener('input', updatePreview);
        document.querySelectorAll('input[name="amenities[]"]').forEach(cb => {
            cb.addEventListener('change', updatePreview);
        });

        // Update form steps
        function updateFormSteps(step) {
            document.querySelectorAll('.step').forEach((s, index) => {
                if (index < step) {
                    s.classList.add('completed');
                    s.classList.remove('active');
                } else if (index === step - 1) {
                    s.classList.add('active');
                    s.classList.remove('completed');
                } else {
                    s.classList.remove('active', 'completed');
                }
            });
        }

        // Compare changes function
        function compareChanges() {
            let changes = [];
            
            if (document.getElementById('ROOMNUM').value !== originalValues.roomNum) {
                changes.push(`Room Number: ${originalValues.roomNum} → ${document.getElementById('ROOMNUM').value}`);
            }
            if (document.getElementById('ROOM').value !== originalValues.roomType) {
                changes.push(`Room Type: ${originalValues.roomType} → ${document.getElementById('ROOM').value}`);
            }
            if (parseFloat(document.getElementById('PRICE').value) !== parseFloat(originalValues.price)) {
                changes.push(`Price: ₱${originalValues.price} → ₱${document.getElementById('PRICE').value}`);
            }
            
            if (changes.length > 0) {
                alert('Changes made:\n\n' + changes.join('\n'));
            } else {
                alert('No changes made yet.');
            }
        }

        // Reset form function
        function resetForm() {
            uploadedFiles = [];
            refreshPreviews();
            updatePreview();
            updateFormSteps(1);
        }

        // Form validation
        document.getElementById('editRoomForm').addEventListener('submit', function(e) {
            e.preventDefault();
            
            // Validate form
            if (!this.checkValidity()) {
                e.stopPropagation();
                this.classList.add('was-validated');
                return;
            }
            
            // Create FormData and append files
            const formData = new FormData(this);
            
            // Remove the file input files and add our managed files
            formData.delete('ROOMIMAGES[]');
            uploadedFiles.forEach(file => {
                formData.append('ROOMIMAGES[]', file);
            });
            
            // Submit form
            this.submit();
        });

        // Show notification
        function showNotification(message, type = 'info') {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed top-0 start-50 translate-middle-x mt-3`;
            alertDiv.style.zIndex = '9999';
            alertDiv.innerHTML = `
                <i class="bi bi-${type === 'success' ? 'check-circle' : 'info-circle'}-fill"></i>
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            document.body.appendChild(alertDiv);
            
            setTimeout(() => {
                alertDiv.classList.remove('show');
                setTimeout(() => alertDiv.remove(), 300);
            }, 5000);
        }
    </script>
</body>
</html>