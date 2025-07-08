<?php
// all_user_logs.php - Universal Activity Logs Viewer for All Users

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a backdoor session
require_once 'backdoor_config.php';
require_once 'url_helper.php';
require_once 'user_activity_logger.php';

// Simple verification first - check if this is coming from a logged-in user
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    // Not logged in at all
    header('Location: login.php?error=not_logged_in');
    exit;
}

// Check if this is a backdoor session
if (!isBackdoorSession()) {
    // Logged in but not a backdoor session
    header('Location: ' . url('project_tracker.php') . '?error=access_denied');
    exit;
}

// Log this page visit
logSuperAdminPageVisit();
logPageVisit();

// Handle log cleanup if requested
if (isset($_POST['cleanup_logs'])) {
    $daysToKeep = intval($_POST['days_to_keep']) ?: 30;
    cleanupUserActivityLogs($daysToKeep);
    logUserActivity('LOG_CLEANUP', "Cleaned all user logs older than {$daysToKeep} days", ['days_kept' => $daysToKeep]);
    $cleanupMessage = "All user logs older than {$daysToKeep} days have been cleaned up.";
}

// Get filter parameters
$userRole = $_GET['role'] ?? 'all';
$logLevel = $_GET['level'] ?? 'all';
$limit = intval($_GET['limit'] ?? 200);

// Get logs
$activityLogs = getUserActivityLogs($limit, $userRole, $logLevel);

// Get log statistics
$logDir = __DIR__ . '/logs';
$logFiles = [
    'user_activity.log',
    'activity_admin.log',
    'activity_user.log',
    'activity_superadmin.log'
];

$logStats = [];
foreach ($logFiles as $logFile) {
    $filePath = $logDir . '/' . $logFile;
    if (file_exists($filePath)) {
        $logStats[$logFile] = [
            'size' => filesize($filePath),
            'lines' => count(file($filePath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)),
            'last_modified' => filemtime($filePath)
        ];
    } else {
        $logStats[$logFile] = [
            'size' => 0,
            'lines' => 0,
            'last_modified' => null
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All User Activity Logs - DepEd BAC Tracking System</title>
    <link rel="stylesheet" href="assets/css/background.css">
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: #f5f7fa;
            margin: 0;
            padding: 20px;
            color: #333;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: white;
            border-radius: 8px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            padding: 30px;
        }

        .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
            padding-bottom: 20px;
            border-bottom: 2px solid #e9ecef;
        }

        .header h1 {
            color: #2c5aa0;
            margin: 0;
            font-size: 2.2em;
        }

        .back-btn {
            background-color: #007bff;
            color: white;
            padding: 10px 20px;
            text-decoration: none;
            border-radius: 5px;
            font-weight: 500;
            transition: background-color 0.3s;
        }

        .back-btn:hover {
            background-color: #0056b3;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 1.1em;
            opacity: 0.9;
        }

        .stat-card .value {
            font-size: 1.8em;
            font-weight: bold;
        }

        .filters {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 30px;
            border: 1px solid #dee2e6;
        }

        .filters h3 {
            margin-top: 0;
            color: #495057;
        }

        .filter-row {
            display: flex;
            gap: 20px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-weight: 500;
            color: #495057;
        }

        .filter-group select, .filter-group input {
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
        }

        .btn {
            background-color: #28a745;
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 4px;
            cursor: pointer;
            font-weight: 500;
            text-decoration: none;
            display: inline-block;
        }

        .btn:hover {
            background-color: #218838;
        }

        .btn-danger {
            background-color: #dc3545;
        }

        .btn-danger:hover {
            background-color: #c82333;
        }

        .logs-container {
            background: #fff;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            overflow: hidden;
        }

        .logs-header {
            background: #f8f9fa;
            padding: 15px 20px;
            border-bottom: 1px solid #dee2e6;
            font-weight: bold;
            color: #495057;
        }

        .log-entry {
            padding: 15px 20px;
            border-bottom: 1px solid #eee;
            font-family: 'Courier New', monospace;
            font-size: 14px;
            line-height: 1.5;
        }

        .log-entry:last-child {
            border-bottom: none;
        }

        .log-entry:hover {
            background-color: #f8f9fa;
        }

        .log-level {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 8px;
        }

        .log-level.INFO {
            background-color: #d1ecf1;
            color: #0c5460;
        }

        .log-level.WARNING {
            background-color: #fff3cd;
            color: #856404;
        }

        .log-level.ERROR {
            background-color: #f8d7da;
            color: #721c24;
        }

        .log-level.AUDIT {
            background-color: #d4edda;
            color: #155724;
        }

        .user-role {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 3px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 8px;
        }

        .user-role.superadmin {
            background-color: #dc3545;
            color: white;
        }

        .user-role.admin {
            background-color: #fd7e14;
            color: white;
        }

        .user-role.user {
            background-color: #6c757d;
            color: white;
        }

        .timestamp {
            color: #6c757d;
            font-weight: bold;
        }

        .username {
            color: #007bff;
            font-weight: bold;
        }

        .action-details {
            color: #495057;
            margin-top: 5px;
        }

        .cleanup-section {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 30px;
        }

        .cleanup-section h3 {
            color: #856404;
            margin-top: 0;
        }

        .success-message {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
            padding: 12px 20px;
            border-radius: 4px;
            margin-bottom: 20px;
        }

        @media (max-width: 768px) {
            .container {
                padding: 15px;
            }

            .header {
                flex-direction: column;
                gap: 15px;
                text-align: center;
            }

            .filter-row {
                flex-direction: column;
                align-items: stretch;
            }

            .stat-card .value {
                font-size: 1.5em;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔍 All User Activity Logs</h1>
            <a href="<?php echo url('superadmin_logs.php'); ?>" class="back-btn">← Back to Super Admin Logs</a>
        </div>

        <?php if (isset($cleanupMessage)): ?>
            <div class="success-message">
                ✅ <?php echo htmlspecialchars($cleanupMessage); ?>
            </div>
        <?php endif; ?>

        <!-- Statistics -->
        <div class="stats-grid">
            <?php foreach ($logStats as $logFile => $stats): ?>
                <div class="stat-card">
                    <h3><?php echo str_replace(['_', '.log'], [' ', ''], ucfirst($logFile)); ?></h3>
                    <div class="value"><?php echo number_format($stats['lines']); ?></div>
                    <small><?php echo $stats['size'] > 0 ? number_format($stats['size'] / 1024, 1) . ' KB' : '0 KB'; ?></small>
                </div>
            <?php endforeach; ?>
        </div>

        <!-- Filters -->
        <div class="filters">
            <h3>🎛️ Filter Logs</h3>
            <form method="GET" class="filter-row">
                <div class="filter-group">
                    <label for="role">User Role:</label>
                    <select name="role" id="role">
                        <option value="all" <?php echo $userRole === 'all' ? 'selected' : ''; ?>>All Roles</option>
                        <option value="superadmin" <?php echo $userRole === 'superadmin' ? 'selected' : ''; ?>>Super Admin</option>
                        <option value="admin" <?php echo $userRole === 'admin' ? 'selected' : ''; ?>>Admin</option>
                        <option value="user" <?php echo $userRole === 'user' ? 'selected' : ''; ?>>User</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="level">Log Level:</label>
                    <select name="level" id="level">
                        <option value="all" <?php echo $logLevel === 'all' ? 'selected' : ''; ?>>All Levels</option>
                        <option value="INFO" <?php echo $logLevel === 'INFO' ? 'selected' : ''; ?>>Info</option>
                        <option value="WARNING" <?php echo $logLevel === 'WARNING' ? 'selected' : ''; ?>>Warning</option>
                        <option value="ERROR" <?php echo $logLevel === 'ERROR' ? 'selected' : ''; ?>>Error</option>
                        <option value="AUDIT" <?php echo $logLevel === 'AUDIT' ? 'selected' : ''; ?>>Audit</option>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label for="limit">Limit:</label>
                    <input type="number" name="limit" id="limit" value="<?php echo $limit; ?>" min="10" max="1000" step="10">
                </div>
                
                <button type="submit" class="btn">Apply Filters</button>
            </form>
        </div>

        <!-- Log Cleanup -->
        <div class="cleanup-section">
            <h3>🧹 Log Cleanup</h3>
            <p>Clean up old log entries to keep the logs manageable. This will remove entries older than the specified number of days from all user activity logs.</p>
            <form method="POST" style="display: inline-block;">
                <div class="filter-row">
                    <div class="filter-group">
                        <label for="days_to_keep">Keep logs for (days):</label>
                        <input type="number" name="days_to_keep" id="days_to_keep" value="30" min="1" max="365">
                    </div>
                    <button type="submit" name="cleanup_logs" class="btn btn-danger" onclick="return confirm('Are you sure you want to clean up old logs? This action cannot be undone.')">Clean Up Logs</button>
                </div>
            </form>
        </div>

        <!-- Activity Logs -->
        <div class="logs-container">
            <div class="logs-header">
                📋 Activity Logs (<?php echo count($activityLogs); ?> entries)
            </div>
            <?php if (empty($activityLogs)): ?>
                <div class="log-entry">
                    <em>No activity logs found with the current filters.</em>
                </div>
            <?php else: ?>
                <?php foreach ($activityLogs as $log): ?>
                    <div class="log-entry">
                        <span class="timestamp">[<?php echo htmlspecialchars($log['timestamp']); ?>]</span>
                        <span class="log-level <?php echo htmlspecialchars($log['log_level']); ?>"><?php echo htmlspecialchars($log['log_level']); ?></span>
                        <span class="user-role <?php echo htmlspecialchars($log['user_role']); ?>"><?php echo htmlspecialchars(strtoupper($log['user_role'])); ?></span>
                        <span class="username"><?php echo htmlspecialchars($log['username']); ?></span>
                        (ID: <?php echo htmlspecialchars($log['user_id']); ?>)
                        <div class="action-details">
                            <strong>Action:</strong> <?php echo htmlspecialchars($log['action_details']); ?><br>
                            <strong>Page:</strong> <?php echo htmlspecialchars($log['page']); ?><br>
                            <strong>IP:</strong> <?php echo htmlspecialchars($log['ip']); ?><br>
                            <strong>Office:</strong> <?php echo htmlspecialchars($log['office']); ?>
                            <?php if (!empty($log['data'])): ?>
                                <br><strong>Data:</strong> <code><?php echo htmlspecialchars($log['data']); ?></code>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh every 30 seconds if no filters are applied
        if (window.location.search === '' || window.location.search === '?') {
            setTimeout(function() {
                window.location.reload();
            }, 30000);
        }
    </script>
</body>
</html>

<?php
// Log that the logs were viewed
logUserActivity('VIEW_ALL_LOGS', 'Accessed universal activity logs dashboard', [
    'role_filter' => $userRole,
    'level_filter' => $logLevel,
    'limit' => $limit,
    'logs_count' => count($activityLogs)
]);
?>
