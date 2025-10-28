<?php
require_once './pdo_conexion.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Enable error reporting in PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get monthly discards revenue lost data from aviar table
    // Calculate revenue lost as descarte_peso * descarte_precio
    $query = "SELECT 
                DATE_FORMAT(descarte_fecha, '%Y-%m') AS month,
                SUM(CASE 
                    WHEN descarte_peso IS NOT NULL AND descarte_precio IS NOT NULL 
                    THEN descarte_peso * descarte_precio 
                    ELSE 0 
                END) AS discards_count,
                COUNT(*) AS total_discards,
                SUM(CASE 
                    WHEN descarte_peso IS NOT NULL 
                    THEN descarte_peso 
                    ELSE 0 
                END) AS total_weight_discarded,
                GROUP_CONCAT(DISTINCT tagid ORDER BY tagid SEPARATOR ', ') AS tagids
              FROM aviar
              WHERE 
                descarte_fecha IS NOT NULL AND 
                descarte_fecha != '0000-00-00' AND
                descarte_peso IS NOT NULL AND
                descarte_precio IS NOT NULL
              GROUP BY DATE_FORMAT(descarte_fecha, '%Y-%m')
              ORDER BY month ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output the data as JSON
    echo json_encode($monthlyData);
    
} catch (PDOException $e) {
    // Return error message
    echo json_encode([
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
    // Log the error
    error_log('Error in get_discards_data.php: ' . $e->getMessage());
}
?> 