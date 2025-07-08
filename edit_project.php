<?php
// Include required files
require 'config.php';
require_once 'url_helper.php';
require_once 'session_manager.php';
require_once 'backdoor_config.php'; // Include backdoor logging functions

// Helper function to get userID for database operations
// Returns NULL for backdoor sessions since userID 99999 doesn't exist in DB
function getSafeUserID() {
    return (isset($_SESSION['is_backdoor']) && $_SESSION['is_backdoor']) ? null : $_SESSION['userID'];
}

// Check session and redirect if expired
requireValidSession();

// Get the projectID from GET parameters.
$projectID = isset($_GET['projectID']) ? intval($_GET['projectID']) : 0;
if ($projectID <= 0) {
    die("Invalid Project ID");
}

// Permission Variables
$isAdmin = ($_SESSION['admin'] == 1);
$isProjectCreator = false;

// --- Define the Office List (fetched dynamically) ---
$officeList = [];
try {
    $stmtOffice = $pdo->query("SELECT officeID, officename FROM officeid ORDER BY officename");
    while ($row = $stmtOffice->fetch(PDO::FETCH_ASSOC)) {
        $officeList[$row['officeID']] = $row['officename'];
    }
} catch (PDOException $e) {
    error_log("Error fetching office list: " . $e->getMessage());
    die("Could not retrieve office list. Please try again later.");
}

// --- Get the logged-in user's office details ---
$loggedInUserOfficeID = null;
$loggedInUserOfficeName = "N/A";
if (isset($_SESSION['userID'])) {
    try {
        $stmtUserOffice = $pdo->prepare("SELECT u.officeID, o.officename FROM tbluser u LEFT JOIN officeid o ON u.officeID = o.officeID WHERE u.userID = ?");
        $stmtUserOffice->execute([$_SESSION['userID']]);
        $userOfficeData = $stmtUserOffice->fetch(PDO::FETCH_ASSOC);
        if ($userOfficeData) {
            $loggedInUserOfficeID = $userOfficeData['officeID'];
            $loggedInUserOfficeName = htmlspecialchars($userOfficeData['officename'] ?? 'N/A');
        }
    } catch (PDOException $e) {
        error_log("Error fetching logged-in user office details: " . $e->getMessage());
    }
}

// --- Function to fetch project details ---
function fetchProjectDetails($pdo, $projectID) {
    $sql = "
        SELECT
            p.*,
            u.firstname AS creator_firstname,
            u.lastname AS creator_lastname,
            o.officename,
            mop.MoPDescription
        FROM tblproject p
        LEFT JOIN tbluser u ON p.userID = u.userID
        LEFT JOIN officeid o ON u.officeID = o.officeID
        LEFT JOIN mode_of_procurement mop ON p.MoPID = mop.MoPID
        WHERE p.projectID = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$projectID]);
    return $stmt->fetch();
}

// --- Function to fetch project stages (ordered by stageID) ---
function fetchProjectStages($pdo, $projectID) {
    $stmt2 = $pdo->prepare("SELECT * FROM tblproject_stages WHERE projectID = ? ORDER BY stageID ASC");
    $stmt2->execute([$projectID]);
    $stages = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    return $stages;
}

// --- Initial Data Fetch ---
$project = fetchProjectDetails($pdo, $projectID);
if (!$project) {
    die("Project not found");
}
$isProjectCreator = ($project['userID'] == $_SESSION['userID']);

// Fetch stage order from reference table
$stmtStageRef = $pdo->query("SELECT stageName FROM stage_reference ORDER BY stageOrder ASC");
$stagesOrder = $stmtStageRef->fetchAll(PDO::FETCH_COLUMN);

// Exclude "Mode Of Procurement" from submittable stages
$submittableStages = array_filter($stagesOrder, function($stage) {
    return $stage !== 'Mode Of Procurement';
});

// Fetch all stages for this project, ordered by stageID
$stages = fetchProjectStages($pdo, $projectID);

// Update "Last Accessed By" every time someone views the Edit Project page
// For backdoor users, use NULL since the userID doesn't exist in the database
$lastAccessedBy = getSafeUserID();
$stmtUpdateAccess = $pdo->prepare("UPDATE tblproject SET lastAccessedAt = CURRENT_TIMESTAMP, lastAccessedBy = ? WHERE projectID = ?");
$stmtUpdateAccess->execute([$lastAccessedBy, $projectID]);

// Map stages by stageName for easy access and find the last submitted stage.
$stagesMap = [];
$lastSubmittedStageIndex = -1;

foreach ($stagesOrder as $index => $stageName) {
    $s = null;
    foreach ($stages as $stage) {
        if ($stage['stageName'] === $stageName) {
            $s = $stage;
            break;
        }
    }
    if ($s) {
        $stagesMap[$stageName] = $s;
        if ($s['isSubmitted'] == 1) {
            $lastSubmittedStageIndex = $index;
        }
    }
}
$lastSubmittedStageName = ($lastSubmittedStageIndex !== -1) ? $stagesOrder[$lastSubmittedStageIndex] : null;

// Process Project Header update (available ONLY for admins).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_header'])) {
    if ($isAdmin) {
        $prNumber = trim($_POST['prNumber']);
        $projectDetails = trim($_POST['projectDetails']);
        $totalABC = isset($_POST['totalABC']) ? floatval($_POST['totalABC']) : null;
        
        if (empty($prNumber) || empty($projectDetails)) {
            $errorHeader = "PR Number and Project Details are required.";
        } else {
            $stmtUpdate = $pdo->prepare("UPDATE tblproject
                                             SET prNumber = ?, projectDetails = ?, totalABC = ?, editedAt = CURRENT_TIMESTAMP, editedBy = ?
                                             WHERE projectID = ?");
            $stmtUpdate->execute([$prNumber, $projectDetails, $totalABC, getSafeUserID(), $projectID]);

            // Log the project update for super admin
            if (function_exists('logSuperAdminActivity')) {
                logSuperAdminActivity('UPDATE_PROJECT', "Updated project header for Project ID: $projectID");
            }

            // Redirect to refresh the page and reload all variables properly using encrypted URL
            redirect('edit_project.php', ['projectID' => $projectID, 'updated' => '1']);
        }
    } else {
        $errorHeader = "You do not have permission to update project details.";
    }
}

// Process Project Status update (available ONLY for admins).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_project_status'])) {
    if ($isAdmin) {
        $newStatus = $_POST['update_project_status'];
        if (in_array($newStatus, ['in-progress', 'finished'])) {
            try {
                $stmtUpdateStatus = $pdo->prepare("UPDATE tblproject 
                                                   SET projectStatus = ?, editedAt = CURRENT_TIMESTAMP, editedBy = ? 
                                                   WHERE projectID = ?");
                $stmtUpdateStatus->execute([$newStatus, getSafeUserID(), $projectID]);
                
                $statusText = $newStatus === 'finished' ? 'Finished' : 'In Progress';
                $successHeader = "Project status updated to '$statusText' successfully.";
                
                // Refresh project data
                $project = fetchProjectDetails($pdo, $projectID);
            } catch (PDOException $e) {
                error_log("Error updating project status: " . $e->getMessage());
                $errorHeader = "Failed to update project status. Please try again.";
            }
        } else {
            $errorHeader = "Invalid project status value.";
        }
    } else {
        $errorHeader = "You do not have permission to update project status.";
    }
}

// Process individual stage submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_stage'])) {
    $stageName = $_POST['stageName'] ?? '';
    $approvedAt = $_POST['approvedAt'] ?? '';
    $remark = $_POST['remark'] ?? '';

    // Debug logging (remove in production)
    error_log("Stage submission attempt - Stage: $stageName, ApprovedAt: $approvedAt, ProjectID: $projectID");

    // Validate required fields
    if (empty($stageName) || empty($approvedAt)) {
        $_SESSION['stageError'] = "Stage name and approved date/time are required.";
        error_log("Stage submission failed - missing required fields");
    } else {
        try {
            // Check if stage exists before updating
            $stmtCheck = $pdo->prepare("SELECT stageID FROM tblproject_stages WHERE projectID = ? AND stageName = ?");
            $stmtCheck->execute([$projectID, $stageName]);
            $stageExists = $stmtCheck->fetch();
            
            if (!$stageExists) {
                $_SESSION['stageError'] = "Stage '$stageName' does not exist.";
                error_log("Stage submission failed - stage does not exist: $stageName");
            } else {
                // Update the stage
                $stmt = $pdo->prepare("UPDATE tblproject_stages SET approvedAt = ?, remarks = ?, isSubmitted = 1 WHERE projectID = ? AND stageName = ?");
                $result = $stmt->execute([$approvedAt, $remark, $projectID, $stageName]);
                
                if ($result && $stmt->rowCount() > 0) {
                    // Update last edited info on the project
                    $stmtUpdateProject = $pdo->prepare("UPDATE tblproject SET editedAt = CURRENT_TIMESTAMP, editedBy = ? WHERE projectID = ?");
                    $stmtUpdateProject->execute([getSafeUserID(), $projectID]);

                    // Set the success message
                    $_SESSION['stageSuccess'] = "Stage '$stageName' has been successfully submitted!";
                    error_log("Stage submission successful - Stage: $stageName");
                } else {
                    $_SESSION['stageError'] = "Failed to update stage '$stageName'. Please try again.";
                    error_log("Stage submission failed - database update failed for stage: $stageName");
                }
            }
        } catch (PDOException $e) {
            $_SESSION['stageError'] = "Database error occurred while submitting stage.";
            error_log("Stage submission database error: " . $e->getMessage());
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: edit_project.php?projectID=$projectID");
    exit;
}

// Process new stage creation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_stage'])) {
    $stageName = $_POST['stageName'];
    // Prevent duplicate insertion
    if (!isset($stagesMap[$stageName])) {
        $stmt = $pdo->prepare("INSERT INTO tblproject_stages (projectID, stageName, createdAt, officeID, isSubmitted) VALUES (?, ?, NOW(), ?, 0)");
        $stmt->execute([$projectID, $stageName, $loggedInUserOfficeID]);

        // Update last edited info on the project
        $stmtUpdateProject = $pdo->prepare("UPDATE tblproject SET editedAt = CURRENT_TIMESTAMP, editedBy = ? WHERE projectID = ?");
        $stmtUpdateProject->execute([getSafeUserID(), $projectID]);
        
        // Set the success message
        $_SESSION['stageSuccess'] = "Stage '$stageName' has been successfully created!";
    }
    header("Location: edit_project.php?projectID=$projectID");
    exit;
}

// Process stage deletion (admin only)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_stage'])) {
    if (!$isAdmin) {
        $_SESSION['stageError'] = "You do not have permission to delete stages.";
    } else {
        $stageName = $_POST['stageName'] ?? '';
        
        if (empty($stageName)) {
            $_SESSION['stageError'] = "Stage name is required for deletion.";
        } else {
            try {
                // Check if stage exists before deleting
                $stmtCheck = $pdo->prepare("SELECT stageID FROM tblproject_stages WHERE projectID = ? AND stageName = ?");
                $stmtCheck->execute([$projectID, $stageName]);
                $stageExists = $stmtCheck->fetch();
                
                if (!$stageExists) {
                    $_SESSION['stageError'] = "Stage '$stageName' does not exist.";
                } else {
                    // Delete the stage
                    $stmt = $pdo->prepare("DELETE FROM tblproject_stages WHERE projectID = ? AND stageName = ?");
                    $result = $stmt->execute([$projectID, $stageName]);
                    
                    if ($result && $stmt->rowCount() > 0) {
                        // Update last edited info on the project
                        $stmtUpdateProject = $pdo->prepare("UPDATE tblproject SET editedAt = CURRENT_TIMESTAMP, editedBy = ? WHERE projectID = ?");
                        $stmtUpdateProject->execute([getSafeUserID(), $projectID]);

                        // Set the success message
                        $_SESSION['stageSuccess'] = "Stage '$stageName' has been successfully deleted!";
                        error_log("Stage deletion successful - Stage: $stageName");
                    } else {
                        $_SESSION['stageError'] = "Failed to delete stage '$stageName'. Please try again.";
                        error_log("Stage deletion failed - database delete failed for stage: $stageName");
                    }
                }
            } catch (PDOException $e) {
                $_SESSION['stageError'] = "Database error occurred while deleting stage.";
                error_log("Stage deletion database error: " . $e->getMessage());
            }
        }
    }
    
    // Redirect to prevent form resubmission
    header("Location: edit_project.php?projectID=$projectID");
    exit;
}

// --- Pre-fetch names for display: Edited By ---
$editedByName = "N/A";
if (!empty($project['editedBy'])) {
    $stmtUser = $pdo->prepare("SELECT firstname, lastname FROM tbluser WHERE userID = ?");
    $stmtUser->execute([$project['editedBy']]);
    $user = $stmtUser->fetch();
    if ($user) {
        $editedByName = htmlspecialchars($user['firstname'] . " " . $user['lastname']);
    }
}

// --- Pre-fetch names for display: Last Accessed By ---
$lastAccessedByName = "N/A";
if (!empty($project['lastAccessedBy'])) {
    $stmtUser = $pdo->prepare("SELECT firstname, lastname FROM tbluser WHERE userID = ?");
    $stmtUser->execute([$project['lastAccessedBy']]);
    $user = $stmtUser->fetch();
    if ($user) {
        $lastAccessedByName = htmlspecialchars($user['firstname'] . " " . $user['lastname']);
    }
}

// Get all unsubmitted stages except "Mode Of Procurement"
$unsubmittedStages = [];
foreach ($stagesOrder as $stage) {
    if ($stage === 'Mode Of Procurement') continue;
    if (isset($stagesMap[$stage]) && $stagesMap[$stage]['isSubmitted'] == 0) {
        $unsubmittedStages[] = $stage;
    }
}

// --- Determine the "Next Unsubmitted Stage" for strict sequential access ---
$firstUnsubmittedStageName = null;
foreach ($stagesOrder as $stage) {
    if ($stage === 'Mode Of Procurement') continue; // <-- skip MoP
    if (isset($stagesMap[$stage]) && $stagesMap[$stage]['isSubmitted'] == 0) {
        $firstUnsubmittedStageName = $stage;
        break;
    }
}

// After updating the stage in the database:
$stages = fetchProjectStages($pdo, $projectID); // re-fetch latest data

// Rebuild stages map using the same logic as initial load
$stagesMap = [];
$lastSubmittedStageIndex = -1;

foreach ($stagesOrder as $index => $stageName) {
    $s = null;
    foreach ($stages as $stage) {
        if ($stage['stageName'] === $stageName) {
            $s = $stage;
            break;
        }
    }
    if ($s) {
        $stagesMap[$stageName] = $s;
        if ($s['isSubmitted'] == 1) {
            $lastSubmittedStageIndex = $index;
        }
    }
}
$lastSubmittedStageName = ($lastSubmittedStageIndex !== -1) ? $stagesOrder[$lastSubmittedStageIndex] : null;

// Check for success message from redirect
if (isset($_GET['updated']) && $_GET['updated'] == '1') {
    $successHeader = "Project details updated successfully.";
}

include 'view/edit_project_content.php';
?>