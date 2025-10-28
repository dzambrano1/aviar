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
    
                 if ($action === 'update') {
                 // Validate required fields
                 $tagid = trim($_POST['tagid'] ?? '');
                 $precio_venta = floatval($_POST['precio_venta'] ?? 0);
                 $peso_venta = floatval($_POST['peso_venta'] ?? 0);
                 $aves_vendidas = intval($_POST['aves_vendidas'] ?? 0);
                 $fecha_venta = trim($_POST['fecha_venta'] ?? '');
                 $estatus = trim($_POST['estatus'] ?? 'Vendido');
                 
                 if (empty($tagid) || $precio_venta <= 0 || $peso_venta <= 0 || $aves_vendidas <= 0 || empty($fecha_venta)) {
                     throw new Exception('Todos los campos son requeridos y deben tener valores válidos');
                 }
        
        // Check if animal exists and get current population info
        $checkQuery = "SELECT id, poblacion FROM aviar WHERE tagid = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->execute([$tagid]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$animal) {
            throw new Exception('Animal con Tag ID ' . $tagid . ' no encontrado');
        }
        
        // Get current sold quantity from ah_ventas table
        $ventasQuery = "SELECT ah_ventas_cantidad FROM ah_ventas WHERE ah_ventas_tagid = ?";
        $stmt = $conn->prepare($ventasQuery);
        $stmt->execute([$tagid]);
        $ventasData = $stmt->fetch(PDO::FETCH_ASSOC);
        $oldAvesVendidas = $ventasData ? $ventasData['ah_ventas_cantidad'] : 0;
        
        // Calculate population adjustment
        $populationAdjustment = $oldAvesVendidas - $aves_vendidas; // Positive if reducing, negative if increasing
        
        // Check if the new quantity would exceed available population
        $newPopulation = $animal['poblacion'] + $populationAdjustment;
        if ($newPopulation < 0) {
            throw new Exception('No hay suficientes aves disponibles. La cantidad solicitada excedería la población disponible.');
        }
                 
                 // Calculate total amount: (precio * peso) * aves_vendidas
                 $totalAmount = ($precio_venta * $peso_venta) * $aves_vendidas;
        
        // Begin transaction
        $conn->beginTransaction();
        
        try {
                        // Update aviar table with new values
            $updateQuery = "UPDATE aviar SET 
                            precio_venta = ?,
                            peso_venta = ?,
                            fecha_venta = ?,
                            estatus = ?
                           WHERE tagid = ?";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->execute([$precio_venta, $peso_venta, $fecha_venta, $estatus, $tagid]);
            
                                 // Update ah_ventas table with new values including quantity
                     $updateVentasQuery = "UPDATE ah_ventas SET 
                                             ah_ventas_precio_promedio = ?,
                                             ah_ventas_peso_promedio = ?,
                                             ah_ventas_cantidad = ?,
                                             ah_ventas_fecha = ?,
                                             ah_ventas_monto = ?,
                                             ah_ventas_estatus = ?
                                            WHERE ah_ventas_tagid = ?";
                     
                                          $stmt = $conn->prepare($updateVentasQuery);
                     $stmt->execute([$precio_venta, $peso_venta, $aves_vendidas, $fecha_venta, $totalAmount, $estatus, $tagid]);
                     
                     // Update population in aviar table to reflect the quantity change
                     $updatePopulationQuery = "UPDATE aviar SET poblacion = ? WHERE tagid = ?";
                     $stmt = $conn->prepare($updatePopulationQuery);
                     $stmt->execute([$newPopulation, $tagid]);
                     
                     // Commit transaction
                     $conn->commit();
            
                                 echo json_encode([
                         'success' => true,
                         'message' => 'Información de venta actualizada correctamente',
                         'total_amount' => $totalAmount,
                         'population_adjustment' => $populationAdjustment,
                         'new_population' => $newPopulation
                     ]);
            
        } catch (Exception $e) {
            // Rollback transaction on error
            $conn->rollBack();
            throw $e;
        }
        
    } elseif ($action === 'delete') {
        $tagid = trim($_POST['tagid'] ?? '');
        
        if (empty($tagid)) {
            throw new Exception('Tag ID es requerido');
        }
        
        // Check if animal exists
        $checkQuery = "SELECT id, poblacion FROM aviar WHERE tagid = ?";
        $stmt = $conn->prepare($checkQuery);
        $stmt->execute([$tagid]);
        $animal = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$animal) {
            throw new Exception('Animal con Tag ID ' . $tagid . ' no encontrado');
        }
        
        // Get current sold quantity from ah_ventas table
        $ventasQuery = "SELECT ah_ventas_cantidad FROM ah_ventas WHERE ah_ventas_tagid = ?";
        $stmt = $conn->prepare($ventasQuery);
        $stmt->execute([$tagid]);
        $ventasData = $stmt->fetch(PDO::FETCH_ASSOC);
        $avesVendidas = $ventasData ? $ventasData['ah_ventas_cantidad'] : 0;
        
        // Begin transaction
        $conn->beginTransaction();
        
        try {
            // Restore population and clear sale information in aviar table
            $restorePopulation = $animal['poblacion'] + $avesVendidas;
            
            $updateQuery = "UPDATE aviar SET 
                            precio_venta = NULL,
                            peso_venta = NULL,
                            fecha_venta = NULL,
                            poblacion = ?,
                            estatus = 'Activo'
                           WHERE tagid = ?";
            
            $stmt = $conn->prepare($updateQuery);
            $stmt->execute([$restorePopulation, $tagid]);
            
            // Delete from ah_ventas table
            $deleteQuery = "DELETE FROM ah_ventas WHERE ah_ventas_tagid = ?";
            $stmt = $conn->prepare($deleteQuery);
            $stmt->execute([$tagid]);
            
            // Commit transaction
            $conn->commit();
            
            echo json_encode([
                'success' => true,
                'message' => 'Registro de venta eliminado correctamente. El animal ha vuelto a estar disponible para venta.',
                'restored_population' => $restorePopulation
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
    error_log("Error in aviar_venta_update.php: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
?>
