<?php
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
        } else {
            $error = error_get_last();
            $actionMessage = "Error: Unable to create administrator account. " . ($error ? $error['message'] : '');
            logAdminAction("Failed to create admin account: $newUsername - " . ($error ? $error['message'] : ''), false);
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - <?php echo SITE_NAME; ?></title>
    <link rel="apple-touch-icon" sizes="180x180" href="/resources/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/resources/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/resources/favicon-16x16.png">
    <link rel="manifest" href="/resources/site.webmanifest">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .container {
            margin-top: 2rem;
            margin-bottom: 2rem;
            flex-grow: 1;
        }
        .card {
            margin-bottom: 1.5rem;
            box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        }
        .stats-card {
            text-align: center;
            border-left: 4px solid #007bff;
        }
        .form-card {
            border-left: 4px solid #28a745;
        }
        .form-actions {
            display: flex;
            justify-content: flex-end;
        }
        .form-actions a, .form-actions button {
            margin-left: 0.5rem;
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
        .modal-form {
            padding: 1rem;
        }
        .login-container {
            max-width: 400px;
            margin: 5rem auto;
        }
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }
        .stat-value {
            font-size: 1.5rem;
            font-weight: bold;
            color: #007bff;
        }
        .stat-label {
            color: #6c757d;
            font-size: 0.875rem;
        }
        .form-list-empty {
            padding: 2rem;
            text-align: center;
            color: #6c757d;
        }
        .search-box {
            margin-bottom: 1rem;
        }
        .truncate {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            max-width: 200px;
        }
        /* New styles for log viewing */
        .log-actions {
            margin-top: 1rem;
        }
        .log-button {
            margin-right: 0.5rem;
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
        
        /* Container/Form styles */
        body.dark-mode .card {
            background-color: #1e1e1e;
            color: #e0e0e0;
            border-color: #444;
        }
        
        body.dark-mode .card-header {
            background-color: #2d2d2d;
            border-color: #444;
        }
        
        body.dark-mode .table {
            color: #e0e0e0;
        }
        
        body.dark-mode .table thead th {
            border-color: #444;
        }
        
        body.dark-mode .table-bordered,
        body.dark-mode .table-bordered td,
        body.dark-mode .table-bordered th {
            border-color: #444;
        }
        
        body.dark-mode .table-striped tbody tr:nth-of-type(odd) {
            background-color: rgba(255, 255, 255, 0.05);
        }
        
        body.dark-mode .table-hover tbody tr:hover {
            color: #e0e0e0;
            background-color: rgba(255, 255, 255, 0.075);
        }
        
        /* Form controls */
        body.dark-mode .form-control {
            background-color: #2d2d2d;
            color: #e0e0e0;
            border-color: #444;
        }
        
        body.dark-mode .input-group-text {
            background-color: #2d2d2d;
            color: #e0e0e0;
            border-color: #444;
        }
        
        /* Stats styling */
        body.dark-mode .stat-value {
            color: #4da3ff;
        }
        
        body.dark-mode .stat-label {
            color: #b0b0b0;
        }
        
        /* Modal styling */
        body.dark-mode .modal-content {
            background-color: #1e1e1e;
            color: #e0e0e0;
            border-color: #444;
        }
        
        body.dark-mode .modal-header,
        body.dark-mode .modal-footer {
            border-color: #444;
        }
        
        body.dark-mode .close {
            color: #e0e0e0;
            text-shadow: none;
        }
        
        /* Footer */
        body.dark-mode .footer {
            background-color: #1e1e1e;
            color: #aaa;
        }
        
        body.dark-mode .footer a {
            color: #4da3ff;
        }
    </style>
</head>
<body>
    <div class="container">
        <?php if (!$isLoggedIn && !file_exists($authConfig['htpasswd_file'])): ?>
            <!-- Initial setup form when .htpasswd doesn't exist -->
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
                            <div class="form-group">
                                <label for="new_username">Username:</label>
                                <input type="text" class="form-control" id="new_username" name="new_username" required>
                            </div>
                            <div class="form-group">
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
        <?php elseif (!$isLoggedIn): ?>
            <!-- Login form -->
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
                            <div class="form-group">
                                <label for="username">Username:</label>
                                <input type="text" class="form-control" id="username" name="username" required>
                            </div>
                            <div class="form-group">
                                <label for="password">Password:</label>
                                <input type="password" class="form-control" id="password" name="password" required>
                            </div>
                            <button type="submit" name="login" class="btn btn-primary btn-block">Log In</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Admin dashboard -->
            <div class="page-header">
                <h1>Admin Dashboard</h1>
                <div>
                    <a href="?" class="btn btn-outline-secondary mr-2"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
                    <a href="?action=logout" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
                </div>
            </div>
            
            <?php if ($actionMessage): ?>
                <div class="alert alert-info"><?php echo htmlspecialchars($actionMessage); ?></div>
            <?php endif; ?>
            
            <!-- Log Viewing Buttons -->
            <div class="card mb-4">
                <div class="card-header">
                    <h3 class="mb-0">View System Logs</h3>
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
                    </div>
                </div>
            </div>
            
            <!-- Stats Row -->
            <div class="row mb-4">
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="stat-value"><?php echo $stats['total_forms']; ?></div>
                            <div class="stat-label">Total Forms</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="stat-value"><?php echo $stats['recent_forms']; ?></div>
                            <div class="stat-label">Forms Created (24h)</div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card stats-card">
                        <div class="card-body">
                            <div class="stat-value"><?php echo $stats['total_size_formatted']; ?></div>
                            <div class="stat-label">Total Storage Used</div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Forms List -->
            <div class="card">
                <div class="card-header">
                    <h3 class="mb-0">Manage Forms</h3>
                </div>
                <div class="card-body">
                    <!-- Search box -->
                    <div class="search-box">
                        <input type="text" id="formSearch" class="form-control" placeholder="Search forms...">
                    </div>
                    
                    <?php if (empty($forms)): ?>
                        <div class="form-list-empty">
                            <p><i class="bi bi-inbox fs-1"></i></p>
                            <p>No forms have been created yet.</p>
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
                                                        data-toggle="modal" 
                                                        data-target="#deleteModal" 
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
            <div class="modal fade" id="deleteModal" tabindex="-1" role="dialog" aria-labelledby="deleteModalLabel" aria-hidden="true">
                <div class="modal-dialog" role="document">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="deleteModalLabel">Confirm Deletion</h5>
                            <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                                <span aria-hidden="true">&times;</span>
                            </button>
                        </div>
                        <div class="modal-body">
                            Are you sure you want to delete the form "<span id="formNameToDelete"></span>"?
                            <p class="text-danger mt-2">This action cannot be undone.</p>
                        </div>
                        <div class="modal-footer">
                            <form method="post" action="admin.php">
                                <input type="hidden" name="form_id" id="formIdToDelete">
                                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                                <button type="submit" name="delete_form" class="btn btn-danger">Delete</button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <footer class="footer">
        <p>Made with ❤️ by <a href="https://booskit.dev/">booskit</a></br>
        <a href="<?php echo FOOTER_GITHUB; ?>">Github</a> • <a href="#" class="dark-mode-toggle">🌙 Dark Mode</a></br>
        <span style="font-size: 12px;"><?php echo SITE_VERSION; ?></span></p>
    </footer>
    
    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Delete modal handler
        $('#deleteModal').on('show.bs.modal', function (event) {
            const button = $(event.relatedTarget);
            const formId = button.data('formid');
            const formName = button.data('formname');
            
            const modal = $(this);
            modal.find('#formNameToDelete').text(formName);
            modal.find('#formIdToDelete').val(formId);
        });
        
        // Search functionality - with null check
        const searchBox = document.getElementById('formSearch');
        if (searchBox) {
            searchBox.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#formsList tr');
                
                rows.forEach(row => {
                    const formId = row.querySelector('td:first-child').textContent.toLowerCase();
                    const formName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    
                    if (formId.includes(searchTerm) || formName.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        
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