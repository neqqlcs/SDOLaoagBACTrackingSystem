<?php
// Start the session if it hasn't been started yet
require 'config.php'; // Ensure this file exists and contains PDO connection
require_once 'url_helper.php'; // Include URL helper functions
require_once 'session_manager.php'; // Include session management
require_once 'backdoor_config.php'; // Include backdoor logging functions
require_once 'user_activity_logger.php'; // Add universal activity logger

// Load deployment configuration
$deploymentConfig = require 'deployment_config.php';

// Check session and redirect if expired
requireValidSession();

// Log page visit
logPageVisit();

// Only admin users can access this page.
if ($_SESSION['admin'] != 1) {
    redirect('index.php');
    exit();
}

$editSuccess = "";
$deleteSuccess = "";
$error = "";

// Fetch office names and IDs from the database for the dropdown
$officeList = [];
try {
    $stmtOffices = $pdo->query("SELECT officeID, officename FROM officeid ORDER BY officeID");
    while ($office = $stmtOffices->fetch()) {
        $officeList[$office['officeID']] = $office['officename'];
    }
} catch (PDOException $e) {
    $error = "Error fetching office list: " . $e->getMessage();
}

// Process deletion if a 'delete' GET parameter is provided.
if (isset($_GET['delete'])) {
    $deleteID = intval($_GET['delete']);
    try {
        // Get user info before deletion for logging
        $stmt = $pdo->prepare("SELECT username, firstname, lastname FROM tbluser WHERE userID = ?");
        $stmt->execute([$deleteID]);
        $userToDelete = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->prepare("DELETE FROM tbluser WHERE userID = ?");
        $stmt->execute([$deleteID]);
        
        // Log the deletion
        if ($userToDelete) {
            $deletedUserName = $userToDelete['username'];
            $deletedFullName = $userToDelete['firstname'] . ' ' . $userToDelete['lastname'];
            
            logSuperAdminUserManagement('DELETE', $deletedUserName, [
                'user_id' => $deleteID,
                'full_name' => $deletedFullName
            ]);
            logSuperAdminDatabaseActivity('DELETE', 'tbluser', ['userID' => $deleteID], $userToDelete);
            
            // Universal logging for all users
            logUserManagement('DELETE', $deletedUserName, [
                'user_id' => $deleteID,
                'full_name' => $deletedFullName
            ], 'Account deleted by admin');
            logDatabaseActivity('DELETE', 'tbluser', ['userID' => $deleteID], $userToDelete, 'User account deletion');
        }
        
        $deleteSuccess = "Account deleted successfully.";
    } catch (PDOException $e) {
        $error = "Error deleting account: " . $e->getMessage();
    }
}

// Process editing if the form is submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['editAccount'])) {
    $editUserID = intval($_POST['editUserID']);
    $firstname  = trim($_POST['firstname']);
    $middlename = trim($_POST['middlename'] ?? "");
    $lastname   = trim($_POST['lastname']);
    $position   = trim($_POST['position'] ?? "");
    $username   = trim($_POST['username']);
    $password   = trim($_POST['password']);    // If empty, do not update password.
    $adminFlag  = isset($_POST['admin']) ? 1 : 0;
    $officeName = trim($_POST['office']); // Now comes from a select dropdown

    if (empty($firstname) || empty($lastname) || empty($username) || empty($officeName)) {
        $error = "Please fill in all required fields for editing.";
    } else {
        try {
            // Extract the office ID from the selected option (format: "1 - OSDS", "2 - ADMIN", etc.)
            // Use regex to get the number before the hyphen, or if no hyphen, try to match by name
            if (preg_match('/^(\d+)\s*-\s*.*/', $officeName, $matches)) {
                $officeID = intval($matches[1]);
            } else {
                // Fallback: if format is just "OSDS", try to find by name
                $stmtOffice = $pdo->prepare("SELECT officeID FROM officeid WHERE officename = ?");
                $stmtOffice->execute([$officeName]);
                $office = $stmtOffice->fetch();
                if ($office) {
                    $officeID = $office['officeID'];
                } else {
                    // Default to office ID 1 if no match found
                    $officeID = 1;
                    $error = "Warning: Office name did not match an existing office. Defaulting to Office ID 1.";
                }
            }

            // Update the account. If password is provided, hash it first.
            if (!empty($password)) {
                // Hash the password for security
                if ($deploymentConfig['security']['password_hashing']) {
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                } else {
                    // For development/testing - store plain text (not recommended)
                    $hashedPassword = $password;
                }
                
                $stmtEdit = $pdo->prepare("UPDATE tbluser SET firstname = ?, middlename = ?, lastname = ?, position = ?, username = ?, password = ?, admin = ?, officeID = ? WHERE userID = ?");
                $stmtEdit->execute([$firstname, $middlename, $lastname, $position, $username, $hashedPassword, $adminFlag, $officeID, $editUserID]);
                
                // Log the edit with password change
                $changes = [
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'username' => $username,
                    'password' => 'CHANGED',
                    'admin' => $adminFlag,
                    'officeID' => $officeID
                ];
            } else {
                $stmtEdit = $pdo->prepare("UPDATE tbluser SET firstname = ?, middlename = ?, lastname = ?, position = ?, username = ?, admin = ?, officeID = ? WHERE userID = ?");
                $stmtEdit->execute([$firstname, $middlename, $lastname, $position, $username, $adminFlag, $officeID, $editUserID]);
                
                // Log the edit without password change
                $changes = [
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'username' => $username,
                    'admin' => $adminFlag,
                    'officeID' => $officeID
                ];
            }
            
            // Log user management activity
            logSuperAdminUserManagement('EDIT', $username, $changes);
            logSuperAdminDatabaseActivity('UPDATE', 'tbluser', ['userID' => $editUserID], $changes);
            
            // Universal logging for all users
            logUserManagement('EDIT', $username, $changes, 'Account updated by admin');
            logDatabaseActivity('UPDATE', 'tbluser', ['userID' => $editUserID], $changes, 'User account modification');
            
            $editSuccess = "Account updated successfully.";
        } catch(PDOException $e) {
            $error = "Error updating account: " . $e->getMessage();
        }
    }
}

// Retrieve all accounts along with their office names and IDs.
$stmt = $pdo->query("SELECT u.*, o.officeID, o.officename FROM tbluser u LEFT JOIN officeid o ON u.officeID = o.officeID ORDER BY u.userID ASC");
$accounts = $stmt->fetchAll();

include 'view/manage_accounts_content.php';
?>
