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

    // Process survey components to create individual question wildcards
    function processSurveyData(formData) {
        const processedData = {...formData};
        
        // Look for survey components in the data
        Object.keys(formData).forEach(key => {
            const value = formData[key];
            
            // If the value is an object and not an array, it might be a survey component
            if (value && typeof value === 'object' && !Array.isArray(value)) {
                // For each question in the survey
                Object.keys(value).forEach(questionKey => {
                    // Create a new key combining survey key and question key
                    const combinedKey = `${key}_${questionKey}`;
                    // Add the selected value for this question to the processed data
                    processedData[combinedKey] = value[questionKey];
                });
            }
        });
        
        return processedData;
    }

    // Decode the template before processing it
    template = decodeHTMLEntities(template);

    // Process survey data to create individual question wildcards
    data = processSurveyData(data);

  // Decode the template before processing it
  template = decodeHTMLEntities(template);
  
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
      } else if (componentId in data) {
          // Handle non-datagrid fields
          let content = sectionContent;
          content = content.replace(/\{(\w+)\}/g, (placeholder, key) => {
              return data[key] !== undefined ? data[key] : '';
          });
          processedTemplate += content;
      }
      
      currentIndex = match.index + match[0].length;
  }

  processedTemplate += template.substring(currentIndex);
  
  // Process regular placeholders outside of START/END blocks
  processedTemplate = processedTemplate.replace(/\{(\w+)\}/g, (match, key) => {
      return data[key] !== undefined ? data[key] : match;
  });
  
  return processedTemplate;
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

Formio.createForm(document.getElementById('formio'), formSchema, { noAlerts: true })
.then(function(form) {
  const outputContainer = document.getElementById('output-container');
  const outputField = document.getElementById('output');
  const copyButton = document.getElementById('copyOutputBtn');
  
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

  form.on('submit', function(submission) {
    // Clone the data to prevent any issues with form reset
    const submissionCopy = JSON.parse(JSON.stringify(submission.data));
    const generatedOutput = processTemplate(formTemplate, submissionCopy);
    outputField.value = generatedOutput;
    form.emit('submitDone');
  });
})
.catch(function(error) {
  console.error('Form initialization error:', error);
  document.getElementById('form-container').innerHTML = `
    <div class="alert alert-danger">
      Error loading form: ${error.message}
    </div>
  `;
});
function setCookie(name, value, daysToLive = 7) {
  const date = new Date();
  date.setTime(date.getTime() + (daysToLive * 24 * 60 * 60 * 1000));
  const expires = "expires=" + date.toUTCString();
  document.cookie = `${name}=${encodeURIComponent(value)};${expires};path=/`;
}

function getCookie(name) {
  const decodedCookie = decodeURIComponent(document.cookie);
  const cookies = decodedCookie.split(';');
  for (let cookie of cookies) {
      cookie = cookie.trim();
      if (cookie.startsWith(name + "=")) {
      return cookie.substring(name.length + 1);
      }
  }
  return null;
}