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
    
    // Query to get coriza data ordered by date
    $query = "SELECT         
                ah_coriza_fecha as fecha, 
                ah_coriza_dosis as dosis,
                ah_coriza_costo as costo,
                ah_coriza_producto as vacuna,
                a.nombre as animal_nombre,
                ah_coriza_tagid as tagid
              FROM ah_coriza
              LEFT JOIN aviar a ON ah_coriza_tagid = a.tagid 
              ORDER BY ah_coriza_fecha DESC";
    
    // Fetch all records as associative array
    $result = $conn->query($query)->fetchAll(PDO::FETCH_ASSOC);
    
    // Output the result as JSON
    echo json_encode($result);
    
} catch (Exception $e) {
    // Return error as JSON
    echo json_encode(['error' => $e->getMessage()]);
}