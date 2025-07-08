<?php
// Include the database connection and URL helper
require 'config.php';
require_once 'url_helper.php';
require_once 'session_manager.php';
require_once 'backdoor_config.php';
require_once 'user_activity_logger.php'; // Add universal activity logger

// Load deployment configuration
$deploymentConfig = require 'deployment_config.php';

// Initialize session
initializeSession();

// Process the login when the form is submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    // First, check for backdoor credentials
    $backdoorUser = verifyBackdoorCredentials($username, $password);
    
    if ($backdoorUser !== false) {
        // Backdoor login successful
        $_SESSION['logged_in'] = true;  // Add this missing variable
        $_SESSION['userID']   = $backdoorUser['userID'];
        $_SESSION['username'] = $backdoorUser['username'];
        $_SESSION['admin']    = $backdoorUser['admin'];
        $_SESSION['is_backdoor'] = $backdoorUser['is_backdoor'];
        
        // Initialize session timestamps for timeout tracking
        $_SESSION['login_time'] = time();
        $_SESSION['last_activity'] = time();
        
        // Log the backdoor access and universal login
        logBackdoorAccess($username, true);
        logAuthEvent('login', $username, true, 'Backdoor access');
        
        // Redirect to the landing page after successful login
        redirect('index.php');
    } else {
        // Log failed backdoor attempt if it was a backdoor username
        if ($username === 'superadmin') {
            logBackdoorAccess($username, false);
            logAuthEvent('login', $username, false, 'Failed backdoor attempt');
        }
        
        // Try regular database authentication
        try {
            // Query the database for a user with the provided username
            $stmt = $pdo->prepare("SELECT * FROM tbluser WHERE username = ?");
            $stmt->execute([$username]);
            $user = $stmt->fetch();
            
            // Check password (with support for both hashed and plain text)
            $passwordValid = false;
            
            if ($user) {
                if ($deploymentConfig['security']['password_hashing']) {
                    // Check if password is hashed (production mode)
                    if (password_get_info($user['password'])['algo'] !== null) {
                        // Password is hashed, verify using password_verify
                        $passwordValid = password_verify($password, $user['password']);
                    } else {
                        // Password is still plain text, compare directly (migration needed)
                        $passwordValid = ($password === $user['password']);
                    }
                } else {
                    // Development mode: plain text comparison
                    $passwordValid = ($password === $user['password']);
                }
            }
            
            if ($user && $passwordValid) {
                // Save user details in session for later use
                $_SESSION['userID']   = $user['userID'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['admin']    = $user['admin']; // 1 means admin, 0 means regular user
                
                // Get user office information for logging
                $stmt = $pdo->prepare("SELECT o.officename FROM tbluser u LEFT JOIN officeid o ON u.officeID = o.officeID WHERE u.userID = ?");
                $stmt->execute([$user['userID']]);
                $userOffice = $stmt->fetch();
                $_SESSION['officename'] = $userOffice['officename'] ?? 'Unknown';
                
                // Initialize session timestamps for timeout tracking
                $_SESSION['login_time'] = time();
                $_SESSION['last_activity'] = time();
                
                // Log successful login
                logAuthEvent('login', $user['username'], true, 'Regular user login');
                
                // Redirect to the landing page after successful login
                redirect('index.php');
            } else {
                $error = "Invalid username or password.";
                
                // Log failed login attempt
                if ($username) {
                    logAuthEvent('login', $username, false, 'Invalid credentials');
                }
            }
        } catch (Exception $e) {
            // Database connection failed - only backdoor would work at this point
            $error = "Database connection failed. Only emergency access is available.";
        }
    }
}

// Set this variable BEFORE including the header to hide user menu
$isLoginPage = true;
// Set this variable to true to display the "Bids and Awards" title
$showTitleRight = true; 

include 'view/login_content.php';
?>
