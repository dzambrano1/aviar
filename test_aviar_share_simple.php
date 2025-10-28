<?php
// ULTRA SIMPLE TEST - Shows exact error
// Upload this and access: https://registroca.com/aviar/test_aviar_share_simple.php?file=Tito_12111_2025-10-14_192455.pdf&tagid=12111

// Enable ALL error display
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h1>Testing aviar_share.php Step by Step</h1><hr>";

// Test 1: Basic PHP
echo "<h2>Test 1: PHP Working</h2>";
echo "✓ PHP is running<br>";
echo "PHP Version: " . phpversion() . "<br><hr>";

// Test 2: Check parameters
echo "<h2>Test 2: URL Parameters</h2>";
$file = $_GET['file'] ?? 'NOT PROVIDED';
$tagid = $_GET['tagid'] ?? 'NOT PROVIDED';
echo "File parameter: <strong>$file</strong><br>";
echo "TagID parameter: <strong>$tagid</strong><br><hr>";

// Test 3: Try to load pdo_conexion.php
echo "<h2>Test 3: Loading Database Connection</h2>";
try {
    require_once './pdo_conexion.php';
    echo "✓ pdo_conexion.php loaded<br>";
    
    if (isset($conn) && $conn instanceof PDO) {
        echo "✓ PDO connection object exists<br>";
    } else {
        echo "<span style='color:red;'>✗ \$conn variable not set or not PDO</span><br>";
        var_dump($conn);
    }
} catch (Exception $e) {
    echo "<span style='color:red;font-weight:bold;'>✗ ERROR loading pdo_conexion.php:</span><br>";
    echo "<pre style='background:#fee;padding:10px;'>";
    echo htmlspecialchars($e->getMessage());
    echo "</pre>";
    die("Fix this error before continuing.");
}
echo "<hr>";

// Test 4: Check if file exists
echo "<h2>Test 4: Check PDF File</h2>";
if ($file !== 'NOT PROVIDED') {
    $filepath = './reports/' . $file;
    echo "Looking for: <code>$filepath</code><br>";
    
    if (file_exists($filepath)) {
        echo "✓ File exists<br>";
        echo "File size: " . filesize($filepath) . " bytes<br>";
        echo "Readable: " . (is_readable($filepath) ? 'YES' : 'NO') . "<br>";
    } else {
        echo "<span style='color:red;'>✗ File NOT found</span><br>";
        echo "Check that:<br>";
        echo "1. PDF was generated successfully<br>";
        echo "2. reports/ directory exists<br>";
        echo "3. File has correct name<br>";
    }
} else {
    echo "<span style='color:orange;'>⚠ No file parameter provided in URL</span><br>";
}
echo "<hr>";

// Test 5: Try database query
echo "<h2>Test 5: Database Query</h2>";
if ($tagid !== 'NOT PROVIDED') {
    try {
        $sql = "SELECT tagid, nombre FROM aviar WHERE tagid = ?";
        echo "SQL: <code>$sql</code><br>";
        echo "TagID: <code>$tagid</code><br><br>";
        
        $stmt = $conn->prepare($sql);
        $stmt->execute([$tagid]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($animal) {
            echo "✓ Animal found<br>";
            echo "Name: <strong>{$animal['nombre']}</strong><br>";
            echo "Tag ID: <strong>{$animal['tagid']}</strong><br>";
        } else {
            echo "<span style='color:red;'>✗ Animal NOT found in database</span><br>";
            echo "Check that:<br>";
            echo "1. Database was imported<br>";
            echo "2. Animal with tagid '$tagid' exists<br>";
        }
    } catch (PDOException $e) {
        echo "<span style='color:red;font-weight:bold;'>✗ DATABASE ERROR:</span><br>";
        echo "<pre style='background:#fee;padding:10px;'>";
        echo htmlspecialchars($e->getMessage());
        echo "</pre>";
    }
} else {
    echo "<span style='color:orange;'>⚠ No tagid parameter provided in URL</span><br>";
}
echo "<hr>";

// Final summary
echo "<h2>Summary</h2>";
echo "<p>If all tests above show green checkmarks, aviar_share.php should work.</p>";
echo "<p>If you see red X marks, those are the issues preventing aviar_share.php from working.</p>";
echo "<hr>";
echo "<p><strong>Next step:</strong> Try the actual file:</p>";
echo "<a href='aviar_share.php?file=$file&tagid=$tagid' style='display:inline-block;padding:15px 30px;background:#28a745;color:white;text-decoration:none;border-radius:5px;font-weight:bold;'>Test aviar_share.php with these parameters</a>";
?>


