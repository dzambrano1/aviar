<?php
require_once './pdo_conexion.php';

// Set content type to JSON
header('Content-Type: application/json');

// Enable CORS if needed
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

try {
    // Get search query from GET parameter
    if (!isset($_GET['query']) || empty(trim($_GET['query']))) {
        throw new Exception('Parámetro de búsqueda requerido');
    }
    
    $query = trim($_GET['query']);
    
    // Connect to database
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    
    if (!$conn) {
        throw new Exception('Error de conexión a la base de datos: ' . mysqli_connect_error());
    }
    
    // Set charset
    mysqli_set_charset($conn, "utf8");
    
    // Prepare SQL query to search by tagid or name
    $sql = "SELECT tagid, nombre, genero, raza, etapa, grupo, estatus, fecha_nacimiento 
            FROM aviar 
            WHERE (tagid LIKE ? OR nombre LIKE ?) 
            AND estatus = 'Activo'
            ORDER BY 
                CASE 
                    WHEN tagid = ? THEN 1
                    WHEN nombre = ? THEN 2
                    WHEN tagid LIKE ? THEN 3
                    WHEN nombre LIKE ? THEN 4
                    ELSE 5
                END
            LIMIT 1";
    
    $stmt = $conn->prepare($sql);
    
    if (!$stmt) {
        throw new Exception('Error preparando la consulta: ' . $conn->error);
    }
    
    // Bind parameters for exact match and partial match
    $searchTerm = "%{$query}%";
    $stmt->bind_param('ssssss', $searchTerm, $searchTerm, $query, $query, $searchTerm, $searchTerm);
    
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No se encontró ningún animal activo con Tag ID o nombre: '{$query}'");
    }
    
    $animal = $result->fetch_assoc();
    
    // Close connections
    $stmt->close();
    $conn->close();
    
    // Return success response
    echo json_encode([
        'success' => true,
        'animal' => $animal,
        'message' => 'Animal encontrado exitosamente'
    ]);
    
} catch (Exception $e) {
    // Return error response
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage(),
        'error' => true
    ]);
} catch (Error $e) {
    // Return fatal error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error interno del servidor',
        'error' => true,
        'debug' => $e->getMessage()
    ]);
}
?> 