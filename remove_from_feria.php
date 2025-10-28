<?php
// Include session check
require_once 'check_session.php';
requireAdmin(); // Only admin can remove from feria

// Include database connection
require_once './pdo_conexion.php';

// Initialize response array
$response = [
    'success' => false,
    'message' => ''
];

// Check if request is POST and tagid is provided
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tagid'])) {
    $tagid = $_POST['tagid'];
    
    try {
        // Initialize the mysqli connection
        $mysqli_conn = mysqli_connect($servername, $username, $password, $dbname);
        if (!$mysqli_conn) {
            throw new Exception('Failed to connect to database: ' . mysqli_connect_error());
        }
        
        // Update the animal record to set precio to 0 (removing from feria)
        $update_sql = "UPDATE aviar SET precio_venta = 0, fecha_publicacion = NULL WHERE tagid = ?";
        $update_stmt = $mysqli_conn->prepare($update_sql);
        $update_stmt->bind_param("s", $tagid);
        
        if ($update_stmt->execute()) {
            if ($update_stmt->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = "Animal removido de la feria correctamente.";
            } else {
                $response['message'] = "No se encontró el animal o ya estaba removido de la feria.";
            }
        } else {
            $response['message'] = "Error al actualizar: " . $mysqli_conn->error;
        }
        
        $update_stmt->close();
        $mysqli_conn->close();
        
    } catch (Exception $e) {
        $response['message'] = "Error: " . $e->getMessage();
    }
} else {
    $response['message'] = "Datos inválidos o método incorrecto.";
}

// Return JSON response
header('Content-Type: application/json');
echo json_encode($response);