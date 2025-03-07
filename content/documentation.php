<?php
/**
 * reBB - Documentation
 * 
 * This file serves as the render point for documentation.
 */

// Make sure SESSION_LIFETIME is defined
if (!defined('SESSION_LIFETIME')) {
    define('SESSION_LIFETIME', 1800); // Default to 30 minutes
}

// Initialize session if not already started
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

// Define the libraries directory
$libDir = ROOT_DIR . '/lib';
$parsedownPath = $libDir . '/Parsedown.php';

// Create the lib directory if it doesn't exist
if (!is_dir($libDir)) {
    @mkdir($libDir, 0755, true);
}

// Auto-download Parsedown if not exists
if (!file_exists($parsedownPath)) {
    $parsedownSource = @file_get_contents('https://raw.githubusercontent.com/erusev/parsedown/master/Parsedown.php');
    if ($parsedownSource) {
        @file_put_contents($parsedownPath, $parsedownSource);
        logDocAction("Downloaded Parsedown library to {$libDir}");
    }
}

// Include Parsedown
if (file_exists($parsedownPath)) {
    require_once $parsedownPath;
}

// Configuration - make it global so it's accessible in functions
global $config;
$config = [
    'docs_dir' => ROOT_DIR . '/documentation',
    'log_file' => ROOT_DIR . '/logs/documentation_activity.log', // Fixed missing slash here
    'allowed_extensions' => ['md'],
    'default_doc' => 'getting-started.md',
    'session_timeout' => SESSION_LIFETIME
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

/**
 * Log documentation actions
 * @param string $action The action to log
 * @param bool $success Whether the action was successful
 */
function logDocAction($action, $success = true) {
    global $config;
    
    // Safety check - ensure log file path exists
    if (empty($config) || empty($config['log_file'])) {
        // Fallback log path
        $log_file = ROOT_DIR . '/logs/documentation_activity.log';
    } else {
        $log_file = $config['log_file'];
    }
    
    // Make sure logs directory exists
    $logsDir = dirname($log_file);
    if (!is_dir($logsDir)) {
        @mkdir($logsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user = $_SESSION['admin_username'] ?? 'Unauthenticated';
    $status = $success ? 'SUCCESS' : 'FAILED';
    
    $logEntry = "[$timestamp] [$status] [IP: $ip] [User: $user] $action" . PHP_EOL;
    @file_put_contents($log_file, $logEntry, FILE_APPEND);
}

/**
 * Get a list of all documentation files
 * @return array List of documentation files with metadata
 */
function getDocumentationFiles() {
    global $config;
    
    $files = [];
    
    // Safety check
    if (empty($config) || empty($config['docs_dir']) || !is_dir($config['docs_dir'])) {
        return $files;
    }
    
    try {
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
    } catch (Exception $e) {
        // Handle any errors silently
        error_log("Error getting documentation files: " . $e->getMessage());
    }
    
    return $files;
}

/**
 * Extract order number from document content
 * @param string $filePath Path to the document file
 * @return int Order number (999 if not found)
 */
function getDocumentOrder($filePath) {
    if (!file_exists($filePath)) {
        return 999; // Default high number for new files
    }
    
    $content = @file_get_contents($filePath);
    if ($content === false) {
        return 999;
    }
    
    // Look for [ORDER: X] where X is a number
    if (preg_match('/\[ORDER:\s*(\d+)\]/i', $content, $matches)) {
        return (int)$matches[1];
    }
    
    return 999; // Default high number if no order found
}

/**
 * Extract a title from the markdown file (first heading)
 * @param string $filePath Path to the document file
 * @return string Document title
 */
function getDocumentTitle($filePath) {
    if (!file_exists($filePath)) {
        return pathinfo($filePath, PATHINFO_FILENAME);
    }
    
    $content = @file_get_contents($filePath);
    if ($content === false) {
        return pathinfo($filePath, PATHINFO_FILENAME);
    }
    
    // Look for the first heading (# Title)
    if (preg_match('/^#\s+(.+)$/m', $content, $matches)) {
        return $matches[1];
    }
    
    // If no heading found, use the filename without extension
    return pathinfo($filePath, PATHINFO_FILENAME);
}

/**
 * Convert markdown to HTML
 * @param string $markdown Markdown content
 * @return string HTML content
 */
function markdownToHtml($markdown) {
    // Remove order tags before processing
    $markdown = preg_replace('/\[ORDER:\s*\d+\]/i', '', $markdown);
    
    // If Parsedown is available, use it
    if (class_exists('Parsedown')) {
        $parsedown = new Parsedown();
        return $parsedown->text($markdown);
    } else {
        // Fallback to basic conversion if Parsedown isn't available
        $html = nl2br(htmlspecialchars($markdown));
        return '<div class="alert alert-warning">Parsedown library not available. Showing raw content.</div>' . $html;
    }
}

/**
 * Sanitize filenames to prevent directory traversal attacks
 * @param string $filename Filename to sanitize
 * @return string Sanitized filename
 */
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
            $docContent = @file_get_contents($filePath);
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
            
            if (@file_put_contents($filePath, $fullContent)) {
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
                
                if (@file_put_contents($filePath, $fullContent)) {
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
        
        if (file_exists($filePath) && @unlink($filePath)) {
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
            $docContent = @file_get_contents($filePath);
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
        $markdownContent = @file_get_contents($docPath);
        $docContent = markdownToHtml($markdownContent);
    }
}

// Create a sample "Getting Started" doc if no documents exist
if (empty($documentFiles) && is_dir($config['docs_dir'])) {
    $defaultDocPath = $config['docs_dir'] . '/' . $config['default_doc'];
    
    if (!file_exists($defaultDocPath)) {
        $defaultContent = <<<EOT
[ORDER: 1]

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

        if (@file_put_contents($defaultDocPath, $defaultContent)) {
            // Refresh file list
            $documentFiles = getDocumentationFiles();
            
            if (empty($activeDoc)) {
                $activeDoc = $config['default_doc'];
                $markdownContent = $defaultContent;
                $docContent = markdownToHtml($markdownContent);
            }
            
            logDocAction("Created default 'Getting Started' document");

            header('Location: docs');
            exit;
        }
    }
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container documentation-container">
    <header>
        <div class="d-flex">
            <h1>
                <a href="<?php echo site_url(); ?>" class="text-decoration-none">
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
            <div class="card card-documentation">
                <div class="card-header">
                    <h3><?php echo isset($_GET['edit']) && $_GET['edit'] !== 'new' ? 'Edit Document' : 'Create New Document'; ?></h3>
                </div>
                <div class="card-body">
                    <form method="post" action="docs">
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
                            <div class="form-text">Lower numbers appear at the top. Edit the [ORDER: X] tag in your document to change order later.</div>
                        </div>
                        <?php else: ?>
                        <div class="alert alert-info">
                            <strong>Note:</strong> To change document order, edit the [ORDER: X] tag in your document content (where X is a number).
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
                            <form method="post" action="docs">
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
                <a href="<?php echo site_url('admin'); ?>" class="alert-link">Log in here</a>.
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'Documentation';

// Add page-specific CSS
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/documentation.css') .'?v=' . APP_VERSION . '">';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';