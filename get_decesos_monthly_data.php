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
    
    // Query to get monthly aggregated death data from ah_decesos table
    $query = "
        SELECT 
            DATE_FORMAT(ah_decesos_fecha, '%Y-%m') AS month_year,
            SUM(ah_decesos_cantidad) AS death_count,
            GROUP_CONCAT(DISTINCT ah_decesos_causa SEPARATOR ', ') AS causes
        FROM 
            ah_decesos
        WHERE 
            ah_decesos_fecha IS NOT NULL 
            AND ah_decesos_fecha != '0000-00-00'
        GROUP BY 
            DATE_FORMAT(ah_decesos_fecha, '%Y-%m')
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
            'message' => 'No monthly death data found',
            'data' => []
        ]);
        exit;
    }
    
    // Process monthly data to include detailed cause breakdown
    $finalData = [];
    
    foreach ($monthlyResults as $monthData) {
        // Get more detailed cause breakdown for this month
        $detailQuery = "
            SELECT 
                ah_decesos_causa AS cause,
                SUM(ah_decesos_cantidad) AS count
            FROM 
                ah_decesos
            WHERE 
                DATE_FORMAT(ah_decesos_fecha, '%Y-%m') = :month_year
                AND ah_decesos_fecha IS NOT NULL
                AND ah_decesos_fecha != '0000-00-00'
            GROUP BY 
                ah_decesos_causa
            ORDER BY 
                count DESC
        ";
        
        $detailStmt = $conn->prepare($detailQuery);
        $detailStmt->bindParam(':month_year', $monthData['month_year'], PDO::PARAM_STR);
        $detailStmt->execute();
        $causeBreakdown = $detailStmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Format date for display (MM/YYYY)
        $dateParts = explode('-', $monthData['month_year']);
        $displayDate = $dateParts[1] . '/' . $dateParts[0];
        
        // Add the month data with cause breakdown
        $finalData[] = [
            'month_year' => $monthData['month_year'],
            'display_date' => $displayDate,
            'death_count' => (int)$monthData['death_count'],
            'causes_summary' => $monthData['causes'],
            'cause_breakdown' => $causeBreakdown
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
    error_log("Database Error in get_deceso_monthly_data.php: " . $e->getMessage());
    
    // Return error message
    echo json_encode([
        'error' => true,
        'message' => 'Database error: ' . $e->getMessage(),
        'data' => []
    ]);
} catch (Exception $e) {
    // Log the error
    error_log("General Error in get_deceso_monthly_data.php: " . $e->getMessage());
    
    // Return error message
    echo json_encode([
        'error' => true,
        'message' => 'Error: ' . $e->getMessage(),
        'data' => []
    ]);
}