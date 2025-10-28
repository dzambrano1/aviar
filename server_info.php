<?php
/**
 * Server Information Script
 * Upload this to GoDaddy to see full PHP configuration
 * Access via: https://yourdomain.com/aviar/server_info.php
 * 
 * SECURITY WARNING: Delete this file after checking!
 */

// Add password protection (optional but recommended)
$access_password = "check123"; // Change this password!

if (!isset($_GET['pass']) || $_GET['pass'] !== $access_password) {
    die('<h1>Access Denied</h1><p>Add ?pass=check123 to URL</p>');
}

echo "<h1>Server Configuration Information</h1>";
echo "<p style='color:red;'><strong>WARNING: Delete this file after checking! It contains sensitive information.</strong></p>";
echo "<hr>";

// Display full PHP configuration
phpinfo();
?>

