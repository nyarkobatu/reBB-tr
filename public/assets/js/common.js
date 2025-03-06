/**
 * Common JavaScript functions used across the application
 */

// Function to set a cookie
function setDarkModeCookie(darkMode) {
    const date = new Date();
    date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000)); // 1 year
    const expires = "expires=" + date.toUTCString();
    document.cookie = `darkMode=${darkMode};${expires};path=/`;
}

// Function to get a cookie value
function getDarkModeCookie() {
    const name = "darkMode=";
    const decodedCookie = decodeURIComponent(document.cookie);
    const cookies = decodedCookie.split(';');
    for (let cookie of cookies) {
        cookie = cookie.trim();
        if (cookie.startsWith(name)) {
            return cookie.substring(name.length, cookie.length);
        }
    }
    return null;
}

// Function to toggle dark mode
function toggleDarkMode() {
    const body = document.body;
    const isDarkMode = body.classList.toggle('dark-mode');
    setDarkModeCookie(isDarkMode ? 'true' : 'false');
    
    // Update toggle text
    const toggleLinks = document.querySelectorAll('.dark-mode-toggle');
    toggleLinks.forEach(link => {
        link.textContent = isDarkMode ? '☀️ Light Mode' : '🌙 Dark Mode';
    });
}

// Function to initialize dark mode based on cookie
function initDarkMode() {
    const darkModeSetting = getDarkModeCookie();
    if (darkModeSetting === 'true') {
        document.body.classList.add('dark-mode');
    }
    
    // Set initial toggle text
    const toggleLinks = document.querySelectorAll('.dark-mode-toggle');
    const isDarkMode = document.body.classList.contains('dark-mode');
    toggleLinks.forEach(link => {
        link.textContent = isDarkMode ? '☀️ Light Mode' : '🌙 Dark Mode';
    });
}

// Initialize dark mode when the DOM is fully loaded
document.addEventListener('DOMContentLoaded', function() {
    const toggleLinks = document.querySelectorAll('.dark-mode-toggle');
    toggleLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            toggleDarkMode();
        });
    });
    
    // Initialize dark mode from cookie
    initDarkMode();
});