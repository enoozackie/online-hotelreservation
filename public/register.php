<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';

use Lourdian\MonbelaHotel\Model\Guest;

$guest = new Guest();
$message = '';
$success = false;
$errors = [];

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    // Generate CSRF token if not exists
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    // CSRF validation
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $errors['general'] = "Security token mismatch. Please try again.";
    } else {
        // Generate reference number
        $refNo = 'REF' . date('YmdHis') . rand(100, 999);
        
        $data = [
            'REFNO' => $refNo,
            'G_FNAME' => trim($_POST['fname'] ?? ''),
            'G_LNAME' => trim($_POST['lname'] ?? ''),
            'G_CITY' => trim($_POST['city'] ?? ''),
            'G_ADDRESS' => trim($_POST['address'] ?? ''),
            'DBIRTH' => $_POST['birthday'] ?? '',
            'G_PHONE' => trim($_POST['phone'] ?? ''),
            'G_NATIONALITY' => trim($_POST['nationality'] ?? 'Filipino'),
            'G_COMPANY' => trim($_POST['company'] ?? ''),
            'G_CADDRESS' => trim($_POST['caddress'] ?? ''),
            'G_TERMS' => isset($_POST['terms']) ? 1 : 0,
            'G_UNAME' => trim($_POST['username'] ?? ''),
            'G_PASS' => $_POST['password'] ?? '',
            'ZIP' => trim($_POST['zip'] ?? ''),
            'LOCATION' => trim($_POST['location'] ?? '')
        ];

        // Enhanced validation
        if (empty($data['G_FNAME'])) {
            $errors['fname'] = "First name is required.";
        } elseif (!preg_match("/^[a-zA-Z\s]+$/", $data['G_FNAME'])) {
            $errors['fname'] = "First name should contain only letters and spaces.";
        }
        
        if (empty($data['G_LNAME'])) {
            $errors['lname'] = "Last name is required.";
        } elseif (!preg_match("/^[a-zA-Z\s]+$/", $data['G_LNAME'])) {
            $errors['lname'] = "Last name should contain only letters and spaces.";
        }
        
        if (empty($data['G_CITY'])) {
            $errors['city'] = "City is required.";
        }
        
        if (empty($data['G_ADDRESS'])) {
            $errors['address'] = "Address is required.";
        }
        
        if (empty($data['DBIRTH'])) {
            $errors['birthday'] = "Date of birth is required.";
        } else {
            $dob = new DateTime($data['DBIRTH']);
            $today = new DateTime();
            $age = $today->diff($dob)->y;
            if ($age < 18) {
                $errors['birthday'] = "You must be at least 18 years old to register.";
            }
        }
        
        if (empty($data['G_PHONE'])) {
            $errors['phone'] = "Phone number is required.";
        } elseif (!preg_match("/^[+]?[\d\s()\-]+$/", $data['G_PHONE'])) {
            $errors['phone'] = "Please enter a valid phone number.";
        }
        
        if (empty($data['G_UNAME'])) {
            $errors['username'] = "Username is required.";
        } elseif (strlen($data['G_UNAME']) < 4) {
            $errors['username'] = "Username must be at least 4 characters long.";
        } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $data['G_UNAME'])) {
            $errors['username'] = "Username can only contain letters, numbers, and underscores.";
        }
        
        if (empty($data['G_PASS'])) {
            $errors['password'] = "Password is required.";
        } elseif (strlen($data['G_PASS']) < 6) {
            $errors['password'] = "Password must be at least 6 characters long.";
        } elseif (!preg_match("/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/", $data['G_PASS'])) {
            $errors['password'] = "Password must contain at least one uppercase letter, one lowercase letter, and one number.";
        }
        
        if ($data['G_TERMS'] != 1) {
            $errors['terms'] = "You must agree to terms and conditions.";
        }
        
        if (empty($errors)) {
            try {
                // Check if username already exists
                if ($guest->usernameExists($data['G_UNAME'])) {
                    $errors['username'] = "Username '{$data['G_UNAME']}' is already taken. Please choose another.";
                } 
                // Check if phone already exists
                elseif ($guest->phoneExists($data['G_PHONE'])) {
                    $errors['phone'] = "Phone number '{$data['G_PHONE']}' is already registered.";
                } 
                // Try to register
                else {
                    if($guest->register($data)){
                        $message = "Registration successful! You can now login.";
                        $success = true;
                        // Clear form data on success
                        $_POST = [];
                        // Regenerate CSRF token
                        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    } else {
                        $errors['general'] = "Registration failed. Please try again. Error: " . $guest->getLastError();
                    }
                }
            } catch (Exception $e) {
                error_log("Registration error: " . $e->getMessage());
                $errors['general'] = "An error occurred: " . $e->getMessage();
            }
        }
    }
} else {
    // Generate CSRF token for GET requests
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Guest Registration - Monbela Hotel</title>
    
    <!-- Common CSS -->
    <style>
        :root {
            /* Unified Color System */
            --primary-gold: #D4AF37;
            --dark-gold: #B8941F;
            --deep-navy: #1a1f36;
            --soft-navy: #2d3561;
            --cream: #FAF7F0;
            --light-gray: #F5F5F5;
            --medium-gray: #6B7280;
            --white: #FFFFFF;
            --success: #10b981;
            --warning: #f59e0b;
            --danger: #ef4444;
            
            /* Unified Typography */
            --font-serif: 'Playfair Display', serif;
            --font-sans: 'Inter', sans-serif;
            
            /* Unified Effects */
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.1);
            --shadow-md: 0 4px 6px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 25px rgba(0, 0, 0, 0.15);
            --shadow-xl: 0 20px 50px rgba(0, 0, 0, 0.2);
            --radius-sm: 8px;
            --radius-md: 12px;
            --radius-lg: 20px;
            --radius-xl: 30px;
            --radius-full: 999px;
            
            /* Animations */
            --transition-fast: 0.2s ease;
            --transition-normal: 0.3s ease;
            --transition-slow: 0.5s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-sans);
            background: linear-gradient(135deg, var(--cream) 0%, var(--light-gray) 100%);
            min-height: 100vh;
            color: var(--deep-navy);
            line-height: 1.6;
            overflow-x: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Luxury Background Pattern */
        .luxury-bg {
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            z-index: -1;
            opacity: 0.05;
            background-image: 
                radial-gradient(circle at 20% 80%, var(--primary-gold) 0%, transparent 50%),
                radial-gradient(circle at 80% 20%, var(--soft-navy) 0%, transparent 50%),
                radial-gradient(circle at 40% 40%, var(--dark-gold) 0%, transparent 50%);
            animation: gradientFloat 20s ease infinite;
        }

        @keyframes gradientFloat {
            0%, 100% { transform: translate(0, 0) rotate(0deg); }
            25% { transform: translate(-10px, 10px) rotate(90deg); }
            50% { transform: translate(10px, -10px) rotate(180deg); }
            75% { transform: translate(-10px, -10px) rotate(270deg); }
        }

        /* Floating Elements */
        .floating-elements span {
            position: fixed;
            display: block;
            background: rgba(212, 175, 55, 0.1);
            border-radius: 50%;
            animation: float 25s linear infinite;
            z-index: -1;
        }

        .floating-elements span:nth-child(1) {
            width: 100px;
            height: 100px;
            top: 20%;
            left: 10%;
            animation-delay: 0s;
        }

        .floating-elements span:nth-child(2) {
            width: 150px;
            height: 150px;
            top: 60%;
            right: 15%;
            animation-delay: 5s;
        }

        .floating-elements span:nth-child(3) {
            width: 80px;
            height: 80px;
            bottom: 20%;
            left: 70%;
            animation-delay: 10s;
        }

        @keyframes float {
            0% { transform: translateY(0) rotate(0deg); opacity: 0; }
            10% { opacity: 0.3; }
            50% { opacity: 0.6; }
            90% { opacity: 0.3; }
            100% { transform: translateY(-100vh) rotate(360deg); opacity: 0; }
        }

        /* Unified Header/Navigation */
        .luxury-header {
            background: rgba(255, 255, 255, 0.98);
            backdrop-filter: blur(20px);
            position: sticky;
            top: 0;
            z-index: 1000;
            border-bottom: 1px solid rgba(212, 175, 55, 0.1);
            box-shadow: var(--shadow-sm);
            transition: all var(--transition-normal);
        }

        .luxury-header.scrolled {
            padding: 0.5rem 0;
            background: rgba(255, 255, 255, 0.95);
            box-shadow: var(--shadow-lg);
        }

        .luxury-nav {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem 2rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .luxury-brand {
            font-family: var(--font-serif);
            font-size: 2rem;
            font-weight: 700;
            color: var(--deep-navy);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: transform var(--transition-normal);
        }

        .luxury-brand:hover {
            transform: scale(1.05);
        }

        .luxury-brand i {
            color: var(--primary-gold);
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.1); opacity: 0.8; }
        }

        .luxury-nav-links {
            display: flex;
            list-style: none;
            gap: 2rem;
            align-items: center;
        }

        .luxury-nav-link {
            color: var(--deep-navy);
            text-decoration: none;
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: var(--radius-full);
            transition: all var(--transition-normal);
            position: relative;
            overflow: hidden;
        }

        .luxury-nav-link::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(212, 175, 55, 0.1), transparent);
            transition: left 0.6s ease;
        }

        .luxury-nav-link:hover::before {
            left: 100%;
        }

        .luxury-nav-link:hover {
            color: var(--primary-gold);
            background: rgba(212, 175, 55, 0.05);
        }

        .luxury-nav-link.active {
            color: var(--primary-gold);
            background: rgba(212, 175, 55, 0.1);
        }

        .luxury-cta {
            display: flex;
            gap: 1rem;
            align-items: center;
        }

        /* Unified Button System */
        .luxury-btn {
            padding: 0.875rem 2rem;
            border-radius: var(--radius-full);
            font-weight: 600;
            text-decoration: none;
            transition: all var(--transition-normal);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            border: none;
            cursor: pointer;
            font-size: 1rem;
            position: relative;
            overflow: hidden;
        }

        .luxury-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
            transition: left 0.6s ease;
        }

        .luxury-btn:hover::before {
            left: 100%;
        }

        .luxury-btn-primary {
            background: linear-gradient(135deg, var(--primary-gold) 0%, var(--dark-gold) 100%);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(212, 175, 55, 0.3);
        }

        .luxury-btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(212, 175, 55, 0.4);
        }

        .luxury-btn-secondary {
            background: transparent;
            color: var(--deep-navy);
            border: 2px solid var(--deep-navy);
        }

        .luxury-btn-secondary:hover {
            background: var(--deep-navy);
            color: var(--white);
            transform: translateY(-3px);
        }

        .luxury-btn-outline {
            background: transparent;
            color: var(--primary-gold);
            border: 2px solid var(--primary-gold);
        }

        .luxury-btn-outline:hover {
            background: var(--primary-gold);
            color: var(--white);
            transform: translateY(-3px);
        }

        /* Unified Container */
        .luxury-container {
            max-width: 1400px;
            margin: 2rem auto;
            padding: 0 2rem;
            flex: 1;
        }

        /* Unified Card Design */
        .luxury-card {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            border-radius: var(--radius-xl);
            box-shadow: var(--shadow-lg);
            border: 1px solid rgba(212, 175, 55, 0.1);
            transition: all var(--transition-normal);
            overflow: hidden;
            margin-bottom: 2rem;
        }

        .luxury-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--shadow-xl);
            border-color: rgba(212, 175, 55, 0.3);
        }

        /* Unified Form Elements */
        .luxury-form-group {
            margin-bottom: 1.5rem;
            position: relative;
        }

        .luxury-label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--deep-navy);
            font-weight: 600;
            font-size: 0.9rem;
        }

        .luxury-label .required {
            color: var(--danger);
        }

        .luxury-input {
            width: 100%;
            padding: 1rem 1.5rem;
            background: var(--light-gray);
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-full);
            font-size: 1rem;
            color: var(--deep-navy);
            transition: all var(--transition-normal);
        }

        .luxury-input:focus {
            outline: none;
            border-color: var(--primary-gold);
            background: var(--white);
            box-shadow: 0 0 0 4px rgba(212, 175, 55, 0.1);
        }

        .luxury-input.error {
            border-color: var(--danger);
        }

        .luxury-error {
            color: var(--danger);
            font-size: 0.85rem;
            margin-top: 0.25rem;
            display: none;
        }

        .luxury-form-group.error .luxury-error {
            display: block;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        /* Unified Alert System */
        .luxury-alert {
            padding: 1rem 1.5rem;
            border-radius: var(--radius-lg);
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            animation: slideIn 0.5s ease;
        }

        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-10px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .luxury-alert-success {
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .luxury-alert-error {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border-left: 4px solid var(--danger);
        }

        .luxury-alert-warning {
            background: rgba(245, 158, 11, 0.1);
            color: var(--warning);
            border-left: 4px solid var(--warning);
        }

        /* Card Header */
        .luxury-card-header {
            background: linear-gradient(135deg, var(--deep-navy) 0%, var(--soft-navy) 100%);
            color: var(--white);
            padding: 2.5rem;
            text-align: center;
            position: relative;
            overflow: hidden;
        }

        .luxury-card-header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0) 70%);
            animation: rotate 20s linear infinite;
        }

        @keyframes rotate {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }

        .luxury-card-header h1 {
            font-family: var(--font-serif);
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .luxury-card-header p {
            opacity: 0.9;
        }

        /* Card Body */
        .luxury-card-body {
            padding: 2.5rem;
        }

        /* Form Grid */
        .form-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 1.5rem;
        }

        .form-full-width {
            grid-column: span 2;
        }

        /* Progress Bar */
        .luxury-progress {
            height: 4px;
            background: var(--light-gray);
            border-radius: var(--radius-full);
            overflow: hidden;
            margin: 1.5rem 0;
        }

        .luxury-progress-bar {
            height: 100%;
            background: linear-gradient(90deg, var(--primary-gold), var(--dark-gold));
            transition: width 0.3s ease;
        }

        /* Progress Steps */
        .progress-steps {
            display: flex;
            justify-content: space-between;
            padding: 1rem 2rem;
            background: var(--light-gray);
            border-radius: var(--radius-lg);
            margin-bottom: 2rem;
        }

        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            flex: 1;
        }

        .step-icon {
            width: 30px;
            height: 30px;
            border-radius: 50%;
            background: var(--medium-gray);
            color: var(--white);
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-bottom: 0.5rem;
            transition: all var(--transition-normal);
        }

        .progress-step.active .step-icon {
            background: var(--primary-gold);
        }

        .progress-step.completed .step-icon {
            background: var(--success);
        }

        .step-label {
            font-size: 0.8rem;
            color: var(--medium-gray);
            text-align: center;
        }

        /* Password Container */
        .password-container {
            position: relative;
        }

        .password-toggle {
            position: absolute;
            right: 1.5rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--medium-gray);
            cursor: pointer;
            font-size: 1.1rem;
            padding: 0.25rem;
            transition: color var(--transition-normal);
        }

        .password-toggle:hover {
            color: var(--primary-gold);
        }

        /* Password Strength */
        .password-strength {
            height: 4px;
            margin-top: 0.5rem;
            border-radius: var(--radius-full);
            background: var(--light-gray);
            overflow: hidden;
        }

        .password-strength-meter {
            height: 100%;
            width: 0;
            transition: width 0.3s ease, background-color 0.3s ease;
        }

        .strength-weak {
            width: 33%;
            background-color: var(--danger);
        }

        .strength-medium {
            width: 66%;
            background-color: var(--warning);
        }

        .strength-strong {
            width: 100%;
            background-color: var(--success);
        }

        .password-strength-text {
            font-size: 0.8rem;
            margin-top: 0.25rem;
            color: var(--medium-gray);
        }

        /* Checkbox */
        .luxury-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin: 1.5rem 0;
        }

        .luxury-checkbox input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--primary-gold);
        }

        .luxury-checkbox label {
            color: var(--deep-navy);
            font-size: 0.9rem;
        }

        /* Tooltip */
        .tooltip {
            position: relative;
            display: inline-block;
            cursor: help;
        }

        .tooltip .tooltiptext {
            visibility: hidden;
            width: 200px;
            background-color: var(--deep-navy);
            color: var(--white);
            text-align: center;
            border-radius: var(--radius-sm);
            padding: 0.5rem;
            position: absolute;
            z-index: 1;
            bottom: 125%;
            left: 50%;
            margin-left: -100px;
            opacity: 0;
            transition: opacity 0.3s;
            font-size: 0.8rem;
        }

        .tooltip:hover .tooltiptext {
            visibility: visible;
            opacity: 1;
        }

        /* Footer */
        .luxury-footer {
            background: var(--deep-navy);
            color: var(--cream);
            padding: 3rem 0;
            margin-top: auto;
        }

        .luxury-footer-content {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 3rem;
        }

        .luxury-footer-section h3 {
            font-family: var(--font-serif);
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            color: var(--primary-gold);
        }

        .luxury-footer-links {
            list-style: none;
        }

        .luxury-footer-links li {
            margin-bottom: 0.75rem;
        }

        .luxury-footer-links a {
            color: var(--cream);
            text-decoration: none;
            transition: color var(--transition-normal);
            opacity: 0.8;
        }

        .luxury-footer-links a:hover {
            color: var(--primary-gold);
            opacity: 1;
        }

        .luxury-footer-bottom {
            text-align: center;
            padding-top: 2rem;
            margin-top: 2rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.9rem;
        }

        /* Social Icons */
        .luxury-social {
            display: flex;
            gap: 1rem;
            margin-top: 1rem;
        }

        .luxury-social a {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--cream);
            text-decoration: none;
            transition: all var(--transition-normal);
        }

        .luxury-social a:hover {
            background: var(--primary-gold);
            transform: translateY(-3px);
        }

        /* Loading Spinner */
        .luxury-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            border-top-color: white;
            animation: spin 1s ease-in-out infinite;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        /* Text Gradients */
        .luxury-text-gradient {
            background: linear-gradient(135deg, var(--primary-gold), var(--dark-gold));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* Link */
        .luxury-link {
            color: var(--primary-gold);
            text-decoration: none;
            font-weight: 600;
            transition: color var(--transition-normal);
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .luxury-link:hover {
            color: var(--dark-gold);
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .luxury-footer-content {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 768px) {
            .luxury-nav {
                padding: 1rem;
            }

            .luxury-brand {
                font-size: 1.5rem;
            }

            .luxury-nav-links {
                display: none;
            }

            .luxury-cta {
                flex-direction: column;
                gap: 0.5rem;
            }

            .luxury-btn {
                padding: 0.75rem 1.5rem;
                font-size: 0.9rem;
            }

            .luxury-container {
                padding: 1rem;
            }

            .luxury-footer-content {
                grid-template-columns: 1fr;
                gap: 2rem;
            }

            .luxury-card-header,
            .luxury-card-body {
                padding: 2rem;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-full-width {
                grid-column: span 1;
            }

            .progress-steps {
                padding: 0.5rem 1rem;
            }

            .step-label {
                font-size: 0.7rem;
            }
        }

        @media (max-width: 480px) {
            .luxury-brand {
                font-size: 1.25rem;
            }

            .luxury-btn {
                width: 100%;
                justify-content: center;
            }

            .luxury-card-header h1 {
                font-size: 1.75rem;
            }
        }

        /* Custom Scrollbar */
        ::-webkit-scrollbar {
            width: 10px;
        }

        ::-webkit-scrollbar-track {
            background: var(--light-gray);
        }

        ::-webkit-scrollbar-thumb {
            background: var(--primary-gold);
            border-radius: var(--radius-full);
        }

        ::-webkit-scrollbar-thumb:hover {
            background: var(--dark-gold);
        }

        /* Utility Classes */
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .mt-1 { margin-top: 0.5rem; }
        .mt-2 { margin-top: 1rem; }
        .mt-3 { margin-top: 1.5rem; }
        .mt-4 { margin-top: 2rem; }
        .mb-1 { margin-bottom: 0.5rem; }
        .mb-2 { margin-bottom: 1rem; }
        .mb-3 { margin-bottom: 1.5rem; }
        .mb-4 { margin-bottom: 2rem; }
        .p-1 { padding: 0.5rem; }
        .p-2 { padding: 1rem; }
        .p-3 { padding: 1.5rem; }
        .p-4 { padding: 2rem; }
        .hidden { display: none; }
        .flex { display: flex; }
        .flex-col { flex-direction: column; }
        .items-center { align-items: center; }
        .justify-center { justify-content: center; }
        .justify-between { justify-content: space-between; }
        .gap-1 { gap: 0.5rem; }
        .gap-2 { gap: 1rem; }
        .gap-3 { gap: 1.5rem; }
        .gap-4 { gap: 2rem; }
        .w-full { width: 100%; }
        .h-full { height: 100%; }
    </style>

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@400;500;600;700&family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <!-- Background Elements -->
    <div class="luxury-bg"></div>
    <div class="floating-elements">
        <span></span>
        <span></span>
        <span></span>
    </div>

    <!-- Header -->
    <header class="luxury-header" id="luxuryHeader">
        <nav class="luxury-nav">
            <a href="home.php" class="luxury-brand">
                <i class="fas fa-crown"></i>
                <span class="luxury-text-gradient">Monbela</span>
            </a>
            
            <ul class="luxury-nav-links">
                <li><a href="home.php" class="luxury-nav-link">Home</a></li>
                <li><a href="#rooms" class="luxury-nav-link">Suites</a></li>
                <li><a href="#amenities" class="luxury-nav-link">Amenities</a></li>
                <li><a href="#about" class="luxury-nav-link">Experience</a></li>
                <li><a href="login.php" class="luxury-nav-link">Login</a></li>
                <li><a href="register.php" class="luxury-nav-link active">Register</a></li>
            </ul>
            
            <div class="luxury-cta">
                <a href="login.php" class="luxury-btn luxury-btn-secondary">Book a Stay</a>
                <a href="register.php" class="luxury-btn luxury-btn-primary">Get Started</a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="luxury-container">
        <!-- Hero Section -->
        <div class="text-center mb-4">
            <h1 class="luxury-text-gradient" style="font-size: 2.5rem; font-family: var(--font-serif);">Create Your Account</h1>
            <p class="text-lg text-gray-600">Join Monbela Hotel for exclusive offers and seamless booking experience</p>
        </div>

        <div class="luxury-card">
            <!-- Progress Bar -->
            <div class="luxury-progress">
                <div class="luxury-progress-bar" id="progressBar" style="width: 0%"></div>
            </div>
            
            <!-- Progress Steps -->
            <div class="progress-steps">
                <div class="progress-step active" id="step1">
                    <div class="step-icon">1</div>
                    <div class="step-label">Personal Info</div>
                </div>
                <div class="progress-step" id="step2">
                    <div class="step-icon">2</div>
                    <div class="step-label">Contact Details</div>
                </div>
                <div class="progress-step" id="step3">
                    <div class="step-icon">3</div>
                    <div class="step-label">Account Setup</div>
                </div>
            </div>

            <!-- Card Header -->
            <div class="luxury-card-header">
                <h1>Guest Registration</h1>
                <p>Fill in your details to get started</p>
            </div>
            
            <!-- Card Body -->
            <div class="luxury-card-body">
                <?php if ($message): ?>
                    <div class="luxury-alert <?= $success ? 'luxury-alert-success' : 'luxury-alert-error' ?>">
                        <i class="fas <?= $success ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
                        <span><?= htmlspecialchars($message) ?></span>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors['general'])): ?>
                    <div class="luxury-alert luxury-alert-error">
                        <i class="fas fa-exclamation-circle"></i>
                        <span><?= htmlspecialchars($errors['general']) ?></span>
                    </div>
                <?php endif; ?>

                <form method="post" action="" id="registrationForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <div class="form-grid">
                        <!-- Personal Information Section -->
                        <div class="luxury-form-group">
                            <label for="fname" class="luxury-label">First Name <span class="required">*</span></label>
                            <input type="text" 
                                   id="fname" 
                                   name="fname" 
                                   class="luxury-input <?= isset($errors['fname']) ? 'error' : '' ?>" 
                                   placeholder="Enter your first name" 
                                   required 
                                   value="<?= htmlspecialchars($_POST['fname'] ?? '') ?>">
                            <div class="luxury-error"><?= $errors['fname'] ?? '' ?></div>
                        </div>
                        
                        <div class="luxury-form-group">
                            <label for="lname" class="luxury-label">Last Name <span class="required">*</span></label>
                            <input type="text" 
                                   id="lname" 
                                   name="lname" 
                                   class="luxury-input <?= isset($errors['lname']) ? 'error' : '' ?>" 
                                   placeholder="Enter your last name" 
                                   required 
                                   value="<?= htmlspecialchars($_POST['lname'] ?? '') ?>">
                            <div class="luxury-error"><?= $errors['lname'] ?? '' ?></div>
                        </div>
                        
                        <div class="luxury-form-group">
                            <label for="birthday" class="luxury-label">
                                Date of Birth <span class="required">*</span>
                                <span class="tooltip">
                                    <i class="fas fa-info-circle text-sm"></i>
                                    <span class="tooltiptext">You must be at least 18 years old to register</span>
                                </span>
                            </label>
                            <input type="date" 
                                   id="birthday" 
                                   name="birthday" 
                                   class="luxury-input <?= isset($errors['birthday']) ? 'error' : '' ?>" 
                                   required 
                                   value="<?= htmlspecialchars($_POST['birthday'] ?? '') ?>">
                            <div class="luxury-error"><?= $errors['birthday'] ?? '' ?></div>
                        </div>
                        
                        <div class="luxury-form-group">
                            <label for="nationality" class="luxury-label">Nationality</label>
                            <input type="text" 
                                   id="nationality" 
                                   name="nationality" 
                                   class="luxury-input" 
                                   placeholder="Filipino" 
                                   value="<?= htmlspecialchars($_POST['nationality'] ?? 'Filipino') ?>">
                        </div>
                        
                        <!-- Contact Information Section -->
                        <div class="luxury-form-group">
                            <label for="city" class="luxury-label">City <span class="required">*</span></label>
                            <input type="text" 
                                   id="city" 
                                   name="city" 
                                   class="luxury-input <?= isset($errors['city']) ? 'error' : '' ?>" 
                                   placeholder="Enter your city" 
                                   required 
                                   value="<?= htmlspecialchars($_POST['city'] ?? '') ?>">
                            <div class="luxury-error"><?= $errors['city'] ?? '' ?></div>
                        </div>
                        
                        <div class="luxury-form-group">
                            <label for="phone" class="luxury-label">Phone <span class="required">*</span></label>
                            <input type="tel" 
                                   id="phone" 
                                   name="phone" 
                                   class="luxury-input <?= isset($errors['phone']) ? 'error' : '' ?>" 
                                   placeholder="+639123456789" 
                                   required 
                                   value="<?= htmlspecialchars($_POST['phone'] ?? '') ?>">
                            <div class="luxury-error"><?= $errors['phone'] ?? '' ?></div>
                        </div>
                        
                        <div class="luxury-form-group form-full-width">
                            <label for="address" class="luxury-label">Address <span class="required">*</span></label>
                            <input type="text" 
                                   id="address" 
                                   name="address" 
                                   class="luxury-input <?= isset($errors['address']) ? 'error' : '' ?>" 
                                   placeholder="Enter your full address" 
                                   required 
                                   value="<?= htmlspecialchars($_POST['address'] ?? '') ?>">
                            <div class="luxury-error"><?= $errors['address'] ?? '' ?></div>
                        </div>
                        
                        <div class="luxury-form-group">
                            <label for="zip" class="luxury-label">ZIP Code</label>
                            <input type="text" 
                                   id="zip" 
                                   name="zip" 
                                   class="luxury-input" 
                                   placeholder="Enter ZIP code" 
                                   value="<?= htmlspecialchars($_POST['zip'] ?? '') ?>">
                        </div>
                        
                        <div class="luxury-form-group">
                            <label for="location" class="luxury-label">Location</label>
                            <input type="text" 
                                   id="location" 
                                   name="location" 
                                   class="luxury-input" 
                                   placeholder="Enter your location" 
                                   value="<?= htmlspecialchars($_POST['location'] ?? '') ?>">
                        </div>
                        
                        <!-- Work Information Section -->
                        <div class="luxury-form-group">
                            <label for="company" class="luxury-label">Company (Optional)</label>
                            <input type="text" 
                                   id="company" 
                                   name="company" 
                                   class="luxury-input" 
                                   placeholder="Enter company name" 
                                   value="<?= htmlspecialchars($_POST['company'] ?? '') ?>">
                        </div>
                        
                        <div class="luxury-form-group form-full-width">
                            <label for="caddress" class="luxury-label">Company Address (Optional)</label>
                            <input type="text" 
                                   id="caddress" 
                                   name="caddress" 
                                   class="luxury-input" 
                                   placeholder="Enter company address" 
                                   value="<?= htmlspecialchars($_POST['caddress'] ?? '') ?>">
                        </div>
                        
                        <!-- Account Setup Section -->
                        <div class="luxury-form-group">
                            <label for="username" class="luxury-label">
                                Username <span class="required">*</span>
                                <span class="tooltip">
                                    <i class="fas fa-info-circle text-sm"></i>
                                    <span class="tooltiptext">Username must be at least 4 characters and can only contain letters, numbers, and underscores</span>
                                </span>
                            </label>
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="luxury-input <?= isset($errors['username']) ? 'error' : '' ?>" 
                                   placeholder="Choose a username" 
                                   required 
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                            <div class="luxury-error"><?= $errors['username'] ?? '' ?></div>
                        </div>
                        
                        <div class="luxury-form-group">
                            <label for="password" class="luxury-label">
                                Password <span class="required">*</span>
                                <span class="tooltip">
                                    <i class="fas fa-info-circle text-sm"></i>
                                    <span class="tooltiptext">Password must contain at least one uppercase letter, one lowercase letter, and one number</span>
                                </span>
                            </label>
                            <div class="password-container">
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       class="luxury-input <?= isset($errors['password']) ? 'error' : '' ?>" 
                                       placeholder="Create a password (min 6 characters)" 
                                       required>
                                <button type="button" class="password-toggle" id="passwordToggle">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                            <div class="luxury-error"><?= $errors['password'] ?? '' ?></div>
                            <div class="password-strength">
                                <div class="password-strength-meter" id="passwordStrengthMeter"></div>
                            </div>
                            <div class="password-strength-text" id="passwordStrengthText"></div>
                        </div>
                        
                        <div class="luxury-form-group form-full-width">
                            <div class="luxury-checkbox">
                                <input type="checkbox" 
                                       id="terms" 
                                       name="terms" 
                                       required 
                                       <?= isset($_POST['terms']) && $_POST['terms'] ? 'checked' : '' ?>>
                                <label for="terms">I agree to terms and conditions <span class="required">*</span></label>
                            </div>
                            <div class="luxury-error"><?= $errors['terms'] ?? '' ?></div>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <button type="submit" class="luxury-btn luxury-btn-primary" id="registerBtn" style="min-width: 200px;">
                            <span id="btnText">Create Account</span>
                            <i class="fas fa-user-plus" id="btnIcon"></i>
                            <div class="luxury-spinner hidden" id="registerSpinner"></div>
                        </button>
                    </div>
                    
                    <div class="text-center mt-3">
                        <span class="text-sm text-gray-600">Already have an account?</span>
                        <a href="login.php" class="luxury-link text-sm ml-2">
                            <i class="fas fa-sign-in-alt"></i> Sign in here
                        </a>
                    </div>
                </form>
            </div>
        </div>
    </main>

    <!-- Footer -->
    <footer class="luxury-footer">
        <div class="luxury-footer-content">
            <div class="luxury-footer-section">
                <h3>Monbela Hotel</h3>
                <p>Where every stay becomes an unforgettable experience of luxury and comfort.</p>
                <div class="luxury-social">
                    <a href="#"><i class="fab fa-facebook-f"></i></a>
                    <a href="#"><i class="fab fa-twitter"></i></a>
                    <a href="#"><i class="fab fa-instagram"></i></a>
                    <a href="#"><i class="fab fa-linkedin-in"></i></a>
                </div>
            </div>
            
            <div class="luxury-footer-section">
                <h3>Quick Links</h3>
                <ul class="luxury-footer-links">
                    <li><a href="home.php">Home</a></li>
                    <li><a href="#rooms">Suites</a></li>
                    <li><a href="#amenities">Amenities</a></li>
                    <li><a href="#about">Experience</a></li>
                </ul>
            </div>
            
            <div class="luxury-footer-section">
                <h3>Services</h3>
                <ul class="luxury-footer-links">
                    <li><a href="#">Spa & Wellness</a></li>
                    <li><a href="#">Fine Dining</a></li>
                    <li><a href="#">Events & Meetings</a></li>
                    <li><a href="#">Concierge</a></li>
                </ul>
            </div>
            
            <div class="luxury-footer-section">
                <h3>Contact Info</h3>
                <ul class="luxury-footer-links">
                    <li><i class="fas fa-map-marker-alt"></i> 123 Luxury Avenue, City</li>
                    <li><i class="fas fa-phone"></i> +1 (234) 567-8900</li>
                    <li><i class="fas fa-envelope"></i> info@monbelahotel.com</li>
                </ul>
            </div>
        </div>
        
        <div class="luxury-footer-bottom">
            &copy; <?php echo date('Y'); ?> Monbela Hotel. All rights reserved. | Luxury Redefined.
        </div>
    </footer>

    <script>
        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.getElementById('luxuryHeader');
            if (window.scrollY > 50) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
        });

        // Password toggle functionality
        document.getElementById('passwordToggle').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = this.querySelector('i');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Password strength indicator
        const passwordStrengthMeter = document.getElementById('passwordStrengthMeter');
        const passwordStrengthText = document.getElementById('passwordStrengthText');
        
        document.getElementById('password').addEventListener('input', function() {
            const password = this.value;
            let strength = 0;
            
            if (password.length >= 6) strength += 1;
            if (password.length >= 8) strength += 1;
            if (password.match(/[a-z]+/)) strength += 1;
            if (password.match(/[A-Z]+/)) strength += 1;
            if (password.match(/[0-9]+/)) strength += 1;
            if (password.match(/[^a-zA-Z0-9]+/)) strength += 1;
            
            passwordStrengthMeter.className = 'password-strength-meter';
            passwordStrengthText.style.display = 'block';
            
            if (password.length > 0) {
                if (strength <= 2) {
                    passwordStrengthMeter.classList.add('strength-weak');
                    passwordStrengthText.textContent = 'Weak password';
                    passwordStrengthText.style.color = 'var(--danger)';
                } else if (strength <= 4) {
                    passwordStrengthMeter.classList.add('strength-medium');
                    passwordStrengthText.textContent = 'Medium password';
                    passwordStrengthText.style.color = 'var(--warning)';
                } else {
                    passwordStrengthMeter.classList.add('strength-strong');
                    passwordStrengthText.textContent = 'Strong password';
                    passwordStrengthText.style.color = 'var(--success)';
                }
            } else {
                passwordStrengthText.style.display = 'none';
            }
        });

        // Progress bar update
        const progressBar = document.getElementById('progressBar');
        const progressSteps = document.querySelectorAll('.progress-step');
        const formGroups = document.querySelectorAll('.luxury-form-group');
        
        function updateProgress() {
            let filledFields = 0;
            const totalFields = formGroups.length;
            
            formGroups.forEach(group => {
                const input = group.querySelector('input');
                if (input && input.value.trim() !== '') {
                    filledFields++;
                }
            });
            
            const progress = Math.round((filledFields / totalFields) * 100);
            progressBar.style.width = progress + '%';
            
            // Update progress steps
            progressSteps.forEach((step, index) => {
                step.classList.remove('active', 'completed');
                if (index < Math.floor(progress / 33.33)) {
                    step.classList.add('completed');
                } else if (index === Math.floor(progress / 33.33)) {
                    step.classList.add('active');
                }
            });
        }
        
        // Add input event listeners to all form fields
        formGroups.forEach(group => {
            const input = group.querySelector('input');
            if (input) {
                input.addEventListener('input', updateProgress);
            }
        });
        
        // Form submission handling
        document.getElementById('registrationForm').addEventListener('submit', function(e) {
            const registerBtn = document.getElementById('registerBtn');
            const btnText = document.getElementById('btnText');
            const btnIcon = document.getElementById('btnIcon');
            const spinner = document.getElementById('registerSpinner');
            
            // Show loading state
            registerBtn.disabled = true;
            btnText.textContent = 'Creating Account...';
            btnIcon.classList.add('hidden');
            spinner.classList.remove('hidden');
        });
        
        // Real-time validation
        const fnameInput = document.getElementById('fname');
        const lnameInput = document.getElementById('lname');
        const phoneInput = document.getElementById('phone');
        const usernameInput = document.getElementById('username');
        
        fnameInput.addEventListener('blur', function() {
            const value = this.value.trim();
            const formGroup = this.closest('.luxury-form-group');
            const error = formGroup.querySelector('.luxury-error');
            
            if (value === '') {
                formGroup.classList.add('error');
                error.textContent = 'First name is required.';
            } else if (!/^[a-zA-Z\s]+$/.test(value)) {
                formGroup.classList.add('error');
                error.textContent = 'First name should contain only letters and spaces.';
            } else {
                formGroup.classList.remove('error');
            }
        });
        
        lnameInput.addEventListener('blur', function() {
            const value = this.value.trim();
            const formGroup = this.closest('.luxury-form-group');
            const error = formGroup.querySelector('.luxury-error');
            
            if (value === '') {
                formGroup.classList.add('error');
                error.textContent = 'Last name is required.';
            } else if (!/^[a-zA-Z\s]+$/.test(value)) {
                formGroup.classList.add('error');
                error.textContent = 'Last name should contain only letters and spaces.';
            } else {
                formGroup.classList.remove('error');
            }
        });
        
        phoneInput.addEventListener('blur', function() {
            const value = this.value.trim();
            const formGroup = this.closest('.luxury-form-group');
            const error = formGroup.querySelector('.luxury-error');
            
            if (value === '') {
                formGroup.classList.add('error');
                error.textContent = 'Phone number is required.';
            } else if (!/^[+]?[\d\s-()]+$/.test(value)) {
                formGroup.classList.add('error');
                error.textContent = 'Please enter a valid phone number.';
            } else {
                formGroup.classList.remove('error');
            }
        });
        
        usernameInput.addEventListener('blur', function() {
            const value = this.value.trim();
            const formGroup = this.closest('.luxury-form-group');
            const error = formGroup.querySelector('.luxury-error');
            
            if (value === '') {
                formGroup.classList.add('error');
                error.textContent = 'Username is required.';
            } else if (value.length < 4) {
                formGroup.classList.add('error');
                error.textContent = 'Username must be at least 4 characters long.';
            } else if (!/^[a-zA-Z0-9_]+$/.test(value)) {
                formGroup.classList.add('error');
                error.textContent = 'Username can only contain letters, numbers, and underscores.';
            } else {
                formGroup.classList.remove('error');
            }
        });
        
        // Initialize progress on page load
        updateProgress();

        // Smooth scrolling for navigation links
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
    </script>
</body>
</html>