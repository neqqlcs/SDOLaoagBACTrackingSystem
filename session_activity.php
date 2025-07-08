<?php
// session_activity.php - AJAX endpoint for session activity tracking

require_once 'session_manager.php';

// Set content type for JSON response
header('Content-Type: application/json');

// Initialize session
initializeSession();

$response = ['status' => 'error', 'message' => 'Invalid request'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'refresh':
            // Refresh session activity
            if (refreshSessionActivity()) {
                $response = [
                    'status' => 'success',
                    'remaining_time' => getRemainingSessionTime(),
                    'show_warning' => shouldShowTimeoutWarning()
                ];
            } else {
                $response = [
                    'status' => 'expired',
                    'message' => 'Session expired'
                ];
            }
            break;
            
        case 'check':
            // Check session status
            if (checkSessionTimeout()) {
                $response = [
                    'status' => 'active',
                    'remaining_time' => getRemainingSessionTime(),
                    'show_warning' => shouldShowTimeoutWarning()
                ];
            } else {
                $response = [
                    'status' => 'expired',
                    'message' => 'Session expired'
                ];
            }
            break;
            
        case 'extend':
            // Extend session (user clicked "Stay logged in")
            if (isset($_SESSION['username'])) {
                $_SESSION['last_activity'] = time();
                $response = [
                    'status' => 'extended',
                    'remaining_time' => getRemainingSessionTime(),
                    'show_warning' => false
                ];
            } else {
                $response = [
                    'status' => 'expired',
                    'message' => 'Session expired'
                ];
            }
            break;
            
        default:
            $response['message'] = 'Unknown action';
    }
}

echo json_encode($response);
?>
