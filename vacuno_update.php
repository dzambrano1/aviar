<?php
require_once './pdo_conexion.php';
// Disable error reporting in output
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json');

try {
    $conn = new mysqli($hostname, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get action parameter to determine operation type
    $action = $_POST['action'] ?? 'update';

    // Get form data
    $tagid = $_POST['tagid'] ?? '';
    $nombre = $_POST['nombre'] ?? '';
    $fecha_nacimiento = $_POST['fecha_nacimiento'] ?? '';
    $fecha_compra = $_POST['fecha_compra'] ?? '';
    $genero = $_POST['genero'] ?? '';
    $etapa = $_POST['etapa'] ?? '';
    $raza = $_POST['raza'] ?? '';
    $grupo = $_POST['grupo'] ?? '';
    $estatus = $_POST['estatus'] ?? '';
    $peso_compra = $_POST['peso_compra'] ?? $_POST['peso'] ?? '';
    $precio_compra = $_POST['precio_compra'] ?? $_POST['precio'] ?? '';

    // Validate required fields based on action
    if (empty($tagid)) {
        throw new Exception("Tag ID es requerido");
    }

    if ($action === 'update' && (empty($nombre) || empty($fecha_nacimiento))) {
        throw new Exception("Nombre y fecha de nacimiento son requeridos para actualizar");
    }

    // Process image uploads for update and insert actions
    $update_fields = [];
    $params = [];
    $types = "";
    
    if ($action === 'update' || $action === 'insert') {
        // Handle main image upload if present
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';

            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate unique filename
            $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
            $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            
            // Full path for file storage
            $targetPath = $uploadDir . $newFileName;

            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($fileExtension, $allowedTypes)) {
                throw new Exception("Tipo de archivo no permitido");
            }
            
            // Move uploaded file
            if (!move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
                throw new Exception("Error al subir la imagen");
            }
            
            // Store the path in the update fields
            $update_fields[] = "image = ?";
            $params[] = $targetPath;
            $types .= "s";
        }
        
        // Handle image2 upload if present
        if (isset($_FILES['image2']) && $_FILES['image2']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';

            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate unique filename
            $fileExtension = strtolower(pathinfo($_FILES['image2']['name'], PATHINFO_EXTENSION));
            $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            
            // Full path for file storage
            $targetPath = $uploadDir . $newFileName;

            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($fileExtension, $allowedTypes)) {
                throw new Exception("Tipo de archivo no permitido para imagen 2");
            }
            
            // Move uploaded file
            if (!move_uploaded_file($_FILES['image2']['tmp_name'], $targetPath)) {
                throw new Exception("Error al subir la imagen 2");
            }
            
            // Store the path in the update fields
            $update_fields[] = "image2 = ?";
            $params[] = $targetPath;
            $types .= "s";
        }
        
        // Handle image3 upload if present
        if (isset($_FILES['image3']) && $_FILES['image3']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/';

            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate unique filename
            $fileExtension = strtolower(pathinfo($_FILES['image3']['name'], PATHINFO_EXTENSION));
            $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            
            // Full path for file storage
            $targetPath = $uploadDir . $newFileName;

            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
            if (!in_array($fileExtension, $allowedTypes)) {
                throw new Exception("Tipo de archivo no permitido para imagen 3");
            }
            
            // Move uploaded file
            if (!move_uploaded_file($_FILES['image3']['tmp_name'], $targetPath)) {
                throw new Exception("Error al subir la imagen 3");
            }
            
            // Store the path in the update fields
            $update_fields[] = "image3 = ?";
            $params[] = $targetPath;
            $types .= "s";
        }
        
        // Handle video upload if present
        if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/videos/';

            // Create directory if it doesn't exist
            if (!file_exists($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }

            // Generate unique filename
            $fileExtension = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
            $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
            
            // Full path for file storage
            $targetPath = $uploadDir . $newFileName;

            // Validate file type
            $allowedTypes = ['mp4', 'webm', 'ogg', 'mov'];
            if (!in_array($fileExtension, $allowedTypes)) {
                throw new Exception("Tipo de archivo de video no permitido");
            }
            
            // Move uploaded file
            if (!move_uploaded_file($_FILES['video']['tmp_name'], $targetPath)) {
                throw new Exception("Error al subir el video");
            }
            
            // Store the path in the update fields
            $update_fields[] = "video = ?";
            $params[] = $targetPath;
            $types .= "s";
        }
    }

    // Handle different operations based on action
    switch ($action) {
        case 'insert':
            // Check if record already exists
            $checkQuery = "SELECT COUNT(*) as count FROM vacuno WHERE tagid = ?";
            $checkStmt = $conn->prepare($checkQuery);
            $checkStmt->bind_param("s", $tagid);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            $checkStmt->close();
            
            if ($row['count'] > 0) {
                // Animal exists, update purchase fields
                $update_fields[] = "fecha_compra = ?";
                $update_fields[] = "peso_compra = ?";
                $update_fields[] = "precio_compra = ?";
                
                $params[] = $fecha_compra;
                $params[] = $peso_compra;
                $params[] = $precio_compra;
                
                $types .= "sss";
                
                // Add tagid for WHERE clause
                $params[] = $tagid;
                $types .= "s";
                
                // Create update query
                $sql = "UPDATE vacuno SET " . implode(", ", $update_fields) . " WHERE tagid = ?";
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $conn->error);
                }
                
                // Bind parameters
                $stmt->bind_param($types, ...$params);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar el registro: " . $stmt->error);
                }
                
                $stmt->close();
                
                echo json_encode([
                    "success" => true,
                    "message" => "Registro de compra agregado exitosamente",
                    "redirect" => "vacuno_register_compras.php"
                ]);
            } else {
                // Animal doesn't exist, insert new record (basic fields + purchase fields)
                $fields = ["tagid", "nombre", "fecha_nacimiento", "fecha_compra", "genero", "etapa", "raza", "grupo", "estatus", "peso_compra", "precio_compra"];
                $placeholders = array_fill(0, count($fields), "?");
                
                foreach ($update_fields as $field) {
                    $field_name = substr($field, 0, strpos($field, " ="));
                    $fields[] = $field_name;
                    $placeholders[] = "?";
                }
                
                $sql = "INSERT INTO vacuno (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $placeholders) . ")";
                
                $insert_params = [$tagid, $nombre, $fecha_nacimiento, $fecha_compra, $genero, $etapa, $raza, $grupo, $estatus, $peso_compra, $precio_compra];
                $insert_params = array_merge($insert_params, $params);
                
                $insert_types = "sssssssssss" . $types;
                
                $stmt = $conn->prepare($sql);
                if (!$stmt) {
                    throw new Exception("Error en la preparación de la consulta: " . $conn->error);
                }
                
                // Bind parameters
                $stmt->bind_param($insert_types, ...$insert_params);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error al insertar el registro: " . $stmt->error);
                }
                
                $stmt->close();
                
                echo json_encode([
                    "success" => true,
                    "message" => "Animal agregado exitosamente con registro de compra",
                    "redirect" => "vacuno_register_compras.php"
                ]);
            }
            break;
            
        case 'update':
            // Update all fields
            $update_fields[] = "nombre = ?";
            $update_fields[] = "fecha_nacimiento = ?";
            $update_fields[] = "fecha_compra = ?";
            $update_fields[] = "genero = ?";
            $update_fields[] = "etapa = ?";
            $update_fields[] = "raza = ?";
            $update_fields[] = "grupo = ?";
            $update_fields[] = "estatus = ?";
            $update_fields[] = "peso_compra = ?";
            $update_fields[] = "precio_compra = ?";
            
            // Add the values for these fields
            $params[] = $nombre;
            $params[] = $fecha_nacimiento;
            $params[] = $fecha_compra;
            $params[] = $genero;
            $params[] = $etapa;
            $params[] = $raza;
            $params[] = $grupo;
            $params[] = $estatus;
            $params[] = $peso_compra;
            $params[] = $precio_compra;
            
            // Add the types for these fields
            $types .= "ssssssssss";
            
            // Add tagid at the end for the WHERE clause
            $params[] = $tagid;
            $types .= "s";

            // Create the SQL query
            $sql = "UPDATE vacuno SET " . implode(", ", $update_fields) . " WHERE tagid = ?";
            
            // Prepare and execute the update
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }
            
            // Bind parameters dynamically
            $stmt->bind_param($types, ...$params);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al actualizar el registro: " . $stmt->error);
            }
            
            $stmt->close();
            
            echo json_encode([
                "success" => true,
                "message" => "Animal actualizado exitosamente",
                "redirect" => "vacuno_register_compras.php"
            ]);
            break;
            
        case 'delete':
            // Set purchase fields to NULL (not deleting the entire animal record)
            $sql = "UPDATE vacuno SET fecha_compra = NULL, peso_compra = NULL, precio_compra = NULL WHERE tagid = ?";
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error en la preparación de la consulta: " . $conn->error);
            }
            
            $stmt->bind_param("s", $tagid);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al eliminar el registro de compra: " . $stmt->error);
            }
            
            if ($stmt->affected_rows === 0) {
                throw new Exception("No se encontró el registro o no se realizaron cambios");
            }
            
            $stmt->close();
            
            echo json_encode([
                "success" => true,
                "message" => "Registro de compra eliminado exitosamente",
                "redirect" => "vacuno_register_compras.php"
            ]);
            break;
            
        default:
            throw new Exception("Acción no válida");
    }
    
    // Close connection
    $conn->close();

} catch (Exception $e) {
    // Log error to file instead of output
    error_log("Error in vacuno_update.php: " . $e->getMessage());
    
    // Return error response
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}

