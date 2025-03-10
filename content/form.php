<?php
/**
 * reBB - Form
 * 
 * This file serves as the renderer for the form (enduser).
 * Enhanced with additional security measures to protect against malicious JavaScript.
 */

// List of potentially dangerous JavaScript patterns to scan for
$dangerousPatterns = [
    'eval\s*\(' => 'JavaScript eval() function detected',
    'document\.write' => 'document.write() function detected',
    'innerHTML\s*=' => 'innerHTML manipulation detected',
    'outerHTML\s*=' => 'outerHTML manipulation detected',
    '<script' => 'Inline script tag detected',
    'javascript:' => 'JavaScript protocol detected',
    'onerror\s*=' => 'onerror event handler detected',
    'onclick\s*=' => 'onclick event handler detected',
    'onload\s*=' => 'onload event handler detected',
    'onmouseover\s*=' => 'onmouseover event handler detected',
    'fetch\s*\(' => 'Fetch API call detected',
    'XMLHttpRequest' => 'XMLHttpRequest detected',
    'localStorage' => 'localStorage manipulation detected',
    'sessionStorage' => 'sessionStorage manipulation detected',
    'window\.open' => 'window.open() detected',
    'window\.location' => 'window.location manipulation detected',
    'setTimeout\s*\(' => 'setTimeout() function detected',
    'setInterval\s*\(' => 'setInterval() function detected',
    '<iframe' => 'iframe element detected',
    'document\.cookie' => 'Cookie manipulation detected',
    'document\.domain' => 'Document domain manipulation detected',
    'document\.referrer' => 'Document referrer access detected',
    '\bdata:' => 'data: URI scheme detected'
];

/**
 * Enhanced function to detect sensitive information in forms
 * This provides more comprehensive detection than the original implementation
 */
function detectSensitiveInformation($data, $template = '') {
    // Expanded list of sensitive keywords
    $sensitiveKeywords = [
        "password", "passcode", "passwd", "pwd", 
        "secret", "pin", "ssn", "social security",
        "credit card", "cc number", "ccnumber", "card number", "cardnumber",
        "cvv", "cvc", "security code", "securitycode",
        "credentials", "login", "private key", "privatekey",
        "authenticate", "token", "api key", "apikey", "access key"
    ];
    
    // Convert data to a JSON string for comprehensive text search
    $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
    
    // Flatten everything to lowercase for case-insensitive matching
    $jsonDataLower = strtolower($jsonData);
    $templateLower = strtolower($template);
    
    // Check for exact field types and patterns that indicate sensitive data collection
    $fieldPatterns = [
        '"type":\s*"password"',
        'password',
        'type=[\'"]{1}password[\'"]{1}',
        '<input[^>]*type=[\'"]password[\'"]',
        'inputmode=[\'"]numeric[\'"][^>]*pattern=[\'"][0-9\*]+[\'"]',  // PIN pattern
        '\bpin\b',
        'secret',
        'ssn',
        'secure',
        'credentials'
    ];
    
    // Check schema against patterns
    foreach ($fieldPatterns as $pattern) {
        if (preg_match('/' . $pattern . '/i', $jsonDataLower)) {
            return true;
        }
    }
    
    // Check template against patterns if provided
    if (!empty($template)) {
        foreach ($fieldPatterns as $pattern) {
            if (preg_match('/' . $pattern . '/i', $templateLower)) {
                return true;
            }
        }
    }
    
    // Check for sensitive keywords in both schema and template
    foreach ($sensitiveKeywords as $keyword) {
        // Use word boundary checks to avoid partial matches
        $pattern = '/\b' . preg_quote($keyword, '/') . '\b/i';
        
        if (preg_match($pattern, $jsonDataLower) || 
            (!empty($template) && preg_match($pattern, $templateLower))) {
            return true;
        }
    }
    
    // Additional check for form components that might collect sensitive data
    $componentPatterns = [
        '"key":\s*"[^"]*pass[^"]*"',
        '"key":\s*"[^"]*secure[^"]*"',
        '"key":\s*"[^"]*pin[^"]*"',
        '"key":\s*"[^"]*auth[^"]*"',
        '"label":\s*"[^"]*password[^"]*"',
        '"label":\s*"[^"]*secret[^"]*"',
        '"placeholder":\s*"[^"]*enter.*password[^"]*"',
        '"placeholder":\s*"[^"]*enter.*pin[^"]*"',
        '"description":\s*"[^"]*security[^"]*"'
    ];
    
    foreach ($componentPatterns as $pattern) {
        if (preg_match('/' . $pattern . '/i', $jsonDataLower)) {
            return true;
        }
    }
    
    // Perform deep scan on arrays to catch nested sensitive information
    if (is_array($data)) {
        return recursiveKeyScan($data, $sensitiveKeywords);
    }
    
    return false;
}

/**
 * Helper function to recursively scan array keys and values
 */
function recursiveKeyScan($array, $keywords) {
    foreach ($array as $key => $value) {
        // Check key names
        if (is_string($key)) {
            $keyLower = strtolower($key);
            foreach ($keywords as $keyword) {
                // Use word boundary for more accurate matches
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $keyLower)) {
                    return true;
                }
            }
        }
        
        // Check string values
        if (is_string($value)) {
            $valueLower = strtolower($value);
            foreach ($keywords as $keyword) {
                if (preg_match('/\b' . preg_quote($keyword, '/') . '\b/i', $valueLower)) {
                    return true;
                }
            }
        }
        
        // Check component properties that might indicate password fields
        if (is_array($value) && isset($value['type']) && $value['type'] === 'password') {
            return true;
        }
        
        // Check for masked input patterns (often used for sensitive data)
        if (is_array($value) && isset($value['inputMask']) && !empty($value['inputMask'])) {
            return true;
        }
        
        // Continue recursive scanning for nested arrays
        if (is_array($value)) {
            if (recursiveKeyScan($value, $keywords)) {
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Function to scan for dangerous JavaScript patterns
 */
function scanForDangerousPatterns($content, $patterns) {
    $threats = [];
    if (is_string($content)) {
        foreach ($patterns as $pattern => $description) {
            if (preg_match('/' . $pattern . '/i', $content)) {
                $threats[] = $description;
            }
        }
    } else if (is_array($content)) {
        foreach ($content as $key => $value) {
            $subThreats = scanForDangerousPatterns($value, $patterns);
            if (!empty($subThreats)) {
                $threats = array_merge($threats, $subThreats);
            }
        }
    }
    return array_unique($threats);
}

$isJsonRequest = false;
$formName = '';
$detectedThreats = [];
$dangerousJSDetected = false;

if (isset($_GET['f'])) {
    $formName = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $_GET['f']); // Allow slash for /json

    if (str_ends_with($formName, '/json')) {
        $isJsonRequest = true;
        $formName = substr($formName, 0, -5); // Remove "/json" to get the base form name
    }
}

if ($isJsonRequest) {
    $filename = ROOT_DIR . '/forms/' . $formName . '_schema.json';
    if (file_exists($filename)) {
        header('Content-Type: application/json');
        $fileContents = file_get_contents($filename);
        echo $fileContents;
        exit(); // Stop further HTML rendering
    } else {
        header('Content-Type: text/plain');
        echo "Form JSON not found for form: " . htmlspecialchars($formName);
        exit();
    }
} else {
    header('Content-Type: text/html');
    $formSchema = null;
    $formTemplate = '';
    $formNameDisplay = '';
    $formStyle = 'default'; // Default value
    $showAlert = false; // Flag to control sensitive information banner display
    $bypassSecurityCheck = isset($_GET['confirm']) && $_GET['confirm'] === '1';

    if (isset($_GET['f'])) {
        $formName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['f']);
        $filename = ROOT_DIR . '/forms/' . $formName . '_schema.json';

        if (file_exists($filename)) {
            $fileContents = file_get_contents($filename);
            $formData = json_decode($fileContents, true);
            $formSchema = $formData['schema'] ?? null;
            $formTemplate = $formData['template'] ?? '';
            $formStyle = $formData['formStyle'] ?? 'default';
            $formNameDisplay = $formData['formName'] ?? '';

            // Enhanced sensitive information detection
            if ($formSchema) {
                // Check both the schema and template for sensitive information
                $showAlert = detectSensitiveInformation($formSchema, $formTemplate);
            }

            // Scan for dangerous JavaScript patterns
            if ($formSchema) {
                $schemaThreats = scanForDangerousPatterns(json_encode($formSchema), $dangerousPatterns);
                $detectedThreats = array_merge($detectedThreats, $schemaThreats);
            }
            
            if ($formTemplate) {
                $templateThreats = scanForDangerousPatterns($formTemplate, $dangerousPatterns);
                $detectedThreats = array_merge($detectedThreats, $templateThreats);
            }
            
            $dangerousJSDetected = !empty($detectedThreats);
        }
    }
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<?php if ($showAlert): ?>
<div class="alert alert-warning">
    <strong>Warning:</strong> This form appears to be requesting sensitive information such as passwords or passcodes. For your security, please do not enter your personal passwords or passcodes into this form unless you are absolutely certain it is legitimate and secure. Be cautious of phishing attempts.
</div>
<?php endif; ?>

<?php if ($dangerousJSDetected && !$bypassSecurityCheck): ?>
<div class="security-warning-container">
    <div class="security-warning">
        <h3><i class="bi bi-exclamation-triangle-fill"></i> Security Warning</h3>
        <p>This form contains potentially dangerous JavaScript code that could pose security risks:</p>
        <ul>
            <?php foreach ($detectedThreats as $threat): ?>
                <li><?= htmlspecialchars($threat) ?></li>
            <?php endforeach; ?>
        </ul>
        <p class="warning-text">Loading forms with such code may put your personal information at risk or compromise your browser security.</p>
        <div class="action-buttons">
            <a href="<?= htmlspecialchars($_SERVER['REQUEST_URI']) . (strpos($_SERVER['REQUEST_URI'], '?') === false ? '?' : '&') . 'confirm=1' ?>" class="btn btn-danger">
                I understand the risks, load anyway
            </a>
            <a href="index.php" class="btn btn-secondary">
                Return to safety
            </a>
        </div>
    </div>
</div>
<?php elseif (!$formSchema): ?>
  <div class="alert alert-danger">
    <?php if (!isset($_GET['f'])): ?>
      Form parameter missing. Please provide a valid form identifier.
    <?php else: ?>
      Form '<?= htmlspecialchars($_GET['f']) ?>' not found or invalid schema.
    <?php endif; ?>
  </div>
<?php else: ?>
  <div id="form-container">
    <?php if (!empty($formNameDisplay)): ?>
      <h2 class="text-center mb-4"><?= htmlspecialchars($formNameDisplay) ?></h2>
    <?php endif; ?>
    <div id="formio"></div>
  </div>

  <div id="output-container">
    <h4>Generated Output:</h4>
    <textarea id="output" class="form-control" rows="5" readonly></textarea>
    <div class="mt-2 text-end">
      <button id="copyOutputBtn" class="btn btn-primary">
        <i class="bi bi-clipboard"></i> Copy to Clipboard
      </button>
    </div>
  </div>
<?php endif; ?>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = $formNameDisplay;

// Page-specific settings
$GLOBALS['page_settings'] = [
    'formio_assets' => true,
    'footer' => 'form'
];

// Define allowed styles and set default
$allowedStyles = ['default', 'paperwork', 'vector', 'retro', 'modern'];
$formStyle = in_array($formStyle, $allowedStyles) ? $formStyle : 'default';

$formStyleCSS = '<link rel="stylesheet" href="' . asset_path("css/forms/{$formStyle}.css") . '?v=' . APP_VERSION . '">' . PHP_EOL;
$formStyleCSS .= '<link rel="stylesheet" href="' . asset_path("css/forms/{$formStyle}-dark.css") . '?v=' . APP_VERSION . '">' . PHP_EOL;
if ($dangerousJSDetected && !$bypassSecurityCheck) {
    $formStyleCSS .= '<link rel="stylesheet" href="' . asset_path("css/security-warning.css") . '?v=' . APP_VERSION . '">';
}
$GLOBALS['page_css'] = $formStyleCSS;

// Add page-specific JavaScript
if (!$dangerousJSDetected || $bypassSecurityCheck) {
    $formSchema = json_encode(
        $formSchema, 
        JSON_PRETTY_PRINT | 
        JSON_UNESCAPED_SLASHES | 
        JSON_UNESCAPED_UNICODE | 
        JSON_HEX_QUOT | 
        JSON_HEX_TAG
    );
    $formTemplate = json_encode($formTemplate, JSON_UNESCAPED_SLASHES);
    $GLOBALS['page_js_vars'] = <<<JSVARS
const formSchema = $formSchema;
const formTemplate = $formTemplate;
JSVARS;
    $GLOBALS['page_javascript'] = '<script src="'. asset_path('js/app/form.js') .'?v=' . APP_VERSION . '"></script>';
} else {
    // Empty the JavaScript variables when showing the security warning
    $GLOBALS['page_js_vars'] = '';
    $GLOBALS['page_javascript'] = '';
}

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';