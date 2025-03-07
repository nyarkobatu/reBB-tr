/**
 * Index page specific JavaScript
 */
document.addEventListener('DOMContentLoaded', function() {
    // Handle "Create a form" button click
    document.getElementById('createFormBtn').addEventListener('click', function() {
        window.location.href = current_header + 'builder';
    });

    // Handle "Use a form" button click
    document.getElementById('useFormBtn').addEventListener('click', function() {
        // Toggle visibility with smooth animation
        const hashContainer = document.getElementById('hashInputContainer');
        if (hashContainer.style.display === 'block') {
            hashContainer.style.display = 'none';
        } else {
            hashContainer.style.display = 'block';
            // Focus on the input field
            document.getElementById('shareableHash').focus();
        }
    });

    // Handle form submission
    document.getElementById('submitHashBtn').addEventListener('click', function() {
        const hash = document.getElementById('shareableHash').value.trim();
        if (hash) {
            // Check if the input is a URL
            if (hash.includes('://') && hash.includes('?f=')) {
                // Extract the form ID from the URL
                const urlParams = new URLSearchParams(hash.substring(hash.indexOf('?')));
                const formId = urlParams.get('f');
                if (formId) {
                    window.location.href = current_header + 'form?f=' + formId;
                } else {
                    showError('Invalid form URL. Please enter a valid form ID or URL.');
                }
            } else {
                // Treat as direct form ID
                window.location.href = current_header + 'form?f=' + hash;
            }
        } else {
            showError('Please enter a form ID or URL.');
        }
    });

    // Allow hitting Enter key in the input field to submit
    document.getElementById('shareableHash').addEventListener('keypress', function(e) {
        if (e.key === 'Enter') {
            document.getElementById('submitHashBtn').click();
        }
    });

    // Function to show error message
    function showError(message) {
        const hashContainer = document.getElementById('hashInputContainer');
        
        // Check if error message element already exists
        let errorElement = hashContainer.querySelector('.error-message');
        if (!errorElement) {
            // Create error message element
            errorElement = document.createElement('div');
            errorElement.className = 'error-message text-danger mt-2';
            hashContainer.appendChild(errorElement);
        }
        
        // Set error message
        errorElement.textContent = message;
        
        // Clear error after 3 seconds
        setTimeout(() => {
            errorElement.textContent = '';
        }, 3000);
    }
});