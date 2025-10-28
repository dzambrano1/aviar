<?php
// Include database connection
require_once './pdo_conexion.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Verify connection is PDO
    if (!($conn instanceof PDO)) {
        throw new Exception("Error: La conexión no es una instancia de PDO");
    }
    
    // Enable PDO error mode
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query to get cbr data ordered by date
    $query = "SELECT         
                ah_brucelosis_fecha as fecha, 
                ah_brucelosis_dosis as dosis,
                ah_brucelosis_producto as producto,
                ah_brucelosis_costo as costo,
                v.nombre as animal_nombre,
                ah_brucelosis_tagid as tagid
                FROM ah_brucelosis
              LEFT JOIN aviar v ON ah_brucelosis_tagid = v.tagid 
              ORDER BY ah_brucelosis_fecha DESC";
    
    // Fetch all records as associative array
    $result = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Output the result as JSON
    echo json_encode($result);
    
} catch (Exception $e) {
    // Return error as JSON
    echo json_encode(['error' => $e->getMessage()]);
}
?>