<?php
/**
 * SIM800C SMS Integration
 * Sends SMS via SIM800C module using Python script
 */

/**
 * Send SMS using SIM800C module via Python script
 *
 * @param string $phoneNumber Phone number in international format (e.g., +639636184722)
 * @param string $message SMS message text
 * @return array ['success' => bool, 'error' => string|null]
 */
function send_sms_sim800c($phoneNumber, $message)
{
    if (function_exists('secure_log')) {
        secure_log('[SIM800C] send_sms_sim800c called', [
            'phone_prefix' => !empty($phoneNumber) ? substr($phoneNumber, 0, 4) . '****' : 'EMPTY',
            'message_length' => strlen($message)
        ]);
    }

    // Get Python script path (use realpath for absolute path)
    $scriptPathRaw = __DIR__ . '/../python/texttest.py';
    $scriptPath = realpath($scriptPathRaw);

    if (!$scriptPath || !file_exists($scriptPath)) {
        // Try without realpath
        $scriptPath = str_replace('/', DIRECTORY_SEPARATOR, $scriptPathRaw);
        if (!file_exists($scriptPath)) {
            $error = 'Python script not found: ' . $scriptPathRaw . ' (also tried: ' . $scriptPath . ')';
            if (function_exists('secure_log')) {
                secure_log('[SIM800C] Python script not found', [
                    'script_path_raw' => substr($scriptPathRaw, 0, 200),
                    'script_path' => substr($scriptPath, 0, 200)
                ]);
            }
            return ['success' => false, 'error' => $error];
        }
    }

    // Normalize path for Windows
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        $scriptPath = str_replace('/', '\\', $scriptPath);
    }

    if (function_exists('secure_log')) {
        secure_log('[SIM800C] Script path', ['script_path' => substr($scriptPath, 0, 200)]);
    }

    if (empty($phoneNumber)) {
        $error = 'Phone number is required';
        if (function_exists('secure_log')) {
            secure_log('[SIM800C] Phone number is required');
        }
        return ['success' => false, 'error' => $error];
    }

    // Format phone number
    $originalPhone = $phoneNumber;
    $phoneNumber = format_phone_for_sim800c($phoneNumber);
    if (function_exists('secure_log')) {
        secure_log('[SIM800C] Phone formatted', [
            'original_prefix' => substr($originalPhone, 0, 4) . '****',
            'formatted_prefix' => substr($phoneNumber, 0, 4) . '****'
        ]);
    }

    // Get Python executable path
    $pythonPath = get_python_path();
    if (!$pythonPath) {
        $error = 'Python not found. Please install Python and ensure it\'s in PATH.';
        if (function_exists('secure_log')) {
            secure_log('[SIM800C] Python not found');
        }
        return ['success' => false, 'error' => $error];
    }

    if (function_exists('secure_log')) {
        secure_log('[SIM800C] Python path', ['python_path' => substr($pythonPath, 0, 200)]);
    }

    // Build and execute the command
    if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
        // Windows
        $pythonPathNormalized = str_replace('/', '\\', $pythonPath);
        $scriptPathNormalized = str_replace('/', '\\', $scriptPath);

        $pythonPathEscaped = escapeshellarg($pythonPathNormalized);
        $scriptPathEscaped = escapeshellarg($scriptPathNormalized);
        $phoneEscaped = escapeshellarg($phoneNumber);
        $messageEscaped = escapeshellarg($message);

        $command = $pythonPathEscaped . ' ' . $scriptPathEscaped . ' ' . $phoneEscaped . ' ' . $messageEscaped . ' 2>&1';
        if (function_exists('secure_log')) {
            secure_log('[SIM800C] Windows command', ['command_preview' => substr($command, 0, 300)]);
        }

        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        $outputStr = implode("\n", $output);

        if (empty($outputStr)) {
            $outputStr = @shell_exec($command);
        }
    } else {
        // Linux / macOS
        $phoneEscaped = escapeshellarg($phoneNumber);
        $messageEscaped = escapeshellarg($message);
        $command = escapeshellcmd($pythonPath) . ' ' . escapeshellarg($scriptPath) . ' ' . $phoneEscaped . ' ' . $messageEscaped . ' 2>&1';
        if (function_exists('secure_log')) {
            secure_log('[SIM800C] Unix command', ['command_preview' => substr($command, 0, 200)]);
        }
        $output = [];
        $returnCode = 0;
        exec($command, $output, $returnCode);
        $outputStr = implode("\n", $output);
    }

    // Check results
    $isSuccess = false;
    $outputLower = strtolower($outputStr ?? '');

    if ($returnCode === 0 &&
        (stripos($outputStr, 'SUCCESS') !== false ||
         stripos($outputStr, 'SMS sent') !== false ||
         stripos($outputStr, '+CMGS') !== false)) {
        $isSuccess = true;
    }

    if ($isSuccess) {
        log_sms_attempt($phoneNumber, $message, true, null);
        if (function_exists('secure_log')) {
            secure_log('[SIM800C] SMS sent successfully', [
                'phone_prefix' => !empty($phoneNumber) ? substr($phoneNumber, 0, 4) . '****' : 'EMPTY'
            ]);
        }
        return ['success' => true, 'response' => $outputStr];
    } else {
        $error = $outputStr ?: 'Failed to send SMS (Return code: ' . $returnCode . ')';
        log_sms_attempt($phoneNumber, $message, false, $error);
        if (function_exists('secure_log')) {
            secure_log('[SIM800C] SMS failed', [
                'phone_prefix' => !empty($phoneNumber) ? substr($phoneNumber, 0, 4) . '****' : 'EMPTY',
                'error' => substr($error, 0, 500),
                'return_code' => $returnCode
            ]);
        }
        return ['success' => false, 'error' => $error];
    }
}

/**
 * Format phone number for SIM800C
 */
function format_phone_for_sim800c($phoneNumber)
{
    if (empty($phoneNumber)) return '';
    $phoneNumber = preg_replace('/[^0-9+]/', '', trim($phoneNumber));

    if (str_starts_with($phoneNumber, '+')) return $phoneNumber;
    if (str_starts_with($phoneNumber, '0')) return '+63' . substr($phoneNumber, 1);
    if (str_starts_with($phoneNumber, '63')) return '+' . $phoneNumber;
    if (strlen($phoneNumber) >= 9) return '+63' . $phoneNumber;

    return '+63' . ltrim($phoneNumber, '0');
}

/**
 * Get Python executable path
 */
function get_python_path()
{
    $paths = [
        'python', 'python3', 'py',
        'C:\\Python39\\python.exe',
        'C:\\Python310\\python.exe',
        'C:\\Python311\\python.exe',
        'C:\\Python312\\python.exe',
        'C:\\xampp\\python\\python.exe',
        '/usr/bin/python3', '/usr/bin/python',
        '/usr/local/bin/python3', '/usr/local/bin/python'
    ];

    foreach ($paths as $path) {
        if (strpos($path, '\\') !== false || strpos($path, '/') !== false) {
            if (file_exists($path) && is_executable($path)) return $path;
        } else {
            $output = [];
            $returnCode = 0;
            exec(escapeshellcmd($path) . ' --version 2>&1', $output, $returnCode);
            if ($returnCode === 0) return $path;
        }
    }

    return null;
}

/**
 * Log SMS attempt
 */
function log_sms_attempt($phoneNumber, $message, $success, $error = null)
{
    try {
        require_once __DIR__ . '/../config/db.php';
        $logSql = "INSERT INTO sms_logs (phone_number, message, status, error_message, provider, created_at)
                   VALUES (?, ?, ?, ?, 'sim800c', NOW())";
        $status = $success ? 'sent' : 'failed';
        executeQuery($logSql, [
            substr($phoneNumber, 0, 4) . '****' . substr($phoneNumber, -4),
            substr($message, 0, 255),
            $status,
            $error
        ]);
    } catch (Exception $e) {
        if (function_exists('secure_log')) {
            secure_log('Failed to log SMS attempt', [
                'error' => substr($e->getMessage(), 0, 500)
            ]);
        }
    }
}

/**
 * Check if SIM800C SMS is enabled
 */
function is_sim800c_enabled()
{
    try {
        require_once __DIR__ . '/../config/db.php';
        if (!function_exists('getRow')) return false;

        $config = getRow("SELECT * FROM sms_config WHERE active = 1 AND provider = 'sim800c' AND enabled = 1 ORDER BY id DESC LIMIT 1");
        return !empty($config);
    } catch (Exception $e) {
        if (function_exists('secure_log')) {
            secure_log('[SIM800C] is_sim800c_enabled exception', [
                'error' => substr($e->getMessage(), 0, 500)
            ]);
        }
        return false;
    }
}

/**
 * Automatically send SMS for notifications
 */
function send_sms_for_notification($userRole, $userId, $message)
{
    try {
        require_once __DIR__ . '/../config/db.php';
        if (!is_sim800c_enabled()) {
            return ['success' => false, 'error' => 'SIM800C not enabled'];
        }

        $phone = null;
        switch ($userRole) {
            case 'patient':
                $patientRow = getRow("SELECT phone FROM patient_users WHERE id = ?", [$userId]);
                $phone = $patientRow['phone'] ?? '';
                if (function_exists('decrypt_value')) $phone = decrypt_value($phone);
                break;
            case 'donor':
                $donorRow = getRow("SELECT phone FROM donor_users WHERE id = ?", [$userId]);
                $phone = $donorRow['phone'] ?? '';
                if (function_exists('decrypt_value')) $phone = decrypt_value($phone);
                break;
            default:
                return ['success' => false, 'error' => 'Unknown user role'];
        }

        if (empty($phone)) return ['success' => false, 'error' => 'Phone number not found'];

        return send_sms_sim800c($phone, $message);
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}
?>
