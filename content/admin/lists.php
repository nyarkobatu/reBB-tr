<?php
/**
 * reBB - Admin Form Lists Management
 * 
 * This file provides an interface for admins to manage all form lists in the system.
 * This page requires authentication with admin role.
 */

// Require admin authentication before processing anything else
auth()->requireRole('admin', 'login');

// Since we've passed the auth check, we can safely get the current user
$currentUser = auth()->getUser();

// Initialize variables
$actionMessage = '';
$actionMessageType = 'info';
$allLists = [];

/**
 * Log admin actions
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

// Handle list deletion
if (isset($_POST['delete_list']) && isset($_POST['list_id'])) {
    $listId = $_POST['list_id'];
    
    try {
        // Initialize the SleekDB store for form lists
        $dbPath = ROOT_DIR . '/db';
        
        // Make sure DB directory exists
        if (!is_dir($dbPath)) {
            mkdir($dbPath, 0755, true);
        }
        
        $listStore = new \SleekDB\Store('form_lists', $dbPath, [
            'auto_cache' => false,
            'timeout' => false
        ]);
        
        $listItemStore = new \SleekDB\Store('form_list_items', $dbPath, [
            'auto_cache' => false,
            'timeout' => false
        ]);
        
        // Find the list
        $list = $listStore->findOneBy([
            ['list_id', '=', $listId]
        ]);
        
        if ($list) {
            // Get owner username
            $userStore = new \SleekDB\Store('users', $dbPath, [
                'auto_cache' => false,
                'timeout' => false
            ]);
            
            $owner = $userStore->findById($list['user_id'] ?? '');
            $ownerUsername = $owner ? $owner['username'] : 'Unknown';
            
            // Delete all list items first
            $listItems = $listItemStore->findBy([
                ['list_id', '=', $listId]
            ]);
            
            foreach ($listItems as $item) {
                $listItemStore->deleteById($item['_id']);
            }
            
            // Delete the list
            $listStore->deleteById($list['_id']);
            
            $actionMessage = "List '{$list['list_name']}' by user '$ownerUsername' has been deleted successfully.";
            $actionMessageType = 'success';
            logAdminAction("Deleted list: {$listId} (name: {$list['list_name']}) by user: $ownerUsername");
        } else {
            $actionMessage = "List not found.";
            $actionMessageType = 'danger';
            logAdminAction("Failed to delete list: $listId - List not found", false);
        }
    } catch (\Exception $e) {
        $actionMessage = "Error deleting list: " . $e->getMessage();
        $actionMessageType = 'danger';
        logAdminAction("Error deleting list: $listId - " . $e->getMessage(), false);
    }
}

// Get all lists
try {
    // Initialize the SleekDB store for form lists
    $dbPath = ROOT_DIR . '/db';
    
    // Make sure DB directory exists
    if (!is_dir($dbPath)) {
        mkdir($dbPath, 0755, true);
    }
    
    $listStore = new \SleekDB\Store('form_lists', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    $listItemStore = new \SleekDB\Store('form_list_items', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    $userStore = new \SleekDB\Store('users', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    // Get all lists
    $allLists = $listStore->findAll(['created_at' => 'desc']);
    
    // Add owner name and item count to each list
    foreach ($allLists as &$list) {
        // Get owner
        $owner = $userStore->findById($list['user_id'] ?? '');
        $list['owner_username'] = $owner ? $owner['username'] : 'Unknown';
        
        // Count items
        $listItems = $listItemStore->findBy([
            ['list_id', '=', $list['list_id']]
        ]);
        $list['item_count'] = count($listItems);
    }
} catch (\Exception $e) {
    $actionMessage = "Error loading lists: " . $e->getMessage();
    $actionMessageType = 'danger';
    logAdminAction("Error loading lists: " . $e->getMessage(), false);
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container-admin">
    <div class="page-header">
        <h1>Form Lists Management</h1>
        <div>
            <span class="text-muted me-3">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></span>
            <a href="<?php echo site_url('admin'); ?>" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-left"></i> Back to Admin
            </a>
            <a href="?" class="btn btn-outline-secondary me-2">
                <i class="bi bi-arrow-clockwise"></i> Refresh
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
    
    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="stat-value"><?php echo count($allLists); ?></div>
                    <div class="stat-label">Total Form Lists</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="stat-value">
                        <?php 
                            $publicLists = 0;
                            foreach ($allLists as $list) {
                                if (isset($list['is_public']) && $list['is_public']) {
                                    $publicLists++;
                                }
                            }
                            echo $publicLists;
                        ?>
                    </div>
                    <div class="stat-label">Public Lists</div>
                </div>
            </div>
        </div>
        <div class="col-md-4 mb-3 mb-md-0">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="stat-value">
                        <?php
                            $totalItems = 0;
                            foreach ($allLists as $list) {
                                $totalItems += $list['item_count'];
                            }
                            echo $totalItems;
                        ?>
                    </div>
                    <div class="stat-label">Total Form References</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Lists Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">All Form Lists</h4>
        </div>
        <div class="card-body">
            <!-- Search box -->
            <div class="search-box">
                <input type="text" id="listSearch" class="form-control" placeholder="Search lists by name or owner...">
            </div>
            
            <?php if (empty($allLists)): ?>
                <div class="text-center my-5">
                    <i class="bi bi-collection" style="font-size: 3rem; color: #ccc;"></i>
                    <p class="mt-3">No form lists have been created yet.</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>List ID</th>
                                <th>List Name</th>
                                <th>Owner</th>
                                <th>Created</th>
                                <th>Updated</th>
                                <th>Forms</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="listsList">
                            <?php foreach ($allLists as $list): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($list['list_id']); ?></td>
                                    <td class="truncate"><?php echo htmlspecialchars($list['list_name']); ?></td>
                                    <td><?php echo htmlspecialchars($list['owner_username']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', $list['created_at']); ?></td>
                                    <td><?php echo date('Y-m-d H:i', $list['updated_at']); ?></td>
                                    <td><?php echo $list['item_count']; ?></td>
                                    <td>
                                        <?php if (isset($list['is_public']) && $list['is_public']): ?>
                                            <span class="badge bg-success">Public</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Private</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="<?php echo site_url('list') . '?l=' . htmlspecialchars($list['list_id']); ?>" class="btn btn-sm btn-outline-primary me-1" target="_blank">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-danger delete-list-btn" 
                                                data-id="<?php echo htmlspecialchars($list['list_id']); ?>"
                                                data-name="<?php echo htmlspecialchars($list['list_name']); ?>"
                                                data-owner="<?php echo htmlspecialchars($list['owner_username']); ?>">
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
    
    <!-- Delete List Confirmation Modal -->
    <div class="modal fade" id="deleteListModal" tabindex="-1" aria-labelledby="deleteListModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="deleteListModalLabel">Confirm List Deletion</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this list?</p>
                    <div class="alert alert-info">
                        <strong>List Name:</strong> <span id="list-name-to-delete"></span><br>
                        <strong>Owner:</strong> <span id="list-owner-to-delete"></span><br>
                        <strong>ID:</strong> <span id="list-id-display"></span>
                    </div>
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> This action cannot be undone and will permanently remove the list and all its contents.
                    </div>
                </div>
                <div class="modal-footer">
                    <form method="post" action="lists">
                        <input type="hidden" name="list_id" id="list-id-to-delete">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_list" class="btn btn-danger">Delete List</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Delete list confirmation
    const deleteButtons = document.querySelectorAll('.delete-list-btn');
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const listId = this.getAttribute('data-id');
            const listName = this.getAttribute('data-name');
            const listOwner = this.getAttribute('data-owner');
            
            document.getElementById('list-id-to-delete').value = listId;
            document.getElementById('list-id-display').textContent = listId;
            document.getElementById('list-name-to-delete').textContent = listName;
            document.getElementById('list-owner-to-delete').textContent = listOwner;
            
            const deleteListModal = new bootstrap.Modal(document.getElementById('deleteListModal'));
            deleteListModal.show();
        });
    });
    
    // Search functionality
    const searchBox = document.getElementById('listSearch');
    if (searchBox) {
        searchBox.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = document.querySelectorAll('#listsList tr');
            
            rows.forEach(row => {
                const listName = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                const owner = row.querySelector('td:nth-child(3)').textContent.toLowerCase();
                const listId = row.querySelector('td:first-child').textContent.toLowerCase();
                
                if (listName.includes(searchTerm) || owner.includes(searchTerm) || listId.includes(searchTerm)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    }
});
</script>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'Form Lists Management';

// Add page-specific CSS (using the admin styles)
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/admin.css') .'?v=' . APP_VERSION . '">';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';
?>