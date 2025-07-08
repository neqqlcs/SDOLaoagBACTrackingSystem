<?php
require_once 'url_helper.php';
require_once 'session_manager.php';
require_once 'user_activity_logger.php';

// Initialize session
initializeSession();

// Log logout before destroying session
if (isset($_SESSION['username'])) {
    logAuthEvent('logout', $_SESSION['username'], true, 'User logout');
}

// Logout user
logoutUser();

// Redirect to login page using encrypted URL
redirect('login.php');
?>
