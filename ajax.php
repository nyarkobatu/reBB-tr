<?php
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'error' => 'Invalid request method. Only POST allowed.']);
    exit;
}

$jsonData = file_get_contents('php://input');
$requestData = json_decode($jsonData, true);

if ($requestData === null && json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['success' => false, 'error' => 'Invalid JSON data received.']);
    exit;
}

$requestType = isset($requestData['type']) ? $requestData['type'] : null;

$randomString = bin2hex(random_bytes(16)); // Generate a random string
$formsDir = 'forms';

if (!is_dir($formsDir)) {
    if (!mkdir($formsDir, 0777, true)) { // Create directory with write permissions
        echo json_encode(['success' => false, 'error' => 'Failed to create forms directory.']);
        exit;
    }
}

if ($requestType === 'schema') {
    // Save Form Schema, Template and Name
    $formSchema = isset($requestData['schema']) ? $requestData['schema'] : null;
    $formTemplate = isset($requestData['template']) ? $requestData['template'] : ''; // Retrieve template, default to empty string
    $formName = isset($requestData['formName']) ? $requestData['formName'] : ''; // Retrieve form name

    if ($formSchema === null) {
        echo json_encode(['success' => false, 'error' => 'No form schema data received.']);
        exit;
    }

    $filename = $formsDir . '/' . $randomString . '_schema.json'; // Add _schema to filename
    // Create an array containing schema, template and formName
    $fileContent = json_encode([
        'success' => true, // Add success flag
        'filename' => $filename, // Include filename in response for potential use in Javascript
        'formName' => $formName, // Include form name
        'schema' => $formSchema,
        'template' => $formTemplate,
    ], JSON_PRETTY_PRINT);

    // Here you would typically save the $fileContent to the file $filename.
    // Assuming you intend to save the schema and template to a file:
    if (!file_put_contents($filename, $fileContent)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save form schema to file.']);
        exit;
    }


    echo $fileContent; // **Crucially add this line to send the JSON response**
    exit; // Important to exit after sending the response
}
?>