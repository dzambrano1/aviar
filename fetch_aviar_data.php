<?php

require_once './pdo_conexion.php';

header('Content-Type: application/json');

if (!isset($_GET['tagid']) || empty($_GET['tagid'])) {
    echo json_encode(['error' => 'TagID is required']);
    exit;
}

$tagid = $_GET['tagid'];

// Use PDO for better security and prepared statements
try {
    $conn = new PDO("mysql:host=$servername;dbname=$dbname", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $stmt = $conn->prepare("SELECT a.id, a.tagid, a.nombre, a.genero, a.raza, a.etapa, a.grupo, a.estatus, 
                               a.fecha_nacimiento, a.fecha_compra, a.poblacion, a.peso_compra, a.monto_compra,
                               a.image, a.image2, a.image3, a.video, a.cantidad_compra, c.ah_compra_cantidad
                          FROM aviar a 
                          LEFT JOIN ah_compra c ON a.tagid = c.ah_compra_tagid 
                          WHERE a.tagid = :tagid 
                          ORDER BY c.id DESC LIMIT 1");
    $stmt->bindParam(':tagid', $tagid);
    $stmt->execute();
    
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($result) {
        echo json_encode($result);
    } else {
        echo json_encode(['error' => 'No record found for this TagID']);
    }
} catch(PDOException $e) {
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}