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
    
    // Query to get lombrices data ordered by date
    $query = "SELECT         
                ah_lombrices_fecha as fecha, 
                ah_lombrices_dosis as dosis,
                ah_lombrices_costo as costo,
                ah_lombrices_producto as vacuna,
                v.nombre as animal_nombre,
                ah_lombrices_tagid as tagid
              FROM ah_lombrices
              LEFT JOIN aviar v ON ah_lombrices_tagid = v.tagid 
              ORDER BY ah_lombrices_fecha DESC";
    
    // Fetch all records as associative array
    $result = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Output the result as JSON
    echo json_encode($result);
    
} catch (Exception $e) {
    // Return error as JSON
    echo json_encode(['error' => $e->getMessage()]);
}
?> 