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
    
    // Query to get weight data ordered by date
    $query = "SELECT                 
                ah_egg_fecha as fecha, 
                ah_egg_count as egg,
                v.nombre as animal_nombre,
                ah_egg_tagid as tagid
              FROM ah_egg
              LEFT JOIN aviar v ON ah_egg_tagid = v.tagid 
              ORDER BY ah_egg_fecha DESC";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    
    // Fetch all records as associative array
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Output the result as JSON
    echo json_encode($result);
    
} catch (Exception $e) {
    // Return error as JSON
    echo json_encode(['error' => $e->getMessage()]);
}
?> 