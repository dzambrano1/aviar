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
$raza = isset($_POST['raza']) ? $conn->real_escape_string($_POST['raza']) : '';

if ($id > 0 && !empty($raza)) {
    $sql = "UPDATE aviar SET raza = '$raza' WHERE id = $id";
    if ($conn->query($sql) === TRUE) {
        echo json_encode(['success' => true, 'message' => 'Raza actualizada correctamente.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar: ' . $conn->error]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Datos inválidos proporcionados.']);
}

$conn->close();
?>