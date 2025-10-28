<?php
// STEP 4: Test pdo_conexion.php Include
// This tests if pdo_conexion.php can be loaded without errors
// Upload and access: https://yourdomain.com/aviar/test4_include.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Testing pdo_conexion.php</h2>";

echo "Current directory: " . getcwd() . "<br>";
echo "File exists: " . (file_exists('./pdo_conexion.php') ? 'YES' : 'NO') . "<br><br>";

if (!file_exists('./pdo_conexion.php')) {
    die("ERROR: pdo_conexion.php not found in current directory!");
}

echo "Attempting to include pdo_conexion.php...<br><br>";

try {
    require_once './pdo_conexion.php';
    
    if (isset($conn) && $conn instanceof PDO) {
        echo "<h3 style='color:green;'>✓ pdo_conexion.php loaded successfully!</h3>";
        echo "<h3 style='color:green;'>✓ Database connection established!</h3>";
        
        // Try a simple query
        $stmt = $conn->query("SELECT COUNT(*) FROM aviar");
        $count = $stmt->fetchColumn();
        echo "Records in database: <strong>$count</strong><br>";
        echo "<br><strong>✅ Everything is working! Your main file should work too.</strong>";
    } else {
        echo "<h3 style='color:red;'>✗ pdo_conexion.php loaded but \$conn variable not set properly</h3>";
    }
} catch (Exception $e) {
    echo "<h3 style='color:red;'>✗ Error loading pdo_conexion.php</h3>";
    echo "Error message: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "Error file: " . $e->getFile() . "<br>";
    echo "Error line: " . $e->getLine() . "<br>";
    echo "<br><strong>Fix this error in pdo_conexion.php before proceeding.</strong>";
}
?>

