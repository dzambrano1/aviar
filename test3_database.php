<?php
// STEP 3: Database Connection Test
// Tests the actual database connection
// Upload and access: https://yourdomain.com/aviar/test3_database.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Database Connection Test</h2>";
echo "<p>Enter your GoDaddy database credentials:</p>";

?>
<form method="post" style="background:#f5f5f5;padding:20px;border:1px solid #ccc;max-width:500px;">
    <label>Database Host:</label><br>
    <input type="text" name="db_host" value="<?php echo $_POST['db_host'] ?? 'localhost'; ?>" style="width:100%;padding:8px;margin-bottom:10px;"><br>
    
    <label>Database Name:</label><br>
    <input type="text" name="db_name" value="<?php echo $_POST['db_name'] ?? ''; ?>" style="width:100%;padding:8px;margin-bottom:10px;"><br>
    
    <label>Database Username:</label><br>
    <input type="text" name="db_user" value="<?php echo $_POST['db_user'] ?? ''; ?>" style="width:100%;padding:8px;margin-bottom:10px;"><br>
    
    <label>Database Password:</label><br>
    <input type="password" name="db_pass" value="<?php echo $_POST['db_pass'] ?? ''; ?>" style="width:100%;padding:8px;margin-bottom:10px;"><br>
    
    <button type="submit" name="test" style="padding:10px 30px;background:#4CAF50;color:white;border:none;cursor:pointer;">Test Connection</button>
</form>

<?php
if (isset($_POST['test'])) {
    $db_host = $_POST['db_host'] ?? '';
    $db_name = $_POST['db_name'] ?? '';
    $db_user = $_POST['db_user'] ?? '';
    $db_pass = $_POST['db_pass'] ?? '';
    
    echo "<div style='margin-top:20px;padding:15px;background:#f9f9f9;border:1px solid #ddd;max-width:500px;'>";
    
    try {
        echo "Attempting to connect...<br>";
        $conn = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8", $db_user, $db_pass);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        echo "<h3 style='color:green;'>✓ CONNECTION SUCCESSFUL!</h3>";
        echo "Host: <strong>$db_host</strong><br>";
        echo "Database: <strong>$db_name</strong><br>";
        echo "Username: <strong>$db_user</strong><br>";
        echo "<hr>";
        
        // Check if aviar table exists
        $tables = $conn->query("SHOW TABLES LIKE 'aviar'")->fetchAll();
        if (count($tables) > 0) {
            echo "<span style='color:green;'>✓ Table 'aviar' exists</span><br>";
            $count = $conn->query("SELECT COUNT(*) FROM aviar")->fetchColumn();
            echo "Records in aviar table: <strong>$count</strong><br>";
        } else {
            echo "<span style='color:red;'>✗ Table 'aviar' NOT found</span><br>";
            echo "You need to import your database SQL file.<br>";
        }
        
        echo "<hr>";
        echo "<h4>✅ Update pdo_conexion.php with these values:</h4>";
        echo "<pre style='background:#fff;padding:10px;border:1px solid #ccc;'>";
        echo "\$servername = \"$db_host\";\n";
        echo "\$username = \"$db_user\";\n";
        echo "\$password = \"$db_pass\";\n";
        echo "\$dbname = \"$db_name\";";
        echo "</pre>";
        
        $conn = null;
    } catch (PDOException $e) {
        echo "<h3 style='color:red;'>✗ CONNECTION FAILED!</h3>";
        echo "Error: " . htmlspecialchars($e->getMessage()) . "<br><br>";
        echo "<strong>Common issues:</strong><br>";
        echo "• Wrong database name (GoDaddy often prefixes with username)<br>";
        echo "• Wrong username (also often prefixed)<br>";
        echo "• Wrong password<br>";
        echo "• Database doesn't exist yet<br>";
        echo "<br><strong>Check GoDaddy cPanel → MySQL Databases for correct values</strong>";
    }
    echo "</div>";
}
?>

