<?php
// TEMPORARY DEBUG VERSION - Shows all PHP errors
// Use this to see the exact error causing the 500
// Upload as inventario_aviar_debug.php and access it
// Once you see the error, fix it in the main file

// Enable ALL error display
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<!-- Debug mode enabled. Any PHP errors will be displayed. -->\n";

// Now include the original file logic
require_once './pdo_conexion.php';

// Rest of your original code would go here
// But let's test just the connection first

try {
    echo "<!DOCTYPE html><html><head><title>Debug Mode</title></head><body>";
    echo "<h1 style='background:yellow;padding:10px;'>DEBUG MODE - Error Display Enabled</h1>";
    echo "<p>Database connection: ";
    
    if (isset($conn) && $conn instanceof PDO) {
        echo "<span style='color:green;font-weight:bold;'>✓ Connected</span></p>";
        
        // Test query
        $stmt = $conn->query("SELECT COUNT(*) FROM aviar");
        $count = $stmt->fetchColumn();
        echo "<p>Records in database: <strong>$count</strong></p>";
        
        echo "<hr>";
        echo "<h2>If you see this, the database connection works!</h2>";
        echo "<p>The 500 error in inventario_aviar.php might be caused by:</p>";
        echo "<ol>";
        echo "<li>PHP syntax error in the HTML/CSS section</li>";
        echo "<li>Memory limit exceeded (file is too large)</li>";
        echo "<li>Execution timeout</li>";
        echo "<li>Missing function or class</li>";
        echo "</ol>";
        echo "<hr>";
        echo "<h3>Next Step:</h3>";
        echo "<p>Check your GoDaddy error log for the specific error message:</p>";
        echo "<p><strong>cPanel → Metrics → Error Logs</strong></p>";
        
    } else {
        echo "<span style='color:red;font-weight:bold;'>✗ Not connected</span></p>";
    }
    
    echo "</body></html>";
    
} catch (Exception $e) {
    echo "<h2 style='color:red;'>Exception Caught:</h2>";
    echo "<pre style='background:#fee;padding:15px;border:2px solid #c00;'>";
    echo "Message: " . htmlspecialchars($e->getMessage()) . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n\n";
    echo "Stack Trace:\n" . htmlspecialchars($e->getTraceAsString());
    echo "</pre>";
}
?>

