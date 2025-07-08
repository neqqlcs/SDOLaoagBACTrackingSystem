<?php
/**
 * Universal Activity Logger
 * Logs all user activities in the DepEd BAC Tracking System
 */

/**
 * Log any user activity
 * @param string $action The action being performed
 * @param string $details Additional details about the action
 * @param mixed $data Optional data related to the action (will be JSON encoded)
 * @param string $logLevel Log level: 'INFO', 'WARNING', 'ERROR', 'AUDIT'
 */
function logUserActivity($action, $details = '', $data = null, $logLevel = 'INFO') {
    // Ensure session is started
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'unknown');
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
    $page = $_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? 'unknown';
    
    // Get user information
    $userId = $_SESSION['userID'] ?? 'anonymous';
    $username = $_SESSION['username'] ?? 'anonymous';
    $userRole = ($_SESSION['admin'] ?? 0) == 1 ? 'admin' : 'user';
    $userOffice = $_SESSION['officename'] ?? 'unknown';
    
    // Check if this is a backdoor/superadmin session
    $isBackdoor = function_exists('isBackdoorSession') ? isBackdoorSession() : false;
    if ($isBackdoor) {
        $userRole = 'superadmin';
        $username = 'superadmin';
        $userId = 'backdoor';
    }
    
    $logEntry = [
        'timestamp' => $timestamp,
        'user_id' => $userId,
        'username' => $username,
        'user_role' => $userRole,
        'user_office' => $userOffice,
        'action' => $action,
        'details' => $details,
        'page' => $page,
        'ip' => $ip,
        'user_agent' => $userAgent,
        'log_level' => $logLevel,
        'data' => $data
    ];
    
    // Create formatted log entry
    $formattedEntry = "[{$timestamp}] {$logLevel} [{$userRole}] {$username} ({$userId}) - {$action}";
    if (!empty($details)) {
        $formattedEntry .= " - {$details}";
    }
    $formattedEntry .= " | Page: {$page} | IP: {$ip} | Office: {$userOffice}";
    
    if ($data !== null) {
        $formattedEntry .= " | Data: " . json_encode($data);
    }
    
    // Ensure logs directory exists
    $logDir = __DIR__ . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    // Write to main activity log
    $logFile = $logDir . '/user_activity.log';
    file_put_contents($logFile, $formattedEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    // Also write to role-specific log for easier filtering
    $roleLogFile = $logDir . "/activity_{$userRole}.log";
    file_put_contents($roleLogFile, $formattedEntry . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    // For backward compatibility, also call superadmin logging if it's a backdoor session
    if ($isBackdoor && function_exists('logSuperAdminActivity')) {
        // Don't create infinite loop - check if we're already in superadmin logging
        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 2);
        $callingFunction = $trace[1]['function'] ?? '';
        if ($callingFunction !== 'logSuperAdminActivity') {
            logSuperAdminActivity($action, $details, $data);
        }
    }
}

/**
 * Log page visits
 */
function logPageVisit($page = null) {
    if ($page === null) {
        $page = $_SERVER['REQUEST_URI'] ?? $_SERVER['PHP_SELF'] ?? 'unknown';
    }
    
    logUserActivity('PAGE_VISIT', "Visited page: {$page}");
}

/**
 * Log user authentication events
 */
function logAuthEvent($action, $username, $success = true, $details = '') {
    $level = $success ? 'INFO' : 'WARNING';
    $status = $success ? 'SUCCESS' : 'FAILED';
    $actionDetails = "Authentication {$action} {$status} for user: {$username}";
    if (!empty($details)) {
        $actionDetails .= " - {$details}";
    }
    
    logUserActivity('AUTH_' . strtoupper($action), $actionDetails, [
        'username' => $username,
        'success' => $success,
        'auth_action' => $action
    ], $level);
}

/**
 * Log database operations
 */
function logDatabaseActivity($operation, $table, $conditions = [], $affectedData = null, $details = '') {
    $actionDetails = "Database {$operation} on table '{$table}'";
    if (!empty($conditions)) {
        $actionDetails .= " with conditions: " . json_encode($conditions);
    }
    if (!empty($details)) {
        $actionDetails .= " - {$details}";
    }
    
    logUserActivity('DATABASE_OPERATION', $actionDetails, [
        'operation' => $operation,
        'table' => $table,
        'conditions' => $conditions,
        'affected_data' => $affectedData
    ], 'AUDIT');
}

/**
 * Log user management activities
 */
function logUserManagement($action, $targetUser, $changes = [], $details = '') {
    $changesList = is_array($changes) ? implode(', ', array_keys($changes)) : $changes;
    $actionDetails = "User management: {$action} user '{$targetUser}'";
    if (!empty($changesList)) {
        $actionDetails .= " - Changes: {$changesList}";
    }
    if (!empty($details)) {
        $actionDetails .= " - {$details}";
    }
    
    logUserActivity('USER_MANAGEMENT', $actionDetails, [
        'action' => $action,
        'target_user' => $targetUser,
        'changes' => $changes
    ], 'AUDIT');
}

/**
 * Log project management activities
 */
function logProjectActivity($action, $projectId, $details = [], $customDetails = '') {
    $actionDetails = "Project management: {$action} project '{$projectId}'";
    if (is_array($details) && !empty($details)) {
        $detailsList = [];
        foreach ($details as $key => $value) {
            $detailsList[] = "{$key}: {$value}";
        }
        $actionDetails .= " - " . implode(', ', $detailsList);
    } elseif (is_string($details) && !empty($details)) {
        $actionDetails .= " - {$details}";
    }
    if (!empty($customDetails)) {
        $actionDetails .= " - {$customDetails}";
    }
    
    logUserActivity('PROJECT_MANAGEMENT', $actionDetails, [
        'action' => $action,
        'project_id' => $projectId,
        'details' => $details
    ], 'AUDIT');
}

/**
 * Log system errors
 */
function logSystemError($error, $details = '', $data = null) {
    logUserActivity('SYSTEM_ERROR', "Error: {$error} - {$details}", $data, 'ERROR');
}

/**
 * Log security events
 */
function logSecurityEvent($event, $details = '', $data = null) {
    logUserActivity('SECURITY_EVENT', "Security: {$event} - {$details}", $data, 'WARNING');
}

/**
 * Get user activity logs
 * @param int $limit Number of log entries to retrieve
 * @param string $userRole Filter by user role ('all', 'admin', 'user', 'superadmin')
 * @param string $logLevel Filter by log level ('all', 'INFO', 'WARNING', 'ERROR', 'AUDIT')
 * @return array Array of log entries
 */
function getUserActivityLogs($limit = 100, $userRole = 'all', $logLevel = 'all') {
    $logDir = __DIR__ . '/logs';
    $logs = [];
    
    // Determine which log files to read
    $logFiles = [];
    if ($userRole === 'all') {
        $logFiles[] = $logDir . '/user_activity.log';
    } else {
        $logFiles[] = $logDir . "/activity_{$userRole}.log";
    }
    
    foreach ($logFiles as $logFile) {
        if (file_exists($logFile)) {
            $lines = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            if ($lines) {
                // Get the last $limit lines and reverse to show newest first
                $lines = array_slice($lines, -$limit);
                $lines = array_reverse($lines);
                
                foreach ($lines as $line) {
                    // Parse log entry
                    if (preg_match('/\[(.*?)\]\s+(\w+)\s+\[(.*?)\]\s+(.*?)\s+\((.*?)\)\s+-\s+(.*?)\s+\|\s+Page:\s+(.*?)\s+\|\s+IP:\s+(.*?)\s+\|\s+Office:\s+(.*?)(?:\s+\|\s+Data:\s+(.*))?$/', $line, $matches)) {
                        $logEntry = [
                            'timestamp' => $matches[1],
                            'log_level' => $matches[2],
                            'user_role' => $matches[3],
                            'username' => $matches[4],
                            'user_id' => $matches[5],
                            'action_details' => $matches[6],
                            'page' => $matches[7],
                            'ip' => $matches[8],
                            'office' => $matches[9],
                            'data' => isset($matches[10]) ? $matches[10] : null,
                            'raw_line' => $line
                        ];
                        
                        // Filter by log level if specified
                        if ($logLevel !== 'all' && $logEntry['log_level'] !== $logLevel) {
                            continue;
                        }
                        
                        $logs[] = $logEntry;
                    } else {
                        // If parsing fails, include the raw line
                        $logs[] = [
                            'timestamp' => 'unknown',
                            'log_level' => 'unknown',
                            'user_role' => 'unknown',
                            'username' => 'unknown',
                            'user_id' => 'unknown',
                            'action_details' => 'unknown',
                            'page' => 'unknown',
                            'ip' => 'unknown',
                            'office' => 'unknown',
                            'data' => null,
                            'raw_line' => $line
                        ];
                    }
                }
            }
        }
    }
    
    // Sort by timestamp (newest first)
    usort($logs, function($a, $b) {
        return strtotime($b['timestamp']) - strtotime($a['timestamp']);
    });
    
    return array_slice($logs, 0, $limit);
}

/**
 * Clean up old log files
 * @param int $daysToKeep Number of days to keep logs
 */
function cleanupUserActivityLogs($daysToKeep = 30) {
    $logDir = __DIR__ . '/logs';
    $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
    
    $logFiles = [
        'user_activity.log',
        'activity_admin.log',
        'activity_user.log',
        'activity_superadmin.log'
    ];
    
    foreach ($logFiles as $logFile) {
        $filePath = $logDir . '/' . $logFile;
        if (file_exists($filePath)) {
            $lines = file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $filteredLines = [];
            
            foreach ($lines as $line) {
                // Extract timestamp from log line
                if (preg_match('/\[(.*?)\]/', $line, $matches)) {
                    $timestamp = strtotime($matches[1]);
                    if ($timestamp > $cutoffTime) {
                        $filteredLines[] = $line;
                    }
                }
            }
            
            // Write back filtered lines
            file_put_contents($filePath, implode(PHP_EOL, $filteredLines) . PHP_EOL);
        }
    }
    
    logUserActivity('LOG_CLEANUP', "Cleaned logs older than {$daysToKeep} days", ['days_kept' => $daysToKeep], 'INFO');
}
?>
