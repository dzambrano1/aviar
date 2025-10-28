<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include session check functions
require_once 'check_session.php';

// Require user to be logged in (adjust role if needed, e.g., requireCustomer())
requireLogin(); 

// Get user ID from session
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php?error=session_expired");
    exit;
}
$user_id = $_SESSION['user_id'];

// Include database connection (MUST provide a PDO connection as $conn)
require_once "./pdo_conexion.php"; 

// Ensure we have a valid PDO connection
if (!isset($conn) || !($conn instanceof PDO)) {
    error_log("Pagos: PDO connection not available in conexion.php");
    die('No se pudo conectar a la base de datos. Por favor, inténtelo de nuevo más tarde.');
}

$payment_message = '';
$payment_error = '';

// --- Handle Simulated Payment POST Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['pay_order'])) {
    $order_id_to_pay = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
    $amount_to_pay = filter_input(INPUT_POST, 'amount', FILTER_VALIDATE_FLOAT);

    if ($order_id_to_pay && $amount_to_pay > 0) {
        try {
            $conn->beginTransaction();

            // 1. Verify the order belongs to the user and is pending payment
            $verify_query = "SELECT total, status FROM orders WHERE order_id = :order_id AND user_id = :user_id FOR UPDATE";
            $stmt_verify = $conn->prepare($verify_query);
            $stmt_verify->bindParam(':order_id', $order_id_to_pay, PDO::PARAM_INT);
            $stmt_verify->bindParam(':user_id', $user_id, PDO::PARAM_INT);
            $stmt_verify->execute();
            $order = $stmt_verify->fetch(PDO::FETCH_ASSOC);

            if (!$order) {
                throw new Exception("Orden no encontrada o no pertenece a ti.");
            }

            if ($order['status'] !== 'pendiente') {
                throw new Exception("Esta orden no esta pendiente de pago (Status: " . htmlspecialchars($order['status']) . ").");
            }
            
            // Optional: Verify amount matches order total
            if (abs($order['total'] - $amount_to_pay) > 0.01) { // Allow for small floating point differences
                // Decide how to handle mismatch - here we throw an error
                throw new Exception("El monto del pago no coincide con el total de la orden.");
            }

            // 2. Insert into payments table
            $insert_payment_query = "INSERT INTO payments (order_id, payment_date, amount, payment_method, status) 
                                       VALUES (:order_id, NOW(), :amount, :method, :status)";
            $stmt_payment = $conn->prepare($insert_payment_query);
            $payment_method = 'simulated_card'; // Example payment method
            $payment_status = 'pagado';
            $stmt_payment->bindParam(':order_id', $order_id_to_pay, PDO::PARAM_INT);
            $stmt_payment->bindParam(':amount', $amount_to_pay, PDO::PARAM_STR);
            $stmt_payment->bindParam(':method', $payment_method, PDO::PARAM_STR);
            $stmt_payment->bindParam(':status', $payment_status, PDO::PARAM_STR);
            
            if (!$stmt_payment->execute()) {
                throw new Exception("Fallo en el registro del pago.");
            }
            $payment_id = $conn->lastInsertId();

            // 3. Update order status
            $update_order_query = "UPDATE orders SET status = :new_status WHERE order_id = :order_id";
            $stmt_update = $conn->prepare($update_order_query);
            $new_status = 'pagado';
            $stmt_update->bindParam(':new_status', $new_status, PDO::PARAM_STR);
            $stmt_update->bindParam(':order_id', $order_id_to_pay, PDO::PARAM_INT);

            if (!$stmt_update->execute()) {
                throw new Exception("Fallo actualizacion de estatus luego del pago.");
            }

            // 4. Commit transaction
            $conn->commit();
            $payment_message = "Pago de la orden #{$order_id_to_pay} exitoso! Payment ID: {$payment_id}";

        } catch (Exception $e) {
            if ($conn->inTransaction()) {
                $conn->rollBack();
            }
            $payment_error = "Pago fallido: " . $e->getMessage();
            error_log("Error de procesamiento de pago para el usuario {$user_id}, orden {$order_id_to_pay}: " . $e->getMessage());
        }
    } else {
        $payment_error = "Datos inválidos enviados para el pago.";
    }
}

// --- Fetch Orders Awaiting Payment with Details --- 
$pending_orders = []; // This will hold the grouped orders
try {
    // Query to get orders and their details
    $query = "SELECT 
                o.order_id, o.order_date, o.total, o.status,
                od.tagid, od.nombre, od.raza, od.genero, od.image
              FROM orders o
              JOIN order_details od ON o.order_id = od.order_id
              WHERE o.user_id = :user_id AND o.status = :status 
              ORDER BY o.order_id DESC"; // Assuming detail_id exists for consistent item order
    
    $stmt = $conn->prepare($query);
    $pending_status = 'pendiente';
    $stmt->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt->bindParam(':status', $pending_status, PDO::PARAM_STR);
    $stmt->execute();
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group results by order_id
    $grouped_orders = [];
    foreach ($results as $row) {
        $order_id = $row['order_id'];
        if (!isset($grouped_orders[$order_id])) {
            // Initialize order info if first time seeing this order_id
            $grouped_orders[$order_id] = [
                'order_id' => $order_id,
                'order_date' => $row['order_date'],
                'total' => $row['total'],
                'status' => $row['status'],
                'items' => [] // Initialize items array for this order
            ];
        }
        // Add the current item's details to the order
        $grouped_orders[$order_id]['items'][] = [
            'tagid' => $row['tagid'],
            'nombre' => $row['nombre'],
            'raza' => $row['raza'],
            'genero' => $row['genero'],
            'image' => $row['image'] ?: './images/default_animal.png' // Provide default image
        ];
    }
    $pending_orders = $grouped_orders; // Assign the grouped structure

} catch (PDOException $e) {
    $payment_error = "Error fetching orders: " . $e->getMessage();
    error_log("Database error fetching pending orders for user {$user_id}: " . $e->getMessage());
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Pagos Pendientes - Feria Ganadera Aviar</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS (reuse if applicable or create new) -->
    <link rel="stylesheet" href="aviar.css"> 
    <style>
        .payment-container {
            background-color: #f8f9fa;
            padding: 30px;
            border-radius: 8px;
            margin-top: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        .order-card {
            border: 1px solid #dee2e6;
            border-left: 5px solid #0d6efd; /* Blue border for pending */
            margin-bottom: 15px;
            padding: 15px;
            border-radius: 5px;
            background-color: #fff;
        }
    </style>
</head>
<body>

<!-- Navigation Title (reuse from cart.php or similar) -->
<nav class="navbar text-center">
    <!-- Title Row -->
    <div class="container-fluid">
        <div class="row w-100">
            <div class="col-12 d-flex justify-content-center align-items-center">
                <h1 class="navbar-title text-center mx-auto">
                    <i class="fas fa-credit-card me-2"></i>
                    PAGOS
                    <i class="fas fa-dollar-sign ms-2"></i>
                </h1>
                <!-- Optional: Add user info/logout from check_session.php variables -->
                <div class="position-absolute end-0 me-3 d-flex align-items-center">
                    <span class="badge bg-primary me-2" style="font-size: 0.9rem; text-transform: capitalize;">
                        <?php echo htmlspecialchars($_SESSION["role"] ?? 'usuario'); ?>
                    </span>
                    <a href="logout.php" class="btn btn-outline-danger btn-sm" title="Cerrar sesión">
                        <i class="fas fa-sign-out-alt"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </div>
</nav>

<!-- Optional: Icon Navigation Buttons (reuse if applicable) -->
<!-- ... your standard navigation icons ... -->
<!-- Icon Navigation Buttons -->

<div class="container nav-icons-container">
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
<!-- Optional: E-commerce Navigation Row (reuse if applicable) -->
<div class="container mt-4" id="ecommerce-navbar">    
    <div class="row">
        <div class="col-12">
             <div class="nav-links-container d-flex justify-content-center">
                <!-- Links to Feria, Cart, Orders, Payments -->
                <div class="nav-link-item text-center mx-3">
                    <img src="./images/rebaño.png" alt="Publicados" width="40" class="nav-img">
                    <div><a class="nav-link" href="aviar_feria.php">PUBLICADOS</a></div>
                </div>
                <div class="nav-link-item text-center mx-3">
                     <img src="./images/carrito-animal.png" alt="Seleccionados" width="40" class="nav-img">
                     <div><a class="nav-link" href="cart.php">SELECCIONADOS</a></div>
                </div>
                 <div class="nav-link-item text-center mx-3">
                    <img src="./images/pedidos.png" alt="Ventas" width="40" class="nav-img">
                    <div><a class="nav-link" href="orders.php">VENTAS</a></div>
                </div>
                <div class="nav-link-item text-center mx-3">
                    <img src="./images/pagos.png" alt="Pagos" width="40" class="nav-img">
                    <div><a class="nav-link active" href="payments.php">PAGOS</a></div>
                </div>
             </div>
        </div>
    </div>
</div>


<!-- Main Content -->
<div class="container mt-4">
    <div class="payment-container">
        <h2><i class="fas fa-file-invoice-dollar me-2"></i> Pagos Pendientes</h2>
        <hr>

        <?php if ($payment_message): ?>
            <div class="alert alert-success" role="alert">
                <?php echo htmlspecialchars($payment_message); ?>
            </div>
        <?php endif; ?>
        <?php if ($payment_error): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo htmlspecialchars($payment_error); ?>
            </div>
        <?php endif; ?>

        <?php if (empty($pending_orders)): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-check-circle fa-3x mb-3"></i>
                <h4>¡Todo al día!</h4>
                <p>No tienes órdenes pendientes de pago.</p>
                <a href="orders.php" class="btn btn-primary mt-2">Ver Historial de Órdenes</a>
            </div>
        <?php else: ?>
            <p>Tienes las siguientes órdenes pendientes de pago:</p>
            <?php foreach ($pending_orders as $order_id => $order): // Loop through grouped orders ?>
                <div class="order-card">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <h5>Orden #<?php echo htmlspecialchars($order['order_id']); ?></h5>
                                <span class="badge bg-warning text-dark"><?php echo htmlspecialchars(ucfirst(str_replace('_', ' ', $order['status']))); ?></span>
                            </div>
                            <p class="mb-1"><small>Fecha: <?php echo date("d/m/Y H:i", strtotime($order['order_date'])); ?></small></p>
                            <p class="mb-2">Total: <strong>$<?php echo number_format($order['total'], 2); ?></strong></p>
                        </div>
                    </div>
                    
                    <!-- Item Details -->
                    <div class="order-items-details mt-3 pt-3 border-top">
                        <h6>Artículos en esta orden:</h6>
                        <?php if (!empty($order['items'])): ?>
                            <?php foreach ($order['items'] as $item): ?>
                            <div class="d-flex align-items-center mb-2 pb-2 border-bottom">
                                <img src="<?php echo htmlspecialchars($item['image']); ?>" alt="<?php echo htmlspecialchars($item['nombre']); ?>" style="width: 60px; height: 60px; object-fit: contain; margin-right: 15px; border-radius: 4px; background-color: #eee;">
                                <div style="line-height: 1.3;">
                                    <strong><?php echo htmlspecialchars($item['nombre']); ?></strong> (ID: <?php echo htmlspecialchars($item['tagid']); ?>)<br>
                                    <small class="text-muted">
                                        <?php echo htmlspecialchars($item['raza']); ?> / <?php echo htmlspecialchars($item['genero']); ?>
                                    </small>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <p class="text-muted"><small>No se encontraron detalles de artículos para esta orden.</small></p>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Payment Button -->
                    <div class="text-end mt-3">
                        <form method="post" action="payments.php" class="d-inline-block">
                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id']); ?>">
                            <input type="hidden" name="amount" value="<?php echo htmlspecialchars($order['total']); ?>">
                            <button type="submit" name="pay_order" class="btn btn-success btn-sm">
                                <i class="fas fa-credit-card me-1"></i> Pagar Orden #<?php echo htmlspecialchars($order['order_id']); ?> (Simulado)
                            </button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>

    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
<!-- jQuery (optional, if you need it for other things) -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</body>
</html>
