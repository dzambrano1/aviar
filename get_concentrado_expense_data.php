<?php
require_once './pdo_conexion.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Enable error reporting in PDO
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to calculate concentrado expenses using new column structure
    // Formula: (ah_concentrado_fecha_fin - ah_concentrado_fecha_inicio) * ah_concentrado_racion * ah_concentrado_costo
    // Group by ah_concentrado_fecha_fin month
    $query = "SELECT 
                DATE_FORMAT(ah_concentrado_fecha_fin, '%Y-%m') AS month,
                SUM(
                    DATEDIFF(ah_concentrado_fecha_fin, ah_concentrado_fecha_inicio) * 
                    ah_concentrado_racion/1000 * 
                    ah_concentrado_costo
                ) AS total_expense,
                SUM(
                    DATEDIFF(ah_concentrado_fecha_fin, ah_concentrado_fecha_inicio) * 
                    ah_concentrado_racion
                ) AS total_consumption_kg,
                COUNT(*) AS total_records,
                COUNT(DISTINCT ah_concentrado_tagid) AS unique_animals
              FROM ah_concentrado
              WHERE 
                ah_concentrado_fecha_inicio IS NOT NULL AND 
                ah_concentrado_fecha_inicio != '0000-00-00' AND
                ah_concentrado_fecha_fin IS NOT NULL AND 
                ah_concentrado_fecha_fin != '0000-00-00' AND
                ah_concentrado_racion IS NOT NULL AND
                ah_concentrado_costo IS NOT NULL AND
                ah_concentrado_racion > 0 AND
                ah_concentrado_costo > 0 AND
                ah_concentrado_fecha_fin >= ah_concentrado_fecha_inicio
              GROUP BY DATE_FORMAT(ah_concentrado_fecha_fin, '%Y-%m')
              ORDER BY month ASC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $chartData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format the data with proper numeric values
    $formattedData = [];
    foreach ($chartData as $row) {
        $formattedData[] = [
            'month' => $row['month'],
            'total_expense' => round((float)$row['total_expense'], 2),
            'total_consumption_kg' => round((float)$row['total_consumption_kg'], 2),
            'total_records' => (int)$row['total_records'],
            'unique_animals' => (int)$row['unique_animals']
        ];
    }
    
    echo json_encode($formattedData);
    
} catch (PDOException $e) {
    // Return error message
    echo json_encode([
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
    
    // Log the error
    error_log('Error in get_concentrado_expense_data.php: ' . $e->getMessage());
}
?> 