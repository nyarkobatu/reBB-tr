// Function to track analytics events with a check for analytics being enabled
function trackAnalytics(action, data = {}) {
    // First check if analytics was already determined to be disabled
    if (window.analyticsDisabled === true) {
        return Promise.resolve({success: true, analyticsEnabled: false});
    }
    
    return fetch('ajax', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            type: 'analytics',
            action: action,
            ...data
        })
    })
    .then(response => response.json())
    .then(responseData => {
        // If server responds that analytics is disabled, remember this for future calls
        if (responseData.analyticsEnabled === false) {
            window.analyticsDisabled = true;
        }
        return responseData;
    })
    .catch(err => {
        console.warn('Analytics error:', err);
        return {success: false, error: err.message};
    });
}

// Track page views with the new function
document.addEventListener('DOMContentLoaded', function() {
    // Don't track on admin pages
    if (!window.location.pathname.includes('admin') && !window.location.pathname.includes('analytics')) {
        trackAnalytics('track_pageview', {
            page: window.location.pathname
        });
    }
});