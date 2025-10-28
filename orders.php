<?php
// Include the session check file
require_once 'check_session.php';

// Require login for this page
requireLogin();

// Check if user has a valid role (admin, usuario, or comprador)
if ($_SESSION["role"] !== "admin" && $_SESSION["role"] !== "usuario" && $_SESSION["role"] !== "comprador") {
    // Redirect to permission denied or home page
    header("Location: ./login.php?error=permission_denied");
    exit();
}

// Check user role
$user_logged_in = $isUserLoggedIn;
$user_role = $user_logged_in ? $_SESSION["role"] : "cliente";

// Include database connection (Use PDO connection file)
require_once "./pdo_conexion.php";

// Ensure we have a valid PDO connection
if (!isset($conn) || !($conn instanceof PDO)) {
    error_log("Orders page failed: PDO connection not available in conexion.php");
    die('Could not connect to the database to view orders. Please try again later.');
}

// Get user ID
$user_id = $_SESSION["user_id"];

// Initialize variables
$orders = [];
$item_count = 0;
$message = '';
$error = '';

// --- Get cart item count for navigation badge --- 
if ($user_logged_in) {
    try {
        $cart_query = "SELECT SUM(quantity) as total FROM cart_items WHERE user_id = :user_id";
        $cart_stmt = $conn->prepare($cart_query);
        $cart_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        $cart_stmt->execute();
        $cart_row = $cart_stmt->fetch(PDO::FETCH_ASSOC);
        $item_count = (int)($cart_row['total'] ?? 0);
    } catch (PDOException $e) {
        // Silently fail, just don't show the cart count
        error_log("Error fetching cart count for orders page: " . $e->getMessage());
        $item_count = 0;
    }
}

// --- Handle estatus update (admin only) --- 
if ($user_role === "admin" && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $order_id_to_update = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $new_status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);
    $valid_statuses = ['pendiente', 'pagado', 'cancelado']; // Adjusted status values

    if ($order_id_to_update && $new_status && in_array($new_status, $valid_statuses)) {
        try {
            $update_query = "UPDATE orders SET status = :status WHERE order_id = :order_id";
            $update_stmt = $conn->prepare($update_query);
            $update_stmt->bindParam(':status', $new_status, PDO::PARAM_STR);
            $update_stmt->bindParam(':order_id', $order_id_to_update, PDO::PARAM_INT);
            
            if ($update_stmt->execute()) {
                $message = "Estado del pedido #{$order_id_to_update} actualizado a {$new_status}.";
            } else {
                $error = "Error al actualizar el estado del pedido.";
            }
        } catch (PDOException $e) {
            $error = "Error de base de datos al actualizar estado: " . $e->getMessage();
            error_log($error);
        }
    } else {
        $error = "Datos inválidos para actualizar estado.";
    }
    // Optional: Redirect after POST to prevent resubmission
    // header("Location: orders.php?" . ($error ? "error=" . urlencode($error) : "message=" . urlencode($message)));
    // exit();
}

// --- Fetch Orders (All for Admin, Own for User) --- 
$fetched_orders = [];
try {
    if ($user_role === "admin") {
        // Admin fetches all orders and joins with users table (assuming users table pk is 'id')
        $order_query = "SELECT o.*, u.firstName, u.email, u.mobile 
                       FROM orders o 
                       LEFT JOIN users u ON o.user_id = u.id -- Corrected JOIN condition
                       ORDER BY o.order_date DESC";
        $order_stmt = $conn->prepare($order_query);
    } else {
        // Regular user fetches only their own orders
        $order_query = "SELECT o.* 
                       FROM orders o 
                       WHERE o.user_id = :user_id 
                       ORDER BY o.order_date DESC";
        $order_stmt = $conn->prepare($order_query);
        $order_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    }
    $order_stmt->execute();
    $fetched_orders = $order_stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (PDOException $e) {
    $error = "Error fetching orders: " . $e->getMessage();
    error_log($error);
}

// --- Fetch Order Details for each order --- 
if (!empty($fetched_orders)) {
    // Prepare statement for fetching items once
    $items_query = "SELECT od.*, v.image AS vacuno_image -- Use alias for vacuno image 
                    FROM order_details od 
                    LEFT JOIN vacuno v ON od.tagid = v.tagid 
                    WHERE od.order_id = :order_id";
    $items_stmt = $conn->prepare($items_query);

    foreach ($fetched_orders as $order) {
        $current_order_id = $order['order_id']; // Use correct key
        $order_details = [];
        try {
            $items_stmt->bindParam(':order_id', $current_order_id, PDO::PARAM_INT);
            $items_stmt->execute();
            $items_result = $items_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($items_result as $item) {
                 // Prefer order_details image if exists, otherwise use vacuno image
                $item['image'] = $item['image'] ?: ($item['vacuno_image'] ?: './images/default_animal.png');
                unset($item['vacuno_image']); // Clean up alias
                $order_details[] = $item;
            }

        } catch (PDOException $e) {
            $error .= " | Error fetching details for order #{$current_order_id}: " . $e->getMessage();
            error_log("Error fetching details for order #{$current_order_id}: " . $e->getMessage());
        }
        
        // Add the fetched items to the order array
        // Find the key of the current order in the main $orders array to add items
        $order_key = array_search($current_order_id, array_column($fetched_orders, 'order_id'));
        if ($order_key !== false) {
             $fetched_orders[$order_key]['items'] = $order_details;
        }
    }
    $orders = $fetched_orders; // Final array with items included
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ventas - Feria Ganadera</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="vacuno.css">
    <style>
        .orders-container {
            background: linear-gradient(to bottom, #ffffff, #f8f9fa);
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.05),
                        0 1px 3px rgba(0, 0, 0, 0.1),
                        0 -1px 0 rgba(255, 255, 255, 0.8) inset;
            border: 1px solid rgba(0, 0, 0, 0.05);
            transform: perspective(1000px) translateZ(0);
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        .orders-container:hover {
            transform: perspective(1000px) translateZ(10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08),
                        0 1px 3px rgba(0, 0, 0, 0.1),
                        0 -1px 0 rgba(255, 255, 255, 0.8) inset;
        }
        
        .orders-header {
            border-bottom: 2px solid #4e6c41;
            margin-bottom: 20px;
            padding-bottom: 15px;
        }
        
        .order-card {
            margin-bottom: 30px;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
        }
        
        .order-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.15);
        }
        
        .order-header {
            background: linear-gradient(135deg, #4e6c41, #759c63);
            color: white;
            padding: 15px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .order-id {
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .order-date {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .order-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: capitalize;
            letter-spacing: 0.5px;
        }
        
        .status-pendiente {
            background-color: #ffc107;
            color: #212529;
        }
        
        .status-pagado {
            background-color: #28a745;
            color: white;
        }
        
        .status-cancelado {
            background-color: #dc3545;
            color: white;
        }
        
        .order-body {
            padding: 20px;
            background-color: white;
        }
        
        .order-items {
            margin-top: 15px;
        }
        
        .order-item {
            padding: 10px 15px;
            margin-bottom: 10px;
            border-radius: 6px;
            background-color: #f8f9fa;
            display: flex;
            align-items: center;
            transition: all 0.2s ease;
        }
        
        .order-item:hover {
            background-color: #e9ecef;
            transform: translateX(5px);
        }
        
        .order-item-image {
            width: 60px;
            height: 60px;
            object-fit: cover;
            border-radius: 4px;
            margin-right: 15px;
        }
        
        .order-item-details {
            flex-grow: 1;
        }
        
        .order-item-title {
            font-weight: 600;
            margin-bottom: 3px;
            color: #4e6c41;
        }
        
        .order-item-meta {
            font-size: 0.85rem;
            color: #6c757d;
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        
        .order-item-price {
            font-weight: 700;
            color: #4e6c41;
            text-align: right;
            margin-left: auto;
            padding-left: 15px;
        }
        
        .order-total {
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
            display: flex;
            justify-content: space-between;
            font-weight: 700;
            font-size: 1.1rem;
        }
        
        .order-meta {
            margin-top: 15px;
            padding: 15px;
            background-color: #f8f9fa;
            border-radius: 6px;
            font-size: 0.9rem;
        }
        
        .order-meta-row {
            display: flex;
            margin-bottom: 8px;
        }
        
        .order-meta-label {
            font-weight: 600;
            width: 150px;
            color: #495057;
        }
        
        .order-meta-value {
            flex-grow: 1;
            color: #212529;
        }
        
        .order-actions {
            margin-top: 20px;
            display: flex;
            justify-content: flex-end;
            gap: 10px;
        }
        
        .empty-orders {
            text-align: center;
            padding: 50px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.05);
            margin: 30px 0;
        }
        
        .empty-orders i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-orders h3 {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .animal-tag {
            display: inline-block;
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            margin-right: 5px;
            color: #495057;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
        }
        
        .animal-tag i {
            margin-right: 3px;
            color: #4e6c41;
        }
    </style>
</head>
<body>

<!-- Navigation Title -->
<nav class="navbar text-center">
    <!-- Title Row -->
    <div class="container-fluid">
        <div class="row w-100">
            <div class="col-12 d-flex justify-content-center align-items-center">
                <h1 class="navbar-title text-center mx-auto">
                    <i class="fas fa-file-invoice-dollar ms-2"></i>
                    VENTAS DE GANADO
                    <i class="fas fa-file-invoice-dollar ms-2"></i>
                </h1>
                <div class="position-absolute end-0 me-3 d-flex align-items-center">
                    <span class="badge bg-primary me-2" style="font-size: 0.9rem; text-transform: capitalize;">
                        <?php echo $_SESSION["role"]; ?>
                    </span>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm" title="Cerrar sesión">
                        <i class="fas fa-sign-out-alt"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Icon Navigation Buttons -->
<div class="container navigation-buttons">
    <div class="row">
        <div class="col-12">
            <div class="nav-icons-container d-flex justify-content-center">
                <div class="icon-button-container">
                    <button onclick="window.location.href='../inicio.php'" class="icon-button">
                        <img src="./images/default_image.png" alt="Inicio" class="nav-icon">
                    </button>
                    <span class="button-label">INICIO</span>
                </div>
                
                <div class="icon-button-container">
                    <button onclick="window.location.href='./inventario_aviar.php'" class="icon-button">
                        <img src="./images/aviar-poblacion.png" alt="Inicio" class="nav-icon">
                    </button>
                    <span class="button-label">POBLACION</span>
                </div>

                <div class="icon-button-container">
                    <button onclick="window.location.href='./aviar_registros.php'" class="icon-button">
                        <img src="./images/registros.png" alt="Inicio" class="nav-icon">
                    </button>
                    <span class="button-label">REGISTROS</span>
                </div>
                
                <div class="icon-button-container">
                    <button onclick="window.location.href='./aviar_indices.php'" class="icon-button">
                        <img src="./images/fondo-indexado.png" alt="Inicio" class="nav-icon">
                    </button>
                    <span class="button-label">INDICES</span>
                </div>
                
                <div class="icon-button-container">
                    <button onclick="window.location.href='./aviar_configuracion.php'" class="icon-button">
                        <img src="./images/configuracion.png" alt="Inicio" class="nav-icon">
                    </button>
                    <span class="button-label">CONFIG</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Navigation Row -->
<div class="container mt-4" id="ecommerce-navbar">    
    <div class="row">
        <div class="col-12">
            <div class="nav-links-container d-flex justify-content-center">
                <div class="nav-link-item text-center mx-3">
                    <img src="./images/rebaño.png" alt="Publicados" width="40" class="nav-img">
                    <div>
                        <a class="nav-link" href="aviar_feria.php">PUBLICADOS</a>
                    </div>
                </div>
                
                <div class="nav-link-item text-center mx-3">
                    <div class="position-relative">
                        <img src="./images/carrito-animal.png" alt="Seleccionados" width="40" class="nav-img">
                        <?php if ($item_count > 0): ?>                                
                        <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size: 0.75rem;">
                            <?php echo $item_count; ?>
                        </span>
                        <?php endif; ?>
                    </div>
                    <div>
                        <a class="nav-link" href="cart.php">SELECCIONADOS</a>
                    </div>
                </div>
                
                <?php if ($user_logged_in): ?>
                <div class="nav-link-item text-center mx-3">
                    <img src="./images/pedidos.png" alt="Ventas" width="40" class="nav-img">
                    <div>
                        <a class="nav-link active" href="orders.php">VENTAS</a>
                    </div>
                </div>
                
                <div class="nav-link-item text-center mx-3">
                    <img src="./images/pagos.png" alt="Pagos" width="40" class="nav-img">
                    <div>
                        <a class="nav-link" href="payments.php">PAGOS</a>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Main Content -->
<div class="container mt-4">
    <div class="orders-container">
        <div class="orders-header">
            <h2><i class="fas fa-file-invoice me-2"></i> Ventas de Ganado Aviar</h2>
            <p class="text-muted">
                <?php echo ($user_role === "admin") ? "Administración de ventas y pedidos" : "Historial de sus compras y pedidos"; ?>
            </p>
        </div>
        
        <!-- Display messages/errors -->
        <?php if ($message): ?>
            <div class="alert alert-success" role="alert"><?php echo htmlspecialchars($message); ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger" role="alert"><?php echo htmlspecialchars($error); ?></div>
        <?php endif; ?>
        
        <?php if (!empty($orders)): ?>
            <!-- Orders -->
            <?php foreach ($orders as $order): ?>
            <div class="order-card">
                <div class="order-header">
                    <div>
                        <span class="order-id">Pedido #<?php echo $order['order_id']; ?></span>
                        <span class="order-date ms-3">
                            <i class="far fa-calendar-alt me-1"></i> 
                            <?php echo date('d/m/Y', strtotime($order['order_date'])); ?>
                        </span>
                    </div>
                    <div class="order-status status-<?php echo strtolower(htmlspecialchars($order['status'])); ?>">
                        <?php echo ucfirst(htmlspecialchars($order['status'])); ?>
                    </div>
                </div>
                <div class="order-body">
                    <?php if ($user_role === "admin" && isset($order['firstName'])): ?>
                    <div class="order-customer">
                        <strong><i class="fas fa-user me-1"></i> Cliente:</strong> 
                        <?php echo htmlspecialchars($order['firstName']); ?> 
                        (<?php echo htmlspecialchars($order['email']); ?>)
                        <?php if (!empty($order['mobile'])): ?>
                            <span class="ms-2"><i class="fas fa-mobile me-1"></i> <?php echo htmlspecialchars($order['mobile']); ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                    
                    <div class="order-items">
                        <h5>Animales en este pedido</h5>
                        
                        <?php foreach ($order['items'] as $item): ?>
                        <div class="order-item">
                            <img src="<?php echo htmlspecialchars($item['image'] ?: './images/default_image.png'); ?>" 
                                 alt="<?php echo htmlspecialchars($item['nombre'] ?? 'Animal'); ?>" 
                                 class="order-item-image">
                            
                            <div class="order-item-details">
                                <div class="order-item-title">
                                    <?php echo htmlspecialchars($item['nombre'] ?? 'Animal'); ?>
                                    <?php if (isset($item['quantity']) && $item['quantity'] > 1): ?>
                                    <span class="badge bg-secondary ms-1"><?php echo $item['quantity']; ?></span>
                                    <?php endif; ?>
                                </div>
                                <div class="order-item-meta">
                                    <span class="animal-tag"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['tagid']); ?></span>
                                    <span class="animal-tag"><i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($item['genero'] ?? ''); ?></span>
                                    <span class="animal-tag"><i class="fas fa-horse"></i> <?php echo htmlspecialchars($item['raza'] ?? ''); ?></span>
                                </div>
                            </div>
                            
                            <div class="order-item-price">
                                $<?php echo number_format($item['precio'] * $item['quantity'], 2); ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <div class="order-total">
                        <span>Total</span>
                        <span>$<?php echo number_format($order['total'], 2); ?></span>
                    </div>
                    
                    <?php if ($user_role === "admin"): ?>
                    <div class="order-actions">
                        <form method="post" class="d-flex gap-2">
                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                            <select name="status" class="form-select form-select-sm" style="width: auto;">
                                <option value="pendiente" <?php echo (strtolower($order['status']) === 'pendiente') ? 'selected' : ''; ?>>Pendiente</option>
                                <option value="pagado" <?php echo (strtolower($order['status']) === 'pagado') ? 'selected' : ''; ?>>Pagado</option>
                                <option value="cancelado" <?php echo (strtolower($order['status']) === 'cancelado') ? 'selected' : ''; ?>>Cancelado</option>
                            </select>
                            <button type="submit" name="update_status" class="btn btn-primary btn-sm">
                                <i class="fas fa-sync-alt me-1"></i> Actualizar
                            </button>
                        </form>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            
        <?php else: ?>
            <!-- Empty Orders -->
            <div class="empty-orders">
                <i class="fas fa-file-invoice"></i>
                <h3>No hay pedidos para mostrar</h3>
                <p>
                    <?php if ($user_role === "comprador"): ?>
                    Aún no ha realizado ninguna compra. Visite la feria para explorar los animales disponibles.
                    <?php else: ?>
                    No hay pedidos registrados en el sistema.
                    <?php endif; ?>
                </p>
                <a href="aviar_feria.php" class="btn btn-outline-primary mt-3">
                    <i class="fas fa-arrow-left me-2"></i> Ir a la Feria
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<script>
$(document).ready(function() {
    // Show success message if status was updated
    <?php if (isset($_GET['updated'])): ?>
    alert('Estatus del pedido actualizado correctamente');
    <?php endif; ?>
});
</script>

</body>
</html> 