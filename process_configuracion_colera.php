<?php
// Include database connection
require_once './pdo_conexion.php';

// Enable error reporting for debugging (can be disabled in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set response header to JSON
header('Content-Type: application/json');

// Debug received data
function debugData($data) {
    error_log("DEBUG DATA: " . print_r($data, true));
}

// Function to validate and sanitize input data
function validateInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

// Check if the request is POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Log all POST data for debugging
error_log("POST data: " . print_r($_POST, true));

// Get action from POST
$action = isset($_POST['action']) ? validateInput($_POST['action']) : '';

// Process based on action
try {
    // Ensure connection is a PDO instance
    if (!($conn instanceof PDO)) {
        throw new Exception("Database connection error");
    }

    switch ($action) {
        case 'insert':
            // Validate required fields
            if (!isset($_POST['vacuna']) || !isset($_POST['dosis']) || !isset($_POST['costo']) || !isset($_POST['vigencia'])) {
                $missing = [];
                if (!isset($_POST['vacuna'])) $missing[] = 'vacuna';
                if (!isset($_POST['dosis'])) $missing[] = 'dosis';
                if (!isset($_POST['costo'])) $missing[] = 'costo';
                if (!isset($_POST['vigencia'])) $missing[] = 'vigencia';
                
                throw new Exception("Campos requeridos faltantes: " . implode(', ', $missing));
            }

            // Sanitize and validate inputs
            $vacuna = validateInput($_POST['vacuna']);
            $dosis = filter_var($_POST['dosis'], FILTER_VALIDATE_FLOAT);
            $costo = filter_var($_POST['costo'], FILTER_VALIDATE_FLOAT);
            $vigencia = filter_var($_POST['vigencia'], FILTER_VALIDATE_INT);

            if ($dosis === false || $costo === false || $vigencia === false) {
                throw new Exception("Los valores ingresados no son válidos");
            }

            // Debug values
            error_log("Insert values: vacuna=$vacuna, dosis=$dosis, costo=$costo, vigencia=$vigencia");

            // Insert new record
            $sql = "INSERT INTO ac_colera (ac_colera_vacuna, ac_colera_dosis, ac_colera_costo, ac_colera_vigencia) 
                    VALUES (:vacuna, :dosis, :costo, :vigencia)";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':vacuna', $vacuna, PDO::PARAM_STR);
            $stmt->bindValue(':dosis', $dosis, PDO::PARAM_STR);
            $stmt->bindValue(':costo', $costo, PDO::PARAM_STR);
            $stmt->bindValue(':vigencia', $vigencia, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Registro agregado exitosamente']);
            } else {
                throw new Exception("Error al insertar el registro: " . implode(", ", $stmt->errorInfo()));
            }
            break;

        case 'update':
            // Validate required fields including ID
            if (!isset($_POST['id']) || !isset($_POST['vacuna']) || !isset($_POST['dosis']) || !isset($_POST['costo']) || !isset($_POST['vigencia'])) {
                $missing = [];
                if (!isset($_POST['id'])) $missing[] = 'id';
                if (!isset($_POST['vacuna'])) $missing[] = 'vacuna';
                if (!isset($_POST['dosis'])) $missing[] = 'dosis';
                if (!isset($_POST['costo'])) $missing[] = 'costo';
                if (!isset($_POST['vigencia'])) $missing[] = 'vigencia';
                
                throw new Exception("Campos requeridos faltantes: " . implode(', ', $missing));
            }

            // Sanitize and validate inputs
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            $vacuna = validateInput($_POST['vacuna']);
            $dosis = filter_var($_POST['dosis'], FILTER_VALIDATE_FLOAT);
            $costo = filter_var($_POST['costo'], FILTER_VALIDATE_FLOAT);
            $vigencia = filter_var($_POST['vigencia'], FILTER_VALIDATE_INT);

            if ($id === false || $dosis === false || $costo === false || $vigencia === false) {
                throw new Exception("Los valores ingresados no son válidos");
            }
            
            // Debug values
            error_log("Update values: id=$id, vacuna=$vacuna, dosis=$dosis, costo=$costo, vigencia=$vigencia");

            // Update existing record
            $sql = "UPDATE ac_colera 
                    SET ac_colera_vacuna = :vacuna, 
                        ac_colera_dosis = :dosis, 
                        ac_colera_costo = :costo, 
                        ac_colera_vigencia = :vigencia 
                    WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':vacuna', $vacuna, PDO::PARAM_STR);
            $stmt->bindValue(':dosis', $dosis, PDO::PARAM_STR);
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
            $sql = "DELETE FROM ac_colera WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Registro eliminado exitosamente']);
            } else {
                throw new Exception("Error al eliminar el registro: " . implode(", ", $stmt->errorInfo()));
            }
            break;

        default:
            throw new Exception("Acción no válida: '$action'");
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