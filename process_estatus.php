<?php
require_once './pdo_conexion.php';

$conn = new mysqli($servername, $username, $password, $dbname);

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Database connection
    $conn = new mysqli('localhost', $username, $password, $dbname);
    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Get form data
    $grupos_nombre = $_POST['grupos_nombre'];

    // Insert data into alimentacion table
    $sql = "INSERT INTO b_grupos (grupos_nombre) VALUES ('$grupos_nombre')";

    if ($conn->query($sql) === FALSE) {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

    $conn->close();

    // Redirect to config_alimento.php
    header("Location: aviar_configuracion_grupos.php");
    exit();
}
?>