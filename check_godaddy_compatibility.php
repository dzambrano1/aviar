<?php
/**
 * GoDaddy Server Compatibility Checker
 * Upload this file to your GoDaddy server and access it via browser
 * This will help diagnose the 500 Internal Server Error
 */

// Enable error display for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<h1>GoDaddy Server Compatibility Check</h1>";
echo "<hr>";

// 1. Check PHP Version
echo "<h2>1. PHP Version</h2>";
echo "Current PHP Version: <strong>" . phpversion() . "</strong><br>";
echo "Required: PHP 7.0 or higher<br>";
if (version_compare(phpversion(), '7.0.0', '>=')) {
    echo "<span style='color:green;'>✓ PHP version is compatible</span><br>";
} else {
    echo "<span style='color:red;'>✗ PHP version is too old. Please upgrade to PHP 7.4 or 8.x</span><br>";
}
echo "<hr>";

// 2. Check PDO Extension
echo "<h2>2. PDO MySQL Extension</h2>";
if (extension_loaded('pdo') && extension_loaded('pdo_mysql')) {
    echo "<span style='color:green;'>✓ PDO and PDO_MySQL extensions are loaded</span><br>";
} else {
    echo "<span style='color:red;'>✗ PDO or PDO_MySQL extension is not loaded</span><br>";
    if (!extension_loaded('pdo')) {
        echo "Missing: PDO extension<br>";
    }
    if (!extension_loaded('pdo_mysql')) {
        echo "Missing: PDO_MySQL extension<br>";
    }
}
echo "<hr>";

// 3. Check File Permissions
echo "<h2>3. File Permissions Check</h2>";
$files_to_check = [
    'inventario_aviar.php',
    'pdo_conexion.php',
    'uploads'
];

foreach ($files_to_check as $file) {
    if (file_exists($file)) {
        $perms = substr(sprintf('%o', fileperms($file)), -4);
        echo "$file - Permissions: <strong>$perms</strong>";
        
        if (is_dir($file)) {
            echo " (Directory)";
            if (is_writable($file)) {
                echo " <span style='color:green;'>✓ Writable</span>";
            } else {
                echo " <span style='color:red;'>✗ Not writable (should be 755 or 775)</span>";
            }
        } else {
            echo " (File)";
            if (is_readable($file)) {
                echo " <span style='color:green;'>✓ Readable</span>";
            } else {
                echo " <span style='color:red;'>✗ Not readable (should be 644 or 755)</span>";
            }
        }
        echo "<br>";
    } else {
        echo "<span style='color:orange;'>⚠ $file not found in current directory</span><br>";
    }
}
echo "<hr>";

// 4. Check .htaccess Issues
echo "<h2>4. .htaccess Configuration</h2>";
if (file_exists('.htaccess')) {
    echo "<span style='color:orange;'>⚠ .htaccess file exists</span><br>";
    echo "Content:<br><pre style='background:#f5f5f5;padding:10px;'>";
    echo htmlspecialchars(file_get_contents('.htaccess'));
    echo "</pre>";
    echo "Note: Check for any PHP version directives or syntax errors<br>";
} else {
    echo "<span style='color:green;'>✓ No .htaccess file found (this is fine)</span><br>";
}
echo "<hr>";

// 5. Check Server Variables
echo "<h2>5. Server Information</h2>";
echo "Server Software: <strong>" . $_SERVER['SERVER_SOFTWARE'] . "</strong><br>";
echo "Server Name: <strong>" . $_SERVER['SERVER_NAME'] . "</strong><br>";
echo "Document Root: <strong>" . $_SERVER['DOCUMENT_ROOT'] . "</strong><br>";
echo "Current Directory: <strong>" . getcwd() . "</strong><br>";
echo "Script Filename: <strong>" . $_SERVER['SCRIPT_FILENAME'] . "</strong><br>";
echo "<hr>";

// 6. Check Memory Limit
echo "<h2>6. PHP Memory Configuration</h2>";
echo "Memory Limit: <strong>" . ini_get('memory_limit') . "</strong><br>";
echo "Max Execution Time: <strong>" . ini_get('max_execution_time') . " seconds</strong><br>";
echo "Upload Max Filesize: <strong>" . ini_get('upload_max_filesize') . "</strong><br>";
echo "Post Max Size: <strong>" . ini_get('post_max_size') . "</strong><br>";
echo "<hr>";

// 7. Test Database Connection
echo "<h2>7. Database Connection Test</h2>";
echo "<form method='post' style='background:#f5f5f5;padding:15px;border:1px solid #ddd;'>";
echo "<p>Enter your GoDaddy database credentials:</p>";
echo "Database Host: <input type='text' name='db_host' value='" . ($_POST['db_host'] ?? 'localhost') . "' style='width:300px;'><br><br>";
echo "Database Name: <input type='text' name='db_name' value='" . ($_POST['db_name'] ?? '') . "' style='width:300px;'><br><br>";
echo "Database User: <input type='text' name='db_user' value='" . ($_POST['db_user'] ?? '') . "' style='width:300px;'><br><br>";
echo "Database Password: <input type='password' name='db_pass' value='" . ($_POST['db_pass'] ?? '') . "' style='width:300px;'><br><br>";
echo "<button type='submit' name='test_db' style='padding:10px 20px;background:#4CAF50;color:white;border:none;cursor:pointer;'>Test Connection</button>";
echo "</form>";

if (isset($_POST['test_db'])) {
    $db_host = $_POST['db_host'] ?? '';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    
    echo "<div style='margin-top:15px;padding:15px;background:#f9f9f9;border:1px solid #ddd;'>";
    try {
        $test_conn = new PDO("mysql:host=$db_host;dbname=$db_name", $db_user, $db_pass);
        $test_conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        echo "<span style='color:green;font-weight:bold;'>✓ DATABASE CONNECTION SUCCESSFUL!</span><br><br>";
        echo "Connection details are correct. Use these in your pdo_conexion.php file.<br>";
        
        // Test if aviar table exists
        $tables = $test_conn->query("SHOW TABLES LIKE 'aviar'")->fetchAll();
        if (count($tables) > 0) {
            echo "<span style='color:green;'>✓ Table 'aviar' exists in database</span><br>";
            
            // Count records
            $count = $test_conn->query("SELECT COUNT(*) FROM aviar")->fetchColumn();
            echo "Records in aviar table: <strong>$count</strong><br>";
        } else {
            echo "<span style='color:red;'>✗ Table 'aviar' not found in database</span><br>";
        }
        
        $test_conn = null;
    } catch (PDOException $e) {
        echo "<span style='color:red;font-weight:bold;'>✗ DATABASE CONNECTION FAILED!</span><br><br>";
        echo "Error: " . htmlspecialchars($e->getMessage()) . "<br><br>";
        echo "<strong>Common GoDaddy database issues:</strong><br>";
        echo "• Database host is usually 'localhost' but might be different<br>";
        echo "• Database name, username might have a prefix (e.g., 'username_dbname')<br>";
        echo "• Make sure the database user has proper privileges<br>";
        echo "• Check your GoDaddy cPanel for exact database credentials<br>";
    }
    echo "</div>";
}
echo "<hr>";

// 8. Test PDO Connection Syntax
echo "<h2>8. Test Include Files</h2>";
if (file_exists('pdo_conexion.php')) {
    echo "<span style='color:green;'>✓ pdo_conexion.php exists</span><br>";
    echo "Attempting to include it...<br>";
    try {
        ob_start();
        require_once 'pdo_conexion.php';
        $output = ob_get_clean();
        if (isset($conn) && $conn instanceof PDO) {
            echo "<span style='color:green;'>✓ Successfully included and connected!</span><br>";
        } else {
            echo "<span style='color:red;'>✗ File included but \$conn variable not set properly</span><br>";
        }
        if (!empty($output)) {
            echo "Output: <pre>" . htmlspecialchars($output) . "</pre>";
        }
    } catch (Exception $e) {
        echo "<span style='color:red;'>✗ Error including file: " . htmlspecialchars($e->getMessage()) . "</span><br>";
    }
} else {
    echo "<span style='color:red;'>✗ pdo_conexion.php not found</span><br>";
}
echo "<hr>";

echo "<h2>9. Recommendations</h2>";
echo "<ul>";
echo "<li><strong>Update pdo_conexion.php</strong> with your correct GoDaddy database credentials</li>";
echo "<li><strong>Check file permissions:</strong> Files should be 644, directories 755</li>";
echo "<li><strong>Enable error logging</strong> in your PHP file to see actual errors</li>";
echo "<li><strong>Check GoDaddy error logs</strong> in cPanel → Metrics → Error Logs</li>";
echo "<li><strong>Verify uploads directory</strong> exists and is writable (755 or 775)</li>";
echo "<li><strong>Remove or fix .htaccess</strong> if it has syntax errors</li>";
echo "</ul>";

echo "<hr>";
echo "<p style='color:#666;font-size:12px;'>This diagnostic tool can be deleted after resolving issues for security.</p>";
?>

