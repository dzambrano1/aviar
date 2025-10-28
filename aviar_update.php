<?php
require_once './pdo_conexion.php';

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Create database connection
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($conn->connect_error) {
        throw new Exception("Connection failed: " . $conn->connect_error);
    }

    // Get action parameter
    $action = $_POST['action'] ?? 'insert';

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
    $peso = $_POST['peso'] ?? '';
    $precio = $_POST['precio'] ?? '';
    $poblacion_compra = $_POST['poblacion'] ?? '';
    $cantidad = $_POST['cantidad'] ?? '';

    // Debug logging for update action
    if ($action === 'update') {
        error_log("=== UPDATE ACTION DEBUG ===");
        error_log("POST data received: " . print_r($_POST, true));
        error_log("FILES data received: " . print_r($_FILES, true));
        error_log("Update action received - tagid: $tagid, nombre: $nombre, fecha_nacimiento: $fecha_nacimiento, genero: $genero, etapa: $etapa, raza: $raza, grupo: $grupo, estatus: $estatus, peso: $peso, precio: $precio, poblacion_compra: $poblacion_compra, cantidad: $cantidad");
        error_log("=== END UPDATE DEBUG ===");
    }

    // Process image uploads
    $image_paths = ['', '', '', '']; // [image, image2, image3, video]
    $upload_fields = [];
    $upload_params = [];
    $upload_types = '';
    
    // Handle main image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $targetPath = $uploadDir . $newFileName;
        
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExtension, $allowedTypes)) {
            throw new Exception("Tipo de archivo no permitido para imagen 1");
        }
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $targetPath)) {
            $image_paths[0] = $targetPath;
            $upload_fields[] = "image = ?";
            $upload_params[] = $targetPath;
            $upload_types .= "s";
        }
    }
    
    // Handle image2 upload
    if (isset($_FILES['image2']) && $_FILES['image2']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['image2']['name'], PATHINFO_EXTENSION));
        $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $targetPath = $uploadDir . $newFileName;
        
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExtension, $allowedTypes)) {
            throw new Exception("Tipo de archivo no permitido para imagen 2");
        }
        
        if (move_uploaded_file($_FILES['image2']['tmp_name'], $targetPath)) {
            $image_paths[1] = $targetPath;
            $upload_fields[] = "image2 = ?";
            $upload_params[] = $targetPath;
            $upload_types .= "s";
        }
    }
    
    // Handle image3 upload
    if (isset($_FILES['image3']) && $_FILES['image3']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['image3']['name'], PATHINFO_EXTENSION));
        $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $targetPath = $uploadDir . $newFileName;
        
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif'];
        if (!in_array($fileExtension, $allowedTypes)) {
            throw new Exception("Tipo de archivo no permitido para imagen 3");
        }
        
        if (move_uploaded_file($_FILES['image3']['tmp_name'], $targetPath)) {
            $image_paths[2] = $targetPath;
            $upload_fields[] = "image3 = ?";
            $upload_params[] = $targetPath;
            $upload_types .= "s";
        }
    }
    
    // Handle video upload
    if (isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK) {
        $uploadDir = 'uploads/videos/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0777, true);
        }
        
        $fileExtension = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
        $newFileName = uniqid() . '_' . time() . '.' . $fileExtension;
        $targetPath = $uploadDir . $newFileName;
        
        $allowedTypes = ['mp4', 'webm', 'ogg', 'mov'];
        if (!in_array($fileExtension, $allowedTypes)) {
            throw new Exception("Tipo de archivo de video no permitido");
        }
        
        if (move_uploaded_file($_FILES['video']['tmp_name'], $targetPath)) {
            $image_paths[3] = $targetPath;
            $upload_fields[] = "video = ?";
            $upload_params[] = $targetPath;
            $upload_types .= "s";
        }
    }

    // Validate required fields
    if (empty($tagid)) {
        throw new Exception("Tag ID es requerido");
    }

    if ($action === 'insert' && (empty($nombre) || empty($fecha_nacimiento))) {
        throw new Exception("Nombre y fecha de nacimiento son requeridos para insertar");
    }
    
    if ($action === 'update' && (empty($nombre) || empty($fecha_nacimiento))) {
        throw new Exception("Nombre y fecha de nacimiento son requeridos para actualizar");
    }

    // Handle different operations
    switch ($action) {
        case 'insert':
            // Check if animal already exists
            $checkStmt = $conn->prepare("SELECT COUNT(*) as count FROM aviar WHERE tagid = ?");
            $checkStmt->bind_param("s", $tagid);
            $checkStmt->execute();
            $result = $checkStmt->get_result();
            $row = $result->fetch_assoc();
            $checkStmt->close();
            
            if ($row['count'] > 0) {
                // Animal exists, update purchase information and images if provided
                $update_sql_parts = [
                    "fecha_compra = ?", "peso_compra = ?", "monto_compra = ?", "poblacion = ?", "cantidad_compra = ?"
                ];
                
                // Use poblacion_compra from form (which is the "Cantidad Comprada" input)
                $poblacion_value = !empty($poblacion_compra) ? $poblacion_compra : 0;
                $update_params = [$fecha_compra, $peso, $precio, $poblacion_value, $poblacion_value];
                $update_types = "sssss";
                
                // Add image upload fields if any
                if (!empty($upload_fields)) {
                    $update_sql_parts = array_merge($update_sql_parts, $upload_fields);
                    $update_params = array_merge($update_params, $upload_params);
                    $update_types .= $upload_types;
                }
                
                // Add tagid for WHERE clause
                $update_params[] = $tagid;
                $update_types .= "s";
                
                $sql = "UPDATE aviar SET " . implode(", ", $update_sql_parts) . " WHERE tagid = ?";
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param($update_types, ...$update_params);
                
                if (!$stmt->execute()) {
                    throw new Exception("Error al actualizar: " . $stmt->error);
                }
                
                $stmt->close();
                
                echo json_encode([
                    "success" => true,
                    "message" => "Información de compra e imágenes actualizadas exitosamente",
                    "redirect" => "aviar_register_compras.php"
                ]);
            } else {
                // Animal doesn't exist, insert new record
                $sql = "INSERT INTO aviar (
                    tagid, nombre, fecha_nacimiento, fecha_compra, genero, etapa, 
                    raza, grupo, estatus, peso_compra, monto_compra, poblacion, cantidad_compra,
                    peso_nacimiento, image, image2, image3, video
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                
                // Set default values for required fields
                $peso_nacimiento = $peso ?: 0.00;
                $poblacion_insert = !empty($poblacion_compra) ? $poblacion_compra : 0;
                
                // Debug logging for new purchase
                error_log("New purchase INSERT - poblacion_compra: $poblacion_compra, poblacion_insert: $poblacion_insert, peso: $peso, precio: $precio");
                $image = $image_paths[0] ?: '';
                $image2 = $image_paths[1] ?: '';
                $image3 = $image_paths[2] ?: '';
                $video = $image_paths[3] ?: '';
                
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("ssssssssssssssssss", 
                    $tagid, $nombre, $fecha_nacimiento, $fecha_compra, $genero, $etapa,
                    $raza, $grupo, $estatus, $peso, $precio, $poblacion_insert, $poblacion_insert,
                    $peso_nacimiento, $image, $image2, $image3, $video
                );
                
                if (!$stmt->execute()) {
                    throw new Exception("Error al insertar: " . $stmt->error);
                }
                
                $stmt->close();
                
                // Also insert into ah_compra table for new purchases
                if (!empty($poblacion_insert) || !empty($peso) || !empty($precio)) {
                    try {
                        $compra_fecha = !empty($fecha_compra) ? $fecha_compra : date('Y-m-d');
                        
                        $compra_sql = "INSERT INTO ah_compra (ah_compra_tagid, ah_compra_cantidad, ah_compra_peso, ah_compra_precio, ah_compra_fecha) 
                                       VALUES (?, ?, ?, ?, ?)";
                        
                        $compra_stmt = $conn->prepare($compra_sql);
                        $cantidad_value = $poblacion_insert ?: 0;
                        $peso_value = $peso ?: 0;
                        $precio_value = $precio ?: 0;
                        
                        $compra_stmt->bind_param("sisss", 
                            $tagid, 
                            $cantidad_value, 
                            $peso_value, 
                            $precio_value, 
                            $compra_fecha
                        );
                        
                        $compra_stmt->execute();
                        $compra_stmt->close();
                        
                        error_log("Inserted new purchase into ah_compra table for tagid: $tagid");
                    } catch (Exception $e) {
                        error_log("Error inserting into ah_compra table for new purchase: " . $e->getMessage());
                    }
                }
                
                echo json_encode([
                    "success" => true,
                    "message" => "Animal agregado exitosamente",
                    "redirect" => "aviar_register_compras.php"
                ]);
            }
            break;
            
        case 'update':
            // Get the animal ID from the form
            $animal_id = $_POST['id'] ?? '';
            
            // Debug logging
            error_log("Update case - Received POST data: " . print_r($_POST, true));
            error_log("Animal ID from form: '$animal_id'");
            error_log("TagID from form: '$tagid'");
            
            // If no ID provided, try to get it using tagid
            if (empty($animal_id) && !empty($tagid)) {
                $stmt = $conn->prepare("SELECT id FROM aviar WHERE tagid = ?");
                $stmt->bind_param("s", $tagid);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $animal_id = $row['id'];
                    error_log("Found animal ID using tagid: $animal_id");
                }
                $stmt->close();
            }
            
            if (empty($animal_id)) {
                throw new Exception("ID del animal es requerido para actualización. TagID: $tagid");
            }
            
            // Validate required fields
            if (empty($nombre)) {
                throw new Exception("El nombre del animal es requerido");
            }
            
            if (empty($tagid)) {
                throw new Exception("El Tag ID es requerido");
            }
            
            // Calculate new poblacion (current poblacion + new cantidad)
            $new_poblacion = 0;
            if (!empty($cantidad) && is_numeric($cantidad)) {
                // Get current poblacion value
                $stmt = $conn->prepare("SELECT poblacion FROM aviar WHERE id = ?");
                $stmt->bind_param("s", $animal_id);
                $stmt->execute();
                $result = $stmt->get_result();
                if ($row = $result->fetch_assoc()) {
                    $current_poblacion = (int)($row['poblacion'] ?? 0);
                    $new_poblacion = $current_poblacion + (int)$cantidad;
                    error_log("Adding cantidad to poblacion: $current_poblacion + $cantidad = $new_poblacion");
                }
                $stmt->close();
            } else {
                // If no cantidad provided, use the poblacion value from form (direct update)
                if (!empty($poblacion_compra) && is_numeric($poblacion_compra)) {
                    $new_poblacion = (int)$poblacion_compra;
                    error_log("Setting poblacion directly to: $new_poblacion");
                } else {
                    // Keep current poblacion if no values provided
                    $stmt = $conn->prepare("SELECT poblacion FROM aviar WHERE id = ?");
                    $stmt->bind_param("s", $animal_id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    if ($row = $result->fetch_assoc()) {
                        $new_poblacion = (int)($row['poblacion'] ?? 0);
                    }
                    $stmt->close();
                }
            }

            // Build update SQL parts - include existing columns and peso_compra/monto_compra/cantidad_compra if they exist
            $update_sql_parts = [
                "nombre = ?", "fecha_nacimiento = ?", "fecha_compra = ?", 
                "genero = ?", "raza = ?", "etapa = ?", "grupo = ?", "estatus = ?", "poblacion = ?",
                "peso_compra = ?", "monto_compra = ?", "cantidad_compra = ?"
            ];
            
            $update_params = [
                $nombre, $fecha_nacimiento, $fecha_compra,
                $genero, $raza, $etapa, $grupo, $estatus, $new_poblacion,
                $cantidad ?: $peso, $precio, $cantidad ?: 0
            ];
            $update_types = "ssssssssssss";
            
            // Add image upload fields if any
            if (!empty($upload_fields)) {
                $update_sql_parts = array_merge($update_sql_parts, $upload_fields);
                $update_params = array_merge($update_params, $upload_params);
                $update_types .= $upload_types;
            }
            
            // Add animal ID for WHERE clause
            $update_params[] = $animal_id;
            $update_types .= "s";
            
            $sql = "UPDATE aviar SET " . implode(", ", $update_sql_parts) . " WHERE id = ?";
            
            error_log("Update SQL: $sql");
            error_log("Update params: " . print_r($update_params, true));
            
            $stmt = $conn->prepare($sql);
            if (!$stmt) {
                throw new Exception("Error preparing SQL statement: " . $conn->error);
            }
            
            $stmt->bind_param($update_types, ...$update_params);
            
            if (!$stmt->execute()) {
                error_log("SQL Error: " . $stmt->error);
                error_log("SQL Query: " . $sql);
                error_log("Parameters: " . print_r($update_params, true));
                throw new Exception("Error al actualizar: " . $stmt->error);
            }
            
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            // Insert into ah_compra table only if there's purchase data (cantidad, peso, or precio)
            if ($affected_rows > 0 && (!empty($cantidad) || !empty($peso) || !empty($precio))) {
                try {
                    // Get the current date for the purchase
                    $compra_fecha = !empty($fecha_compra) ? $fecha_compra : date('Y-m-d');
                    
                    // Insert new ah_compra record for each purchase transaction
                    $compra_sql = "INSERT INTO ah_compra (ah_compra_tagid, ah_compra_cantidad, ah_compra_peso, ah_compra_precio, ah_compra_fecha) 
                                   VALUES (?, ?, ?, ?, ?)";
                    
                    $compra_stmt = $conn->prepare($compra_sql);
                    // Prepare variables for bind_param
                    $cantidad_value = $cantidad ?: 0;
                    $peso_value = $peso ?: 0;
                    $precio_value = $precio ?: 0;
                    
                    $compra_stmt->bind_param("sisss", 
                        $tagid, 
                        $cantidad_value, 
                        $peso_value, 
                        $precio_value, 
                        $compra_fecha
                    );
                    
                    if (!$compra_stmt->execute()) {
                        throw new Exception("Error al insertar en ah_compra: " . $compra_stmt->error);
                    }
                    $compra_stmt->close();
                    
                    error_log("Inserted into ah_compra table for tagid: $tagid with cantidad: $cantidad, peso: $peso, precio: $precio, fecha: $compra_fecha");
                } catch (Exception $e) {
                    error_log("Error inserting into ah_compra table: " . $e->getMessage());
                    // Don't throw the exception here, just log it so the main update can still succeed
                }
            }
            
            if ($affected_rows > 0) {
                echo json_encode([
                    "success" => true,
                    "message" => "Animal actualizado exitosamente"
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "No se realizaron cambios o el animal no fue encontrado"
                ]);
            }
            break;
            
        case 'update_purchase':
            // Handle purchase transaction updates (ah_compra table only)
            $compra_id = $_POST['compra_id'] ?? '';
            $animal_id = $_POST['id'] ?? '';
            
            if (empty($compra_id)) {
                throw new Exception("ID de compra es requerido para actualización de compra");
            }
            
            // Get current purchase data to calculate poblacion changes
            $current_sql = "SELECT ah_compra_cantidad, ah_compra_tagid FROM ah_compra WHERE id = ?";
            $current_stmt = $conn->prepare($current_sql);
            $current_stmt->bind_param("s", $compra_id);
            $current_stmt->execute();
            $current_result = $current_stmt->get_result();
            $current_data = $current_result->fetch_assoc();
            $current_stmt->close();
            
            if (!$current_data) {
                throw new Exception("No se encontró el registro de compra");
            }
            
            $old_cantidad = $current_data['ah_compra_cantidad'];
            $compra_tagid = $current_data['ah_compra_tagid'];
            $new_cantidad = !empty($cantidad) ? $cantidad : 0;
            
            // Update ah_compra table with new purchase data
            $purchase_sql = "UPDATE ah_compra SET 
                           ah_compra_cantidad = ?, ah_compra_peso = ?, ah_compra_precio = ?, ah_compra_fecha = ?
                           WHERE id = ?";
            
            $purchase_stmt = $conn->prepare($purchase_sql);
            $compra_fecha = !empty($fecha_compra) ? $fecha_compra : date('Y-m-d');
            $peso_value = $peso ?: 0;
            $precio_value = $precio ?: 0;
            
            $purchase_stmt->bind_param("sssss", 
                $new_cantidad, $peso_value, $precio_value, $compra_fecha, $compra_id
            );
            
            if (!$purchase_stmt->execute()) {
                throw new Exception("Error al actualizar compra: " . $purchase_stmt->error);
            }
            
            $affected_rows = $purchase_stmt->affected_rows;
            $purchase_stmt->close();
            
            if ($affected_rows > 0) {
                // Update poblacion in aviar table (adjust by the difference)
                $cantidad_diff = $new_cantidad - $old_cantidad;
                if ($cantidad_diff != 0) {
                    $poblacion_sql = "UPDATE aviar SET poblacion = GREATEST(0, poblacion + ?) WHERE tagid = ?";
                    $poblacion_stmt = $conn->prepare($poblacion_sql);
                    $poblacion_stmt->bind_param("ss", $cantidad_diff, $compra_tagid);
                    $poblacion_stmt->execute();
                    $poblacion_stmt->close();
                    
                    error_log("Updated purchase ID: $compra_id, adjusted poblacion by $cantidad_diff for tagid: $compra_tagid");
                }
                
                // Update animal details if provided (animal table)
                if (!empty($animal_id) && (!empty($nombre) || !empty($fecha_nacimiento))) {
                    $animal_sql_parts = [];
                    $animal_params = [];
                    $animal_types = "";
                    
                    if (!empty($nombre)) {
                        $animal_sql_parts[] = "nombre = ?";
                        $animal_params[] = $nombre;
                        $animal_types .= "s";
                    }
                    if (!empty($fecha_nacimiento)) {
                        $animal_sql_parts[] = "fecha_nacimiento = ?";
                        $animal_params[] = $fecha_nacimiento;
                        $animal_types .= "s";
                    }
                    if (!empty($genero)) {
                        $animal_sql_parts[] = "genero = ?";
                        $animal_params[] = $genero;
                        $animal_types .= "s";
                    }
                    if (!empty($raza)) {
                        $animal_sql_parts[] = "raza = ?";
                        $animal_params[] = $raza;
                        $animal_types .= "s";
                    }
                    if (!empty($etapa)) {
                        $animal_sql_parts[] = "etapa = ?";
                        $animal_params[] = $etapa;
                        $animal_types .= "s";
                    }
                    if (!empty($grupo)) {
                        $animal_sql_parts[] = "grupo = ?";
                        $animal_params[] = $grupo;
                        $animal_types .= "s";
                    }
                    if (!empty($estatus)) {
                        $animal_sql_parts[] = "estatus = ?";
                        $animal_params[] = $estatus;
                        $animal_types .= "s";
                    }
                    
                    if (!empty($animal_sql_parts)) {
                        // Add image upload fields if any
                        if (!empty($upload_fields)) {
                            $animal_sql_parts = array_merge($animal_sql_parts, $upload_fields);
                            $animal_params = array_merge($animal_params, $upload_params);
                            $animal_types .= $upload_types;
                        }
                        
                        $animal_params[] = $animal_id;
                        $animal_types .= "s";
                        
                        $animal_sql = "UPDATE aviar SET " . implode(", ", $animal_sql_parts) . " WHERE id = ?";
                        $animal_stmt = $conn->prepare($animal_sql);
                        $animal_stmt->bind_param($animal_types, ...$animal_params);
                        $animal_stmt->execute();
                        $animal_stmt->close();
                    }
                }
                
                echo json_encode([
                    "success" => true,
                    "message" => "Transacción de compra actualizada exitosamente"
                ]);
            } else {
                echo json_encode([
                    "success" => true,
                    "message" => "Transacción de compra actualizada exitosamente"
                ]);
            }
            break;
            
        case 'add_purchase':
            // Handle adding new purchase to existing animal
            if (empty($tagid)) {
                throw new Exception("Tag ID es requerido para agregar compra");
            }
            
            // Validate required purchase fields
            if (empty($cantidad) || !is_numeric($cantidad) || $cantidad <= 0) {
                throw new Exception("Cantidad es requerida y debe ser mayor a 0");
            }
            
            if (empty($peso) || !is_numeric($peso) || $peso <= 0) {
                throw new Exception("Peso es requerido y debe ser mayor a 0");
            }
            
            if (empty($precio) || !is_numeric($precio) || $precio <= 0) {
                throw new Exception("Precio es requerido y debe ser mayor a 0");
            }
            
            // Check if animal exists
            $check_sql = "SELECT id, poblacion FROM aviar WHERE tagid = ?";
            $check_stmt = $conn->prepare($check_sql);
            $check_stmt->bind_param("s", $tagid);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $animal_data = $check_result->fetch_assoc();
            $check_stmt->close();
            
            if (!$animal_data) {
                throw new Exception("No se encontró el animal con Tag ID: $tagid");
            }
            
            // Insert new purchase record
            $compra_fecha = !empty($fecha_compra) ? $fecha_compra : date('Y-m-d');
            $insert_sql = "INSERT INTO ah_compra (ah_compra_tagid, ah_compra_cantidad, ah_compra_peso, ah_compra_precio, ah_compra_fecha) 
                          VALUES (?, ?, ?, ?, ?)";
            
            $insert_stmt = $conn->prepare($insert_sql);
            $cantidad_value = (int)$cantidad;
            $peso_value = (float)$peso;
            $precio_value = (float)$precio;
            
            $insert_stmt->bind_param("sisss", 
                $tagid, 
                $cantidad_value, 
                $peso_value, 
                $precio_value, 
                $compra_fecha
            );
            
            if (!$insert_stmt->execute()) {
                throw new Exception("Error al insertar compra: " . $insert_stmt->error);
            }
            
            $insert_stmt->close();
            
            // Update poblacion in aviar table
            $current_poblacion = (int)($animal_data['poblacion'] ?? 0);
            $new_poblacion = $current_poblacion + $cantidad_value;
            
            $update_sql = "UPDATE aviar SET poblacion = ? WHERE tagid = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ss", $new_poblacion, $tagid);
            $update_stmt->execute();
            $update_stmt->close();
            
            error_log("Added new purchase for tagid: $tagid, cantidad: $cantidad_value, updated poblacion from $current_poblacion to $new_poblacion");
            
            echo json_encode([
                "success" => true,
                "message" => "Nueva compra registrada exitosamente"
            ]);
            break;
            
        case 'delete':
            $compra_id = $_POST['compra_id'] ?? '';
            
            if (empty($compra_id)) {
                throw new Exception("ID de compra es requerido para eliminar");
            }
            
            // First, get the purchase details before deleting
            $get_sql = "SELECT ah_compra_cantidad, ah_compra_tagid FROM ah_compra WHERE id = ?";
            $get_stmt = $conn->prepare($get_sql);
            $get_stmt->bind_param("s", $compra_id);
            $get_stmt->execute();
            $get_result = $get_stmt->get_result();
            $purchase_data = $get_result->fetch_assoc();
            $get_stmt->close();
            
            if (!$purchase_data) {
                throw new Exception("No se encontró el registro de compra para eliminar");
            }
            
            $deleted_cantidad = $purchase_data['ah_compra_cantidad'];
            $purchase_tagid = $purchase_data['ah_compra_tagid'];
            
            // Delete the specific purchase record from ah_compra table
            $sql = "DELETE FROM ah_compra WHERE id = ?";
            $stmt = $conn->prepare($sql);
            $stmt->bind_param("s", $compra_id);
            
            if (!$stmt->execute()) {
                throw new Exception("Error al eliminar registro de compra: " . $stmt->error);
            }
            
            $affected_rows = $stmt->affected_rows;
            $stmt->close();
            
            if ($affected_rows > 0) {
                // Decrement poblacion in aviar table by the deleted quantity
                $update_sql = "UPDATE aviar SET poblacion = GREATEST(0, poblacion - ?) WHERE tagid = ?";
                $update_stmt = $conn->prepare($update_sql);
                $update_stmt->bind_param("ss", $deleted_cantidad, $purchase_tagid);
                $update_stmt->execute();
                $update_stmt->close();
                
                error_log("Deleted compra record ID: $compra_id, decremented poblacion for tagid $purchase_tagid by $deleted_cantidad");
                
                echo json_encode([
                    "success" => true,
                    "message" => "Registro de compra eliminado exitosamente",
                    "redirect" => "aviar_register_compras.php"
                ]);
            } else {
                echo json_encode([
                    "success" => false,
                    "message" => "No se encontró el registro de compra para eliminar"
                ]);
            }
            break;
            
        default:
            throw new Exception("Acción no válida");
    }
    
    // Close connection
    $conn->close();

} catch (Exception $e) {
    // Log the error for debugging
    error_log("Exception in aviar_update.php: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    
    // Return error response
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}
