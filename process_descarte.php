<?php
header('Content-Type: application/json');
require_once './pdo_conexion.php';

try {
    // Check if connection is a valid PDO instance
    if (!($conn instanceof PDO)) {
        throw new Exception("Error: Database connection is not a valid PDO instance");
    }
    
    // Enable PDO error mode to get better error messages
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get the action from POST data
    $action = $_POST['action'] ?? '';
    
    if (empty($action)) {
        throw new Exception("Action is required");
    }
    
    switch ($action) {
        case 'insert':
            // Validate required fields for insert
            if (empty($_POST['tagid']) || empty($_POST['peso']) || empty($_POST['precio']) || empty($_POST['fecha']) || empty($_POST['cantidad'])) {
                throw new Exception("Tag ID, peso, precio, fecha y cantidad son requeridos para registrar un descarte");
            }
            
            $tagid = $_POST['tagid'];
            $peso = $_POST['peso'];
            $precio = $_POST['precio'];
            $fecha = $_POST['fecha'];
            $cantidad = $_POST['cantidad'];
            
            // Check if animal exists and has sufficient population
            $checkSql = "SELECT id, estatus, poblacion FROM aviar WHERE tagid = ?";
            $checkStmt = $conn->prepare($checkSql);
            $checkStmt->execute([$tagid]);
            $animal = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$animal) {
                throw new Exception("No se encontró un animal con el Tag ID especificado");
            }
            
            if ($animal['estatus'] === 'Descartado') {
                throw new Exception("El animal ya está marcado como descartado");
            }
            
            if ($animal['poblacion'] < $cantidad) {
                throw new Exception("No hay suficiente población para registrar este descarte");
            }
            
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                // Insert into ah_descarte table
                $insertSql = "INSERT INTO ah_descarte (ah_descarte_tagid, ah_descarte_fecha, ah_descarte_peso, ah_descarte_precio, ah_descarte_cantidad) VALUES (?, ?, ?, ?, ?)";
                $insertStmt = $conn->prepare($insertSql);
                $insertStmt->execute([$tagid, $fecha, $peso, $precio, $cantidad]);
                
                // Update aviar table
                $updateSql = "UPDATE aviar SET 
                                descarte_fecha = ?, 
                                descarte_peso = ?, 
                                descarte_precio = ?, 
                                descarte_cantidad = ?,
                                estatus = 'Descartado',
                                poblacion = poblacion - ?
                            WHERE tagid = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->execute([$fecha, $peso, $precio, $cantidad, $cantidad, $tagid]);
                
                // Commit transaction
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Descarte registrado correctamente'
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'update':
            // Validate required fields for update
            if (empty($_POST['id']) || empty($_POST['tagid']) || empty($_POST['peso']) || empty($_POST['precio']) || empty($_POST['fecha']) || empty($_POST['cantidad']) || empty($_POST['poblacion'])) {
                throw new Exception("ID, Tag ID, peso, precio, fecha, cantidad y población son requeridos para actualizar un descarte");
            }
            
            $id = $_POST['id'];
            $tagid = $_POST['tagid'];
            $peso = $_POST['peso'];
            $precio = $_POST['precio'];
            $fecha = $_POST['fecha'];
            $cantidad = $_POST['cantidad'];
            $poblacion = $_POST['poblacion'];
            
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                // Get current discard record information
                $checkSql = "SELECT ah_descarte_cantidad FROM ah_descarte WHERE id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->execute([$id]);
                $currentRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$currentRecord) {
                    throw new Exception("No se encontró el registro de descarte especificado");
                }
                
                $oldCantidad = $currentRecord['ah_descarte_cantidad'];
                $cantidadDifference = $cantidad - $oldCantidad;
                
                // Update ah_descarte table
                $updateDescarteSql = "UPDATE ah_descarte SET 
                                        ah_descarte_fecha = ?, 
                                        ah_descarte_peso = ?, 
                                        ah_descarte_precio = ?, 
                                        ah_descarte_cantidad = ?
                                    WHERE id = ?";
                $updateDescarteStmt = $conn->prepare($updateDescarteSql);
                $updateDescarteStmt->execute([$fecha, $peso, $precio, $cantidad, $id]);
                
                // Update aviar table
                $updateAviarSql = "UPDATE aviar SET 
                                    descarte_fecha = ?, 
                                    descarte_peso = ?, 
                                    descarte_precio = ?, 
                                    descarte_cantidad = ?,
                                    poblacion = ?
                                WHERE tagid = ?";
                $updateAviarStmt = $conn->prepare($updateAviarSql);
                $updateAviarStmt->execute([$fecha, $peso, $precio, $cantidad, $poblacion, $tagid]);
                
                // Commit transaction
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Descarte actualizado correctamente'
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        case 'delete':
            // For delete, we need the id from ah_descarte table
            if (empty($_POST['id'])) {
                throw new Exception("ID es requerido para eliminar un descarte");
            }
            
            $id = $_POST['id'];
            
            // Begin transaction
            $conn->beginTransaction();
            
            try {
                // Get discard record information before deletion
                $checkSql = "SELECT ah_descarte_tagid, ah_descarte_cantidad FROM ah_descarte WHERE id = ?";
                $checkStmt = $conn->prepare($checkSql);
                $checkStmt->execute([$id]);
                $discardRecord = $checkStmt->fetch(PDO::FETCH_ASSOC);
                
                if (!$discardRecord) {
                    throw new Exception("No se encontró el registro de descarte especificado");
                }
                
                $tagid = $discardRecord['ah_descarte_tagid'];
                $cantidad = $discardRecord['ah_descarte_cantidad'];
                
                // Update aviar table to restore status and population
                $updateSql = "UPDATE aviar SET 
                                descarte_fecha = NULL, 
                                descarte_peso = NULL, 
                                descarte_precio = NULL, 
                                descarte_cantidad = NULL,
                                estatus = 'Activo',
                                poblacion = poblacion + ?
                            WHERE tagid = ?";
                $updateStmt = $conn->prepare($updateSql);
                $updateStmt->execute([$cantidad, $tagid]);
                
                // Delete from ah_descarte table
                $deleteSql = "DELETE FROM ah_descarte WHERE id = ?";
                $deleteStmt = $conn->prepare($deleteSql);
                $deleteStmt->execute([$id]);
                
                // Commit transaction
                $conn->commit();
                
                echo json_encode([
                    'success' => true,
                    'message' => 'Descarte eliminado correctamente'
                ]);
                
            } catch (Exception $e) {
                $conn->rollBack();
                throw $e;
            }
            break;
            
        default:
            throw new Exception("Invalid action specified");
    }
    
} catch (PDOException $e) {
    // Log the error
    error_log("Database Error in process_descarte.php: " . $e->getMessage());
    
    // Return error message
    echo json_encode([
        'success' => false,
        'message' => 'Database error: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    // Log the error
    error_log("General Error in process_descarte.php: " . $e->getMessage());
    
    // Return error message
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
?>
