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
    
    // Query to get marek data ordered by date
    $query = "SELECT         
                ah_marek_fecha as fecha, 
                ah_marek_dosis as dosis,
                ah_marek_costo as costo,
                ah_marek_producto as vacuna,
                a.nombre as animal_nombre,
                ah_marek_tagid as tagid
              FROM ah_marek
              LEFT JOIN aviar a ON ah_marek_tagid = a.tagid 
              ORDER BY ah_marek_fecha DESC";
    
    // Fetch all records as associative array
    $result = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Output the result as JSON
    echo json_encode($result);
    
} catch (Exception $e) {
    // Return error as JSON
    echo json_encode(['error' => $e->getMessage()]);
}