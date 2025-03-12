<?php
/**
 * reBB - Account Sharing Page
 * 
 * This file provides an interface for admins to create and share user account details.
 * This page requires authentication with admin role.
 */

// Require admin authentication before processing anything else
auth()->requireRole('admin', 'login');

// Since we've passed the auth check, we can safely get the current user
$currentUser = auth()->getUser();

// Initialize variables
$actionMessage = '';
$actionMessageType = 'info';
$generatedAccount = null;

// Handle account creation
if (isset($_POST['create_account'])) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $role = isset($_POST['role']) ? $_POST['role'] : 'user';
    
    // Generate a random password
    $password = generateSecurePassword(12);
    
    // Validate username format
    if (!preg_match('/^[a-zA-Z0-9_]{3,20}$/', $username)) {
        $actionMessage = "Invalid username format. Use 3-20 characters (letters, numbers, underscore).";
        $actionMessageType = 'danger';
    }
    else {
        // Try to create the user
        $result = auth()->register($username, $password, ['role' => $role]);
        
        if ($result) {
            $actionMessage = "Account created successfully. Share the details below.";
            $actionMessageType = 'success';
            logAdminAction("Created shareable account: $username with role: $role");
            
            // Store the generated account details for display
            $generatedAccount = [
                'username' => $username,
                'password' => $password,
                'role' => $role
            ];
        } else {
            $actionMessage = "Failed to create account. Username may already exist.";
            $actionMessageType = 'danger';
            logAdminAction("Failed to create shareable account: $username", false);
        }
    }
}

/**
 * Generate a secure random password
 *
 * @param int $length Length of the password
 * @return string Secure password
 */
function generateSecurePassword($length = 12) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()-_=+';
    $password = '';
    
    // Ensure we have at least one of each character type
    $password .= $chars[random_int(0, 25)]; // lowercase
    $password .= $chars[random_int(26, 51)]; // uppercase
    $password .= $chars[random_int(52, 61)]; // number
    $password .= $chars[random_int(62, strlen($chars) - 1)]; // special
    
    // Fill the rest with random characters
    $max = strlen($chars) - 1;
    for ($i = strlen($password); $i < $length; $i++) {
        $password .= $chars[random_int(0, $max)];
    }
    
    // Shuffle the password to mix the guaranteed character types
    return str_shuffle($password);
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<!-- Admin dashboard - Using the special admin container class -->
<div class="container-admin">
    <div class="page-header">
        <h1>Share Account</h1>
        <div>
            <span class="text-muted me-3">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></span>
            <a href="<?php echo site_url('admin'); ?>" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Back to Admin
            </a>
            <a href="<?php echo site_url('admin/users'); ?>" class="btn btn-outline-primary me-2">
                <i class="bi bi-people"></i> User Management
            </a>
            <a href="<?php echo site_url('logout'); ?>" class="btn btn-outline-danger">
                <i class="bi bi-box-arrow-right"></i> Logout
            </a>
        </div>
    </div>
    
    <?php if ($actionMessage): ?>
        <div class="alert alert-<?php echo $actionMessageType; ?> alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($actionMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Left column - Create Account -->
        <div class="col-md-6">
            <div class="card mb-4">
                <div class="card-header">
                    <h4 class="mb-0">Create Shareable Account</h4>
                </div>
                <div class="card-body">
                    <p>Create a new account and receive a randomly generated password to share with the user.</p>
                    
                    <form method="post" action="<?php echo site_url('admin/share'); ?>">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required 
                                   pattern="[a-zA-Z0-9_]{3,20}" placeholder="Enter a username">
                            <div class="form-text">3-20 characters, letters, numbers, and underscores only.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Role</label>
                            <select class="form-select" id="role" name="role">
                                <option value="user">User</option>
                                <option value="editor">Editor</option>
                                <option value="admin">Admin</option>
                            </select>
                            <div class="form-text">
                                <strong>Admin:</strong> Full system access. <strong>User:</strong> Can create/manage forms.
                            </div>
                        </div>
                        
                        <button type="submit" name="create_account" class="btn btn-success w-100">
                            <i class="bi bi-person-plus"></i> Create Account
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Security Info -->
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0"><i class="bi bi-shield-check"></i> Security Information</h5>
                </div>
                <div class="card-body">
                    <p>When sharing account details:</p>
                    <ul>
                        <li>Use a secure communication channel</li>
                        <li>Ask users to change their password after first login</li>
                        <li>Do not create admin accounts unless absolutely necessary</li>
                        <li>You can manage all accounts in the <a href="<?php echo site_url('admin/users'); ?>">User Management</a> panel</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <!-- Right column - Account Details -->
        <div class="col-md-6">
            <?php if ($generatedAccount): ?>
                <div class="card border-success mb-4">
                    <div class="card-header bg-success text-white">
                        <h4 class="mb-0"><i class="bi bi-person-check"></i> Account Created</h4>
                    </div>
                    <div class="card-body">
                        <p class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle"></i> 
                            <strong>Important:</strong> Save these details now. The password cannot be retrieved later.
                        </p>
                        
                        <div class="account-details p-3 bg-light rounded mb-3">
                            <p><strong>Username:</strong> <?php echo htmlspecialchars($generatedAccount['username']); ?></p>
                            <p><strong>Password:</strong> <span id="password-display"><?php echo htmlspecialchars($generatedAccount['password']); ?></span>
                                <button id="toggle-password" class="btn btn-sm btn-outline-secondary ms-2" type="button" data-shown="true">
                                    <i class="bi bi-eye-slash"></i>
                                </button>
                                <button id="copy-password" class="btn btn-sm btn-outline-primary ms-1" type="button">
                                    <i class="bi bi-clipboard"></i>
                                </button>
                            </p>
                            <p><strong>Role:</strong> 
                                <span class="badge bg-<?php echo $generatedAccount['role'] === 'admin' ? 'danger' : 'success'; ?>">
                                    <?php echo htmlspecialchars($generatedAccount['role']); ?>
                                </span>
                            </p>
                            <p><strong>Login URL:</strong> <a href="<?php echo site_url('login'); ?>" target="_blank"><?php echo site_url('login'); ?></a></p>
                        </div>
                        
                        <!-- Sharing options -->
                        <div class="sharing-options">
                            <h5>Share via:</h5>
                            <div class="d-flex flex-wrap gap-2">
                                <button class="btn btn-primary copy-all">
                                    <i class="bi bi-clipboard"></i> Copy All Details
                                </button>
                                <button class="btn btn-info" id="btn-print">
                                    <i class="bi bi-printer"></i> Print
                                </button>
                                <button class="btn btn-success" id="btn-download">
                                    <i class="bi bi-file-earmark-text"></i> Download as Text
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="card mb-4 h-100">
                    <div class="card-header bg-light">
                        <h4 class="mb-0">Account Details</h4>
                    </div>
                    <div class="card-body d-flex align-items-center justify-content-center flex-column">
                        <div class="text-center p-4">
                            <i class="bi bi-person-plus" style="font-size: 3rem; color: #ccc;"></i>
                            <p class="mt-3">Create an account to see the details here.</p>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Custom JavaScript for this page -->
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Toggle password visibility
        const togglePasswordBtn = document.getElementById('toggle-password');
        if (togglePasswordBtn) {
            togglePasswordBtn.addEventListener('click', function() {
                const passwordDisplay = document.getElementById('password-display');
                const isShown = this.getAttribute('data-shown') === 'true';
                
                if (isShown) {
                    // Hide password
                    const password = passwordDisplay.textContent;
                    passwordDisplay.textContent = '•'.repeat(password.length);
                    this.setAttribute('data-shown', 'false');
                    this.innerHTML = '<i class="bi bi-eye"></i>';
                } else {
                    // Show password
                    const password = '<?php echo $generatedAccount ? htmlspecialchars($generatedAccount['password']) : ''; ?>';
                    passwordDisplay.textContent = password;
                    this.setAttribute('data-shown', 'true');
                    this.innerHTML = '<i class="bi bi-eye-slash"></i>';
                }
            });
        }
        
        // Copy password
        const copyPasswordBtn = document.getElementById('copy-password');
        if (copyPasswordBtn) {
            copyPasswordBtn.addEventListener('click', function() {
                const password = '<?php echo $generatedAccount ? htmlspecialchars($generatedAccount['password']) : ''; ?>';
                copyToClipboard(password).then(() => {
                    // Show feedback
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check"></i>';
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                    }, 1000);
                });
            });
        }
        
        // Copy all details
        const copyAllBtn = document.querySelector('.copy-all');
        if (copyAllBtn) {
            copyAllBtn.addEventListener('click', function() {
                const username = '<?php echo $generatedAccount ? htmlspecialchars($generatedAccount['username']) : ''; ?>';
                const password = '<?php echo $generatedAccount ? htmlspecialchars($generatedAccount['password']) : ''; ?>';
                const role = '<?php echo $generatedAccount ? htmlspecialchars($generatedAccount['role']) : ''; ?>';
                const loginUrl = '<?php echo site_url('login'); ?>';
                
                const allDetails = `
Account Details:
Username: ${username}
Password: ${password}
Role: ${role}
Login URL: ${loginUrl}
                `.trim();
                
                copyToClipboard(allDetails).then(() => {
                    // Show feedback
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check"></i> Copied!';
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                    }, 1000);
                });
            });
        }
        
        // Download as text
        const downloadBtn = document.getElementById('btn-download');
        if (downloadBtn) {
            downloadBtn.addEventListener('click', function() {
                const username = '<?php echo $generatedAccount ? htmlspecialchars($generatedAccount['username']) : ''; ?>';
                const password = '<?php echo $generatedAccount ? htmlspecialchars($generatedAccount['password']) : ''; ?>';
                const role = '<?php echo $generatedAccount ? htmlspecialchars($generatedAccount['role']) : ''; ?>';
                const loginUrl = '<?php echo site_url('login'); ?>';
                
                const allDetails = `
Account Details for ${username}
----------------------------------
Username: ${username}
Password: ${password}
Role: ${role}
Login URL: ${loginUrl}
----------------------------------
Generated on: ${new Date().toLocaleString()}
                `.trim();
                
                // Create blob and download
                const blob = new Blob([allDetails], { type: 'text/plain' });
                const url = URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `account_${username}.txt`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                URL.revokeObjectURL(url);
            });
        }
        
        // Print function
        const printBtn = document.getElementById('btn-print');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                const username = '<?php echo $generatedAccount ? htmlspecialchars($generatedAccount['username']) : ''; ?>';
                const password = '<?php echo $generatedAccount ? htmlspecialchars($generatedAccount['password']) : ''; ?>';
                const role = '<?php echo $generatedAccount ? htmlspecialchars($generatedAccount['role']) : ''; ?>';
                const loginUrl = '<?php echo site_url('login'); ?>';
                
                // Create print window
                const printWindow = window.open('', '_blank');
                printWindow.document.write(`
                    <!DOCTYPE html>
                    <html>
                    <head>
                        <title>Account Details</title>
                        <style>
                            body { font-family: Arial, sans-serif; line-height: 1.6; padding: 20px; }
                            .container { max-width: 500px; margin: 0 auto; padding: 20px; border: 1px solid #ddd; }
                            h1 { color: #333; font-size: 24px; margin-bottom: 20px; }
                            .details { margin-bottom: 20px; }
                            .details p { margin: 10px 0; }
                            .label { font-weight: bold; }
                            .footer { margin-top: 30px; font-size: 0.8em; color: #777; }
                        </style>
                    </head>
                    <body>
                        <div class=\"containe\r">
                            <h1>Account Details</h1>
                            <div class=\"details\">
                                <p><span class=\"label\">Username:</span> ${username}</p>
                                <p><span class=\"label\">Password:</span> ${password}</p>
                                <p><span class=\"label\">Role:</span> ${role}</p>
                                <p><span class=\"label\">Login URL:</span> ${loginUrl}</p>
                            </div>
                            <div class=\"footer\">
                                Generated on: ${new Date().toLocaleString()}<br>
                                Please save this information securely and change your password upon first login.
                            </div>
                        </div>\
                    </body>
                    </html>
                `);
                printWindow.document.close();
            });
        }
        
        // Copy to clipboard helper
        function copyToClipboard(text) {
            // Modern approach - Clipboard API
            if (navigator && navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                return navigator.clipboard.writeText(text);
            }
            
            // Fallback approach
            return new Promise((resolve, reject) => {
                try {
                    const textarea = document.createElement('textarea');
                    textarea.value = text;
                    textarea.style.position = 'fixed';
                    textarea.style.opacity = '0';
                    document.body.appendChild(textarea);
                    textarea.select();
                    
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
    });
</script>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'Share Account';

// Add page-specific CSS (using the admin styles)
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/admin.css') .'?v=' . APP_VERSION . '">';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';

/**
 * Log admin actions - reusing from admin.php
 * @param string $action The action to log
 * @param bool $success Whether the action was successful
 */
function logAdminAction($action, $success = true) {
    $logFile = STORAGE_DIR . '/logs/admin_activity.log';
    $logsDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!is_dir($logsDir)) {
        mkdir($logsDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    
    // Get current authenticated user
    $user = auth()->getUser();
    $username = $user ? $user['username'] : 'Unknown';
    
    $status = $success ? 'SUCCESS' : 'FAILED';
    $logEntry = "[$timestamp] [$status] [IP: $ip] [User: $username] $action" . PHP_EOL;
    
    // Try to write to log file, silently fail if unable
    @file_put_contents($logFile, $logEntry, FILE_APPEND);
}
?>