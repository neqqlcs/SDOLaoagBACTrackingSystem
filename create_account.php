<?php
// Include required files
require 'config.php'; // Ensure this file exists and contains PDO connection
require_once 'url_helper.php';
require_once 'session_manager.php';
require_once 'backdoor_config.php'; // Include backdoor logging functions
require_once 'user_activity_logger.php'; // Add universal activity logger

// Load deployment configuration
$deploymentConfig = require 'deployment_config.php';

// Check session and redirect if expired
requireValidSession();

// Log page visit
logPageVisit();

// Allow only admin users to create accounts.
if ($_SESSION['admin'] != 1) {
    redirect('index.php');
    exit();
}

$success = false;
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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and trim the form values.
    $firstname   = trim($_POST['firstname']);
    $middlename  = trim($_POST['middlename'] ?? "");
    $lastname    = trim($_POST['lastname']);
    $position    = trim($_POST['position'] ?? "");
    $username    = trim($_POST['username']);
    $password    = trim($_POST['password']);
    $adminFlag   = isset($_POST['admin']) ? 1 : 0;
    $officeName  = trim($_POST['office']);      // Now comes from a select dropdown
    
    // Hash the password for security (production ready)
    if ($deploymentConfig['security']['password_hashing']) {
        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
    } else {
        // For development/testing - store plain text (not recommended)
        $hashedPassword = $password;
    }

    // Basic validation—check that required fields are filled.
    // Also check that a valid office was selected (not the empty default option)
    if(empty($firstname) || empty($lastname) || empty($username) || empty($password) || empty($officeName)){
       $error = "Please fill in all required fields.";
    } else {
        try {
            // Find the office ID from the selected office name
            $stmtOffice = $pdo->prepare("SELECT officeID FROM officeid WHERE officename = ?");
            $stmtOffice->execute([$officeName]);
            $office = $stmtOffice->fetch();
            
            if (!$office) {
                $error = "Invalid office selected.";
            } else {
                $officeID = $office['officeID'];

                // Now insert the new user into tbluser with hashed password.
                $stmtUser = $pdo->prepare("INSERT INTO tbluser (firstname, middlename, lastname, position, username, password, admin, officeID) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmtUser->execute([$firstname, $middlename, $lastname, $position, $username, $hashedPassword, $adminFlag, $officeID]);

                $success = true;
                // Retrieve the newly created account details using the auto-generated userID.
                $newAccountID = $pdo->lastInsertId();
                $stmt2 = $pdo->prepare("SELECT u.*, o.officename FROM tbluser u LEFT JOIN officeid o ON u.officeID = o.officeID WHERE u.userID = ?");
                $stmt2->execute([$newAccountID]);
                $newAccount = $stmt2->fetch();

                // Log the account creation
                $userData = [
                    'user_id' => $newAccountID,
                    'firstname' => $firstname,
                    'lastname' => $lastname,
                    'username' => $username,
                    'admin' => $adminFlag,
                    'officeID' => $officeID
                ];
                logSuperAdminUserManagement('CREATE', $username, $userData);
                logSuperAdminDatabaseActivity('INSERT', 'tbluser', [], $userData);
                
                // Universal logging for all users
                logUserManagement('CREATE', $username, $userData, 'Account created by admin');
                logDatabaseActivity('INSERT', 'tbluser', [], $userData, 'New user account creation');
            }

        } catch (PDOException $e) {
            $error = "Error creating account: " . $e->getMessage();
        }
    }
}
include 'view/create_account_content.php';
?>
