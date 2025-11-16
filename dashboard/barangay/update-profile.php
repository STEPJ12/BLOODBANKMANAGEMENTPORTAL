<?php
// Start session
session_start();

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'barangay') {
    header("Location: ../../barangay-login.php?role=barangay");
    exit;
}

// Include database connection
require_once '../../config/db.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $barangayId = $_SESSION['user_id'];
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $address = $_POST['address'];

    // Update barangay information
    $success = executeQuery("
        UPDATE barangay_users 
        SET name = ?, email = ?, phone = ?, address = ?
        WHERE id = ?
    ", [$name, $email, $phone, $address, $barangayId]);

    if ($success) {
        $_SESSION['user_name'] = $name; // Update session name
        $_SESSION['success_message'] = "Profile updated successfully!";
    } else {
        $_SESSION['error_message'] = "Failed to update profile. Please try again.";
    }
}

// Redirect back to profile page
header("Location: profile.php");
exit; 