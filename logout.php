<?php
// Start the session
session_start();

// If the user is logged in, log the logout time
if (isset($_SESSION['user_id'])) {
    // Include database connection
    require_once './pdo_conexion.php';
    
    try {
        // Update the logout time in the users table
        $update = $conn->prepare("UPDATE users SET last_logout = NOW() WHERE id = ?");
        $update->bindParam(1, $_SESSION['user_id']);
        $update->execute();
    } catch (PDOException $e) {
        // Silently fail - logout should still proceed even if DB update fails
    }
}

// Unset all session variables
$_SESSION = array();

// If a session cookie is used, destroy it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy the session
session_destroy();

// Redirect to the login page
header("Location: login.php");
exit();