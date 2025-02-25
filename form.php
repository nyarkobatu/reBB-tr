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
          return template.replace(/\{(\w+)\}/g, (match, key) => {
            const value = data[key];
            if (value === undefined && key.includes('.')) {
              return key.split('.').reduce((obj, k) => (obj || {})[k], data) || match;
            }
            return value !== undefined ? value : match;
          });
        }

        Formio.createForm(document.getElementById('formio'), formSchema, { noAlerts: true })
          .then(function(form) {
            const outputContainer = document.getElementById('output-container');
            const outputField = document.getElementById('output');

            form.on('submit', function(submission) {
              event.preventDefault();
              const generatedOutput = processTemplate(formTemplate, submission.data);
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