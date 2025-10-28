<?php
require_once './pdo_conexion.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Create database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get tagid parameter
    $tagid = $_GET['tagid'] ?? '';
    
    if (empty($tagid)) {
        throw new Exception("Tag ID es requerido");
    }

    // Query to get sales information for the animal
    $sql = "SELECT 
                tagid, 
                nombre, 
                DATE_FORMAT(fecha_venta, '%Y-%m-%d') as fecha_venta, 
                precio_venta, 
                peso_venta,
                estatus,
                image, 
                image2, 
                image3, 
                video
            FROM aviar 
            WHERE tagid = ?";
            
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $tagid);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 0) {
        throw new Exception("No se encontró el animal con el Tag ID especificado");
    }
    
    $data = $result->fetch_assoc();
    $stmt->close();
    
    // Return success response with data
    echo json_encode([
        "success" => true,
        "data" => $data
    ]);
    
    // Close connection
    $conn->close();

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
?>
