<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit();
}

require_once './pdo_conexion.php';

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input || !isset($input['query'])) {
        throw new Exception('Query parameter is required');
    }
    
    $query = trim($input['query']);
    
    if (empty($query)) {
        throw new Exception('Query cannot be empty');
    }
    
    // Search for animal by tagid or name
    $sql = "SELECT id, tagid, nombre, genero, raza, etapa, grupo, estatus, fecha_nacimiento, image, image2, image3, video 
            FROM aviar 
            WHERE tagid = ? OR nombre LIKE ? 
            ORDER BY tagid ASC 
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    if ($stmt === false) {
        throw new Exception('Failed to prepare search statement');
    }
    
    // Bind parameters: exact tagid match and name like search
    $searchPattern = '%' . $query . '%';
    $stmt->execute([$query, $searchPattern]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$result) {
        // No animal found
        echo json_encode([
            'success' => true,
            'animal' => null,
            'message' => 'No se encontró el animal'
        ]);
        exit();
    }
    
    // Animal found - return the data
    echo json_encode([
        'success' => true,
        'animal' => $result,
        'message' => 'Animal encontrado'
    ]);
    
} catch (Exception $e) {
    // Log the error
    error_log("aviar_search.php error: " . $e->getMessage());
    
    // Return error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
