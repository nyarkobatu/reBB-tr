<?php
/**
 * reBB - Admin Page
 * 
 * This file serves the administrative interface for the app.
 */
require_once 'site.php';

// Start session for authentication
session_start();

// Admin authentication configuration
$authConfig = [
    'htpasswd_file' => __DIR__ . '/.htpasswd',
    'session_timeout' => 1800, // 30 minutes
    'log_file' => 'logs/admin_access.log'
];

// Create logs directory if it doesn't exist
$logsDir = dirname($authConfig['log_file']);
if (!is_dir($logsDir)) {
    @mkdir($logsDir, 0755, true);
}

// Enable error reporting during setup
if (!file_exists($authConfig['htpasswd_file']) && isset($_GET['debug'])) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// Authentication functions
function verifyPassword($username, $password, $htpasswdFile) {
    if (!file_exists($htpasswdFile)) {
        return false;
    }
    
    $lines = file($htpasswdFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        list($storedUsername, $storedHash) = explode(':', $line, 2);
        
        if ($username === $storedUsername) {
            // Check if it's bcrypt (starts with $2y$)
            if (strpos($storedHash, '$2y$') === 0) {
                return password_verify($password, $storedHash);
            } 
            // Check if it's MD5 based (default for Apache htpasswd)
            else if (strpos($storedHash, '$apr1$') === 0 || strpos($storedHash, '$1$') === 0) {
                // For MD5 hashes, we compare directly (Apache style)
                return crypt($password, $storedHash) === $storedHash;
            }
            // Plain text comparison fallback (not recommended)
            else {
                return $storedHash === $password;
            }
        }
    }
    
    return false;
}

function logAdminAction($action, $success = true) {
    global $authConfig;
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    $user = $_SESSION['admin_username'] ?? 'Unauthenticated';
    $status = $success ? 'SUCCESS' : 'FAILED';
    
    $logEntry = "[$timestamp] [$status] [IP: $ip] [User: $user] $action" . PHP_EOL;
    file_put_contents($authConfig['log_file'], $logEntry, FILE_APPEND);
}

// Initialize variables
$isLoggedIn = false;
$loginError = '';
$actionMessage = '';
$forms = [];
$stats = [
    'total_forms' => 0,
    'recent_forms' => 0,
    'total_size' => 0
];

// Check for login timeout
if (isset($_SESSION['admin_last_activity']) && (time() - $_SESSION['admin_last_activity'] > $authConfig['session_timeout'])) {
    // Last activity was more than 30 minutes ago
    session_unset();
    session_destroy();
    $loginError = 'Your session has expired. Please log in again.';
}

// Process login
if (isset($_POST['login']) && isset($_POST['username']) && isset($_POST['password'])) {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    if (verifyPassword($username, $password, $authConfig['htpasswd_file'])) {
        $_SESSION['admin_username'] = $username;
        $_SESSION['admin_last_activity'] = time();
        $isLoggedIn = true;
        logAdminAction("Admin login");
    } else {
        $loginError = 'Invalid username or password';
        logAdminAction("Failed login attempt - Username: $username", false);
    }
}

// Check if user is logged in
if (isset($_SESSION['admin_username'])) {
    $isLoggedIn = true;
    $_SESSION['admin_last_activity'] = time(); // Update last activity time
}

// Handle logout
if (isset($_GET['action']) && $_GET['action'] === 'logout' && $isLoggedIn) {
    logAdminAction("Admin logout");
    session_unset();
    session_destroy();
    header('Location: admin.php');
    exit;
}

// NEW FEATURE: Log viewing functionality
$viewingLogs = false;
$logContent = '';
$logType = '';

if ($isLoggedIn && isset($_GET['logs'])) {
    $viewingLogs = true;
    $logType = $_GET['logs'];
    
    // Determine which log file to show
    $logFile = '';
    if ($logType === 'admin') {
        $logFile = $authConfig['log_file']; // Admin access logs
        logAdminAction("Viewed admin access logs");
    } elseif ($logType === 'forms') {
        $logFile = 'logs/form_submissions.log'; // Form submission logs
        logAdminAction("Viewed form submission logs");
    } elseif ($logType === 'docs') {
        $logFile = 'logs/documentation_activity.log';
        logAdminAction("Viewed documentation activity logs");
    }
    
    // Read and display log content if file exists
    if (!empty($logFile) && file_exists($logFile)) {
        $logContent = file_get_contents($logFile);
    } else {
        $logContent = "Log file not found.";
    }
    
    // Output log content as plain text
    header('Content-Type: text/plain');
    echo $logContent;
    exit;
}

// Handle form deletion
if ($isLoggedIn && isset($_POST['delete_form']) && isset($_POST['form_id'])) {
    $formId = $_POST['form_id'];
    $filename = 'forms/' . $formId . '_schema.json';
    
    if (file_exists($filename) && is_readable($filename) && unlink($filename)) {
        $actionMessage = "Form $formId has been deleted successfully.";
        logAdminAction("Deleted form: $formId");
    } else {
        $actionMessage = "Error: Unable to delete form $formId.";
        logAdminAction("Failed to delete form: $formId", false);
    }
}

// Get form data if logged in
if ($isLoggedIn) {
    $formsDir = 'forms';
    
    if (is_dir($formsDir)) {
        $files = scandir($formsDir);
        
        foreach ($files as $file) {
            if ($file === '.' || $file === '..' || !str_ends_with($file, '_schema.json')) {
                continue;
            }
            
            $filePath = $formsDir . '/' . $file;
            $fileSize = filesize($filePath);
            $stats['total_size'] += $fileSize;
            
            $formId = str_replace('_schema.json', '', $file);
            $creationTime = filectime($filePath);
            $modificationTime = filemtime($filePath);
            
            // Try to get form name from the file
            $formName = "";
            $fileContent = file_get_contents($filePath);
            $formData = json_decode($fileContent, true);
            if ($formData && isset($formData['formName'])) {
                $formName = $formData['formName'];
            }
            
            $forms[] = [
                'id' => $formId,
                'name' => $formName,
                'created' => $creationTime,
                'modified' => $modificationTime,
                'size' => $fileSize,
                'url' => SITE_URL . '/form.php?f=' . $formId
            ];
        }
        
        // Sort forms by creation date (newest first)
        usort($forms, function($a, $b) {
            return $b['created'] - $a['created'];
        });
        
        // Calculate stats
        $stats['total_forms'] = count($forms);
        
        // Count forms created in the last 24 hours
        $oneDayAgo = time() - (24 * 60 * 60);
        foreach ($forms as $form) {
            if ($form['created'] >= $oneDayAgo) {
                $stats['recent_forms']++;
            }
        }
    }
    
    // Format total size to be more readable
    if ($stats['total_size'] < 1024) {
        $stats['total_size_formatted'] = $stats['total_size'] . ' B';
    } elseif ($stats['total_size'] < 1048576) {
        $stats['total_size_formatted'] = round($stats['total_size'] / 1024, 2) . ' KB';
    } else {
        $stats['total_size_formatted'] = round($stats['total_size'] / 1048576, 2) . ' MB';
    }
}

// Determine if .htpasswd file exists and create if not
if (!file_exists($authConfig['htpasswd_file']) && isset($_POST['create_admin']) && isset($_POST['new_username']) && isset($_POST['new_password'])) {
    $newUsername = $_POST['new_username'];
    $newPassword = $_POST['new_password'];
    
    // Create a new .htpasswd file with bcrypt hash
    $bcryptHash = password_hash($newPassword, PASSWORD_BCRYPT);
    $htpasswdContent = "$newUsername:$bcryptHash\n";
    
    $dirPath = dirname($authConfig['htpasswd_file']);
    if (!is_dir($dirPath)) {
        @mkdir($dirPath, 0755, true);
    }
    
    // Check if directory is writable
    if (!is_writable($dirPath)) {
        $actionMessage = "Error: Directory is not writable. Please check permissions.";
        logAdminAction("Failed to create admin account due to permissions: $newUsername", false);
    } else {
        // Try to create the file with proper error handling
        $result = @file_put_contents($authConfig['htpasswd_file'], $htpasswdContent);
        if ($result !== false) {
            $actionMessage = "Administrator account created successfully.";
            logAdminAction("Created admin account: $newUsername");
            // Auto-login the user
            $_SESSION['admin_username'] = $newUsername;
            $_SESSION['admin_last_activity'] = time();
            $isLoggedIn = true;

            // Store success message in session to display after redirect
            $_SESSION['admin_message'] = $actionMessage;

            // Redirect to refresh the page - this must happen before any HTML output
            header("Location: " . $_SERVER['PHP_SELF']);
            exit();
        } else {
            $error = error_get_last();
            $actionMessage = "Error: Unable to create administrator account. " . ($error ? $error['message'] : '');
            logAdminAction("Failed to create admin account: $newUsername - " . ($error ? $error['message'] : ''), false);
        }
    }
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<?php if (!$isLoggedIn && !file_exists($authConfig['htpasswd_file'])): ?>
    <!-- Initial setup form when .htpasswd doesn't exist -->
    <div class="container login-page">
        <div class="login-container">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Initial Admin Setup</h3>
                </div>
                <div class="card-body">
                    <?php if ($actionMessage): ?>
                        <div class="alert alert-info"><?php echo htmlspecialchars($actionMessage); ?></div>
                    <?php endif; ?>
                    
                    <p>Welcome to the admin panel setup. Please create an administrator account:</p>
                    
                    <form method="post" action="admin.php">
                        <div class="form-group mb-3">
                            <label for="new_username">Username:</label>
                            <input type="text" class="form-control" id="new_username" name="new_username" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="new_password">Password:</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required>
                            <small class="form-text text-muted">Choose a strong password with at least 8 characters.</small>
                        </div>
                        <button type="submit" name="create_admin" class="btn btn-primary btn-block">Create Admin Account</button>
                    </form>
                    
                    <!-- Debug information for troubleshooting -->
                    <div class="mt-4">
                        <p><small>Having trouble? <a href="?debug=1">Enable debug mode</a></small></p>
                        <?php if (isset($_GET['debug'])): ?>
                            <div class="alert alert-warning">
                                <h5>Debug Information:</h5>
                                <ul>
                                    <li>PHP Version: <?php echo PHP_VERSION; ?></li>
                                    <li>Document Root: <?php echo $_SERVER['DOCUMENT_ROOT']; ?></li>
                                    <li>Script Path: <?php echo __FILE__; ?></li>
                                    <li>HtPasswd Path: <?php echo $authConfig['htpasswd_file']; ?></li>
                                    <li>Is Writable: <?php echo is_writable(dirname($authConfig['htpasswd_file'])) ? 'Yes' : 'No'; ?></li>
                                    <li>
                                        PHP user: 
                                        <?php 
                                            if (function_exists('posix_getpwuid') && function_exists('posix_geteuid')) {
                                                $processUser = posix_getpwuid(posix_geteuid());
                                                echo $processUser['name'];
                                            } else {
                                                echo 'Unknown (posix functions not available)';
                                            }
                                        ?>
                                    </li>
                                </ul>
                                <p>If the directory is not writable, try changing permissions with: <code>chmod 755 <?php echo dirname(__FILE__); ?></code></p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php elseif (!$isLoggedIn): ?>
    <!-- Login form -->
    <div class="container">
        <div class="login-container">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Admin Login</h3>
                </div>
                <div class="card-body">
                    <?php if ($loginError): ?>
                        <div class="alert alert-danger"><?php echo htmlspecialchars($loginError); ?></div>
                    <?php endif; ?>
                    
                    <form method="post" action="admin.php">
                        <div class="form-group mb-3">
                            <label for="username">Username:</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="form-group mb-3">
                            <label for="password">Password:</label>
                            <input type="password" class="form-control" id="password" name="password" required>
                        </div>
                        <button type="submit" name="login" class="btn btn-primary btn-block">Log In</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Admin dashboard - Using the special admin container class -->
    <div class="container-admin">
        <div class="page-header">
            <h1>Admin Dashboard</h1>
            <div>
                <a href="?" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
                <a href="?action=logout" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
            </div>
        </div>
        
        <?php if ($actionMessage): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($actionMessage); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="stat-value"><?php echo $stats['total_forms']; ?></div>
                        <div class="stat-label">Total Forms</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="stat-value"><?php echo $stats['recent_forms']; ?></div>
                        <div class="stat-label">Forms Created (24h)</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="stat-value"><?php echo $stats['total_size_formatted']; ?></div>
                        <div class="stat-label">Total Storage Used</div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Log Viewing Buttons -->
        <div class="card mb-4">
            <div class="card-header">
                <h4 class="mb-0">View System Logs</h4>
            </div>
            <div class="card-body">
                <p>Access system logs to monitor activity:</p>
                <div class="log-actions">
                    <a href="?logs=admin" class="btn btn-info log-button" target="_blank">
                        <i class="bi bi-file-text"></i> View Admin Logs
                    </a>
                    <a href="?logs=forms" class="btn btn-info log-button" target="_blank">
                        <i class="bi bi-file-text"></i> View Form Submission Logs
                    </a>
                    <a href="?logs=docs" class="btn btn-info log-button" target="_blank">
                        <i class="bi bi-file-text"></i> View Documentation Activity Logs
                    </a>
                </div>
            </div>
        </div>
        
        <!-- Forms List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h4 class="mb-0">Manage Forms</h4>
                <a href="builder.php" class="btn btn-sm btn-success">
                    <i class="bi bi-plus-circle"></i> Create New Form
                </a>
            </div>
            <div class="card-body">
                <!-- Search box -->
                <div class="search-box">
                    <input type="text" id="formSearch" class="form-control" placeholder="Search forms by name or ID...">
                </div>
                
                <?php if (empty($forms)): ?>
                    <div class="form-list-empty">
                        <p><i class="bi bi-inbox"></i></p>
                        <p>No forms have been created yet.</p>
                        <a href="builder.php" class="btn btn-primary">Create your first form</a>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Form ID</th>
                                    <th>Name</th>
                                    <th>Created</th>
                                    <th>Size</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="formsList">
                                <?php foreach ($forms as $form): ?>
                                    <tr>
                                        <td class="truncate"><?php echo htmlspecialchars($form['id']); ?></td>
                                        <td class="truncate"><?php echo htmlspecialchars($form['name'] ?: 'Unnamed Form'); ?></td>
                                        <td><?php echo date('Y-m-d H:i:s', $form['created']); ?></td>
                                        <td>
                                            <?php 
                                                if ($form['size'] < 1024) {
                                                    echo $form['size'] . ' B';
                                                } elseif ($form['size'] < 1048576) {
                                                    echo round($form['size'] / 1024, 2) . ' KB';
                                                } else {
                                                    echo round($form['size'] / 1048576, 2) . ' MB';
                                                }
                                            ?>
                                        </td>
                                        <td class="form-actions">
                                            <a href="<?php echo htmlspecialchars($form['url']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                                <i class="bi bi-eye"></i> View
                                            </a>
                                            <a href="builder.php?f=<?php echo htmlspecialchars($form['id']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                            <button type="button" class="btn btn-sm btn-outline-danger" 
                                                    data-bs-toggle="modal" 
                                                    data-bs-target="#deleteModal" 
                                                    data-formid="<?php echo htmlspecialchars($form['id']); ?>"
                                                    data-formname="<?php echo htmlspecialchars($form['name'] ?: 'Unnamed Form'); ?>">
                                                <i class="bi bi-trash"></i> Delete
                                            </button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Delete Confirmation Modal -->
        <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        Are you sure you want to delete the form "<span id="formNameToDelete"></span>"?
                        <p class="text-danger mt-2">This action cannot be undone.</p>
                    </div>
                    <div class="modal-footer">
                        <form method="post" action="admin.php">
                            <input type="hidden" name="form_id" id="formIdToDelete">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" name="delete_form" class="btn btn-danger">Delete</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'Admin Panel';

// Add page-specific JavaScript
$GLOBALS['page_javascript'] = '<script src="'. ASSETS_DIR .'/js/app/admin.js?v=' . SITE_VERSION . '"></script>';

// Include the master layout
require_once BASE_DIR . '/includes/master.php';