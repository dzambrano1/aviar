<?php
// Include session check
require_once 'check_session.php';

// Include database connection
require_once './pdo_conexion.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Initialize response array
$animals = [];

try {
    // Initialize the mysqli connection
    $mysqli_conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$mysqli_conn) {
        throw new Exception('Failed to connect to database: ' . mysqli_connect_error());
    }
    
    // Debug: Check for any animals with price > 0
    $debug_query = "SELECT COUNT(*) as count FROM aviar WHERE precio_venta > 0";
    $debug_result = $mysqli_conn->query($debug_query);
    if ($debug_result && $debug_result->num_rows > 0) {
        $debug_row = $debug_result->fetch_assoc();
        error_log("Total animals with price > 0: " . $debug_row['count']);
    }
    
    // Debug: Check for all unique estatus values
    $status_query = "SELECT DISTINCT estatus FROM aviar";
    $status_result = $mysqli_conn->query($status_query);
    if ($status_result) {
        $status_values = [];
        while($status_row = $status_result->fetch_assoc()) {
            $status_values[] = $status_row['estatus'];
        }
        error_log("Unique estatus values: " . implode(", ", $status_values));
    }
    
    // Build the query with filters
    $query = "SELECT tagid, nombre, genero, raza, etapa, grupo, image, precio_venta, fecha_publicacion, estatus
              FROM aviar 
              WHERE precio_venta > 0 AND UPPER(estatus) = UPPER('Feria')";
    
    // Add filters if provided
    if (isset($_GET['genero']) && !empty($_GET['genero'])) {
        $genero = $mysqli_conn->real_escape_string($_GET['genero']);
        $query .= " AND genero = '$genero'";
    }
    
    if (isset($_GET['raza']) && !empty($_GET['raza'])) {
        $raza = $mysqli_conn->real_escape_string($_GET['raza']);
        $query .= " AND raza = '$raza'";
    }
    
    if (isset($_GET['etapa']) && !empty($_GET['etapa'])) {
        $etapa = $mysqli_conn->real_escape_string($_GET['etapa']);
        $query .= " AND etapa = '$etapa'";
    }
    
    if (isset($_GET['grupo']) && !empty($_GET['grupo'])) {
        $grupo = $mysqli_conn->real_escape_string($_GET['grupo']);
        $query .= " AND grupo = '$grupo'";
    }
    
    // Order by most recent publication date
    $query .= " ORDER BY fecha_publicacion DESC";
    
    // Debug: Log the query
    error_log("Query: " . $query);
    
    // Execute the query
    $result = $mysqli_conn->query($query);
    
    if ($result) {
        // Debug: Log the number of rows returned
        error_log("Number of rows: " . $result->num_rows);
        
        // Fetch BCV rate for calculating BS price
        $bcv_rate = 0;
        $bcv_query = "SELECT rate FROM bcv ORDER BY rate_date DESC LIMIT 1";
        $bcv_result = $mysqli_conn->query($bcv_query);
        
        if ($bcv_result && $bcv_result->num_rows > 0) {
            $bcv_row = $bcv_result->fetch_assoc();
            $bcv_rate = floatval($bcv_row['rate']);
        }
        
        // Fetch and process each animal
        while ($row = $result->fetch_assoc()) {
            // Calculate BS price if BCV rate is available
            $bs_price = null;
            if ($bcv_rate > 0) {
                $bs_price = $row['precio_venta'] * $bcv_rate;
            }
            
            // Format image path
            $image_path = !empty($row['image']) ? $row['image'] : './images/default_image.png';
            
            // Add animal to results
            $animals[] = [
                'tagid' => $row['tagid'],
                'nombre' => $row['nombre'],
                'genero' => $row['genero'],
                'raza' => $row['raza'],
                'etapa' => $row['etapa'],
                'grupo' => $row['grupo'],
                'image' => $image_path,
                'precio_venta' => $row['precio_venta'],
                'Bscash' => $bs_price,
                'stock' => 1, // Each animal is a unique item
                'fecha_publicacion' => $row['fecha_publicacion'],
                'estatus' => $row['estatus']
            ];
        }
        
        $result->free();
    } else {
        throw new Exception('Error executing query: ' . $mysqli_conn->error);
    }
    
    $mysqli_conn->close();
    
} catch (Exception $e) {
    // In case of error, return empty array with error message
    error_log('Error in get_feria_animals.php: ' . $e->getMessage());
    // Add the error to the response for debugging
    $animals = ['error' => $e->getMessage()];
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($animals);