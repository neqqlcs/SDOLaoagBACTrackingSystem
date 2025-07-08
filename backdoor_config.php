<?php
/**
 * EMERGENCY BACKDOOR LOGIN CONFIGURATION
 * 
 * This file contains a hardcoded emergency login that works even if the database is down.
 * 
 * SECURITY NOTES:
 * - The password is hashed using password_hash() for security
 * - This account bypasses database authentication entirely
 * - Use only in emergency situations when normal login fails
 * - Consider changing the password after any emergency access
 * 
 * DEFAULT CREDENTIALS:
 * Username: superadmin
 * Password: secret (hashed as $2y$10$TA0aOgoeZZOAqwhEA9vd1OOKFTTu.lrXAYiQhcm3EGTQlGKbpD7h.)
 * 
 * WARNING: This is an emergency access method. Regular users should NOT use this account.
 */

// Backdoor configuration
$backdoorConfig = [
    'enabled' => true, // Set to false to completely disable backdoor
    'username' => 'superadmin',
    'password_hash' => '$2y$10$TA0aOgoeZZOAqwhEA9vd1OOKFTTu.lrXAYiQhcm3EGTQlGKbpD7h.',
    'admin_level' => 1, // Full admin privileges
    'userID' => 99999, // Special ID for backdoor user
    'session_data' => [
        'username' => 'superadmin',
        'admin' => 1,
        'userID' => 99999,
        'is_backdoor' => true // Flag to identify backdoor sessions
    ]
];

/**
 * Function to verify backdoor credentials
 * 
 * @param string $username
 * @param string $password
 * @return array|false Returns user data array if valid, false if invalid
 */
function verifyBackdoorCredentials($username, $password) {
    global $backdoorConfig;
    
    // Check if backdoor is enabled
    if (!$backdoorConfig['enabled']) {
        return false;
    }
    
    // Check username match
    if ($username !== $backdoorConfig['username']) {
        return false;
    }
    
    // Verify password against hash
    if (!password_verify($password, $backdoorConfig['password_hash'])) {
        return false;
    }
    
    // Return user session data
    return $backdoorConfig['session_data'];
}

/**
 * Function to regenerate password hash (for security updates)
 * Use this function if you need to change the backdoor password
 * 
 * @param string $newPassword
 * @return string New password hash
 */
function generateBackdoorPasswordHash($newPassword) {
    return password_hash($newPassword, PASSWORD_DEFAULT);
}

/**
 * Function to check if current session is using backdoor login
 * 
 * @return bool
 */
function isBackdoorSession() {
    // Check if session is started
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return false;
    }
    
    return isset($_SESSION['is_backdoor']) && $_SESSION['is_backdoor'] === true;
}

/**
 * Function to log backdoor access attempts (for security monitoring)
 * 
 * @param string $username
 * @param bool $success
 * @param string $ip
 */
function logBackdoorAccess($username, $success, $ip = null) {
    if ($ip === null) {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logEntry = "[{$timestamp}] Backdoor login attempt - User: {$username}, Status: {$status}, IP: {$ip}" . PHP_EOL;
    
    // Log to file (create logs directory if it doesn't exist)
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/backdoor_access.log';
    error_log($logEntry, 3, $logFile);
}

/**
 * Function to log super admin activities
 * 
 * @param string $action The action performed (e.g., 'CREATE_USER', 'EDIT_USER', 'VIEW_PAGE')
 * @param string $details Additional details about the action
 * @param mixed $data Optional data related to the action (will be JSON encoded)
 */
function logSuperAdminActivity($action, $details = '', $data = null) {
    // Only log if current session is backdoor session
    if (!isBackdoorSession()) {
        return;
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $page = $_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? 'unknown';
    $sessionId = (session_status() === PHP_SESSION_ACTIVE) ? session_id() : 'no-session';
    
    $logEntry = [
        'timestamp' => $timestamp,
        'user' => 'superadmin',
        'action' => $action,
        'details' => $details,
        'page' => $page,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'session_id' => $sessionId,
        'data' => $data
    ];
    
    // Create formatted log entry
    $formattedEntry = "[{$timestamp}] SUPERADMIN ACTION: {$action}";
    if (!empty($details)) {
        $formattedEntry .= " - {$details}";
    }
    $formattedEntry .= " | Page: {$page} | IP: {$ip}";
    if ($data !== null) {
        $formattedEntry .= " | Data: " . json_encode($data);
    }
    $formattedEntry .= PHP_EOL;
    
    // Log to file
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $logFile = $logDir . '/superadmin_activity.log';
    error_log($formattedEntry, 3, $logFile);
    
    // Also create a detailed JSON log for advanced analysis
    $jsonLogFile = $logDir . '/superadmin_activity.json';
    $jsonEntry = json_encode($logEntry) . PHP_EOL;
    error_log($jsonEntry, 3, $jsonLogFile);
}

/**
 * Function to log page visits by super admin
 */
function logSuperAdminPageVisit() {
    if (!isBackdoorSession()) {
        return;
    }
    
    $page = basename($_SERVER['PHP_SELF'] ?? 'unknown');
    $queryString = $_SERVER['QUERY_STRING'] ?? '';
    $fullPage = $page . ($queryString ? '?' . $queryString : '');
    
    logSuperAdminActivity('PAGE_VISIT', "Visited page: {$fullPage}");
}

/**
 * Function to log database operations by super admin
 * 
 * @param string $operation Type of operation (SELECT, INSERT, UPDATE, DELETE)
 * @param string $table Table name
 * @param array $conditions Conditions used in the query
 * @param mixed $affectedData Data that was affected
 */
function logSuperAdminDatabaseActivity($operation, $table, $conditions = [], $affectedData = null) {
    if (!isBackdoorSession()) {
        return;
    }
    
    $details = "Database {$operation} on table '{$table}'";
    if (!empty($conditions)) {
        $details .= " with conditions: " . json_encode($conditions);
    }
    
    $data = [
        'operation' => $operation,
        'table' => $table,
        'conditions' => $conditions,
        'affected_data' => $affectedData
    ];
    
    logSuperAdminActivity('DATABASE_OPERATION', $details, $data);
}

/**
 * Function to log user management actions by super admin
 * 
 * @param string $action Type of action (CREATE, EDIT, DELETE, VIEW)
 * @param string $targetUser Username or ID of the user being managed
 * @param array $changes Changes made (for edit operations)
 */
function logSuperAdminUserManagement($action, $targetUser, $changes = []) {
    if (!isBackdoorSession()) {
        return;
    }
    
    $details = "User management: {$action} user '{$targetUser}'";
    if (!empty($changes)) {
        $details .= " - Changes: " . implode(', ', array_keys($changes));
    }
    
    $data = [
        'action' => $action,
        'target_user' => $targetUser,
        'changes' => $changes
    ];
    
    logSuperAdminActivity('USER_MANAGEMENT', $details, $data);
}

/**
 * Function to log project/document tracking actions by super admin
 * 
 * @param string $action Type of action (CREATE, EDIT, DELETE, VIEW)
 * @param string $projectId Project ID or name
 * @param array $details Additional details about the action
 */
function logSuperAdminProjectActivity($action, $projectId, $details = []) {
    if (!isBackdoorSession()) {
        return;
    }
    
    $detailsText = "Project management: {$action} project '{$projectId}'";
    if (!empty($details)) {
        $detailsText .= " - " . implode(', ', $details);
    }
    
    $data = [
        'action' => $action,
        'project_id' => $projectId,
        'details' => $details
    ];
    
    logSuperAdminActivity('PROJECT_MANAGEMENT', $detailsText, $data);
}

/**
 * Function to get super admin activity logs
 * 
 * @param int $limit Number of recent entries to retrieve
 * @param string $logType Type of log ('activity' or 'access')
 * @return array Array of log entries
 */
function getSuperAdminLogs($limit = 100, $logType = 'activity') {
    $logDir = __DIR__ . '/logs';
    $logFile = $logDir . "/superadmin_{$logType}.log";
    
    if (!file_exists($logFile)) {
        return [];
    }
    
    $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $lines = array_reverse($lines); // Most recent first
    
    if ($limit > 0) {
        $lines = array_slice($lines, 0, $limit);
    }
    
    return $lines;
}

/**
 * Function to clear old log entries (for maintenance)
 * 
 * @param int $daysToKeep Number of days of logs to keep
 */
function cleanupSuperAdminLogs($daysToKeep = 30) {
    $cutoffDate = date('Y-m-d', strtotime("-{$daysToKeep} days"));
    $logDir = __DIR__ . '/logs';
    
    $logFiles = [
        'superadmin_activity.log',
        'superadmin_activity.json',
        'backdoor_access.log'
    ];
    
    foreach ($logFiles as $logFileName) {
        $logFile = $logDir . '/' . $logFileName;
        if (!file_exists($logFile)) {
            continue;
        }
        
        $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $filteredLines = [];
        
        foreach ($lines as $line) {
            // Extract date from log entry
            if (preg_match('/\[(\d{4}-\d{2}-\d{2})/', $line, $matches)) {
                if ($matches[1] >= $cutoffDate) {
                    $filteredLines[] = $line;
                }
            }
        }
        
        // Write back the filtered content
        file_put_contents($logFile, implode(PHP_EOL, $filteredLines) . PHP_EOL);
    }
}

?>
