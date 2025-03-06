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
        document.getElementById('hashInputContainer').style.display = 'block';
    });

    // Handle form submission
    document.getElementById('submitHashBtn').addEventListener('click', function() {
        const hash = document.getElementById('shareableHash').value;
        if (hash) {
            window.location.href = current_header + 'form?f=' + hash;
        } else {
            alert('Please enter a shareable hash.');
        }
    });
});