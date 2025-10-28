<?php
// Include database connection
require_once './pdo_conexion.php';

// Set response header to JSON
header('Content-Type: application/json');

// Handle different actions
if (isset($_POST['action'])) {
    $action = $_POST['action'];
    
    try {
        // INSERT operation
        if ($action === 'insert') {
            // Validate required fields
            $requiredFields = ['tagid', 'etapa', 'producto', 'racion', 'costo', 'fecha'];
            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field]) || empty($_POST[$field])) {
                    throw new Exception("Campo requerido: $field");
                }
            }
            
            // Prepare data
            $tagid = $_POST['tagid'];
            $etapa = $_POST['etapa'];
            $producto = $_POST['producto'];
            $racion = floatval($_POST['racion']);
            $costo = floatval($_POST['costo']);
            $fecha = $_POST['fecha'];
            
            // Execute query
            $stmt = $conn->prepare("INSERT INTO ah_sal (ah_sal_tagid, ah_sal_etapa, ah_sal_producto, ah_sal_racion, ah_sal_costo, ah_sal_fecha) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$tagid, $etapa, $producto, $racion, $costo, $fecha]);
            
            echo json_encode(['success' => true, 'message' => 'Registro agregado correctamente']);
        }
        
        // UPDATE operation
        else if ($action === 'update') {
            // Validate required fields
            $requiredFields = ['id', 'tagid', 'etapa', 'producto', 'racion', 'costo', 'fecha'];
            foreach ($requiredFields as $field) {
                if (!isset($_POST[$field]) || empty($_POST[$field])) {
                    throw new Exception("Campo requerido: $field");
                }
            }
            
            // Prepare data
            $id = intval($_POST['id']);
            $tagid = $_POST['tagid'];
            $etapa = $_POST['etapa'];
            $producto = $_POST['producto'];
            $racion = floatval($_POST['racion']);            
            $costo = floatval($_POST['costo']);
            $fecha = $_POST['fecha'];
            
            // Execute query
            $stmt = $conn->prepare("UPDATE ah_sal SET ah_sal_tagid = ?, ah_sal_etapa = ?, ah_sal_producto = ?, ah_sal_racion = ?, ah_sal_costo = ?, ah_sal_fecha = ? WHERE id = ?");
            $stmt->execute([$tagid, $etapa, $producto, $racion, $costo, $fecha, $id]);
            
            echo json_encode(['success' => true, 'message' => 'Registro actualizado correctamente']);
        }
        
        // DELETE operation
        else if ($action === 'delete') {
            // Validate required fields
            if (!isset($_POST['id']) || empty($_POST['id'])) {
                throw new Exception("ID requerido para eliminar");
            }
            
            // Prepare data
            $id = intval($_POST['id']);
            
            // Execute query
            $stmt = $conn->prepare("DELETE FROM ah_sal WHERE id = ?");
            $stmt->execute([$id]);
            
            echo json_encode(['success' => true, 'message' => 'Registro eliminado correctamente']);
        }
        
        // Invalid action
        else {
            throw new Exception("Acción inválida: $action");
        }
    } 
    catch (Exception $e) {
        // Return error message
        http_response_code(400);
        echo json_encode(['success' => false, 'message' => $e->getMessage()]);
    }
} 
else {
    // No action specified
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'No se especificó ninguna acción']);
}