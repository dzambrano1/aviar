<?php
// Only set session parameters and start session if not already started
if (session_status() === PHP_SESSION_NONE) {
    // Set extended session lifetime - 30 days in seconds
    ini_set('session.gc_maxlifetime', 2592000); // server side
    session_set_cookie_params(2592000); // client side

    // Start or resume session
    session_start();
}

// Set default session variables
$isUserLoggedIn = false;
$user_id = null;
$username = null;
$firstName = "Invitado";
$user_role = "usuario";

// Check if user is logged in
if (isset($_SESSION['user_id']) && !empty($_SESSION['user_id'])) {
    $isUserLoggedIn = true;
    $user_id = $_SESSION['user_id'];
    $username = $_SESSION['username'] ?? '';
    $firstName = $_SESSION['firstName'] ?? 'usuario';
    $user_role = $_SESSION['role'] ?? 'usuario';
}

/**
 * Function to require login for protected pages
 * If user is not logged in, redirects to login page
 * 
 * @param string $redirect Optional redirect URL after login
 * @return void
 */
function requireLogin($redirect = '') {
    global $isUserLoggedIn;
    
    if (!$isUserLoggedIn) {
        $redirect_param = !empty($redirect) ? "?redirect=" . urlencode($redirect) : "";
        header("Location: login.php" . $redirect_param);
        exit();
    }
}

/**
 * Function to require admin role
 * If user is not an admin, redirects to permission denied page or inventory
 * 
 * @return void
 */
function requireAdmin() {
    global $isUserLoggedIn, $user_role;
    
    if (!$isUserLoggedIn) {
        header("Location: login.php");
        exit();
    }
    
    if ($user_role !== 'admin') {
        // Redirect to permission denied or home page
        header("Location: login.php?error=permission_denied");
        exit();
    }
}

/**
 * Function to require comprador role or higher
 * If user is not a comprador or admin, redirects to permission denied page
 * 
 * @return void
 */
function requireBuyer() {
    global $isUserLoggedIn, $user_role;
    
    if (!$isUserLoggedIn) {
        header("Location: login.php");
        exit();
    }
    
    if ($user_role !== 'comprador' && $user_role !== 'admin') {
        // Redirect to permission denied or home page
        header("Location: login.php?error=permission_denied");
        exit();
    }
}

/**
 * Function to require customer role or higher
 * If user doesn't have sufficient permissions, redirects to permission denied page
 * 
 * @return void
 */
function requireCustomer() {
    global $isUserLoggedIn, $user_role;
    
    if (!$isUserLoggedIn) {
        header("Location: login.php");
        exit();
    }
    
    if ($user_role !== 'usuario' && $user_role !== 'comprador' && $user_role !== 'admin') {
        // Redirect to permission denied or home page
        header("Location: login.php?error=permission_denied");
        exit();
    }
}

/**
 * Check if the current user has admin privileges
 * 
 * @return bool True if user is admin, false otherwise
 */
function isAdmin() {
    global $user_role;
    return ($user_role === 'admin');
}

/**
 * Check if the current user has comprador privileges or higher
 * 
 * @return bool True if user is comprador or admin, false otherwise
 */
function isBuyer() {
    global $user_role;
    // User has buyer privileges if they are a comprador or an admin
    return ($user_role === 'comprador' || $user_role === 'admin');
}

/**
 * Check if the current user has customer privileges or higher
 * 
 * @return bool True if user is customer, comprador or admin, false otherwise
 */
function isCustomer() {
    global $user_role;
    // User has customer privileges if they are a usuario, comprador, or admin
    return ($user_role === 'usuario' || $user_role === 'comprador' || $user_role === 'admin');
} 