<?php
/**
 * Password Migration Script
 * 
 * This script converts all existing plain text passwords in the database to hashed passwords.
 * Run this ONCE before deploying the application with the new password hashing system.
 * 
 * WARNING: This script should be deleted after running to prevent unauthorized access.
 */

require 'config.php';

echo "<!DOCTYPE html>\n<html>\n<head>\n<title>Password Migration</title>\n</head>\n<body>";
echo "<h1>Password Migration Script</h1>\n";

try {
    // Fetch all users from the database
    $stmt = $pdo->query("SELECT userID, username, password FROM tbluser");
    $users = $stmt->fetchAll();
    
    $migratedCount = 0;
    $skippedCount = 0;
    
    echo "<p>Starting password migration...</p>\n";
    echo "<ul>\n";
    
    foreach ($users as $user) {
        // Check if password is already hashed (starts with $2y$ for bcrypt)
        if (password_get_info($user['password'])['algo'] === null) {
            // Password is not hashed, hash it now
            $hashedPassword = password_hash($user['password'], PASSWORD_DEFAULT);
            
            // Update the password in the database
            $updateStmt = $pdo->prepare("UPDATE tbluser SET password = ? WHERE userID = ?");
            $updateStmt->execute([$hashedPassword, $user['userID']]);
            
            echo "<li>✓ Migrated password for user: " . htmlspecialchars($user['username']) . "</li>\n";
            $migratedCount++;
        } else {
            // Password is already hashed, skip it
            echo "<li>- Skipped user: " . htmlspecialchars($user['username']) . " (already hashed)</li>\n";
            $skippedCount++;
        }
    }
    
    echo "</ul>\n";
    echo "<h2>Migration Complete!</h2>\n";
    echo "<p><strong>Summary:</strong></p>\n";
    echo "<ul>\n";
    echo "<li>Passwords migrated: " . $migratedCount . "</li>\n";
    echo "<li>Passwords skipped (already hashed): " . $skippedCount . "</li>\n";
    echo "<li>Total users processed: " . count($users) . "</li>\n";
    echo "</ul>\n";
    
    if ($migratedCount > 0) {
        echo "<div style='background-color: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h3>✓ Migration Successful!</h3>\n";
        echo "<p>All plain text passwords have been successfully converted to secure hashes.</p>\n";
        echo "<p><strong>Important:</strong> Please delete this migration script file (migrate_passwords.php) for security reasons.</p>\n";
        echo "</div>\n";
    } else {
        echo "<div style='background-color: #d1ecf1; border: 1px solid #bee5eb; color: #0c5460; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<h3>ℹ No Migration Needed</h3>\n";
        echo "<p>All passwords were already hashed. No changes were made.</p>\n";
        echo "</div>\n";
    }
    
} catch (PDOException $e) {
    echo "<div style='background-color: #f8d7da; border: 1px solid #f5c6cb; color: #721c24; padding: 15px; border-radius: 5px; margin: 20px 0;'>\n";
    echo "<h3>❌ Migration Failed</h3>\n";
    echo "<p>Error: " . htmlspecialchars($e->getMessage()) . "</p>\n";
    echo "</div>\n";
}

echo "<hr>\n";
echo "<p><strong>Next Steps:</strong></p>\n";
echo "<ol>\n";
echo "<li>Test the login system to ensure all users can still log in</li>\n";
echo "<li>Delete this migration script file for security</li>\n";
echo "<li>Deploy your application</li>\n";
echo "</ol>\n";

echo "</body>\n</html>";
?>
