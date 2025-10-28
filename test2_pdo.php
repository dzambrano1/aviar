<?php
// STEP 2: PDO Extension Test
// This tests if PDO is available
// Upload and access: https://yourdomain.com/aviar/test2_pdo.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>PDO Extension Test</h2>";

if (extension_loaded('pdo')) {
    echo "✓ PDO extension is loaded<br>";
} else {
    echo "✗ PDO extension is NOT loaded<br>";
    die("ERROR: PDO is required. Contact GoDaddy support to enable it.");
}

if (extension_loaded('pdo_mysql')) {
    echo "✓ PDO_MySQL extension is loaded<br>";
} else {
    echo "✗ PDO_MySQL extension is NOT loaded<br>";
    die("ERROR: PDO_MySQL is required. Contact GoDaddy support to enable it.");
}

echo "<br>✓ All required extensions are available.";
echo "<br><br>PHP Version: " . phpversion();
?>

