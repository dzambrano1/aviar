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
    
    $stmt = $conn->prepare("SELECT id, tagid, nombre, genero, raza, etapa, grupo, estatus, 
                               fecha_nacimiento, fecha_compra, image, image2, image3, video 
                          FROM aviar 
                          WHERE tagid = :tagid");
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
?> 