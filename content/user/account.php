<?php
/**
 * reBB - Account Management
 * 
 * This file allows users to manage their account settings such as password.
 * It will be expanded in the future to include additional account management features.
 */

// Require authentication before processing anything else
auth()->requireAuth('login');

// Since we've passed the auth check, we can safely get the current user
$currentUser = auth()->getUser();

// Initialize variables
$actionMessage = '';
$actionMessageType = 'info';
$activeTab = 'password'; // Default tab - more tabs can be added in the future

// Process password change if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    // Verify CSRF token
    $csrfToken = $_POST['csrf_token'] ?? '';
    
    if (!auth()->verifyCsrfToken($csrfToken)) {
        $actionMessage = "Invalid form submission. Please try again.";
        $actionMessageType = 'danger';
    } else {
        // Get form data
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Basic validation
        if (empty($currentPassword)) {
            $actionMessage = "Current password is required.";
            $actionMessageType = 'danger';
        } elseif (empty($newPassword)) {
            $actionMessage = "New password is required.";
            $actionMessageType = 'danger';
        } elseif (strlen($newPassword) < 8) {
            $actionMessage = "New password must be at least 8 characters long.";
            $actionMessageType = 'danger';
        } elseif ($newPassword !== $confirmPassword) {
            $actionMessage = "New passwords do not match.";
            $actionMessageType = 'danger';
        } else {
            // Access the database stores directly
            $dbPath = ROOT_DIR . '/db';
            $userStore = new \SleekDB\Store('users', $dbPath, [
                'auto_cache' => false,
                'timeout' => false
            ]);
            
            // Get user data including password hash
            $user = $userStore->findById($currentUser['_id']);
            
            if (!$user) {
                $actionMessage = "User not found. Please log in again.";
                $actionMessageType = 'danger';
            } elseif (!password_verify($currentPassword, $user['password_hash'])) {
                $actionMessage = "Current password is incorrect.";
                $actionMessageType = 'danger';
            } else {
                // Update the password
                $userData = [
                    'password_hash' => password_hash($newPassword, PASSWORD_BCRYPT),
                    'updated_at' => time()
                ];
                
                try {
                    $userStore->updateById($user['_id'], $userData);
                    $actionMessage = "Password updated successfully!";
                    $actionMessageType = 'success';
                    
                    // Log the action (reuse the function from admin pages)
                    logUserAction("Changed password");
                } catch (\Exception $e) {
                    $actionMessage = "Error updating password: " . $e->getMessage();
                    $actionMessageType = 'danger';
                }
            }
        }
    }
}

// Generate CSRF token for form
$csrfToken = auth()->generateCsrfToken();

/**
 * Log user actions
 * @param string $action The action to log
 * @param bool $success Whether the action was successful
 */
function logUserAction($action, $success = true) {
    $logFile = STORAGE_DIR . '/logs/user_activity.log';
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

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container-admin">
    <div class="page-header">
        <h1>Account Management</h1>
        <div>
            <span class="text-muted me-3">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></span>
            <a href="<?php echo site_url('profile'); ?>" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Back to Profile
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
        <!-- Sidebar with tabs - for future expansion -->
        <div class="col-md-3 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">Settings</h5>
                </div>
                <div class="list-group list-group-flush">
                    <a href="#password-tab" class="list-group-item list-group-item-action active" 
                       data-bs-toggle="tab" role="tab" aria-controls="password-tab" aria-selected="true">
                        <i class="bi bi-key"></i> Change Password
                    </a>
                    <!-- Additional tabs can be added here in the future -->
                    <!-- Example:
                    <a href="#profile-tab" class="list-group-item list-group-item-action" 
                       data-bs-toggle="tab" role="tab" aria-controls="profile-tab" aria-selected="false">
                        <i class="bi bi-person"></i> Profile Information
                    </a>
                    <a href="#notifications-tab" class="list-group-item list-group-item-action" 
                       data-bs-toggle="tab" role="tab" aria-controls="notifications-tab" aria-selected="false">
                        <i class="bi bi-bell"></i> Notification Settings
                    </a>
                    -->
                </div>
            </div>
            
            <!-- Account Info Card -->
            <div class="card mt-3">
                <div class="card-header">
                    <h5 class="mb-0">Account Information</h5>
                </div>
                <div class="card-body">
                    <p><strong>Username:</strong> <?php echo htmlspecialchars($currentUser['username']); ?></p>
                    <p><strong>Role:</strong> 
                        <span class="badge bg-<?php echo $currentUser['role'] === 'admin' ? 'danger' : 'success'; ?>">
                            <?php echo htmlspecialchars($currentUser['role']); ?>
                        </span>
                    </p>
                    <p><strong>Created:</strong> 
                        <?php echo isset($currentUser['created_at']) ? 
                              date('Y-m-d H:i', $currentUser['created_at']) : 'Unknown'; ?>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Main content with tab panels -->
        <div class="col-md-9">
            <div class="tab-content">
                <!-- Password Change Tab -->
                <div class="tab-pane fade show active" id="password-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">Change Password</h4>
                        </div>
                        <div class="card-body">
                            <form method="post" action="<?php echo site_url('account'); ?>">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrfToken; ?>">
                                
                                <div class="mb-3">
                                    <label for="current_password" class="form-label">Current Password:</label>
                                    <input type="password" class="form-control" id="current_password" name="current_password" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="new_password" class="form-label">New Password:</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                                    <div class="form-text">Password must be at least 8 characters long.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password:</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                                </div>
                                
                                <div class="mb-3">
                                    <button type="submit" name="change_password" class="btn btn-primary">Update Password</button>
                                </div>
                            </form>
                            
                            <div class="password-guidelines mt-4">
                                <h5>Password Guidelines</h5>
                                <ul class="list-unstyled">
                                    <li><i class="bi bi-check-circle text-success"></i> Use at least 8 characters</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Include uppercase and lowercase letters</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Include numbers and special characters</li>
                                    <li><i class="bi bi-check-circle text-success"></i> Avoid easily guessed information like birthdates</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Additional tab panels can be added here in the future -->
                <!-- Example:
                <div class="tab-pane fade" id="profile-tab" role="tabpanel">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="mb-0">Profile Information</h4>
                        </div>
                        <div class="card-body">
                            Profile form content would go here
                        </div>
                    </div>
                </div>
                -->
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Password validation
        const newPasswordInput = document.getElementById('new_password');
        const confirmPasswordInput = document.getElementById('confirm_password');
        const form = document.querySelector('form');
        
        form.addEventListener('submit', function(e) {
            if (newPasswordInput.value !== confirmPasswordInput.value) {
                alert('Passwords do not match!');
                e.preventDefault();
            }
        });
        
        // Handle tab navigation
        const tabLinks = document.querySelectorAll('.list-group-item');
        tabLinks.forEach(tabLink => {
            tabLink.addEventListener('click', function(e) {
                // Remove active class from all tabs
                tabLinks.forEach(link => link.classList.remove('active'));
                
                // Add active class to clicked tab
                this.classList.add('active');
            });
        });
    });
</script>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'Account Management';

// Add page-specific CSS (using the admin styles)
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/admin.css') .'?v=' . APP_VERSION . '">';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';
?>