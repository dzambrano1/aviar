<?php
header('Content-Type: application/json');
require_once './pdo_conexion.php';

try {
    // Check if connection is a valid PDO instance
    if (!($conn instanceof PDO)) {
        throw new Exception("Error: Database connection is not a valid PDO instance");
    }
    
    // Enable PDO error mode to get better error messages
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get monthly aggregated discount value data from aviar table
    $query = "
        SELECT 
            DATE_FORMAT(descarte_fecha, '%Y-%m') AS month_year,
            COUNT(DISTINCT tagid) AS discount_count,
            SUM(descarte_precio * descarte_peso) AS total_value,
            AVG(descarte_precio * descarte_peso) AS average_value,
            MAX(descarte_precio * descarte_peso) AS max_value,
            MIN(descarte_precio * descarte_peso) AS min_value
        FROM 
            aviar
        WHERE 
            descarte_fecha IS NOT NULL 
            AND descarte_fecha != '0000-00-00'
            AND descarte_precio IS NOT NULL 
            AND descarte_precio > 0
            AND descarte_peso IS NOT NULL 
            AND descarte_peso > 0
        GROUP BY 
            DATE_FORMAT(descarte_fecha, '%Y-%m')
        ORDER BY 
            month_year ASC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    // Fetch all results as an associative array
    $monthlyResults = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if data was found
    if (count($monthlyResults) === 0) {
        // Return empty array with a message
        echo json_encode([
            'error' => false,
            'message' => 'No monthly discount data found',
            'data' => []
        ]);
        exit;
    }
    
    // Process monthly data
    $finalData = [];
    
    foreach ($monthlyResults as $monthData) {
        // Format date for display (MM/YYYY)
        $dateParts = explode('-', $monthData['month_year']);
        $displayDate = $dateParts[1] . '/' . $dateParts[0];
        
        // Get animal details for this month
        $detailQuery = "
            SELECT 
                tagid,
                nombre,
                descarte_precio,
                descarte_peso,
                (descarte_precio * descarte_peso) AS value
            FROM 
                aviar
            WHERE 
                DATE_FORMAT(descarte_fecha, '%Y-%m') = :month_year
                AND descarte_fecha IS NOT NULL
                AND descarte_fecha != '0000-00-00'
                AND descarte_precio IS NOT NULL 
                AND descarte_precio > 0
                AND descarte_peso IS NOT NULL 
                AND descarte_peso > 0
            ORDER BY 
                (descarte_precio * descarte_peso) DESC
        ";
        
        $detailStmt = $conn->prepare($detailQuery);
        $detailStmt->bindParam(':month_year', $monthData['month_year'], PDO::PARAM_STR);
        $detailStmt->execute();
        $animalDetails = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Add the month data with breakdown
        $finalData[] = [
            'month_year' => $monthData['month_year'],
            'display_date' => $displayDate,
            'discount_count' => (int)$monthData['discount_count'],
            'total_value' => (float)$monthData['total_value'],
            'average_value' => (float)$monthData['average_value'],
            'max_value' => (float)$monthData['max_value'],
            'min_value' => (float)$monthData['min_value'],
            'animal_details' => $animalDetails
        ];
    }
    
    // Return successful response with data
    echo json_encode([
        'error' => false,
        'message' => 'Success',
        'data' => $finalData
    ]);

} catch (PDOException $e) {
    // Log the error
    error_log("Database Error in get_descarte_monthly_data.php: " . $e->getMessage());
    
    // Return error message
    echo json_encode([
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage(),
        'data' => []
    ]);
} catch (Exception $e) {
    // Log the error
    error_log("General Error in get_descarte_monthly_data.php: " . $e->getMessage());
    
    // Return error message
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => []
    ]);
}