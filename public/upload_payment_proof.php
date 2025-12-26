<?php
// =============================================================================
// public/upload_payment_proof.php - Handles Payment Proof Upload
// =============================================================================
session_start();

// Prevent caching
header("Expires: Tue, 01 Jan 2000 00:00:00 GMT");
header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

require __DIR__ . '/../vendor/autoload.php';
use Lourdian\MonbelaHotel\Model\Booking;

// Restrict Access
if (!isset($_SESSION['id']) || ($_SESSION['role'] ?? '') !== 'guest') {
    $_SESSION['error_message'] = 'Unauthorized access.';
    header('Location: my_reservations.php');
    exit;
}

// Check if the form was submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['reservation_id'])) {
    $_SESSION['error_message'] = 'Invalid request.';
    header('Location: my_reservations.php');
    exit;
}

$reservationId = (int)$_POST['reservation_id'];
$guestId = (int)$_SESSION['id'];

// --- 1. Authorization using the Booking Class ---
try {
    $bookingModel = new Booking();
    $reservation = $bookingModel->getByIdAndGuestId($reservationId, $guestId);
    
    if (!$reservation) {
        $_SESSION['error_message'] = 'You do not have permission to modify this reservation.';
        header('Location: my_reservations.php');
        exit;
    }
} catch (Exception $e) {
    error_log("Error fetching reservation: " . $e->getMessage());
    $_SESSION['error_message'] = 'An error occurred. Please try again.';
    header('Location: my_reservations.php');
    exit;
}

// --- 2. File Upload Logic ---
if (!isset($_FILES['payment_proof']) || $_FILES['payment_proof']['error'] !== UPLOAD_ERR_OK) {
    $uploadErrors = [
        UPLOAD_ERR_INI_SIZE => 'The file exceeds the upload_max_filesize directive in php.ini.',
        UPLOAD_ERR_FORM_SIZE => 'The file exceeds the MAX_FILE_SIZE directive in the HTML form.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Missing a temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.'
    ];
    
    $errorCode = $_FILES['payment_proof']['error'] ?? UPLOAD_ERR_NO_FILE;
    $errorMessage = $uploadErrors[$errorCode] ?? 'Unknown upload error.';
    
    $_SESSION['error_message'] = 'File upload failed: ' . $errorMessage;
    header('Location: my_reservations.php');
    exit;
}

$file = $_FILES['payment_proof'];

// IMPORTANT: Update this path to match your project structure
// Since the payment files are stored in C:\xampp\htdocs\MonbelaHotel\uploads\payment\
$uploadDir = 'C:/xampp/htdocs/MonbelaHotel/uploads/payment/';
$webPath = '/MonbelaHotel/uploads/payment/'; // Web accessible path

// Create the directory if it doesn't exist
if (!is_dir($uploadDir)) {
    if (!mkdir($uploadDir, 0755, true)) {
        $_SESSION['error_message'] = 'Failed to create upload directory.';
        header('Location: my_reservations.php');
        exit;
    }
}

// --- 3. File Security Checks ---
$allowedMimeTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'application/pdf'];
$allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'pdf'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// Check file size
if ($file['size'] > $maxFileSize) {
    $_SESSION['error_message'] = 'File is too large. Maximum size is 5MB.';
    header('Location: my_reservations.php');
    exit;
}

// Check file extension
$fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
if (!in_array($fileExtension, $allowedExtensions)) {
    $_SESSION['error_message'] = 'Invalid file extension. Allowed types: ' . implode(', ', $allowedExtensions);
    header('Location: my_reservations.php');
    exit;
}

// Check MIME type for security
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mimeType = $finfo->file($file['tmp_name']);

if (!in_array($mimeType, $allowedMimeTypes)) {
    $_SESSION['error_message'] = 'Invalid file type. Only JPG, PNG, GIF, WEBP, and PDF are allowed.';
    header('Location: my_reservations.php');
    exit;
}

// Additional security: Check if it's really an image (except for PDFs)
if ($mimeType !== 'application/pdf') {
    $imageInfo = getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        $_SESSION['error_message'] = 'Invalid image file.';
        header('Location: my_reservations.php');
        exit;
    }
}

// Generate filename matching your pattern: payment_{reservationId}_{timestamp}.{extension}
$timestamp = time();
$newFileName = 'payment_' . $reservationId . '_' . $timestamp . '.' . $fileExtension;
$destinationPath = $uploadDir . $newFileName;

// Check if a payment file already exists for this reservation and delete it
$existingFiles = glob($uploadDir . 'payment_' . $reservationId . '_*');
foreach ($existingFiles as $existingFile) {
    if (file_exists($existingFile)) {
        unlink($existingFile);
    }
}

// --- 4. Move File and Update Database ---
if (move_uploaded_file($file['tmp_name'], $destinationPath)) {
    // File moved successfully, now update the database
    $publicPath = $webPath . $newFileName;
    
    try {
        if ($bookingModel->updatePaymentProof($reservationId, $publicPath)) {
            $_SESSION['success_message'] = 'Payment proof uploaded successfully!';
            
            // Log the successful upload
            error_log("Payment proof uploaded: Reservation ID: $reservationId, File: $newFileName");
        } else {
            // Database update failed, remove the uploaded file
            unlink($destinationPath);
            $_SESSION['error_message'] = 'Could not save file information. Please try again.';
        }
    } catch (Exception $e) {
        // Remove the uploaded file on database error
        if (file_exists($destinationPath)) {
            unlink($destinationPath);
        }
        error_log("Database error during payment upload: " . $e->getMessage());
        $_SESSION['error_message'] = 'Database error occurred. Please try again.';
    }
} else {
    $uploadError = error_get_last();
    error_log("File move failed: " . ($uploadError['message'] ?? 'Unknown error'));
    $_SESSION['error_message'] = 'Error moving the uploaded file. Please check file permissions.';
}

// Redirect back to the reservations page
header('Location: my_reservations.php');
exit;