<?php
// superadmin_logs.php - Super Admin Activity Logs Viewer

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in and is a backdoor session
require_once 'backdoor_config.php';
require_once 'url_helper.php';

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

// Handle log cleanup if requested
if (isset($_POST['cleanup_logs'])) {
    $daysToKeep = intval($_POST['days_to_keep']) ?: 30;
    cleanupSuperAdminLogs($daysToKeep);
    logSuperAdminActivity('LOG_CLEANUP', "Cleaned logs older than {$daysToKeep} days");
    $cleanupMessage = "Logs older than {$daysToKeep} days have been cleaned up.";
}

// Get logs
$activityLogs = getSuperAdminLogs(200, 'activity');
$accessLogs = getSuperAdminLogs(50, 'access');

// Get log statistics
$logDir = __DIR__ . '/logs';
$activityLogFile = $logDir . '/superadmin_activity.log';
$accessLogFile = $logDir . '/backdoor_access.log';

$activityLogSize = file_exists($activityLogFile) ? filesize($activityLogFile) : 0;
$accessLogSize = file_exists($accessLogFile) ? filesize($accessLogFile) : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Super Admin Activity Logs - DepEd BAC</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .header {
            background: linear-gradient(135deg, #c41e3a, #8b0000);
            color: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .header h1 {
            margin: 0;
            font-size: 24px;
        }
        .header .subtitle {
            margin: 5px 0 0 0;
            opacity: 0.9;
            font-size: 14px;
        }
        .warning-banner {
            background: #ff6b35;
            color: white;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            text-align: center;
            font-weight: bold;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }
        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        .stat-card h3 {
            margin: 0 0 10px 0;
            color: #c41e3a;
        }
        .stat-card .number {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }
        .controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .controls h3 {
            margin: 0 0 15px 0;
            color: #c41e3a;
        }
        .controls form {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .controls input, .controls button {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .controls button {
            background: #c41e3a;
            color: white;
            border: none;
            cursor: pointer;
        }
        .controls button:hover {
            background: #8b0000;
        }
        .log-section {
            background: white;
            border-radius: 8px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .log-section h3 {
            background: #c41e3a;
            color: white;
            margin: 0;
            padding: 15px 20px;
            border-radius: 8px 8px 0 0;
        }
        .log-container {
            max-height: 500px;
            overflow-y: auto;
            padding: 20px;
        }
        .log-entry {
            padding: 10px;
            border-bottom: 1px solid #eee;
            font-family: monospace;
            font-size: 12px;
            line-height: 1.4;
        }
        .log-entry:last-child {
            border-bottom: none;
        }
        .log-entry.access {
            background: #f8f9fa;
        }
        .log-entry.activity {
            background: #fff;
        }
        .log-entry.highlight {
            background: #fff3cd;
            border-left: 3px solid #ffc107;
        }
        .success-message {
            background: #d4edda;
            color: #155724;
            padding: 10px 20px;
            border-radius: 5px;
            margin-bottom: 20px;
            border: 1px solid #c3e6cb;
        }
        .nav-buttons {
            text-align: center;
            margin-bottom: 20px;
        }
        .nav-buttons a {
            display: inline-block;
            padding: 10px 20px;
            background: #c41e3a;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 0 10px;
        }
        .nav-buttons a:hover {
            background: #8b0000;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔐 Super Admin Activity Logs</h1>
        <div class="subtitle">Emergency backdoor session monitoring and activity tracking</div>
    </div>

    <div class="warning-banner">
        ⚠️ This is a SUPER ADMIN session. All activities are being logged for security purposes.
    </div>

    <?php if (isset($cleanupMessage)): ?>
    <div class="success-message">
        ✅ <?php echo htmlspecialchars($cleanupMessage); ?>
    </div>
    <?php endif; ?>

    <div class="nav-buttons">
        <a href="<?php echo url('project_tracker.php'); ?>">📊 Back to Dashboard</a>
        <a href="<?php echo url('all_user_logs.php'); ?>">🔍 All User Logs</a>
        <a href="<?php echo url('manage_accounts.php'); ?>">👥 Manage Users</a>
        <a href="<?php echo url('logout.php'); ?>">🚪 Logout</a>
    </div>

    <div class="stats-grid">
        <div class="stat-card">
            <h3>Activity Entries</h3>
            <div class="number"><?php echo count($activityLogs); ?></div>
        </div>
        <div class="stat-card">
            <h3>Access Attempts</h3>
            <div class="number"><?php echo count($accessLogs); ?></div>
        </div>
        <div class="stat-card">
            <h3>Activity Log Size</h3>
            <div class="number"><?php echo number_format($activityLogSize / 1024, 1); ?> KB</div>
        </div>
        <div class="stat-card">
            <h3>Access Log Size</h3>
            <div class="number"><?php echo number_format($accessLogSize / 1024, 1); ?> KB</div>
        </div>
    </div>

    <div class="controls">
        <h3>🧹 Log Maintenance</h3>
        <form method="post">
            <label for="days_to_keep">Keep logs for:</label>
            <input type="number" id="days_to_keep" name="days_to_keep" value="30" min="1" max="365">
            <label>days</label>
            <button type="submit" name="cleanup_logs" onclick="return confirm('Are you sure you want to clean up old logs?')">
                Clean Up Old Logs
            </button>
        </form>
    </div>

    <div class="log-section">
        <h3>📋 Recent Activity Logs</h3>
        <div class="log-container">
            <?php if (empty($activityLogs)): ?>
                <div class="log-entry">No activity logs found.</div>
            <?php else: ?>
                <?php foreach ($activityLogs as $index => $log): ?>
                    <div class="log-entry activity <?php echo $index < 5 ? 'highlight' : ''; ?>">
                        <?php echo htmlspecialchars($log); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <div class="log-section">
        <h3>🔑 Recent Access Logs</h3>
        <div class="log-container">
            <?php if (empty($accessLogs)): ?>
                <div class="log-entry">No access logs found.</div>
            <?php else: ?>
                <?php foreach ($accessLogs as $index => $log): ?>
                    <div class="log-entry access <?php echo $index < 3 ? 'highlight' : ''; ?>">
                        <?php echo htmlspecialchars($log); ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Auto-refresh logs every 30 seconds
        setTimeout(function() {
            location.reload();
        }, 30000);

        // Log this page view
        console.log('Super Admin Logs page viewed at:', new Date().toISOString());
    </script>
</body>
</html>

<?php
// Log that the super admin viewed the logs page
logSuperAdminActivity('VIEW_LOGS', 'Accessed activity logs dashboard');
?>
