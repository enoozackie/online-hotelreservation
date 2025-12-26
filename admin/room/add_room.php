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

    // Get accommodations for dropdown
    $accommodations = $roomModel->getAllAccommodations();

    // Variables for errors and success
    $errors = [];
    $success = '';

    // Sticky form values
    $ROOMNUM = $ROOM = $ROOMDESC = $NUMPERSON = $PRICE = $ACCOMID = '';
    $amenities = [];

    // Define the correct upload directory path (at project root level)
    $uploadDir = __DIR__ . '/../../uploads/rooms/';
    $webPath = '../../uploads/rooms/'; // Path for web access

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

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ROOMNUM = trim($_POST['ROOMNUM'] ?? '');
        $ROOM = trim($_POST['ROOM'] ?? '');
        $ROOMDESC = trim($_POST['ROOMDESC'] ?? '');
        $NUMPERSON = trim($_POST['NUMPERSON'] ?? '');
        $PRICE = trim($_POST['PRICE'] ?? '');
        $ACCOMID = trim($_POST['ACCOMID'] ?? '');
        $amenities = $_POST['amenities'] ?? [];

        // --- ENHANCED Validation ---
        if (empty($ROOMNUM)) {
            $errors['ROOMNUM'] = "Room number is required.";
        } else if ($roomModel->isRoomNumberExists($ROOMNUM)) {
            $errors['ROOMNUM'] = "Room number already exists.";
        }
        
        // CRITICAL: Validate ROOM field (Room Type/Name)
        if (empty($ROOM)) {
            $errors['ROOM'] = "Room type/name is required.";
        } else if (strlen($ROOM) < 3) {
            $errors['ROOM'] = "Room type/name must be at least 3 characters.";
        } else if ($ROOM === '0' || is_numeric($ROOM)) {
            $errors['ROOM'] = "Invalid room type selected.";
        }
        
        if (empty($ROOMDESC)) {
            $errors['ROOMDESC'] = "Room description is required.";
        } else if (strlen($ROOMDESC) < 10) {
            $errors['ROOMDESC'] = "Room description must be at least 10 characters.";
        }
        
        if (empty($NUMPERSON) || !is_numeric($NUMPERSON) || $NUMPERSON < 1) {
            $errors['NUMPERSON'] = "Capacity must be a positive number.";
        }
        
        if (empty($PRICE) || !is_numeric($PRICE) || $PRICE < 0) {
            $errors['PRICE'] = "Price must be a valid positive number.";
        }
        
        if (empty($ACCOMID)) {
            $errors['ACCOMID'] = "Accommodation type is required.";
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

        // --- Handle file upload (only primary image for this schema) ---
        $uploadedImage = null;
        if (isset($_FILES['ROOMIMAGE']) && $_FILES['ROOMIMAGE']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['ROOMIMAGE']['tmp_name'];
            $fileName = basename($_FILES['ROOMIMAGE']['name']);
            $fileSize = $_FILES['ROOMIMAGE']['size'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            
            if (!in_array($fileExtension, $allowedExtensions)) {
                $errors['ROOMIMAGE'] = "Invalid image format.";
            } elseif ($fileSize > 5 * 1024 * 1024) { // 5MB
                $errors['ROOMIMAGE'] = "File exceeds 5MB limit.";
            } else {
                $newFileName = uniqid('room_', true) . '.' . $fileExtension;
                $dest_path = $uploadDir . $newFileName;
                
                if (move_uploaded_file($fileTmpPath, $dest_path)) {
                    $uploadedImage = $newFileName;
                } else {
                    $errors['ROOMIMAGE'] = "Error uploading file.";
                }
            }
        }

        // --- Insert into database if no errors ---
        if (empty($errors)) {
            try {
                // Include amenities in the room description since there's no separate amenities field
                $amenitiesText = !empty($amenities) ? "\n\nAmenities: " . implode(', ', array_map(function($key) use ($availableAmenities) {
                    return $availableAmenities[$key] ?? $key;
                }, $amenities)) : '';
                
                $roomData = [
                    'ROOMNUM' => $ROOMNUM,
                    'ROOM' => $ROOM,
                    'ACCOMID' => $ACCOMID,
                    'ROOMDESC' => $ROOMDESC . $amenitiesText,
                    'NUMPERSON' => $NUMPERSON,
                    'PRICE' => $PRICE,
                    'ROOMIMAGE' => $uploadedImage
                ];

                // Add room with history tracking
                $roomModel->addRoomWithHistory(
                    $roomData,
                    $_SESSION['id'] ?? null,
                    $_SESSION['username'] ?? 'Admin',
                    'New room added via admin panel'
                );
                
                $success = "Room added successfully!";
                
                // Clear form data on success
                $ROOMNUM = $ROOM = $ROOMDESC = $NUMPERSON = $PRICE = $ACCOMID = '';
                $amenities = [];
                
            } catch (Exception $e) {
                // Clean up uploaded file on error
                if ($uploadedImage) {
                    @unlink($uploadDir . $uploadedImage);
                }
                $errors['db_error'] = "Error adding room: " . $e->getMessage();
            }
        }
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Add Room - Monbela Hotel</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
        <style>
            /* Styles remain the same as in your original code */
            :root {
                /* Enhanced Color System - White Theme */
                --primary-dark: #f8f9fa;
                --primary-main: #ffffff;
                --primary-light: #f1f3f5;
                --secondary-main: #3498db;
                --secondary-dark: #2980b9;
                --secondary-light: #85c1e9;
                --accent: #9b59b6;
                --accent-light: #bb8fce;
                --success: #2ecc71;
                --success-light: #58d68d;
                --danger: #e74c3c;
                --danger-light: #ec7063;
                --warning: #f39c12;
                --info: #3498db;
                --neutral-100: #f8f9fa;
                --neutral-200: #e9ecef;
                --neutral-300: #dee2e6;
                --neutral-400: #ced4da;
                --neutral-500: #adb5bd;
                --neutral-600: #6c757d;
                --neutral-700: #495057;
                --neutral-800: #343a40;
                --neutral-900: #212529;
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

            /* Enhanced Sidebar - White Theme */
            .sidebar {
                position: fixed;
                top: 0;
                left: 0;
                height: 100vh;
                width: var(--sidebar-width);
                background: var(--white);
                color: var(--neutral-700);
                overflow-y: auto;
                z-index: 1000;
                box-shadow: var(--shadow-xl);
                transition: var(--transition-base);
                border-right: 1px solid var(--neutral-200);
            }

            .sidebar-header {
                padding: 2rem;
                background: var(--neutral-100);
                backdrop-filter: blur(10px);
                border-bottom: 1px solid var(--neutral-200);
            }

            .sidebar-header h5 {
                font-weight: 700;
                font-size: 1.5rem;
                letter-spacing: -0.02em;
                display: flex;
                align-items: center;
                gap: 0.75rem;
                color: var(--neutral-800);
            }

            .sidebar-header h5 i {
                font-size: 1.75rem;
                color: var(--secondary-main);
            }

            .sidebar .nav-link {
                color: var(--neutral-600);
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
                background: rgba(52, 152, 219, 0.1);
                transition: var(--transition-base);
            }

            .sidebar .nav-link:hover::before,
            .sidebar .nav-link.active::before {
                width: 100%;
            }

            .sidebar .nav-link:hover,
            .sidebar .nav-link.active {
                color: var(--secondary-main);
                border-left-color: var(--secondary-main);
                background: rgba(52, 152, 219, 0.05);
            }

            .nav-section {
                margin-top: 1.5rem;
                padding-top: 1.5rem;
                border-top: 1px solid var(--neutral-200);
            }

            .nav-section-title {
                padding: 0.5rem 2rem;
                font-size: 0.75rem;
                text-transform: uppercase;
                letter-spacing: 0.08em;
                color: var(--neutral-500);
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
                color: var(--neutral-800);
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
                box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.2);
                transform: translateY(-1px);
            }

            .form-label-premium {
                font-weight: 600;
                color: var(--neutral-800);
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
                background: rgba(52, 152, 219, 0.1);
            }

            /* Enhanced Image Upload */
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
                background: rgba(52, 152, 219, 0.05);
            }

            .image-upload-container.dragover {
                border-color: var(--secondary-main);
                background: rgba(52, 152, 219, 0.1);
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

            /* Image Preview */
            .image-preview {
                margin-top: 1.5rem;
                max-width: 300px;
                border-radius: var(--radius-lg);
                overflow: hidden;
                box-shadow: var(--shadow-md);
            }

            .image-preview img {
                width: 100%;
                height: auto;
                display: block;
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
                background: rgba(52, 152, 219, 0.1);
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
                color: var(--neutral-800);
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
                color: var(--neutral-800);
                font-weight: 600;
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
                    <a class="nav-link" href="manage_rooms.php">
                        <i class="bi bi-door-closed-fill"></i> Manage Rooms
                    </a>
                    <a class="nav-link active" href="add_room.php">
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
                        <h2><i class="bi bi-plus-square-fill me-2"></i>Add New Room</h2>
                        <p class="mb-0 text-muted">Create a new room listing with all details and amenities</p>
                    </div>
                    <a href="manage_rooms.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Rooms
                    </a>
                </div>
            </div>

            <!-- Form Progress Steps -->
            <div class="form-steps mb-4">
                <div class="step active">
                    <div class="step-circle">1</div>
                    <div class="step-label">Basic Info</div>
                </div>
                <div class="step">
                    <div class="step-circle">2</div>
                    <div class="step-label">Pricing</div>
                </div>
                <div class="step">
                    <div class="step-circle">3</div>
                    <div class="step-label">Amenities</div>
                </div>
                <div class="step">
                    <div class="step-circle">4</div>
                    <div class="step-label">Image</div>
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

            <form action="" method="POST" enctype="multipart/form-data" id="addRoomForm" novalidate>
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
                                        value="<?= htmlspecialchars($ROOMNUM) ?>" 
                                        placeholder="e.g., 101, A-205" 
                                        required>
                                    <div class="invalid-feedback"><?= $errors['ROOMNUM'] ?? '' ?></div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label for="ROOM" class="form-label-premium required-field">
                                        Room Type/Name
                                        <i class="bi bi-question-circle tooltip-icon" 
                                        data-bs-toggle="tooltip" 
                                        data-bs-placement="top" 
                                        title="Select the predefined room category or type"></i>
                                    </label>
                                    <select class="form-select <?= isset($errors['ROOM']) ? 'is-invalid' : '' ?>" 
                                            id="ROOM" 
                                            name="ROOM" 
                                            required>
                                        <option value="">-- Select Room Type --</option>
                                        <option value="Wing B" <?= $ROOM === 'Wing B' ? 'selected' : '' ?>>Wing B</option>
                                        <option value="Wing A" <?= $ROOM === 'Wing A' ? 'selected' : '' ?>>Wing A</option>
                                    </select>
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
                                                    <?= ($ACCOMID == $accom['ACCOMID']) ? 'selected' : '' ?>>
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
                                        placeholder="Describe the room's features, view, special amenities..."><?= htmlspecialchars($ROOMDESC) ?></textarea>
                                <div class="form-text">
                                </div>
                            </div>
                        </div>

                        <!-- Step 2: Pricing & Capacity -->
                        <div class="form-section">
                            <h6><i class="bi bi-currency-dollar"></i> Pricing & Capacity</h6>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="NUMPERSON" class="form-label-premium required-field">
                                        Maximum Guests
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-people-fill"></i></span>
                                        <input type="number" 
                                            class="form-control <?= isset($errors['NUMPERSON']) ? 'is-invalid' : '' ?>" 
                                            id="NUMPERSON" 
                                            name="NUMPERSON" 
                                            value="<?= htmlspecialchars($NUMPERSON) ?>" 
                                            min="1" 
                                            max="10"
                                            required>
                                        <div class="invalid-feedback"><?= $errors['NUMPERSON'] ?? '' ?></div>
                                    </div>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="PRICE" class="form-label-premium required-field">
                                        Price per Night
                                    </label>
                                    <div class="input-group">
                                        <span class="input-group-text">₱</span>
                                        <input type="number" 
                                            class="form-control <?= isset($errors['PRICE']) ? 'is-invalid' : '' ?>" 
                                            id="PRICE" 
                                            name="PRICE" 
                                            value="<?= htmlspecialchars($PRICE) ?>" 
                                            min="0" 
                                            step="0.01" 
                                            placeholder="0.00" 
                                            required>
                                        <div class="invalid-feedback"><?= $errors['PRICE'] ?? '' ?></div>
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
                                            <?= in_array($key, $amenities) ? 'checked' : '' ?>>
                                        <label for="amenity_<?= $key ?>" style="cursor: pointer; margin: 0;">
                                            <?= htmlspecialchars($amenity) ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <!-- Step 4: Room Image -->
                        <div class="form-section">
                            <h6><i class="bi bi-camera-fill"></i> Room Image</h6>
                            <p class="text-muted mb-3">Upload a high-quality image of the room</p>
                            
                            <div class="image-upload-container" id="dropZone">
                                <input type="file" 
                                    class="d-none" 
                                    id="ROOMIMAGE" 
                                    name="ROOMIMAGE" 
                                    accept="image/jpeg,image/png,image/jpg,image/webp">
                                <i class="bi bi-cloud-upload-fill upload-icon"></i>
                                <div class="upload-text">Drop image here or click to browse</div>
                                <div class="upload-hint">JPG, PNG, WEBP (Max 5MB)</div>
                            </div>
                            
                            <div class="image-preview d-none" id="imagePreview">
                                <img id="previewImg" src="" alt="Room preview">
                            </div>
                        </div>

                        <!-- Room Preview (Optional) -->
                        <div class="form-section">
                            <h6><i class="bi bi-eye-fill"></i> Room Preview</h6>
                            <div class="room-preview" id="roomPreview">
                                <div class="preview-item">
                                    <span class="preview-label">Room Number:</span>
                                    <span class="preview-value" id="previewRoomNum">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">Room Type:</span>
                                    <span class="preview-value" id="previewRoomType">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">Price per Night:</span>
                                    <span class="preview-value" id="previewPrice">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">Max Guests:</span>
                                    <span class="preview-value" id="previewCapacity">-</span>
                                </div>
                                <div class="preview-item">
                                    <span class="preview-label">Amenities:</span>
                                    <span class="preview-value" id="previewAmenities">-</span>
                                </div>
                            </div>
                        </div>

                        <!-- Submit Buttons -->
                        <div class="p-3 bg-light border-top d-flex gap-2 justify-content-between">
                            <div>
                                <button type="button" class="btn btn-outline-secondary" onclick="saveDraft()">
                                    <i class="bi bi-save"></i> Save as Draft
                                </button>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="manage_rooms.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                                <button type="reset" class="btn btn-outline-secondary" onclick="resetForm()">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset
                                </button>
                                <button type="submit" class="btn btn-premium btn-primary-premium">
                                    <i class="bi bi-check-circle-fill"></i> Create Room
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
            const fileInput = document.getElementById('ROOMIMAGE');
            const imagePreview = document.getElementById('imagePreview');
            const previewImg = document.getElementById('previewImg');

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
                
                if (e.dataTransfer.files.length > 0) {
                    handleFile(e.dataTransfer.files[0]);
                }
            });

            fileInput.addEventListener('change', (e) => {
                if (e.target.files.length > 0) {
                    handleFile(e.target.files[0]);
                }
            });

            function handleFile(file) {
                const validTypes = ['image/jpeg', 'image/png', 'image/webp'];
                
                if (!validTypes.includes(file.type)) {
                    alert('Invalid file type. Please upload a JPG, PNG, or WEBP image.');
                    return;
                }
                
                if (file.size > 5 * 1024 * 1024) {
                    alert('File too large. Please upload an image smaller than 5MB.');
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    previewImg.src = e.target.result;
                    imagePreview.classList.remove('d-none');
                    updateFormSteps(4);
                };
                reader.readAsDataURL(file);
            }

            // Real-time preview update
            function updatePreview() {
                document.getElementById('previewRoomNum').textContent = document.getElementById('ROOMNUM').value || '-';
                
                const roomTypeSelect = document.getElementById('ROOM');
                document.getElementById('previewRoomType').textContent = roomTypeSelect.options[roomTypeSelect.selectedIndex].text || '-';
                
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
            document.getElementById('ROOM').addEventListener('change', updatePreview);
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

            // Save draft functionality
            function saveDraft() {
                const formData = new FormData(document.getElementById('addRoomForm'));
                const draft = {};
                
                for (let [key, value] of formData.entries()) {
                    if (draft[key]) {
                        if (Array.isArray(draft[key])) {
                            draft[key].push(value);
                        } else {
                            draft[key] = [draft[key], value];
                        }
                    } else {
                        draft[key] = value;
                    }
                }
                
                localStorage.setItem('roomDraft', JSON.stringify(draft));
                showNotification('Draft saved successfully!', 'success');
            }

            // Load draft if exists
            window.addEventListener('load', function() {
                const draft = localStorage.getItem('roomDraft');
                if (draft) {
                    if (confirm('A draft was found. Would you like to restore it?')) {
                        const data = JSON.parse(draft);
                        // Restore form fields
                        Object.keys(data).forEach(key => {
                            const field = document.querySelector(`[name="${key}"]`);
                            if (field) {
                                if (field.type === 'checkbox' || field.type === 'radio') {
                                    if (Array.isArray(data[key])) {
                                        data[key].forEach(val => {
                                            const cb = document.querySelector(`[name="${key}"][value="${val}"]`);
                                            if (cb) cb.checked = true;
                                        });
                                    } else {
                                        field.checked = field.value === data[key];
                                    }
                                } else {
                                    field.value = data[key];
                                }
                            }
                        });
                        updatePreview();
                    }
                }
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

            // Reset form and clear draft
            function resetForm() {
                if (confirm('Are you sure you want to reset the form? This will also clear any saved draft.')) {
                    localStorage.removeItem('roomDraft');
                    imagePreview.classList.add('d-none');
                    updatePreview();
                    updateFormSteps(1);
                }
            }

            // Form validation
            document.getElementById('addRoomForm').addEventListener('submit', function(e) {
                e.preventDefault();
                
                // Validate form
                if (!this.checkValidity()) {
                    e.stopPropagation();
                    this.classList.add('was-validated');
                    return;
                }
                
                // Submit form
                this.submit();
            });
        </script>
    </body>
    </html>