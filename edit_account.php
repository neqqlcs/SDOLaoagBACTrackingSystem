<?php
require 'config.php'; // Ensure your PDO connection is set up correctly
require_once 'url_helper.php';
require_once 'session_manager.php';
require_once 'backdoor_config.php';

// Load deployment configuration
$deploymentConfig = require 'deployment_config.php';

// Check session and redirect if expired
requireValidSession();

// Prevent backdoor users from accessing account editing
if (isBackdoorSession()) {
    header('Location: ' . url('project_tracker.php') . '?error=backdoor_no_account');
    exit;
}

$userID = $_SESSION['userID'];
$error = "";
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $oldPassword = trim($_POST['old_password'] ?? '');
    $newPassword = trim($_POST['new_password'] ?? '');
    $confirmNewPassword = trim($_POST['confirm_new_password'] ?? '');

    // Fetch current user info to get the stored password
    $stmtUser = $pdo->prepare("SELECT password FROM tbluser WHERE userID = ?");
    $stmtUser->execute([$userID]);
    $user = $stmtUser->fetch();

    if (!$user) {
        $error = "User not found.";
    } elseif (empty($oldPassword) || empty($newPassword) || empty($confirmNewPassword)) {
        $error = "All password fields are required.";
    } elseif ($newPassword !== $confirmNewPassword) {
        $error = "New password and confirm new password do not match.";
    } elseif (empty($newPassword)) { // New password cannot be empty
        $error = "New password cannot be empty.";
    } else {
        // Verify old password (supports both hashed and plain text)
        $oldPasswordValid = false;
        
        if ($deploymentConfig['security']['password_hashing']) {
            // Check if current password is hashed
            if (password_get_info($user['password'])['algo'] !== null) {
                // Password is hashed, verify using password_verify
                $oldPasswordValid = password_verify($oldPassword, $user['password']);
            } else {
                // Password is still plain text, compare directly
                $oldPasswordValid = ($oldPassword === $user['password']);
            }
        } else {
            // Development mode: plain text comparison
            $oldPasswordValid = ($oldPassword === $user['password']);
        }
        
        if (!$oldPasswordValid) {
            $error = "Old password does not match.";
        } else {
            try {
                // Hash the new password
                if ($deploymentConfig['security']['password_hashing']) {
                    $hashedNewPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                } else {
                    // For development/testing - store plain text (not recommended)
                    $hashedNewPassword = $newPassword;
                }
                
                $stmt = $pdo->prepare("UPDATE tbluser SET password = ? WHERE userID = ?");
                $stmt->execute([$hashedNewPassword, $userID]);
                $success = true;
            } catch (PDOException $e) {
                $error = "Error updating password: " . $e->getMessage();
            }
        }
    }
}

// Fetch current user info for display (even if not changing password)
$stmt = $pdo->prepare("SELECT u.*, o.officeID, o.officename FROM tbluser u LEFT JOIN officeid o ON u.officeID = o.officeID WHERE u.userID = ?");
$stmt->execute([$userID]);
$user = $stmt->fetch();
include 'view/edit_account_content.php';
?>
