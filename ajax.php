<?php
header('Content-Type: application/json');

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Anti-spam configuration
$config = [
    'max_requests_per_hour' => 10,          // Maximum form submissions per hour per IP
    'cooldown_period' => 5,                 // Seconds between submissions
    'log_file' => 'logs/form_submissions.log', // Path to log file (relative to script)
    'ip_blacklist' => ['192.0.2.1'],        // Example blacklisted IPs (replace with actual ones)
];

// Create logs directory if it doesn't exist
$logsDir = dirname($config['log_file']);
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Check if request method is valid
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logAttempt('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'error' => 'Invalid request method. Only POST allowed.']);
    exit;
}

// Get client IP address
$ip = getClientIP();

// Check if IP is blacklisted
if (in_array($ip, $config['ip_blacklist'])) {
    logAttempt('Blacklisted IP attempt');
    echo json_encode(['success' => false, 'error' => 'Request denied.']);
    exit;
}

// Check CSRF token (if set in session)
if (isset($_SESSION['csrf_token'])) {
    $jsonData = file_get_contents('php://input');
    $requestData = json_decode($jsonData, true);
    
    if (!isset($requestData['csrf_token']) || $requestData['csrf_token'] !== $_SESSION['csrf_token']) {
        logAttempt('Invalid CSRF token');
        echo json_encode(['success' => false, 'error' => 'Invalid or missing security token.']);
        exit;
    }
}

// Rate limiting: Check submission cooldown
if (isset($_SESSION['last_submission_time'])) {
    $timeSinceLastSubmission = time() - $_SESSION['last_submission_time'];
    if ($timeSinceLastSubmission < $config['cooldown_period']) {
        logAttempt('Submission rate limit exceeded (cooldown period)');
        echo json_encode([
            'success' => false, 
            'error' => 'Please wait ' . ($config['cooldown_period'] - $timeSinceLastSubmission) . ' seconds before submitting again.'
        ]);
        exit;
    }
}

// Rate limiting: Check hourly submission limit
if (!isset($_SESSION['hourly_submissions'])) {
    $_SESSION['hourly_submissions'] = ['count' => 0, 'reset_time' => time() + 3600];
}

// Reset counter if hour has passed
if (time() > $_SESSION['hourly_submissions']['reset_time']) {
    $_SESSION['hourly_submissions'] = ['count' => 0, 'reset_time' => time() + 3600];
}

// Check if hourly limit exceeded
if ($_SESSION['hourly_submissions']['count'] >= $config['max_requests_per_hour']) {
    $resetTimeFormatted = date('H:i:s', $_SESSION['hourly_submissions']['reset_time']);
    logAttempt('Hourly submission limit exceeded');
    echo json_encode([
        'success' => false, 
        'error' => 'You have reached the maximum submissions per hour. Please try again after ' . $resetTimeFormatted
    ]);
    exit;
}

// Process the JSON data
$jsonData = file_get_contents('php://input');
$requestData = json_decode($jsonData, true);

if ($requestData === null && json_last_error() !== JSON_ERROR_NONE) {
    logAttempt('Invalid JSON data received');
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data received.']);
    exit;
}

$requestType = isset($requestData['type']) ? $requestData['type'] : null;

$randomString = bin2hex(random_bytes(16)); // Generate a random string
$formsDir = 'forms';

if (!is_dir($formsDir)) {
    if (!mkdir($formsDir, 0777, true)) { // Create directory with write permissions
        logAttempt('Failed to create forms directory');
        echo json_encode(['success' => false, 'error' => 'Failed to create forms directory.']);
        exit;
    }
}

if ($requestType === 'schema') {
    // Basic content validation
    $formSchema = isset($requestData['schema']) ? $requestData['schema'] : null;
    $formTemplate = isset($requestData['template']) ? $requestData['template'] : ''; 
    $formName = isset($requestData['formName']) ? $requestData['formName'] : '';

    if ($formSchema === null) {
        logAttempt('No form schema data received');
        echo json_encode(['success' => false, 'error' => 'No form schema data received.']);
        exit;
    }

    // Check for very large submissions (potential DoS)
    $jsonSize = strlen(json_encode($formSchema));
    if ($jsonSize > 1000000) { // ~1MB limit
        logAttempt('Form schema too large: ' . $jsonSize . ' bytes');
        echo json_encode(['success' => false, 'error' => 'Form schema exceeds maximum allowed size.']);
        exit;
    }

    // Sanitize template to prevent malicious content
    $formTemplate = htmlspecialchars($formTemplate, ENT_QUOTES, 'UTF-8');
    
    $filename = $formsDir . '/' . $randomString . '_schema.json';
    $fileContent = json_encode([
        'success' => true,
        'filename' => $filename,
        'formName' => $formName,
        'schema' => $formSchema,
        'template' => $formTemplate,
    ], JSON_PRETTY_PRINT);

    if (!file_put_contents($filename, $fileContent)) {
        logAttempt('Failed to save form schema to file');
        echo json_encode(['success' => false, 'error' => 'Failed to save form schema to file.']);
        exit;
    }

    // Update rate limiting counters
    $_SESSION['last_submission_time'] = time();
    $_SESSION['hourly_submissions']['count']++;
    
    // Log successful submission with form ID
    logAttempt("Successful form schema submission - Form ID: $randomString", false);
    
    // Generate new CSRF token for next request
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    
    // Include CSRF token in successful response
    $responseData = json_decode($fileContent, true);
    $responseData['csrf_token'] = $_SESSION['csrf_token'];
    
    echo json_encode($responseData);
    exit;
} else {
    logAttempt('Invalid request type: ' . $requestType);
    echo json_encode(['success' => false, 'error' => 'Invalid request type.']);
    exit;
}

/**
 * Get client's real IP address
 * @return string The IP address
 */
function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        // Get the first IP in case of multiple proxies
        $ips = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
        return trim($ips[0]);
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }
}

/**
 * Log submission attempts for security monitoring
 * @param string $message The message to log
 * @param bool $isFailure Whether this is a failed attempt (default: true)
 */
function logAttempt($message, $isFailure = true) {
    global $config, $ip;
    
    $timestamp = date('Y-m-d H:i:s');
    $status = $isFailure ? 'FAILED' : 'SUCCESS';
    $logEntry = "[$timestamp] [$status] [IP: $ip] $message" . PHP_EOL;
    
    file_put_contents($config['log_file'], $logEntry, FILE_APPEND);
}
?>