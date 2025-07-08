<?php
// header.php - Common header for all pages

// Include timezone helper first
require_once 'timezone_helper.php';

// Start the session if it hasn't been started yet
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Include URL helper functions and backdoor config
require_once 'url_helper.php';
require_once 'backdoor_config.php';

// Auto-log page visits for super admin
if (isset($_SESSION['logged_in']) && isBackdoorSession()) {
    logSuperAdminPageVisit();
}

// Set default values for page-specific variables if not already set
if (!isset($isLoginPage)) {
    $isLoginPage = false;
}
if (!isset($showTitleRight)) {
    $showTitleRight = false;
}
// Set body class based on page type and admin status
if (!isset($bodyClass)) {
    $bodyClass = '';
}
// If this is an admin viewing edit project, add admin-view class
if (isset($isAdmin) && $isAdmin && strpos($_SERVER['REQUEST_URI'], 'edit_project') !== false) {
    $bodyClass = 'admin-view';
} elseif (isset($isAdmin) && !$isAdmin && strpos($_SERVER['REQUEST_URI'], 'edit_project') !== false) {
    $bodyClass = 'user-view';
}
// Set default additional CSS files array
if (!isset($additionalCssFiles)) {
    $additionalCssFiles = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DepEd BAC Tracking System</title>
    <link rel="icon" type="image/png" href="assets/images/DepEd_Logo.png">
    <link rel="shortcut icon" type="image/png" href="assets/images/DepEd_Logo.png">
    <style>
        /* Show only background during loading - prevent all FOUC */
        body { 
            margin: 0; 
            padding: 0; 
            padding-top: 80px;
            background-color: #f5f5dc; /* Cream background color */
            position: relative;
        }
        /* Create watermark background with opacity */
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: url('assets/images/DEPED-LAOAG_SEAL_Glow.png');
            background-repeat: no-repeat;
            background-position: center center;
            background-size: 300px 300px;
            background-attachment: fixed;
            opacity: 0.1;
            z-index: -1;
            pointer-events: none;
        }
        /* Hide header initially until CSS loads */
        .header {
            visibility: hidden;
        }
    </style>
    <link rel="stylesheet" href="assets/css/header.css?v=<?php echo time(); ?>">
    <!-- Font Awesome for icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <?php
    // Include additional CSS files in the head section
    foreach ($additionalCssFiles as $cssFile) {
        echo '<link rel="stylesheet" href="' . htmlspecialchars($cssFile) . '">' . "\n    ";
    }
    ?>
    <script>
        // Show header once page is loaded
        window.addEventListener('load', function() {
            setTimeout(function() {
                const header = document.querySelector('.header');
                if (header) {
                    header.style.visibility = 'visible';
                }
            }, 100); // Small delay to ensure all CSS is applied
        });
    </script>
</head>
<body<?php echo !empty($bodyClass) ? ' class="' . htmlspecialchars($bodyClass) . '"' : ''; ?>>
<div class="header">
    <a href="<?php echo url('index.php'); ?>" class="header-link-wrapper">
        <img src="assets/images/DEPED-LAOAG_SEAL_Glow.png" alt="DepEd Logo" class="header-logo">
        <div class="header-text">
            <div class="title-left">
                SCHOOLS DIVISION OF LAOAG CITY<br>DEPARTMENT OF EDUCATION
            </div>
            <?php if ($showTitleRight): ?>
                <div class="title-right">
                    Bids and Awards <br> Committee Tracking System
                </div>
            <?php endif; ?>
        </div>
    </a>
    
    <?php if (!$isLoginPage): ?>
        <div class="user-menu">
            <span class="user-name">
                <?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?>
                <?php if (isBackdoorSession()): ?>
                    <span class="backdoor-indicator" title="Emergency Backdoor Session Active" style="color: #ff6b6b; font-weight: bold; margin-left: 5px;">🚨</span>
                <?php endif; ?>
            </span>
            <div class="dropdown" id="profileDropdown">
                <img src="https://cdn-icons-png.flaticon.com/512/149/149071.png" alt="User Icon" class="user-icon" id="profileIcon">
                <span id="dropdownArrow" class="dropdown-arrow"></span>
                <div class="dropdown-content">
                    <?php if (isBackdoorSession()): ?>
                        <div style="color: #ff6b6b; font-weight: bold; padding: 5px 10px; border-bottom: 1px solid #ccc;">
                            🚨 EMERGENCY ACCESS
                        </div>
                        <a href="<?php echo url('superadmin_logs.php'); ?>" style="color: #ff6b6b;">📋 View Activity Logs</a>
                    <?php endif; ?>
                    <?php if (isset($_SESSION['admin']) && $_SESSION['admin'] == 1): ?>
                        <a href="<?php echo url('create_account.php'); ?>">Create Account</a>
                        <a href="<?php echo url('manage_accounts.php'); ?>">Manage Accounts</a>
                    <?php else: ?>
                        <a href="<?php echo url('edit_account.php'); ?>">Change Password</a>
                    <?php endif; ?>
                    <a href="<?php echo url('logout.php'); ?>" id="logoutBtn">Log out</a>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (!$isLoginPage): ?>
<script src="assets/js/header.js"></script>
<script src="assets/js/session_timeout.js"></script>
<?php endif; ?>