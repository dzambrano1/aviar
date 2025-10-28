<?php
require_once './pdo_conexion.php';

try {
    // Enable PDO error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Query to get monthly averages of (costo × racion)
    $query = "
        SELECT 
            DATE_FORMAT(ah_concentrado_fecha, '%Y-%m') as month,
            a.tagid,
            a.nombre as animal_nombre,
            ROUND(AVG(ah_concentrado_costo * ah_concentrado_racion), 2) as average_cost
        FROM 
            ah_concentrado c
            LEFT JOIN aviar a ON c.ah_concentrado_tagid = a.tagid 
        GROUP BY 
            DATE_FORMAT(ah_concentrado_fecha, '%Y-%m'),
            a.tagid,
            a.nombre
        ORDER BY 
            month ASC, 
            a.tagid ASC
    ";

    $stmt = $conn->prepare($query);
    $stmt->execute();
    $data = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Format the data for the chart
    $formattedData = array_map(function($row) {
        return [
            'fecha' => $row['month'] . '-01', // Add day to make it a valid date
            'tagid' => $row['tagid'],
            'animal_nombre' => $row['animal_nombre'],
            'ah_concentrado_cantidad' => $row['average_cost']
        ];
    }, $data);

    // Return JSON response
    header('Content-Type: application/json');
    echo json_encode($formattedData);

} catch (PDOException $e) {
    // Return error response
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}