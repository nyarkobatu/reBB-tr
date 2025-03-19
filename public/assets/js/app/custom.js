/**
 * custom.js - Custom Utility Functions
 * 
 * This file provides simple functionality that can be used
 * throughout your website or application.
 * 
 * @file
 * @version 1.0
 * @description A lightweight custom utility for web applications
 */

/**
 * Creates or updates a cookie with the specified name and value
 * 
 * @param {string} name - The name of the cookie to set
 * @param {string} value - The value to store in the cookie
 * @param {number} daysToLive - Number of days until the cookie expires (default: 7 days)
 * 
 * @example
 * // Set a cookie that expires in 7 days (default)
 * setCookie("username", "John");
 * 
 * // Set a cookie that expires in 30 days
 * setCookie("preferences", "darkMode", 30);
 * 
 * // Set a session cookie (expires when browser is closed)
 * setCookie("temporaryData", "someValue", 0);
 */
function setCookie(name, value, daysToLive = 7) {
    const date = new Date();
    date.setTime(date.getTime() + (daysToLive * 24 * 60 * 60 * 1000));
    const expires = "expires=" + date.toUTCString();
    document.cookie = `${name}=${encodeURIComponent(value)};${expires};path=/`;
}
  
/**
 * Retrieves a cookie value by its name
 * 
 * This function searches through all available cookies and returns
 * the value of the specified cookie if found.
 * 
 * @param {string} name - The name of the cookie to retrieve
 * @returns {string|null} The value of the cookie if found, null otherwise
 * 
 * @example
 * // Check if a user has previously logged in
 * const username = getCookie("username");
 * if (username) {
 *     console.log(`Welcome back, ${username}!`);
 * } else {
 *     console.log("No user is currently logged in");
 * }
 */
function getCookie(name) {
    const decodedCookie = decodeURIComponent(document.cookie);
    const cookies = decodedCookie.split(';');
    for (let cookie of cookies) {
        cookie = cookie.trim();
        if (cookie.startsWith(name + "=")) {
            return cookie.substring(name.length + 1);
        }
    }
    return null;
}