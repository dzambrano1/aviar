<?php
// Test file to verify aviar_share.php fix
// Upload this and access: https://yourdomain.com/aviar/test_share_fix.php

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Testing aviar_share.php Fix</h1>";
echo "<hr>";

// Test 1: Check if pdo_conexion.php works
echo "<h2>Test 1: Database Connection</h2>";
try {
    require_once './pdo_conexion.php';
    if (isset($conn) && $conn instanceof PDO) {
        echo "<span style='color:green;font-weight:bold;'>✓ PDO Connection works</span><br>";
    } else {
        echo "<span style='color:red;font-weight:bold;'>✗ PDO Connection failed</span><br>";
        die("Fix pdo_conexion.php first!");
    }
} catch (Exception $e) {
    echo "<span style='color:red;font-weight:bold;'>✗ Error: " . $e->getMessage() . "</span><br>";
    die("Fix the error above first!");
}

// Test 2: Check if aviar table exists and has data
echo "<h2>Test 2: Aviar Table</h2>";
try {
    $stmt = $conn->query("SELECT COUNT(*) as count FROM aviar");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $count = $result['count'];
    
    if ($count > 0) {
        echo "<span style='color:green;font-weight:bold;'>✓ Table 'aviar' exists with $count records</span><br>";
        
        // Get a sample record
        $stmt = $conn->query("SELECT tagid, nombre FROM aviar LIMIT 1");
        $sample = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "Sample animal: {$sample['nombre']} (TagID: {$sample['tagid']})<br>";
    } else {
        echo "<span style='color:orange;font-weight:bold;'>⚠ Table 'aviar' exists but is empty</span><br>";
    }
} catch (PDOException $e) {
    echo "<span style='color:red;font-weight:bold;'>✗ Error: " . $e->getMessage() . "</span><br>";
}

// Test 3: Check if reports directory exists
echo "<h2>Test 3: Reports Directory</h2>";
if (is_dir('./reports')) {
    echo "<span style='color:green;font-weight:bold;'>✓ Directory 'reports/' exists</span><br>";
    
    // List PDF files
    $files = glob('./reports/*.pdf');
    if (count($files) > 0) {
        echo "<span style='color:green;'>✓ Found " . count($files) . " PDF file(s)</span><br>";
        echo "Sample files:<br>";
        foreach (array_slice($files, 0, 3) as $file) {
            $basename = basename($file);
            echo "  - $basename (" . round(filesize($file)/1024, 2) . " KB)<br>";
        }
    } else {
        echo "<span style='color:orange;'>⚠ No PDF files found yet (will be created when you generate reports)</span><br>";
    }
    
    // Check if writable
    if (is_writable('./reports')) {
        echo "<span style='color:green;'>✓ Directory is writable</span><br>";
    } else {
        echo "<span style='color:red;'>✗ Directory is NOT writable - set permissions to 755 or 775</span><br>";
    }
} else {
    echo "<span style='color:red;font-weight:bold;'>✗ Directory 'reports/' does NOT exist</span><br>";
    echo "Create it with: mkdir('reports', 0755, true);<br>";
}

// Test 4: Simulate what aviar_share.php does
echo "<h2>Test 4: Simulate aviar_share.php Logic</h2>";
try {
    // Get first animal
    $stmt = $conn->query("SELECT tagid, nombre FROM aviar LIMIT 1");
    $animal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($animal) {
        echo "<span style='color:green;'>✓ Can fetch animal data</span><br>";
        echo "Animal: {$animal['nombre']} (TagID: {$animal['tagid']})<br>";
        
        // Check if a PDF exists for this animal
        $pdfPattern = "./reports/*_{$animal['tagid']}_*.pdf";
        $pdfFiles = glob($pdfPattern);
        
        if (count($pdfFiles) > 0) {
            $testFile = basename($pdfFiles[0]);
            echo "<span style='color:green;'>✓ Found PDF for this animal: $testFile</span><br>";
            echo "<hr>";
            echo "<h3 style='color:green;'>✅ ALL TESTS PASSED!</h3>";
            echo "<p><strong>You can now test the actual share page:</strong></p>";
            echo "<a href='aviar_share.php?file=$testFile&tagid={$animal['tagid']}' target='_blank' style='display:inline-block;padding:15px 30px;background:#28a745;color:white;text-decoration:none;border-radius:5px;font-weight:bold;'>Test aviar_share.php</a>";
        } else {
            echo "<span style='color:orange;'>⚠ No PDF found for this animal yet</span><br>";
            echo "Generate a report first, then the share feature will work.<br>";
        }
    } else {
        echo "<span style='color:red;'>✗ No animals in database</span><br>";
    }
} catch (PDOException $e) {
    echo "<span style='color:red;font-weight:bold;'>✗ Error: " . $e->getMessage() . "</span><br>";
}

echo "<hr>";
echo "<h2>Summary</h2>";
echo "<p>If all tests above show green checkmarks, aviar_share.php should work correctly.</p>";
echo "<p>If you see any red X marks, fix those issues first.</p>";

?>

<style>
    body {
        font-family: Arial, sans-serif;
        padding: 20px;
        background: #f5f5f5;
    }
    h1, h2, h3 {
        color: #333;
    }
    hr {
        margin: 20px 0;
        border: none;
        border-top: 2px solid #ddd;
    }
</style>

