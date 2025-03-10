/**
 * Common JavaScript functions used across the application
 */

// Function to set a cookie
function setDarkModeCookie(darkMode) {
    if (darkMode === 'system') {
        // Remove the cookie to use system preference
        document.cookie = "darkMode=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;";
    } else {
        const date = new Date();
        date.setTime(date.getTime() + (365 * 24 * 60 * 60 * 1000)); // 1 year
        const expires = "expires=" + date.toUTCString();
        document.cookie = `darkMode=${darkMode};${expires};path=/`;
    }
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
    const isDarkMode = body.classList.contains('dark-mode');
    
    // Toggle the mode
    if (isDarkMode) {
        body.classList.remove('dark-mode');
        setDarkModeCookie('false');
    } else {
        body.classList.add('dark-mode');
        setDarkModeCookie('true');
    }
    
    // Update toggle text
    const toggleLinks = document.querySelectorAll('.dark-mode-toggle');
    toggleLinks.forEach(link => {
        link.textContent = isDarkMode ? '🌙 Dark Mode' : '☀️ Light Mode';
    });
}

// Function to initialize dark mode based on cookie or system preference
function initDarkMode() {
    const darkModeSetting = getDarkModeCookie();
    
    // If no cookie is set, check system preference
    if (darkModeSetting === null) {
        const prefersDarkMode = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        if (prefersDarkMode) {
            document.body.classList.add('dark-mode');
            setDarkModeCookie('true');
        }
    } else if (darkModeSetting === 'true') {
        document.body.classList.add('dark-mode');
    }
    
    // Set initial toggle text
    const toggleLinks = document.querySelectorAll('.dark-mode-toggle');
    const isDarkMode = document.body.classList.contains('dark-mode');
    toggleLinks.forEach(link => {
        link.textContent = isDarkMode ? '☀️ Light Mode' : '🌙 Dark Mode';
    });
}

// Listen for changes in OS theme preference
document.addEventListener('DOMContentLoaded', function() {
    // Initialize dark mode from cookie or system preference
    initDarkMode();
    
    // Add dark mode toggle listeners
    const toggleLinks = document.querySelectorAll('.dark-mode-toggle');
    toggleLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            toggleDarkMode();
        });
    });
    
    // Add listener for OS theme changes
    if (window.matchMedia) {
        const colorSchemeQuery = window.matchMedia('(prefers-color-scheme: dark)');
        
        // Modern browsers
        if (colorSchemeQuery.addEventListener) {
            colorSchemeQuery.addEventListener('change', function(e) {
                // Only apply if user hasn't manually set a preference
                if (getDarkModeCookie() === null) {
                    if (e.matches) {
                        document.body.classList.add('dark-mode');
                    } else {
                        document.body.classList.remove('dark-mode');
                    }
                    
                    // Update toggle text
                    const isDarkMode = document.body.classList.contains('dark-mode');
                    toggleLinks.forEach(link => {
                        link.textContent = isDarkMode ? '☀️ Light Mode' : '🌙 Dark Mode';
                    });
                }
            });
        }
    }
});