<?php
// session_config.php - Session timeout configuration

// Session timeout configuration
return [
    // Main session timeout (2 minutes for testing - change to 30*60 for production)
    'session_timeout' => 60 * 60,
    
    // Warning time before timeout (20 seconds for testing - change to 5*60 for production)
    'warning_time' => 10 * 60,
    
    // Session check frequency on client side (30 seconds in milliseconds)
    'check_frequency' => 30000,
    
    // Activity tracking delay (5 seconds in milliseconds)
    'activity_delay' => 5000,
    
    // Enable/disable session timeout feature
    'enabled' => true,
    
    // Show debugging information (set to false in production)
    'debug' => false
];

/*
PRODUCTION CONFIGURATION:
For production use, change the values above to:
    'session_timeout' => 30 * 60,  // 30 minutes
    'warning_time' => 5 * 60,      // 5 minutes
*/
?>
