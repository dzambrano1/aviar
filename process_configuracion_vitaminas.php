<?php
require_once './pdo_conexion.php'; // Adjust path if necessary

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response header to JSON
header('Content-Type: application/json');

// Debug received data
function debugData($data) {
    error_log("DEBUG DATA: " . print_r($data, true));
}

// Log all POST data for debugging
error_log("POST data: " . print_r($_POST, true));

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método de solicitud no válido. Se esperaba POST.']);
    exit;
}

// Get action from POST
$action = isset($_POST['action']) ? trim($_POST['action']) : '';

try {
    // Ensure connection is a PDO instance
    if (!($conn instanceof PDO)) {
        throw new Exception("Database connection error");
    }

    switch ($action) {
        case 'insert':
            // Validate required fields
            $requiredFields = ['vitaminas', 'etapa', 'racion', 'costo', 'vigencia'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                throw new Exception('Faltan los siguientes campos requeridos: ' . implode(', ', $missingFields));
            }

            // Sanitize and prepare data
            $vitaminas = trim($_POST['vitaminas']);
            $etapa = trim($_POST['etapa']);
            $racion = filter_var(trim($_POST['racion']), FILTER_VALIDATE_FLOAT);
            $costo = filter_var(trim($_POST['costo']), FILTER_VALIDATE_FLOAT);
            $vigencia = filter_var(trim($_POST['vigencia']), FILTER_VALIDATE_INT);

            // Additional validation for numeric fields
            if ($racion === false || $racion < 0) {
                throw new Exception('El valor de ración no es válido o es negativo.');
            }
            if ($costo === false || $costo < 0) {
                throw new Exception('El valor de costo no es válido o es negativo.');
            }
            if ($vigencia === false || $vigencia < 0) {
                throw new Exception('El valor de vigencia no es válido o es negativo.');
            }

            // Debug values
            error_log("Insert values: vitaminas=$vitaminas, etapa=$etapa, racion=$racion, costo=$costo, vigencia=$vigencia");

            // Insert new record - FIXED parameter names in SQL
            $sql = "INSERT INTO aviar_vitaminas (aviar_vitaminas_nombre, aviar_vitaminas_etapa, aviar_vitaminas_racion, aviar_vitaminas_costo, aviar_vitaminas_vigencia) 
                   VALUES (:vitaminas, :etapa, :racion, :costo, :vigencia)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':vitaminas', $vitaminas, PDO::PARAM_STR);
            $stmt->bindValue(':etapa', $etapa, PDO::PARAM_STR);
            $stmt->bindValue(':racion', $racion, PDO::PARAM_STR);
            $stmt->bindValue(':costo', $costo, PDO::PARAM_STR);
            $stmt->bindValue(':vigencia', $vigencia, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Alimento vitaminas agregado exitosamente.']);
            } else {
                throw new Exception("Error al insertar el registro: " . implode(", ", $stmt->errorInfo()));
            }
            break;

        case 'update':
            // Validate required fields
            if (!isset($_POST['id']) || !isset($_POST['vitaminas']) || !isset($_POST['etapa']) || 
                !isset($_POST['racion']) || !isset($_POST['costo']) || !isset($_POST['vigencia'])) {
                $missing = [];
                if (!isset($_POST['id'])) $missing[] = 'id';
                if (!isset($_POST['vitaminas'])) $missing[] = 'vitaminas';
                if (!isset($_POST['etapa'])) $missing[] = 'etapa';
                if (!isset($_POST['racion'])) $missing[] = 'racion';
                if (!isset($_POST['costo'])) $missing[] = 'costo';
                if (!isset($_POST['vigencia'])) $missing[] = 'vigencia';
                
                throw new Exception("Campos requeridos faltantes: " . implode(', ', $missing));
            }

            // Sanitize and validate inputs
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            $vitaminas = trim($_POST['vitaminas']);
            $etapa = trim($_POST['etapa']);
            $racion = filter_var(trim($_POST['racion']), FILTER_VALIDATE_FLOAT);
            $costo = filter_var(trim($_POST['costo']), FILTER_VALIDATE_FLOAT);
            $vigencia = filter_var(trim($_POST['vigencia']), FILTER_VALIDATE_INT);

            if ($id === false || $racion === false || $costo === false || $vigencia === false) {
                throw new Exception("Los valores ingresados no son válidos");
            }
            
            // Debug values
            error_log("Update values: id=$id, vitaminas=$vitaminas, etapa=$etapa, racion=$racion, costo=$costo, vigencia=$vigencia");

            // Update existing record
            $sql = "UPDATE ac_vitaminas 
                    SET ac_vitaminas_nombre = :vitaminas, 
                        ac_vitaminas_etapa = :etapa,
                        ac_vitaminas_racion = :racion, 
                        ac_vitaminas_costo = :costo, 
                        ac_vitaminas_vigencia = :vigencia 
                    WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':vitaminas', $vitaminas, PDO::PARAM_STR);
            $stmt->bindValue(':etapa', $etapa, PDO::PARAM_STR);
            $stmt->bindValue(':racion', $racion, PDO::PARAM_STR);
            $stmt->bindValue(':costo', $costo, PDO::PARAM_STR);
            $stmt->bindValue(':vigencia', $vigencia, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Registro actualizado exitosamente']);
            } else {
                throw new Exception("Error al actualizar el registro: " . implode(", ", $stmt->errorInfo()));
            }
            break;

        case 'delete':
            // Validate required ID field
            if (!isset($_POST['id'])) {
                throw new Exception("ID es requerido para eliminar");
            }

            // Sanitize and validate ID
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            
            if ($id === false) {
                throw new Exception("ID inválido");
            }

            // Delete record
            $sql = "DELETE FROM ac_vitaminas WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Registro eliminado exitosamente']);
            } else {
                throw new Exception("Error al eliminar el registro: " . implode(", ", $stmt->errorInfo()));
            }
            break;

        default:
            throw new Exception("Acción no válida o no especificada: '$action'");
    }
} catch (PDOException $e) {
    // Log the error for debugging
    error_log("Database error: " . $e->getMessage());
    
    // Return error message
    echo json_encode(['success' => false, 'message' => 'Error de base de datos: ' . $e->getMessage()]);
} catch (Exception $e) {
    // Log the error for debugging
    error_log("Application error: " . $e->getMessage());
    
    // Return error message
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
