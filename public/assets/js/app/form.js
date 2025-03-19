// Wait for all scripts to load before initializing the form
document.addEventListener('DOMContentLoaded', function() {
    // Track form usage
    trackFormUsage(false);
    
    // Initialize with a delay to ensure ComponentRegistry is available
    setTimeout(function() {
        initializeFormWithComponents();
    }, 100);
});

// Initialize form with components loaded first
function initializeFormWithComponents() {
    console.log('Initializing form with components...');
    
    // If ComponentRegistry is available, use it
    if (window.ComponentRegistry) {
        const basePath = ASSETS_BASE_PATH || '';
        console.log('Component Registry found, initializing components from:', basePath);
        
        // Initialize the registry and wait for components to load
        ComponentRegistry.init(basePath)
            .then(function() {
                console.log('Components loaded successfully, creating form...');
                setTimeout(function() {
                    // Double-check component registration
                    verifyComponentRegistration();
                    // Create the form
                    createFormWithSchema();
                }, 100); // Small delay to ensure components are fully registered
            })
            .catch(function(error) {
                console.error('Failed to load components:', error);
                // Create form even if component loading fails
                createFormWithSchema();
            });
    } else {
        console.warn('ComponentRegistry not found, proceeding without custom components');
        // Create form without custom components
        createFormWithSchema();
    }
}

// Verify component registration
function verifyComponentRegistration() {
    return null;
}

// Create the form with schema
function createFormWithSchema() {
    console.log('Creating form with schema...');
    
    // Create the actual form with Form.io
    Formio.createForm(document.getElementById('formio'), formSchema, { noAlerts: true })
        .then(function(form) {
            console.log('Form created successfully');
            setupFormEventHandlers(form);
        })
        .catch(function(error) {
            console.error('Form initialization error:', error);
            document.getElementById('form-container').innerHTML = `
                <div class="alert alert-danger">
                    Error loading form: ${error.message}
                </div>
            `;
        });
}

// Set up all form event handlers
function setupFormEventHandlers(form) {
    const outputContainer = document.getElementById('output-container');
    const outputField = document.getElementById('output');
    const copyButton = document.getElementById('copyOutputBtn');
    const templateTitleContainer = document.getElementById('template-title-container');
    const generatedTitleField = document.getElementById('generated-title');
    const postContentBtn = document.getElementById('postContentBtn');
    
    // Initialize template title and link features based on toggle states
    if (typeof enableTemplateTitle !== 'undefined' && enableTemplateTitle && 
        typeof formTemplateTitle !== 'undefined' && formTemplateTitle) {
        templateTitleContainer.style.display = 'block';
    } else {
        templateTitleContainer.style.display = 'none';
    }

    if (typeof enableTemplateLink !== 'undefined' && enableTemplateLink && 
        typeof formTemplateLink !== 'undefined' && formTemplateLink) {
        postContentBtn.style.display = 'inline-block';
        postContentBtn.href = formTemplateLink;
    } else {
        postContentBtn.style.display = 'none';
    }
    
    // Initialize the copy button functionality
    if (copyButton) {
        copyButton.addEventListener('click', function() {
            if (outputField.value) {
                copyToClipboard(outputField.value)
                    .then(() => {
                        // Show feedback that copy was successful
                        const originalText = copyButton.innerHTML;
                        copyButton.innerHTML = '<i class="bi bi-check-lg"></i> Copied!';
                        
                        // Reset button text after delay
                        setTimeout(() => {
                            copyButton.innerHTML = originalText;
                        }, 2000);
                    })
                    .catch(err => {
                        console.error('Copy failed:', err);
                        alert('Could not copy to clipboard. Please try selecting and copying manually.');
                    });
            }
        });
    }
    
    // Ensure every datagrid starts with one empty row that will be hidden
    // and a second visible row for the user
    setTimeout(() => {
        Object.keys(form.components).forEach(key => {
            const component = form.getComponent(key);
            if (component && component.type === 'datagrid') {
                // Make sure we always have at least 2 rows (first hidden, second visible)
                if (component.rows && component.rows.length < 2) {
                    component.addRow();
                }
            }
        });
    }, 500);

    // Handle form submission
    form.on('submit', function(submission) {
        // Clone the data to prevent any issues with form reset
        const submissionCopy = JSON.parse(JSON.stringify(submission.data));
        
        // Find and process all date inputs in the form
        document.querySelectorAll('input[type="hidden"][value*="T00:00:00"]').forEach(hiddenInput => {
            const key = hiddenInput.name.replace('data[', '').replace(']', '');
            
            // Find the visible input next to this hidden input
            const visibleInput = hiddenInput.nextElementSibling;
            
            if (visibleInput && visibleInput.classList.contains('input') && key in submissionCopy) {
                // Replace the ISO date with the displayed date value
                submissionCopy[key] = visibleInput.value;
            }
        });
        
        // Process the template with our updated data, passing the form schema
        const generatedOutput = processTemplate(formTemplate, submissionCopy, form.form);
        outputField.value = generatedOutput;
        
        // Process template title if it's enabled and exists
        if (typeof enableTemplateTitle !== 'undefined' && enableTemplateTitle && 
            typeof formTemplateTitle !== 'undefined' && formTemplateTitle) {
            const generatedTitle = processTemplate(formTemplateTitle, submissionCopy, form.form);
            generatedTitleField.value = generatedTitle;
            templateTitleContainer.style.display = 'block';
        }
        
        trackFormUsage(true);
        form.emit('submitDone');
    });
}

// Process template with data
function processTemplate(template, data, formSchema) {
    // Create a recursive decode function to handle multiple levels of HTML entity encoding
    function decodeHTMLEntities(text) {
        const textArea = document.createElement('textarea');
        textArea.innerHTML = text;
        const decoded = textArea.value;
        
        // If the decoded text is different from the input, it might have more entities to decode
        if (decoded !== text && decoded.includes('&')) {
            // Recursively decode until no more changes
            return decodeHTMLEntities(decoded);
        }
        return decoded;
    }

    // Filter out data from components with disableWildcard=true
    function filterDisabledWildcards(data, schema) {
        if (!schema || !schema.components) return data;
        
        const filteredData = {...data};
        const disabledKeys = new Set();
        
        // Recursive function to find all components with disableWildcard=true
        function findDisabledWildcards(components) {
            components.forEach(component => {
                if (component.disableWildcard === true && component.key) {
                    disabledKeys.add(component.key);
                }
                
                // Process nested components
                if (component.components) {
                    findDisabledWildcards(component.components);
                }
                
                // Process columns
                if (component.columns) {
                    component.columns.forEach(col => {
                        if (col.components) {
                            findDisabledWildcards(col.components);
                        }
                    });
                }
            });
        }
        
        // Start the search from the top-level components
        findDisabledWildcards(schema.components);
        
        // Remove disabled keys from the data
        disabledKeys.forEach(key => {
            delete filteredData[key];
        });
        
        return filteredData;
    }

    // Process survey components to create individual question wildcards
    function processSurveyData(formData) {
        const processedData = {...formData};
        
        // Look for survey components in the data
        Object.keys(formData).forEach(key => {
            const value = formData[key];
            
            // If the value is an object and not an array, it might be a survey component
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                // Get the question values and find out how many questions there are
                const questionKeys = Object.keys(value);
                
                questionKeys.forEach((questionKey, index) => {
                    // Only create keys for questions that have answers (not undefined, null, or empty)
                    if (value[questionKey] !== undefined && value[questionKey] !== null && value[questionKey] !== '') {
                        // Create the traditional combined key for backward compatibility
                        const traditionalKey = `${key}_${questionKey}`;
                        processedData[traditionalKey] = value[questionKey];
                        
                        // Create the new shortened key format with question number
                        // Extract the base question key (without long text)
                        const shortKey = questionKey.substring(0, 15).replace(/[^A-Za-z0-9]/g, '');
                        const numberedKey = `${key}_${shortKey}${index + 1}`;
                        processedData[numberedKey] = value[questionKey];
                    }
                });
            }
        });
        
        return processedData;
    }

    // Decode the template before processing it
    template = decodeHTMLEntities(template);

    // Filter out data from components with disableWildcard=true
    let filteredData = formSchema ? filterDisabledWildcards(data, formSchema) : data;

    // Process survey data to create individual question wildcards
    filteredData = processSurveyData(filteredData);
  
    let processedTemplate = '';
    let currentIndex = 0;
    const customEntryRegex = /\{@START_(\w+)@\}([\s\S]*?)\{@END_\1@\}/g;
    let match;

    while ((match = customEntryRegex.exec(template)) !== null) {
        const componentId = match[1];
        const sectionContent = match[2];
        let componentData = filteredData[componentId] || [];
        
        processedTemplate += template.substring(currentIndex, match.index);
        
        if (Array.isArray(componentData)) {
            
            // HACK: Skip the first row of each datagrid
            if (componentData.length > 0) {
                componentData = componentData.slice(1);
            }
            
            // Find field keys by examining non-empty rows
            const fieldKeys = new Set();
            componentData.forEach(row => {
                if (row && typeof row === 'object' && !Array.isArray(row)) {
                    Object.keys(row).forEach(key => fieldKeys.add(key));
                }
            });
            
            // Process rows (skipping the first one)
            componentData.forEach((rowData, index) => {
                let rowContent = sectionContent;
                let processedRowData = rowData;
                
                // Convert empty arrays to empty objects
                if (Array.isArray(rowData) && rowData.length === 0) {
                    processedRowData = {};
                }
                
                // Handle case where rowData is null or undefined
                if (!processedRowData) processedRowData = {};
                
                rowContent = rowContent.replace(/\{(\w+)\}/g, (placeholder, key) => {
                    const value = processedRowData[key];
                    
                    // If value is undefined but this is a valid field key, treat as empty string
                    if (value === undefined && fieldKeys.has(key)) {
                        return '';
                    }
                    
                    // Return empty string for undefined values, otherwise return the value
                    return value !== undefined ? value : '';
                });
                
                processedTemplate += rowContent;
            });
        } else if (componentId in filteredData) {
            // Handle non-datagrid fields
            let content = sectionContent;
            content = content.replace(/\{(\w+)\}/g, (placeholder, key) => {
                return filteredData[key] !== undefined ? filteredData[key] : '';
            });
            processedTemplate += content;
        }
        
        currentIndex = match.index + match[0].length;
    }

    processedTemplate += template.substring(currentIndex);
    
    // Process regular placeholders outside of START/END blocks
    // Always replace undefined or null values with empty string instead of keeping the placeholder
    processedTemplate = processedTemplate.replace(/\{(\w+)\}/g, (match, key) => {
        return (filteredData[key] !== undefined && filteredData[key] !== null) ? filteredData[key] : '';
    });
    
    return processedTemplate;
}

function trackFormUsage(isSubmission = false) {
    // Extract form ID from URL
    const urlParams = new URLSearchParams(window.location.search);
    const formId = urlParams.get('f');
    
    if (formId) {
        fetch('ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'analytics',
                action: 'track_form',
                formId: formId,
                isSubmission: isSubmission
            })
        }).catch(err => console.warn('Analytics error:', err));
    }
}

/**
* Copy text to clipboard function
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