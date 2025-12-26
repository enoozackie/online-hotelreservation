<?php
session_start();

// Prevent caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Security Headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

// Prevent non-admin access
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

require __DIR__ . '/../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Admin;

// Generate CSRF token if not exists
if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Object creation
 $admin = new Admin();
 $errors = [];
 $success = false;
 $adminData = null;

// Get admin ID from URL
 $adminId = $_GET['id'] ?? null;

if (!$adminId) {
    $_SESSION['error_message'] = 'Admin ID not provided.';
    header("Location: manage_admins.php");
    exit;
}

try {
    // Fetch admin data
    $adminData = $admin->getAdminById($adminId);
    
    if (!$adminData) {
        $_SESSION['error_message'] = 'Admin not found.';
        header("Location: manage_admins.php");
        exit;
    }
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// Handle update operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = 'Invalid CSRF token.';
        header("Location: edit_admin.php?id=" . $adminId);
        exit;
    }

    if ($_POST['action'] === 'update' && isset($_POST['name'], $_POST['username'])) {
        try {
            // Prepare update data
            $updateData = [
                'fullname' => $_POST['name'],
                'username' => $_POST['username']
            ];
            
            // Only update password if provided
            if (!empty($_POST['password'])) {
                // Check if passwords match
                if ($_POST['password'] !== ($_POST['confirm_password'] ?? '')) {
                    $errors[] = 'Passwords do not match.';
                } else {
                    $updateData['password'] = $_POST['password'];
                }
            }
            
            if (empty($errors)) {
                $result = $admin->updateAdmin($adminId, $updateData);
                
                if ($result) {
                    $_SESSION['success_message'] = 'Admin updated successfully!';
                    header("Location: manage_admins.php");
                    exit;
                } else {
                    $errors[] = 'Failed to update admin. Username might already exist.';
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Error: ' . $e->getMessage();
        }
    }
}

// Get theme preference
 $theme = $_COOKIE['admin_theme'] ?? 'light';
?>
<!DOCTYPE html>
<html lang="en" data-theme="<?= $theme ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Admin - Monbela Hotel</title>
    
    <!-- CSS Dependencies -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <style>
        /* Modern Design System - Matching Dashboard */
        :root {
            --primary: #2563eb;
            --primary-dark: #1e40af;
            --primary-light: #3b82f6;
            --secondary: #64748b;
            --success: #22c55e;
            --warning: #f59e0b;
            --danger: #ef4444;
            --info: #06b6d4;
            --white: #ffffff;
            --gray-50: #f8fafc;
            --gray-100: #f1f5f9;
            --gray-200: #e2e8f0;
            --gray-300: #cbd5e1;
            --gray-400: #94a3b8;
            --gray-500: #64748b;
            --gray-600: #475569;
            --gray-700: #334155;
            --gray-800: #1e293b;
            --gray-900: #0f172a;
            --bg-primary: #ffffff;
            --bg-secondary: #f8fafc;
            --bg-tertiary: #f1f5f9;
            --text-primary: #0f172a;
            --text-secondary: #475569;
            --text-muted: #94a3b8;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.08);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
            --shadow-xl: 0 20px 25px -5px rgba(0, 0, 0, 0.1);
            --sidebar-width: 280px;
            --header-height: 70px;
            --radius: 12px;
            --radius-sm: 8px;
            --radius-lg: 16px;
            --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        
        [data-theme="dark"] {
            --bg-primary: #0f172a;
            --bg-secondary: #1e293b;
            --bg-tertiary: #334155;
            --text-primary: #f1f5f9;
            --text-secondary: #cbd5e1;
            --text-muted: #94a3b8;
            --border-color: #334155;
            --shadow-sm: 0 1px 3px rgba(0, 0, 0, 0.3);
            --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.3);
            --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.3);
        }
        
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: var(--bg-secondary);
            color: var(--text-primary);
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            -moz-osx-font-smoothing: grayscale;
        }
        
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            bottom: 0;
            width: var(--sidebar-width);
            background: var(--bg-primary);
            border-right: 1px solid var(--border-color);
            z-index: 1000;
            overflow-y: auto;
            transition: var(--transition);
        }
        
        .sidebar-brand {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 24px;
            text-decoration: none;
            color: var(--text-primary);
            font-weight: 700;
            font-size: 1.25rem;
            border-bottom: 1px solid var(--border-color);
        }
        
        .sidebar-brand i {
            font-size: 1.5rem;
            color: var(--primary);
        }
        
        .user-profile {
            padding: 24px;
            text-align: center;
            background: var(--bg-secondary);
            margin: 16px;
            border-radius: var(--radius);
        }
        
        .user-avatar-lg {
            width: 72px;
            height: 72px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 12px;
            font-size: 1.5rem;
            font-weight: 600;
            color: white;
            box-shadow: var(--shadow-lg);
        }
        
        .nav-section {
            padding: 8px 16px;
        }
        
        .nav-section-title {
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            color: var(--text-muted);
            padding: 8px 12px;
            letter-spacing: 0.05em;
        }
        
        .nav-link {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 10px 12px;
            color: var(--text-secondary);
            text-decoration: none;
            border-radius: var(--radius-sm);
            transition: var(--transition);
            position: relative;
            font-weight: 500;
            margin-bottom: 4px;
        }
        
        .nav-link:hover {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .nav-link.active {
            background: var(--primary);
            color: white;
        }
        
        .nav-link i {
            font-size: 1.125rem;
            width: 24px;
        }
        
        .main-content {
            margin-left: var(--sidebar-width);
            min-height: 100vh;
            padding: 24px;
            transition: var(--transition);
        }
        
        .top-bar {
            background: var(--bg-primary);
            padding: 20px 24px;
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        
        .page-title {
            font-size: 1.75rem;
            font-weight: 700;
            margin: 0;
            color: var(--text-primary);
        }
        
        .page-subtitle {
            font-size: 0.875rem;
            color: var(--text-muted);
            margin: 4px 0 0;
        }
        
        .user-menu {
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .theme-toggle {
            width: 44px;
            height: 44px;
            border-radius: var(--radius);
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: var(--transition);
            color: var(--text-primary);
        }
        
        .theme-toggle:hover {
            background: var(--bg-tertiary);
            transform: translateY(-2px);
            box-shadow: var(--shadow-md);
        }
        
        .user-avatar {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, var(--primary) 0%, var(--primary-light) 100%);
            border-radius: var(--radius);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: 600;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .user-avatar:hover {
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 24px;
            color: var(--text-muted);
            font-size: 0.875rem;
        }
        
        .breadcrumb a {
            color: var(--primary);
            text-decoration: none;
            transition: var(--transition);
        }
        
        .breadcrumb a:hover {
            color: var(--primary-dark);
        }
        
        .breadcrumb .separator {
            margin: 0 8px;
        }
        
        .breadcrumb .current {
            color: var(--text-primary);
            font-weight: 500;
        }
        
        .card {
            background: var(--bg-primary);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-sm);
            overflow: hidden;
            margin-bottom: 24px;
        }
        
        .card-header {
            background: var(--bg-primary);
            border-bottom: 1px solid var(--border-color);
            padding: 20px 24px;
        }
        
        .card-header h2 {
            font-size: 1.125rem;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            color: var(--text-primary);
        }
        
        .card-header h2 i {
            color: var(--primary);
        }
        
        .card-body {
            padding: 24px;
        }
        
        .admin-info {
            background: var(--bg-secondary);
            padding: 20px;
            border-radius: var(--radius);
            margin-bottom: 0;
        }
        
        .admin-info h3 {
            font-size: 1rem;
            font-weight: 600;
            margin-bottom: 16px;
            color: var(--text-primary);
        }
        
        .info-row {
            display: flex;
            margin-bottom: 12px;
        }
        
        .info-label {
            font-weight: 500;
            color: var(--text-secondary);
            width: 120px;
            flex-shrink: 0;
        }
        
        .info-value {
            color: var(--text-primary);
        }
        
        .form-label {
            font-weight: 600;
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-size: 0.875rem;
            display: block;
        }
        
        .form-control,
        .form-select {
            border: 1px solid var(--border-color);
            border-radius: var(--radius-sm);
            padding: 10px 16px;
            font-size: 0.875rem;
            transition: var(--transition);
            background: var(--bg-primary);
            color: var(--text-primary);
            width: 100%;
        }
        
        .form-control:focus,
        .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
            outline: none;
        }
        
        .form-text {
            font-size: 0.75rem;
            color: var(--text-muted);
            margin-top: 4px;
        }
        
        .password-toggle {
            position: relative;
        }
        
        .password-toggle-btn {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-muted);
            cursor: pointer;
            padding: 4px;
        }
        
        .password-toggle-btn:hover {
            color: var(--primary);
        }
        
        .form-actions {
            display: flex;
            gap: 12px;
            justify-content: flex-end;
            margin-top: 24px;
        }
        
        .btn {
            padding: 10px 20px;
            border-radius: var(--radius-sm);
            font-weight: 600;
            font-size: 0.875rem;
            transition: var(--transition);
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .btn-secondary {
            background: var(--bg-tertiary);
            color: var(--text-primary);
        }
        
        .btn-secondary:hover {
            background: var(--border-color);
        }
        
        .btn-primary {
            background: var(--primary);
            color: white;
        }
        
        .btn-primary:hover {
            background: var(--primary-dark);
            transform: translateY(-2px);
            box-shadow: var(--shadow-lg);
        }
        
        .alert-message {
            padding: 16px 20px;
            border-radius: var(--radius);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideInDown 0.3s ease;
        }
        
        .alert-success {
            background: rgba(34, 197, 94, 0.1);
            color: var(--success);
            border: 1px solid rgba(34, 197, 94, 0.2);
        }
        
        .alert-danger {
            background: rgba(239, 68, 68, 0.1);
            color: var(--danger);
            border: 1px solid rgba(239, 68, 68, 0.2);
        }
        
        .mobile-menu-btn {
            display: none;
            width: 44px;
            height: 44px;
            align-items: center;
            justify-content: center;
            background: var(--primary);
            color: white;
            border: none;
            border-radius: var(--radius);
            font-size: 1.25rem;
            cursor: pointer;
            transition: var(--transition);
        }
        
        .mobile-menu-btn:hover {
            background: var(--primary-dark);
        }
        
        @media (max-width: 768px) {
            :root {
                --sidebar-width: 100%;
            }
            
            .mobile-menu-btn {
                display: flex;
            }
            
            .sidebar {
                transform: translateX(-100%);
                box-shadow: var(--shadow-xl);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
            
            .form-actions {
                flex-direction: column;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
        }
        
        @keyframes slideInDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    </style>
</head>
<body>
    <!-- App Container -->
    <div class="app-container">
        <!-- Sidebar -->
        <aside class="sidebar" id="sidebar">
            <a href="admin_dashboard.php" class="sidebar-brand">
                <i class="bi bi-building"></i>
                <span>Monbela Hotel</span>
            </a>
            
            <!-- User Profile -->
            <div class="user-profile">
                <div class="user-avatar-lg">
                    <?= strtoupper(substr($_SESSION['fullname'] ?? 'A', 0, 1)) ?>
                </div>
                <h5 style="margin: 0 0 4px; font-size: 1rem; font-weight: 600;">
                    <?= htmlspecialchars($_SESSION['fullname'] ?? 'Admin') ?>
                </h5>
                <span style="font-size: 0.75rem; color: var(--text-muted); text-transform: uppercase;">
                    Administrator
                </span>
            </div>
            
            <!-- Navigation -->
            <nav class="sidebar-nav">
                <div class="nav-section">
                    <div class="nav-section-title">Main</div>
                    <a href="admin_dashboard.php" class="nav-link">
                        <i class="bi bi-speedometer2"></i>
                        <span>Dashboard</span>
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a href="manage_admins.php" class="nav-link active">
                        <i class="bi bi-shield-lock"></i>
                        <span>Manage Admins</span>
                    </a>
                    <a href="admin_logout.php?token=<?= $_SESSION['csrf_token'] ?>" class="nav-link">
                        <i class="bi bi-box-arrow-right"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </nav>
        </aside>
        
        <!-- Main Content -->
        <main class="main-content">
            <!-- Top Bar -->
            <div class="top-bar">
                <div class="d-flex align-items-center gap-3">
                    <button class="mobile-menu-btn" id="mobileMenuBtn" aria-label="Toggle menu">
                        <i class="bi bi-list"></i>
                    </button>
                    <div>
                        <h1 class="page-title">Edit Admin Account</h1>
                        <p class="page-subtitle">Modify administrator details</p>
                    </div>
                </div>
                <div class="user-menu">
                    <button class="theme-toggle" id="themeToggle" aria-label="Toggle theme">
                        <i class="bi bi-sun-fill" id="themeIcon"></i>
                    </button>
                    <div class="user-avatar">
                        <?= strtoupper(substr($_SESSION['fullname'] ?? 'A', 0, 1)) ?>
                    </div>
                </div>
            </div>
            
            <!-- Breadcrumb -->
            <div class="breadcrumb">
                <a href="admin_dashboard.php">Dashboard</a>
                <span class="separator">/</span>
                <a href="manage_admins.php">Manage Admins</a>
                <span class="separator">/</span>
                <span class="current">Edit Admin</span>
            </div>
            
            <!-- Error Messages -->
            <?php if (!empty($errors)): ?>
                <div class="alert-message alert-danger" role="alert">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <span><?= implode("<br>", $errors) ?></span>
                </div>
            <?php endif; ?>
            
            <!-- Admin Info Card -->
            <?php if ($adminData): ?>
                <div class="card">
                    <div class="card-header">
                        <h2><i class="bi bi-person-badge"></i> Current Admin Information</h2>
                    </div>
                    <div class="card-body">
                        <div class="admin-info">
                            <h3>Account Details</h3>
                            <div class="info-row">
                                <span class="info-label">Admin ID:</span>
                                <span class="info-value"><?= htmlspecialchars($adminData['id']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Full Name:</span>
                                <span class="info-value"><?= htmlspecialchars($adminData['fullname']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Username:</span>
                                <span class="info-value"><?= htmlspecialchars($adminData['username']) ?></span>
                            </div>
                            <div class="info-row">
                                <span class="info-label">Role:</span>
                                <span class="info-value"><?= ucfirst(htmlspecialchars($adminData['role'])) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Form -->
                <div class="card">
                    <div class="card-header">
                        <h2><i class="bi bi-pencil-square"></i> Edit Admin Details</h2>
                    </div>
                    <div class="card-body">
                        <form class="edit-form" method="POST" action="">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                            
                            <div class="mb-3">
                                <label for="adminName" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="adminName" name="name" 
                                       value="<?= htmlspecialchars($adminData['fullname']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="adminUsername" class="form-label">Username</label>
                                <input type="text" class="form-control" id="adminUsername" name="username" 
                                       value="<?= htmlspecialchars($adminData['username']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="adminPassword" class="form-label">New Password</label>
                                <div class="password-toggle">
                                    <input type="password" class="form-control" id="adminPassword" name="password" 
                                           placeholder="Leave blank to keep current password">
                                    <button type="button" class="password-toggle-btn" onclick="togglePassword()">
                                        <i class="bi bi-eye" id="passwordToggleIcon"></i>
                                    </button>
                                </div>
                                <div class="form-text">Leave this field empty if you don't want to change the password</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="confirmPassword" class="form-label">Confirm New Password</label>
                                <div class="password-toggle">
                                    <input type="password" class="form-control" id="confirmPassword" name="confirm_password" 
                                           placeholder="Confirm new password">
                                    <button type="button" class="password-toggle-btn" onclick="toggleConfirmPassword()">
                                        <i class="bi bi-eye" id="confirmPasswordToggleIcon"></i>
                                    </button>
                                </div>
                            </div>
                            
                            <div class="form-actions">
                                <a href="manage_admins.php" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-check-circle"></i> Update Admin
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            <?php endif; ?>
        </main>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize theme
            const theme = getCookie('admin_theme') || 'light';
            setTheme(theme);
            
            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.getElementById('sidebar');
            
            mobileMenuBtn?.addEventListener('click', () => {
                sidebar.classList.toggle('active');
            });
            
            // Close sidebar on outside click (mobile)
            document.addEventListener('click', (event) => {
                if (window.innerWidth <= 768) {
                    if (!sidebar.contains(event.target) && !mobileMenuBtn.contains(event.target)) {
                        sidebar.classList.remove('active');
                    }
                }
            });
            
            // Theme toggle
            const themeToggle = document.getElementById('themeToggle');
            themeToggle?.addEventListener('click', () => {
                const currentTheme = document.documentElement.getAttribute('data-theme');
                const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
                setTheme(newTheme);
            });
        });
        
        // Theme management
        function setTheme(theme) {
            document.documentElement.setAttribute('data-theme', theme);
            setCookie('admin_theme', theme, 365);
            
            const themeIcon = document.getElementById('themeIcon');
            if (themeIcon) {
                themeIcon.className = theme === 'dark' ? 'bi bi-moon-fill' : 'bi bi-sun-fill';
            }
        }
        
        // Cookie helpers
        function setCookie(name, value, days) {
            const expires = new Date();
            expires.setTime(expires.getTime() + (days * 24 * 60 * 60 * 1000));
            document.cookie = `${name}=${value};expires=${expires.toUTCString()};path=/`;
        }
        
        function getCookie(name) {
            const nameEQ = name + "=";
            const ca = document.cookie.split(';');
            for (let i = 0; i < ca.length; i++) {
                let c = ca[i].trim();
                if (c.indexOf(nameEQ) === 0) return c.substring(nameEQ.length, c.length);
            }
            return null;
        }
        
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('adminPassword');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }

        // Toggle confirm password visibility
        function toggleConfirmPassword() {
            const confirmPasswordInput = document.getElementById('confirmPassword');
            const toggleIcon = document.getElementById('confirmPasswordToggleIcon');
            
            if (confirmPasswordInput.type === 'password') {
                confirmPasswordInput.type = 'text';
                toggleIcon.classList.remove('bi-eye');
                toggleIcon.classList.add('bi-eye-slash');
            } else {
                confirmPasswordInput.type = 'password';
                toggleIcon.classList.remove('bi-eye-slash');
                toggleIcon.classList.add('bi-eye');
            }
        }

        // Form validation
        document.querySelector('.edit-form').addEventListener('submit', function(e) {
            const password = document.getElementById('adminPassword').value;
            const confirmPassword = document.getElementById('confirmPassword').value;
            
            // If password is entered, confirm password must match
            if (password && password !== confirmPassword) {
                e.preventDefault();
                
                // Create or update error message
                let errorDiv = document.querySelector('.password-error');
                if (!errorDiv) {
                    errorDiv = document.createElement('div');
                    errorDiv.className = 'alert-message alert-danger password-error';
                    errorDiv.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Passwords do not match';
                    document.querySelector('.edit-form').insertBefore(errorDiv, document.querySelector('.form-actions'));
                }
                
                // Scroll to error
                errorDiv.scrollIntoView({ behavior: 'smooth', block: 'center' });
            } else {
                // Remove any existing error message
                const errorDiv = document.querySelector('.password-error');
                if (errorDiv) {
                    errorDiv.remove();
                }
            }
        });

        // Clear confirm password when password is cleared
        document.getElementById('adminPassword').addEventListener('input', function() {
            if (!this.value) {
                document.getElementById('confirmPassword').value = '';
            }
        });
    </script>
</body>
</html> 