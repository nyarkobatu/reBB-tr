<?php
require_once 'site.php';

$existingSchema = null;
$existingFormName = '';
$existingTemplate = '';

if (isset($_GET['f']) && !empty($_GET['f'])) {
    $formId = $_GET['f'];
    $filename = 'forms/' . $formId . '_schema.json';

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
?>
<!DOCTYPE html>
<html>
<head>
    <title><?php echo SITE_NAME; ?> - <?php echo SITE_DESCRIPTION; ?></title>
    <link rel="apple-touch-icon" sizes="180x180" href="/resources/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/resources/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/resources/favicon-16x16.png">
    <link rel="manifest" href="/resources/site.webmanifest">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.form.io/js/formio.full.min.css">
    <script src='https://cdn.form.io/js/formio.full.min.js'></script>
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }
        #content-wrapper {
            padding: 20px;
            flex: 1;
        }
        #builder {

        }
        #button-container { margin-top: 20px; text-align: center; }
        #template-container { margin-top: 20px; }
        #wildcard-container { margin-bottom: 10px; }
        #wildcard-list { display: inline-block; }
        
        /* Wildcard styling */
        .wildcard {
            display: inline-flex;
            align-items: center;
            margin-right: 8px;
            margin-bottom: 8px;
            padding: 5px 10px;
            background-color: #f0f0f0;
            border: 1px solid #ccc;
            border-radius: 5px;
            font-size: 0.9em;
            transition: all 0.3s ease;
            cursor: default;
            position: relative;
        }

        /* Copy button styling */
        .copy-btn {
            margin-left: 6px;
            background: none;
            border: none;
            color: #6c757d;
            padding: 2px 5px;
            cursor: pointer;
            border-radius: 3px;
            font-size: 0.85em;
            transition: all 0.2s ease;
        }

        .copy-btn:hover {
            color: #007bff;
            background-color: rgba(0, 123, 255, 0.1);
        }

        /* Used wildcard styling */
        .wildcard-used {
            background-color: #d4edda;
            border-color: #c3e6cb;
            color: #155724;
            text-decoration: line-through;
        }

        .wildcard-used .copy-btn {
            color: #155724;
        }

        .wildcard-used .copy-btn:hover {
            color: #0b2e13;
            background-color: rgba(21, 87, 36, 0.1);
        }
        
        #success-message { margin-top: 20px; display: none; }
        #shareable-link { word-break: break-all; display: inline-block; margin: 10px 0; }
        .footer {
            background-color: #e0e0e0;
            padding: 20px 0;
            text-align: center;
            color: #555;
            margin-top: auto;
        }
        .footer a {
            color: #007bff;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
        .dark-mode-toggle {
            cursor: pointer;
        }

        /* Danger styling for unused dataset wildcards */
        .wildcard-danger {
            background-color: #f8d7da;
            border-color: #dc3545;
            color: #721c24;
            border-width: 2px;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
            font-weight: bold;
        }

        /* Copy button styling within danger wildcards */
        .wildcard-danger .copy-btn {
            color: #721c24;
        }

        .wildcard-danger .copy-btn:hover {
            color: #dc3545;
            background-color: rgba(220, 53, 69, 0.1);
        }
    </style>
    
    <!-- Dark Mode Styles -->
    <style>
        /* Dark Mode Styles */
        body.dark-mode {
            background-color: #121212;
            color: #e0e0e0;
        }
        
        /* Header/Title styles */
        body.dark-mode h1, 
        body.dark-mode h2, 
        body.dark-mode h3, 
        body.dark-mode h4, 
        body.dark-mode h5, 
        body.dark-mode h6 {
            color: #ffffff;
        }
        
        /* Container/Form styles */
        body.dark-mode .form-box,
        body.dark-mode #form-container,
        body.dark-mode #output-container,
        body.dark-mode #content-wrapper,
        body.dark-mode .card,
        body.dark-mode .table {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }
        
        /* Form controls */
        body.dark-mode .form-control,
        body.dark-mode input,
        body.dark-mode textarea,
        body.dark-mode select {
            background-color: #2d2d2d;
            color: #e0e0e0;
            border-color: #444;
        }
        
        /* Placeholder text in dark mode */
        body.dark-mode ::placeholder,
        body.dark-mode input::placeholder,
        body.dark-mode textarea::placeholder {
            color: #aaaaaa;
            opacity: 1;
        }
        
        /* Builder specific */
        body.dark-mode #builder,
        body.dark-mode .formio-builder,
        body.dark-mode .formio-dialog .formio-dialog-content {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }
        
        /* Builder sidebar text */
        body.dark-mode #builder-sidebar-build,
        body.dark-mode #builder-sidebar-build *,
        body.dark-mode .builder-sidebar,
        body.dark-mode .builder-sidebar *,
        body.dark-mode .formio-builder-panel-header,
        body.dark-mode .formio-builder-group-header,
        body.dark-mode .formio-builder-component {
            color: #ffffff;
        }
        
        /* Wildcard dark mode adjustments */
        body.dark-mode .wildcard {
            background-color: #2d2d2d;
            border-color: #444;
            color: #e0e0e0;
        }

        body.dark-mode .copy-btn {
            color: #aaa;
        }

        body.dark-mode .copy-btn:hover {
            color: #4da3ff;
            background-color: rgba(77, 163, 255, 0.1);
        }

        body.dark-mode .wildcard-used {
            background-color: #1e462d;
            border-color: #285e3b;
            color: #a3d7b5;
        }

        body.dark-mode .wildcard-used .copy-btn {
            color: #a3d7b5;
        }

        body.dark-mode .wildcard-used .copy-btn:hover {
            color: #c3e6cb;
            background-color: rgba(163, 215, 181, 0.1);
        }
        
        /* Footer */
        body.dark-mode .footer {
            background-color: #1e1e1e;
            color: #aaa;
        }
        
        body.dark-mode .footer a {
            color: #4da3ff;
        }
        
        /* Success message */
        body.dark-mode #success-message.alert-success {
            background-color: #1e462d;
            color: #e0e0e0;
            border-color: #285e3b;
        }

        /* Dark mode for danger wildcard state */
        body.dark-mode .wildcard-danger {
            background-color: #2c1315;
            border-color: #dc3545;
            color: #f8d7da;
            box-shadow: 0 0 0 0.2rem rgba(220, 53, 69, 0.25);
            font-weight: bold;
        }

        body.dark-mode .wildcard-danger .copy-btn {
            color: #f8d7da;
        }

        body.dark-mode .wildcard-danger .copy-btn:hover {
            color: #ff6b70;
            background-color: rgba(220, 53, 69, 0.2);
        }

        /* Table component dark mode styles */
        body.dark-mode .table-responsive,
        body.dark-mode table.table,
        body.dark-mode .formio-component-table,
        body.dark-mode .formio-component-table .table,
        body.dark-mode .formio-component-table .table tbody,
        body.dark-mode .formio-component-table .table tr,
        body.dark-mode .formio-component-table .table td,
        body.dark-mode .formio-component-table .table th {
            background-color: #1e1e1e;
            color: #e0e0e0;
            border-color: #444;
        }

        /* Additional fixes for form.io builder specific tables */
        body.dark-mode .formbuilder .table,
        body.dark-mode .formbuilder .table thead,
        body.dark-mode .formbuilder .table tbody,
        body.dark-mode .formbuilder .table tr,
        body.dark-mode .formbuilder .table th,
        body.dark-mode .formbuilder .table td,
        body.dark-mode div[ref="element"] table {
            background-color: #1e1e1e;
            color: #e0e0e0;
            border-color: #444;
        }

        /* Handle striped tables */
        body.dark-mode .table-striped tbody tr:nth-of-type(odd) {
            background-color: #2a2a2a;
        }

        /* Handle hover effect */
        body.dark-mode .table-hover tbody tr:hover {
            background-color: #333;
        }

        /* Fix any remaining white backgrounds in form.io components */
        body.dark-mode .formio-component,
        body.dark-mode .formio-container,
        body.dark-mode .formio-form,
        body.dark-mode .formio-component-table .formio-table {
            background-color: #1e1e1e;
        }

        /* Fix table cell inputs in dark mode */
        body.dark-mode .formio-component-table input,
        body.dark-mode .formio-component-table select,
        body.dark-mode .formio-component-table textarea {
            background-color: #2d2d2d;
            color: #e0e0e0;
            border-color: #444;
        }

      /* Dropdown option styling for dark mode */
      body.dark-mode select option {
          background-color: #2d2d2d !important;
          color: #e0e0e0 !important;
      }

      /* Handle optgroup if used */
      body.dark-mode select optgroup {
          background-color: #2d2d2d !important;
          color: #e0e0e0 !important;
      }

      /* Form.io specific dropdown components */
      body.dark-mode .choices__list--dropdown .choices__item {
          background-color: #2d2d2d !important;
          color: #e0e0e0 !important;
      }

      body.dark-mode .choices__list--dropdown {
          background-color: #2d2d2d !important;
          border-color: #444 !important;
      }

      /* Dropdown search input styling */
      body.dark-mode .choices__input,
      body.dark-mode .choices__input--cloned {
          background-color: #383838 !important;
          color: #e0e0e0 !important;
          border-color: #555 !important;
      }

      body.dark-mode .choices__input::placeholder {
          color: #aaa !important;
      }

      /* Search container styling */
      body.dark-mode .choices__list--dropdown .choices__list {
          background-color: #2d2d2d !important;
      }

      /* Select2 search field if used */
      body.dark-mode .select2-search, 
      body.dark-mode .select2-search--dropdown {
          background-color: #2d2d2d !important;
      }

      body.dark-mode .select2-search__field {
          background-color: #383838 !important;
          color: #e0e0e0 !important;
          border-color: #555 !important;
      }

      body.dark-mode .select2-search--dropdown .select2-search__field::placeholder {
          color: #aaa !important;
      }

      /* Generic search inputs in dropdowns */
      body.dark-mode .dropdown-menu input[type="search"],
      body.dark-mode .dropdown-menu input[type="text"] {
          background-color: #383838 !important;
          color: #e0e0e0 !important;
          border-color: #555 !important;
      }

      /* Active/highlighted state */
      body.dark-mode .choices__list--dropdown .choices__item--selectable.is-highlighted {
          background-color: #444 !important;
      }

      /* Select2 component if used */
      body.dark-mode .select2-dropdown,
      body.dark-mode .select2-dropdown--below,
      body.dark-mode .select2-dropdown--above {
          background-color: #2d2d2d !important;
          color: #e0e0e0 !important;
          border-color: #444 !important;
      }

      body.dark-mode .select2-results__option {
          background-color: #2d2d2d !important;
          color: #e0e0e0 !important;
      }

      body.dark-mode .select2-results__option--highlighted,
      body.dark-mode .select2-results__option[aria-selected=true] {
          background-color: #444 !important;
          color: #ffffff !important;
      }

      /* Bootstrap dropdown menus if used */
      body.dark-mode .dropdown-menu {
          background-color: #2d2d2d !important;
          border-color: #444 !important;
      }

      body.dark-mode .dropdown-item {
          color: #e0e0e0 !important;
      }

      body.dark-mode .dropdown-item:hover,
      body.dark-mode .dropdown-item:focus {
          background-color: #444 !important;
          color: #ffffff !important;
      }
    </style>
</head>
<body>
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

    <footer class="footer">
       <p>Made with ❤️ by <a href="https://booskit.dev/">booskit</a></br>
        <a href="<?php echo FOOTER_GITHUB; ?>">Github</a> • <a href="#" class="dark-mode-toggle">🌙 Dark Mode</a></br>
        <span style="font-size: 12px;"><?php echo SITE_VERSION; ?></span></p>
    </footer>

    <script>
        (function() {
            const builderElement = document.getElementById('builder');
            const saveButton = document.getElementById('saveFormButton');
            const templateInput = document.getElementById('formTemplate');
            const wildcardList = document.getElementById('wildcard-list');
            const formNameInput = document.getElementById('formName');
            let componentCounter = 0;
            let builderInstance;
            let predefinedKeys = new Set();

            let existingFormData = <?php echo $existingSchema ? $existingSchema : 'null'; ?>;
            let existingFormNamePHP = "<?php echo $existingFormName; ?>";
            let existingTemplatePHP = <?php echo json_encode($existingTemplate, JSON_UNESCAPED_SLASHES); ?>;

            function collectKeys(schema, keysSet) {
                if (schema.key) {
                    keysSet.add(schema.key);
                }
                if (schema.components) {
                    schema.components.forEach(c => collectKeys(c, keysSet));
                }
                if (schema.columns) {
                    schema.columns.forEach(col => {
                        if (col.components) {
                            col.components.forEach(c => collectKeys(c, keysSet));
                        }
                    });
                }
            }

            async function registerCustomComponents(builderOptions) {
                try {
                    // Fetch the components.json file
                    const response = await fetch('components.json');
                    if (!response.ok) {
                        throw new Error(`HTTP error! status: ${response.status}`);
                    }
                    const componentData = await response.json();


                  componentData.forEach(groupData => {
                    // Create the Builder Group
                    builderOptions.builder[groupData.groupKey] = {
                      title: groupData.groupTitle,
                      default: false,
                      weight: groupData.groupWeight,
                      components: {}
                    };

                    groupData.components.forEach(componentDef => {
                        collectKeys(componentDef.schema, predefinedKeys);

                        // Add component definition to the builder
                        builderOptions.builder[groupData.groupKey].components[componentDef.key] = {
                            title: componentDef.title,
                            group: groupData.groupKey,
                            icon: componentDef.icon,
                            schema: componentDef.schema
                        };

                        const isContainer = componentDef.schema.components && Array.isArray(componentDef.schema.components);
                        const baseComponent = isContainer 
                            ? Formio.Components.components.container 
                            : Formio.Components.components.component;

                        Formio.Components.addComponent(
                            componentDef.schema.type,
                            class extends baseComponent {
                                static schema(...extend) {
                                    return baseComponent.schema(
                                        componentDef.schema,
                                        ...extend
                                    );
                                }

                                static get builderInfo() {
                                    return {
                                        title: componentDef.title,
                                        group: groupData.groupKey,
                                        icon: componentDef.icon,
                                        weight: 10,
                                        schema: this.schema()
                                    };
                                }
                            },
                            {
                                template: isContainer 
                                    ? `<div class="${componentDef.schema.type}-component"><div ref="components"></div></div>`
                                    : `<div class="${componentDef.schema.type}-component">{{ view }}</div>`
                            }
                        );
                    });
                  });
                } catch (error) {
                    console.error("Error loading custom components:", error);
                    alert("Failed to load custom components.  Check the console for details.");
                }
                return builderOptions;
            }

            // Initial Builder Options
            let builderOptions = {
                builder: {
                    resource: false,
                    advanced: false,
                    premium: false,
                    basic: {
                        weight: 10,
                        components: {
                            password: false,
                            number: false
                        }
                    }
                }
            };

            // Async function to initialize the builder *after* fetching components
            async function initializeFormio() {
                builderOptions = await registerCustomComponents(builderOptions); // Await the registration
                Formio.builder(builderElement, existingFormData, builderOptions).then(builder => {
                    builderInstance = builder;
                    initializeBuilder();
                    if (existingFormNamePHP) {
                        formNameInput.value = existingFormNamePHP;
                    }
                    if (existingTemplatePHP) {
                        templateInput.value = existingTemplatePHP;
                    }
                });
            }

            initializeFormio(); // Call the async initialization function

            function initializeBuilder() {
                builderInstance.on('change', updateWildcards);
                builderInstance.on('updateComponent', handleComponentUpdate);
                saveButton.addEventListener('click', saveForm);
                setupTemplateListener();
                updateWildcards();
                
                // Create a help text element that we'll show/hide as needed
                const wildcardContainer = document.getElementById('wildcard-container');
                const helpText = document.createElement('div');
                helpText.id = 'dataset-help-text';
                helpText.className = 'alert alert-info mt-2';
                helpText.style.display = 'none'; // Hidden by default
                helpText.innerHTML = '<small><strong>Note:</strong> Dataset wildcards (highlighted in red) must be included in your template before saving. They define where repeating content will appear.</small>';
                wildcardContainer.appendChild(helpText);
            }

            function generateUniqueId() {
                componentCounter++;
                return `${componentCounter}${Math.random().toString(36).substring(2, 6).toUpperCase()}`;
            }

            function generateKey(label, component) {
                if (component.type === 'button' && component.action === 'submit') return component.key || '';
                const cleanLabel = label.toUpperCase().replace(/ /g, '_').replace(/[^A-Z0-9_]/g, '');
                return `${cleanLabel}_${generateUniqueId()}`;
            }

            function updateWildcards() {
                const components = builderInstance?.form?.components || [];
                const wildcardArray = components.flatMap(getComponentKeys);
                
                // Check if there are any dataset wildcards
                const hasDatasetWildcards = wildcardArray.some(key => 
                    key.startsWith('@START_') || key.startsWith('@END_')
                );
                
                // Show or hide help text based on whether we have dataset wildcards
                const helpText = document.getElementById('dataset-help-text');
                if (helpText) {
                    helpText.style.display = hasDatasetWildcards ? 'block' : 'none';
                }
                
                wildcardList.innerHTML = wildcardArray
                    .map(key => {
                        const wildcardText = `{${key}}`;
                        const isDatasetWildcard = key.startsWith('@START_') || key.startsWith('@END_');
                        const datasetClass = isDatasetWildcard ? 'wildcard-dataset' : '';
                        const dangerClass = isDatasetWildcard ? 'wildcard-danger' : '';
                        
                        return `
                            <span class="wildcard ${datasetClass} ${dangerClass}" data-wildcard="${key}">
                                ${wildcardText}
                                <button class="copy-btn" data-clipboard="${wildcardText}" title="Copy to clipboard">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </span>
                        `;
                    })
                    .join('');
                
                // Add event listeners to the copy buttons
                document.querySelectorAll('.copy-btn').forEach(btn => {
                    btn.addEventListener('click', function(e) {
                        e.stopPropagation();
                        const textToCopy = this.getAttribute('data-clipboard');
                        navigator.clipboard.writeText(textToCopy)
                            .then(() => {
                                // Show feedback that copy was successful
                                const originalIcon = this.innerHTML;
                                this.innerHTML = '<i class="bi bi-check-lg"></i>';
                                setTimeout(() => {
                                    this.innerHTML = originalIcon;
                                }, 1000);
                            })
                            .catch(err => console.error('Copy failed:', err));
                    });
                });
                
                // Check for used wildcards in the template
                checkUsedWildcards();
            }

            function getComponentKeys(component) {
                if (component.type === 'button' && component.action === 'submit') return [];
                let keys = [];

                if (component.type === 'datagrid' && component.key) {
                    keys.push(`@START_${component.key}@`, `@END_${component.key}@`);
                }

                if (['textfield', 'textarea', 'checkbox', 'select', 'radio', 'hidden'].includes(component.type)) {
                    if (component.key) {
                        keys.push(component.key);
                    }
                }

                // Recursively process components and columns, regardless of the current component's type
                if (component.components) keys.push(...component.components.flatMap(getComponentKeys));
                if (component.columns) keys.push(...component.columns.flatMap(col => col.components?.flatMap(getComponentKeys) || []));

                return keys;
            }
            
            // Function to check if all dataset wildcards are used
            function checkAllDatasetWildcardsUsed() {
                const datasetWildcards = document.querySelectorAll('.wildcard-dataset');
                
                // If there are no dataset wildcards, return true (no blocking)
                if (datasetWildcards.length === 0) return true;
                
                // Check if all dataset wildcards are used
                for (const wildcard of datasetWildcards) {
                    if (!wildcard.classList.contains('wildcard-used')) {
                        return false;
                    }
                }
                
                return true;
            }

            // Update the save button state based on dataset wildcards
            function updateSaveButtonState() {
                const allDatasetWildcardsUsed = checkAllDatasetWildcardsUsed();
                saveButton.disabled = !allDatasetWildcardsUsed;
                
                if (!allDatasetWildcardsUsed) {
                    saveButton.title = 'All dataset wildcards must be used in the template before saving';
                } else {
                    saveButton.title = 'Save form';
                }
            }

            // Function to check which wildcards are being used in the template
            function checkUsedWildcards() {
                const templateText = templateInput.value;
                const wildcardElements = document.querySelectorAll('.wildcard');
                
                wildcardElements.forEach(element => {
                    const key = element.getAttribute('data-wildcard');
                    const wildcardPattern = new RegExp(`\\{${key}\\}`, 'g');
                    
                    if (wildcardPattern.test(templateText)) {
                        // Wildcard is being used in the template
                        element.classList.add('wildcard-used');
                        if(element.classList.contains('wildcard-dataset')) {
                            element.classList.remove('wildcard-danger');
                        }
                    } else {
                        // Wildcard is not being used
                        element.classList.remove('wildcard-used');
                        if(element.classList.contains('wildcard-dataset')) {
                            element.classList.add('wildcard-danger');
                        }
                    }
                });
                
                // Update save button state
                updateSaveButtonState();
            }

            // Add event listener to the template textarea to detect changes
            function setupTemplateListener() {
                templateInput.addEventListener('input', checkUsedWildcards);
                // Initial check for save button state
                setTimeout(updateSaveButtonState, 500);
            }

            // Modified handleComponentUpdate function
            function handleComponentUpdate(component) {
                if (component.action === 'submit') return;
                
                // Check if component key is in predefined set
                if (predefinedKeys.has(component.key)) {
                    return; // Skip key generation for predefined components
                }

                const newKey = generateKey(component.label, component);
                if (component.key !== newKey) {
                    component.key = newKey;
                    builderInstance.redraw();
                    setTimeout(updateWildcards, 0);
                }
            }

             async function saveForm() {
                const formSchema = builderInstance?.form;
                if (!formSchema) return alert('No form schema found');

                const formName = formNameInput.value;

                try {
                    const response = await fetch('ajax.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({
                            type: 'schema',
                            schema: formSchema,
                            template: templateInput.value,
                            formName: formName
                        })
                    });

                    const data = await response.json();
                    if (!data.success) throw new Error(data.error);

                    const formId = data.filename.replace('forms/', '').replace('_schema.json', '');
                    const shareUrl = `<?php echo SITE_URL; ?>/form.php?f=${formId}`;

                    document.getElementById('shareable-link').textContent = shareUrl;
                    document.getElementById('shareable-link').href = shareUrl;
                    document.getElementById('go-to-form-button').href = shareUrl;
                    document.getElementById('success-message').style.display = 'block';
                } catch (error) {
                    console.error('Save error:', error);
                    alert('Error saving form. Check console.');
                }
            }
        })();
        
        // Dark Mode Functions
        // Function to set a cookie
        function setDarkModeCookie(darkMode) {
            const date = new Date();
            date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000)); // 1 year
            const expires = "expires=" + date.toUTCString();
            document.cookie = `darkMode=${darkMode};${expires};path=/`;
        }
        
        // Function to get a cookie value
        function getDarkModeCookie() {
            const name = "darkMode=";
            const decodedCookie = decodeURIComponent(document.cookie);
            const cookies = decodedCookie.split(';');
            for (let cookie of cookies) {
                cookie = cookie.trim();
                if (cookie.startsWith(name)) {
                    return cookie.substring(name.length, cookie.length);
                }
            }
            return null;
        }
        
        // Function to toggle dark mode
        function toggleDarkMode() {
            const body = document.body;
            const isDarkMode = body.classList.toggle('dark-mode');
            setDarkModeCookie(isDarkMode ? 'true' : 'false');
            
            // Update toggle text
            const toggleLinks = document.querySelectorAll('.dark-mode-toggle');
            toggleLinks.forEach(link => {
                link.textContent = isDarkMode ? '☀️ Light Mode' : '🌙 Dark Mode';
            });
        }
        
        // Function to initialize dark mode based on cookie
        function initDarkMode() {
            const darkModeSetting = getDarkModeCookie();
            if (darkModeSetting === 'true') {
                document.body.classList.add('dark-mode');
            }
            
            // Set initial toggle text
            const toggleLinks = document.querySelectorAll('.dark-mode-toggle');
            const isDarkMode = document.body.classList.contains('dark-mode');
            toggleLinks.forEach(link => {
                link.textContent = isDarkMode ? '☀️ Light Mode' : '🌙 Dark Mode';
            });
        }
        
        // Add event listeners to dark mode toggles
        document.addEventListener('DOMContentLoaded', function() {
            const toggleLinks = document.querySelectorAll('.dark-mode-toggle');
            toggleLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    e.preventDefault();
                    toggleDarkMode();
                });
            });
            
            // Initialize dark mode from cookie
            initDarkMode();
        });
    </script>
</body>
</html>