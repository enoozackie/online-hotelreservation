<?php
session_start();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require __DIR__ . '/../vendor/autoload.php';

use Lourdian\MonbelaHotel\Model\Auth;

// Initialize variables
$auth = new Auth();
$error = '';
$success = '';

// Redirect if already logged in
if (isset($_SESSION['id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: ../admin/admin_dashboard.php");
    } else {
        header("Location: booking.php");
    }
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        // Rate limiting
        $maxAttempts = 5;
        $timeWindow = 300; // 5 minutes
        $attemptKey = 'login_attempts_' . $_SERVER['REMOTE_ADDR'];
        
        if (!isset($_SESSION[$attemptKey])) {
            $_SESSION[$attemptKey] = [];
        }
        
        // Clean old attempts
        $_SESSION[$attemptKey] = array_filter($_SESSION[$attemptKey], function($timestamp) use ($timeWindow) {
            return (time() - $timestamp) < $timeWindow;
        });
        
        // Check if rate limit exceeded
        if (count($_SESSION[$attemptKey]) >= $maxAttempts) {
            $error = "Too many failed attempts. Please try again in 5 minutes.";
        } else {
            $username = trim($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';
            $remember = isset($_POST['remember']) ? true : false;

            if (empty($username) || empty($password)) {
                $error = "Please enter both username and password.";
            } else {
                // Attempt login
                $user = $auth->login($username, $password);
                
                if ($user) {
                    // Clear failed attempts on successful login
                    unset($_SESSION[$attemptKey]);
                    
                    // Set session variables
                    $_SESSION['id'] = $user['id'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['fullname'] = $user['fullname'] ?? '';
                    
                    // Regenerate session ID for security
                    session_regenerate_id(true);
                    
                    // Regenerate CSRF token
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    
                    // Set remember me cookie if checked
                    if ($remember) {
                        $token = bin2hex(random_bytes(16));
                        setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 days
                        // In a real application, you would store this token in the database
                    }
                    
                    // Redirect based on role
                    if ($user['role'] === 'admin') {
                        header("Location: ../admin/admin_dashboard.php");
                    } else {
                        header("Location: booking.php");
                    }
                    exit;
                } else {
                    // Record failed attempt
                    $_SESSION[$attemptKey][] = time();
                    $error = "Invalid username or password.";
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Monbela Hotel</title>
    
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
            margin: 0 auto;
            padding: 2rem;
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
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
            width: 100%;
            max-width: 450px;
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

        .luxury-card-header i {
            font-size: 3rem;
            margin-bottom: 1rem;
            display: block;
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

        /* Checkbox */
        .luxury-checkbox {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 1.5rem;
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

        /* Divider */
        .luxury-divider {
            text-align: center;
            margin: 1.5rem 0;
            position: relative;
            color: var(--medium-gray);
            font-size: 0.9rem;
        }

        .luxury-divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: var(--light-gray);
        }

        .luxury-divider span {
            background: var(--white);
            padding: 0 1rem;
            position: relative;
        }

        /* Social Buttons */
        .luxury-social-btn {
            width: 100%;
            padding: 0.875rem;
            border: 2px solid var(--light-gray);
            border-radius: var(--radius-full);
            background: var(--white);
            color: var(--deep-navy);
            font-weight: 600;
            cursor: pointer;
            transition: all var(--transition-normal);
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .luxury-social-btn:hover {
            transform: translateY(-2px);
            border-color: var(--primary-gold);
            color: var(--primary-gold);
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
                <li><a href="login.php" class="luxury-nav-link active">Login</a></li>
                <li><a href="register.php" class="luxury-nav-link">Register</a></li>
            </ul>
            
            <div class="luxury-cta">
                <a href="login.php" class="luxury-btn luxury-btn-secondary">Book a Stay</a>
                <a href="register.php" class="luxury-btn luxury-btn-primary">Get Started</a>
            </div>
        </nav>
    </header>

    <!-- Main Content -->
    <main class="luxury-container">
        <div class="luxury-card">
            <div class="luxury-card-header">
                <i class="fas fa-calendar-check"></i>
                <h1>Welcome Back</h1>
                <p>Sign in to your Monbela account</p>
            </div>
            
            <div class="luxury-card-body">
                <form method="post" action="" id="loginForm">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    
                    <?php if ($error): ?>
                        <div class="luxury-alert luxury-alert-error">
                            <i class="fas fa-exclamation-circle"></i>
                            <span><?= htmlspecialchars($error) ?></span>
                        </div>
                    <?php endif; ?>

                    <?php if ($success): ?>
                        <div class="luxury-alert luxury-alert-success">
                            <i class="fas fa-check-circle"></i>
                            <span><?= htmlspecialchars($success) ?></span>
                        </div>
                    <?php endif; ?>

                    <div class="luxury-form-group">
                        <label for="username" class="luxury-label">Username</label>
                        <div class="relative">
                            <input type="text" 
                                   id="username" 
                                   name="username" 
                                   class="luxury-input" 
                                   placeholder="Enter your username" 
                                   required 
                                   autocomplete="username"
                                   value="<?= htmlspecialchars($_POST['username'] ?? '') ?>">
                            <i class="fas fa-user absolute" style="right: 1.5rem; top: 50%; transform: translateY(-50%); color: var(--medium-gray);"></i>
                        </div>
                    </div>

                    <div class="luxury-form-group">
                        <label for="password" class="luxury-label">Password</label>
                        <div class="password-container">
                            <input type="password" 
                                   id="password" 
                                   name="password" 
                                   class="luxury-input" 
                                   placeholder="Enter your password" 
                                   required 
                                   autocomplete="current-password">
                            <button type="button" class="password-toggle" id="togglePassword">
                                <i class="fas fa-eye" id="toggleIcon"></i>
                            </button>
                        </div>
                    </div>

                    <div class="flex justify-between items-center mb-3">
                        <div class="luxury-checkbox">
                            <input type="checkbox" id="remember" name="remember">
                            <label for="remember">Remember me</label>
                        </div>
                        <a href="forgot_password.php" class="luxury-link text-sm">
                            Forgot password?
                        </a>
                    </div>

                    <button type="submit" class="luxury-btn luxury-btn-primary w-full mb-3" id="loginBtn">
                        <span id="btnText">Sign In</span>
                        <i class="fas fa-arrow-right" id="btnIcon"></i>
                        <div class="luxury-spinner hidden" id="loginSpinner"></div>
                    </button>

                    <div class="text-center mt-3">
                        <span class="text-sm text-gray-600">Don't have an account?</span>
                        <a href="register.php" class="luxury-link text-sm ml-2">
                            <i class="fas fa-user-plus"></i> Create Account
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
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('password');
            const icon = document.getElementById('toggleIcon');
            
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

        // Form submission loading state
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const loginBtn = document.getElementById('loginBtn');
            const btnText = document.getElementById('btnText');
            const btnIcon = document.getElementById('btnIcon');
            const spinner = document.getElementById('loginSpinner');
            
            // Show loading state
            loginBtn.disabled = true;
            btnText.textContent = 'Signing In...';
            btnIcon.classList.add('hidden');
            spinner.classList.remove('hidden');
        });

        // Social login handlers
        document.getElementById('googleLogin').addEventListener('click', function() {
            alert('Google login would be implemented here');
        });

        document.getElementById('facebookLogin').addEventListener('click', function() {
            alert('Facebook login would be implemented here');
        });

        // Auto-focus username on page load
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('username').focus();
            
            // Check for remember me cookie
            if (document.cookie.includes('remember_token')) {
                document.getElementById('remember').checked = true;
            }
        });

        // Add keyboard navigation
        document.addEventListener('keydown', function(e) {
            // Press Enter in username field to focus password field
            if (e.key === 'Enter' && document.activeElement.id === 'username') {
                e.preventDefault();
                document.getElementById('password').focus();
            }
            
            // Press Escape to clear form
            if (e.key === 'Escape') {
                document.getElementById('username').value = '';
                document.getElementById('password').value = '';
                document.getElementById('username').focus();
            }
        });

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