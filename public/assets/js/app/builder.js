(function() {
    const builderElement = document.getElementById('builder');
    const saveButton = document.getElementById('saveFormButton');
    const templateInput = document.getElementById('formTemplate');
    const wildcardList = document.getElementById('wildcard-list');
    const formNameInput = document.getElementById('formName');
    let componentCounter = 0;
    let builderInstance;
    let predefinedKeys = new Set();

    function collectKeys(schema, keysSet) {
        // Only add key to predefinedKeys if uniqueKey is true
        if (schema.key && schema.uniqueKey === true) {
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
            const response = await fetch('assets/components.json');
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }
            const componentData = await response.json();

            // Track registered component types to prevent duplicates
            const registeredTypes = new Set();

            componentData.forEach(groupData => {
                // Create the Builder Group
                builderOptions.builder[groupData.groupKey] = {
                    title: groupData.groupTitle,
                    default: false,
                    weight: groupData.groupWeight,
                    components: {}
                };

                groupData.components.forEach(componentDef => {
                    // Check if this is a direct component or a section wrapper
                    const isDirectComponent = componentDef.direct === true;
                    
                    if (isDirectComponent) {
                        // For direct components, the schema IS the component
                        const componentSchema = componentDef.schema;
                        collectKeys(componentSchema, predefinedKeys);
                        
                        // Add the direct component to the builder panel
                        builderOptions.builder[groupData.groupKey].components[componentDef.key] = {
                            title: componentDef.title,
                            group: groupData.groupKey,
                            icon: componentDef.icon,
                            schema: componentSchema
                        };
                        
                        // No need to register a new component type since we're using native types
                    } else {
                        // This is a section/container component (existing behavior)
                        collectKeys(componentDef.schema, predefinedKeys);
                        
                        // Add section component definition to the builder
                        builderOptions.builder[groupData.groupKey].components[componentDef.key] = {
                            title: componentDef.title,
                            group: groupData.groupKey,
                            icon: componentDef.icon,
                            schema: componentDef.schema
                        };

                        // Skip registration if this type is already registered
                        if (registeredTypes.has(componentDef.schema.type)) {
                            console.log(`Component type ${componentDef.schema.type} already registered, skipping duplicate registration`);
                            return;
                        }
                        
                        // Add this type to our registry
                        registeredTypes.add(componentDef.schema.type);

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
                    }
                });
            });
        } catch (error) {
            console.error("Error loading custom components:", error);
            alert("Failed to load custom components. Check the console for details.");
        }
        return builderOptions;
    }

    // Initial Builder Options
    let builderOptions = {
        builder: {
            resource: false,
            premium: false,
            advanced: {
                weight: 20,
                components: {
                    email: false,
                    url: true,
                    phoneNumber: false,
                    tags: false,
                    address: false,
                    currency: false,
                    survey: true,
                    signature: false
                }
            },
            basic: {
                weight: 10,
                components: {
                    password: false,
                    number: false
                }
            },
            data: {
                components: {
                    container: false,
                    datamap: false,
                    editgrid: false
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
        enhanceBuilderInit();
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
                
                // Try to copy text to clipboard using the best available method
                copyToClipboard(textToCopy)
                    .then(() => {
                        // Show feedback that copy was successful
                        const originalIcon = this.innerHTML;
                        this.innerHTML = '<i class="bi bi-check-lg"></i>';
                        setTimeout(() => {
                            this.innerHTML = originalIcon;
                        }, 1000);
                    })
                    .catch(err => {
                        console.error('Copy failed:', err);
                        alert('Could not copy to clipboard. Please try selecting and copying manually.');
                    });
            });
        });
        
        // Check for used wildcards in the template
        checkUsedWildcards();
    }
    
    /**
     * Cross-browser clipboard copy function
     * @param {string} text - The text to copy
     * @returns {Promise} - Resolves when copy is successful
     */
    function copyToClipboard(text) {
        // Modern approach - Clipboard API (not supported in all browsers)
        if (navigator && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
            return navigator.clipboard.writeText(text);
        }
        
        // Fallback approach - Create a temporary element and copy from it
        return new Promise((resolve, reject) => {
            try {
                const textarea = document.createElement('textarea');
                textarea.value = text;
                textarea.style.position = 'fixed';  // Avoid scrolling to bottom
                textarea.style.opacity = '0';
                document.body.appendChild(textarea);
                textarea.select();
                
                // Execute the copy command
                const successful = document.execCommand('copy');
                document.body.removeChild(textarea);
                
                if (successful) {
                    resolve();
                } else {
                    reject(new Error('Unable to copy'));
                }
            } catch (err) {
                reject(err);
            }
        });
    }

    function getComponentKeys(component) {
        if (component.type === 'button' && component.action === 'submit') return [];
        let keys = [];

        if (component.type === 'datagrid' && component.key) {
            keys.push(`@START_${component.key}@`, `@END_${component.key}@`);
        }

        if (['textfield', 'textarea', 'checkbox', 'select', 'radio', 'hidden', 'datetime', 'day', 'time'].includes(component.type)) {
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

    function handleComponentUpdate(component) {
        if (component.action === 'submit') return;
        
        // Skip key generation for components with uniqueKey=true
        if (component.uniqueKey === true) {
            return;
        }
        
        // Also check if component has uniqueKey=false explicitly set (force key generation)
        if (component.uniqueKey === false) {
            const newKey = generateKey(component.label, component);
            if (component.key !== newKey) {
                component.key = newKey;
                builderInstance.redraw();
                setTimeout(updateWildcards, 0);
            }
            return;
        }
        
        // Check predefined keys for backward compatibility
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

    // Updates to builder.js for the form style selector

    // Add to the saveForm function to include style selection
    async function saveForm() {
        const formSchema = builderInstance?.form;
        if (!formSchema) return alert('No form schema found');

        const formName = formNameInput.value;
        const formStyle = document.querySelector('input[name="formStyle"]:checked').value;

        try {
            // Get the last returned CSRF token if it exists
            const csrfToken = window.lastCsrfToken || '';

            const response = await fetch('ajax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'schema',
                    schema: formSchema,
                    template: templateInput.value,
                    formName: formName,
                    formStyle: formStyle, // Add the form style
                    csrf_token: csrfToken // Include CSRF token
                })
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.error);

            // Store the new CSRF token for next request
            if (data.csrf_token) {
                window.lastCsrfToken = data.csrf_token;
            }

            // Extract just the form ID from the filename
            let formId = data.filename.replace('forms/', '').replace('_schema.json', '');
            
            // Make sure we don't include any directory paths in the formId
            if (formId.includes('/') || formId.includes('\\')) {
                formId = formId.split(/[\/\\]/).pop();
            }
            
            const shareUrl = siteURL + `form?f=${formId}`;

            document.getElementById('shareable-link').textContent = shareUrl;
            document.getElementById('shareable-link').href = shareUrl;
            document.getElementById('go-to-form-button').href = shareUrl;
            document.getElementById('success-message').style.display = 'block';
        } catch (error) {
            console.error('Save error:', error);
            alert('Error saving form: ' + (error.message || 'Unknown error'));
        }
    }

    // Updates to builder.js for the form style selector

    // Add to the saveForm function to include style selection
    async function saveForm() {
        const formSchema = builderInstance?.form;
        if (!formSchema) return alert('No form schema found');

        const formName = formNameInput.value;
        const formStyle = document.querySelector('input[name="formStyle"]:checked').value;

        try {
            // Get the last returned CSRF token if it exists
            const csrfToken = window.lastCsrfToken || '';

            const response = await fetch('ajax', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    type: 'schema',
                    schema: formSchema,
                    template: templateInput.value,
                    formName: formName,
                    formStyle: formStyle, // Add the form style
                    csrf_token: csrfToken // Include CSRF token
                })
            });

            const data = await response.json();
            if (!data.success) throw new Error(data.error);

            // Store the new CSRF token for next request
            if (data.csrf_token) {
                window.lastCsrfToken = data.csrf_token;
            }

            // Extract just the form ID from the filename
            let formId = data.filename.replace('forms/', '').replace('_schema.json', '');
            
            // Make sure we don't include any directory paths in the formId
            if (formId.includes('/') || formId.includes('\\')) {
                formId = formId.split(/[\/\\]/).pop();
            }
            
            const shareUrl = siteURL + `form?f=${formId}`;

            document.getElementById('shareable-link').textContent = shareUrl;
            document.getElementById('shareable-link').href = shareUrl;
            document.getElementById('go-to-form-button').href = shareUrl;
            document.getElementById('success-message').style.display = 'block';
        } catch (error) {
            console.error('Save error:', error);
            alert('Error saving form: ' + (error.message || 'Unknown error'));
        }
    }

    // Add an initialization function to set the form style when editing an existing form
    function initFormStyle() {
        if (typeof existingFormStyle !== 'undefined' && existingFormStyle) {
            const styleRadio = document.querySelector(`input[name="formStyle"][value="${existingFormStyle}"]`);
            if (styleRadio) {
                styleRadio.checked = true;
            }
        }
    }

    // Call this after the form builder is initialized
    function enhanceBuilderInit() {
        // Set up the form style radio buttons for better UX
        document.querySelectorAll('.style-option').forEach(option => {
            option.addEventListener('click', function(e) {
                // If clicking on the div but not directly on the radio button, check the radio button
                if (e.target.type !== 'radio') {
                    const radio = this.querySelector('input[type="radio"]');
                    if (radio) {
                        // Uncheck all other radio buttons first
                        document.querySelectorAll('input[name="formStyle"]').forEach(r => {
                            r.checked = false;
                        });
                        
                        // Check this radio button
                        radio.checked = true;
                        
                        // Trigger a change event to ensure any listeners are notified
                        radio.dispatchEvent(new Event('change'));
                    }
                }
            });
        });

        // Initialize form style from existing data
        initFormStyle();
    }
})();