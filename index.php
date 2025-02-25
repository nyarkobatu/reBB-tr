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
        </div>
    </div>

    <footer class="footer">
        <p>Made with ❤️ by <a href="https://booskit.dev/">booskit</a></br>
        <a href="<?php echo FOOTER_GITHUB; ?>">Github</a></br>
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
    </script>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.5.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
