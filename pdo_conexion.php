<?php

// Detect environment based on server name
$is_production = (isset($_SERVER['SERVER_NAME']) && $_SERVER['SERVER_NAME'] !== 'localhost');

if ($is_production) {
    // ============================================================
    // PRODUCTION DATABASE CREDENTIALS (GoDaddy)
    // ============================================================
    // IMPORTANT: Update these values with your GoDaddy database credentials
    // You can find these in GoDaddy cPanel → Databases → MySQL Databases
    
    $servername = "localhost"; // Usually 'localhost' on GoDaddy shared hosting
    
    // GoDaddy typically uses prefixed database names and usernames
    // Example: if your cPanel username is 'myuser', database might be 'myuser_aviar'
    $username = "root"; // Change to your GoDaddy database username (e.g., 'myuser_aviar')
    $password = ""; // Change to your GoDaddy database password
    $dbname = "aviar"; // Change to your GoDaddy database name (e.g., 'myuser_aviar')
    
    // ============================================================
} else {
    // Local development database credentials
    $servername = "localhost";
    $username = "root";
    $password = "";
    $dbname = "aviar";
}

try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname;charset=utf8", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    $conn->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $conn->exec("SET NAMES utf8");
} catch(PDOException $e) {
    // Log the error to PHP error log
    error_log('Database connection failed: ' . $e->getMessage() . ' | Server: ' . $_SERVER['SERVER_NAME']);
    
    // For debugging - show detailed error in development only
    if (!$is_production) {
        die('Database connection failed: ' . $e->getMessage() . '<br>Host: ' . $servername . '<br>Database: ' . $dbname . '<br>User: ' . $username);
    }
    
    // In production, show generic error
    if (php_sapi_name() === 'cli') {
        // Command line
        die('Database connection failed. Check error logs for details.');
    } else {
        // Web request
        header('Content-Type: text/html; charset=utf-8');
        http_response_code(500);
        echo '<!DOCTYPE html>
<html>
<head>
    <title>Database Connection Error</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 50px; text-align: center; }
        .error-box { background: #fee; border: 1px solid #c00; padding: 20px; border-radius: 5px; max-width: 600px; margin: 0 auto; }
        h1 { color: #c00; }
    </style>
</head>
<body>
    <div class="error-box">
        <h1>Database Connection Error</h1>
        <p>Unable to connect to the database.</p>
        <p>Please contact the system administrator.</p>
        <hr>
        <small>Error has been logged for review.</small>
    </div>
</body>
</html>';
        exit;
    }
}