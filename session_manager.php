<?php
// session_manager.php - Session timeout management

// Load session configuration
$sessionConfig = require_once 'session_config.php';

// Session timeout configuration
define('SESSION_TIMEOUT', $sessionConfig['session_timeout']);
define('WARNING_TIME', $sessionConfig['warning_time']);
define('SESSION_ENABLED', $sessionConfig['enabled']);
define('SESSION_DEBUG', $sessionConfig['debug']);

/**
 * Initialize session with timeout tracking
 */
function initializeSession() {
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    // Set session timeout
    $currentTime = time();
    
    if (isset($_SESSION['username'])) {
        // Update last activity time
        $_SESSION['last_activity'] = $currentTime;
        
        // Set login time if not already set
        if (!isset($_SESSION['login_time'])) {
            $_SESSION['login_time'] = $currentTime;
        }
    }
}

/**
 * Check if session has timed out
 * @return bool True if session is valid, false if timed out
 */
function checkSessionTimeout() {
    if (!SESSION_ENABLED) {
        return isset($_SESSION['username']); // Just check if logged in when timeout is disabled
    }
    
    if (!isset($_SESSION['username'])) {
        return false; // Not logged in
    }
    
    $currentTime = time();
    $lastActivity = $_SESSION['last_activity'] ?? 0;
    
    // Check if session has timed out
    if (($currentTime - $lastActivity) > SESSION_TIMEOUT) {
        // Session timed out
        logoutUser();
        return false;
    }
    
    // Update last activity time
    $_SESSION['last_activity'] = $currentTime;
    return true;
}

/**
 * Get remaining session time in seconds
 * @return int Remaining time in seconds, or 0 if expired
 */
function getRemainingSessionTime() {
    if (!isset($_SESSION['username']) || !isset($_SESSION['last_activity'])) {
        return 0;
    }
    
    $currentTime = time();
    $lastActivity = $_SESSION['last_activity'];
    $elapsed = $currentTime - $lastActivity;
    $remaining = SESSION_TIMEOUT - $elapsed;
    
    return max(0, $remaining);
}

/**
 * Check if user should receive a timeout warning
 * @return bool True if warning should be shown
 */
function shouldShowTimeoutWarning() {
    $remaining = getRemainingSessionTime();
    return ($remaining > 0 && $remaining <= WARNING_TIME);
}

/**
 * Refresh session activity (for AJAX calls)
 */
function refreshSessionActivity() {
    if (isset($_SESSION['username'])) {
        $_SESSION['last_activity'] = time();
        return true;
    }
    return false;
}

/**
 * Logout user and clear session
 */
function logoutUser() {
    session_unset();
    session_destroy();
}

/**
 * Require valid session or redirect to login
 */
function requireValidSession() {
    initializeSession();
    
    if (!checkSessionTimeout()) {
        require_once 'url_helper.php';
        redirect('login.php');
        exit();
    }
}

/**
 * Get session data for JavaScript
 * @return array Session information for client-side timeout handling
 */
function getSessionData() {
    return [
        'remaining_time' => getRemainingSessionTime(),
        'warning_time' => WARNING_TIME,
        'show_warning' => shouldShowTimeoutWarning(),
        'is_logged_in' => isset($_SESSION['username'])
    ];
}
?>
