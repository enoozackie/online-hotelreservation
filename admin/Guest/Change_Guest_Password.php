<?php
// =============================================================================
// Change_Guest_Password.php - Admin Change Guest Password
// =============================================================================
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require __DIR__ . '/../../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Guest;

// Check authentication
if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../../public/login.php");
    exit;
}

$guestModel = new Guest();
$guestId = $_GET['id'] ?? 0;
$guest = $guestModel->getById($guestId);

if (!$guest) {
    header("Location: Manage_guest.php?error=" . urlencode("Guest not found"));
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch. Please try again.";
    } else {
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validation
        if (empty($newPassword)) {
            $error = "Please enter a new password.";
        } elseif (strlen($newPassword) < 6) {
            $error = "Password must be at least 6 characters long.";
        } elseif ($newPassword !== $confirmPassword) {
            $error = "Passwords do not match.";
        } else {
            // Update password
            if ($guestModel->updatePassword($guestId, $newPassword)) {
                $success = "Password updated successfully for " . htmlspecialchars($guest['G_FNAME'] . ' ' . $guest['G_LNAME']) . ".";
                
                // Optional: Redirect after success
                // header("Location: Manage_guest.php?success=" . urlencode($success));
                // exit;
            } else {
                $error = "Failed to update password. Please try again.";
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
    <title>Change Guest Password - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-dark: #1a365d;
            --primary-main: #2d3748;
            --primary-light: #4a5568;
            --accent-color: #3498db;
            --accent-hover: #2980b9;
            --success-color: #27ae60;
            --warning-color: #f39c12;
            --danger-color: #e74c3c;
            --light-bg: #f8f9fa;
            --card-bg: #ffffff;
            --text-primary: #2c3e50;
            --text-secondary: #7f8c8d;
            --border-color: #e0e0e0;
            --gradient-primary: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary-main) 100%);
            --gradient-accent: linear-gradient(135deg, var(--primary-main) 0%, var(--accent-color) 100%);
            --gradient-card: linear-gradient(145deg, #ffffff 0%, #f8fafc 100%);
            --shadow-sm: 0 2px 4px rgba(0, 0, 0, 0.05);
            --shadow-md: 0 4px 8px rgba(0, 0, 0, 0.08);
            --shadow-lg: 0 10px 20px rgba(0, 0, 0, 0.1);
            --radius-sm: 0.375rem;
            --radius-md: 0.5rem;
            --radius-lg: 0.75rem;
            --sidebar-width: 260px;
            --font-family: 'Inter', sans-serif;
        }

        body {
            font-family: var(--font-family);
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            color: var(--text-primary);
            line-height: 1.6;
        }

        .sidebar {
            position: fixed;
            top: 0;
            left: 0;
            height: 100vh;
            width: var(--sidebar-width);
            background: var(--gradient-primary);
            color: white;
            overflow-y: auto;
            z-index: 1000;
            box-shadow: var(--shadow-lg);
        }
        .sidebar-header {
            padding: 2rem 1.5rem;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 0.75rem 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            transition: all 0.3s;
            text-decoration: none;
            border-left: 3px solid transparent;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.15);
            border-left-color: white;
        }
        .sidebar .nav-section {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid rgba(255,255,255,0.1);
        }
        .sidebar .nav-section-title {
            padding: 0.25rem 1.5rem;
            font-size: 0.75rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: rgba(255,255,255,0.5);
        }
        .main-content {
            margin-left: var(--sidebar-width);
            padding: 2rem;
            min-height: 100vh;
        }

        .password-card {
            background: var(--gradient-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 2rem;
            border: none;
            max-width: 600px;
            margin: 0 auto;
        }

        .guest-info-banner {
            background: var(--gradient-primary);
            color: white;
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        .guest-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 600;
        }

        .btn-premium {
            font-weight: 600;
            border: none;
            border-radius: var(--radius-md);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
            padding: 0.75rem 1.75rem;
        }
        .btn-primary-premium {
            background: var(--gradient-accent);
            color: white;
        }
        .btn-primary-premium:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .form-control-premium {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            padding-left: 3rem;
            transition: all 0.3s ease;
        }
        .form-control-premium:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
            transform: translateY(-1px);
        }

        .input-group-premium {
            position: relative;
        }
        .input-icon {
            position: absolute;
            left: 1rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--text-secondary);
            z-index: 10;
        }
        .password-toggle {
            position: absolute;
            right: 1rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            z-index: 10;
        }
        .password-toggle:hover {
            color: var(--accent-color);
        }

        .alert-premium {
            border: none;
            border-radius: var(--radius-lg);
            font-weight: 500;
            box-shadow: var(--shadow-sm);
        }
        .alert-danger-premium {
            background: linear-gradient(135deg, #fed7d7 0%, #feb2b2 100%);
            color: #742a2a;
            border-left: 4px solid var(--danger-color);
        }
        .alert-success-premium {
            background: linear-gradient(135deg, #c6f6d5 0%, #9ae6b4 100%);
            color: #22543d;
            border-left: 4px solid var(--success-color);
        }

        .password-requirements {
            background: rgba(52, 152, 219, 0.1);
            border-left: 3px solid var(--accent-color);
            padding: 1rem;
            border-radius: var(--radius-md);
            margin-top: 1rem;
        }
        .password-requirements ul {
            margin: 0;
            padding-left: 1.5rem;
        }
        .password-requirements li {
            color: var(--text-secondary);
            font-size: 0.9rem;
        }

        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
                transition: transform 0.3s ease;
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <!-- Sidebar -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h5 class="mb-0"><i class="bi bi-building"></i> Monbela Hotel</h5>
            <small>Admin Panel</small>
        </div>
        <div>
            <nav class="nav flex-column mt-3">
                <a class="nav-link" href="../admin_dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
                
                <div class="nav-section">
                    <div class="nav-section-title">Guest Management</div>
                    <a class="nav-link" href="Manage_guest.php">
                        <i class="bi bi-people"></i> Manage Guests
                    </a>
                    <a class="nav-link" href="Add_guest.php">
                        <i class="bi bi-person-plus"></i> Add Guest
                    </a>
                    <a class="nav-link active" href="#">
                        <i class="bi bi-key"></i> Change Password
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Room Management</div>
                    <a class="nav-link" href="../room/manage_rooms.php">
                        <i class="bi bi-door-closed"></i> Manage Rooms
                    </a>
                    <a class="nav-link" href="../room/add_room.php">
                        <i class="bi bi-plus-square"></i> Add Room
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a class="nav-link" href="../manage_admins.php">
                        <i class="bi bi-shield-lock"></i> Manage Admins
                    </a>
                    <a class="nav-link" href="../admin_logout.php?token=<?= $_SESSION['csrf_token'] ?>">
                        <i class="bi bi-box-arrow-right"></i> Logout
                    </a>
                </div>
            </nav>
        </div>
    </div>

    <!-- Main Content -->
    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2><i class="bi bi-key me-2"></i>Change Guest Password</h2>
                <p class="text-muted mb-0">Update the password for this guest account</p>
            </div>
            <div>
                <a href="Manage_guest.php" class="btn btn-secondary me-2">
                    <i class="bi bi-arrow-left"></i> Back to Guests
                </a>
                <button class="btn btn-primary-premium btn-premium d-md-none" id="mobileMenuBtn">
                    <i class="bi bi-list"></i>
                </button>
            </div>
        </div>

        <!-- Guest Info Banner -->
        <div class="guest-info-banner">
            <div class="guest-avatar">
                <?= strtoupper(substr($guest['G_FNAME'], 0, 1) . substr($guest['G_LNAME'], 0, 1)) ?>
            </div>
            <div>
                <h5 class="mb-1"><?= htmlspecialchars($guest['G_FNAME'] . ' ' . $guest['G_LNAME']) ?></h5>
                <div class="d-flex gap-3">
                    <small><i class="bi bi-person-badge me-1"></i> <?= htmlspecialchars($guest['G_UNAME']) ?></small>
                    <small><i class="bi bi-telephone me-1"></i> <?= htmlspecialchars($guest['G_PHONE']) ?></small>
                </div>
            </div>
        </div>

        <!-- Password Change Card -->
        <div class="password-card">
            <?php if ($error): ?>
                <div class="alert alert-danger-premium alert-premium mb-4">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($success): ?>
                <div class="alert alert-success-premium alert-premium mb-4">
                    <i class="bi bi-check-circle me-2"></i>
                    <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="passwordForm">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="mb-4">
                    <label class="form-label fw-semibold">New Password *</label>
                    <div class="input-group-premium">
                        <i class="bi bi-lock input-icon"></i>
                        <input 
                            type="password" 
                            name="new_password" 
                            id="newPassword"
                            class="form-control-premium form-control" 
                            placeholder="Enter new password"
                            required
                            minlength="6">
                        <button type="button" class="password-toggle" id="toggleNew">
                            <i class="bi bi-eye" id="toggleNewIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold">Confirm New Password *</label>
                    <div class="input-group-premium">
                        <i class="bi bi-lock-fill input-icon"></i>
                        <input 
                            type="password" 
                            name="confirm_password" 
                            id="confirmPassword"
                            class="form-control-premium form-control" 
                            placeholder="Re-enter new password"
                            required
                            minlength="6">
                        <button type="button" class="password-toggle" id="toggleConfirm">
                            <i class="bi bi-eye" id="toggleConfirmIcon"></i>
                        </button>
                    </div>
                    <div id="passwordMatch" class="small mt-2"></div>
                </div>

                <div class="password-requirements">
                    <h6 class="mb-2"><i class="bi bi-info-circle me-1"></i> Password Requirements:</h6>
                    <ul>
                        <li>Minimum 6 characters</li>
                        <li>Both passwords must match</li>
                        <li>Use a strong, unique password</li>
                    </ul>
                </div>

                <div class="d-flex gap-2 justify-content-end pt-4 border-top mt-4">
                    <a href="Manage_guest.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary-premium btn-premium" id="submitBtn">
                        <i class="bi bi-check-lg me-2"></i>Update Password
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const newPassword = document.getElementById('newPassword');
            const confirmPassword = document.getElementById('confirmPassword');
            const passwordMatch = document.getElementById('passwordMatch');
            const submitBtn = document.getElementById('submitBtn');
            const form = document.getElementById('passwordForm');

            // Toggle password visibility
            document.getElementById('toggleNew').addEventListener('click', function() {
                togglePasswordVisibility('newPassword', 'toggleNewIcon');
            });

            document.getElementById('toggleConfirm').addEventListener('click', function() {
                togglePasswordVisibility('confirmPassword', 'toggleConfirmIcon');
            });

            function togglePasswordVisibility(inputId, iconId) {
                const input = document.getElementById(inputId);
                const icon = document.getElementById(iconId);
                
                if (input.type === 'password') {
                    input.type = 'text';
                    icon.classList.remove('bi-eye');
                    icon.classList.add('bi-eye-slash');
                } else {
                    input.type = 'password';
                    icon.classList.remove('bi-eye-slash');
                    icon.classList.add('bi-eye');
                }
            }

            // Password match validation
            function checkPasswordMatch() {
                if (confirmPassword.value.length === 0) {
                    passwordMatch.textContent = '';
                    passwordMatch.className = 'small mt-2';
                    return;
                }

                if (newPassword.value === confirmPassword.value) {
                    passwordMatch.textContent = '✓ Passwords match';
                    passwordMatch.className = 'small mt-2 text-success';
                } else {
                    passwordMatch.textContent = '✗ Passwords do not match';
                    passwordMatch.className = 'small mt-2 text-danger';
                }
            }

            newPassword.addEventListener('input', checkPasswordMatch);
            confirmPassword.addEventListener('input', checkPasswordMatch);

            // Form validation
            form.addEventListener('submit', function(e) {
                if (newPassword.value !== confirmPassword.value) {
                    e.preventDefault();
                    alert('Passwords do not match. Please try again.');
                    return false;
                }

                if (newPassword.value.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long.');
                    return false;
                }

                // Show loading state
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Updating...';
            });

            // Mobile menu toggle
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.querySelector('.sidebar');
            
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                });
            }

            // Auto-dismiss alerts after 5 seconds
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    const bsAlert = new bootstrap.Alert(alert);
                    bsAlert.close();
                }, 5000);
            });
        });
    </script>
</body>
</html>