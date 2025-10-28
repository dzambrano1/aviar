<?php
// Set session to last 30 days
ini_set('session.gc_maxlifetime', 2592000); // server side
session_set_cookie_params(2592000); // client side

// Start session with extended lifetime
session_start();

// Include database connection
require_once './pdo_conexion.php';

// Initialize error message variable
$error_message = '';

// Check if user is already logged in
if (isset($_SESSION['user_id'])) {
    // Redirect to inventory page
    header("Location: inventario_aviar.php");
    exit();
}

// Process login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get form data
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) ? true : false;
    
    // Validate input
    if (empty($username) || empty($password)) {
        $error_message = "Por favor ingrese usuario y contraseña.";
    } else {
        try {
            // Check credentials using prepared statement
            $stmt = $conn->prepare("SELECT id, username, password, role FROM users WHERE username = ?");
            $stmt->execute([$username]);
            
            $user = null;
            if ($row = $stmt->fetch()) {
                $user = $row;
            }
            
            if ($user) {
                
                // Verify password (using password_hash)
                if (password_verify($password, $user['password'])) {
                    // Password is correct, set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['role'] = $user['role'];
                    
                    // If remember me is checked, set a longer cookie expiration
                    if ($remember) {
                        // 30 days in seconds
                        $lifetime = 30 * 24 * 60 * 60;
                        setcookie(session_name(), session_id(), time() + $lifetime, '/');
                    }
                    
                    // Update last login time
                    $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                    $update->execute([$user['id']]);
                    
                    // Redirect to inventory page
                    header("Location: inventario_aviar.php");
                    exit();
                } else {
                    // If password is stored in plain text (for compatibility with older systems)
                    if ($password === $user['password']) {
                        // Password is correct, set session variables
                        $_SESSION['user_id'] = $user['id'];
                        $_SESSION['username'] = $user['username'];
                        $_SESSION['role'] = $user['role'];
                        
                        // If remember me is checked, set a longer cookie expiration
                        if ($remember) {
                            // 30 days in seconds
                            $lifetime = 30 * 24 * 60 * 60;
                            setcookie(session_name(), session_id(), time() + $lifetime, '/');
                        }
                        
                        // Update last login time
                        $update = $conn->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
                        $update->execute([$user['id']]);
                        
                        // Redirect to inventory page
                        header("Location: inventario_aviar.php");
                        exit();
                    } else {
                        $error_message = "Contraseña incorrecta.";
                    }
                }
            } else {
                $error_message = "Usuario no encontrado.";
            }
        } catch (PDOException $e) {
            $error_message = "Error de conexión: " . $e->getMessage();
        }
    }
}

// Get redirect parameter if exists
$redirect = isset($_GET['redirect']) ? $_GET['redirect'] : '';
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inicio de Sesión - Sistema Ganadero Aviar</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #4e6c41, #759c63);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .login-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 400px;
            animation: fadeIn 0.6s ease;
        }
        .login-header {
            background: linear-gradient(135deg, #4e6c41, #759c63);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .login-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid white;
            margin-bottom: 10px;
            object-fit: cover;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .login-body {
            padding: 30px;
        }
        .form-group {
            margin-bottom: 20px;
            position: relative;
        }
        .form-control {
            padding: 12px 15px 12px 45px;
            height: auto;
            border-radius: 30px;
            border: 1px solid #ddd;
            transition: all 0.3s;
        }
        .form-control:focus {
            border-color: #4e6c41;
            box-shadow: 0 0 0 0.2rem rgba(78, 108, 65, 0.25);
        }
        .input-icon {
            position: absolute;
            left: 15px;
            top: 13px;
            color: #4e6c41;
        }
        .btn-login {
            background: linear-gradient(135deg, #4e6c41, #759c63);
            border: none;
            border-radius: 30px;
            color: white;
            font-weight: 600;
            padding: 12px;
            width: 100%;
            margin-top: 15px;
            transition: all 0.3s;
        }
        .btn-login:hover {
            background: linear-gradient(135deg, #759c63, #4e6c41);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(78, 108, 65, 0.3);
        }
        .error-message {
            background-color: #ffebee;
            color: #c62828;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
            border-left: 4px solid #c62828;
        }
        .brand-name {
            font-weight: 700;
            margin-top: 5px;
        }
        .footer-text {
            text-align: center;
            color: rgba(255,255,255,0.7);
            margin-top: 20px;
            font-size: 0.9rem;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-20px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .field-animated {
            animation: slideIn 0.5s ease forwards;
            opacity: 0;
            transform: translateY(20px);
        }
        .field-animated:nth-child(1) { animation-delay: 0.2s; }
        .field-animated:nth-child(2) { animation-delay: 0.3s; }
        .field-animated:nth-child(3) { animation-delay: 0.4s; }
        .field-animated:nth-child(4) { animation-delay: 0.5s; }
        
        .form-check {
            padding-left: 1.8rem;
        }
        
        .form-check-input {
            width: 1.2em;
            height: 1.2em;
            margin-top: 0.15em;
            margin-left: -1.8rem;
            cursor: pointer;
        }
        
        .form-check-input:checked {
            background-color: #4e6c41;
            border-color: #4e6c41;
        }
        
        .form-check-label {
            cursor: pointer;
            font-size: 0.9rem;
            color: #555;
        }
        
        @keyframes slideIn {
            to { opacity: 1; transform: translateY(0); }
        }
        
        .role-badge {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            color: white;
            margin: 5px 5px 5px 0;
        }
        
        .role-admin {
            background-color: #4e6c41;
        }
        
        .role-buyer {
            background-color: #759c63;
        }
        
        .role-customer {
            background-color: #91c788;
        }
        
        .role-info {
            background-color: rgba(255, 255, 255, 0.1);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.75rem;
            padding: 7px 12px;
            border-radius: 20px;
            margin-top: 15px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6 col-lg-4">
                <div class="login-container">
                    <div class="login-header">
                        <img src="./images/default_image.png" alt="Logo">
                        <h4>Sistema Ganadero</h4>
                        <div class="brand-name">Control de Rebaño</div>
                        
                        <div class="role-info">
                            <span class="role-badge role-admin">Administrador</span>
                            <span class="role-badge role-buyer">Comprador</span>
                            <span class="role-badge role-customer">Usuario</span>
                        </div>
                    </div>
                    <div class="login-body">
                        <?php if (!empty($error_message)): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo htmlspecialchars($error_message); ?>
                            </div>
                        <?php endif; ?>
                        
                        <form method="post" action="">
                            <?php if (!empty($redirect)): ?>
                                <input type="hidden" name="redirect" value="<?php echo htmlspecialchars($redirect); ?>">
                            <?php endif; ?>
                            
                            <div class="form-group field-animated">
                                <i class="fas fa-user input-icon"></i>
                                <input type="text" class="form-control" name="username" placeholder="Usuario" required autofocus>
                            </div>
                            
                            <div class="form-group field-animated">
                                <i class="fas fa-lock input-icon"></i>
                                <input type="password" class="form-control" name="password" placeholder="Contraseña" required>
                            </div>
                            
                            <div class="form-check field-animated mb-4">
                                <input class="form-check-input" type="checkbox" id="rememberMe" name="remember" checked>
                                <label class="form-check-label" for="rememberMe">
                                    Mantener sesión iniciada
                                </label>
                            </div>
                            
                            <div class="field-animated">
                                <button type="submit" class="btn btn-login">
                                    <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                                </button>
                            </div>
                            
                            <div class="text-center mt-4">
                                <p class="mb-0">¿No tienes una cuenta?</p>
                                <a href="register.php" class="btn btn-outline-success mt-2 w-100">
                                    <i class="fas fa-user-plus me-2"></i>Crear Cuenta
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
                <div class="footer-text">
                    &copy; <?php echo date('Y'); ?> Sistema Ganadero - Todos los derechos reservados
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Focus on username field when page loads
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelector('input[name="username"]').focus();
        });
    </script>
</body>
</html> 