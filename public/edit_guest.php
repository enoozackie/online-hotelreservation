<?php
// =============================================================================
// public/edit_guest.php - Enhanced Edit Profile Page
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

// Restrict Access
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'guest') {
    header("Location: login.php");
    exit;
}

// Initialize variables
 $guest = new Guest();
 $errors = [];
 $success = false;

// Fetch current guest data
 $currentGuest = $guest->getById($_SESSION['id']);
if (!$currentGuest) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $data = [
        'G_FNAME' => trim($_POST['G_FNAME'] ?? ''),
        'G_LNAME' => trim($_POST['G_LNAME'] ?? ''),
        'G_UNAME' => trim($_POST['G_UNAME'] ?? ''),
        'G_CITY' => trim($_POST['G_CITY'] ?? ''),
        'G_ADDRESS' => trim($_POST['G_ADDRESS'] ?? ''),
        'DBIRTH' => $_POST['DBIRTH'] ?? '',
        'G_PHONE' => trim($_POST['G_PHONE'] ?? ''),
        'G_NATIONALITY' => trim($_POST['G_NATIONALITY'] ?? ''),
        'G_COMPANY' => trim($_POST['G_COMPANY'] ?? ''),
        'G_CADDRESS' => trim($_POST['G_CADDRESS'] ?? ''),
        'ZIP' => trim($_POST['ZIP'] ?? ''),
        'LOCATION' => trim($_POST['LOCATION'] ?? '')
    ];

    // Basic validation
    if (empty($data['G_FNAME'])) $errors['G_FNAME'] = 'First name is required';
    if (empty($data['G_LNAME'])) $errors['G_LNAME'] = 'Last name is required';
    if (empty($data['G_UNAME'])) $errors['G_UNAME'] = 'Username is required';
    if (empty($data['G_PHONE'])) $errors['G_PHONE'] = 'Phone number is required';

    // If no errors, update the profile
    if (empty($errors)) {
        try {
            $result = $guest->update($_SESSION['id'], $data);
            if ($result) {
                $_SESSION['success_message'] = 'Profile updated successfully!';
                header("Location: guest_profile.php");
                exit;
            } else {
                $errors['general'] = 'Failed to update profile. Please try again.';
            }
        } catch (Exception $e) {
            $errors['general'] = 'An error occurred: ' . $e->getMessage();
        }
    }

    // If there are errors, keep the posted data
    $currentGuest = array_merge($currentGuest, $data);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Profile - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root { 
            --primary: #8b5cf6; 
            --primary-dark: #7c3aed; 
            --secondary: #ec4899; 
            --danger: #ef4444; 
            --light: #faf5ff; 
            --dark: #4c1d95; 
            --gray: #6b7280; 
            --border: #e5e7eb; 
            --shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1); 
            --shadow-lg: 0 20px 25px -5px rgba(0, 0, 0, 0.1); 
        }

        * { margin: 0; padding: 0; box-sizing: border-box; }

        body { 
            font-family: 'Poppins', sans-serif; 
            background: linear-gradient(135deg, #faf5ff 0%, #ede9fe 100%); 
            min-height: 100vh; 
            color: var(--dark); 
        }

        /* Header */
        .header { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); 
            color: white; 
            padding: 1.5rem 0; 
            box-shadow: var(--shadow); 
            position: sticky; 
            top: 0; 
            z-index: 1000; 
        }

        .header .container { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            max-width: 1200px; 
            margin: 0 auto; 
            padding: 0 1rem; 
        }

        .brand { 
            display: flex; 
            align-items: center; 
            font-size: 1.5rem; 
            font-weight: 700; 
            text-decoration: none; 
            color: white; 
        }

        .brand i { 
            margin-right: 0.75rem; 
            font-size: 2rem; 
        }

        .nav-links { 
            display: flex; 
            list-style: none; 
            gap: 2rem; 
            margin: 0; 
            padding: 0; 
        }

        .nav-links a { 
            color: white; 
            text-decoration: none; 
            font-weight: 500; 
            transition: all 0.3s ease; 
            position: relative; 
        }

        .nav-links a::after {
            content: '';
            position: absolute;
            bottom: -5px;
            left: 0;
            width: 0;
            height: 2px;
            background: var(--secondary);
            transition: width 0.3s ease;
        }

        .nav-links a:hover::after {
            width: 100%;
        }

        .nav-links a:hover { 
            color: var(--secondary); 
        }

        .nav-links a.active { 
            color: var(--secondary); 
        }

        .nav-links a.active::after { 
            width: 100%; 
        }

        .mobile-toggle { 
            display: none; 
            background: none; 
            border: none; 
            color: white; 
            font-size: 1.5rem; 
            cursor: pointer; 
        }

        /* Main Container */
        .container { 
            max-width: 1200px; 
            margin: 2rem auto; 
            padding: 0 1rem; 
        }

        /* Breadcrumb */
        .breadcrumb { 
            background: white; 
            padding: 1rem 1.5rem; 
            border-radius: 12px; 
            box-shadow: var(--shadow); 
            margin-bottom: 2rem; 
            display: flex; 
            align-items: center; 
        }

        .breadcrumb a { 
            color: var(--gray); 
            text-decoration: none; 
            transition: color 0.3s ease; 
        }

        .breadcrumb a:hover { 
            color: var(--primary); 
        }

        .breadcrumb .separator { 
            margin: 0 0.75rem; 
            color: var(--gray); 
        }

        .breadcrumb .current { 
            color: var(--primary); 
            font-weight: 600; 
        }

        /* Progress Indicator */
        .progress-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 3rem;
            position: relative;
        }

        .progress-container::before {
            content: '';
            position: absolute;
            top: 25px;
            left: 0;
            right: 0;
            height: 2px;
            background: var(--border);
            z-index: 0;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 1;
            flex: 1;
        }

        .step-number {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: white;
            border: 2px solid var(--border);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            color: var(--gray);
            transition: all 0.3s ease;
            margin-bottom: 0.5rem;
        }

        .progress-step.active .step-number {
            background: var(--primary);
            border-color: var(--primary);
            color: white;
            transform: scale(1.1);
        }

        .progress-step.completed .step-number {
            background: var(--secondary);
            border-color: var(--secondary);
            color: white;
        }

        .step-title {
            font-size: 0.875rem;
            color: var(--gray);
            text-align: center;
        }

        .progress-step.active .step-title {
            color: var(--primary);
            font-weight: 600;
        }

        .progress-step.completed .step-title {
            color: var(--secondary);
            font-weight: 600;
        }

        /* Card Styles */
        .card { 
            background: white; 
            border-radius: 16px; 
            box-shadow: var(--shadow-lg); 
            overflow: hidden; 
            margin-bottom: 2rem; 
            border: none; 
            animation: fadeInUp 0.5s ease; 
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

        .card-header { 
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); 
            color: white; 
            padding: 1.5rem 2rem; 
            border: none; 
        }

        .card-header h2 { 
            margin: 0; 
            font-weight: 600; 
            display: flex; 
            align-items: center; 
        }

        .card-header h2 i { 
            margin-right: 0.75rem; 
            font-size: 1.5rem; 
        }

        .card-body { 
            padding: 2rem; 
        }

        /* Form Styles */
        .form-section {
            margin-bottom: 2.5rem;
        }

        .form-section:last-child {
            margin-bottom: 0;
        }

        .section-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: var(--primary);
            margin-bottom: 1.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 2px solid var(--border);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-label {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .form-label i {
            color: var(--primary);
        }

        .form-control {
            border: 2px solid var(--border);
            border-radius: 10px;
            padding: 0.875rem 1rem;
            font-size: 1rem;
            transition: all 0.3s ease;
        }

        .form-control:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
            outline: none;
            transform: translateY(-2px);
        }

        .form-control.is-invalid {
            border-color: var(--danger);
        }

        .invalid-feedback {
            color: var(--danger);
            font-size: 0.875rem;
            margin-top: 0.5rem;
            display: flex;
            align-items: center;
        }

        .invalid-feedback i {
            margin-right: 0.25rem;
        }

        /* Button Styles */
        .btn { 
            padding: 0.875rem 2rem; 
            border-radius: 10px; 
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
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); 
            color: white; 
            box-shadow: 0 4px 6px rgba(139, 92, 246, 0.25); 
        }

        .btn-primary:hover { 
            transform: translateY(-2px); 
            box-shadow: 0 6px 12px rgba(139, 92, 246, 0.35); 
            color: white; 
        }

        .btn-secondary { 
            background: white; 
            color: var(--gray); 
            border: 2px solid var(--border); 
        }

        .btn-secondary:hover { 
            background: var(--light); 
            border-color: var(--primary); 
            color: var(--primary); 
        }

        .btn-group { 
            display: flex; 
            gap: 1rem; 
            justify-content: flex-end; 
            margin-top: 2rem; 
            padding-top: 2rem; 
            border-top: 2px solid var(--border); 
        }

        /* Alert Styles */
        .alert { 
            border-radius: 12px; 
            border: none; 
            padding: 1rem 1.5rem; 
            margin-bottom: 1.5rem; 
            display: flex; 
            align-items: center; 
        }

        .alert-danger { 
            background: linear-gradient(135deg, #fef2f2 0%, #fee2e2 100%); 
            color: var(--danger); 
            border-left: 4px solid var(--danger); 
        }

        .alert i { 
            margin-right: 0.75rem; 
            font-size: 1.25rem; 
        }

        /* Responsive Design */
        @media (max-width: 768px) { 
            .nav-links { 
                display: none; 
                position: absolute; 
                top: 100%; 
                left: 0; 
                right: 0; 
                background: var(--primary-dark); 
                flex-direction: column; 
                padding: 1rem 0; 
                box-shadow: var(--shadow); 
            } 
            
            .nav-links.active { 
                display: flex; 
            } 
            
            .mobile-toggle { 
                display: block; 
            } 
            
            .form-grid { 
                grid-template-columns: 1fr; 
            }
            
            .btn-group { 
                flex-direction: column; 
            } 
            
            .btn { 
                width: 100%; 
                justify-content: center; 
            }
        }

        /* Animation for form elements */
        .form-section { 
            animation: fadeIn 0.5s ease forwards; 
            opacity: 0; 
        }

        .form-section:nth-child(1) { animation-delay: 0.1s; }
        .form-section:nth-child(2) { animation-delay: 0.2s; }
        .form-section:nth-child(3) { animation-delay: 0.3s; }

        @keyframes fadeIn { 
            to { 
                opacity: 1; 
            } 
        }

        /* Tooltip Styles */
        .tooltip-icon {
            display: inline-block;
            margin-left: 0.5rem;
            color: var(--gray);
            cursor: help;
            font-size: 0.875rem;
        }

        .tooltip-icon:hover {
            color: var(--primary);
        }

        /* Loading state */
        .loading { 
            position: relative; 
        }

        .loading::after { 
            content: ''; 
            position: absolute; 
            top: 50%; 
            left: 50%; 
            width: 20px; 
            height: 20px; 
            margin: -10px 0 0 -10px; 
            border: 2px solid transparent; 
            border-top: 2px solid white; 
            border-radius: 50%; 
            animation: spin 1s linear infinite; 
        }

        @keyframes spin { 
            0% { transform: rotate(0deg); } 
            100% { transform: rotate(360deg); } 
        }
    </style>
</head>
<body>
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
                <li><a href="homepage.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li><a href="booking.php"><i class="bi bi-calendar-plus"></i> Book Now</a></li>
                <li><a href="guest_profile.php" class="active"><i class="bi bi-person-circle"></i> Profile</a></li>
                <li><a href="my_reservations.php"><i class="bi bi-calendar-check"></i> My Reservations</a></li>
                <li><a href="guest_profile.php?logout=1"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="homepage.php">Home</a>
            <span class="separator">/</span>
            <a href="guest_profile.php">My Profile</a>
            <span class="separator">/</span>
            <span class="current">Edit Profile</span>
        </div>

        <!-- Progress Indicator -->
        <div class="progress-container">
            <div class="progress-step completed">
                <div class="step-number"><i class="bi bi-check"></i></div>
                <div class="step-title">View Profile</div>
            </div>
            <div class="progress-step active">
                <div class="step-number">2</div>
                <div class="step-title">Edit Details</div>
            </div>
            <div class="progress-step">
                <div class="step-number">3</div>
                <div class="step-title">Save Changes</div>
            </div>
        </div>

        <!-- Edit Form Card -->
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-pencil-square"></i> Edit Personal Information</h2>
            </div>
            <div class="card-body">
                <?php if (!empty($errors['general'])): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?= htmlspecialchars($errors['general']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="">
                    <!-- Personal Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="bi bi-person-badge"></i>
                            Personal Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="G_FNAME" class="form-label">
                                    <i class="bi bi-person"></i>
                                    First Name
                                </label>
                                <input type="text" class="form-control <?= !empty($errors['G_FNAME']) ? 'is-invalid' : '' ?>" 
                                       id="G_FNAME" name="G_FNAME" value="<?= htmlspecialchars($currentGuest['G_FNAME'] ?? '') ?>" required>
                                <?php if (!empty($errors['G_FNAME'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-x-circle-fill"></i>
                                        <?= htmlspecialchars($errors['G_FNAME']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="G_LNAME" class="form-label">
                                    <i class="bi bi-person"></i>
                                    Last Name
                                </label>
                                <input type="text" class="form-control <?= !empty($errors['G_LNAME']) ? 'is-invalid' : '' ?>" 
                                       id="G_LNAME" name="G_LNAME" value="<?= htmlspecialchars($currentGuest['G_LNAME'] ?? '') ?>" required>
                                <?php if (!empty($errors['G_LNAME'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-x-circle-fill"></i>
                                        <?= htmlspecialchars($errors['G_LNAME']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="G_UNAME" class="form-label">
                                    <i class="bi bi-person-badge"></i>
                                    Username
                                </label>
                                <input type="text" class="form-control <?= !empty($errors['G_UNAME']) ? 'is-invalid' : '' ?>" 
                                       id="G_UNAME" name="G_UNAME" value="<?= htmlspecialchars($currentGuest['G_UNAME'] ?? '') ?>" required>
                                <?php if (!empty($errors['G_UNAME'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-x-circle-fill"></i>
                                        <?= htmlspecialchars($errors['G_UNAME']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="DBIRTH" class="form-label">
                                    <i class="bi bi-calendar-event"></i>
                                    Date of Birth
                                </label>
                                <input type="date" class="form-control" 
                                       id="DBIRTH" name="DBIRTH" value="<?= htmlspecialchars($currentGuest['DBIRTH'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="G_PHONE" class="form-label">
                                    <i class="bi bi-telephone"></i>
                                    Phone Number
                                </label>
                                <input type="tel" class="form-control <?= !empty($errors['G_PHONE']) ? 'is-invalid' : '' ?>" 
                                       id="G_PHONE" name="G_PHONE" value="<?= htmlspecialchars($currentGuest['G_PHONE'] ?? '') ?>" required>
                                <?php if (!empty($errors['G_PHONE'])): ?>
                                    <div class="invalid-feedback">
                                        <i class="bi bi-x-circle-fill"></i>
                                        <?= htmlspecialchars($errors['G_PHONE']) ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                            <div class="form-group">
                                <label for="G_NATIONALITY" class="form-label">
                                    <i class="bi bi-flag"></i>
                                    Nationality
                                </label>
                                <input type="text" class="form-control" 
                                       id="G_NATIONALITY" name="G_NATIONALITY" value="<?= htmlspecialchars($currentGuest['G_NATIONALITY'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Location Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="bi bi-geo-alt"></i>
                            Location Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group full-width">
                                <label for="G_ADDRESS" class="form-label">
                                    <i class="bi bi-house"></i>
                                    Address
                                </label>
                                <input type="text" class="form-control" 
                                       id="G_ADDRESS" name="G_ADDRESS" value="<?= htmlspecialchars($currentGuest['G_ADDRESS'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="G_CITY" class="form-label">
                                    <i class="bi bi-buildings"></i>
                                    City
                                </label>
                                <input type="text" class="form-control" 
                                       id="G_CITY" name="G_CITY" value="<?= htmlspecialchars($currentGuest['G_CITY'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="ZIP" class="form-label">
                                    <i class="bi bi-mailbox"></i>
                                    ZIP Code
                                </label>
                                <input type="text" class="form-control" 
                                       id="ZIP" name="ZIP" value="<?= htmlspecialchars($currentGuest['ZIP'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="LOCATION" class="form-label">
                                    <i class="bi bi-geo"></i>
                                    Location
                                </label>
                                <input type="text" class="form-control" 
                                       id="LOCATION" name="LOCATION" value="<?= htmlspecialchars($currentGuest['LOCATION'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Company Information Section -->
                    <div class="form-section">
                        <h3 class="section-title">
                            <i class="bi bi-building"></i>
                            Company Information
                        </h3>
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="G_COMPANY" class="form-label">
                                    <i class="bi bi-briefcase"></i>
                                    Company
                                </label>
                                <input type="text" class="form-control" 
                                       id="G_COMPANY" name="G_COMPANY" value="<?= htmlspecialchars($currentGuest['G_COMPANY'] ?? '') ?>">
                            </div>
                            <div class="form-group">
                                <label for="G_CADDRESS" class="form-label">
                                    <i class="bi bi-building"></i>
                                    Company Address
                                </label>
                                <input type="text" class="form-control" 
                                       id="G_CADDRESS" name="G_CADDRESS" value="<?= htmlspecialchars($currentGuest['G_CADDRESS'] ?? '') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Form Actions -->
                    <div class="btn-group">
                        <a href="guest_profile.php" class="btn btn-secondary">
                            <i class="bi bi-x-circle"></i> Cancel
                        </a>
                        <button type="submit" class="btn btn-primary">
                            <i class="bi bi-check-circle-fill"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        function toggleMenu() {
            document.getElementById('navLinks').classList.toggle('active');
        }

        // Add loading state to form submission
        document.querySelector('form').addEventListener('submit', function() {
            const submitBtn = this.querySelector('button[type="submit"]');
            submitBtn.classList.add('loading');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span></span> Saving...';
        });

        // Form field animations
        document.querySelectorAll('.form-control').forEach(field => {
            field.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            field.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>