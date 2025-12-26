<?php
session_start();

// Prevent caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Optional: Prevent non-admin access
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

// -------------------------------------------------------
// OBJECT CREATION
// -------------------------------------------------------
 $admin = new Admin();
 $errors = [];
 $success = false;
 $admins = [];

try {
    // Fetch list of admins
    $admins = $admin->getAdmins();
} catch (Exception $e) {
    $errors[] = $e->getMessage();
}

// Handle delete operation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    // Verify CSRF token
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $_SESSION['error_message'] = 'Invalid CSRF token.';
        header("Location: manage_admins.php");
        exit;
    }

    if ($_POST['action'] === 'delete' && isset($_POST['id'])) {
        try {
            $result = $admin->deleteAdmin($_POST['id']);
            if ($result) {
                $_SESSION['success_message'] = 'Admin deleted successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to delete admin.';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
        header("Location: manage_admins.php");
        exit;
    }

    // Handle add operation
    if ($_POST['action'] === 'add' && isset($_POST['name'], $_POST['username'], $_POST['password'])) {
        try {
            // Note: the register method in the provided Admin model does not accept role
            $result = $admin->register($_POST['username'], $_POST['password'], $_POST['name']);
            if ($result) {
                $_SESSION['success_message'] = 'Admin added successfully!';
            } else {
                $_SESSION['error_message'] = 'Failed to add admin. Username might already exist.';
            }
        } catch (Exception $e) {
            $_SESSION['error_message'] = 'Error: ' . $e->getMessage();
        }
        header("Location: manage_admins.php");
        exit;
    }
}

// Display success/error messages
 $success_message = $_SESSION['success_message'] ?? '';
unset($_SESSION['success_message']);

 $error_message = $_SESSION['error_message'] ?? '';
unset($_SESSION['error_message']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Admins - Monbela Hotel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #1e3a8a;
            --secondary-color: #3b82f6;
            --accent-color: #60a5fa;
            --light-color: #f0f9ff;
            --dark-color: #1e293b;
            --success-color: #10b981;
            --danger-color: #ef4444;
            --warning-color: #f59e0b;
            --info-color: #3b82f6;
            --light-gray: #f8fafc;
            --medium-gray: #e2e8f0;
            --dark-gray: #64748b;
            --border-radius: 12px;
            --box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06);
            --transition: all 0.3s ease;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f1f5f9;
            color: var(--dark-color);
            line-height: 1.6;
        }

        /* Header Styles */
        .header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem 0;
            box-shadow: var(--box-shadow);
            position: sticky;
            top: 0;
            z-index: 1000;
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
            font-size: 1.5rem;
            font-weight: 700;
            color: white;
            text-decoration: none;
            transition: var(--transition);
        }

        .brand i {
            margin-right: 0.5rem;
            font-size: 1.8rem;
        }

        .brand:hover {
            color: var(--light-color);
            transform: translateY(-2px);
        }

        .mobile-toggle {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 1.5rem;
            cursor: pointer;
            transition: var(--transition);
        }

        .mobile-toggle:hover {
            transform: scale(1.1);
        }

        .nav-links {
            display: flex;
            list-style: none;
            margin: 0;
            padding: 0;
        }

        .nav-links li {
            margin-left: 1.5rem;
        }

        .nav-links a {
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            font-weight: 500;
            display: flex;
            align-items: center;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            transition: var(--transition);
        }

        .nav-links a i {
            margin-right: 0.5rem;
        }

        .nav-links a:hover,
        .nav-links a.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
        }

        /* Main Content */
        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 2rem 1.5rem;
        }

        /* Breadcrumb */
        .breadcrumb {
            display: flex;
            align-items: center;
            margin-bottom: 2rem;
            color: var(--dark-gray);
            font-size: 0.9rem;
        }

        .breadcrumb a {
            color: var(--secondary-color);
            text-decoration: none;
            transition: var(--transition);
        }

        .breadcrumb a:hover {
            color: var(--primary-color);
        }

        .breadcrumb .separator {
            margin: 0 0.5rem;
        }

        .breadcrumb .current {
            color: var(--dark-color);
            font-weight: 500;
        }

        /* Page Header */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .page-title {
            font-size: 2rem;
            font-weight: 700;
            color: var(--dark-color);
            margin: 0;
        }

        .page-header .d-flex {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .search-box {
            position: relative;
            width: 250px;
        }

        .search-box input {
            width: 100%;
            padding: 0.75rem 1rem 0.75rem 2.5rem;
            border: 1px solid var(--medium-gray);
            border-radius: var(--border-radius);
            font-size: 0.9rem;
            transition: var(--transition);
            background-color: white;
        }

        .search-box input:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .search-box i {
            position: absolute;
            left: 0.75rem;
            top: 50%;
            transform: translateY(-50%);
            color: var(--dark-gray);
        }

        .filter-btn,
        .add-btn {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: var(--border-radius);
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
        }

        .filter-btn {
            background-color: white;
            color: var(--dark-color);
            border: 1px solid var(--medium-gray);
        }

        .filter-btn:hover {
            background-color: var(--light-gray);
            transform: translateY(-2px);
        }

        .add-btn {
            background-color: var(--secondary-color);
            color: white;
        }

        .add-btn:hover {
            background-color: var(--primary-color);
            transform: translateY(-2px);
        }

        /* Alerts */
        .alert {
            padding: 1rem 1.5rem;
            border-radius: var(--border-radius);
            margin-bottom: 2rem;
            display: flex;
            align-items: center;
            animation: slideIn 0.3s ease;
        }

        .alert i {
            margin-right: 0.75rem;
            font-size: 1.2rem;
        }

        .alert-success {
            background-color: rgba(16, 185, 129, 0.1);
            color: var(--success-color);
            border-left: 4px solid var(--success-color);
        }

        .alert-danger {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
            border-left: 4px solid var(--danger-color);
        }

        /* Card */
        .card {
            background-color: white;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
            overflow: hidden;
            margin-bottom: 2rem;
            animation: fadeIn 0.5s ease;
        }

        .card-header {
            background-color: white;
            padding: 1.5rem;
            border-bottom: 1px solid var(--medium-gray);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .card-header h2 {
            font-size: 1.25rem;
            font-weight: 600;
            margin: 0;
            color: var(--dark-color);
            display: flex;
            align-items: center;
        }

        .card-header h2 i {
            margin-right: 0.5rem;
            color: var(--secondary-color);
        }

        .card-header span {
            background-color: var(--light-color);
            color: var(--primary-color);
            padding: 0.25rem 0.75rem;
            border-radius: 50px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .card-body {
            padding: 1.5rem;
        }

        /* Table */
        .table-container {
            overflow-x: auto;
        }

        .admin-table {
            width: 100%;
            border-collapse: collapse;
        }

        .admin-table th {
            background-color: var(--light-gray);
            color: var(--dark-color);
            font-weight: 600;
            text-align: left;
            padding: 1rem;
            border-bottom: 2px solid var(--medium-gray);
        }

        .admin-table td {
            padding: 1rem;
            border-bottom: 1px solid var(--medium-gray);
        }

        .admin-table tr:last-child td {
            border-bottom: none;
        }

        .admin-table tbody tr {
            transition: var(--transition);
        }

        .admin-table tbody tr:hover {
            background-color: var(--light-gray);
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .btn-action {
            padding: 0.5rem 0.75rem;
            border: none;
            border-radius: 6px;
            font-size: 0.85rem;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
            display: flex;
            align-items: center;
            gap: 0.25rem;
            text-decoration: none;
        }

        .btn-edit {
            background-color: rgba(59, 130, 246, 0.1);
            color: var(--info-color);
        }

        .btn-edit:hover {
            background-color: var(--info-color);
            color: white;
            transform: translateY(-2px);
        }

        .btn-delete {
            background-color: rgba(239, 68, 68, 0.1);
            color: var(--danger-color);
        }

        .btn-delete:hover {
            background-color: var(--danger-color);
            color: white;
            transform: translateY(-2px);
        }

        /* Empty State */
        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--dark-gray);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--medium-gray);
        }

        .empty-state h4 {
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        /* Modal */
        .modal-content {
            border-radius: var(--border-radius);
            border: none;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
        }

        .modal-header {
            background-color: var(--light-gray);
            border-bottom: 1px solid var(--medium-gray);
            padding: 1.5rem;
            border-radius: var(--border-radius) var(--border-radius) 0 0;
        }

        .modal-title {
            font-weight: 600;
            color: var(--dark-color);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1.5rem;
            border-top: 1px solid var(--medium-gray);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 500;
            margin-bottom: 0.5rem;
            color: var(--dark-color);
        }

        .form-control {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--medium-gray);
            border-radius: 8px;
            font-size: 0.95rem;
            transition: var(--transition);
        }

        .form-control:focus {
            outline: none;
            border-color: var(--secondary-color);
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .btn {
            padding: 0.75rem 1.25rem;
            border: none;
            border-radius: 8px;
            font-weight: 500;
            cursor: pointer;
            transition: var(--transition);
        }

        .btn-secondary {
            background-color: var(--medium-gray);
            color: var(--dark-color);
        }

        .btn-secondary:hover {
            background-color: var(--dark-gray);
            color: white;
        }

        .btn-primary {
            background-color: var(--secondary-color);
            color: white;
        }

        .btn-primary:hover {
            background-color: var(--primary-color);
        }

        .btn-danger {
            background-color: var(--danger-color);
            color: white;
        }

        .btn-danger:hover {
            background-color: #dc2626;
        }

        /* Animations */
        @keyframes slideIn {
            from {
                transform: translateY(-10px);
                opacity: 0;
            }
            to {
                transform: translateY(0);
                opacity: 1;
            }
        }

        @keyframes fadeIn {
            from {
                opacity: 0;
            }
            to {
                opacity: 1;
            }
        }

        /* Responsive */
        @media (max-width: 992px) {
            .page-header {
                flex-direction: column;
                align-items: flex-start;
            }

            .page-header .d-flex {
                width: 100%;
                justify-content: flex-start;
            }

            .search-box {
                width: 100%;
                max-width: 300px;
            }
        }

        @media (max-width: 768px) {
            .mobile-toggle {
                display: block;
            }

            .nav-links {
                position: fixed;
                top: 70px;
                left: 0;
                width: 100%;
                background-color: var(--primary-color);
                flex-direction: column;
                padding: 1rem 0;
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease;
            }

            .nav-links.active {
                max-height: 500px;
            }

            .nav-links li {
                margin: 0;
            }

            .nav-links a {
                width: 100%;
                padding: 0.75rem 1.5rem;
            }

            .card-header {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .action-buttons {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <!-- Header -->
    <header class="header">
        <div class="container">
            <a href="admin_dashboard.php" class="brand">
                <i class="bi bi-building"></i>
                Monbela Hotel Admin
            </a>
            <button class="mobile-toggle" onclick="toggleMenu()">
                <i class="bi bi-list"></i>
            </button>
            <ul class="nav-links" id="navLinks">
                <li><a href="admin_dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li><a href="manage_admins.php" class="active"><i class="bi bi-people"></i> Manage Admins</a></li>
                <li><a href="manage_rooms.php"><i class="bi bi-door-open"></i> Manage Rooms</a></li>
                <li><a href="manage_bookings.php"><i class="bi bi-calendar-check"></i> Manage Bookings</a></li>
                <li><a href="admin_profile.php"><i class="bi bi-person-circle"></i> Profile</a></li>
                <li><a href="admin_logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </header>

    <!-- Main Content -->
    <div class="container">
        <!-- Breadcrumb -->
        <div class="breadcrumb">
            <a href="admin_dashboard.php">Dashboard</a>
            <span class="separator">/</span>
            <span class="current">Manage Admins</span>
        </div>

        <!-- Success/Error Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success">
                <i class="bi bi-check-circle-fill"></i>
                <?= htmlspecialchars($success_message) ?>
            </div>
        <?php endif; ?>
        <?php if ($error_message): ?>
            <div class="alert alert-danger">
                <i class="bi bi-exclamation-triangle-fill"></i>
                <?= htmlspecialchars($error_message) ?>
            </div>
        <?php endif; ?>

        <!-- Page Header with Actions -->
        <div class="page-header">
            <h1 class="page-title">Admin Accounts</h1>
            <div class="d-flex gap-2">
                <div class="search-box">
                    <input type="text" id="searchInput" placeholder="Search admins...">
                    <i class="bi bi-search"></i>
                </div>
                <button class="filter-btn" onclick="toggleFilter()">
                    <i class="bi bi-funnel"></i> Filter
                </button>
                <button class="add-btn" data-bs-toggle="modal" data-bs-target="#addAdminModal">
                    <i class="bi bi-plus-circle"></i> Add Admin
                </button>
            </div>
        </div>

        <!-- Admin Table -->
        <div class="card">
            <div class="card-header">
                <h2><i class="bi bi-people"></i> Admin Accounts</h2>
                <span id="adminCount"><?= count($admins) ?> Admins</span>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <?= implode("<br>", $errors) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($admins)): ?>
                    <div class="table-container">
                        <table class="admin-table" id="adminTable">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>Name</th>
                                    <th>Username</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($admins as $admin): ?>
                                    <tr>
                                        <td><?= htmlspecialchars($admin['id']) ?></td>
                                        <td><?= htmlspecialchars($admin['fullname']) ?></td>
                                        <td><?= htmlspecialchars($admin['username']) ?></td>
                                        <td>
                                            <div class="action-buttons">
                                                <a href="edit_admin.php?id=<?= $admin['id'] ?>" class="btn-action btn-edit">
                                                    <i class="bi bi-pencil"></i> Edit
                                                </a>
                                                <button class="btn-action btn-delete" onclick="confirmDelete(<?= $admin['id'] ?>)">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="bi bi-person-x"></i>
                        <h4>No admins found</h4>
                        <p>There are no admin accounts in the system.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Add Admin Modal -->
    <div class="modal fade" id="addAdminModal" tabindex="-1" aria-labelledby="addAdminModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAdminModalLabel">Add New Admin</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addAdminForm" method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                        <div class="form-group">
                            <label for="adminName" class="form-label">Name</label>
                            <input type="text" class="form-control" id="adminName" name="name" required>
                        </div>
                        <div class="form-group">
                            <label for="adminUsername" class="form-label">Username</label>
                            <input type="text" class="form-control" id="adminUsername" name="username" required>
                        </div>
                        <div class="form-group">
                            <label for="adminPassword" class="form-label">Password</label>
                            <input type="password" class="form-control" id="adminPassword" name="password" required>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="addAdmin()">Add Admin</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteModalLabel">Confirm Delete</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this admin account? This action cannot be undone.</p>
                    <input type="hidden" id="deleteAdminId">
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmDeleteBtn">Delete</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Mobile menu toggle
        function toggleMenu() {
            document.getElementById('navLinks').classList.toggle('active');
        }

        // Search functionality
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#adminTable tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // Filter functionality (placeholder)
        function toggleFilter() {
            // This would typically open a filter panel or modal
            alert('Filter functionality would be implemented here');
        }

        // Delete confirmation
        function confirmDelete(id) {
            document.getElementById('deleteAdminId').value = id;
            const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
            deleteModal.show();
        }

        // Confirm delete button
        document.getElementById('confirmDeleteBtn').addEventListener('click', function() {
            const adminId = document.getElementById('deleteAdminId').value;
            
            // Create form and submit
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';
            
            const actionInput = document.createElement('input');
            actionInput.type = 'hidden';
            actionInput.name = 'action';
            actionInput.value = 'delete';
            
            const idInput = document.createElement('input');
            idInput.type = 'hidden';
            idInput.name = 'id';
            idInput.value = adminId;
            
            const csrfInput = document.createElement('input');
            csrfInput.type = 'hidden';
            csrfInput.name = 'csrf_token';
            csrfInput.value = "<?= $_SESSION['csrf_token'] ?>";
            
            form.appendChild(actionInput);
            form.appendChild(idInput);
            form.appendChild(csrfInput);
            
            document.body.appendChild(form);
            form.submit();
        });

        // Add admin functionality
        function addAdmin() {
            const form = document.getElementById('addAdminForm');
            
            // Basic validation
            if (!form.checkValidity()) {
                // Let the browser handle validation
                return;
            }
            
            // Submit the form
            form.submit();
        }
    </script>
</body>
</html>