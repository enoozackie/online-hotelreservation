<?php
session_start();
require __DIR__ . '/../vendor/autoload.php';

use Lourdian\MonbelaHotel\Model\Admin;

$admin = new Admin();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    $fullname = trim($_POST['fullname'] ?? '');

    if (empty($username) || empty($password) || empty($fullname)) {
        $error = "Please fill in all fields.";
    } else {
        if ($admin->register($username, $password, $fullname)) {
            $success = "Admin registered successfully!";
        } else {
            $error = "Failed to register admin. Username might already exist.";
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Admin Registration</title>
</head>
<body>
    <h2>Register New Admin</h2>

    <?php if ($error): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <?php if ($success): ?>
        <p style="color:green"><?= htmlspecialchars($success) ?></p>
    <?php endif; ?>

    <form method="post" action="">
        <label>Full Name:</label><br>
        <input type="text" name="fullname" required><br><br>

        <label>Username:</label><br>
        <input type="text" name="username" required><br><br>

        <label>Password:</label><br>
        <input type="password" name="password" required><br><br>

        <button type="submit">Register Admin</button>
    </form>
</body>
</html>
