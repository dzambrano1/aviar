<?php
require_once './pdo_conexion.php';

// Enable PDO error mode to get better error messages
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Get action parameter
    $action = $_POST['action'] ?? '';

    // Get form data for death record
    $tagid = $_POST['tagid'] ?? '';
    $causa = $_POST['causa'] ?? '';
    $fecha = $_POST['fecha'] ?? '';
    $id = $_POST['id'] ?? '';
    $precio = $_POST['precio'] ?? '';
    $peso = $_POST['peso'] ?? '';
    $aves = $_POST['aves'] ?? '';
    $poblacion = $_POST['poblacion'] ?? '';

    // Debug logging
    error_log("POST data received: " . print_r($_POST, true));
    error_log("Action: '$action', TagID: '$tagid', Causa: '$causa', Fecha: '$fecha', ID: '$id', Precio: '$precio', Peso: '$peso', Aves: '$aves', Poblacion: '$poblacion'");

    // Validate required fields
    if (empty($action)) {
        throw new Exception("Acción no válida o datos no proporcionados");
    }

    // Handle different operations
    switch ($action) {
        case 'insert':
            // Validate required fields for insert
            if (empty($tagid) || empty($causa) || empty($fecha) || empty($precio) || empty($peso) || empty($aves)) {
                throw new Exception("Tag ID, causa, fecha, precio, peso y aves son requeridos para registrar un deceso");
            }
            
            // Check if animal exists
            $checkSql = "SELECT id, estatus, poblacion FROM aviar WHERE tagid = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$tagid]);
            $animal = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$animal) {
                throw new Exception("No se encontró un animal con el Tag ID especificado");
            }
            
            // Check if there are enough animals available
            if ($animal['poblacion'] < intval($aves)) {
                throw new Exception("No hay suficientes aves disponibles. Población actual: " . $animal['poblacion'] . ", Aves muertas solicitadas: " . $aves);
            }
            
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                // Check if this animal already has death records
                $checkExistingSql = "SELECT COUNT(*) as existing_count FROM ah_decesos WHERE ah_decesos_tagid = ?";
                $checkExistingStmt = $conn->prepare($checkExistingSql);
                $checkExistingStmt->execute([$tagid]);
                $existingCount = $checkExistingStmt->fetch(PDO::FETCH_ASSOC)['existing_count'];
                
                if ($existingCount > 0) {
                    // Animal already has death records, just add to the count
                    $updateAviarSql = "UPDATE aviar SET 
                            poblacion = poblacion - ?
                            WHERE tagid = ?";
                    
                    $stmt = $conn->prepare($updateAviarSql);
                    $stmt->execute([intval($aves), $tagid]);
                } else {
                    // First death record for this animal
                    $updateAviarSql = "UPDATE aviar SET 
                            deceso_fecha = ?, 
                            deceso_causa = ?,
                            deceso_precio = ?,
                            deceso_peso = ?,
                            deceso_cantidad = ?,
                            estatus = 'Muerto',
                            poblacion = poblacion - ?
                            WHERE tagid = ?";
                    
                    $stmt = $conn->prepare($updateAviarSql);
                    $stmt->execute([$fecha, $causa, floatval($precio), floatval($peso), intval($aves), intval($aves), $tagid]);
                }

                // Insert into ah_decesos table
                $insertDecesoSql = "INSERT INTO ah_decesos (
                    ah_decesos_tagid,
                    ah_decesos_fecha,
                    ah_decesos_causa,
                    ah_decesos_precio,
                    ah_decesos_peso,
                    ah_decesos_cantidad
                ) VALUES (?, ?, ?, ?, ?, ?)";
                
                $stmt = $conn->prepare($insertDecesoSql);
                $stmt->execute([$tagid, $fecha, $causa, floatval($precio), floatval($peso), intval($aves)]);

                // Commit transaction
                $conn->commit();
                
                echo json_encode([
                    "success" => true,
                    "message" => "Deceso registrado exitosamente"
                ]);
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'update':
            // Validate required fields for update
            if (empty($tagid) || empty($causa) || empty($fecha) || empty($precio) || empty($peso) || empty($aves) || empty($poblacion)) {
                throw new Exception("Tag ID, causa, fecha, precio, peso, aves y población son requeridos para actualizar un deceso");
            }
            
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                // Get current death record information from ah_decesos
                $checkSql = "SELECT ah_decesos_cantidad FROM ah_decesos WHERE id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->execute([$id]);
                $currentRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$currentRecord) {
                    throw new Exception("No se encontró un registro de deceso para este animal");
                }
                
                $oldAves = intval($currentRecord['ah_decesos_cantidad']);
                $newAves = intval($aves);
                $avesDifference = $newAves - $oldAves;
                
                // Use the poblacion value directly from the form
                $newPopulation = intval($poblacion);
                
                // Update aviar table
                $updateAviarSql = "UPDATE aviar SET 
                        poblacion = ?
                        WHERE tagid = ?";
                
                $stmt = $conn->prepare($updateAviarSql);
                $stmt->execute([$newPopulation, $tagid]);

                // Update ah_decesos table using the record ID
                $updateDecesoSql = "UPDATE ah_decesos SET 
                        ah_decesos_fecha = ?,
                        ah_decesos_causa = ?,
                        ah_decesos_precio = ?,
                        ah_decesos_peso = ?,
                        ah_decesos_cantidad = ?
                        WHERE id = ?";
                
                $stmt = $conn->prepare($updateDecesoSql);
                $stmt->execute([$fecha, $causa, floatval($precio), floatval($peso), intval($aves), $id]);

                // Commit transaction
                $conn->commit();
                
                echo json_encode([
                    "success" => true,
                    "message" => "Deceso actualizado exitosamente",
                    "population_adjustment" => $avesDifference,
                    "new_population" => $newPopulation
                ]);
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'delete':
            // For delete, we need the id from ah_decesos table
            if (empty($id)) {
                throw new Exception("ID es requerido para eliminar un deceso");
            }
            
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                // Get death record information before deletion
                $checkSql = "SELECT ah_decesos_tagid, ah_decesos_cantidad FROM ah_decesos WHERE id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->execute([$id]);
                $deathRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$deathRecord) {
                    throw new Exception("No se encontró el registro de deceso");
                }
                
                $tagid = $deathRecord['ah_decesos_tagid'];
                $avesMuertas = intval($deathRecord['ah_decesos_cantidad']);
                
                // Remove death record from aviar table (set to NULL and change status back to Activo)
                $updateAviarSql = "UPDATE aviar SET 
                        deceso_fecha = NULL, 
                        deceso_causa = NULL,
                        deceso_precio = NULL,
                        deceso_peso = NULL,
                        deceso_cantidad = NULL,
                        estatus = 'Activo',
                        poblacion = poblacion + ?
                        WHERE tagid = ?";
                $stmt = $conn->prepare($updateAviarSql);
                $stmt->execute([$avesMuertas, $tagid]);
                
                // Delete from ah_decesos table
                $deleteDecesoSql = "DELETE FROM ah_decesos WHERE id = ?";
                $stmt = $conn->prepare($deleteDecesoSql);
                $stmt->execute([$id]);

                // Commit transaction
                $conn->commit();
                
                echo json_encode([
                    "success" => true,
                    "message" => "Deceso eliminado exitosamente. Se han restaurado " . $avesMuertas . " aves a la población."
                ]);
            } catch (Exception $e) {
                // Rollback transaction on error
                $conn->rollBack();
                throw $e;
            }
            break;
            
        default:
            throw new Exception("Acción no válida: $action");
    }

} catch (Exception $e) {
    // Return error response
    echo json_encode([
        "success" => false,
        "message" => $e->getMessage()
    ]);
}