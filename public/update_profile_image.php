<?php

session_start();

// Restrict Access
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'guest') {
    http_response_code(403);
    exit('Access Denied');
}

require __DIR__ . '/../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Guest;

 $guest = new Guest();
 $errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_image'])) {
    $file = $_FILES['profile_image'];
    
    // --- 1. Define Paths ---
    // IMPORTANT: This path is for saving the file on the server
    $uploadDir = __DIR__ . '/../images/profiles/'; 
    
    // IMPORTANT: This path is for displaying the image in HTML
    $publicPath = '/images/profiles/'; 

    $maxFileSize = 2 * 1024 * 1024; // 2MB
    $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

    // --- 2. Validate ---
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors['general'] = 'File upload error. Please try again.';
    } elseif (!in_array($file['type'], $allowedTypes)) {
        $errors['general'] = 'Invalid file type. Only JPG, PNG, and WEBP are allowed.';
    } elseif ($file['size'] > $maxFileSize) {
        $errors['general'] = 'File is too large. Maximum size is 2MB.';
    } else {
        // --- 3. Prepare for Upload ---
        $fileExtension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $newFilename = 'profile_' . $_SESSION['id'] . '_' . uniqid() . '.' . $fileExtension;
        $destination = $uploadDir . $newFilename;

        // Ensure upload directory exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // --- 4. Move File and Update DB ---
        if (move_uploaded_file($file['tmp_name'], $destination)) {
            // Update database with the PUBLIC path part only
            if ($guest->updateProfileImage($_SESSION['id'], $newFilename)) {
                $_SESSION['success_message'] = 'Profile picture updated successfully!';
            } else {
                $errors['general'] = 'Failed to update profile picture in the database.';
                // Delete the uploaded file if the DB update fails
                unlink($destination);
            }
        } else {
            $errors['general'] = 'Failed to move the uploaded file to the correct directory.';
        }
    }
} else {
    $errors['general'] = 'No file uploaded or invalid request.';
}

// Redirect back to the profile page
 $_SESSION['upload_errors'] = $errors;
header("Location: guest_profile.php");
exit;   