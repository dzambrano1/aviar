<?php
require_once './pdo_conexion.php';

// Set header for JSON response
header('Content-Type: application/json');

// Check if it's a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode([
        'success' => false,
        'message' => 'Método no permitido'
    ]);
    exit;
}

// Check if action is create_table
if (!isset($_POST['action']) || $_POST['action'] !== 'create_table') {
    echo json_encode([
        'success' => false,
        'message' => 'Acción no válida'
    ]);
    exit;
}

try {
    // Check if table already exists
    $checkTableQuery = "SHOW TABLES LIKE 'alimentacion_engorde'";
    $stmt = $conn->prepare($checkTableQuery);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        echo json_encode([
            'success' => false,
            'message' => 'La tabla alimentacion_engorde ya existe'
        ]);
        exit;
    }
    
    // Create the table
    $createTableQuery = "
    CREATE TABLE IF NOT EXISTS `alimentacion_engorde` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `etapa` varchar(100) NOT NULL COMMENT 'Growth stage (e.g., Inicio, Crecimiento, Finalización)',
        `edad_dias` int(11) NOT NULL COMMENT 'Age in days for this stage',
        `alimento_tipo` varchar(100) NOT NULL COMMENT 'Type of feed (e.g., Starter, Grower, Finisher)',
        `racion_diaria_kg` decimal(10,2) NOT NULL COMMENT 'Daily ration in kg per bird',
        `frecuencia_diaria` int(11) NOT NULL DEFAULT 2 COMMENT 'Number of feedings per day',
        `proteinas_porcentaje` decimal(5,2) NOT NULL COMMENT 'Protein percentage in feed',
        `energia_kcal_kg` int(11) NOT NULL COMMENT 'Energy content in kcal/kg',
        `fibra_porcentaje` decimal(5,2) NOT NULL COMMENT 'Fiber percentage in feed',
        `calcio_porcentaje` decimal(5,2) NOT NULL COMMENT 'Calcium percentage in feed',
        `fosforo_porcentaje` decimal(5,2) NOT NULL COMMENT 'Phosphorus percentage in feed',
        `observaciones` text COMMENT 'Additional notes or recommendations',
        `fecha_creacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
        `fecha_actualizacion` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (`id`),
        KEY `idx_etapa` (`etapa`),
        KEY `idx_edad_dias` (`edad_dias`)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Suggested feeding plan for broiler chickens'
    ";
    
    $stmt = $conn->prepare($createTableQuery);
    $stmt->execute();
    
    // Insert sample data
    $insertDataQuery = "
    INSERT INTO `alimentacion_engorde` 
    (`etapa`, `edad_dias`, `alimento_tipo`, `racion_diaria_kg`, `frecuencia_diaria`, 
     `proteinas_porcentaje`, `energia_kcal_kg`, `fibra_porcentaje`, `calcio_porcentaje`, 
     `fosforo_porcentaje`, `peso_objetivo_kg`, `observaciones`) VALUES
    (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ";
    
    $stmt = $conn->prepare($insertDataQuery);
    
    // Sample data array
    $sampleData = [
        ['Inicio', 1, 'Starter', 0.02, 6, 23.00, 3000, 3.50, 1.00, 0.45, 0.05, 'Alimento de alta proteína para pollitos recién nacidos. Alimentar cada 4 horas.'],
        ['Inicio', 7, 'Starter', 0.04, 6, 23.00, 3000, 3.50, 1.00, 0.45, 0.15, 'Mantener alta frecuencia de alimentación. Agua fresca siempre disponible.'],
        ['Crecimiento', 14, 'Grower', 0.08, 4, 20.00, 3100, 4.00, 0.90, 0.40, 0.45, 'Reducir frecuencia de alimentación. Monitorear consumo de agua.'],
        ['Crecimiento', 21, 'Grower', 0.12, 4, 20.00, 3100, 4.00, 0.90, 0.40, 0.85, 'Ajustar raciones según consumo. Verificar calidad del alimento.'],
        ['Crecimiento', 28, 'Grower', 0.16, 3, 20.00, 3100, 4.00, 0.90, 0.40, 1.35, 'Controlar peso corporal. Evitar sobrealimentación.'],
        ['Finalización', 35, 'Finisher', 0.18, 3, 18.00, 3200, 4.50, 0.80, 0.35, 1.85, 'Alimento de menor proteína para engorde final.'],
        ['Finalización', 42, 'Finisher', 0.20, 3, 18.00, 3200, 4.50, 0.80, 0.35, 2.35, 'Última semana antes del sacrificio. Control de peso final.'],
        ['Finalización', 49, 'Finisher', 0.22, 3, 18.00, 3200, 4.50, 0.80, 0.35, 2.85, 'Día del sacrificio. Retirar alimento 8-12 horas antes.']
    ];
    
    // Insert each sample record
    foreach ($sampleData as $data) {
        $stmt->execute($data);
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Tabla alimentacion_engorde creada exitosamente con ' . count($sampleData) . ' registros de ejemplo'
    ]);
    
} catch (PDOException $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error al crear la tabla: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'message' => 'Error inesperado: ' . $e->getMessage()
    ]);
}
?>
