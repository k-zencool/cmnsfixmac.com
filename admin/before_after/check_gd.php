<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>GD Library Check</title>
    <style>
        body { font-family: sans-serif; display: grid; place-content: center; height: 100vh; margin: 0; }
        .status { padding: 40px; border-radius: 10px; font-size: 2em; font-weight: bold; color: white; }
        .ok { background-color: #28a745; }
        .error { background-color: #dc3545; }
    </style>
</head>
<body>
    <?php
    if (extension_loaded('gd') && function_exists('gd_info')) {
        echo '<div class="status ok">OK: GD Library is installed!</div>';
    } else {
        echo '<div class="status error">ERROR: GD Library is NOT installed.</div>';
    }
    ?>
</body>
</html>