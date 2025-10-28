<?php
session_start();
require_once 'pdo_conexion.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log the incoming request
error_log("process_configuracion_concentrado.php called with POST data: " . print_r($_POST, true));

// Set content type to JSON
header('Content-Type: application/json');

try {
    // Use the existing connection from pdo_conexion.php
    if (!($conn instanceof PDO)) {
        throw new Exception("Database connection not available");
    }
    
    // Get the action from POST data
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'create':
            handleCreate($conn);
            break;
        case 'read':
            handleRead($conn);
            break;
        case 'update':
            handleUpdate($conn);
            break;
        case 'delete':
            handleDelete($conn);
            break;
        default:
            echo json_encode(['success' => false, 'message' => 'Invalid action']);
            break;
    }
    
} catch (PDOException $e) {
    error_log("Database error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Database error occurred: ' . $e->getMessage()]);
} catch (Exception $e) {
    error_log("General error: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}

function handleCreate($pdo) {
    // Validate required fields based on ac_concentrado table structure
    $required_fields = ['concentrado', 'etapa', 'costo', 'vigencia'];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Sanitize and validate input
    $concentrado = trim($_POST['concentrado']);
    $etapa = trim($_POST['etapa']);
    $costo = floatval($_POST['costo']);
    $vigencia = intval($_POST['vigencia']);
    
    // Validate numeric values
    if ($costo < 0 || $vigencia < 0) {
        echo json_encode(['success' => false, 'message' => 'Numeric values must be positive']);
        return;
    }
    
    try {
        $sql = "INSERT INTO ac_concentrado (ac_concentrado_nombre, ac_concentrado_etapa, ac_concentrado_costo, ac_concentrado_vigencia) VALUES (?, ?, ?, ?)";
        error_log("SQL Query: " . $sql);
        error_log("Parameters: " . print_r([$concentrado, $etapa, $costo, $vigencia], true));
        
        $stmt = $pdo->prepare($sql);
        
        $result = $stmt->execute([
            $concentrado, $etapa, $costo, $vigencia
        ]);
        
        error_log("Execute result: " . ($result ? 'true' : 'false'));
        
        if ($result) {
            $newId = $pdo->lastInsertId();
            error_log("New ID: " . $newId);
            
            echo json_encode([
                'success' => true, 
                'message' => 'Concentrado created successfully',
                'id' => $newId
            ]);
        } else {
            error_log("Execute failed");
            echo json_encode(['success' => false, 'message' => 'Failed to create concentrado: Execute returned false']);
        }
        
    } catch (PDOException $e) {
        error_log("Create error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to create concentrado: ' . $e->getMessage()]);
    }
}

function handleRead($pdo) {
    try {
        $stmt = $pdo->query("
            SELECT id, ac_concentrado_nombre, ac_concentrado_etapa, ac_concentrado_costo, ac_concentrado_vigencia
            FROM ac_concentrado 
            ORDER BY id ASC
        ");
        
        $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        echo json_encode([
            'success' => true, 
            'data' => $data
        ]);
        
    } catch (PDOException $e) {
        error_log("Read error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to fetch concentrado data: ' . $e->getMessage()]);
    }
}

function handleUpdate($pdo) {
    // Validate required fields
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Valid ID is required']);
        return;
    }
    
    $required_fields = ['concentrado', 'etapa', 'costo', 'vigencia'];
    
    foreach ($required_fields as $field) {
        if (!isset($_POST[$field]) || empty(trim($_POST[$field]))) {
            echo json_encode(['success' => false, 'message' => "Field '$field' is required"]);
            return;
        }
    }
    
    // Sanitize and validate input
    $id = intval($_POST['id']);
    $concentrado = trim($_POST['concentrado']);
    $etapa = trim($_POST['etapa']);
    $costo = floatval($_POST['costo']);
    $vigencia = intval($_POST['vigencia']);
    
    // Validate numeric values
    if ($costo < 0 || $vigencia < 0) {
        echo json_encode(['success' => false, 'message' => 'Numeric values must be positive']);
        return;
    }
    
    try {
        $sql = "UPDATE ac_concentrado SET ac_concentrado_nombre = ?, ac_concentrado_etapa = ?, ac_concentrado_costo = ?, ac_concentrado_vigencia = ? WHERE id = ?";
        error_log("Update SQL Query: " . $sql);
        error_log("Update Parameters: " . print_r([$concentrado, $etapa, $costo, $vigencia, $id], true));
        
        $stmt = $pdo->prepare($sql);
        
        $result = $stmt->execute([
            $concentrado, $etapa, $costo, $vigencia, $id
        ]);
        
        error_log("Update execute result: " . ($result ? 'true' : 'false'));
        error_log("Update row count: " . $stmt->rowCount());
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Concentrado updated successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'No changes made or record not found']);
        }
        
    } catch (PDOException $e) {
        error_log("Update error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to update concentrado: ' . $e->getMessage()]);
    }
}

function handleDelete($pdo) {
    // Validate required fields
    if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
        echo json_encode(['success' => false, 'message' => 'Valid ID is required']);
        return;
    }
    
    $id = intval($_POST['id']);
    
    try {
        $stmt = $pdo->prepare("DELETE FROM ac_concentrado WHERE id = ?");
        $stmt->execute([$id]);
        
        if ($stmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Concentrado deleted successfully'
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Record not found']);
        }
        
    } catch (PDOException $e) {
        error_log("Delete error: " . $e->getMessage());
        echo json_encode(['success' => false, 'message' => 'Failed to delete concentrado: ' . $e->getMessage()]);
    }
}
?>
