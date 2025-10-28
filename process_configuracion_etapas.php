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
error_log("Request method: " . $_SERVER['REQUEST_METHOD']);
error_log("Content type: " . $_SERVER['CONTENT_TYPE'] ?? 'not set');

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
            error_log("Processing insert action");
            error_log("POST data in insert: " . print_r($_POST, true));
            
            // Validate required fields
            $requiredFields = ['etapas'];
            $missingFields = [];

            foreach ($requiredFields as $field) {
                error_log("Checking field: $field, value: " . ($_POST[$field] ?? 'NOT SET'));
                if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
                    $missingFields[] = $field;
                }
            }

            if (!empty($missingFields)) {
                error_log("Missing fields: " . implode(', ', $missingFields));
                throw new Exception('Faltan los siguientes campos requeridos: ' . implode(', ', $missingFields));
            }

            // Sanitize and prepare data
            $etapas = trim($_POST['etapas']);

            // Debug values
            error_log("Insert values: etapas=$etapas");

            // Insert new record - FIXED parameter names in SQL
            $sql = "INSERT INTO ac_etapas (ac_etapas_nombre) 
                   VALUES (:etapas)";
            
            error_log("SQL query: $sql");
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':etapas', $etapas, PDO::PARAM_STR);
            
            error_log("Statement prepared and bound");
            
            if ($stmt->execute()) {
                error_log("Insert successful");
                echo json_encode(['success' => true, 'message' => 'Etapa agregada exitosamente.']);
            } else {
                error_log("Insert failed: " . implode(", ", $stmt->errorInfo()));
                throw new Exception("Error al insertar el registro: " . implode(", ", $stmt->errorInfo()));
            }
            break;

        case 'update':
            // Validate required fields
            if (!isset($_POST['id']) || !isset($_POST['etapas'])) {
                $missing = [];
                if (!isset($_POST['id'])) $missing[] = 'id';
                if (!isset($_POST['etapas'])) $missing[] = 'etapas';
                throw new Exception("Campos requeridos faltantes: " . implode(', ', $missing));
            }
            
            // Sanitize and validate inputs
            $id = filter_var($_POST['id'], FILTER_VALIDATE_INT);
            $etapas = trim($_POST['etapas']);
            
            if ($id === false) {
                throw new Exception("Los valores ingresados no son válidos");
            }
            
            // Debug values
            error_log("Update values: id=$id, etapas=$etapas");

            // Update existing record
            $sql = "UPDATE ac_etapas 
                    SET ac_etapas_nombre = :etapas 
                    WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            $stmt->bindValue(':etapas', $etapas, PDO::PARAM_STR);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Etapa actualizada exitosamente']);
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
            $sql = "DELETE FROM ac_etapas WHERE id = :id";
            
            $stmt = $conn->prepare($sql);
            $stmt->bindValue(':id', $id, PDO::PARAM_INT);
            
            if ($stmt->execute()) {
                echo json_encode(['success' => true, 'message' => 'Etapa eliminada exitosamente']);
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
