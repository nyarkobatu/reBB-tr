<?php
/**
 * reBB - Builder
 * 
 * This file serves as the renderer for the form builder.
 */

$existingSchema = null;
$existingFormName = '';
$existingTemplate = '';

if (isset($_GET['f']) && !empty($_GET['f'])) {
    $formId = $_GET['f'];
    $filename = ROOT_DIR . '/forms/' . $formId . '_schema.json';

    if (file_exists($filename)) {
        $fileContent = file_get_contents($filename);
        $formData = json_decode($fileContent, true);
        if ($formData && isset($formData['schema'])) {
            $existingSchema = json_encode($formData['schema']);
            $existingFormName = isset($formData['formName']) ? $formData['formName'] : '';
            $existingTemplate = isset($formData['template']) ? $formData['template'] : '';
        }
    }
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<div id="content-wrapper">
    <div id='builder'></div>

    <div id='form-name-container' style="margin-top: 20px;">
        <h3>Form Name:</h3>
        <input type='text' id='formName' class='form-control' placeholder='Enter form name' value="<?php echo htmlspecialchars($existingFormName); ?>">
    </div>

    <div id='template-container'>
        <div id='wildcard-container'>
            <h3>Available Wildcards:</h3>
            <div id='wildcard-list'></div>
        </div>
        <h3>Form Template:</h3>
        <textarea id='formTemplate' class='form-control' rows='5'
                    placeholder='Paste your BBCode / HTML / Template here, use the wildcards above, example: [b]Name:[/b] {NAME_ABC1}.'><?php echo htmlspecialchars($existingTemplate); ?></textarea>
    </div>

    <div id='button-container'>
        <button id='saveFormButton' class='btn btn-primary'>Save Form</button>
    </div>

    <div id="documentation-link" class="text-center mt-3">
        <a href="<?php echo SITE_URL; ?>/documentation.php" target="_blank" class="btn btn-info">
            <i class="bi bi-book"></i> Documentation
        </a>
    </div>

    <div id="success-message" class="alert alert-success mt-3">
        <p>Form saved successfully! Share this link:</p>
        <a id="shareable-link" class="text-primary" target="_blank"></a>
        <div class="mt-2">
            <a id="go-to-form-button" class="btn btn-primary" target="_blank">
                <i class="bi bi-box-arrow-up-right"></i> Go to Form
            </a>
        </div>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'Form Builder';

// Page-specific settings
$GLOBALS['page_settings'] = [
    'formio_assets' => true,
];

// Add page-specific JavaScript
$existingSchema = $existingSchema ? $existingSchema : 'null';
$existingTemplate = json_encode($existingTemplate, JSON_UNESCAPED_SLASHES);
$siteURL = site_url();
$GLOBALS['page_js_vars'] = <<<JSVARS
let existingFormData = $existingSchema;
let existingFormNamePHP = "$existingFormName";
let existingTemplatePHP = $existingTemplate;
let siteURL = "$siteURL";
JSVARS;
$GLOBALS['page_javascript'] = '<script src="'. asset_path('js/app/builder.js') .'?v=' . APP_VERSION . '"></script>';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';