<?php
/**
 * reBB - Ajax Backend
 * 
 * This file handles the backend ajax calls.
 */
header('Content-Type: application/json');

// Make sure constants are defined
if (!defined('MAX_REQUESTS_PER_HOUR')) {
    // Default values as fallback
    define('MAX_REQUESTS_PER_HOUR', 60);
    define('COOLDOWN_PERIOD', 5);
    define('IP_BLACKLIST', []);
    define('DEBUG_MODE', false);
}

// Anti-spam configuration - define it globally to be accessible in functions
global $ajax_config;
$ajax_config = [
    'max_requests_per_hour' => MAX_REQUESTS_PER_HOUR,          // Maximum form submissions per hour per IP
    'cooldown_period' => COOLDOWN_PERIOD,                      // Seconds between submissions
    'log_file' => STORAGE_DIR . '/logs/form_submissions.log',     // Path to log file (relative to script)
    'ip_blacklist' => IP_BLACKLIST,
];

// Create logs directory if it doesn't exist
$logsDir = dirname($ajax_config['log_file']);
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Check if user is logged in as admin
function isTrusted() {
    return (auth()->hasRole('editor') || auth()->hasRole('trusted') || auth()->hasRole('admin'));
}

// Debug IP variables - to help troubleshoot issues
if (defined('DEBUG_MODE') && DEBUG_MODE) {
    $raw_remote_addr = $_SERVER['REMOTE_ADDR'] ?? 'Not available';
    $raw_client_ip = $_SERVER['HTTP_CLIENT_IP'] ?? 'Not available';
    $raw_forwarded_for = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'Not available';
    
    $debug_file = STORAGE_DIR . '/logs/ip_debug.log';
    file_put_contents($debug_file, "[" . date('Y-m-d H:i:s') . "] IP Debug Info:\n" . 
        "REMOTE_ADDR: {$raw_remote_addr}\n" .
        "HTTP_CLIENT_IP: {$raw_client_ip}\n" .
        "HTTP_X_FORWARDED_FOR: {$raw_forwarded_for}\n\n", FILE_APPEND);
}

// Check if request method is valid
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logAttempt('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    echo json_encode(['success' => false, 'error' => 'Invalid request method. Only POST allowed.']);
    exit;
}

// Get client IP address - get it for each use rather than storing globally
$ip = getClientIP();

// Check if IP is blacklisted
if (in_array($ip, $ajax_config['ip_blacklist'])) {
    logAttempt('Blacklisted IP attempt');
    echo json_encode(['success' => false, 'error' => 'Request denied.']);
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

// Rate limiting: Check submission cooldown
if (isset($_SESSION['last_submission_time'])) {
    $timeSinceLastSubmission = time() - $_SESSION['last_submission_time'];
    if ($timeSinceLastSubmission < $ajax_config['cooldown_period']) {
        logAttempt('Submission rate limit exceeded (cooldown period)');
        echo json_encode([
            'success' => false, 
            'error' => 'Please wait ' . ($ajax_config['cooldown_period'] - $timeSinceLastSubmission) . ' seconds before submitting again.'
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
if ($_SESSION['hourly_submissions']['count'] >= $ajax_config['max_requests_per_hour']) {
    $resetTimeFormatted = date('H:i:s', $_SESSION['hourly_submissions']['reset_time']);
    logAttempt('Hourly submission limit exceeded');
    echo json_encode([
        'success' => false, 
        'error' => 'You have reached the maximum submissions per hour. Please try again after ' . $resetTimeFormatted
    ]);
    exit;
}

$requestType = isset($requestData['type']) ? $requestData['type'] : null;
$formsDir = STORAGE_DIR . '/forms';

if (!is_dir($formsDir)) {
    if (!mkdir($formsDir, 0777, true)) { // Create directory with write permissions
        logAttempt('Failed to create forms directory');
        echo json_encode(['success' => false, 'error' => 'Failed to create forms directory.']);
        exit;
    }
}

/**
 * Generate a unique form ID string that doesn't collide with existing files
 * 
 * @param string $formsDir Directory where form schemas are stored
 * @param int $maxAttempts Maximum number of attempts to generate a unique ID
 * @return string|false The unique form ID or false if generation failed
 */
function generateUniqueFormId($formsDir, $maxAttempts = 10) {
    $attempts = 0;
    
    while ($attempts < $maxAttempts) {
        // Generate a random string
        $randomString = bin2hex(random_bytes(16));
        $filename = $formsDir . '/' . $randomString . '_schema.json';
        
        // Check if file already exists
        if (!file_exists($filename)) {
            return $randomString;
        }
        
        // Log this rare collision
        logAttempt("File collision detected: $filename - regenerating ID", true);
        $attempts++;
    }
    
    // If we've reached the maximum attempts, log this and return false
    logAttempt("Failed to generate a unique form ID after $maxAttempts attempts", true);
    return false;
}

// Later in the code where we handle the 'schema' type request
if ($requestType === 'schema') {
    // Basic content validation
    $formSchema = isset($requestData['schema']) ? $requestData['schema'] : null;
    $formTemplate = isset($requestData['template']) ? $requestData['template'] : ''; 
    $formName = isset($requestData['formName']) ? $requestData['formName'] : '';
    $formStyle = isset($requestData['formStyle']) ? $requestData['formStyle'] : 'default'; // Get form style
    $isEditMode = isset($requestData['editMode']) && $requestData['editMode'] === true;
    $editingFormId = isset($requestData['editingForm']) ? $requestData['editingForm'] : '';
    
    $createdBy = null;
    $verified = false;
    if(auth()->isLoggedIn()) {
        $currentUser = auth()->getUser();
        $createdBy = $currentUser['_id'];
        if(isTrusted()) {
            $verified = true;
        }
    }
    
    // Get the template title and link fields with toggle states
    $enableTemplateTitle = isset($requestData['enableTemplateTitle']) ? (bool)$requestData['enableTemplateTitle'] : false;
    $enableTemplateLink = isset($requestData['enableTemplateLink']) ? (bool)$requestData['enableTemplateLink'] : false;
    $templateTitle = $enableTemplateTitle && isset($requestData['templateTitle']) ? $requestData['templateTitle'] : '';
    $templateLink = $enableTemplateLink && isset($requestData['templateLink']) ? $requestData['templateLink'] : '';

    if ($formSchema === null) {
        logAttempt('No form schema data received');
        echo json_encode(['success' => false, 'error' => 'No form schema data received.']);
        exit;
    }

    // Check for very large submissions (potential DoS)
    $jsonSize = strlen(json_encode($formSchema));
    $allowedSize = MAX_SCHEMA_SIZE_GUEST;
    if(auth()->isLoggedIn()) {
        $allowedSize = MAX_SCHEMA_SIZE_MEMBER;
    }
    if ($jsonSize > $allowedSize) {
        logAttempt('Form schema too large: ' . $jsonSize . ' bytes');
        echo json_encode(['success' => false, 'error' => 'Form schema exceeds maximum allowed size.']);
        exit;
    }

    // Sanitize template to prevent malicious content
    $formTemplate = htmlspecialchars($formTemplate, ENT_QUOTES, 'UTF-8');
    $templateTitle = htmlspecialchars($templateTitle, ENT_QUOTES, 'UTF-8');
    $templateLink = htmlspecialchars($templateLink, ENT_QUOTES, 'UTF-8');
    
    // Validate form style (only allow valid options)
    $allowedStyles = ['default', 'paperwork', 'vector', 'retro', 'modern'];
    if (!in_array($formStyle, $allowedStyles)) {
        $formStyle = 'default'; // Default fallback
    }
    
    // Determine if we're editing or creating a new form
    if ($isEditMode && !empty($editingFormId)) {
        // Handle form editing - verify ownership
        $existingFilename = $formsDir . '/' . $editingFormId . '_schema.json';
        
        if (!file_exists($existingFilename)) {
            logAttempt('Edit attempt for non-existent form: ' . $editingFormId);
            echo json_encode(['success' => false, 'error' => 'Form not found.']);
            exit;
        }
        
        // Load the existing form data
        $existingFormData = json_decode(file_get_contents($existingFilename), true);
        
        // Check if user has permission to edit this form
        if (!auth()->isLoggedIn()) {
            logAttempt('Unauthenticated edit attempt for form: ' . $editingFormId);
            echo json_encode(['success' => false, 'error' => 'Authentication required to edit forms.']);
            exit;
        }
        
        $currentUser = auth()->getUser();
        $formCreator = isset($existingFormData['createdBy']) ? $existingFormData['createdBy'] : null;
        
        if ($formCreator !== $currentUser['_id'] && $currentUser['role'] !== 'admin') {
            logAttempt('Unauthorized edit attempt for form: ' . $editingFormId . ' by user: ' . $currentUser['username']);
            echo json_encode(['success' => false, 'error' => 'You do not have permission to edit this form.']);
            exit;
        }
        
        // User has permission, proceed with update
        // Keep the original creation data but update everything else
        $fileContent = json_encode([
            'success' => true,
            'filename' => $existingFilename,
            'formName' => $formName,
            'schema' => $formSchema,
            'template' => $formTemplate,
            'templateTitle' => $templateTitle,
            'templateLink' => $templateLink,
            'enableTemplateTitle' => $enableTemplateTitle,
            'enableTemplateLink' => $enableTemplateLink,
            'formStyle' => $formStyle,
            'createdBy' => $formCreator, // Maintain original creator
            'verified' => $verified,
            'created' => $existingFormData['created'] ?? time(), // Maintain original creation time
            'updated' => time() // Add update timestamp
        ], JSON_PRETTY_PRINT);
        
        if (!file_put_contents($existingFilename, $fileContent)) {
            logAttempt('Failed to update form: ' . $editingFormId);
            echo json_encode(['success' => false, 'error' => 'Failed to update form.']);
            exit;
        }
        
        // Update the form data in SleekDB only if authenticated
        if (auth()->isLoggedIn()) {
            $currentUser = auth()->getUser();
            $userId = $currentUser['_id'];

            try {
                // Initialize the SleekDB store for user forms
                $dbPath = ROOT_DIR . '/db';
                $userFormsStore = new \SleekDB\Store('user_forms', $dbPath, [
                    'auto_cache' => false,
                    'timeout' => false
                ]);
                
                // Look up existing record for this form
                $existingRecord = $userFormsStore->findOneBy([['form_id', '=', $editingFormId], 'AND', ['user_id', '=', $userId]]);
                
                if ($existingRecord) {
                    // Update existing record
                    $userFormsStore->updateById($existingRecord['_id'], [
                        'form_name' => $formName,
                        'last_updated' => time()
                    ]);
                } else if ($formCreator === $userId) {
                    // Create new record only if user is the creator
                    $userFormsStore->insert([
                        'user_id' => $userId,
                        'form_id' => $editingFormId,
                        'form_name' => $formName,
                        'created_at' => $existingFormData['created'] ?? time(),
                        'last_updated' => time()
                    ]);
                }
                
                logAttempt("Updated form in database: $editingFormId by user: " . $currentUser['username']);
            } catch (\Exception $e) {
                // Log the error but don't stop the process
                logAttempt("Error updating form in database: " . $e->getMessage(), false);
            }
        }
        
        // Update rate limiting counters
        $_SESSION['last_submission_time'] = time();
        $_SESSION['hourly_submissions']['count']++;
        
        // Log successful update
        logAttempt("Successful form update - Form ID: $editingFormId by user: " . ($currentUser['username'] ?? 'Unknown'), false);
        
        // Return success response with original form ID
        echo json_encode([
            'success' => true,
            'filename' => $existingFilename,
            'formId' => $editingFormId,
            'isUpdate' => true
        ]);
        exit;
    } else {
        // This is a new form creation - Generate a unique form ID
        $randomString = generateUniqueFormId($formsDir);
        
        if ($randomString === false) {
            logAttempt('Failed to generate a unique form ID');
            echo json_encode(['success' => false, 'error' => 'Failed to generate a unique form ID. Please try again.']);
            exit;
        }
        
        $filename = $formsDir . '/' . $randomString . '_schema.json';
        $fileContent = json_encode([
            'success' => true,
            'filename' => $filename,
            'formName' => $formName,
            'schema' => $formSchema,
            'template' => $formTemplate,
            'templateTitle' => $templateTitle,
            'templateLink' => $templateLink,
            'enableTemplateTitle' => $enableTemplateTitle,
            'enableTemplateLink' => $enableTemplateLink,
            'formStyle' => $formStyle,
            'createdBy' => $createdBy,
            'verified' => $verified,
            'created' => time()
        ], JSON_PRETTY_PRINT);

        if (!file_put_contents($filename, $fileContent)) {
            logAttempt('Failed to save form schema to file');
            echo json_encode(['success' => false, 'error' => 'Failed to save form schema to file.']);
            exit;
        }
        
        // Store the form data in SleekDB if the user is authenticated
        if (auth()->isLoggedIn()) {
            $currentUser = auth()->getUser();
            $userId = $currentUser['_id'];
            
            try {
                // Initialize the SleekDB store for user forms
                $dbPath = ROOT_DIR . '/db';
                $userFormsStore = new \SleekDB\Store('user_forms', $dbPath, [
                    'auto_cache' => false,
                    'timeout' => false
                ]);
                
                // Insert the form record
                $userFormsStore->insert([
                    'user_id' => $userId,
                    'form_id' => $randomString,
                    'form_name' => $formName,
                    'created_at' => time(),
                    'last_updated' => time()
                ]);
                
                logAttempt("Added form to database: $randomString by user: " . $currentUser['username']);
            } catch (\Exception $e) {
                // Log the error but don't stop the process
                logAttempt("Error adding form to database: " . $e->getMessage(), false);
            }
        }

        // Update rate limiting counters
        $_SESSION['last_submission_time'] = time();
        $_SESSION['hourly_submissions']['count']++;
        
        // Log successful submission with form ID
        $userInfo = auth()->isLoggedIn() ? " by user: " . auth()->getUser()['username'] : "";
        logAttempt("Successful form schema submission - Form ID: $randomString$userInfo", false);
        
        $responseData = json_decode($fileContent, true);
        $responseData['formId'] = $randomString; // Add formId to the response
        echo json_encode($responseData);
        exit;
    }
} elseif ($requestType === 'custom_link') {
    // Require authentication
    if (!auth()->isLoggedIn()) {
        echo json_encode(['success' => false, 'error' => 'Authentication required.']);
        exit;
    }
    
    $currentUser = auth()->getUser();
    $userId = $currentUser['_id'];
    $action = isset($requestData['action']) ? $requestData['action'] : null;
    
    // Initialize custom links store
    $dbPath = ROOT_DIR . '/db';
    $linkStore = new \SleekDB\Store('custom_links', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    // Initialize users store to check link limits
    $userStore = new \SleekDB\Store('users', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    // Get user's max links limit
    $userData = $userStore->findById($userId);
    $maxLinks = isset($userData['max_unique_links']) ? $userData['max_unique_links'] : DEFAULT_MAX_UNIQUE_LINKS;
    
    switch ($action) {
        case 'create':
            // Get parameters
            $formId = isset($requestData['form_id']) ? $requestData['form_id'] : '';
            $customLink = isset($requestData['custom_link']) ? $requestData['custom_link'] : '';
            
            // Basic validation
            if (empty($formId)) {
                echo json_encode(['success' => false, 'error' => 'Form ID is required.']);
                exit;
            }
            
            if (empty($customLink)) {
                echo json_encode(['success' => false, 'error' => 'Custom link is required.']);
                exit;
            }
            
            // Validate link format
            if (!preg_match('/^[a-zA-Z0-9\-_]+$/', $customLink)) {
                echo json_encode(['success' => false, 'error' => 'Custom link can only contain letters, numbers, hyphens and underscores.']);
                exit;
            }
            
            // Validate link length
            $minLength = defined('CUSTOM_LINK_MIN_LENGTH') ? CUSTOM_LINK_MIN_LENGTH : 3;
            $maxLength = defined('CUSTOM_LINK_MAX_LENGTH') ? CUSTOM_LINK_MAX_LENGTH : 30;
            
            if (strlen($customLink) < $minLength || strlen($customLink) > $maxLength) {
                echo json_encode(['success' => false, 'error' => "Custom link must be between {$minLength} and {$maxLength} characters."]);
                exit;
            }
            
            // Check if the form exists
            $formPath = STORAGE_DIR . '/forms/' . $formId . '_schema.json';
            if (!file_exists($formPath)) {
                echo json_encode(['success' => false, 'error' => 'Form not found.']);
                exit;
            }
            
            // Get form data
            $formData = json_decode(file_get_contents($formPath), true);
            $formName = isset($formData['formName']) ? $formData['formName'] : 'Unnamed Form';
            
            // Check if custom link already exists
            $existingLink = $linkStore->findOneBy([['custom_link', '=', $customLink]]);
            if ($existingLink) {
                echo json_encode(['success' => false, 'error' => 'This custom link is already in use. Please choose another.']);
                exit;
            }
            
            // Check if user has reached their link limit
            $userLinks = $linkStore->findBy([['user_id', '=', $userId]]);
            if (count($userLinks) >= $maxLinks) {
                echo json_encode(['success' => false, 'error' => "You have reached your limit of {$maxLinks} custom links. Please delete some before creating new ones."]);
                exit;
            }
            
            // Create the custom link
            try {
                $linkStore->insert([
                    'user_id' => $userId,
                    'form_id' => $formId,
                    'custom_link' => $customLink,
                    'form_name' => $formName,
                    'created_at' => time(),
                    'use_count' => 0
                ]);
                
                echo json_encode(['success' => true, 'message' => 'Custom link created successfully.']);
                logAttempt("Created custom link: {$customLink} for form: {$formId}", false);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to create custom link: ' . $e->getMessage()]);
                logAttempt("Failed to create custom link: {$customLink} - " . $e->getMessage());
            }
            break;
            
        case 'list':
            // Get all custom links for current user
            try {
                $links = $linkStore->findBy([['user_id', '=', $userId]]);
                
                // Add full URL to each link
                foreach ($links as &$link) {
                    $link['full_url'] = site_url('u') . '?f=' . $link['custom_link'];
                }
                
                echo json_encode([
                    'success' => true, 
                    'data' => [
                        'links' => $links,
                        'links_used' => count($links),
                        'max_links' => $maxLinks
                    ]
                ]);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to fetch custom links: ' . $e->getMessage()]);
                logAttempt("Failed to fetch custom links - " . $e->getMessage());
            }
            break;
            
        case 'delete':
            // Get parameters
            $customLink = isset($requestData['custom_link']) ? $requestData['custom_link'] : '';
            
            if (empty($customLink)) {
                echo json_encode(['success' => false, 'error' => 'Custom link is required.']);
                exit;
            }
            
            // Check if link exists and belongs to user
            $linkData = $linkStore->findOneBy([
                ['custom_link', '=', $customLink],
                'AND',
                ['user_id', '=', $userId]
            ]);
            
            if (!$linkData) {
                // For admins, allow deleting any link
                if ($currentUser['role'] === 'admin') {
                    $linkData = $linkStore->findOneBy([['custom_link', '=', $customLink]]);
                    
                    if (!$linkData) {
                        echo json_encode(['success' => false, 'error' => 'Custom link not found.']);
                        exit;
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'You do not have permission to delete this link.']);
                    exit;
                }
            }
            
            // Delete the link
            try {
                $linkStore->deleteById($linkData['_id']);
                echo json_encode(['success' => true, 'message' => 'Custom link deleted successfully.']);
                logAttempt("Deleted custom link: {$customLink}", false);
            } catch (\Exception $e) {
                echo json_encode(['success' => false, 'error' => 'Failed to delete custom link: ' . $e->getMessage()]);
                logAttempt("Failed to delete custom link: {$customLink} - " . $e->getMessage());
            }
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Invalid custom link action.']);
            exit;
    }
    exit;
} elseif ($requestType === 'analytics') {
    $analytics = new Analytics();

    if (!$analytics->isEnabled()) {
        echo json_encode(['success' => true, 'analyticsEnabled' => false]);
        exit;
    }
    
    $action = isset($requestData['action']) ? $requestData['action'] : null;
    
    switch ($action) {
        case 'track_pageview':
            $page = isset($requestData['page']) ? $requestData['page'] : '';
            $analytics->trackPageView($page);
            break;
            
        case 'track_component':
            $component = isset($requestData['component']) ? $requestData['component'] : '';
            $analytics->trackComponentUsage($component);
            break;
            
        case 'track_theme':
            $theme = isset($requestData['theme']) ? $requestData['theme'] : '';
            $analytics->trackThemeUsage($theme);
            break;
            
        case 'track_form':
            $formId = isset($requestData['formId']) ? $requestData['formId'] : '';
            $isSubmission = isset($requestData['isSubmission']) ? 
                $requestData['isSubmission'] : false;
            $analytics->trackFormUsage($formId, $isSubmission);
            break;
            
        case 'get_live_visitors':
            $count = $analytics->getLiveVisitors();
            echo json_encode(['success' => true, 'count' => $count]);
            exit;
    }
    
    echo json_encode(['success' => true, 'analyticsEnabled' => true]);
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
    // Try each common IP source variable
    $ip_sources = [
        'HTTP_CLIENT_IP', 
        'HTTP_X_FORWARDED_FOR', 
        'HTTP_X_FORWARDED', 
        'HTTP_FORWARDED_FOR', 
        'HTTP_FORWARDED', 
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_sources as $source) {
        if (!empty($_SERVER[$source])) {
            // For forwarded IPs that might contain multiple IPs
            if ($source === 'HTTP_X_FORWARDED_FOR') {
                $ips = explode(',', $_SERVER[$source]);
                $ip = trim($ips[0]);
            } else {
                $ip = $_SERVER[$source];
            }
            
            // If it's a valid IP that's not a private range, use it
            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }
    
    // If we didn't find any valid IP in the preferred sources,
    // just return REMOTE_ADDR as a last resort
    return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
}

/**
 * Log submission attempts for security monitoring
 * @param string $message The message to log
 * @param bool $isFailure Whether this is a failed attempt (default: true)
 */
function logAttempt($message, $isFailure = true) {
    global $ajax_config;
    
    // Get IP directly in this function to ensure it's always available
    $ip = getClientIP();
    
    // Safety check - make sure log_file is defined
    if (empty($ajax_config) || empty($ajax_config['log_file'])) {
        // Fallback log file location
        $log_file = STORAGE_DIR . '/logs/form_submissions.log';
    } else {
        $log_file = $ajax_config['log_file'];
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $status = $isFailure ? 'FAILED' : 'SUCCESS';
    $logEntry = "[$timestamp] [$status] [IP: $ip] $message" . PHP_EOL;
    
    // Make sure directory exists
    $logsDir = dirname($log_file);
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0755, true);
    }
    
    // Try to write to log file, silently fail if unable
    @file_put_contents($log_file, $logEntry, FILE_APPEND);
    
    // Additional debug logging if debug mode is enabled
    if (defined('DEBUG_MODE') && DEBUG_MODE && $isFailure) {
        error_log("reBB Ajax Error: $message [IP: $ip]");
    }
}