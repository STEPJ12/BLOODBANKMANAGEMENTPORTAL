<?php
// Include security middleware
require_once '../../includes/negrosfirst_auth.php';
require_once '../../config/db.php';

// Initialize a mysqli connection for maintenance routines that use mysqli_*
global $db_host, $db_name, $db_user, $db_pass;
global $conn; // make available to functions below

// Ensure variables are set (they should be from db.php)
if (!isset($db_host) || !isset($db_name) || !isset($db_user)) {
    // Fallback values if not set
    $db_host = $db_host ?? 'localhost';
    $db_name = $db_name ?? 'blood_bank_portal';
    $db_user = $db_user ?? 'root';
    $db_pass = $db_pass ?? '';
}

// Create mysqli connection
$conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);

if (!$conn && mysqli_connect_errno()) {
    $conn = null;
}

// IMPORTANT: Do not echo PHP warnings/notices into JSON responses
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

// Start output buffering to capture any accidental output
if (!ob_get_level()) { ob_start(); }

// Set JSON header
header('Content-Type: application/json');
http_response_code(200);

// Ensure we always return JSON on uncaught exceptions
set_exception_handler(function($e) {
    if (ob_get_length()) { ob_end_clean(); }
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server error: ' . $e->getMessage()]);
    exit;
});

$response = array('success' => false, 'message' => '');

if (!isset($_POST['action'])) {
    $response['message'] = 'No action specified';
    echo json_encode($response);
    exit;
}

try {
    // Validate mysqli connection once before executing actions that rely on it
    global $conn;
    
    // Reconnect if connection was lost or never established
    if (!$conn || !@mysqli_ping($conn)) {
        global $db_host, $db_name, $db_user, $db_pass;
        $conn = @mysqli_connect($db_host, $db_user, $db_pass, $db_name);
        if (!$conn) {
            throw new Exception('Database connection (mysqli) failed: ' . mysqli_connect_error());
        }
    }
    
    // Set charset to utf8mb4 for proper character encoding
    mysqli_set_charset($conn, 'utf8mb4');
    
    switch ($_POST['action']) {
        case 'backup':
            $backup_file = backupDatabase();
            $response['success'] = true;
            $response['message'] = 'Database backup created successfully: ' . basename($backup_file);
            break;
            
        case 'optimize':
            optimizeDatabase();
            $response['success'] = true;
            $response['message'] = 'Database optimized successfully';
            break;
            
        case 'clear_records':
            $deletedCount = clearOldRecords();
            $response['success'] = true;
            $response['message'] = 'Old records cleared successfully. Deleted ' . $deletedCount . ' records.';
            break;
            
        case 'update_settings':
            updateSystemSettings();
            $response['success'] = true;
            $response['message'] = 'System settings updated successfully';
            break;
            
        case 'clear_cache':
            $clearedCount = clearCache();
            $response['success'] = true;
            $response['message'] = 'System cache cleared successfully. Removed ' . $clearedCount . ' cache files.';
            break;
        
        case 'expire_inventory':
            $affected = expireOldInventory();
            $response['success'] = true;
            $response['message'] = 'Expired inventory updated successfully. Rows affected: ' . $affected;
            break;
            
        case 'check_health':
            $health = checkSystemHealth();
            $response['success'] = true;
            $response['data'] = $health;
            $response['message'] = 'System health check completed';
            break;
            
        default:
            $response['message'] = 'Invalid action';
            break;
    }
} catch (Exception $e) {
    $response['message'] = 'Error: ' . $e->getMessage();
}

// Clean any previous output buffer and send JSON
if (ob_get_length()) { ob_end_clean(); }
header('Content-Type: application/json');
echo json_encode($response);
exit;

// Function to backup database
function backupDatabase() {
    global $conn;
    
    if (!$conn || !@mysqli_ping($conn)) {
        throw new Exception("Database connection is not available");
    }
    
    $backupDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'backups';
    if ($backupDir === false) { 
        $backupDir = __DIR__ . '/../../backups'; 
    }
    
    if (!is_dir($backupDir)) {
        if (!@mkdir($backupDir, 0755, true)) {
            throw new Exception('Failed to create backups directory. Please check permissions.');
        }
    }
    
    if (!is_writable($backupDir)) {
        throw new Exception('Backups directory is not writable. Please check permissions.');
    }
    
    $backup_file = rtrim($backupDir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'negrosfirst_backup_' . date('Y-m-d_H-i-s') . '.sql';
    
    $tables = array();
    $result = @mysqli_query($conn, "SHOW TABLES");
    if (!$result) {
        throw new Exception("Failed to get tables: " . mysqli_error($conn));
    }
    
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    mysqli_free_result($result);
    
    if (empty($tables)) {
        throw new Exception("No tables found in database");
    }
    
    $return = "-- Negros First Database Backup\n";
    $return .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
    $return .= "-- Database: " . mysqli_get_server_info($conn) . "\n\n";
    $return .= "SET SQL_MODE = \"NO_AUTO_VALUE_ON_ZERO\";\n";
    $return .= "SET time_zone = \"+00:00\";\n\n";
    
    foreach ($tables as $table) {
        // SECURITY: Validate table name to prevent SQL injection
        // Table names should only contain alphanumeric characters, underscores, hyphens, and periods
        if (!preg_match('/^[a-zA-Z0-9_\.\-]+$/', $table)) {
            throw new Exception("Invalid table name: $table");
        }
        
        // SECURITY: Escape table name using mysqli_real_escape_string for additional safety
        $tableEscaped = mysqli_real_escape_string($conn, $table);
        
        $return .= "-- Table structure for table `$tableEscaped`\n";
        $return .= "DROP TABLE IF EXISTS `$tableEscaped`;\n";
        
        $createResult = @mysqli_query($conn, "SHOW CREATE TABLE `$tableEscaped`");
        if (!$createResult) {
            throw new Exception("Failed to get CREATE TABLE statement for $tableEscaped: " . mysqli_error($conn));
        }
        
        $row2 = mysqli_fetch_row($createResult);
        if (!$row2 || !isset($row2[1])) {
            mysqli_free_result($createResult);
            throw new Exception("Failed to fetch CREATE TABLE statement for $tableEscaped");
        }
        
        $return .= "\n" . $row2[1] . ";\n\n";
        mysqli_free_result($createResult);
        
        $result = @mysqli_query($conn, "SELECT * FROM `$tableEscaped`");
        if (!$result) {
            $return .= "-- Note: Could not retrieve data from table `$tableEscaped`\n\n";
            continue;
        }
        
        $numColumns = mysqli_num_fields($result);
        $return .= "-- Data for table `$tableEscaped`\n";
        $rowCount = 0;
        while ($row = mysqli_fetch_row($result)) {
            $return .= "INSERT INTO `$tableEscaped` VALUES(";
            for ($j = 0; $j < $numColumns; $j++) {
                if ($j > 0) {
                    $return .= ',';
                }
                if ($row[$j] === null || $row[$j] === 'NULL') {
                    $return .= 'NULL';
                } else {
                    $escaped = mysqli_real_escape_string($conn, $row[$j]);
                    $return .= '"' . $escaped . '"';
                }
            }
            $return .= ");\n";
            $rowCount++;
        }
        mysqli_free_result($result);
        $return .= "\n-- End of data for table `$tableEscaped` ($rowCount rows)\n\n\n";
    }
    
    $written = @file_put_contents($backup_file, $return);
    if ($written === false) {
        throw new Exception("Failed to write backup file. Check directory permissions.");
    }
    
    return $backup_file;
}

// Function to optimize database
function optimizeDatabase() {
    global $conn;
    
    if (!$conn || !@mysqli_ping($conn)) {
        throw new Exception("Database connection is not available");
    }
    
    $tables = array();
    $result = @mysqli_query($conn, "SHOW TABLES");
    if (!$result) {
        throw new Exception("Failed to get table list: " . mysqli_error($conn));
    }
    
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }
    mysqli_free_result($result);
    
    if (empty($tables)) {
        throw new Exception("No tables found to optimize");
    }
    
    $optimizedCount = 0;
    $errors = array();
    
    foreach ($tables as $table) {
        // SECURITY: Validate table name to prevent SQL injection
        if (!preg_match('/^[a-zA-Z0-9_\.\-]+$/', $table)) {
            $errors[] = "Invalid table name: $table";
            continue;
        }
        
        // SECURITY: Escape table name using mysqli_real_escape_string for additional safety
        $tableEscaped = mysqli_real_escape_string($conn, $table);
        
        if (!@mysqli_query($conn, "OPTIMIZE TABLE `$tableEscaped`")) {
            $errors[] = "Failed to optimize table $tableEscaped: " . mysqli_error($conn);
            continue;
        }
        $optimizedCount++;
        @mysqli_query($conn, "ANALYZE TABLE `$tableEscaped`");
    }
    
    if ($optimizedCount === 0) {
        throw new Exception("Failed to optimize any tables. " . implode("; ", $errors));
    }
    
    return true;
}

// Function to clear old records
function clearOldRecords() {
    global $conn;
    
    if (!$conn || !@mysqli_ping($conn)) {
        throw new Exception("Database connection is not available");
    }
    
    $deletedCount = 0;
    
    // Clear old audit logs (older than 1 year)
    $result = @mysqli_query($conn, "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 YEAR)");
    if ($result) {
        $deletedCount += mysqli_affected_rows($conn);
    }
    
    // Clear old notifications (older than 90 days and read)
    $result = @mysqli_query($conn, "DELETE FROM notifications WHERE is_read = 1 AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY)");
    if ($result) {
        $deletedCount += mysqli_affected_rows($conn);
    }
    
    // Clear old expired inventory records (older than 6 months)
    $result = @mysqli_query($conn, "DELETE FROM blood_inventory WHERE status = 'Expired' AND updated_at < DATE_SUB(NOW(), INTERVAL 6 MONTH) AND organization_type = 'negrosfirst'");
    if ($result) {
        $deletedCount += mysqli_affected_rows($conn);
    }
    
    return $deletedCount;
}

// Function to update system settings
function updateSystemSettings() {
    global $conn;
    
    if (!$conn || !@mysqli_ping($conn)) {
        throw new Exception("Database connection is not available");
    }
    
    // Update system metadata or settings if you have a settings table
    // This is a placeholder - implement based on your settings table structure
    $result = @mysqli_query($conn, "UPDATE system_settings SET last_maintenance = NOW() WHERE id = 1");
    
    return true;
}

// Function to clear cache
function clearCache() {
    $cacheDir = realpath(__DIR__ . '/../../') . DIRECTORY_SEPARATOR . 'cache';
    if ($cacheDir === false) { 
        $cacheDir = __DIR__ . '/../../cache'; 
    }
    
    $clearedCount = 0;
    
    if (is_dir($cacheDir)) {
        $files = glob($cacheDir . DIRECTORY_SEPARATOR . '*');
        foreach ($files as $file) {
            if (is_file($file)) {
                if (@unlink($file)) {
                    $clearedCount++;
                }
            }
        }
    }
    
    // Also clear opcache if available
    if (function_exists('opcache_reset')) {
        @opcache_reset();
    }
    
    return $clearedCount;
}

// Function to expire old inventory
function expireOldInventory() {
    global $conn;
    
    if (!$conn || !@mysqli_ping($conn)) {
        throw new Exception("Database connection is not available");
    }
    
    // Update inventory status to 'Expired' where expiry_date has passed and status is 'Available'
    $result = @mysqli_query($conn, "
        UPDATE blood_inventory 
        SET status = 'Expired', updated_at = NOW() 
        WHERE organization_type = 'negrosfirst' 
            AND status = 'Available' 
            AND expiry_date IS NOT NULL 
            AND expiry_date < CURDATE()
    ");
    
    if (!$result) {
        throw new Exception("Failed to expire inventory: " . mysqli_error($conn));
    }
    
    return mysqli_affected_rows($conn);
}

// Function to check system health
function checkSystemHealth() {
    global $conn;
    
    $health = array(
        'database' => false,
        'database_message' => '',
        'disk_space' => false,
        'disk_message' => '',
        'php_version' => false,
        'php_message' => '',
        'mysqli' => false,
        'mysqli_message' => '',
        'memory_limit' => ini_get('memory_limit')
    );
    
    // Check database
    if ($conn && @mysqli_ping($conn)) {
        $health['database'] = true;
        $health['database_message'] = 'Connection established successfully';
    } else {
        $health['database_message'] = 'Connection failed: ' . (isset($conn) ? mysqli_connect_error() : 'Connection not initialized');
    }
    
    // Check disk space
    $diskFree = @disk_free_space(__DIR__);
    $diskTotal = @disk_total_space(__DIR__);
    if ($diskFree !== false && $diskTotal !== false && $diskTotal > 0) {
        $percentFree = ($diskFree / $diskTotal) * 100;
        $health['disk_space'] = ($percentFree > 10); // At least 10% free
        $health['disk_message'] = sprintf('%.2f%% free (%.2f GB / %.2f GB)', 
            $percentFree, 
            $diskFree / (1024*1024*1024), 
            $diskTotal / (1024*1024*1024)
        );
    } else {
        $health['disk_message'] = 'Unable to check disk space';
    }
    
    // Check PHP version
    $phpVersion = PHP_VERSION;
    $health['php_version'] = version_compare($phpVersion, '7.4.0', '>=');
    $health['php_message'] = 'Current version: ' . $phpVersion . ' (Recommended: 7.4+)';
    
    // Check MySQLi extension
    $health['mysqli'] = extension_loaded('mysqli');
    $health['mysqli_message'] = $health['mysqli'] ? 'MySQLi extension is loaded' : 'MySQLi extension is not available';
    
    return $health;
}

