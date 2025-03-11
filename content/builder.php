<?php
/**
 * reBB - Builder
 * 
 * This file serves as the renderer for the form builder.
 */

$existingSchema = null;
$existingFormName = '';
$existingTemplate = '';
$existingTemplateTitle = ''; // New variable for template title
$existingTemplateLink = '';  // New variable for template link
$enableTemplateTitle = false; // New variable for title toggle
$enableTemplateLink = false;  // New variable for link toggle
$existingFormStyle = 'default'; // Default style

if (isset($_GET['f']) && !empty($_GET['f'])) {
    $formId = $_GET['f'];
    $filename = STORAGE_DIR . '/forms/' . $formId . '_schema.json';

    if (file_exists($filename)) {
        $fileContent = file_get_contents($filename);
        $formData = json_decode($fileContent, true);
        if ($formData && isset($formData['schema'])) {
            $existingSchema = json_encode($formData['schema']);
            $existingFormName = isset($formData['formName']) ? $formData['formName'] : '';
            $existingTemplate = isset($formData['template']) ? $formData['template'] : '';
            $existingTemplateTitle = isset($formData['templateTitle']) ? $formData['templateTitle'] : ''; 
            $existingTemplateLink = isset($formData['templateLink']) ? $formData['templateLink'] : '';
            $enableTemplateTitle = isset($formData['enableTemplateTitle']) ? $formData['enableTemplateTitle'] : false;
            $enableTemplateLink = isset($formData['enableTemplateLink']) ? $formData['enableTemplateLink'] : false;
            $existingFormStyle = isset($formData['formStyle']) ? $formData['formStyle'] : 'default';
        }
    }
}

$formStyles = [
    [
        'id' => 'styleDefault',
        'value' => 'default',
        'label' => 'Default',
        'description' => 'Standard form layout with clean design.',
        'default' => true
    ],
    [
        'id' => 'stylePaperwork',
        'value' => 'paperwork',
        'label' => 'Paperwork',
        'description' => 'Form styled like an official document or paperwork.'
    ],
    [
        'id' => 'styleVector',
        'value' => 'vector',
        'label' => 'Vector',
        'description' => 'Clean, professional style resembling a fillable PDF document.'
    ],
    [
        'id' => 'styleRetro',
        'value' => 'retro',
        'label' => 'Retro',
        'description' => 'Classic, nostalgic retro-style theme resembling an old program.'
    ],
    [
        'id' => 'styleModern',
        'value' => 'modern',
        'label' => 'Modern',
        'description' => 'Modern, slick style with a clean, minimalist aesthetic.'
    ]
];

// Define the page content to be yielded in the master layout
ob_start();
?>

<div id="content-wrapper">
    <div id='builder'></div>

    <div id='form-name-container' style="margin-top: 20px;">
        <h3>Form Name:</h3>
        <input type='text' id='formName' class='form-control' placeholder='Enter form name' value="<?php echo htmlspecialchars($existingFormName); ?>">
    </div>

    <div id='form-style-container' style="margin-top: 20px;">
        <h3>Form Style:</h3>
        <div class="form-style-options">
        <?php
        foreach ($formStyles as $style) {?>
            <label class="style-option" for="<?php echo $style['id']; ?>">
                <input class="form-check-input" type="radio" name="formStyle" 
                       id="<?php echo $style['id']; ?>" value="<?php echo $style['value']; ?>">
                <span class="form-check-label"><?php echo $style['label']; ?></span>
                <div class="style-tooltip">
                    <i class="bi bi-info-circle"></i>
                    <div class="tooltip-content">
                        <p><?php echo $style['description']; ?></p>
                        <div class="tooltip-image">
                            <img src="<?php echo asset_path('images/form-types/' . $style['value'] . '.png'); ?>" 
                                 alt="<?php echo $style['label']; ?> style preview">
                        </div>
                    </div>
                </div>
            </label>
            <?php
        }
        ?>
        </div>
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

        <!-- New Template Title and Link Fields with Toggles -->
        <div id='template-extra-container' style="margin-top: 20px;">
        <h3>Additional Form Options:</h3>
        
        <!-- Template Title Toggle & Section -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Dynamic Form Title</h5>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="templateTitleToggle" 
                           <?php echo $enableTemplateTitle ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="templateTitleToggle">Enable</label>
                </div>
            </div>
            <div class="card-body" id="templateTitleSection" style="<?php echo $enableTemplateTitle ? '' : 'display: none;'; ?>">
                <small class="form-text text-muted d-block mb-2">Offer generated titles for users to copy and paste a title into their generated form, you <b>can</b> use wildcards.</small>
                <input type='text' id='templateTitle' class='form-control' 
                       placeholder='Generally used to create dynamic topic names' 
                       value="<?php echo htmlspecialchars($existingTemplateTitle); ?>">
            </div>
        </div>
        
        <!-- Template Link Toggle & Section -->
        <div class="card mb-3">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Form Link</h5>
                <div class="form-check form-switch">
                    <input class="form-check-input" type="checkbox" id="templateLinkToggle" 
                           <?php echo $enableTemplateLink ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="templateLinkToggle">Enable</label>
                </div>
            </div>
            <div class="card-body" id="templateLinkSection" style="<?php echo $enableTemplateLink ? '' : 'display: none;'; ?>">
                <small class="form-text text-muted d-block mb-2">Offer users a link to post their generated content, you <b>cannot</b> use wildcards.</small>
                <input type='text' id='templateLink' class='form-control' 
                       placeholder='Generally used to offer the user a link as to where to post the generated content' 
                       value="<?php echo htmlspecialchars($existingTemplateLink); ?>">
            </div>
        </div>
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

// Add page-specific CSS
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/builder.css') .'?v=' . APP_VERSION . '">';

// Add page-specific JavaScript
$existingSchema = $existingSchema ? $existingSchema : 'null';
$existingTemplate = json_encode($existingTemplate, JSON_UNESCAPED_SLASHES);
$existingTemplateTitle = json_encode($existingTemplateTitle, JSON_UNESCAPED_SLASHES);
$existingTemplateLink = json_encode($existingTemplateLink, JSON_UNESCAPED_SLASHES);
$enableTemplateTitleJS = $enableTemplateTitle ? 'true' : 'false';
$enableTemplateLinkJS = $enableTemplateLink ? 'true' : 'false';
$existingStyleJS = json_encode($existingFormStyle);
$siteURL = site_url();
$GLOBALS['page_js_vars'] = <<<JSVARS
let existingFormData = $existingSchema;
let existingFormNamePHP = "$existingFormName";
let existingTemplatePHP = $existingTemplate;
let existingTemplateTitlePHP = $existingTemplateTitle;
let existingTemplateLinkPHP = $existingTemplateLink;
let enableTemplateTitlePHP = $enableTemplateTitleJS;
let enableTemplateLinkPHP = $enableTemplateLinkJS;
let existingFormStyle = $existingStyleJS;
let siteURL = "$siteURL";
JSVARS;
$GLOBALS['page_javascript'] = '<script src="'. asset_path('js/app/builder.js') .'?v=' . APP_VERSION . '"></script>';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';