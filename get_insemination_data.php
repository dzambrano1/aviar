<?php
require_once './pdo_conexion.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Enable error reporting in PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get monthly insemination data from aviar table
    $query = "SELECT 
                DATE_FORMAT(inseminacion_fecha, '%Y-%m') AS month,
                COUNT(*) AS total_inseminations
              FROM aviar
              WHERE 
                inseminacion_fecha IS NOT NULL AND 
                inseminacion_fecha != '0000-00-00'
              GROUP BY DATE_FORMAT(inseminacion_fecha, '%Y-%m')
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
    error_log('Error in get_insemination_data.php: ' . $e->getMessage());
}
?> 