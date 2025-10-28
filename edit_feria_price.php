<?php
// Include session check
require_once 'check_session.php';
requireAdmin(); // Only admin can edit prices

// Include database connection
require_once './pdo_conexion.php';

// Initialize variables
$tagid = $_GET['tagid'] ?? '';
$success_message = '';
$error_message = '';
$animal_data = null;

// Check if tagid is provided
if (empty($tagid)) {
    $error_message = "ID del animal no proporcionado.";
} else {
    // Initialize the mysqli connection
    $conn = mysqli_connect($servername, $username, $password, $dbname);
    if (!$conn) {
        die('Connection failed: ' . mysqli_connect_error());
    }
    
    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $precio_venta = isset($_POST['precio_venta']) ? floatval($_POST['precio_venta']) : 0;
        
        if ($precio_venta <= 0) {
            $error_message = "Por favor ingrese un precio_venta válido mayor a cero.";
        } else {
            // Update animal price
            $update_sql = "UPDATE aviar SET precio_venta = ?, fecha_publicacion = NOW() WHERE tagid = ?";
            $update_stmt = $conn->prepare($update_sql);
            $update_stmt->bind_param("ds", $precio_venta, $tagid);
            
            if ($update_stmt->execute()) {
                $success_message = "Precio actualizado exitosamente.";
            } else {
                $error_message = "Error al actualizar el precio_venta: " . $conn->error;
            }
        }
    }
    
    // Get animal data
    $animal_sql = "SELECT tagid, nombre, genero, raza, etapa, grupo, image, precio_venta, fecha_publicacion 
                    FROM aviar WHERE tagid = ?";
    $animal_stmt = $conn->prepare($animal_sql);
    $animal_stmt->bind_param("s", $tagid);
    $animal_stmt->execute();
    $result = $animal_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $animal_data = $result->fetch_assoc();
    } else {
        $error_message = "Animal no encontrado.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Editar Precio - Feria Ganadera Aviar</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            padding-top: 56px;
        }
        .animal-details {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .animal-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.2);
        }
        .animal-detail-row {
            margin-bottom: 10px;
            padding-bottom: 10px;
            border-bottom: 1px solid #e9ecef;
        }
        .animal-detail-label {
            font-weight: bold;
            color: #495057;
        }
        .animal-detail-value {
            color: #343a40;
        }
        .page-header {
            margin-bottom: 30px;
            border-bottom: 2px solid #dee2e6;
            padding-bottom: 10px;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <?php include 'navbar.php'; ?>
    
    <div class="container mt-5 pt-3">
        <div class="row">
            <div class="col-md-12">
                <div class="page-header">
                    <h2><i class="fas fa-tags me-2"></i> Editar Precio del Animal</h2>
                    <p class="text-muted">Actualice el precio de venta para la publicación en la feria</p>
                </div>
                
                <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle me-2"></i> <?php echo $error_message; ?>
                </div>
                <?php endif; ?>
                
                <?php if (!empty($success_message)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle me-2"></i> <?php echo $success_message; ?>
                    <div class="mt-2">
                        <a href="aviar_feria.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-arrow-left me-1"></i> Volver a la Feria
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if ($animal_data): ?>
                <div class="row">
                    <div class="col-md-4">
                        <img src="<?php echo !empty($animal_data['image']) ? $animal_data['image'] : './images/default_image.png'; ?>" 
                             alt="<?php echo htmlspecialchars($animal_data['nombre']); ?>" 
                             class="animal-image mb-3">
                    </div>
                    <div class="col-md-8">
                        <div class="animal-details">
                            <div class="animal-detail-row">
                                <span class="animal-detail-label">ID:</span>
                                <span class="animal-detail-value"><?php echo htmlspecialchars($animal_data['tagid']); ?></span>
                            </div>
                            <div class="animal-detail-row">
                                <span class="animal-detail-label">Nombre:</span>
                                <span class="animal-detail-value"><?php echo htmlspecialchars($animal_data['nombre']); ?></span>
                            </div>
                            <div class="animal-detail-row">
                                <span class="animal-detail-label">Raza:</span>
                                <span class="animal-detail-value"><?php echo htmlspecialchars($animal_data['raza']); ?></span>
                            </div>
                            <div class="animal-detail-row">
                                <span class="animal-detail-label">Género:</span>
                                <span class="animal-detail-value"><?php echo htmlspecialchars($animal_data['genero']); ?></span>
                            </div>
                            <div class="animal-detail-row">
                                <span class="animal-detail-label">Etapa:</span>
                                <span class="animal-detail-value"><?php echo htmlspecialchars($animal_data['etapa']); ?></span>
                            </div>
                            <div class="animal-detail-row">
                                <span class="animal-detail-label">Grupo:</span>
                                <span class="animal-detail-value"><?php echo htmlspecialchars($animal_data['grupo']); ?></span>
                            </div>
                            <div class="animal-detail-row">
                                <span class="animal-detail-label">Fecha de Publicación:</span>
                                <span class="animal-detail-value">
                                    <?php echo !empty($animal_data['fecha_publicacion']) ? 
                                            date('d/m/Y', strtotime($animal_data['fecha_publicacion'])) : 'No publicado'; ?>
                                </span>
                            </div>
                            <div class="animal-detail-row">
                                <span class="animal-detail-label">Precio Actual:</span>
                                <span class="animal-detail-value">
                                    <?php echo !empty($animal_data['precio_venta']) && $animal_data['precio_venta'] > 0 ? 
                                            '$' . number_format($animal_data['precio_venta'], 2) : 'No publicado'; ?>
                                </span>
                            </div>
                        </div>
                        
                        <form method="post" action="" class="mt-4">
                            <div class="mb-3">
                                <label for="precio_venta" class="form-label">Nuevo Precio ($)</label>
                                <div class="input-group">
                                    <span class="input-group-text">$</span>
                                    <input type="number" class="form-control" id="precio_venta" name="precio_venta" 
                                           step="0.01" min="0.01" value="<?php echo $animal_data['precio_venta'] ?: ''; ?>" required>
                                </div>
                                <div class="form-text">Ingrese el nuevo precio de venta para este animal en la feria.</div>
                            </div>
                            
                            <div class="d-flex">
                                <button type="submit" class="btn btn-primary me-2">
                                    <i class="fas fa-save me-1"></i> Actualizar Precio
                                </button>
                                <a href="aviar_feria.php" class="btn btn-secondary">
                                    <i class="fas fa-times me-1"></i> Cancelar
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 