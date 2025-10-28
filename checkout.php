<?php
// Start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable error reporting for debugging (consider disabling in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Include session check functions (ensure this path is correct)
require_once 'check_session.php';

// Require user to be logged in
requireLogin(); // Redirects if not logged in

// Get user ID from session (ensure this is set correctly during login)
if (!isset($_SESSION['user_id'])) {
    // This should theoretically not happen due to requireLogin(), but as a safeguard:
    error_log("Checkout attempt failed: User ID not found in session.");
    header("Location: login.php?error=session_expired");
    exit;
}
$user_id = $_SESSION['user_id'];

// Include database connection (MUST provide a PDO connection as $conn)
require_once "./pdo_conexion.php"; 

// Ensure we have a valid PDO connection
if (!isset($conn) || !($conn instanceof PDO)) {
    error_log("Checkout failed: PDO connection not available in conexion.php");
    // Display a user-friendly error message
    // In a real application, you might redirect to an error page
    die('Could not connect to the database to complete your order. Please try again later.');
}

// Define log file
$log_file = 'checkout_debug.log';
file_put_contents($log_file, date('Y-m-d H:i:s') . " - Checkout process started for user_id: {$user_id}\n", FILE_APPEND);

// Initialize status variables
$success = false;
$error_message = '';
$order_id = null;

try {
    // Begin database transaction
    $conn->beginTransaction();
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Transaction started for user_id: {$user_id}\n", FILE_APPEND);

    // --- Step 1: Check if cart has items --- 
    $check_cart_query = "SELECT COUNT(*) FROM cart_items WHERE user_id = :user_id";
    $stmt_check = $conn->prepare($check_cart_query);
    $stmt_check->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_check->execute();
    $cart_count = $stmt_check->fetchColumn();

    if ($cart_count == 0) {
        throw new Exception("Cannot checkout, your cart is empty.");
    }
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Cart check passed for user_id: {$user_id} ({$cart_count} items)\n", FILE_APPEND);

    // --- Step 2: Calculate order total from cart items --- 
    // It's generally safer to recalculate the total here rather than trusting a value passed from the previous page
    $total_query = "SELECT SUM(quantity * precio) FROM cart_items WHERE user_id = :user_id";
    $stmt_total = $conn->prepare($total_query);
    $stmt_total->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_total->execute();
    $total = $stmt_total->fetchColumn();

    if ($total === false || $total <= 0) {
        // fetchColumn returns false on failure
        throw new Exception("Could not calculate order total or total is invalid.");
    }
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Order total calculated for user_id: {$user_id}, Total: {$total}\n", FILE_APPEND);

    // --- Step 3: Create new order in 'orders' table --- 
    $insert_order_query = "INSERT INTO orders (user_id, order_date, total, status) VALUES (:user_id, NOW(), :total, 'pendiente')";
    $stmt_order = $conn->prepare($insert_order_query);
    $stmt_order->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    $stmt_order->bindParam(':total', $total, PDO::PARAM_STR); // PDO treats decimals as strings
    if (!$stmt_order->execute()) {
        throw new Exception("Failed to create order record.");
    }
    $order_id = $conn->lastInsertId();
    if (!$order_id) {
        throw new Exception("Failed to retrieve new order ID.");
    }
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Order created for user_id: {$user_id}, Order ID: {$order_id}\n", FILE_APPEND);

    // --- Step 4: Copy cart items to 'order_details' table --- 
    // Ensure the columns match your order_details table and cart_items table structure
    $copy_items_query = "INSERT INTO order_details 
                        (order_id, tagid, quantity, nombre, genero, raza, precio, image)
                        SELECT :order_id, tagid, quantity, nombre, genero, raza, precio, image
                        FROM cart_items WHERE user_id = :user_id";
    $stmt_copy = $conn->prepare($copy_items_query);
    $stmt_copy->bindParam(':order_id', $order_id, PDO::PARAM_INT);
    $stmt_copy->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    if (!$stmt_copy->execute()) {
         throw new Exception("Failed to copy cart items to order details.");
    }
    $affected_rows = $stmt_copy->rowCount();
    if ($affected_rows <= 0) {
        // Should not happen if cart check passed, but good safeguard
        throw new Exception("No items were copied to order details, though cart was not empty.");
    }
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Copied {$affected_rows} items to order_details for Order ID: {$order_id}\n", FILE_APPEND);

    // --- Step 5: Clear the user's cart --- 
    $clear_cart_query = "DELETE FROM cart_items WHERE user_id = :user_id";
    $stmt_clear = $conn->prepare($clear_cart_query);
    $stmt_clear->bindParam(':user_id', $user_id, PDO::PARAM_INT);
    if (!$stmt_clear->execute()) {
        // This is serious, as the order is created but cart isn't cleared
        throw new Exception("Failed to clear cart after order creation."); 
    }
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Cart cleared for user_id: {$user_id}\n", FILE_APPEND);

    // --- If all steps succeeded, commit the transaction --- 
    $conn->commit();
    $success = true;
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Transaction committed successfully for Order ID: {$order_id}\n", FILE_APPEND);

} catch (PDOException $e) {
    // --- Handle PDO (Database) errors --- 
    $conn->rollBack(); // Roll back changes on error
    $success = false;
    $error_message = "Database error during checkout: " . $e->getMessage();
    error_log($error_message); // Log detailed error
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - PDOException for user_id: {$user_id}. Transaction rolled back. Error: " . $e->getMessage() . "\n", FILE_APPEND);
    // Provide a more generic message to the user
    $user_facing_error = "A database error occurred while processing your order. Please try again later.";

} catch (Exception $e) {
    // --- Handle other general errors --- 
    // Check if transaction was started before rolling back
    if ($conn->inTransaction()) {
        $conn->rollBack();
    }
    $success = false;
    $error_message = "Error during checkout: " . $e->getMessage();
    error_log($error_message);
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Exception for user_id: {$user_id}. Transaction rolled back. Error: " . $e->getMessage() . "\n", FILE_APPEND);
    $user_facing_error = $e->getMessage(); // Use the specific error message here (e.g., 'Cart is empty')
}

// --- Redirect based on success or failure --- 
finally {
    // Log final outcome
    file_put_contents($log_file, date('Y-m-d H:i:s') . " - Checkout process finished for user_id: {$user_id}. Success: " . ($success ? 'Yes' : 'No') . "\n", FILE_APPEND);

    if ($success && $order_id) {
        // Redirect to an order confirmation or orders page on success
        header("Location: orders.php?success=1&order_id=" . $order_id);
        exit;
    } else {
        // Redirect back to the cart page with an error message on failure
        // Use the specific error if available, otherwise a generic one
        $error_param = urlencode($user_facing_error ?? "An unknown error occurred during checkout.");
        header("Location: cart.php?error=" . $error_param);
        exit;
    }
}
