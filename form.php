<?php
require_once 'site.php';

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
    $showAlert = false; // Flag to control banner display


    if (isset($_GET['f'])) {
        $formName = preg_replace('/[^a-zA-Z0-9_\-]/', '', $_GET['f']);
        $filename = 'forms/' . $formName . '_schema.json';

        if (file_exists($filename)) {
            $fileContents = file_get_contents($filename);
            $formData = json_decode($fileContents, true);
            $formSchema = $formData['schema'] ?? null;
            $formTemplate = $formData['template'] ?? '';
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
?>
<!DOCTYPE html>
<html>
  <head>
    <title><?php echo $formNameDisplay; ?> - <?php echo SITE_NAME; ?></title>
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
        width: 100%;
        margin: 0;
      }
      #form-container {
        max-width: 800px;
        margin: 40px auto;
        padding: 20px;
        flex-grow: 1;
        width: 100%;
        box-sizing: border-box;
      }
      #output-container {
        max-width: 800px;
        margin: 20px auto;
        width: 100%;
        box-sizing: border-box;
      }
      .alert {
        max-width: 800px;
        margin: 40px auto;
        width: 100%;
        box-sizing: border-box;
      }
      .footer {
        background-color: #e0e0e0;
        padding: 20px 0;
        text-align: center;
        color: #555;
        margin-top: 20px;
        width: 100%;
        box-sizing: border-box;
      }
      .footer a {
          color: #007bff;
          text-decoration: none;
      }
      .footer a:hover {
          text-decoration: underline;
      }
      /* CSS to hide the first row of every datagrid */
      .formio-component-datagrid .datagrid-table tbody tr:first-child {
        display: none !important;
      }
      
      /* Make the "Add Another" button more prominent so users know how to add rows */
      .formio-component-datagrid .datagrid-add {
        background-color: #f0f8ff;
        padding: 5px;
        margin-top: 10px;
      }
    </style>
  </head>
  <body>

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

      <script type='text/javascript'>
        const formSchema = <?= json_encode($formSchema, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?>;
        const formTemplate = <?= json_encode($formTemplate, JSON_UNESCAPED_SLASHES) ?>;

        function processTemplate(template, data) {
            
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

        Formio.createForm(document.getElementById('formio'), formSchema, { noAlerts: true })
          .then(function(form) {
            const outputContainer = document.getElementById('output-container');
            const outputField = document.getElementById('output');
            
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
      </script>
    <?php endif; ?>
    <footer class="footer">
        <p>Made using <a href="<?php echo SITE_URL; ?>"><?php echo SITE_NAME; ?></a> <?php echo SITE_VERSION; ?></br>
        <?php if (isset($_GET['f']) && !empty($_GET['f'])): ?>
            <a href="?f=<?php echo htmlspecialchars($_GET['f']) ?>/json">View form in json</a> • <a href="<?php echo SITE_URL; ?>/builder.php?f=<?php echo htmlspecialchars($_GET['f']) ?>">Use this form as a template</a><br/>
        <?php endif; ?>
        <a href="<?php echo FOOTER_GITHUB; ?>">Github</a></p>
    </footer>
  </body>
</html>