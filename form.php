<?php
/**
 * reBB - Form
 * 
 * This file serves as the renderer for the form (enduser).
 */
require_once 'kernel.php';

$isJsonRequest = false;
$formName = '';

if (isset($_GET['f'])) {
    $formName = preg_replace('/[^a-zA-Z0-9_\-\/]/', '', $_GET['f']); // Allow slash for /json

    if (str_ends_with($formName, '/json')) {
        $isJsonRequest = true;
        $formName = substr($formName, 0, -5); // Remove "/json" to get the base form name
    }
}

if ($isJsonRequest) {
    $filename = 'forms/' . $formName . '_schema.json';
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
    $showAlert = false; // Flag to control banner display


    if (isset($_GET['f'])) {
        $formName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['f']);
        $filename = 'forms/' . $formName . '_schema.json';

        if (file_exists($filename)) {
            $fileContents = file_get_contents($filename);
            $formData = json_decode($fileContents, true);
            $formSchema = $formData['schema'] ?? null;
            $formTemplate = $formData['template'] ?? '';
            $formStyle = $formData['style'] ?? 'default';
            $formNameDisplay = $formData['formName'] ?? '';

            // Check for sensitive keywords in formSchema
            $sensitiveKeywords = ["password", "passcode", "secret", "pin"];
            if ($formSchema) {
                function searchKeywords($array, $keywords) {
                    foreach ($array as $key => $value) {
                        if (is_array($value)) {
                            if (searchKeywords($value, $keywords)) {
                                return true;
                            }
                        } else if (is_string($value)) {
                            $lowerValue = strtolower($value);
                            foreach ($keywords as $keyword) {
                                if (strpos($lowerValue, $keyword) !== false) {
                                    return true;
                                }
                            }
                        }
                        if (is_string($key)) {
                            $lowerKey = strtolower($key);
                            foreach ($keywords as $keyword) {
                                if (strpos($lowerKey, $keyword) !== false) {
                                    return true;
                                }
                            }
                        }
                    }
                    return false;
                }

                if (searchKeywords($formSchema, $sensitiveKeywords)) {
                    $showAlert = true;
                }
            }
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

<?php if (!$formSchema): ?>
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
  </div>
<?php endif; ?>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = $formNameDisplay . ' - ' . SITE_NAME;

// Page-specific settings
$GLOBALS['page_settings'] = [
    'formio_assets' => true,
    'footer' => 'form'
];

// Add form-specific CSS based on formStyle
$formStyleCSS = '';
if ($formStyle === 'default') {
    // Include both the default CSS and the dark mode version
    $formStyleCSS = '<link rel="stylesheet" href="' . ASSETS_DIR . '/css/form-default.css?v=' . SITE_VERSION . '">' . PHP_EOL;
    $formStyleCSS .= '<link rel="stylesheet" href="' . ASSETS_DIR . '/css/form-default-dark.css?v=' . SITE_VERSION . '">';
}
$GLOBALS['page_css'] = $formStyleCSS;

// Add page-specific JavaScript
$formSchema = json_encode($formSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
$formTemplate = json_encode($formTemplate, JSON_UNESCAPED_SLASHES);
$GLOBALS['page_js_vars'] = <<<JSVARS
const formSchema = $formSchema;
const formTemplate = $formTemplate;
JSVARS;
$GLOBALS['page_javascript'] = '<script src="'. ASSETS_DIR .'/js/app/form.js?v=' . SITE_VERSION . '"></script>';

// Include the master layout
require_once BASE_DIR . '/includes/master.php';