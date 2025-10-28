<?php
require_once './pdo_conexion.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Check for images that don't have uploads/ prefix
    $sql = "SELECT id, tagid, image, image2, image3, video FROM aviar WHERE 
            (image IS NOT NULL AND image != '' AND image NOT LIKE 'uploads/%') OR
            (image2 IS NOT NULL AND image2 != '' AND image2 NOT LIKE 'uploads/%') OR
            (image3 IS NOT NULL AND image3 != '' AND image3 NOT LIKE 'uploads/%') OR
            (video IS NOT NULL AND video != '' AND video NOT LIKE 'uploads/%')";
    
    $stmt = $conn->prepare($sql);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $fixed_count = 0;
    
    foreach ($results as $row) {
        $updates = [];
        $params = [];
        
        // Check and fix image
        if ($row['image'] && $row['image'] !== '' && !str_starts_with($row['image'], 'uploads/')) {
            $updates[] = 'image = ?';
            $params[] = 'uploads/' . $row['image'];
        }
        
        // Check and fix image2
        if ($row['image2'] && $row['image2'] !== '' && !str_starts_with($row['image2'], 'uploads/')) {
            $updates[] = 'image2 = ?';
            $params[] = 'uploads/' . $row['image2'];
        }
        
        // Check and fix image3
        if ($row['image3'] && $row['image3'] !== '' && !str_starts_with($row['image3'], 'uploads/')) {
            $updates[] = 'image3 = ?';
            $params[] = 'uploads/' . $row['image3'];
        }
        
        // Check and fix video
        if ($row['video'] && $row['video'] !== '' && !str_starts_with($row['video'], 'uploads/')) {
            $updates[] = 'video = ?';
            $params[] = 'uploads/' . $row['video'];
        }
        
        if (!empty($updates)) {
            $params[] = $row['id'];
            $update_sql = "UPDATE aviar SET " . implode(', ', $updates) . " WHERE id = ?";
            
            $update_stmt = $conn->prepare($update_sql);
            if ($update_stmt->execute($params)) {
                $fixed_count++;
                error_log("Fixed paths for animal ID {$row['id']} (Tag: {$row['tagid']})");
            }
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => "Fixed $fixed_count animal records with missing uploads/ paths",
        'fixed_count' => $fixed_count,
        'total_checked' => count($results)
    ]);
    
} catch (Exception $e) {
    error_log("Error fixing image paths: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
