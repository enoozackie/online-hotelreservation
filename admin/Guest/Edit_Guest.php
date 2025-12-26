<?php
// Edit_guest.php - Updated with Unified Professional Design
session_start();

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

require __DIR__ . '/../../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Admin;

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

 $admin = new Admin();
 $guestId = $_GET['id'] ?? 0;
 $guest = $admin->getGuestById($guestId);

if (!$guest) {
    header("Location: Manage_guest.php?error=Guest not found");
    exit;
}

 $error = '';
 $success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // CSRF Protection
    if (!hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        $error = "Security token mismatch";
    } else {
        $data = [
            'G_FNAME' => trim($_POST['fname'] ?? ''),
            'G_LNAME' => trim($_POST['lname'] ?? ''),
            'G_CITY' => trim($_POST['city'] ?? ''),
            'G_ADDRESS' => trim($_POST['address'] ?? ''),
            'DBIRTH' => trim($_POST['dbirth'] ?? ''),
            'G_PHONE' => trim($_POST['phone'] ?? ''),
            'G_NATIONALITY' => trim($_POST['nationality'] ?? ''),
            'G_COMPANY' => trim($_POST['company'] ?? ''),
            'G_CADDRESS' => trim($_POST['caddress'] ?? ''),
            'G_UNAME' => trim($_POST['username'] ?? ''),
            'ZIP' => trim($_POST['zip'] ?? ''),
            'LOCATION' => trim($_POST['location'] ?? '')
        ];

        if ($admin->updateGuest($guestId, $data)) {
            header("Location: Manage_guest.php?success=Guest updated successfully");
            exit;
        } else {
            $error = "Failed to update guest";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Guest - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        /* =============================================================================
           UNIFIED PROFESSIONAL HOTEL ADMIN THEME
           ============================================================================= */
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

        .edit-card {
            background: var(--gradient-card);
            border-radius: var(--radius-lg);
            box-shadow: var(--shadow-md);
            padding: 2rem;
            border: none;
        }
        .card-header-premium {
            background: var(--gradient-primary) !important;
            color: white;
            border-radius: var(--radius-lg) var(--radius-lg) 0 0 !important;
            font-weight: 600;
            border: none;
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
        .btn-warning-premium {
            background: linear-gradient(135deg, var(--warning-color) 0%, #e67e22 100%);
            color: white;
        }
        .btn-warning-premium:hover {
            transform: translateY(-1px);
            box-shadow: var(--shadow-lg);
            color: white;
        }

        .form-control-premium {
            border: 2px solid var(--border-color);
            border-radius: var(--radius-md);
            padding: 0.75rem 1rem;
            transition: all 0.3s ease;
        }
        .form-control-premium:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 3px rgba(52, 152, 219, 0.15);
            transform: translateY(-1px);
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

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
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
            .mobile-menu-btn {
                display: block;
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
                <a class="nav-link" href="admin_dashboard.php">
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
                        <i class="bi bi-pencil-square"></i> Edit Guest
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">Room Management</div>
                    <a class="nav-link" href="room/manage_rooms.php">
                        <i class="bi bi-door-closed"></i> Manage Rooms
                    </a>
                    <a class="nav-link" href="room/add_room.php">
                        <i class="bi bi-plus-square"></i> Add Room
                    </a>
                </div>
                
                <div class="nav-section">
                    <div class="nav-section-title">System</div>
                    <a class="nav-link" href="manage_admins.php">
                        <i class="bi bi-shield-lock"></i> Manage Admins
                    </a>
                    <a class="nav-link" href="admin_logout.php?token=<?= $_SESSION['csrf_token'] ?>">
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
                <h2><i class="bi bi-pencil-square me-2"></i>Edit Guest</h2>
                <p class="text-muted mb-0">Update information for <?= htmlspecialchars($guest['G_FNAME'] . ' ' . $guest['G_LNAME']) ?></p>
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

        <div class="edit-card">
            <?php if ($error): ?>
                <div class="alert alert-danger-premium alert-premium mb-4">
                    <i class="bi bi-exclamation-circle me-2"></i>
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">First Name *</label>
                        <input type="text" name="fname" class="form-control-premium form-control" value="<?= htmlspecialchars($guest['G_FNAME']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Last Name *</label>
                        <input type="text" name="lname" class="form-control-premium form-control" value="<?= htmlspecialchars($guest['G_LNAME']) ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Phone *</label>
                        <input type="text" name="phone" class="form-control-premium form-control" value="<?= htmlspecialchars($guest['G_PHONE']) ?>" required>
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Username *</label>
                        <input type="text" name="username" class="form-control-premium form-control" value="<?= htmlspecialchars($guest['G_UNAME']) ?>" required>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">City</label>
                        <input type="text" name="city" class="form-control-premium form-control" value="<?= htmlspecialchars($guest['G_CITY']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Birthday</label>
                        <input type="date" name="dbirth" class="form-control-premium form-control" value="<?= htmlspecialchars($guest['DBIRTH']) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Address</label>
                    <textarea name="address" class="form-control-premium form-control" rows="2"><?= htmlspecialchars($guest['G_ADDRESS']) ?></textarea>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Nationality</label>
                        <input type="text" name="nationality" class="form-control-premium form-control" value="<?= htmlspecialchars($guest['G_NATIONALITY']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">ZIP Code</label>
                        <input type="text" name="zip" class="form-control-premium form-control" value="<?= htmlspecialchars($guest['ZIP']) ?>">
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Company</label>
                        <input type="text" name="company" class="form-control-premium form-control" value="<?= htmlspecialchars($guest['G_COMPANY']) ?>">
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label fw-semibold">Company Address</label>
                        <input type="text" name="caddress" class="form-control-premium form-control" value="<?= htmlspecialchars($guest['G_CADDRESS']) ?>">
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold">Location</label>
                    <input type="text" name="location" class="form-control-premium form-control" value="<?= htmlspecialchars($guest['LOCATION']) ?>">
                </div>

                <div class="d-flex gap-2 justify-content-end pt-3 border-top">
                    <div class="action-buttons">
                        <a href="change_guest_password.php?id=<?= $guestId ?>" class="btn btn-warning-premium btn-premium">
                            <i class="bi bi-key me-2"></i>Change Password
                        </a>
                        <a href="Manage_guest.php" class="btn btn-secondary">
                            <i class="bi bi-x-lg me-2"></i>Cancel
                        </a>
                        <button type="submit" class="btn btn-primary-premium btn-premium">
                            <i class="bi bi-check-lg me-2"></i>Update Guest
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const mobileMenuBtn = document.getElementById('mobileMenuBtn');
            const sidebar = document.querySelector('.sidebar');
            
            // Mobile menu toggle
            if (mobileMenuBtn) {
                mobileMenuBtn.addEventListener('click', function() {
                    sidebar.classList.toggle('mobile-open');
                });
            }
        });
    </script>
</body>
</html>