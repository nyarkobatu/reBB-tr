<?php
/**
 * reBB - Form List Page
 * 
 * This file serves as the public view for a collection of forms.
 */

// Initialize variables
$list = null;
$listItems = [];
$listOwner = null;
$isOwner = false;
$errorMessage = '';

// Check if a list ID is provided
if (!isset($_GET['l']) || empty($_GET['l'])) {
    $errorMessage = 'No list specified. Please provide a valid list ID.';
} else {
    $listId = $_GET['l'];
    
    // Initialize the SleekDB store for form lists
    $dbPath = ROOT_DIR . '/db';
    
    try {
        $listStore = new \SleekDB\Store('form_lists', $dbPath, [
            'auto_cache' => false,
            'timeout' => false
        ]);
        
        $listItemStore = new \SleekDB\Store('form_list_items', $dbPath, [
            'auto_cache' => false,
            'timeout' => false
        ]);
        
        // Get the list information
        $list = $listStore->findOneBy([
            ['list_id', '=', $listId]
        ]);
        
        if (!$list) {
            $errorMessage = 'List not found. It may have been deleted or the ID is incorrect.';
        } else {
            // Check if the list is public or if the current user is the owner
            $isPublic = isset($list['is_public']) && $list['is_public'];
            
            if (auth()->isLoggedIn()) {
                $currentUser = auth()->getUser();
                $isOwner = ($currentUser['_id'] === $list['user_id']);
            }
            
            if (!$isPublic && !$isOwner) {
                $errorMessage = 'This list is private and can only be viewed by its owner.';
            } else {
                // Get the list items
                $listItems = $listItemStore->findBy([
                    ['list_id', '=', $listId]
                ], ['display_order' => 'asc']);
                
                // Load form information for each item
                foreach ($listItems as &$item) {
                    $formPath = STORAGE_DIR . '/forms/' . $item['form_id'] . '_schema.json';
                    
                    if (file_exists($formPath)) {
                        $formData = json_decode(file_get_contents($formPath), true);
                        $item['form_name'] = isset($formData['formName']) ? $formData['formName'] : 'Unnamed Form';
                        
                        // If there's a verified status, add it to the item
                        if (isset($formData['verified'])) {
                            $item['verified'] = $formData['verified'];
                        }
                        
                        // If there's a blacklisted status, add it to the item
                        if (isset($formData['blacklisted']) && !empty($formData['blacklisted'])) {
                            $item['blacklisted'] = $formData['blacklisted'];
                        }
                    } else {
                        $item['form_name'] = 'Unknown Form';
                        $item['not_found'] = true;
                    }
                }
                
                // Get the list owner's username
                if (isset($list['user_id'])) {
                    $userStore = new \SleekDB\Store('users', $dbPath, [
                        'auto_cache' => false,
                        'timeout' => false
                    ]);
                    
                    $owner = $userStore->findById($list['user_id']);
                    if ($owner) {
                        $listOwner = $owner['username'];
                    }
                }
                
                // Increment view count in analytics if available
                if (class_exists('Analytics')) {
                    $analytics = new Analytics();
                    if ($analytics->isEnabled()) {
                        $analytics->trackPageView('list_view_' . $listId);
                    }
                }
            }
        }
    } catch (\Exception $e) {
        $errorMessage = 'An error occurred while loading the list: ' . $e->getMessage();
    }
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="form-list-container">
    <?php if (!empty($errorMessage)): ?>
        <div class="list-error">
            <div class="list-error-icon">
                <i class="bi bi-exclamation-circle"></i>
            </div>
            <h2>List Not Available</h2>
            <p><?php echo htmlspecialchars($errorMessage); ?></p>
            <div class="list-error-actions">
                <a href="<?php echo site_url(); ?>" class="btn btn-primary">Go to Homepage</a>
                <?php if (auth()->isLoggedIn()): ?>
                    <a href="<?php echo site_url('lists'); ?>" class="btn btn-secondary">View My Lists</a>
                <?php endif; ?>
            </div>
        </div>
    <?php elseif ($list): ?>
        <div class="list-header">
            <h1><?php echo htmlspecialchars($list['list_name']); ?></h1>
            <?php if (!empty($list['list_description'])): ?>
                <p class="list-description"><?php echo htmlspecialchars($list['list_description']); ?></p>
            <?php endif; ?>
            <div class="list-meta">
                <?php if ($listOwner): ?>
                    <span class="list-owner">Created by <?php echo htmlspecialchars($listOwner); ?></span>
                <?php endif; ?>
                <span class="list-timestamp">Last updated <?php echo date('F j, Y', $list['updated_at']); ?></span>
                <?php if ($isOwner): ?>
                    <a href="<?php echo site_url('lists') . '?edit=' . htmlspecialchars($list['list_id']); ?>" class="btn btn-sm btn-outline-primary list-edit-btn">
                        <i class="bi bi-pencil"></i> Edit List
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <div class="list-content">
            <?php if (empty($listItems)): ?>
                <div class="list-empty">
                    <div class="list-empty-icon">
                        <i class="bi bi-clipboard-x"></i>
                    </div>
                    <h3>This list doesn't contain any forms yet</h3>
                    <?php if ($isOwner): ?>
                        <p>Add forms to your list by editing it</p>
                        <a href="<?php echo site_url('lists') . '?edit=' . htmlspecialchars($list['list_id']); ?>" class="btn btn-primary">
                            Edit List
                        </a>
                    <?php else: ?>
                        <p>The list owner hasn't added any forms yet</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <div class="list-items">
                    <?php foreach ($listItems as $index => $item): ?>
                        <div class="list-item <?php echo isset($item['not_found']) ? 'list-item-unavailable' : ''; ?>">
                            <div class="list-item-number"><?php echo $index + 1; ?></div>
                            <div class="list-item-content">
                                <h3 class="list-item-title">
                                    <?php echo htmlspecialchars($item['custom_title'] ?: $item['form_name']); ?>
                                    <?php if (isset($item['verified']) && $item['verified']): ?>
                                        <span class="verified-badge"><i class="bi bi-check-circle-fill"></i> Verified</span>
                                    <?php endif; ?>
                                </h3>
                                
                                <?php if (!empty($item['custom_description'])): ?>
                                    <div class="list-item-description">
                                        <?php echo htmlspecialchars($item['custom_description']); ?>
                                    </div>
                                <?php endif; ?>
                                
                                <?php if (isset($item['not_found'])): ?>
                                    <div class="form-unavailable">
                                        <i class="bi bi-exclamation-triangle"></i> This form is no longer available
                                    </div>
                                <?php elseif (isset($item['blacklisted']) && !empty($item['blacklisted'])): ?>
                                    <div class="form-blacklisted">
                                        <i class="bi bi-shield-exclamation"></i> This form has been restricted by the administrator
                                    </div>
                                <?php else: ?>
                                    <div class="list-item-actions">
                                        <a href="<?php echo site_url('form') . '?f=' . htmlspecialchars($item['form_id']); ?>" class="btn btn-primary list-item-button">
                                            <i class="bi bi-box-arrow-up-right"></i> Open Form
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        
        <div class="list-footer">
            <div class="list-share">
                <h3>Share this List</h3>
                <div class="share-input-group">
                    <input type="text" id="share-url" class="form-control" value="<?php echo site_url('list') . '?l=' . htmlspecialchars($list['list_id']); ?>" readonly>
                    <button class="btn btn-primary" id="copy-share-url">
                        <i class="bi bi-clipboard"></i> Copy
                    </button>
                </div>
            </div>
            
            <div class="list-nav-links">
                <a href="<?php echo site_url(); ?>" class="btn btn-outline-secondary">
                    <i class="bi bi-house"></i> Home
                </a>
                
                <?php if (auth()->isLoggedIn()): ?>
                    <a href="<?php echo site_url('lists'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-list-ul"></i> My Lists
                    </a>
                <?php else: ?>
                    <a href="<?php echo site_url('login'); ?>" class="btn btn-outline-primary">
                        <i class="bi bi-person"></i> Sign In
                    </a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Copy share URL functionality
    const copyButton = document.getElementById('copy-share-url');
    const shareUrl = document.getElementById('share-url');
    
    if (copyButton && shareUrl) {
        copyButton.addEventListener('click', function() {
            // Select the URL
            shareUrl.select();
            shareUrl.setSelectionRange(0, 99999); // For mobile devices
            
            // Copy the URL
            document.execCommand('copy');
            
            // Change button text temporarily
            const originalHTML = this.innerHTML;
            this.innerHTML = '<i class="bi bi-check"></i> Copied!';
            
            // Revert back after a delay
            setTimeout(() => {
                this.innerHTML = originalHTML;
            }, 2000);
        });
    }
});
</script>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = $list ? $list['list_name'] . ' - Form List' : 'Form List Not Found';

// Add page-specific CSS
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/list.css') .'?v=' . APP_VERSION . '">';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';