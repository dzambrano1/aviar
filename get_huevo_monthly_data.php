<?php
require_once './pdo_conexion.php';

// Set response content type to JSON
header('Content-Type: application/json');

try {
    // Query to get monthly egg production data
    $query = "
        SELECT 
            DATE_FORMAT(ah_huevo_fecha, '%Y-%m') AS month_year,
            DATE_FORMAT(ah_huevo_fecha, '%m/%Y') AS display_date,
            SUM(ah_huevo_cantidad) AS egg_count,
            SUM(ah_huevo_cantidad * ah_huevo_precio) AS total_revenue
        FROM 
            ah_huevo
        WHERE 
            ah_huevo_fecha IS NOT NULL
        GROUP BY 
            month_year, display_date
        ORDER BY 
            month_year ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $monthlyData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format results for proper JSON encoding
    foreach ($monthlyData as &$item) {
        $item['egg_count'] = (int)$item['egg_count'];
        $item['total_revenue'] = (float)$item['total_revenue'];
    }
    
    // Return success response with data
    echo json_encode([
        'success' => true,
        'data' => $monthlyData
    ]);
    
} catch (PDOException $e) {
    // Return error response
    echo json_encode([
        'success' => false,
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
}
?> 