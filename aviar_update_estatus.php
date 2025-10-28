<?php
require_once './pdo_conexion.php';  // Go up one directory since inventario_aviar.php is in the aviar folder
// Now you can use $conn for database queries


$conn = mysqli_connect($servername, $username, $password, $dbname);

if (!$conn) {
  die("Connection failed: " . mysqli_connect_error());
}

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get POST data
$id = isset($_POST['id']) ? intval($_POST['id']) : 0;
$estatus = isset($_POST['estatus']) ? $conn->real_escape_string($_POST['estatus']) : '';

// Validate input
if ($id > 0 && !empty($estatus)) {
    // Prepare the SQL statement to prevent SQL injection
    $stmt = $conn->prepare("UPDATE aviar SET estatus = ? WHERE id = ?");
    if ($stmt) {
        $stmt->bind_param("si", $estatus, $id);
        
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Estatus actualizado correctamente.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $stmt->error]);
        }
        
        $stmt->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error de preparación de la consulta: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos proporcionados.']);
}

$conn->close();
?> 