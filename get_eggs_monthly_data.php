<?php
// Include database connection
require_once './pdo_conexion.php';

// Set headers for JSON response
header('Content-Type: application/json');

try {
    // Create a new PDO connection
    $pdo = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // First check if the table exists and has data
    $checkTableSql = "SELECT COUNT(*) as count FROM information_schema.tables 
                      WHERE table_schema = ? AND table_name = 'ah_egg'";
    $checkStmt = $pdo->prepare($checkTableSql);
    $checkStmt->execute([$dbname]);
    $tableExists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if (!$tableExists) {
        echo json_encode(['error' => 'La tabla ah_egg no existe en la base de datos.']);
        exit;
    }
    
    // Check if there's any data in the table
    $countSql = "SELECT COUNT(*) as count FROM ah_egg";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute();
    $rowCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($rowCount === 0) {
        echo json_encode([]);
        exit;
    }
    
    // Verify the fields exist
    $checkFieldsSql = "SHOW COLUMNS FROM ah_egg WHERE Field IN ('ah_egg_fecha', 'ah_egg_count', 'ah_egg_precio')";
    $checkFieldsStmt = $pdo->prepare($checkFieldsSql);
    $checkFieldsStmt->execute();
    $fields = $checkFieldsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredFields = ['ah_egg_fecha', 'ah_egg_count', 'ah_egg_precio'];
    $foundFields = array_column($fields, 'Field');
    $missingFields = array_diff($requiredFields, $foundFields);
    
    if (!empty($missingFields)) {
        echo json_encode([
            'error' => 'Faltan campos requeridos en la tabla ah_egg: ' . implode(', ', $missingFields)
        ]);
        exit;
    }
    
    // SQL query to get monthly egg revenue
    $sql = "SELECT 
                DATE_FORMAT(ah_egg_fecha, '%Y-%m') AS month_key,
                DATE_FORMAT(ah_egg_fecha, '%b %Y') AS month_label,
                AVG(ah_egg_count * ah_egg_precio) AS average_revenue,
                SUM(ah_egg_count * ah_egg_precio) AS total_revenue,
                COUNT(*) AS record_count
            FROM 
                ah_egg
            GROUP BY 
                month_key, month_label
            ORDER BY 
                month_key";
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
    
    // Fetch all results
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // If no results after grouping, check for any records with null values
    if (empty($results)) {
        $checkNullsSql = "SELECT 
                            COUNT(*) as count,
                            SUM(CASE WHEN ah_egg_fecha IS NULL THEN 1 ELSE 0 END) as null_dates,
                            SUM(CASE WHEN ah_egg_count IS NULL THEN 1 ELSE 0 END) as null_counts,
                            SUM(CASE WHEN ah_egg_precio IS NULL THEN 1 ELSE 0 END) as null_prices
                         FROM ah_egg";
        $checkNullsStmt = $pdo->prepare($checkNullsSql);
        $checkNullsStmt->execute();
        $nullsData = $checkNullsStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'error' => 'No hay datos para agrupar. Registro de valores nulos: ' .
                      'Fechas nulas: ' . $nullsData['null_dates'] . ', ' .
                      'Cantidades nulas: ' . $nullsData['null_counts'] . ', ' .
                      'Precios nulos: ' . $nullsData['null_prices']
        ]);
        exit;
    }
    
    // Create array for chart data
    $chartData = [];
    
    foreach ($results as $row) {
        $chartData[] = [
            'month' => $row['month_label'],
            'averageRevenue' => floatval($row['average_revenue']),
            'totalRevenue' => floatval($row['total_revenue']),
            'recordCount' => intval($row['record_count']),
            'rawMonth' => $row['month_key']
        ];
    }
    
    // Return the JSON data
    echo json_encode($chartData);
    
} catch(PDOException $e) {
    // Return detailed error message
    echo json_encode([
        'error' => 'Database error: ' . $e->getMessage(),
        'code' => $e->getCode(),
        'trace' => $e->getTraceAsString()
    ]);
}
?> 