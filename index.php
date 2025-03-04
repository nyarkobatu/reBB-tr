<?php
require_once 'site.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo SITE_NAME; ?> - <?php echo SITE_DESCRIPTION; ?></title>
    <link rel="apple-touch-icon" sizes="180x180" href="/resources/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/resources/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/resources/favicon-16x16.png">
    <link rel="manifest" href="/resources/site.webmanifest">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            min-height: 100vh;
        }
        .container {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-grow: 1;
        }
        .form-box {
            background-color: #ffffff;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 0 10px rgba(0, 0, 0, 0.1);
            width: 400px;
        }
        .btn-block {
            margin-bottom: 10px;
        }
        #hashInputContainer {
            display: none;
            margin-top: 15px;
        }
        .footer {
            background-color: #e0e0e0;
            padding: 20px 0;
            text-align: center;
            color: #555;
        }
        .footer a {
            color: #007bff;
            text-decoration: none;
        }
        .footer a:hover {
            text-decoration: underline;
        }
    </style>
    <!-- Dark Mode Styles -->
    <style>
        /* Dark Mode Styles */
        body.dark-mode {
            background-color: #121212;
            color: #e0e0e0;
        }
        
        /* Header/Title styles */
        body.dark-mode h1, 
        body.dark-mode h2, 
        body.dark-mode h3, 
        body.dark-mode h4, 
        body.dark-mode h5, 
        body.dark-mode h6 {
            color: #ffffff;
        }
        
        /* Container/Form styles */
        body.dark-mode .form-box,
        body.dark-mode #form-container,
        body.dark-mode #output-container,
        body.dark-mode #content-wrapper,
        body.dark-mode .card {
            background-color: #1e1e1e;
            color: #e0e0e0;
        }
        
        /* Form controls */
        body.dark-mode .form-control,
        body.dark-mode input,
        body.dark-mode textarea,
        body.dark-mode select {
            background-color: #2d2d2d;
            color: #e0e0e0;
            border-color: #444;
        }
        
        /* Footer */
        body.dark-mode .footer {
            background-color: #1e1e1e;
            color: #aaa;
        }
        
        body.dark-mode .footer a {
            color: #4da3ff;
        }
        
        /* Dark mode toggle link */
        .dark-mode-toggle {
            cursor: pointer;
        }
    </style>
</head>
<body>

    <div class="container">
        <div class="form-box">
            <h2 class="text-center mb-4"><?php echo SITE_NAME; ?></h2>
            <button id="createFormBtn" class="btn btn-primary btn-block">Create a form</button>
            <button id="useFormBtn" class="btn btn-secondary btn-block">Use a form</button>

            <div id="hashInputContainer">
                <div class="form-group">
                    <label for="shareableHash">Form:</label>
                    <input type="text" class="form-control" id="shareableHash" placeholder="Enter the hash/id for the form you wish to use">
                </div>
                <button id="submitHashBtn" class="btn btn-success btn-block">Submit</button>
            </div>

            <div class="mt-3">
                <a href="/documentation.php" class="btn btn-info btn-block">
                    <i class="bi bi-book"></i> Documentation
                </a>
            </div>
        </div>
    </div>

    <footer class="footer">
        <p>Made with ❤️ by <a href="https://booskit.dev/">booskit</a></br>
        <a href="<?php echo FOOTER_GITHUB; ?>">Github</a> • <a href="#" class="dark-mode-toggle">🌙 Dark Mode</a></br>
        <span style="font-size: 12px;"><?php echo SITE_VERSION; ?></span></p>
    </footer>

    <script>
        document.getElementById('createFormBtn').addEventListener('click', function() {
            window.location.href = '/builder.php';
        });

        document.getElementById('useFormBtn').addEventListener('click', function() {
            document.getElementById('hashInputContainer').style.display = 'block';
        });

        document.getElementById('submitHashBtn').addEventListener('click', function() {
            const hash = document.getElementById('shareableHash').value;
            if (hash) {
                window.location.href = '/form.php?f=' + hash;
            } else {
                alert('Please enter a shareable hash.');
            }
        });
        
        // Dark Mode Functions
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
        
        // Add event listeners to dark mode toggles
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
    </script>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>