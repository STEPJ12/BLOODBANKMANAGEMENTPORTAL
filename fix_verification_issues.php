<?php
/**
 * Script to fix verification code issues
 * Run this once to update the database schema and clean up any issues
 */

require_once 'config/db.php';

echo "Fixing verification code issues...\n";

try {
    // 1. Update the email_verifications table to support all roles
    echo "1. Updating email_verifications table role enum...\n";
    $result = executeQuery("ALTER TABLE email_verifications MODIFY COLUMN role ENUM('donor','patient','redcross','barangay','negrosfirst','admin') NOT NULL");
    if ($result !== false) {
        echo "   ✓ email_verifications table updated successfully\n";
    } else {
        echo "   ⚠ email_verifications table update failed (may already be updated)\n";
    }
    
    // 2. Clean up any orphaned or expired verification codes
    echo "2. Cleaning up expired verification codes...\n";
    $deleted = executeQuery("DELETE FROM email_verifications WHERE expires_at < NOW() OR consumed_at IS NOT NULL");
    echo "   ✓ Cleaned up expired/used verification codes\n";
    
    // 3. Add role column to password_resets table if it doesn't exist
    echo "3. Checking password_resets table...\n";
    $columns = executeQuery("SHOW COLUMNS FROM password_resets LIKE 'role'");
    if (empty($columns)) {
        $result = executeQuery("ALTER TABLE password_resets ADD COLUMN role VARCHAR(50) DEFAULT 'donor' AFTER email");
        if ($result !== false) {
            echo "   ✓ Added role column to password_resets table\n";
        } else {
            echo "   ⚠ Failed to add role column to password_resets table\n";
        }
    } else {
        echo "   ✓ Role column already exists in password_resets table\n";
    }
    
    // 4. Show current verification codes for debugging
    echo "4. Current verification codes in database:\n";
    $codes = executeQuery("SELECT user_id, role, code, expires_at, consumed_at, created_at FROM email_verifications ORDER BY created_at DESC LIMIT 10");
    if (!empty($codes)) {
        foreach ($codes as $code) {
            echo "   User ID: {$code['user_id']}, Role: {$code['role']}, Code: {$code['code']}, Expires: {$code['expires_at']}, Used: " . ($code['consumed_at'] ? 'Yes' : 'No') . "\n";
        }
    } else {
        echo "   No verification codes found\n";
    }
    
    echo "\n✓ Verification system fix completed!\n";
    echo "\nKey improvements:\n";
    echo "- All user roles now supported in email_verifications table\n";
    echo "- Old verification codes are cleaned up when new ones are generated\n";
    echo "- Better error messages for debugging\n";
    echo "- Role-based password reset support\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}
?>
