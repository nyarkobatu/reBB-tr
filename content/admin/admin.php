<?php
/**
 * reBB - Admin Page
 * 
 * This file serves the administrative interface for the app.
 * This page requires authentication with admin role.
 */

// Require admin authentication before processing anything else
auth()->requireRole('admin', 'login');

// Since we've passed the auth check, we can safely get the current user
$currentUser = auth()->getUser();

// Initialize variables
$actionMessage = '';
$forms = [];
$stats = [
    'total_forms' => 0,
    'recent_forms' => 0,
    'total_size' => 0
];

// Handle form deletion
if (isset($_POST['delete_form']) && isset($_POST['form_id'])) {
    $formId = $_POST['form_id'];
    $filename = STORAGE_DIR . '/forms/' . $formId . '_schema.json';
    
    if (file_exists($filename) && is_readable($filename) && unlink($filename)) {
        $actionMessage = "Form $formId has been deleted successfully.";
        logAdminAction("Deleted form: $formId");
    } else {
        $actionMessage = "Error: Unable to delete form $formId.";
        logAdminAction("Failed to delete form: $formId", false);
    }
}

// Get form data since we're authenticated
$formsDir = STORAGE_DIR . '/forms';

if (is_dir($formsDir)) {
    $files = scandir($formsDir);
    
    foreach ($files as $file) {
        if ($file === '.' || $file === '..' || !str_ends_with($file, '_schema.json')) {
            continue;
        }
        
        $filePath = $formsDir . '/' . $file;
        $fileSize = filesize($filePath);
        $stats['total_size'] += $fileSize;
        
        // Extract form data (add this inside the loop where forms are being processed)
        $formId = str_replace('_schema.json', '', $file);
        $creationTime = filectime($filePath);
        $modificationTime = filemtime($filePath);

        // Try to get form name and creator info from the file
        $formName = "";
        $createdById = null;
        $fileContent = file_get_contents($filePath);
        $formData = json_decode($fileContent, true);
        if ($formData) {
            if (isset($formData['formName'])) {
                $formName = $formData['formName'];
            }
            if (isset($formData['createdBy'])) {
                $createdById = $formData['createdBy'];
            }
        }

        $forms[] = [
            'id' => $formId,
            'name' => $formName,
            'created' => $creationTime,
            'modified' => $modificationTime,
            'size' => $fileSize,
            'url' => site_url('form') . '?f=' . $formId,
            'createdBy' => $createdById
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

// Define the page content to be yielded in the master layout
ob_start();
?>

<!-- Admin dashboard - Using the special admin container class -->
<div class="container-admin">
    <div class="page-header">
        <h1>Admin Dashboard</h1>
        <div>
            <span class="text-muted me-3">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></span>
            <a href="<?php echo site_url('analytics'); ?>" class="btn btn-primary"><i class="bi bi-graph-up"></i> View Analytics</a>
            <a href="?" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
            <a href="<?php echo site_url('logout'); ?>" class="btn btn-outline-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
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
            <a href="builder" class="btn btn-sm btn-success">
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
                    <a href="builder" class="btn btn-primary">Create your first form</a>
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
                                        
                                        <?php if (isset($form['createdBy']) && $form['createdBy'] === $currentUser['_id'] || $currentUser['role'] === 'admin'): ?>
                                            <a href="<?php echo site_url('edit/' . htmlspecialchars($form['id'])); ?>" class="btn btn-sm btn-outline-secondary">
                                                <i class="bi bi-pencil"></i> Edit
                                            </a>
                                        <?php else: ?>
                                            <a href="builder?f=<?php echo htmlspecialchars($form['id']); ?>" class="btn btn-sm btn-outline-secondary" target="_blank">
                                                <i class="bi bi-pencil"></i> Use as Template
                                            </a>
                                        <?php endif; ?>
                                        
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
                    <form method="post" action="admin">
                        <input type="hidden" name="form_id" id="formIdToDelete">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_form" class="btn btn-danger">Delete</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'Admin Panel';

// Add page-specific CSS
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/admin.css') .'?v=' . APP_VERSION . '">';

// Add page-specific JavaScript
$GLOBALS['page_javascript'] = '<script src="'. asset_path('js/app/admin.js') .'?v=' . APP_VERSION . '"></script>';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';