<?php
require_once 'pdo_conexion.php';

echo "<h2>Database Connection Test</h2>";

try {
    // Test connection
    echo "<p><strong>Connection Status:</strong> ";
    if ($conn instanceof PDO) {
        echo "✅ Connected successfully to database: " . $conn->query('SELECT DATABASE()')->fetchColumn();
    } else {
        echo "❌ Connection failed";
    }
    echo "</p>";
    
    // Test if ac_concentrado table exists
    echo "<p><strong>Table Check:</strong> ";
    $stmt = $conn->query("SHOW TABLES LIKE 'ac_concentrado'");
    if ($stmt->rowCount() > 0) {
        echo "✅ Table 'ac_concentrado' exists";
    } else {
        echo "❌ Table 'ac_concentrado' does not exist";
    }
    echo "</p>";
    
    // Test table structure
    echo "<p><strong>Table Structure:</strong></p>";
    $stmt = $conn->query("DESCRIBE ac_concentrado");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($columns as $column) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($column['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($column['Extra']) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    // Test sample data
    echo "<p><strong>Sample Data:</strong></p>";
    $stmt = $conn->query("SELECT * FROM ac_concentrado LIMIT 3");
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($data)) {
        echo "<p>No data found in table</p>";
    } else {
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr>";
        foreach (array_keys($data[0]) as $header) {
            echo "<th>" . htmlspecialchars($header) . "</th>";
        }
        echo "</tr>";
        
        foreach ($data as $row) {
            echo "<tr>";
            foreach ($row as $value) {
                echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // Test insert operation
    echo "<p><strong>Test Insert Operation:</strong></p>";
    try {
        $stmt = $conn->prepare("INSERT INTO ac_concentrado (ac_concentrado_nombre, ac_concentrado_etapa, ac_concentrado_costo, ac_concentrado_vigencia) VALUES (?, ?, ?, ?)");
        $result = $stmt->execute(['TEST_ALIMENTO', 'TEST_ETAPA', 25.00, 30]);
        
        if ($result) {
            $newId = $conn->lastInsertId();
            echo "✅ Test insert successful. New ID: " . $newId;
            
            // Clean up test data
            $stmt = $conn->prepare("DELETE FROM ac_concentrado WHERE id = ?");
            $stmt->execute([$newId]);
            echo " (Test record cleaned up)";
        } else {
            echo "❌ Test insert failed";
        }
    } catch (Exception $e) {
        echo "❌ Test insert error: " . $e->getMessage();
    }
    
} catch (Exception $e) {
    echo "<p><strong>Error:</strong> " . $e->getMessage() . "</p>";
}
?>
