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
                      WHERE table_schema = ? AND table_name = 'ah_marek'";
    $checkStmt = $pdo->prepare($checkTableSql);
    $checkStmt->execute([$dbname]);
    $tableExists = $checkStmt->fetch(PDO::FETCH_ASSOC)['count'] > 0;
    
    if (!$tableExists) {
        echo json_encode(['error' => 'La tabla ah_marek no existe en la base de datos.']);
        exit;
    }
    
    // Check if there's any data in the table
    $countSql = "SELECT COUNT(*) as count FROM ah_marek";
    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute();
    $rowCount = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    if ($rowCount === 0) {
        echo json_encode([]);
        exit;
    }
    
    // Verify the fields exist
    $checkFieldsSql = "SHOW COLUMNS FROM ah_marek WHERE Field IN ('ah_marek_fecha', 'ah_marek_costo', 'ah_marek_dosis')";
    $checkFieldsStmt = $pdo->prepare($checkFieldsSql);
    $checkFieldsStmt->execute();
    $fields = $checkFieldsStmt->fetchAll(PDO::FETCH_ASSOC);
    
    $requiredFields = ['ah_marek_fecha', 'ah_marek_costo', 'ah_marek_dosis'];
    $foundFields = array_column($fields, 'Field');
    $missingFields = array_diff($requiredFields, $foundFields);
    
    if (!empty($missingFields)) {
        echo json_encode([
            'error' => 'Faltan campos requeridos en la tabla ah_marek: ' . implode(', ', $missingFields)
        ]);
        exit;
    }
    
    // SQL query to get monthly marek costs
    $sql = "SELECT 
                DATE_FORMAT(ah_marek_fecha, '%Y-%m') AS month_key,
                DATE_FORMAT(ah_marek_fecha, '%b %Y') AS month_label,
                SUM(ah_marek_costo * ah_marek_dosis) AS total_cost
            FROM 
                ah_marek
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
                            SUM(CASE WHEN ah_marek_fecha IS NULL THEN 1 ELSE 0 END) as null_dates,
                            SUM(CASE WHEN ah_marek_costo IS NULL THEN 1 ELSE 0 END) as null_costs,
                            SUM(CASE WHEN ah_marek_dosis IS NULL THEN 1 ELSE 0 END) as null_doses
                         FROM ah_marek";
        $checkNullsStmt = $pdo->prepare($checkNullsSql);
        $checkNullsStmt->execute();
        $nullsData = $checkNullsStmt->fetch(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'error' => 'No hay datos para agrupar. Registro de valores nulos: ' .
                      'Fechas nulas: ' . $nullsData['null_dates'] . ', ' .
                      'Costos nulos: ' . $nullsData['null_costs'] . ', ' .
                      'Dosis nulas: ' . $nullsData['null_doses']
        ]);
        exit;
    }
    
    // Create array for chart data
    $chartData = [];
    
    foreach ($results as $row) {
        $chartData[] = [
            'month' => $row['month_label'],
            'cost' => floatval($row['total_cost']),
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