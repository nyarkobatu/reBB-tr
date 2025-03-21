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
        
        // Format date fields according to their component configuration
        formatDateFields(submissionCopy, form.form.components);
        
        // Process the template with our updated data
        const generatedOutput = processTemplate(formTemplate, submissionCopy);
        outputField.value = generatedOutput;
        
        // Process template title if it's enabled and exists
        if (typeof enableTemplateTitle !== 'undefined' && enableTemplateTitle && 
            typeof formTemplateTitle !== 'undefined' && formTemplateTitle) {
            const generatedTitle = processTemplate(formTemplateTitle, submissionCopy);
            generatedTitleField.value = generatedTitle;
            templateTitleContainer.style.display = 'block';
        }
        
        trackFormUsage(true);
        form.emit('submitDone');
    });

    /**
     * Format date fields according to their component configuration
     * @param {Object} data - The form submission data
     * @param {Array} components - The form components configuration
     */
    function formatDateFields(data, components) {
        // Recursively process all components
        function processComponents(components) {
            if (!components || !Array.isArray(components)) return;
            
            components.forEach(component => {
                // Process date/time components
                if (component.type === 'datetime' && component.key && data[component.key]) {
                    // Get the format from the component config
                    const format = component.format || 'MM/dd/yyyy';
                    
                    try {
                        // Parse the ISO date string
                        const dateValue = data[component.key];
                        const date = new Date(dateValue);
                        
                        if (!isNaN(date.getTime())) {
                            // Format the date according to the component's format
                            data[component.key] = formatDate(date, format);
                        }
                    } catch (e) {
                        console.warn(`Error formatting date for ${component.key}:`, e);
                    }
                }
                
                // Recursively process nested components
                if (component.components) {
                    processComponents(component.components);
                }
                
                // Process columns
                if (component.columns) {
                    component.columns.forEach(column => {
                        if (column.components) {
                            processComponents(column.components);
                        }
                    });
                }
                
                // Process rows
                if (component.rows) {
                    component.rows.forEach(row => {
                        if (Array.isArray(row)) {
                            row.forEach(cell => {
                                if (cell && cell.components) {
                                    processComponents(cell.components);
                                }
                            });
                        }
                    });
                }
            });
        }
        
        // Process all components in the form
        processComponents(components);
        
        // Also look for date fields in datagrids
        Object.keys(data).forEach(key => {
            if (Array.isArray(data[key])) {
                data[key].forEach(row => {
                    if (row && typeof row === 'object') {
                        // Find date components in this datagrid
                        const dateFields = findDateFieldsInDatagrid(key, components);
                        
                        // Format each date field in the row
                        dateFields.forEach(dateField => {
                            if (row[dateField.key] && typeof row[dateField.key] === 'string') {
                                try {
                                    const date = new Date(row[dateField.key]);
                                    if (!isNaN(date.getTime())) {
                                        row[dateField.key] = formatDate(date, dateField.format || 'MM/dd/yyyy');
                                    }
                                } catch (e) {
                                    console.warn(`Error formatting date in datagrid row:`, e);
                                }
                            }
                        });
                    }
                });
            }
        });
    }

    /**
     * Find date field components within a datagrid
     * @param {string} datagridKey - The key of the datagrid component
     * @param {Array} components - All form components
     * @returns {Array} Array of date field configurations
     */
    function findDateFieldsInDatagrid(datagridKey, components) {
        const dateFields = [];
        
        function findDatagrid(components) {
            if (!components || !Array.isArray(components)) return null;
            
            for (const component of components) {
                if (component.type === 'datagrid' && component.key === datagridKey) {
                    return component;
                }
                
                // Check nested components
                if (component.components) {
                    const found = findDatagrid(component.components);
                    if (found) return found;
                }
                
                // Check columns
                if (component.columns) {
                    for (const column of component.columns) {
                        if (column.components) {
                            const found = findDatagrid(column.components);
                            if (found) return found;
                        }
                    }
                }
            }
            
            return null;
        }
        
        const datagrid = findDatagrid(components);
        
        if (datagrid && datagrid.components) {
            // Find all datetime components in the datagrid
            function collectDateFields(components) {
                if (!components || !Array.isArray(components)) return;
                
                components.forEach(component => {
                    if (component.type === 'datetime') {
                        dateFields.push({
                            key: component.key,
                            format: component.format || 'MM/dd/yyyy'
                        });
                    }
                    
                    // Check nested components
                    if (component.components) {
                        collectDateFields(component.components);
                    }
                    
                    // Check columns
                    if (component.columns) {
                        component.columns.forEach(column => {
                            if (column.components) {
                                collectDateFields(column.components);
                            }
                        });
                    }
                });
            }
            
            collectDateFields(datagrid.components);
        }
        
        return dateFields;
    }

    /**
     * Format a date according to the specified format string
     * @param {Date} date - The date to format
     * @param {string} format - The format string (e.g., 'MM/dd/yyyy')
     * @returns {string} The formatted date string
     */
    function formatDate(date, format) {
        // Month names for 'MMMM' format
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];
        
        // Short month names for 'MMM' format
        const shortMonthNames = [
            'JAN', 'FEB', 'MAR', 'APR', 'MAY', 'JUN',
            'JUL', 'AUG', 'SEP', 'OCT', 'NOV', 'DEC'
        ];
        
        // Day names for 'EEEE' format
        const dayNames = [
            'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'
        ];
        
        // Short day names for 'EEE' format
        const shortDayNames = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];
        
        // Get date components
        const year = date.getFullYear();
        const month = date.getMonth();
        const day = date.getDate();
        const hours = date.getHours();
        const minutes = date.getMinutes();
        const seconds = date.getSeconds();
        const dayOfWeek = date.getDay();
        
        // Handle common format patterns
        return format
            // Year formats
            .replace(/yyyy/g, year)
            .replace(/yy/g, (year % 100).toString().padStart(2, '0'))
            
            // Month formats
            .replace(/MMMM/g, monthNames[month])
            .replace(/MMM/g, shortMonthNames[month])
            .replace(/MM/g, (month + 1).toString().padStart(2, '0'))
            .replace(/M/g, month + 1)
            
            // Day formats
            .replace(/dd/g, day.toString().padStart(2, '0'))
            .replace(/d/g, day)
            
            // Day of week formats
            .replace(/EEEE/g, dayNames[dayOfWeek])
            .replace(/EEE/g, shortDayNames[dayOfWeek])
            
            // Hour formats (12-hour)
            .replace(/hh/g, (hours % 12 || 12).toString().padStart(2, '0'))
            .replace(/h/g, hours % 12 || 12)
            
            // Hour formats (24-hour)
            .replace(/HH/g, hours.toString().padStart(2, '0'))
            .replace(/H/g, hours)
            
            // Minute formats
            .replace(/mm/g, minutes.toString().padStart(2, '0'))
            .replace(/m/g, minutes)
            
            // Second formats
            .replace(/ss/g, seconds.toString().padStart(2, '0'))
            .replace(/s/g, seconds)
            
            // AM/PM
            .replace(/a/g, hours < 12 ? 'am' : 'pm')
            .replace(/A/g, hours < 12 ? 'AM' : 'PM');
    }
}

// Process template with data
function processTemplate(template, data) {
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

    // Process all form data to expand options for select boxes and other components
    function processFormData(formData) {
        const processedData = {...formData};
        
        // Process all values in the data object
        Object.keys(formData).forEach(key => {
            const value = formData[key];
            
            // Handle selectboxes component - {"option1": true, "option2": false}
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                const keys = Object.keys(value);
                
                // Check if this is a selectboxes component (has boolean values)
                const allBooleanValues = keys.length > 0 && 
                    keys.every(optionKey => typeof value[optionKey] === 'boolean');
                
                if (allBooleanValues) {
                    // 1. Create individual wildcards for each option
                    keys.forEach(optionKey => {
                        const wildcard = `${key}_${optionKey}`;
                        // If the option is selected (true), set the wildcard to the option key
                        // Otherwise, set it to empty string
                        processedData[wildcard] = value[optionKey] ? optionKey : '';
                    });
                    
                    // 2. Set the main component value to a space-separated list of selected options
                    processedData[key] = keys
                        .filter(optionKey => value[optionKey])
                        .join(' ');
                }
                // Handle single select with value/label properties
                else if (value.value !== undefined) {
                    processedData[key] = value.value;
                }
            }
            // Handle arrays (multi-select components)
            else if (Array.isArray(value)) {
                processedData[key] = value
                    .map(item => typeof item === 'object' && item.value ? item.value : item)
                    .join(' ');
            }
        });
        
        return processedData;
    }

    // Process survey components to create individual question wildcards
    function processSurveyData(formData) {
        const processedData = {...formData};
        
        // Look for survey components in the data
        Object.keys(formData).forEach(key => {
            const value = formData[key];
            
            // If the value is an object and not an array, it might be a survey component
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                // Skip objects that we've already determined are selectboxes
                const keys = Object.keys(value);
                const allBooleanValues = keys.length > 0 && 
                    keys.every(optionKey => typeof value[optionKey] === 'boolean');
                
                // Only process if it's not a selectboxes component
                if (!allBooleanValues) {
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
            }
        });
        
        return processedData;
    }

    // Decode the template before processing it
    template = decodeHTMLEntities(template);

    // Process input data
    data = processFormData(data);
    data = processSurveyData(data);
  
    let processedTemplate = '';
    let currentIndex = 0;
    const customEntryRegex = /\{@START_(\w+)@\}([\s\S]*?)\{@END_\1@\}/g;
    let match;

    while ((match = customEntryRegex.exec(template)) !== null) {
        const componentId = match[1];
        const sectionContent = match[2];
        let componentData = data[componentId] || [];
        
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
                
                // Process row data to expand select boxes
                const expandedRowData = {};
                Object.keys(processedRowData).forEach(key => {
                    const value = processedRowData[key];
                    
                    // Process select boxes in the same way as we did for the main data
                    if (value && typeof value === 'object' && !Array.isArray(value)) {
                        const keys = Object.keys(value);
                        const allBooleanValues = keys.length > 0 && 
                            keys.every(optionKey => typeof value[optionKey] === 'boolean');
                        
                        if (allBooleanValues) {
                            // Create individual option wildcards
                            keys.forEach(optionKey => {
                                expandedRowData[`${key}_${optionKey}`] = value[optionKey] ? optionKey : '';
                            });
                            
                            // Set main value to space-separated list
                            expandedRowData[key] = keys
                                .filter(optionKey => value[optionKey])
                                .join(' ');
                        } else {
                            expandedRowData[key] = value.value || value;
                        }
                    } else {
                        expandedRowData[key] = value;
                    }
                });
                
                rowContent = rowContent.replace(/\{(\w+)\}/g, (placeholder, key) => {
                    // Try the expanded data first
                    if (key in expandedRowData) {
                        return expandedRowData[key];
                    }
                    
                    // Then try the original row data
                    if (key in processedRowData) {
                        return processedRowData[key];
                    }
                    
                    // If value is undefined but this is a valid field key, treat as empty string
                    if (fieldKeys.has(key)) {
                        return '';
                    }
                    
                    // For any other placeholder, return empty string
                    return '';
                });
                
                processedTemplate += rowContent;
            });
        } else if (componentId in data) {
            // Handle non-datagrid fields
            let content = sectionContent;
            content = content.replace(/\{(\w+)\}/g, (placeholder, key) => {
                return key in data ? data[key] : '';
            });
            processedTemplate += content;
        }
        
        currentIndex = match.index + match[0].length;
    }

    processedTemplate += template.substring(currentIndex);
    
    // Process regular placeholders outside of START/END blocks
    // Always replace undefined or null values with empty string instead of keeping the placeholder
    processedTemplate = processedTemplate.replace(/\{(\w+)\}/g, (match, key) => {
        return (data[key] !== undefined && data[key] !== null) ? data[key] : '';
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