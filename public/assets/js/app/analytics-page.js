if (window.location.pathname.includes('analytics')) {
    // Refresh live visitor count every 60 seconds
    setInterval(function() {
        fetch('ajax', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                type: 'analytics',
                action: 'get_live_visitors'
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success && document.getElementById('liveVisitorCount')) {
                document.getElementById('liveVisitorCount').textContent = data.count;
            }
        })
        .catch(err => console.warn('Analytics error:', err));
    }, 60000);
}