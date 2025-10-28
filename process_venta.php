<?php
require_once './pdo_conexion.php';

// Enable PDO error mode to get better error messages
$conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

header('Content-Type: application/json');

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método no permitido');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'insert') {
        // Validate required fields
        $tagid = trim($_POST['tagid'] ?? '');
        $precio = floatval($_POST['precio'] ?? 0);
        $peso = floatval($_POST['peso'] ?? 0);
        $aves = intval($_POST['aves'] ?? 0);
        $fecha = trim($_POST['fecha'] ?? '');
        
        if (empty($tagid) || $precio <= 0 || $peso <= 0 || $aves <= 0 || empty($fecha)) {
            throw new Exception('Todos los campos son requeridos y deben tener valores válidos');
        }
        
        // Check if animal exists and has sufficient population
        $checkQuery = "SELECT id, poblacion FROM aviar WHERE tagid = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->execute([$tagid]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$animal) {
            throw new Exception('Animal con Tag ID ' . $tagid . ' no encontrado');
        }
        
        if ($animal['poblacion'] < $aves) {
            throw new Exception('No hay suficientes aves disponibles. Población actual: ' . $animal['poblacion']);
        }
        
        // Calculate total amount: (precio * peso) * aves
        $totalAmount = ($precio * $peso) * $aves;
        
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // Insert into ah_ventas table
            $insertQuery = "INSERT INTO ah_ventas (
                                ah_ventas_tagid,
                                ah_ventas_fecha,
                                ah_ventas_precio_promedio,
                                ah_ventas_peso_promedio,
                                ah_ventas_cantidad,
                                ah_ventas_monto,
                                ah_ventas_estatus
                            ) VALUES (?, ?, ?, ?, ?, ?, 'Vendido')";
            
            $stmt = $conn->prepare($insertQuery);
            $stmt->execute([$tagid, $fecha, $precio, $peso, $aves, $totalAmount]);
            
            // Update aviar table with sale information and population
            $updateQuery = "UPDATE aviar SET 
                            fecha_venta = ?,
                            precio_venta = ?,
                            peso_venta = ?,
                            estatus = 'Vendido',
                            poblacion = poblacion - ?
                           WHERE tagid = ?";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->execute([$fecha, $precio, $peso, $aves, $tagid]);
            
            // Commit transaction
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Venta registrada exitosamente',
                'total_amount' => $totalAmount
            ]);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            throw $e;
        }
        
    } else {
        throw new Exception('Acción no válida');
    }
    
} catch (Exception $e) {
    error_log("Error in process_venta.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}