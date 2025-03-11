<?php
/**
 * reBB - Analytics Dashboard
 * 
 * This file provides a dashboard for viewing site analytics
 */

// Require admin authentication before processing anything else
auth()->requireRole('admin', 'login');

// Make sure analytics is enabled
$analytics = new Analytics();
if (!$analytics->isEnabled()) {
    ?>
    <div class="container-admin">
        <div class="alert alert-warning">
            <h4><i class="bi bi-exclamation-triangle-fill"></i> Analytics System Disabled</h4>
            <p>The analytics system is currently disabled in your configuration. To enable it, set <code>ENABLE_ANALYTICS</code> to <code>true</code> in your config.php file.</p>
            <a href="admin" class="btn btn-primary mt-3">
                <i class="bi bi-arrow-left"></i> Back to Admin
            </a>
        </div>
    </div>
    <?php
    // Store the content in a global variable
    $GLOBALS['page_content'] = ob_get_clean();
    
    // Define a page title
    $GLOBALS['page_title'] = 'Analytics Dashboard';
    
    // Include the master layout
    require_once ROOT_DIR . '/includes/master.php';
    exit;
}

// Initialize analytics
$analytics = new Analytics();

// Get analytics data
$liveVisitors = $analytics->getLiveVisitors();
$popularComponents = $analytics->getPopularComponents();
$themeUsage = $analytics->getPopularThemes();
$popularForms = $analytics->getPopularForms();

// Define the page content to be yielded in the master layout
ob_start();
?>

<div class="container-admin">
    <div class="page-header">
        <h1>Analytics Dashboard</h1>
        <div>
            <a href="?" class="btn btn-outline-secondary me-2"><i class="bi bi-arrow-clockwise"></i> Refresh</a>
            <a href="admin" class="btn btn-outline-primary"><i class="bi bi-arrow-left"></i> Back to Admin</a>
        </div>
    </div>
    
    <!-- Stats Row -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="stat-value"><?php echo $liveVisitors; ?></div>
                    <div class="stat-label">Pages Visited (Last Hour)</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="stat-value"><?php echo count($popularComponents); ?></div>
                    <div class="stat-label">Unique Components Used</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="stat-value"><?php echo count($themeUsage); ?></div>
                    <div class="stat-label">Active Themes</div>
                </div>
            </div>
        </div>
        <div class="col-md-3 mb-3 mb-md-0">
            <div class="card stats-card h-100">
                <div class="card-body">
                    <div class="stat-value"><?php echo count($popularForms); ?></div>
                    <div class="stat-label">Active Forms</div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Components Usage -->
    <div class="row">
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Popular Components</h4>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="componentsChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Theme Usage -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header">
                    <h4 class="mb-0">Theme Usage</h4>
                </div>
                <div class="card-body">
                    <div class="chart-container">
                        <canvas id="themesChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Form Usage -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <h4 class="mb-0">Popular Forms</h4>
            <button class="btn btn-sm btn-outline-primary" id="exportFormDataBtn">
                <i class="bi bi-download"></i> Export Data
            </button>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-striped table-hover">
                    <thead>
                        <tr>
                            <th>Form ID</th>
                            <th>Views</th>
                            <th>Submissions</th>
                            <th>Conversion Rate</th>
                            <th>Last Used</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($popularForms as $formId => $formData): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($formId); ?></td>
                                <td><?php echo $formData['views']; ?></td>
                                <td><?php echo $formData['submissions']; ?></td>
                                <td><?php echo ($formData['views'] > 0) ? 
                                    round(($formData['submissions'] / $formData['views']) * 100, 1) . '%' : '0%'; ?></td>
                                <td><?php echo date('Y-m-d H:i', $formData['last_used']); ?></td>
                                <td>
                                    <a href="<?php echo site_url('form') . '?f=' . $formId; ?>" 
                                       class="btn btn-sm btn-outline-primary" target="_blank">
                                        <i class="bi bi-eye"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Components Chart
    const componentsCtx = document.getElementById('componentsChart').getContext('2d');
    const componentsLabels = <?php echo json_encode(array_keys($popularComponents)); ?>;
    const componentsData = <?php echo json_encode(array_values($popularComponents)); ?>;
    
    new Chart(componentsCtx, {
        type: 'bar',
        data: {
            labels: componentsLabels,
            datasets: [{
                label: 'Component Usage',
                data: componentsData,
                backgroundColor: 'rgba(54, 162, 235, 0.5)',
                borderColor: 'rgba(54, 162, 235, 1)',
                borderWidth: 1
            }]
        },
        options: {
            scales: {
                y: {
                    beginAtZero: true
                }
            },
            responsive: true,
            maintainAspectRatio: false
        }
    });

    // Themes Chart
    const themesCtx = document.getElementById('themesChart').getContext('2d');
    const themesLabels = <?php echo json_encode(array_keys($themeUsage)); ?>;
    const themesData = <?php echo json_encode(array_values($themeUsage)); ?>;
    
    new Chart(themesCtx, {
        type: 'pie',
        data: {
            labels: themesLabels,
            datasets: [{
                label: 'Theme Usage',
                data: themesData,
                backgroundColor: [
                    'rgba(255, 99, 132, 0.5)',
                    'rgba(54, 162, 235, 0.5)',
                    'rgba(255, 206, 86, 0.5)',
                    'rgba(75, 192, 192, 0.5)',
                    'rgba(153, 102, 255, 0.5)'
                ],
                borderColor: [
                    'rgba(255, 99, 132, 1)',
                    'rgba(54, 162, 235, 1)',
                    'rgba(255, 206, 86, 1)',
                    'rgba(75, 192, 192, 1)',
                    'rgba(153, 102, 255, 1)'
                ],
                borderWidth: 1
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false
        }
    });
    
    // Export functionality
    document.getElementById('exportFormDataBtn').addEventListener('click', function() {
        const formData = <?php echo json_encode($popularForms); ?>;
        let csvContent = "data:text/csv;charset=utf-8,";
        
        // Add header row
        csvContent += "Form ID,Views,Submissions,Conversion Rate,Last Used\n";
        
        // Add data rows
        Object.entries(formData).forEach(([formId, data]) => {
            const conversionRate = data.views > 0 ? 
                ((data.submissions / data.views) * 100).toFixed(1) : 0;
            const lastUsed = new Date(data.last_used * 1000).toISOString().split('T')[0];
            
            csvContent += `${formId},${data.views},${data.submissions},${conversionRate}%,${lastUsed}\n`;
        });
        
        // Create download link
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "form_analytics.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    });
});
</script>

<?php
// Store the content in a global variable
$GLOBALS['page_content'] = ob_get_clean();

// Define a page title
$GLOBALS['page_title'] = 'Analytics Dashboard';

// Add page-specific javascript
$GLOBALS['page_javascript'] = '<script src="'. asset_path('js/app/analytics-page.js') .'?v=' . APP_VERSION . '"></script>';

// Add page-specific CSS
$GLOBALS['page_css'] = '<link rel="stylesheet" href="'. asset_path('css/pages/analytics.css') .'?v=' . APP_VERSION . '">';

// Include the master layout
require_once ROOT_DIR . '/includes/master.php';
?>