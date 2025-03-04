<?php
require_once 'site.php';

// Start session for authentication
session_start();

// Configuration
$config = [
    'docs_dir' => __DIR__ . '/documentation',
    'log_file' => 'logs/documentation_activity.log',
    'allowed_extensions' => ['md'],
    'default_doc' => 'getting-started.md',
    'session_timeout' => 1800 // 30 minutes
];

// Create documentation directory if it doesn't exist
if (!is_dir($config['docs_dir'])) {
    @mkdir($config['docs_dir'], 0755, true);
}

// Create logs directory if it doesn't exist
$logsDir = dirname($config['log_file']);
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}

// Check if user is logged in as admin
function isAdminLoggedIn() {
    return isset($_SESSION['admin_username']) && !empty($_SESSION['admin_username']);
}

// Check if admin session has timed out
if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > $config['session_timeout'])) {
    // Last activity was more than timeout period ago
    session_unset();
    session_destroy();
    $loginError = 'Your session has expired. Please log in again.';
}

// Update admin last activity time if logged in
if (isAdminLoggedIn()) {
    $_SESSION['admin_last_activity'] = time();
}

// Log documentation actions
function logDocAction($action, $success = true) {
    global $config;
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user = $_SESSION['admin_username'] ?? 'Unauthenticated';
    $status = $success ? 'SUCCESS' : 'FAILED';
    
    $logEntry = "[$timestamp] [$status] [IP: $ip] [User: $user] $action" . PHP_EOL;
    file_put_contents($config['log_file'], $logEntry, FILE_APPEND);
}

// Get a list of all documentation files
function getDocumentationFiles() {
    global $config;
    
    $files = [];
    
    if (is_dir($config['docs_dir'])) {
        $dirContent = scandir($config['docs_dir']);
        
        foreach ($dirContent as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }
            
            $filePath = $config['docs_dir'] . '/' . $file;
            $extension = pathinfo($file, PATHINFO_EXTENSION);
            
            if (is_file($filePath) && in_array($extension, $config['allowed_extensions'])) {
                $files[] = [
                    'filename' => $file,
                    'title' => getDocumentTitle($filePath),
                    'order' => getDocumentOrder($filePath),
                    'modified' => filemtime($filePath),
                    'size' => filesize($filePath)
                ];
            }
        }
        
        // Sort files by order value (lowest first)
        usort($files, function($a, $b) {
            // Force PHP to compare as integers, not strings
            $orderA = (int)$a['order']; 
            $orderB = (int)$b['order'];
            
            if ($orderA !== $orderB) {
                return $orderA - $orderB; // Lower order numbers first
            }
            
            // If order is the same, sort by title alphabetically
            return strcmp($a['title'], $b['title']);
        });
    }
    
    return $files;
}

// Extract order number from document content
function getDocumentOrder($filePath) {
    if (!file_exists($filePath)) {
        return 999; // Default high number for new files
    }
    
    $content = file_get_contents($filePath);
    
    // Look for [ORDER: X] where X is a number
    if (preg_match('/\[ORDER:\s*(\d+)\]/i', $content, $matches)) {
        return (int)$matches[1];
    }
    
    return 999; // Default high number if no order found
}

// Extract a title from the markdown file (first heading)
function getDocumentTitle($filePath) {
    if (!file_exists($filePath)) {
        return pathinfo($filePath, PATHINFO_FILENAME);
    }
    
    $content = file_get_contents($filePath);
    
    // Look for the first heading (# Title)
    if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
        return $matches[1];
    }
    
    // If no heading found, use the filename without extension
    return pathinfo($filePath, PATHINFO_FILENAME);
}

// This will remove [ORDER: X] tags from the rendered HTML
function markdownToHtml($markdown) {
    // Remove order tags before processing
    $markdown = preg_replace('/\[ORDER:\s*\d+\]/i', '', $markdown);
    
    // Convert headers
    $html = preg_replace('/^######\s+(.+)$/m', '<h6>$1</h6>', $markdown);
    $html = preg_replace('/^#####\s+(.+)$/m', '<h5>$1</h5>', $html);
    $html = preg_replace('/^####\s+(.+)$/m', '<h4>$1</h4>', $html);
    $html = preg_replace('/^###\s+(.+)$/m', '<h3>$1</h3>', $html);
    $html = preg_replace('/^##\s+(.+)$/m', '<h2>$1</h2>', $html);
    $html = preg_replace('/^#\s+(.+)$/m', '<h1>$1</h1>', $html);
    
    // Convert blockquotes
    $html = preg_replace('/^>\s+(.+)$/m', '<blockquote>$1</blockquote>', $html);
    
    // Convert horizontal rules
    $html = preg_replace('/^([\-*_])\1{2,}$/m', '<hr>', $html);
    
    // Convert bold text
    $html = preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $html);
    $html = preg_replace('/\_\_(.+?)\_\_/s', '<strong>$1</strong>', $html);
    
    // Convert italic text
    $html = preg_replace('/\*(.+?)\*/s', '<em>$1</em>', $html);
    $html = preg_replace('/\_(.+?)\_/s', '<em>$1</em>', $html);
    
    // Convert inline code
    $html = preg_replace('/`(.+?)`/s', '<code>$1</code>', $html);
    
    // Convert code blocks
    $html = preg_replace('/```(.*?)\n(.*?)```/s', '<pre><code class="language-$1">$2</code></pre>', $html);
    
    // Convert lists (unordered)
    $html = preg_replace_callback('/(?:^|\n)(?:[ ]*?)([\*\-\+][ ]+.+?)(?:\n(?![\*\-\+][ ]+)|\z)/s', function($matches) {
        $items = preg_split('/\n[ ]*?[\*\-\+][ ]+/', "\n".$matches[1]);
        array_shift($items); // Remove first empty item
        return '<ul><li>' . implode('</li><li>', $items) . '</li></ul>';
    }, $html);
    
    // Convert lists (ordered)
    $html = preg_replace_callback('/(?:^|\n)(?:[ ]*?)(\d+\.[ ]+.+?)(?:\n(?!\d+\.[ ]+)|\z)/s', function($matches) {
        $items = preg_split('/\n[ ]*?\d+\.[ ]+/', "\n".$matches[1]);
        array_shift($items); // Remove first empty item
        return '<ol><li>' . implode('</li><li>', $items) . '</li></ol>';
    }, $html);
    
    // Convert links
    $html = preg_replace('/\[(.+?)\]\((.+?)\)/', '<a href="$2">$1</a>', $html);
    
    // Convert images
    $html = preg_replace('/!\[(.+?)\]\((.+?)\)/', '<img src="$2" alt="$1">', $html);
    
    // Convert line breaks to paragraphs
    $html = '<p>' . str_replace(["\r\n\r\n", "\n\n"], '</p><p>', $html) . '</p>';
    
    // Clean up empty paragraphs
    $html = str_replace(['<p></p>', '<p><h', '</h1></p>', '</h2></p>', '</h3></p>', '</h4></p>', '</h5></p>', '</h6></p>'], ['', '<h', '</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>'], $html);
    $html = str_replace(['<p><ul>', '</ul></p>', '<p><ol>', '</ol></p>', '<p><blockquote>', '</blockquote></p>', '<p><hr></p>'], ['<ul>', '</ul>', '<ol>', '</ol>', '<blockquote>', '</blockquote>', '<hr>'], $html);
    
    return $html;
}

// Sanitize filenames to prevent directory traversal attacks
function sanitizeFilename($filename) {
    // Remove any path information
    $filename = basename($filename);
    
    // Remove any characters not allowed in filenames
    $filename = preg_replace('/[^a-zA-Z0-9\-_\.]/', '', $filename);
    
    // Make sure the file has an md extension
    if (pathinfo($filename, PATHINFO_EXTENSION) !== 'md') {
        $filename .= '.md';
    }
    
    return $filename;
}

// Initialize variables
$isViewMode = true;
$activeDoc = '';
$docContent = '';
$title = '';
$actionMessage = '';
$actionMessageType = 'info';
$documentFiles = getDocumentationFiles();

// Explicitly set edit mode if requested, regardless of login status
// We'll check login status when rendering the form
if (isset($_GET['edit'])) {
    $isViewMode = false;
    
    if ($_GET['edit'] === 'new') {
        // New document - empty fields
        $title = '';
        $docContent = '';
    } else if (!empty($_GET['edit'])) {
        // Edit existing document
        $filename = sanitizeFilename($_GET['edit']);
        $filePath = $config['docs_dir'] . '/' . $filename;
        
        if (file_exists($filePath)) {
            $docContent = file_get_contents($filePath);
            $title = getDocumentTitle($filePath);
            
            // Remove the title from the content (first line with # heading)
            $docContent = preg_replace('/^#\s+.+\n+/m', '', $docContent);
            $docContent = trim($docContent);
        }
    }
}

// ADMIN FUNCTIONALITY (CREATE/EDIT/DELETE)
if (isAdminLoggedIn()) {
    // Create new documentation
    if (isset($_POST['create_doc']) && isset($_POST['doc_title']) && isset($_POST['doc_content'])) {
        $title = trim($_POST['doc_title']);
        $content = $_POST['doc_content'];
        $order = isset($_POST['doc_order']) ? (int)$_POST['doc_order'] : 999;
        
        if (empty($title)) {
            $actionMessage = "Error: Document title cannot be empty.";
            $actionMessageType = 'danger';
        } else {
            // Generate a filename from the title
            $filename = strtolower(str_replace(' ', '-', $title));
            $filename = sanitizeFilename($filename);
            
            $filePath = $config['docs_dir'] . '/' . $filename;
            
            // Add document title as first line heading and add order tag
            $fullContent = "# {$title}\n\n[ORDER: {$order}]\n\n{$content}";
            
            if (file_put_contents($filePath, $fullContent)) {
                $actionMessage = "Document '{$title}' created successfully.";
                $actionMessageType = 'success';
                logDocAction("Created document: {$filename}");
                // Refresh file list
                $documentFiles = getDocumentationFiles();
            } else {
                $actionMessage = "Error: Failed to create document.";
                $actionMessageType = 'danger';
                logDocAction("Failed to create document: {$filename}", false);
            }
        }
    }
    
    // Update existing documentation
    if (isset($_POST['update_doc']) && isset($_POST['doc_filename']) && isset($_POST['doc_title']) && isset($_POST['doc_content'])) {
        $filename = sanitizeFilename($_POST['doc_filename']);
        $title = trim($_POST['doc_title']);
        $content = $_POST['doc_content'];
        
        if (empty($title)) {
            $actionMessage = "Error: Document title cannot be empty.";
            $actionMessageType = 'danger';
        } else {
            $filePath = $config['docs_dir'] . '/' . $filename;
            
            if (file_exists($filePath)) {
                // Preserve any existing [ORDER: X] tag or order value
                $orderTag = '';
                if (preg_match('/\[ORDER:\s*(\d+)\]/i', $content, $matches)) {
                    // Order tag is already in the content, leave it as is
                } else {
                    // No order tag in content, check if we have an order from the original file
                    $originalOrder = getDocumentOrder($filePath);
                    if ($originalOrder < 999) {
                        // Only add the tag if we had a real order value
                        $orderTag = "[ORDER: {$originalOrder}]\n\n";
                    }
                }
                
                // Add document title as first line heading
                $fullContent = "# {$title}\n\n{$orderTag}{$content}";
                
                if (file_put_contents($filePath, $fullContent)) {
                    $actionMessage = "Document '{$title}' updated successfully.";
                    $actionMessageType = 'success';
                    logDocAction("Updated document: {$filename}");
                    // Refresh file list
                    $documentFiles = getDocumentationFiles();
                } else {
                    $actionMessage = "Error: Failed to update document.";
                    $actionMessageType = 'danger';
                    logDocAction("Failed to update document: {$filename}", false);
                }
            } else {
                $actionMessage = "Error: Document not found.";
                $actionMessageType = 'danger';
            }
        }
    }
    
    // Delete documentation
    if (isset($_POST['delete_doc']) && isset($_POST['doc_filename'])) {
        $filename = sanitizeFilename($_POST['doc_filename']);
        $filePath = $config['docs_dir'] . '/' . $filename;
        
        if (file_exists($filePath) && unlink($filePath)) {
            $actionMessage = "Document '{$filename}' deleted successfully.";
            $actionMessageType = 'success';
            logDocAction("Deleted document: {$filename}");
            // Refresh file list
            $documentFiles = getDocumentationFiles();
        } else {
            $actionMessage = "Error: Failed to delete document.";
            $actionMessageType = 'danger';
            logDocAction("Failed to delete document: {$filename}", false);
        }
    }
    
    // Handle editing mode
    if (isset($_GET['edit']) && !empty($_GET['edit'])) {
        $filename = sanitizeFilename($_GET['edit']);
        $filePath = $config['docs_dir'] . '/' . $filename;
        
        if (file_exists($filePath)) {
            $isViewMode = false;
            $docContent = file_get_contents($filePath);
            $title = getDocumentTitle($filePath);
            
            // Remove the title from the content (first line with # heading)
            $docContent = preg_replace('/^#\s+.+\n+/m', '', $docContent);
            $docContent = trim($docContent);
        }
    }
}

// VIEWER FUNCTIONALITY
// Determine which document to display
if (isset($_GET['doc']) && !empty($_GET['doc'])) {
    $activeDoc = sanitizeFilename($_GET['doc']);
} elseif (!empty($documentFiles)) {
    // Use the first document if available
    $activeDoc = $documentFiles[0]['filename'];
} elseif (file_exists($config['docs_dir'] . '/' . $config['default_doc'])) {
    // Use the default document if it exists
    $activeDoc = $config['default_doc'];
}

// Load the active document content for viewing
if ($isViewMode && !empty($activeDoc)) {
    $docPath = $config['docs_dir'] . '/' . $activeDoc;
    if (file_exists($docPath)) {
        $markdownContent = file_get_contents($docPath);
        $docContent = markdownToHtml($markdownContent);
    }
}

// Create a sample "Getting Started" doc if no documents exist
if (empty($documentFiles) && is_dir($config['docs_dir'])) {
    $defaultDocPath = $config['docs_dir'] . '/' . $config['default_doc'];
    
    if (!file_exists($defaultDocPath)) {
        $defaultContent = <<<EOT
# Getting Started with reBB

Welcome to the reBB documentation! This guide will help you get started with using the reBB form system.

## What is reBB?

reBB is a PHP implementation that allows you to create customizable forms that convert input into BBCode or HTML output. It's perfect for:

- Creating forum post templates
- Generating structured content for websites
- Building easy-to-use forms for non-technical users

## Quick Start

1. **Creating a Form**: Click the "Create a form" button on the homepage
2. **Building Your Form**: Use the drag-and-drop interface to add fields
3. **Template Setup**: Create a template using wildcards like `{field_name}`
4. **Saving & Sharing**: Save your form and share the link with others

## Form Components

You can add various components to your form:

- Text fields and areas
- Checkboxes and radio buttons
- Select dropdowns
- Date/time pickers
- And more!

## Need Help?

Check out our documentation for more detailed guides, or contact the administrator if you have any questions.
EOT;

        if (file_put_contents($defaultDocPath, $defaultContent)) {
            // Refresh file list
            $documentFiles = getDocumentationFiles();
            
            if (empty($activeDoc)) {
                $activeDoc = $config['default_doc'];
                $markdownContent = $defaultContent;
                $docContent = markdownToHtml($markdownContent);
            }
            
            logDocAction("Created default 'Getting Started' document");
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Documentation - <?php echo SITE_NAME; ?></title>
    <link rel="apple-touch-icon" sizes="180x180" href="/resources/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/resources/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/resources/favicon-16x16.png">
    <link rel="manifest" href="/resources/site.webmanifest">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap/dist/css/bootstrap.min.css">
    <style>
        body {
            display: flex;
            flex-direction: column;
            min-height: 100vh;
            margin: 0;
        }
        
        .container {
            flex: 1;
            display: flex;
            flex-direction: column;
            padding-top: 2rem;
            padding-bottom: 2rem;
        }
        
        .doc-container {
            display: flex;
            flex: 1;
        }
        
        .sidebar {
            width: 250px;
            padding-right: 1rem;
            border-right: 1px solid #dee2e6;
            flex-shrink: 0;
        }
        
        .doc-content {
            flex: 1;
            padding-left: 2rem;
            overflow-y: auto;
        }
        
        .doc-list {
            list-style: none;
            padding: 0;
        }
        
        .doc-list-item {
            margin-bottom: 0.5rem;
        }
        
        .doc-list-item a {
            display: block;
            padding: 0.5rem;
            border-radius: 4px;
            text-decoration: none;
            transition: background-color 0.2s;
        }
        
        .doc-list-item a:hover {
            background-color: #f8f9fa;
        }
        
        .doc-list-item a.active {
            background-color: #e9ecef;
            font-weight: bold;
        }
        
        .doc-actions {
            margin-top: 1rem;
            padding-top: 1rem;
            border-top: 1px solid #dee2e6;
        }
        
        .footer {
            background-color: #e0e0e0;
            padding: 20px 0;
            text-align: center;
            color: #555;
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
        
        .markdown-content img {
            max-width: 100%;
            height: auto;
        }
        
        .markdown-content code {
            padding: 0.2rem 0.4rem;
            background-color: #f8f9fa;
            border-radius: 3px;
        }
        
        .markdown-content pre {
            padding: 1rem;
            background-color: #f8f9fa;
            border-radius: 5px;
            overflow-x: auto;
        }
        
        .markdown-content blockquote {
            padding: 0.5rem 1rem;
            border-left: 4px solid #dee2e6;
            background-color: #f8f9fa;
        }
        
        @media (max-width: 767.98px) {
            .doc-container {
                flex-direction: column;
            }
            
            .sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #dee2e6;
                padding-bottom: 1rem;
                margin-bottom: 1rem;
            }
            
            .doc-content {
                padding-left: 0;
            }
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
        
        body.dark-mode .doc-list-item a:hover {
            background-color: #2d2d2d;
        }
        
        body.dark-mode .doc-list-item a.active {
            background-color: #2d2d2d;
        }
        
        body.dark-mode .sidebar {
            border-color: #444;
        }
        
        body.dark-mode .doc-actions {
            border-color: #444;
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
        
        body.dark-mode .alert {
            background-color: #1e1e1e;
            border-color: #444;
        }
        
        body.dark-mode .alert-success {
            color: #8fd19e;
            border-color: #28a745;
        }
        
        body.dark-mode .alert-danger {
            color: #ea868f;
            border-color: #dc3545;
        }
        
        body.dark-mode .alert-info {
            color: #a2cbef;
            border-color: #17a2b8;
        }
        
        body.dark-mode .markdown-content code {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }
        
        body.dark-mode .markdown-content pre {
            background-color: #2d2d2d;
            color: #e0e0e0;
        }
        
        body.dark-mode .markdown-content blockquote {
            border-color: #444;
            background-color: #2d2d2d;
        }
        
        /* Footer */
        body.dark-mode .footer {
            background-color: #1e1e1e;
            color: #aaa;
        }
        
        body.dark-mode .footer a {
            color: #4da3ff;
        }
        
        /* Card styling */
        body.dark-mode .card {
            background-color: #1e1e1e;
            border-color: #444;
        }
        
        body.dark-mode .card-header {
            background-color: #2d2d2d;
            border-color: #444;
        }
    </style>
</head>
<body>
    <div class="container">
        <header class="mb-4">
            <div class="d-flex justify-content-between align-items-center">
                <h1>
                    <a href="index.php" class="text-decoration-none">
                        <i class="bi bi-arrow-left-circle"></i>
                    </a> 
                    <?php echo SITE_NAME; ?> Documentation
                </h1>
                <?php if (isAdminLoggedIn() && $isViewMode): ?>
                    <a href="?<?php echo !empty($activeDoc) ? 'edit=' . urlencode($activeDoc) : ''; ?>" class="btn btn-primary">
                        <i class="bi bi-pencil"></i> Edit Documentation
                    </a>
                <?php elseif (isAdminLoggedIn() && !$isViewMode): ?>
                    <a href="?<?php echo !empty($activeDoc) ? 'doc=' . urlencode($activeDoc) : ''; ?>" class="btn btn-secondary">
                        <i class="bi bi-eye"></i> View Documentation
                    </a>
                <?php endif; ?>
            </div>
        </header>
        
        <?php if (!empty($actionMessage)): ?>
            <div class="alert alert-<?php echo $actionMessageType; ?> alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($actionMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($isViewMode): ?>
            <!-- DOCUMENTATION VIEWER MODE -->
            <div class="doc-container">
                <div class="sidebar">
                    <h4>Contents</h4>
                    
                    <?php if (empty($documentFiles)): ?>
                        <p class="text-muted">No documentation available.</p>
                    <?php else: ?>
                        <ul class="doc-list">
                            <?php foreach ($documentFiles as $docFile): ?>
                                <li class="doc-list-item">
                                    <a href="?doc=<?php echo urlencode($docFile['filename']); ?>" 
                                       class="<?php echo ($activeDoc === $docFile['filename']) ? 'active' : ''; ?>">
                                        <?php echo htmlspecialchars($docFile['title']); ?>
                                    </a>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>
                    
                    <?php if (isAdminLoggedIn()): ?>
                        <div class="doc-actions">
                            <a href="?edit=new" class="btn btn-success btn-sm w-100 mb-2">
                                <i class="bi bi-plus-circle"></i> Create New Document
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="doc-content">
                    <?php if (empty($docContent)): ?>
                        <div class="alert alert-info">
                            <?php if (empty($documentFiles)): ?>
                                No documentation available. 
                                <?php if (isAdminLoggedIn()): ?>
                                    <a href="?edit=new">Create your first document</a>.
                                <?php endif; ?>
                            <?php else: ?>
                                Select a document from the sidebar to view its content.
                            <?php endif; ?>
                        </div>
                    <?php else: ?>
                        <div class="markdown-content">
                            <?php echo $docContent; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <!-- DOCUMENTATION EDITOR MODE (Admin only) -->
            <?php if (isAdminLoggedIn()): ?>
                <div class="card">
                    <div class="card-header">
                        <h3><?php echo isset($_GET['edit']) && $_GET['edit'] !== 'new' ? 'Edit Document' : 'Create New Document'; ?></h3>
                    </div>
                    <div class="card-body">
                        <form method="post" action="documentation.php">
                            <?php if (isset($_GET['edit']) && $_GET['edit'] !== 'new'): ?>
                                <input type="hidden" name="doc_filename" value="<?php echo htmlspecialchars($_GET['edit']); ?>">
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="doc_title" class="form-label">Document Title</label>
                                <input type="text" class="form-control" id="doc_title" name="doc_title" 
                                       value="<?php echo isset($title) ? htmlspecialchars($title) : ''; ?>" required>
                                <div class="form-text">This will be displayed in the documentation sidebar.</div>
                            </div>
                            
                            <?php if (!isset($_GET['edit']) || $_GET['edit'] === 'new'): ?>
                            <!-- Only show order field for new documents -->
                            <div class="mb-3">
                                <label for="doc_order" class="form-label">Display Order</label>
                                <input type="number" class="form-control" id="doc_order" name="doc_order" 
                                       value="<?php echo isset($docOrder) ? $docOrder : 10; ?>" min="1" max="999">
                                <div class="form-text">Lower numbers appear at the top. Add [ORDER: X] tag in your document to change order later.</div>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <strong>Note:</strong> To change document order, add or edit the [ORDER: X] tag in your document content (where X is a number).
                            </div>
                            <?php endif; ?>
                            
                            <div class="mb-3">
                                <label for="doc_content" class="form-label">Content (Markdown)</label>
                                <textarea class="form-control" id="doc_content" name="doc_content" rows="15" required><?php echo isset($docContent) ? htmlspecialchars($docContent) : ''; ?></textarea>
                                <div class="form-text">
                                    Use Markdown syntax for formatting. 
                                    <a href="#" data-bs-toggle="modal" data-bs-target="#markdownHelpModal">Markdown Help</a>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-between">
                                <a href="?<?php echo !empty($activeDoc) ? 'doc=' . urlencode($activeDoc) : ''; ?>" class="btn btn-secondary">
                                    <i class="bi bi-x-circle"></i> Cancel
                                </a>
                                
                                <div>
                                    <?php if (isset($_GET['edit']) && $_GET['edit'] !== 'new'): ?>
                                        <button type="button" class="btn btn-danger me-2" data-bs-toggle="modal" data-bs-target="#deleteDocModal">
                                            <i class="bi bi-trash"></i> Delete
                                        </button>
                                        <button type="submit" name="update_doc" class="btn btn-primary">
                                            <i class="bi bi-save"></i> Update Document
                                        </button>
                                    <?php else: ?>
                                        <button type="submit" name="create_doc" class="btn btn-success">
                                            <i class="bi bi-plus-circle"></i> Create Document
                                        </button>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
                
                <!-- Delete Confirmation Modal -->
                <div class="modal fade" id="deleteDocModal" tabindex="-1" aria-labelledby="deleteDocModalLabel" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="deleteDocModalLabel">Confirm Deletion</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                Are you sure you want to delete this document? This action cannot be undone.
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                <form method="post" action="documentation.php">
                                    <input type="hidden" name="doc_filename" value="<?php echo htmlspecialchars($_GET['edit']); ?>">
                                    <button type="submit" name="delete_doc" class="btn btn-danger">Delete Document</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Markdown Help Modal -->
                <div class="modal fade" id="markdownHelpModal" tabindex="-1" aria-labelledby="markdownHelpModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title" id="markdownHelpModalLabel">Markdown Formatting Guide</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h5>Headings</h5>
                                        <pre># Heading 1
## Heading 2
### Heading 3</pre>
                                        
                                        <h5>Emphasis</h5>
                                        <pre>*italic* or _italic_
**bold** or __bold__</pre>
                                        
                                        <h5>Lists</h5>
                                        <pre>- Item 1
- Item 2
  - Subitem

1. First item
2. Second item</pre>
                                    </div>
                                    <div class="col-md-6">
                                        <h5>Links</h5>
                                        <pre>[Link text](https://example.com)</pre>
                                        
                                        <h5>Images</h5>
                                        <pre>![Alt text](image-url.jpg)</pre>
                                        
                                        <h5>Code</h5>
                                        <pre>`inline code`

```
code block
```</pre>
                                        
                                        <h5>Blockquotes</h5>
                                        <pre>> This is a blockquote</pre>
                                    </div>
                                </div>
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="alert alert-danger">
                    You must be logged in as an administrator to edit documentation.
                    <a href="admin.php" class="alert-link">Log in here</a>.
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>
    
    <footer class="footer">
        <p>Made with ❤️ by <a href="https://booskit.dev/">booskit</a></br>
        <a href="<?php echo FOOTER_GITHUB; ?>">Github</a> • <a href="#" class="dark-mode-toggle">🌙 Dark Mode</a></br>
        <span style="font-size: 12px;"><?php echo SITE_VERSION; ?></span></p>
    </footer>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
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