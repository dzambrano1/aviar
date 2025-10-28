<?php
require_once './pdo_conexion.php';

$conn = new mysqli($servername, $username, $password, $dbname);

header('Content-Type: application/json');

try {
    // Get form data
    $tagid = $_POST['tagid'];
    $nombre = $_POST['nombre'];
    $fecha_nacimiento = $_POST['fecha_nacimiento'];
    $genero = $_POST['genero'];
    $raza = $_POST['raza'];
    $etapa = $_POST['etapa'];
    $grupo = $_POST['grupo'];
    $estatus = $_POST['estatus'];
    $fecha_compra = $_POST['fecha_compra'];

    // Handle image upload
    $imagen = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = './images/';
        $fileExtension = pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION);
        $fileName = uniqid() . '.' . $fileExtension;
        $uploadFile = $uploadDir . $fileName;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadFile)) {
            $imagen = $fileName;
        }
    }

    // Prepare update query
    $sql = "UPDATE aviar SET 
            nombre = ?, 
            fecha_nacimiento = ?,
            genero = ?,
            raza = ?,
            etapa = ?,
            grupo = ?,
            estatus = ?,
            fecha_compra = ?";
    
    // Add image to update if new one was uploaded
    if ($imagen) {
        $sql .= ", imagen = ?";
    }
    
    $sql .= " WHERE tagid = ?";

    $stmt = $conn->prepare($sql);

    // Bind parameters
    if ($imagen) {
        $stmt->bind_param("sssssssss", 
            $nombre, $fecha_nacimiento, $genero, $raza, 
            $etapa, $grupo, $estatus, $fecha_compra, $imagen, $tagid
        );
    } else {
        $stmt->bind_param("ssssssss", 
            $nombre, $fecha_nacimiento, $genero, $raza, 
            $etapa, $grupo, $estatus, $fecha_compra, $tagid
        );
    }

    if ($stmt->execute()) {
        echo json_encode(['success' => true]);
    } else {
        throw new Exception($stmt->error);
    }

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}

$conn->close();
?> 