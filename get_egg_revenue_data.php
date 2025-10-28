<?php
// Include database connection
require_once './pdo_conexion.php';

// Set content type to JSON and allow CORS
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

try {
    // Verify connection is PDO
    if (!($conn instanceof PDO)) {
        throw new Exception("Error: La conexión no es una instancia de PDO");
    }
    
    // Enable PDO error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get monthly egg revenue data
    $query = "SELECT 
                CONCAT(YEAR(ah_egg_fecha), '-', MONTH(ah_egg_fecha)) as month_year,
                YEAR(ah_egg_fecha) as year,
                MONTH(ah_egg_fecha) as month,
                SUM(ah_egg_count) as total_eggs,
                AVG(ah_egg_precio) as avg_price,
                SUM(ah_egg_count * ah_egg_precio) as total_revenue
              FROM ah_egg
              GROUP BY YEAR(ah_egg_fecha), MONTH(ah_egg_fecha)
              ORDER BY YEAR(ah_egg_fecha), MONTH(ah_egg_fecha)";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    // Fetch all records as associative array
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Format data for chart
    $months = [];
    $revenues = [];
    
    foreach ($result as $row) {
        // Create readable month name (e.g., "Jan 2023")
        $monthName = date("M Y", mktime(0, 0, 0, $row['month'], 1, $row['year']));
        $months[] = $monthName;
        $revenues[] = floatval($row['total_revenue']);
    }
    
    // Output the formatted result as JSON
    echo json_encode([
        'months' => $months,
        'revenues' => $revenues,
        'rawData' => $result // Include raw data for debugging
    ]);
    
} catch (Exception $e) {
    // Return error as JSON
    echo json_encode(['error' => $e->getMessage()]);
}
?> 