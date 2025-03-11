<?php
/**
 * reBB - Setup Page
 * 
 * This file handles the initial setup of the application.
 * It only works if no users exist in the system.
 */

// Helper class for setup operations
class SetupHelper {
    /**
     * Recursively copy files from one directory to another
     * 
     * @param string $src Source directory
     * @param string $dst Destination directory
     * @return void
     */
    public function recursiveCopy($src, $dst) {
        $dir = opendir($src);
        if (!is_dir($dst)) {
            mkdir($dst, 0755, true);
        }
        
        while (($file = readdir($dir)) !== false) {
            if (($file != '.') && ($file != '..')) {
                if (is_dir($src . '/' . $file)) {
                    $this->recursiveCopy($src . '/' . $file, $dst . '/' . $file);
                } else {
                    copy($src . '/' . $file, $dst . '/' . $file);
                }
            }
        }
        
        closedir($dir);
    }
    
    /**
     * Recursively delete a directory and its contents
     * 
     * @param string $dir Directory to delete
     * @return bool True on success
     */
    public function recursiveDelete($dir) {
        if (!is_dir($dir)) {
            return false;
        }
        
        $files = array_diff(scandir($dir), array('.', '..'));
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            
            if (is_dir($path)) {
                $this->recursiveDelete($path);
            } else {
                unlink($path);
            }
        }
        
        return rmdir($dir);
    }
}

// Handle form submission
$setupComplete = false;
$setupMessage = '';
$setupError = '';

if (isset($_POST['setup_submit'])) {
    // Initialize the setup helper
    $setupHelper = new SetupHelper();
    
    try {
        // Create required directories if they don't exist
        $directories = [
            ROOT_DIR . '/lib',
            STORAGE_DIR . '/logs',
            STORAGE_DIR . '/forms',
            STORAGE_DIR . '/documentation',
            STORAGE_DIR . '/analytics',
            ROOT_DIR . '/db'
        ];
        
        foreach ($directories as $dir) {
            if (!is_dir($dir)) {
                if (!mkdir($dir, 0755, true)) {
                    throw new Exception("Failed to create directory: $dir");
                }
            }
        }
        
        // Install Parsedown.php
        $parsedownUrl = 'https://raw.githubusercontent.com/erusev/parsedown/master/Parsedown.php';
        $parsedownPath = ROOT_DIR . '/lib/Parsedown.php';
        
        $parsedownContent = @file_get_contents($parsedownUrl);
        if ($parsedownContent === false) {
            throw new Exception("Failed to download Parsedown.php from $parsedownUrl");
        }
        
        if (file_put_contents($parsedownPath, $parsedownContent) === false) {
            throw new Exception("Failed to write Parsedown.php to $parsedownPath");
        }
        
        // Check if SleekDB is already installed via Composer
        $sleekDBClassPath = ROOT_DIR . '/vendor/rakibtg/sleekdb/src/Store.php';
        $sleekDBPathExists = file_exists($sleekDBClassPath);
        
        // Only install SleekDB manually if it's not available via Composer
        if (!$sleekDBPathExists && !class_exists('\SleekDB\Store')) {
            // SleekDB not installed via Composer, install manually
            $sleekDBUrl = 'https://github.com/rakibtg/SleekDB/archive/refs/heads/master.zip';
            $sleekDBZipPath = ROOT_DIR . '/lib/sleekdb.zip';
            $sleekDBExtractPath = ROOT_DIR . '/lib/';
            $sleekDBTempPath = ROOT_DIR . '/lib/SleekDB-temp/';
            
            // Make sure the temp directory exists
            if (!is_dir($sleekDBTempPath)) {
                mkdir($sleekDBTempPath, 0755, true);
            }
            
            $sleekDBContent = @file_get_contents($sleekDBUrl);
            if ($sleekDBContent === false) {
                throw new Exception("Failed to download SleekDB from $sleekDBUrl");
            }
            
            if (file_put_contents($sleekDBZipPath, $sleekDBContent) === false) {
                throw new Exception("Failed to write SleekDB zip to $sleekDBZipPath");
            }
            
            // Extract the SleekDB zip file to a temporary directory
            $zip = new ZipArchive;
            if ($zip->open($sleekDBZipPath) === TRUE) {
                $zip->extractTo($sleekDBTempPath);
                $zip->close();
                
                // Create the SleekDB directory
                if (!is_dir($sleekDBExtractPath . 'SleekDB')) {
                    mkdir($sleekDBExtractPath . 'SleekDB', 0755, true);
                }
                
                // Copy only the src directory to the final location
                if (is_dir($sleekDBTempPath . 'SleekDB-master/src')) {
                    // Use recursive copy to copy the src directory
                    $setupHelper->recursiveCopy(
                        $sleekDBTempPath . 'SleekDB-master/src', 
                        $sleekDBExtractPath . 'SleekDB/'
                    );
                } else {
                    throw new Exception("SleekDB src directory not found in the downloaded package");
                }
                
                // Clean up: remove the temporary directory and zip file
                $setupHelper->recursiveDelete($sleekDBTempPath);
                @unlink($sleekDBZipPath);
            } else {
                throw new Exception("Failed to extract SleekDB zip file");
            }
        }
        
        // Create admin user account with SleekDB
        if (!empty($_POST['admin_username']) && !empty($_POST['admin_password'])) {
            $username = $_POST['admin_username'];
            $password = $_POST['admin_password'];
            
            // Load auth system now that SleekDB is available
            if (!class_exists('Auth')) {
                require_once ROOT_DIR . '/core/auth.php';
            }
            
            // Use the Auth system to create the admin user
            $adminUser = auth()->register($username, $password, ['role' => 'admin']);
            
            if (!$adminUser) {
                throw new Exception("Failed to create admin user account. Please check username and password requirements.");
            }
            
            // Auto-login the admin
            auth()->login($username, $password);
        }
        
        // Create a config.php file if it doesn't exist
        if (!file_exists(ROOT_DIR . '/includes/config.php')) {
            $configExamplePath = ROOT_DIR . '/includes/config.example.php';
            $configPath = ROOT_DIR . '/includes/config.php';
            
            if (file_exists($configExamplePath)) {
                $configContent = file_get_contents($configExamplePath);
                
                // Update site URL based on current URL
                $siteUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]";
                $currentPath = dirname($_SERVER['REQUEST_URI']);
                if ($currentPath != '/' && $currentPath != '\\') {
                    $siteUrl .= $currentPath;
                }
                
                // Replace site URL in config
                $configContent = preg_replace(
                    "/define\('SITE_URL',\s*'[^']*'\);/", 
                    "define('SITE_URL', '$siteUrl');", 
                    $configContent
                );
                
                if (file_put_contents($configPath, $configContent) === false) {
                    throw new Exception("Failed to create config.php file");
                }
            }
        }
        
        $setupComplete = true;
        $setupMessage = "Setup completed successfully! You are now logged in as admin. You can <a href='admin'>access the admin panel</a> or <a href='".site_url()."'>go to the homepage</a>.";
        
    } catch (Exception $e) {
        $setupError = "Setup failed: " . $e->getMessage();
    }
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container">
    <div class="setup-container" style="max-width: 700px; margin: 5rem auto;">
        <div class="card">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">reBB - System Setup</h3>
            </div>
            <div class="card-body">
                <?php if ($setupComplete): ?>
                    <div class="alert alert-success">
                        <?php echo $setupMessage; ?>
                    </div>
                <?php elseif ($setupError): ?>
                    <div class="alert alert-danger">
                        <?php echo $setupError; ?>
                    </div>
                <?php else: ?>
                    <p>Welcome to the reBB setup page. This will help you initialize your system by:</p>
                    
                    <ul>
                        <li>Creating required directories</li>
                        <li>Installing the required dependencies</li>
                        <li>Setting up the authentication database</li>
                        <li>Creating an admin account</li>
                    </ul>
                    
                    <form method="post" action="setup">
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">Admin Account Setup</h5>
                            </div>
                            <div class="card-body">
                                <div class="form-group mb-3">
                                    <label for="admin_username">Admin Username:</label>
                                    <input type="text" class="form-control" id="admin_username" name="admin_username" 
                                           required pattern="[a-zA-Z0-9_]{3,20}" 
                                           title="Username must be 3-20 characters and contain only letters, numbers, and underscores.">
                                    <small class="form-text text-muted">3-20 characters, letters, numbers, and underscores only.</small>
                                </div>
                                <div class="form-group mb-3">
                                    <label for="admin_password">Admin Password:</label>
                                    <input type="password" class="form-control" id="admin_password" name="admin_password" required minlength="8">
                                    <small class="form-text text-muted">Choose a strong password with at least 8 characters.</small>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card mb-4">
                            <div class="card-header">
                                <h5 class="mb-0">System Requirements Check</h5>
                            </div>
                            <div class="card-body">
                                <ul class="list-group">
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        PHP Version 
                                        <?php if (version_compare(PHP_VERSION, '7.4.0', '>=')): ?>
                                            <span class="badge bg-success">OK</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Needs PHP 7.4+</span>
                                        <?php endif; ?>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        ZipArchive Extension
                                        <?php if (class_exists('ZipArchive')): ?>
                                            <span class="badge bg-success">OK</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Not Available</span>
                                        <?php endif; ?>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        Directory Permissions
                                        <?php if (is_writable(ROOT_DIR)): ?>
                                            <span class="badge bg-success">OK</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Not Writable</span>
                                        <?php endif; ?>
                                    </li>
                                    <li class="list-group-item d-flex justify-content-between align-items-center">
                                        SleekDB Availability
                                        <?php if (class_exists('\SleekDB\Store')): ?>
                                            <span class="badge bg-success">OK</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Will Install</span>
                                        <?php endif; ?>
                                    </li>
                                </ul>
                            </div>
                        </div>
                        
                        <button type="submit" name="setup_submit" class="btn btn-primary btn-block"
                            <?php if (!class_exists('ZipArchive') || !is_writable(ROOT_DIR) || version_compare(PHP_VERSION, '7.4.0', '<')): ?>
                                disabled
                            <?php endif; ?>
                        >
                            Complete Setup
                        </button>
                        
                        <?php if (!class_exists('ZipArchive') || !is_writable(ROOT_DIR) || version_compare(PHP_VERSION, '7.4.0', '<')): ?>
                            <div class="alert alert-warning mt-3">
                                <strong>Warning:</strong> Your system doesn't meet all the requirements for setup. Please fix the issues marked above.
                            </div>
                        <?php endif; ?>
                    </form>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center text-muted">
                <?php echo SITE_NAME; ?> <?php echo APP_VERSION; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'System Setup';

// Add page-specific CSS
$GLOBALS['page_css'] = '<style>
.setup-container .card {
    border-radius: 10px;
    box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
}
.setup-container .card-header {
    border-top-left-radius: 10px;
    border-top-right-radius: 10px;
}
.setup-container .btn-block {
    display: block;
    width: 100%;
}
</style>';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';