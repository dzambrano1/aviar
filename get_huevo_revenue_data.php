<?php
require_once './pdo_conexion.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Enable error reporting in PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get monthly huevo revenue data from ah_huevo table
    // Calculate revenue as ah_huevo_precio * ah_huevo_cantidad (monthly egg count)
    $query = "SELECT 
                DATE_FORMAT(ah_huevo_fecha, '%Y-%m') AS month,
                SUM(CASE 
                    WHEN ah_huevo_precio IS NOT NULL AND ah_huevo_cantidad IS NOT NULL 
                    THEN ah_huevo_precio * ah_huevo_cantidad 
                    ELSE 0 
                END) AS total_revenue,
                COUNT(*) AS total_records,
                SUM(CASE 
                    WHEN ah_huevo_cantidad IS NOT NULL 
                    THEN ah_huevo_cantidad 
                    ELSE 0 
                END) AS total_eggs_sold,
                AVG(CASE 
                    WHEN ah_huevo_precio IS NOT NULL 
                    THEN ah_huevo_precio 
                    ELSE 0 
                END) AS avg_price_per_egg
              FROM ah_huevo
              WHERE 
                ah_huevo_fecha IS NOT NULL AND 
                ah_huevo_fecha != '0000-00-00' AND
                ah_huevo_precio IS NOT NULL AND
                ah_huevo_cantidad IS NOT NULL
              GROUP BY DATE_FORMAT(ah_huevo_fecha, '%Y-%m')
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
    error_log('Error in get_huevo_revenue_data.php: ' . $e->getMessage());
}
?>