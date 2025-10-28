<?php
require_once './pdo_conexion.php';

// Set response content type to JSON
header('Content-Type: application/json');

try {
    // Arrays to store the different distribution data
    $data = [
        'raza' => [],
        'grupo' => [],
        'estatus' => []
    ];
    
    // Query for Raza distribution
    $razaQuery = "
        SELECT 
            COALESCE(raza, 'No especificado') AS name,
            COUNT(*) AS count
        FROM 
            aviar
        GROUP BY 
            raza
        ORDER BY 
            count DESC
    ";
    
    $stmt = $conn->prepare($razaQuery);
    $stmt->execute();
    $data['raza'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Query for Grupo distribution
    $grupoQuery = "
        SELECT 
            COALESCE(grupo, 'No especificado') AS name,
            COUNT(*) AS count
        FROM 
            aviar
        GROUP BY 
            grupo
        ORDER BY 
            count DESC
    ";
    
    $stmt = $conn->prepare($grupoQuery);
    $stmt->execute();
    $data['grupo'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Query for Estatus distribution
    $estatusQuery = "
        SELECT 
            COALESCE(estatus, 'No especificado') AS name,
            COUNT(*) AS count
        FROM 
            aviar
        GROUP BY 
            estatus
        ORDER BY 
            count DESC
    ";
    
    $stmt = $conn->prepare($estatusQuery);
    $stmt->execute();
    $data['estatus'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Calculate total counts
    $totalQuery = "SELECT COUNT(*) AS total FROM aviar";
    $stmt = $conn->prepare($totalQuery);
    $stmt->execute();
    $totalResult = $stmt->fetch(PDO::FETCH_ASSOC);
    $total = $totalResult['total'];
    
    // Calculate percentages for each dataset
    foreach ($data as $key => &$items) {
        foreach ($items as &$item) {
            $item['count'] = (int)$item['count'];
            $item['percentage'] = $total > 0 ? round(($item['count'] / $total) * 100, 1) : 0;
        }
    }
    
    // Return success response with data
    echo json_encode([
        'success' => true,
        'data' => $data,
        'total' => $total
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