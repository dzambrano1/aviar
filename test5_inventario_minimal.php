<?php
// STEP 5: Minimal Inventario Test
// Tests if the main inventario_aviar.php logic works
// Upload and access: https://yourdomain.com/aviar/test5_inventario_minimal.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h2>Minimal Inventario Test</h2>";

try {
    require_once './pdo_conexion.php';
    echo "✓ Database connection loaded<br>";
    
    // Test query
    $sql = "SELECT id, tagid, nombre FROM aviar LIMIT 5";
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "✓ Query executed successfully<br>";
    echo "Records found: " . count($result) . "<br><br>";
    
    if (!empty($result)) {
        echo "<h3>Sample Records:</h3>";
        echo "<table border='1' style='border-collapse:collapse;'>";
        echo "<tr><th style='padding:8px;'>ID</th><th style='padding:8px;'>Tag ID</th><th style='padding:8px;'>Nombre</th></tr>";
        foreach ($result as $row) {
            echo "<tr>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($row['id']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($row['tagid']) . "</td>";
            echo "<td style='padding:8px;'>" . htmlspecialchars($row['nombre']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
    echo "<br><h3 style='color:green;'>✅ Everything works!</h3>";
    echo "<p>If this works but inventario_aviar.php doesn't, the problem is likely:</p>";
    echo "<ul>";
    echo "<li>PHP syntax error in inventario_aviar.php</li>";
    echo "<li>Memory limit exceeded (too large file)</li>";
    echo "<li>Execution time exceeded</li>";
    echo "<li>Missing PHP extensions for specific features</li>";
    echo "</ul>";
    
} catch (Exception $e) {
    echo "<h3 style='color:red;'>✗ Error:</h3>";
    echo "Message: " . htmlspecialchars($e->getMessage()) . "<br>";
    echo "File: " . $e->getFile() . "<br>";
    echo "Line: " . $e->getLine() . "<br>";
}
?>

