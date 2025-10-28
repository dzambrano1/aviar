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

// Include database connection (Using PDO connection file)
require_once "./pdo_conexion.php"; 

// Ensure we have a valid PDO connection
if (!isset($conn) || !($conn instanceof PDO)) {
    // Log the error or display a user-friendly message
    // For debugging:
    error_log("Connection failed in cart.php: PDO connection not available");
    die('Database connection failed. Please try again later.');
    // In production, you might want a more graceful error handling
}

// Get user ID
$user_id = $_SESSION["user_id"];

// Initialize variables
$cart_items = [];
$total_precio = 0;
$item_count = 0;

try {
    // Get cart items using PDO
    $cart_query = "SELECT ci.id, ci.user_id, ci.tagid, ci.quantity, 
                   ci.precio, ci.nombre, ci.genero, ci.raza, 
                   ci.etapa, ci.grupo, ci.status, ci.image, 
                   a.image AS aviar_image -- Use alias to avoid name collision
                   FROM cart_items ci
                   LEFT JOIN aviar a ON ci.tagid = a.tagid 
                   WHERE ci.user_id = :user_id";
    $cart_stmt = $conn->prepare($cart_query);
    $cart_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $cart_stmt->execute();
    $cart_result = $cart_stmt->fetchAll(PDO::FETCH_ASSOC);

    // Process cart items
    foreach ($cart_result as $item) {
        // Prefer cart_items.image if available, otherwise use aviar.image
        $item['image'] = $item['image'] ?: $item['aviar_image']; 
        unset($item['aviar_image']); // Clean up the alias
        
        $cart_items[] = $item;
        $total_precio += $item['precio'] * $item['quantity'];
        $item_count += $item['quantity'];
    }

    // Handle remove item from cart using PDO
    if (isset($_POST['remove_item']) && isset($_POST['item_id'])) {
        $item_id = $_POST['item_id'];
        
        $remove_query = "DELETE FROM cart_items WHERE id = :item_id AND user_id = :user_id";
        $remove_stmt = $conn->prepare($remove_query);
        $remove_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
        $remove_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        
        if ($remove_stmt->execute()) {
            // Redirect to refresh the page
            header("Location: cart.php?removed=1");
            exit();
        }
    }

    // Handle update quantity using PDO
    if (isset($_POST['update_quantity']) && isset($_POST['item_id']) && isset($_POST['quantity'])) {
        $item_id = $_POST['item_id'];
        $quantity = max(1, intval($_POST['quantity'])); // Ensure quantity is at least 1
        
        $update_query = "UPDATE cart_items SET quantity = :quantity WHERE id = :item_id AND user_id = :user_id";
        $update_stmt = $conn->prepare($update_query);
        $update_stmt->bindParam(':quantity', $quantity, PDO::PARAM_INT);
        $update_stmt->bindParam(':item_id', $item_id, PDO::PARAM_INT);
        $update_stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
        
        if ($update_stmt->execute()) {
            // Redirect to refresh the page
            header("Location: cart.php?updated=1");
            exit();
        }
    }

} catch (PDOException $e) {
    // Log error and show a generic message to the user
    error_log("Database error in cart.php: " . $e->getMessage());
    die('An error occurred while accessing your cart. Please try again later.');
}

// Handle checkout (no database interaction here, just redirect)
if (isset($_POST['checkout']) && !empty($cart_items)) {
    // Redirect to checkout page
    header("Location: checkout.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Camión de Compras - Feria Ganadera Aviar</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="aviar.css">
    <style>
        .cart-container {
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
        
        .cart-container:hover {
            transform: perspective(1000px) translateZ(10px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.08),
                        0 1px 3px rgba(0, 0, 0, 0.1),
                        0 -1px 0 rgba(255, 255, 255, 0.8) inset;
        }
        
        .cart-header {
            border-bottom: 2px solid #4e6c41;
            margin-bottom: 20px;
            padding-bottom: 15px;
        }
        
        .cart-item {
            padding: 15px;
            margin-bottom: 15px;
            border-radius: 8px;
            background-color: #fff;
            box-shadow: 0 3px 10px rgba(0, 0, 0, 0.1);
            transition: all 0.3s ease;
            border-left: 5px solid #4e6c41;
        }
        
        .cart-item:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 15px rgba(0, 0, 0, 0.15);
        }
        
        .cart-item-image {
            max-width: 150px;
            max-height: 150px;
            object-fit: contain;
            border-radius: 6px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transition: transform 0.3s ease;
        }
        
        .cart-item-image:hover {
            transform: scale(1.05);
        }
        
        .cart-item-details {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .cart-item-title {
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 10px;
            color: #4e6c41;
        }
        
        .cart-item-subtitle {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 5px;
        }
        
        .cart-item-precio {
            font-size: 1.1rem;
            font-weight: 700;
            color: #4e6c41;
        }
        
        .cart-total {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 20px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .cart-total-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #dee2e6;
        }
        
        .cart-total-label {
            font-weight: 600;
            color: #495057;
        }
        
        .cart-total-value {
            font-weight: 700;
            color: #4e6c41;
        }
        
        .cart-total-final {
            font-size: 1.3rem;
            padding: 15px 0 5px;
        }
        
        .btn-quantity {
            border: none;
            background: #f8f9fa;
            color: #4e6c41;
            border-radius: 50%;
            width: 30px;
            height: 30px;
            font-size: 1rem;
            display: flex;
            justify-content: center;
            align-items: center;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
            transition: all 0.2s;
        }
        
        .btn-quantity:hover {
            background: #4e6c41;
            color: white;
            transform: scale(1.1);
        }
        
        .quantity-input {
            width: 60px;
            text-align: center;
            border: 1px solid #ced4da;
            border-radius: 4px;
            padding: 5px;
            font-weight: 600;
            color: #4e6c41;
        }
        
        .quantity-controls {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        
        .cart-action-btns {
            margin-top: 30px;
            display: flex;
            justify-content: space-between;
        }
        
        .btn-continue-shopping {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
            border: none;
            padding: 10px 20px;
            border-radius: 6px;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s;
        }
        
        .btn-continue-shopping:hover {
            background: linear-gradient(135deg, #495057, #343a40);
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }
        
        .btn-checkout {
            background: linear-gradient(135deg, #4e6c41, #759c63);
            color: white;
            border: none;
            padding: 15px 30px;
            border-radius: 6px;
            font-weight: 600;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.15);
            transition: all 0.3s;
        }
        
        .btn-checkout:hover {
            background: linear-gradient(135deg, #3d5834, #4e6c41);
            transform: translateY(-3px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.2);
        }
        
        .empty-cart {
            text-align: center;
            padding: 50px 20px;
            background: #f8f9fa;
            border-radius: 8px;
            box-shadow: inset 0 0 10px rgba(0, 0, 0, 0.05);
            margin: 30px 0;
        }
        
        .empty-cart i {
            font-size: 5rem;
            color: #dee2e6;
            margin-bottom: 20px;
        }
        
        .empty-cart h3 {
            color: #6c757d;
            margin-bottom: 20px;
        }
        
        .quantity-badge {
            background-color: #4e6c41;
            color: white;
            border-radius: 50%;
            padding: 3px 8px;
            font-size: 0.75rem;
            margin-left: 5px;
        }
        
        .animal-tag {
            display: inline-block;
            background: #e9ecef;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 0.85rem;
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
                    <i class="fas fa-cow ms-2"></i>
                    CAMIÓN DE COMPRAS
                    <i class="fas fa-truck ms-2"></i>
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
                        <a class="nav-link active" href="cart.php">SELECCIONADOS</a>
                    </div>
                </div>
                
                <?php if ($user_logged_in): ?>
                <div class="nav-link-item text-center mx-3">
                    <img src="./images/pedidos.png" alt="Ventas" width="40" class="nav-img">
                    <div>
                        <a class="nav-link" href="orders.php">VENTAS</a>
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
    <div class="cart-container">
        <div class="cart-header">
            <h2><i class="fas fa-truck-moving me-2"></i> Camión de Compras</h2>
            <p class="text-muted">Revise los animales seleccionados antes de confirmar su compra</p>
        </div>
        
        <?php if (!empty($cart_items)): ?>
            <!-- Cart Items -->
            <?php foreach ($cart_items as $item): ?>
            <div class="cart-item">
                <div class="row">
                    <div class="col-md-2 text-center">
                        <img src="<?php echo $item['image'] ?: './images/default_image.png'; ?>" alt="<?php echo htmlspecialchars($item['nombre']); ?>" class="cart-item-image">
                    </div>
                    <div class="col-md-7">
                        <div class="cart-item-details">
                            <div>
                                <h4 class="cart-item-title">
                                    <?php echo htmlspecialchars($item['nombre']); ?>
                                    <span class="quantity-badge"><?php echo $item['quantity']; ?></span>
                                </h4>
                                <div class="d-flex flex-wrap gap-2 mb-2">
                                    <span class="animal-tag"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($item['tagid']); ?></span>
                                    <span class="animal-tag"><i class="fas fa-venus-mars"></i> <?php echo htmlspecialchars($item['genero']); ?></span>
                                    <span class="animal-tag"><i class="fas fa-horse"></i> <?php echo htmlspecialchars($item['raza']); ?></span>
                                    <span class="animal-tag"><i class="fas fa-baby"></i> <?php echo htmlspecialchars($item['etapa']); ?></span>
                                    <?php if (!empty($item['grupo'])): ?>
                                    <span class="animal-tag"><i class="fas fa-users"></i> <?php echo htmlspecialchars($item['grupo']); ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="cart-item-subtitle">
                                    <span class="text-muted">Estatus:</span> <?php echo htmlspecialchars($item['status']); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="d-flex flex-column h-100 justify-content-between align-items-end">
                            <div class="cart-item-precio">
                                $<?php echo number_format($item['precio'], 2); ?> <small>c/u</small>
                            </div>
                            
                            <form method="post" class="mt-2">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <div class="quantity-controls">
                                    <button type="button" class="btn-quantity decrease-qty">-</button>
                                    <input type="number" name="quantity" min="1" value="<?php echo $item['quantity']; ?>" class="quantity-input">
                                    <button type="button" class="btn-quantity increase-qty">+</button>
                                    <button type="submit" name="update_quantity" class="btn btn-sm btn-outline-primary ms-2">
                                        <i class="fas fa-sync-alt"></i>
                                    </button>
                                </div>
                            </form>
                            
                            <form method="post" class="mt-2">
                                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>">
                                <button type="submit" name="remove_item" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash-alt me-1"></i> Eliminar
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
            
            <!-- Cart Total -->
            <div class="cart-total">
                <div class="cart-total-row">
                    <span class="cart-total-label">Subtotal:</span>
                    <span class="cart-total-value">$<?php echo number_format($total_precio, 2); ?></span>
                </div>
                <div class="cart-total-row">
                    <span class="cart-total-label">Impuestos:</span>
                    <span class="cart-total-value">$<?php echo number_format($total_precio * 0.16, 2); ?></span>
                </div>
                <div class="cart-total-row cart-total-final">
                    <span class="cart-total-label">Total:</span>
                    <span class="cart-total-value">$<?php echo number_format($total_precio * 1.16, 2); ?></span>
                </div>
            </div>
            
            <!-- Cart Action Buttons -->
            <div class="cart-action-btns">
                <a href="aviar_feria.php" class="btn btn-continue-shopping">
                    <i class="fas fa-arrow-left me-2"></i> Continuar Comprando
                </a>
                <form method="post">
                    <button type="submit" name="checkout" class="btn btn-checkout">
                        <i class="fas fa-check-circle me-2"></i> Proceder al Pago
                    </button>
                </form>
            </div>
        <?php else: ?>
            <!-- Empty Cart -->
            <div class="empty-cart">
                <i class="fas fa-shopping-cart"></i>
                <h3>Su camión de compras está vacío</h3>
                <p>Regrese a la feria para seleccionar animales.</p>
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
    // Quantity increment/decrement
    $('.increase-qty').click(function() {
        let input = $(this).siblings('.quantity-input');
        let currentValue = parseInt(input.val());
        input.val(currentValue + 1);
    });
    
    $('.decrease-qty').click(function() {
        let input = $(this).siblings('.quantity-input');
        let currentValue = parseInt(input.val());
        if (currentValue > 1) {
            input.val(currentValue - 1);
        }
    });
    
    // Show success message if item was removed
    <?php if (isset($_GET['removed'])): ?>
    alert('Item removed from cart successfully');
    <?php endif; ?>
    
    // Show success message if quantity was updated
    <?php if (isset($_GET['updated'])): ?>
    alert('Cart updated successfully');
    <?php endif; ?>
});
</script>

</body>
</html> 