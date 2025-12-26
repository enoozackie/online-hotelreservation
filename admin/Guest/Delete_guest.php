<?php
// Delete_guest.php
session_start();

require __DIR__ . '/../../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Admin;

if (!isset($_SESSION['id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../public/login.php");
    exit;
}

// CSRF Protection
if (!hash_equals($_SESSION['csrf_token'], $_GET['token'] ?? '')) {
    header("Location: Manage_guest.php?error=Invalid request");
    exit;
}

 $admin = new Admin();
 $guestId = $_GET['id'] ?? 0;

if ($admin->deleteGuest($guestId)) {
    header("Location: Manage_guest.php?success=Guest deleted successfully");
} else {
    header("Location: Manage_guest.php?error=Failed to delete guest");
}
exit;