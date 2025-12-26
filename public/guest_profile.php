<?php
// =============================================================================
// public/guest_profile.php - Modern, UI-Friendly Guest Profile
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

// Handle logout properly
if (isset($_GET['logout']) || isset($_POST['logout'])) {
    session_destroy();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    header("Location: login.php");
    exit;
}

// Restrict Access for non-logout requests
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'guest') {
    header("Location: login.php");
    exit;
}

// Fetch Guest Data
 $guest = new Guest();
 $currentGuest = $guest->getById($_SESSION['id']);
if (!$currentGuest) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Display success or error messages
 $success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

 $errors = $_SESSION['upload_errors'] ?? [];
unset($_SESSION['upload_errors']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Monbela Hotel</title>
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

        /* Loading Screen */
        .loading-screen {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(255, 255, 255, 0.95);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
            transition: opacity 0.5s ease, visibility 0.5s ease;
        }

        .loading-screen.hidden {
            opacity: 0;
            visibility: hidden;
        }

        .loading-spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(37, 99, 235, 0.2);
            border-top: 5px solid var(--primary);
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }

        .loading-text {
            margin-top: 1.5rem;
            font-size: 1.2rem;
            color: var(--dark);
            font-weight: 500;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
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
        }

        .page-subtitle {
            color: var(--gray);
            margin: 0.5rem 0 0;
            font-size: 1.1rem;
        }

        /* Profile Card */
        .profile-card {
            background: white;
            border-radius: 16px;
            box-shadow: var(--shadow-lg);
            overflow: hidden;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .profile-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
        }

        .profile-header {
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);
            color: white;
            padding: 3rem 2rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .profile-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            animation: pulse 8s infinite linear;
        }

        @keyframes pulse {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .profile-image-container {
            position: relative;
            width: 160px;
            height: 160px;
            margin: 0 auto 1.5rem;
            z-index: 2;
        }

        .profile-image {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            border: 5px solid rgba(255, 255, 255, 0.3);
            object-fit: cover;
            box-shadow: var(--shadow-xl);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            cursor: pointer;
        }

        .profile-image:hover {
            transform: scale(1.05);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
        }

        .profile-image-overlay {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: pointer;
        }

        .profile-image-container:hover .profile-image-overlay {
            opacity: 1;
        }

        .profile-image-overlay i {
            font-size: 2rem;
            color: white;
        }

        .profile-image-placeholder {
            width: 100%;
            height: 100%;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 3.5rem;
            font-weight: 700;
            color: white;
            box-shadow: var(--shadow-xl);
            cursor: pointer;
            transition: transform 0.3s ease;
        }

        .profile-image-placeholder:hover {
            transform: scale(1.05);
        }

        .profile-name {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            position: relative;
            z-index: 2;
        }

        .profile-email {
            color: rgba(255, 255, 255, 0.9);
            margin-bottom: 1rem;
            font-size: 1.1rem;
            position: relative;
            z-index: 2;
        }

        .profile-actions {
            display: flex;
            gap: 1rem;
            justify-content: center;
            position: relative;
            z-index: 2;
        }

        .profile-btn {
            background: rgba(255, 255, 255, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.3);
            color: white;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            font-size: 0.9rem;
            cursor: pointer;
            transition: all 0.3s ease;
            backdrop-filter: blur(10px);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .profile-btn:hover {
            background: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .profile-body {
            padding: 2.5rem;
        }

        .info-section {
            margin-bottom: 2.5rem;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeInUp 0.6s forwards;
        }

        .info-section:nth-child(1) { animation-delay: 0.1s; }
        .info-section:nth-child(2) { animation-delay: 0.2s; }
        .info-section:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .info-section:last-child {
            margin-bottom: 0;
        }

        .info-section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.75rem;
            position: relative;
        }

        .info-section-title::after {
            content: '';
            position: absolute;
            bottom: -2px;
            left: 0;
            width: 60px;
            height: 2px;
            background: var(--primary);
            transition: width 0.3s ease;
        }

        .info-section:hover .info-section-title::after {
            width: 120px;
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 1.5rem;
        }

        .info-item {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: 1.25rem;
            border-radius: 12px;
            background: linear-gradient(135deg, #f8f9fa 0%, #f1f3f5 100%);
            transition: all 0.3s ease;
            border: 1px solid var(--border);
            position: relative;
            overflow: hidden;
        }

        .info-item::before {
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

        .info-item:hover {
            transform: translateY(-3px);
            box-shadow: var(--shadow);
            border-color: var(--primary);
        }

        .info-item:hover::before {
            transform: scaleY(1);
        }

        .info-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.25rem;
            transition: transform 0.3s ease;
        }

        .info-item:hover .info-icon {
            transform: scale(1.1);
        }

        .info-text {
            display: flex;
            flex-direction: column;
        }

        .info-label {
            font-size: 0.875rem;
            color: var(--gray);
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 0.25rem;
        }

        .info-value {
            font-size: 1.125rem;
            font-weight: 500;
            color: var(--dark);
        }

        /* Action Buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            justify-content: center;
            margin-top: 2rem;
            padding: 2rem;
            border-top: 1px solid var(--border);
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

        .btn-secondary {
            background: white;
            color: var(--dark);
            border: 2px solid var(--border);
        }

        .btn-secondary:hover {
            background: var(--light);
            border-color: var(--primary);
            color: var(--primary);
            transform: translateY(-2px);
        }

        /* Alerts */
        .alert {
            border-radius: 12px;
            padding: 1rem 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            border: none;
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
            background: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-success::before {
            background: var(--success);
        }

        .alert-danger {
            background: #fee2e2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .alert-danger::before {
            background: var(--danger);
        }

        .alert i {
            font-size: 1.25rem;
        }

        /* Profile Image Modal */
        .image-modal {
            display: none;
            position: fixed;
            z-index: 2000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            opacity: 0;
            transition: opacity 0.3s ease;
            cursor: zoom-out;
        }

        .image-modal.show {
            opacity: 1;
        }

        .image-modal-content {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) scale(0.8);
            max-width: 90%;
            max-height: 90%;
            border-radius: 8px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5);
            transition: transform 0.3s ease;
            cursor: zoom-in;
        }

        .image-modal.show .image-modal-content {
            transform: translate(-50%, -50%) scale(1);
        }

        .image-modal-content.zoomed {
            transform: translate(-50%, -50%) scale(2);
            cursor: zoom-out;
        }

        .image-modal-close {
            position: absolute;
            top: 20px;
            right: 35px;
            color: #fff;
            font-size: 40px;
            font-weight: bold;
            cursor: pointer;
            opacity: 0.8;
            transition: all 0.3s ease;
            z-index: 2001;
        }

        .image-modal-close:hover {
            opacity: 1;
            transform: scale(1.1);
        }

        /* Responsive Design */
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

            .profile-body {
                padding: 1.5rem;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .action-buttons {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Screen -->
    <div class="loading-screen" id="loadingScreen">
        <div class="loading-spinner"></div>
        <div class="loading-text">Loading Profile...</div>
    </div>

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
                <li><a href="guest_profile.php" class="active"><i class="bi bi-person-circle"></i> Profile</a></li>
                <li><a href="my_reservations.php"><i class="bi bi-calendar-check"></i> My Reservations</a></li>
                <li><a href="guest_profile.php?logout=1"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Page Header -->
        <div class="page-header" data-aos="fade-up">
            <div>
                <h1 class="page-title">My Profile</h1>
                <p class="page-subtitle">View and manage your personal information</p>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success" data-aos="fade-down">
                <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger" data-aos="fade-down">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?php foreach ($errors as $error): ?>
                    <p style="margin: 0;"><?= htmlspecialchars($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Profile Card -->
        <div class="profile-card" data-aos="fade-up">
            <!-- Profile Header -->
            <div class="profile-header">
                <div class="profile-image-container">
                    <?php if (!empty($currentGuest['G_PROFILEIMAGE'])): ?>
                        <img src="/images/profiles/<?= htmlspecialchars($currentGuest['G_PROFILEIMAGE']) ?>" 
                             class="profile-image" 
                             alt="Profile Picture"
                             onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($currentGuest['G_FNAME'] . ' ' . $currentGuest['G_LNAME']) ?>&background=2563eb&color=fff&size=160'"
                             onclick="openImageModal(this.src)">
                        <div class="profile-image-overlay" onclick="openImageModal(this.previousElementSibling.src)">
                            <i class="bi bi-zoom-in"></i>
                        </div>
                    <?php else: ?>
                        <div class="profile-image-placeholder" onclick="document.getElementById('profile_image_input').click()">
                            <?= htmlspecialchars(substr($currentGuest['G_FNAME'], 0, 1) . substr($currentGuest['G_LNAME'], 0, 1)) ?>
                        </div>
                    <?php endif; ?>
                </div>
                <h1 class="profile-name"><?= htmlspecialchars($currentGuest['G_FNAME'] . ' ' . $currentGuest['G_LNAME']) ?></h1>
                <p class="profile-email"><?= htmlspecialchars($currentGuest['G_UNAME']) ?></p>
                
                <!-- Profile Actions -->
                <div class="profile-actions">
                    <!-- Hidden form for image upload -->
                    <form action="update_profile_image.php" method="POST" enctype="multipart/form-data" style="display: inline;">
                        <input type="file" name="profile_image" id="profile_image_input" accept="image/*" style="display: none;" onchange="this.form.submit()">
                        <label for="profile_image_input" class="profile-btn">
                            <i class="bi bi-camera-fill"></i> Change Photo
                        </label>
                    </form>
                    
                    <?php if (!empty($currentGuest['G_PROFILEIMAGE'])): ?>
                        <button class="profile-btn" onclick="removeProfileImage()">
                            <i class="bi bi-trash"></i> Remove
                        </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Profile Body -->
            <div class="profile-body">
                <!-- Personal Information -->
                <div class="info-section">
                    <h2 class="info-section-title">
                        <i class="bi bi-person-fill"></i> Personal Information
                    </h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-person-badge"></i></div>
                            <div class="info-text">
                                <div class="info-label">Full Name</div>
                                <div class="info-value"><?= htmlspecialchars($currentGuest['G_FNAME'] . ' ' . $currentGuest['G_LNAME']) ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-telephone"></i></div>
                            <div class="info-text">
                                <div class="info-label">Phone Number</div>
                                <div class="info-value"><?= htmlspecialchars($currentGuest['G_PHONE']) ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-gift"></i></div>
                            <div class="info-text">
                                <div class="info-label">Birthday</div>
                                <div class="info-value"><?= date("F j, Y", strtotime($currentGuest['DBIRTH'])) ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-flag"></i></div>
                            <div class="info-text">
                                <div class="info-label">Nationality</div>
                                <div class="info-value"><?= htmlspecialchars($currentGuest['G_NATIONALITY']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Contact Information -->
                <div class="info-section">
                    <h2 class="info-section-title">
                        <i class="bi bi-geo-alt"></i> Contact Information
                    </h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-house"></i></div>
                            <div class="info-text">
                                <div class="info-label">Address</div>
                                <div class="info-value"><?= htmlspecialchars($currentGuest['G_ADDRESS']) ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-building"></i></div>
                            <div class="info-text">
                                <div class="info-label">Company</div>
                                <div class="info-value"><?= htmlspecialchars($currentGuest['G_COMPANY']) ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Account Information -->
                <div class="info-section">
                    <h2 class="info-section-title">
                        <i class="bi bi-shield-check"></i> Account Information
                    </h2>
                    <div class="info-grid">
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-person-circle"></i></div>
                            <div class="info-text">
                                <div class="info-label">Username</div>
                                <div class="info-value"><?= htmlspecialchars($currentGuest['G_UNAME']) ?></div>
                            </div>
                        </div>
                        <div class="info-item">
                            <div class="info-icon"><i class="bi bi-calendar-check"></i></div>
                            <div class="info-text">
                                <div class="info-label">Member Since</div>
                                <div class="info-value"><?= date("F j, Y", strtotime($currentGuest['REGISTRATIONDATE'] ?? 'now')) ?></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Action Buttons -->
            <div class="action-buttons">
                <a href="edit_guest.php" class="btn btn-primary">
                    <i class="bi bi-pencil-square"></i> Edit Profile
                </a>
                <a href="homepage.php" class="btn btn-secondary">
                    <i class="bi bi-arrow-left"></i> Back to Dashboard
                </a>
            </div>
        </div>
    </div>

    <!-- Profile Image Modal -->
    <div id="imageModal" class="image-modal">
        <span class="image-modal-close">&times;</span>
        <img class="image-modal-content" id="modalImage">
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

        // Hide loading screen when page is fully loaded
        window.addEventListener('load', function() {
            setTimeout(function() {
                document.getElementById('loadingScreen').classList.add('hidden');
            }, 500);
        });

        // Mobile menu toggle
        function toggleMenu() {
            document.getElementById('navLinks').classList.toggle('active');
        }

        // Profile Image Modal
        let isZoomed = false;

        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImg = document.getElementById('modalImage');
            
            modal.style.display = 'block';
            modalImg.src = imageSrc;
            
            // Reset zoom state
            isZoomed = false;
            modalImg.classList.remove('zoomed');
            
            // Add show class for fade-in effect
            setTimeout(() => {
                modal.classList.add('show');
            }, 10);
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.remove('show');
            
            setTimeout(() => {
                modal.style.display = 'none';
            }, 300);
        }

        function toggleImageZoom() {
            const modalImg = document.getElementById('modalImage');
            isZoomed = !isZoomed;
            
            if (isZoomed) {
                modalImg.classList.add('zoomed');
            } else {
                modalImg.classList.remove('zoomed');
            }
        }

        // Initialize modal event listeners
        document.addEventListener('DOMContentLoaded', function() {
            const modal = document.getElementById('imageModal');
            const span = document.getElementsByClassName('image-modal-close')[0];
            const modalImg = document.getElementById('modalImage');
            
            // Close button
            if (span) {
                span.onclick = closeImageModal;
            }
            
            // Click outside to close
            window.onclick = function(event) {
                if (event.target == modal) {
                    closeImageModal();
                }
            };
            
            // Click on image to zoom
            modalImg.onclick = toggleImageZoom;
            
            // Keyboard navigation
            document.addEventListener('keydown', function(e) {
                if (modal.style.display === 'block') {
                    if (e.key === 'Escape') {
                        closeImageModal();
                    } else if (e.key === ' ' || e.key === 'Enter') {
                        e.preventDefault();
                        toggleImageZoom();
                    }
                }
            });
        });

        // Remove Profile Image
        function removeProfileImage() {
            if (confirm('Are you sure you want to remove your profile picture?')) {
                // Create a form to submit the removal request
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = 'remove_profile_image.php';
                
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Add animation to info items
        document.querySelectorAll('.info-item').forEach(item => {
            item.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-3px)';
                this.style.boxShadow = 'var(--shadow)';
            });
            
            item.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
                this.style.boxShadow = 'none';
            });
        });

        // Add ripple effect to buttons
        document.querySelectorAll('.btn').forEach(button => {
            button.addEventListener('click', function(e) {
                const ripple = document.createElement('span');
                const rect = this.getBoundingClientRect();
                const size = Math.max(rect.width, rect.height);
                const x = e.clientX - rect.left - size / 2;
                const y = e.clientY - rect.top - size / 2;
                
                ripple.style.width = ripple.style.height = size + 'px';
                ripple.style.left = x + 'px';
                ripple.style.top = y + 'px';
                ripple.classList.add('ripple');
                
                this.appendChild(ripple);
                
                setTimeout(() => {
                    ripple.remove();
                }, 600);
            });
        });
    </script>
</body>
</html>