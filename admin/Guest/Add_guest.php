<?php 
// Enhanced Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');


// Initialize variables
$validationErrors = [];
$successMessage = '';
$formData = [];

// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Function to sanitize input
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}


// Function to validate phone
function isValidPhone($phone) {
    return preg_match('/^[0-9+\-\s()]+$/', $phone) && strlen($phone) >= 10;
}

// Function to validate password strength
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "At least 8 characters long";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "At least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "At least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "At least one number";
    }
    if (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
        $errors[] = "At least one special character";
    }
    
    return $errors;
}

if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['save'])) {
    
    // CSRF Token Validation
    if (!isset($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $validationErrors['csrf'] = "Invalid request. Please refresh and try again.";
    }
    
    // Collect and sanitize form data
    $formData = [
        'G_FNAME' => sanitizeInput($_POST['G_FNAME'] ?? ''),
        'G_LNAME' => sanitizeInput($_POST['G_LNAME'] ?? ''),
        'G_CITY' => sanitizeInput($_POST['G_CITY'] ?? ''),
        'G_ADDRESS' => sanitizeInput($_POST['G_ADDRESS'] ?? ''),
        'DBIRTH' => sanitizeInput($_POST['DBIRTH'] ?? ''),
        'G_PHONE' => sanitizeInput($_POST['G_PHONE'] ?? ''),
        'G_NATIONALITY' => sanitizeInput($_POST['G_NATIONALITY'] ?? ''),
        'G_UNAME' => sanitizeInput($_POST['G_UNAME'] ?? ''),
        'G_PASS' => $_POST['G_PASS'] ?? '',
        'G_PASS_CONFIRM' => $_POST['G_PASS_CONFIRM'] ?? ''
    ];
    
    // Comprehensive Validation
    if (empty($formData['G_FNAME']) || strlen($formData['G_FNAME']) < 2) {
        $validationErrors['fname'] = "First name must be at least 2 characters.";
    }
    
    if (empty($formData['G_LNAME']) || strlen($formData['G_LNAME']) < 2) {
        $validationErrors['lname'] = "Last name must be at least 2 characters.";
    }
   
    
    if (empty($formData['G_PHONE'])) {
        $validationErrors['phone'] = "Phone number is required.";
    } elseif (!isValidPhone($formData['G_PHONE'])) {
        $validationErrors['phone'] = "Please enter a valid phone number.";
    }
    
    if (empty($formData['G_UNAME']) || strlen($formData['G_UNAME']) < 4) {
        $validationErrors['username'] = "Username must be at least 4 characters.";
    } elseif (!preg_match('/^[a-zA-Z0-9_]+$/', $formData['G_UNAME'])) {
        $validationErrors['username'] = "Username can only contain letters, numbers, and underscores.";
    }
    
    // Date validation
    if (!empty($formData['DBIRTH'])) {
        $birthDate = strtotime($formData['DBIRTH']);
        $minAge = strtotime('-18 years');
        $maxAge = strtotime('-120 years');
        
        if ($birthDate === false) {
            $validationErrors['dbirth'] = "Invalid date format.";
        } elseif ($birthDate > $minAge) {
            $validationErrors['dbirth'] = "Guest must be at least 18 years old.";
        } elseif ($birthDate < $maxAge) {
            $validationErrors['dbirth'] = "Please enter a valid birth date.";
        }
    }
    
    // Password validation
    if (empty($formData['G_PASS'])) {
        $validationErrors['password'] = "Password is required.";
    } else {
        $passwordErrors = validatePasswordStrength($formData['G_PASS']);
        if (!empty($passwordErrors)) {
            $validationErrors['password'] = "Password must have: " . implode(", ", $passwordErrors);
        }
    }
    
    if ($formData['G_PASS'] !== $formData['G_PASS_CONFIRM']) {
        $validationErrors['confirm_password'] = "Passwords do not match.";
    }
    
    // Check for existing username/email
    if (empty($validationErrors)) {
        $admin = new Guest();
        
        // Check if username exists
        $existingUser = $admin->single_guest($formData['G_UNAME']);
        if ($existingUser) {
            $validationErrors['username'] = "Username already exists.";
        }
        
        // Check if email exists
        $existingEmail = $admin->findByEmail($formData['G_EMAIL']);
        if ($existingEmail) {
            $validationErrors['email'] = "Email already registered.";
        }
    }
    
    // If no validation errors, process registration
    if (empty($validationErrors)) {
        try {
            $guest = new Guest();
            
            // Remove confirm password before saving
            unset($formData['G_PASS_CONFIRM']);
            
            // Hash password
            $formData['G_PASS'] = password_hash($formData['G_PASS'], PASSWORD_DEFAULT);
            
            // Add additional fields
            $formData['STATUS'] = 'Active';
            $formData['CREATED_AT'] = date('Y-m-d H:i:s');
            
            // Create guest
            if ($guest->createGuest($formData)) {
                // Log the registration
                $log = [
                    'ACTION' => 'Guest Registration',
                    'DETAILS' => 'New guest registered: ' . $formData['G_UNAME'],
                    'CREATED_BY' => $_SESSION['ADMIN_UNAME'],
                    'CREATED_AT' => date('Y-m-d H:i:s')
                ];
                saveLog($log);
                
                $successMessage = "Guest registered successfully!";
                $formData = []; // Clear form data
                
                // Regenerate CSRF token
                $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            } else {
                $validationErrors['general'] = "Failed to register guest. Please try again.";
            }
        } catch (Exception $e) {
            error_log("Guest registration error: " . $e->getMessage());
            $validationErrors['general'] = "An error occurred. Please try again later.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Registration - Monbela Hotel</title>
    
    <!-- Enhanced Styles -->
    <style>
        :root {
            --primary-color: #2c3e50;
            --secondary-color: #3498db;
            --success-color: #27ae60;
            --danger-color: #e74c3c;
            --warning-color: #f39c12;
            --info-color: #3498db;
            --light-bg: #ecf0f1;
            --dark-text: #2c3e50;
            --border-color: #bdc3c7;
            --shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;
            background-color: var(--light-bg);
            color: var(--dark-text);
            line-height: 1.6;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .registration-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 30px;
            border-radius: 10px 10px 0 0;
            margin-bottom: 0;
            box-shadow: var(--shadow);
        }

        .registration-header h1 {
            font-size: 2.5rem;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .registration-header p {
            font-size: 1.1rem;
            opacity: 0.9;
        }

        .alert {
            padding: 15px 20px;
            margin-bottom: 20px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            gap: 10px;
            animation: slideIn 0.3s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(-10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .alert-success {
            background-color: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }

        .alert-danger {
            background-color: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }

        .registration-form {
            background: white;
            padding: 40px;
            border-radius: 0 0 10px 10px;
            box-shadow: var(--shadow);
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            margin-bottom: 30px;
        }

        .form-group {
            position: relative;
        }

        .form-group.full-width {
            grid-column: 1 / -1;
        }

        .form-control {
            width: 100%;
            padding: 15px;
            font-size: 16px;
            border: 2px solid var(--border-color);
            border-radius: 8px;
            background-color: #f8f9fa;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary-color);
            background-color: white;
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.1);
        }

        .form-control.error {
            border-color: var(--danger-color);
        }

        .floating-label {
            position: absolute;
            top: 50%;
            left: 15px;
            transform: translateY(-50%);
            background-color: white;
            padding: 0 5px;
            color: #7f8c8d;
            font-size: 16px;
            pointer-events: none;
            transition: var(--transition);
        }

        .form-control:focus ~ .floating-label,
        .form-control:not(:placeholder-shown) ~ .floating-label {
            top: -12px;
            font-size: 13px;
            color: var(--secondary-color);
        }

        .form-control.error ~ .floating-label {
            color: var(--danger-color);
        }

        .error-message {
            color: var(--danger-color);
            font-size: 13px;
            margin-top: 5px;
            display: flex;
            align-items: center;
            gap: 5px;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .password-wrapper {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #7f8c8d;
            transition: var(--transition);
        }

        .password-toggle:hover {
            color: var(--secondary-color);
        }

        .password-strength {
            margin-top: 10px;
        }

        .strength-meter {
            height: 5px;
            background-color: #ecf0f1;
            border-radius: 3px;
            overflow: hidden;
            margin-bottom: 5px;
        }

        .strength-meter-fill {
            height: 100%;
            transition: var(--transition);
            border-radius: 3px;
        }

        .strength-weak { background-color: var(--danger-color); width: 33%; }
        .strength-medium { background-color: var(--warning-color); width: 66%; }
        .strength-strong { background-color: var(--success-color); width: 100%; }

        .strength-text {
            font-size: 12px;
            color: #7f8c8d;
        }

        .btn {
            padding: 12px 30px;
            font-size: 16px;
            border: none;
            border-radius: 8px;
            cursor: pointer;
            transition: var(--transition);
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-weight: 500;
            text-decoration: none;
        }

        .btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
        }

        .btn-primary:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(52, 152, 219, 0.4);
        }

        .btn-secondary {
            background-color: #95a5a6;
            color: white;
        }

        .btn-secondary:hover:not(:disabled) {
            background-color: #7f8c8d;
            transform: translateY(-2px);
        }

        .form-actions {
            display: flex;
            gap: 15px;
            justify-content: flex-end;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 2px solid #ecf0f1;
        }

        .loading-spinner {
            width: 16px;
            height: 16px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: #333;
            color: #fff;
            text-align: center;
            border-radius: 6px;
            padding: 8px;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 12px;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        @media (max-width: 768px) {
            .container {
                padding: 10px;
            }
            
            .registration-form {
                padding: 20px;
            }
            
            .form-grid {
                grid-template-columns: 1fr;
            }
            
            .form-actions {
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
    <div class="container">
        <div class="registration-header">
            <h1>
                <svg width="40" height="40" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 9a3 3 0 100-6 3 3 0 000 6zm-7 9a7 7 0 1114 0H3z" clip-rule="evenodd"/>
                </svg>
                Guest Registration
            </h1>
            <p>Create a new guest account for Monbela Hotel</p>
        </div>

        <!-- Success Message -->
        <?php if ($successMessage): ?>
            <div class="alert alert-success">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                </svg>
                <?= htmlspecialchars($successMessage) ?>
            </div>
        <?php endif; ?>

        <!-- General Error Message -->
        <?php if (isset($validationErrors['general'])): ?>
            <div class="alert alert-danger">
                <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zM8.707 7.293a1 1 0 00-1.414 1.414L8.586 10l-1.293 1.293a1 1 0 101.414 1.414L10 11.414l1.293 1.293a1 1 0 001.414-1.414L11.414 10l1.293-1.293a1 1 0 00-1.414-1.414L10 8.586 8.707 7.293z" clip-rule="evenodd"/>
                </svg>
                <?= htmlspecialchars($validationErrors['general']) ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="" class="registration-form" id="registrationForm">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            
            <div class="form-grid">
                <!-- Personal Information Section -->
                <div class="form-group">
                    <input 
                        type="text" 
                        name="G_FNAME" 
                        id="fname" 
                        class="form-control <?= isset($validationErrors['fname']) ? 'error' : '' ?>"
                        placeholder=" "
                        value="<?= htmlspecialchars($formData['G_FNAME'] ?? '') ?>"
                        required
                    >
                    <label class="floating-label" for="fname">First Name *</label>
                    <?php if (isset($validationErrors['fname'])): ?>
                        <div class="error-message">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <?= htmlspecialchars($validationErrors['fname']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <input 
                        type="text" 
                        name="G_LNAME" 
                        id="lname" 
                        class="form-control <?= isset($validationErrors['lname']) ? 'error' : '' ?>"
                        placeholder=" "
                        value="<?= htmlspecialchars($formData['G_LNAME'] ?? '') ?>"
                        required
                    >
                    <label class="floating-label" for="lname">Last Name *</label>
                    <?php if (isset($validationErrors['lname'])): ?>
                        <div class="error-message">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <?= htmlspecialchars($validationErrors['lname']) ?>
                        </div>
                    <?php endif; ?>
                </div>

               

                <div class="form-group">
                    <input 
                        type="tel" 
                        name="G_PHONE" 
                        id="phone" 
                        class="form-control <?= isset($validationErrors['phone']) ? 'error' : '' ?>"
                        placeholder=" "
                        value="<?= htmlspecialchars($formData['G_PHONE'] ?? '') ?>"
                        required
                    >
                    <label class="floating-label" for="phone">Phone Number *</label>
                    <?php if (isset($validationErrors['phone'])): ?>
                        <div class="error-message">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <?= htmlspecialchars($validationErrors['phone']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <input 
                        type="date" 
                        name="DBIRTH" 
                        id="dbirth" 
                        class="form-control <?= isset($validationErrors['dbirth']) ? 'error' : '' ?>"
                        value="<?= htmlspecialchars($formData['DBIRTH'] ?? '') ?>"
                        max="<?= date('Y-m-d', strtotime('-18 years')) ?>"
                    >
                    <label class="floating-label" for="dbirth">Date of Birth</label>
                    <?php if (isset($validationErrors['dbirth'])): ?>
                        <div class="error-message">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <?= htmlspecialchars($validationErrors['dbirth']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <input 
                        type="text" 
                        name="G_NATIONALITY" 
                        id="nationality" 
                        class="form-control"
                        placeholder=" "
                        value="<?= htmlspecialchars($formData['G_NATIONALITY'] ?? '') ?>"
                    >
                    <label class="floating-label" for="nationality">Nationality</label>
                </div>

                <div class="form-group">
                    <input 
                        type="text" 
                        name="G_CITY" 
                        id="city" 
                        class="form-control"
                        placeholder=" "
                        value="<?= htmlspecialchars($formData['G_CITY'] ?? '') ?>"
                    >
                    <label class="floating-label" for="city">City</label>
                </div>

                <div class="form-group full-width">
                    <textarea 
                        name="G_ADDRESS" 
                        id="address" 
                        class="form-control"
                        placeholder=" "
                        rows="3"
                    ><?= htmlspecialchars($formData['G_ADDRESS'] ?? '') ?></textarea>
                    <label class="floating-label" for="address">Address</label>
                </div>

                <!-- Account Information Section -->
                <div class="form-group">
                    <input 
                        type="text" 
                        name="G_UNAME" 
                        id="username" 
                        class="form-control <?= isset($validationErrors['username']) ? 'error' : '' ?>"
                        placeholder=" "
                        value="<?= htmlspecialchars($formData['G_UNAME'] ?? '') ?>"
                        required
                        pattern="[a-zA-Z0-9_]{4,}"
                    >
                    <label class="floating-label" for="username">
                        Username * 
                        <span class="tooltip">
                            <svg width="14" height="14" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                            </svg>
                            <span class="tooltiptext">Username must be at least 4 characters and contain only letters, numbers, and underscores</span>
                        </span>
                    </label>
                    <?php if (isset($validationErrors['username'])): ?>
                        <div class="error-message">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <?= htmlspecialchars($validationErrors['username']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            name="G_PASS" 
                            id="password" 
                            class="form-control <?= isset($validationErrors['password']) ? 'error' : '' ?>"
                            placeholder=" "
                            required
                        >
                        <label class="floating-label" for="password">Password *</label>
                        <span class="password-toggle" onclick="togglePassword('password')">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" id="password-eye">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                    </div>
                    <div class="password-strength" id="passwordStrength" style="display: none;">
                        <div class="strength-meter">
                            <div class="strength-meter-fill" id="strengthMeterFill"></div>
                        </div>
                        <span class="strength-text" id="strengthText"></span>
                    </div>
                    <?php if (isset($validationErrors['password'])): ?>
                        <div class="error-message">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <?= htmlspecialchars($validationErrors['password']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="form-group">
                    <div class="password-wrapper">
                        <input 
                            type="password" 
                            name="G_PASS_CONFIRM" 
                            id="confirm_password" 
                            class="form-control <?= isset($validationErrors['confirm_password']) ? 'error' : '' ?>"
                            placeholder=" "
                            required
                        >
                        <label class="floating-label" for="confirm_password">Confirm Password *</label>
                        <span class="password-toggle" onclick="togglePassword('confirm_password')">
                            <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" id="confirm_password-eye">
                                <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                                <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                            </svg>
                        </span>
                    </div>
                    <div id="passwordMatch" style="display: none; margin-top: 5px;">
                        <span class="error-message" id="matchError" style="display: none;">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            Passwords do not match
                        </span>
                        <span style="color: var(--success-color); display: none;" id="matchSuccess">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                            </svg>
                            Passwords match
                        </span>
                    </div>
                    <?php if (isset($validationErrors['confirm_password'])): ?>
                        <div class="error-message">
                            <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                            </svg>
                            <?= htmlspecialchars($validationErrors['confirm_password']) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="window.history.back()">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd"/>
                    </svg>
                    Cancel
                </button>
                <button type="submit" name="save" class="btn btn-primary" id="submitBtn">
                    <svg width="20" height="20" fill="currentColor" viewBox="0 0 20 20" id="submitIcon">
                        <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                    </svg>
                    <span id="submitText">Register Guest</span>
                </button>
            </div>
        </form>
    </div>

    <!-- Enhanced JavaScript -->
    <script>
        // Password visibility toggle
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const eye = document.getElementById(fieldId + '-eye');
            
            if (field.type === 'password') {
                field.type = 'text';
                eye.innerHTML = `
                    <path fill-rule="evenodd" d="M3.707 2.293a1 1 0 00-1.414 1.414l14 14a1 1 0 001.414-1.414l-1.473-1.473A10.014 10.014 0 0019.542 10C18.268 5.943 14.478 3 10 3a9.958 9.958 0 00-4.512 1.074l-1.78-1.781zm4.261 4.26l1.514 1.515a2.003 2.003 0 012.45 2.45l1.514 1.514a4 4 0 00-5.478-5.478z" clip-rule="evenodd"/>
                    <path d="M12.454 16.697L9.75 13.992a4 4 0 01-3.742-3.741L2.335 6.578A9.98 9.98 0 00.458 10c1.274 4.057 5.065 7 9.542 7 .847 0 1.669-.105 2.454-.303z"/>
                `;
            } else {
                field.type = 'password';
                eye.innerHTML = `
                    <path d="M10 12a2 2 0 100-4 2 2 0 000 4z"/>
                    <path fill-rule="evenodd" d="M.458 10C1.732 5.943 5.522 3 10 3s8.268 2.943 9.542 7c-1.274 4.057-5.064 7-9.542 7S1.732 14.057.458 10zM14 10a4 4 0 11-8 0 4 4 0 018 0z" clip-rule="evenodd"/>
                `;
            }
        }

        // Password strength checker
        function checkPasswordStrength(password) {
            let strength = 0;
            const patterns = {
                length: password.length >= 8,
                uppercase: /[A-Z]/.test(password),
                lowercase: /[a-z]/.test(password),
                number: /[0-9]/.test(password),
                special: /[!@#$%^&*(),.?":{}|<>]/.test(password)
            };
            
            Object.values(patterns).forEach(passed => {
                if (passed) strength++;
            });
            
            return {
                score: strength,
                patterns: patterns
            };
        }

        // Real-time password strength indicator
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            const strengthDiv = document.getElementById('passwordStrength');
            const meterFill = document.getElementById('strengthMeterFill');
            const strengthText = document.getElementById('strengthText');
            
            if (password.length > 0) {
                strengthDiv.style.display = 'block';
                const strength = checkPasswordStrength(password);
                
                // Remove all classes
                meterFill.classList.remove('strength-weak', 'strength-medium', 'strength-strong');
                
                // Add appropriate class and text
                if (strength.score <= 2) {
                    meterFill.classList.add('strength-weak');
                    strengthText.textContent = 'Weak password';
                    strengthText.style.color = 'var(--danger-color)';
                } else if (strength.score <= 4) {
                    meterFill.classList.add('strength-medium');
                    strengthText.textContent = 'Medium strength';
                    strengthText.style.color = 'var(--warning-color)';
                } else {
                    meterFill.classList.add('strength-strong');
                    strengthText.textContent = 'Strong password';
                    strengthText.style.color = 'var(--success-color)';
                }
                
                // Show requirements not met
                const unmet = [];
                if (!strength.patterns.length) unmet.push('8+ characters');
                if (!strength.patterns.uppercase) unmet.push('uppercase');
                if (!strength.patterns.lowercase) unmet.push('lowercase');
                if (!strength.patterns.number) unmet.push('number');
                if (!strength.patterns.special) unmet.push('special character');
                
                if (unmet.length > 0) {
                    strengthText.textContent += ' (needs: ' + unmet.join(', ') + ')';
                }
            } else {
                strengthDiv.style.display = 'none';
            }
            
            // Check password match if confirm field has value
            const confirmPassword = document.getElementById('confirm_password').value;
            if (confirmPassword) {
                checkPasswordMatch();
            }
        });

        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('passwordMatch');
            const matchError = document.getElementById('matchError');
            const matchSuccess = document.getElementById('matchSuccess');
            
            if (confirmPassword.length > 0) {
                matchDiv.style.display = 'block';
                
                if (password === confirmPassword) {
                    matchError.style.display = 'none';
                    matchSuccess.style.display = 'inline-flex';
                    matchSuccess.style.alignItems = 'center';
                    matchSuccess.style.gap = '5px';
                } else {
                    matchError.style.display = 'inline-flex';
                    matchError.style.alignItems = 'center';
                    matchSuccess.style.display = 'none';
                }
            } else {
                matchDiv.style.display = 'none';
            }
        }

        document.getElementById('confirm_password').addEventListener('input', checkPasswordMatch);

        // Form validation before submission
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const submitBtn = document.getElementById('submitBtn');
            const submitIcon = document.getElementById('submitIcon');
            const submitText = document.getElementById('submitText');
            
            // Disable button and show loading
            submitBtn.disabled = true;
            submitIcon.innerHTML = '<div class="loading-spinner"></div>';
            submitText.textContent = 'Registering...';
            
            // Client-side validation
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            
            if (password !== confirmPassword) {
                e.preventDefault();
                submitBtn.disabled = false;
                submitIcon.innerHTML = `
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                `;
                submitText.textContent = 'Register Guest';
                
                // Focus on confirm password field
                document.getElementById('confirm_password').focus();
                checkPasswordMatch();
                return;
            }
            
            // Check password strength
            const strength = checkPasswordStrength(password);
            if (strength.score < 3) {
                e.preventDefault();
                submitBtn.disabled = false;
                submitIcon.innerHTML = `
                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                `;
                submitText.textContent = 'Register Guest';
                
                // Focus on password field
                document.getElementById('password').focus();
                return;
            }
        });

        // Real-time email validation
        document.getElementById('email').addEventListener('blur', function() {
            const email = this.value;
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            
            if (email && !emailRegex.test(email)) {
                this.classList.add('error');
                // Show error message
                let errorDiv = this.nextElementSibling.nextElementSibling;
                if (!errorDiv || !errorDiv.classList.contains('error-message')) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'error-message';
                    errorDiv.innerHTML = `
                        <svg width="16" height="16" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                        </svg>
                        Please enter a valid email address
                    `;
                    this.parentNode.appendChild(errorDiv);
                }
            } else {
                this.classList.remove('error');
                // Remove error message if exists
                const errorDiv = this.parentNode.querySelector('.error-message');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            // Allow only numbers, +, -, spaces, and parentheses
            let value = e.target.value.replace(/[^\d+\-\s()]/g, '');
            e.target.value = value;
        });

        // Username validation
        document.getElementById('username').addEventListener('input', function(e) {
            const value = e.target.value;
            const validUsername = /^[a-zA-Z0-9_]*$/.test(value);
            
            if (!validUsername && value.length > 0) {
                e.target.value = value.replace(/[^a-zA-Z0-9_]/g, '');
            }
        });

        // Auto-save form data to localStorage
        const formInputs = document.querySelectorAll('.form-control');
        
        // Load saved data on page load
        window.addEventListener('load', function() {
            formInputs.forEach(input => {
                if (input.type !== 'password' && input.name !== 'csrf_token') {
                    const savedValue = localStorage.getItem('guest_reg_' + input.name);
                    if (savedValue && !input.value) {
                        input.value = savedValue;
                        // Trigger floating label
                        if (savedValue) {
                            input.classList.add('has-value');
                        }
                    }
                }
            });
        });

        // Save data as user types (except passwords)
        formInputs.forEach(input => {
            if (input.type !== 'password' && input.name !== 'csrf_token') {
                input.addEventListener('input', function() {
                    localStorage.setItem('guest_reg_' + this.name, this.value);
                });
            }
        });

        // Clear saved data on successful submission
        <?php if ($successMessage): ?>
        localStorage.clear();
        <?php endif; ?>

        // Add animation to form groups on focus
        formInputs.forEach(input => {
            input.addEventListener('focus', function() {
                this.parentElement.style.transform = 'scale(1.02)';
            });
            
            input.addEventListener('blur', function() {
                this.parentElement.style.transform = 'scale(1)';
            });
        });
    </script>
</body>
</html>