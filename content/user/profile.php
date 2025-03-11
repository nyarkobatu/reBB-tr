<?php
/**
 * reBB - User Profile/Dashboard
 * 
 * This file serves as the user dashboard for viewing and managing personal forms.
 * Users must be logged in to access this page.
 */

// Require authentication before processing anything else
auth()->requireAuth('login');

// Since we've passed the auth check, we can safely get the current user
$currentUser = auth()->getUser();

// Initialize variables
$actionMessage = '';
$actionMessageType = 'info';
$userForms = [];

// Access the forms database
try {
    // Initialize the SleekDB store for user forms
    $dbPath = ROOT_DIR . '/db';
    $userFormsStore = new \SleekDB\Store('user_forms', $dbPath, [
        'auto_cache' => false,
        'timeout' => false
    ]);
    
    // Fetch all forms for this user
    $userForms = $userFormsStore->findBy([
        ['user_id', '=', $currentUser['_id']]
    ]);
    
    // Sort by last updated (newest first)
    usort($userForms, function($a, $b) {
        return $b['last_updated'] - $a['last_updated'];
    });
    
    // Fetch additional data for each form from the schema files
    foreach ($userForms as $key => $form) {
        $formId = $form['form_id'];
        $schemaPath = STORAGE_DIR . '/forms/' . $formId . '_schema.json';
        
        if (file_exists($schemaPath)) {
            $schemaData = json_decode(file_get_contents($schemaPath), true);
            
            // Add additional data from schema file
            $userForms[$key]['style'] = $schemaData['formStyle'] ?? 'default';
            $userForms[$key]['created'] = $schemaData['created'] ?? $form['created_at'];
            $userForms[$key]['updated'] = $schemaData['updated'] ?? $form['last_updated'];
            
            // Check file size
            $userForms[$key]['size'] = filesize($schemaPath);
        } else {
            // Mark as potentially missing file
            $userForms[$key]['missing_file'] = true;
        }
    }
} catch (\Exception $e) {
    $actionMessage = "Error fetching your forms: " . $e->getMessage();
    $actionMessageType = 'danger';
}

// Get form usage statistics if analytics is enabled
$formStats = [];
if (defined('ENABLE_ANALYTICS') && ENABLE_ANALYTICS && defined('TRACK_FORM_USAGE') && TRACK_FORM_USAGE) {
    try {
        $analytics = new Analytics();
        if ($analytics->isEnabled()) {
            // Get popular forms data from analytics
            $allForms = $analytics->getPopularForms(100); // Get a large number to ensure we get all forms
            
            // Extract stats for the user's forms
            foreach ($userForms as $key => $form) {
                $formId = $form['form_id'];
                if (isset($allForms[$formId])) {
                    $userForms[$key]['views'] = $allForms[$formId]['views'];
                    $userForms[$key]['submissions'] = $allForms[$formId]['submissions'];
                    
                    // Calculate conversion rate
                    if ($allForms[$formId]['views'] > 0) {
                        $userForms[$key]['conversion'] = round(($allForms[$formId]['submissions'] / $allForms[$formId]['views']) * 100, 1);
                    } else {
                        $userForms[$key]['conversion'] = 0;
                    }
                } else {
                    $userForms[$key]['views'] = 0;
                    $userForms[$key]['submissions'] = 0;
                    $userForms[$key]['conversion'] = 0;
                }
            }
        }
    } catch (\Exception $e) {
        // Silently handle analytics errors - analytics are optional
    }
}

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container-admin">
    <div class="page-header">
        <h1>My Forms Dashboard</h1>
        <div>
            <span class="text-muted me-3">Welcome, <?php echo htmlspecialchars($currentUser['username']); ?></span>
            <a href="<?php echo site_url('builder'); ?>" class="btn btn-success me-2">
                <i class="bi bi-plus-circle"></i> Create New Form
            </a>
            <a href="<?php echo site_url(); ?>" class="btn btn-outline-secondary me-2">
                <i class="bi bi-house"></i> Home
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
                    <div class="stat-value"><?php echo count($userForms); ?></div>
                    <div class="stat-label">My Forms</div>
                </div>
            </div>
        </div>
        <?php if (defined('ENABLE_ANALYTICS') && ENABLE_ANALYTICS): ?>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="stat-value">
                            <?php 
                                $totalViews = 0;
                                foreach ($userForms as $form) {
                                    $totalViews += isset($form['views']) ? $form['views'] : 0;
                                }
                                echo $totalViews;
                            ?>
                        </div>
                        <div class="stat-label">Total Form Views</div>
                    </div>
                </div>
            </div>
            <div class="col-md-4 mb-3 mb-md-0">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="stat-value">
                            <?php 
                                $totalSubmissions = 0;
                                foreach ($userForms as $form) {
                                    $totalSubmissions += isset($form['submissions']) ? $form['submissions'] : 0;
                                }
                                echo $totalSubmissions;
                            ?>
                        </div>
                        <div class="stat-label">Total Form Submissions</div>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div class="col-md-8 mb-3 mb-md-0">
                <div class="card stats-card h-100">
                    <div class="card-body">
                        <div class="stat-value">
                            <?php 
                                $recentForms = 0;
                                $oneDayAgo = time() - (24 * 60 * 60);
                                foreach ($userForms as $form) {
                                    if (isset($form['created']) && $form['created'] > $oneDayAgo) {
                                        $recentForms++;
                                    }
                                }
                                echo $recentForms;
                            ?>
                        </div>
                        <div class="stat-label">Forms Created (Last 24h)</div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Forms Table -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">My Forms</h4>
            <a href="<?php echo site_url('builder'); ?>" class="btn btn-sm btn-success">
                <i class="bi bi-plus-circle"></i> Create New Form
            </a>
        </div>
        <div class="card-body">
            <!-- Search box -->
            <div class="search-box">
                <input type="text" id="formSearch" class="form-control" placeholder="Search my forms...">
            </div>
            
            <?php if (empty($userForms)): ?>
                <div class="form-list-empty">
                    <p><i class="bi bi-clipboard-check"></i></p>
                    <p>You haven't created any forms yet.</p>
                    <a href="<?php echo site_url('builder'); ?>" class="btn btn-primary">Create your first form</a>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>Form Name</th>
                                <th>Form ID</th>
                                <th>Created</th>
                                <th>Last Updated</th>
                                <?php if (defined('ENABLE_ANALYTICS') && ENABLE_ANALYTICS): ?>
                                    <th>Views</th>
                                    <th>Submissions</th>
                                    <th>Conversion</th>
                                <?php endif; ?>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="formsList">
                            <?php foreach ($userForms as $form): ?>
                                <?php 
                                    // Skip forms with missing files
                                    if (isset($form['missing_file']) && $form['missing_file'] === true) {
                                        continue;
                                    }
                                ?>
                                <tr>
                                    <td class="truncate"><?php echo htmlspecialchars($form['form_name'] ?: 'Unnamed Form'); ?></td>
                                    <td class="truncate"><?php echo htmlspecialchars($form['form_id']); ?></td>
                                    <td><?php echo isset($form['created']) ? date('Y-m-d H:i:s', $form['created']) : 'Unknown'; ?></td>
                                    <td><?php echo isset($form['last_updated']) ? date('Y-m-d H:i:s', $form['last_updated']) : 'Unknown'; ?></td>
                                    <?php if (defined('ENABLE_ANALYTICS') && ENABLE_ANALYTICS): ?>
                                        <td><?php echo isset($form['views']) ? number_format($form['views']) : '0'; ?></td>
                                        <td><?php echo isset($form['submissions']) ? number_format($form['submissions']) : '0'; ?></td>
                                        <td>
                                            <?php if (isset($form['conversion'])): ?>
                                                <span class="<?php echo $form['conversion'] > 50 ? 'conversion-high' : ($form['conversion'] > 20 ? 'conversion-medium' : 'conversion-low'); ?>">
                                                    <?php echo $form['conversion']; ?>%
                                                </span>
                                            <?php else: ?>
                                                0%
                                            <?php endif; ?>
                                        </td>
                                    <?php endif; ?>
                                    <td class="form-actions">
                                        <a href="<?php echo site_url('form?f=') . htmlspecialchars($form['form_id']); ?>" class="btn btn-sm btn-outline-primary" target="_blank">
                                            <i class="bi bi-eye"></i> View
                                        </a>
                                        <a href="<?php echo site_url('edit?f=') . htmlspecialchars($form['form_id']); ?>" class="btn btn-sm btn-outline-secondary">
                                            <i class="bi bi-pencil"></i> Edit
                                        </a>
                                        <button type="button" class="btn btn-sm btn-outline-info" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#shareModal" 
                                                data-formid="<?php echo htmlspecialchars($form['form_id']); ?>"
                                                data-formname="<?php echo htmlspecialchars($form['form_name'] ?: 'Unnamed Form'); ?>">
                                            <i class="bi bi-share"></i> Share
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
    
    <!-- Share Form Modal -->
    <div class="modal fade" id="shareModal" tabindex="-1" aria-labelledby="shareModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="shareModalLabel">Share Form</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Share this link with others to let them use your form:</p>
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="shareLink" readonly>
                        <button class="btn btn-outline-primary" type="button" id="copyShareLink">
                            <i class="bi bi-clipboard"></i> Copy
                        </button>
                    </div>
                    <div class="mt-3">
                        <p><strong>QR Code:</strong></p>
                        <div class="text-center">
                            <img id="qrCode" src="" alt="QR Code" class="img-fluid" style="max-width: 200px;">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Form search functionality
        const searchBox = document.getElementById('formSearch');
        if (searchBox) {
            searchBox.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                const rows = document.querySelectorAll('#formsList tr');
                
                rows.forEach(row => {
                    const formName = row.querySelector('td:first-child').textContent.toLowerCase();
                    const formId = row.querySelector('td:nth-child(2)').textContent.toLowerCase();
                    
                    if (formName.includes(searchTerm) || formId.includes(searchTerm)) {
                        row.style.display = '';
                    } else {
                        row.style.display = 'none';
                    }
                });
            });
        }
        
        // Share modal handler
        const shareModal = document.getElementById('shareModal');
        if (shareModal) {
            shareModal.addEventListener('show.bs.modal', function (event) {
                const button = event.relatedTarget;
                const formId = button.getAttribute('data-formid');
                const formName = button.getAttribute('data-formname');
                
                const modalTitle = shareModal.querySelector('.modal-title');
                const shareLink = document.getElementById('shareLink');
                const qrCode = document.getElementById('qrCode');
                
                // Update modal content
                modalTitle.textContent = 'Share: ' + formName;
                
                // Build the share URL
                const shareUrl = `${window.location.origin}/form?f=${formId}`;
                shareLink.value = shareUrl;
                
                // Generate QR code URL (using Google Charts API)
                qrCode.src = `https://chart.googleapis.com/chart?cht=qr&chl=${encodeURIComponent(shareUrl)}&chs=200x200&chld=L|0`;
            });
            
            // Copy share link button
            const copyShareLinkBtn = document.getElementById('copyShareLink');
            if (copyShareLinkBtn) {
                copyShareLinkBtn.addEventListener('click', function() {
                    const shareLink = document.getElementById('shareLink');
                    shareLink.select();
                    document.execCommand('copy');
                    
                    // Show feedback
                    const originalHTML = this.innerHTML;
                    this.innerHTML = '<i class="bi bi-check"></i> Copied!';
                    setTimeout(() => {
                        this.innerHTML = originalHTML;
                    }, 2000);
                });
            }
        }
    });
</script>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'My Forms';

// Add page-specific CSS
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/admin.css') .'?v=' . APP_VERSION . '">
<link rel="stylesheet" href="'. asset_path('css/pages/analytics.css') .'?v=' . APP_VERSION . '">';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';