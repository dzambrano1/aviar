<?php
// Set session parameters
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 2592000); // 30 days
    session_set_cookie_params(2592000);
    session_start();
}

// Include database connection
require_once './pdo_conexion.php';

// Check if already logged in
if (isset($_SESSION['user_id'])) {
    header("Location: inventario_aviar.php");
    exit();
}

// Initialize variables
$error_message = '';
$success_message = '';
$username = '';
$email = '';
$firstName = '';
$lastName = '';
$mobile = '';

// Process registration form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get and sanitize form data
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $firstName = trim($_POST['firstName'] ?? '');
    $lastName = trim($_POST['lastName'] ?? '');
    $mobile = trim($_POST['mobile'] ?? '');
    
    // Validate input
    $errors = [];
    
    if (empty($username)) {
        $errors[] = "El nombre de usuario es obligatorio";
    } elseif (strlen($username) < 3) {
        $errors[] = "El nombre de usuario debe tener al menos 3 caracteres";
    }
    
    if (empty($email)) {
        $errors[] = "El correo electrónico es obligatorio";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Por favor, introduce un correo electrónico válido";
    }
    
    if (empty($password)) {
        $errors[] = "La contraseña es obligatoria";
    } elseif (strlen($password) < 6) {
        $errors[] = "La contraseña debe tener al menos 6 caracteres";
    }
    
    if ($password !== $confirm_password) {
        $errors[] = "Las contraseñas no coinciden";
    }
    
    if (empty($firstName)) {
        $errors[] = "El nombre es obligatorio";
    }
    
    if (empty($lastName)) {
        $errors[] = "El apellido es obligatorio";
    }
    
    if (empty($mobile)) {
        $errors[] = "El número de teléfono móvil es obligatorio";
    }
    
    // Check if username or email already exists
    if (empty($errors)) {
        try {
            // Check username
            $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Este nombre de usuario ya está en uso";
            }
            
            // Check email
            $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$email]);
            if ($stmt->rowCount() > 0) {
                $errors[] = "Este correo electrónico ya está registrado";
            }
        } catch (PDOException $e) {
            $errors[] = "Error de conexión: " . $e->getMessage();
        }
    }
    
    // If no errors, register the user
    if (empty($errors)) {
        try {
            // Hash password
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            
            // Default role is 'customer' - admin will need to upgrade if needed
            $role = 'customer';
            
            
            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (username, password, email, firstName, lastName, role, mobile) VALUES (?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([$username, $hashed_password, $email, $firstName, $lastName, $role, $mobile]);
            
            $success_message = "¡Tu cuenta ha sido creada exitosamente! Ahora puedes iniciar sesión.";
            
            // Clear form data on success
            $username = '';
            $email = '';
            $firstName = '';
            $lastName = '';
            $mobile = '';
        } catch (PDOException $e) {
            $error_message = "Error al crear la cuenta: " . $e->getMessage();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Registro - Sistema Ganadero Aviar</title>
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
        .register-container {
            background-color: white;
            border-radius: 10px;
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
            overflow: hidden;
            width: 100%;
            max-width: 500px;
            animation: fadeIn 0.6s ease;
        }
        .register-header {
            background: linear-gradient(135deg, #4e6c41, #759c63);
            color: white;
            padding: 20px;
            text-align: center;
        }
        .register-header img {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            border: 3px solid white;
            margin-bottom: 10px;
            object-fit: cover;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .register-body {
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
        .btn-register {
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
        .btn-register:hover {
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
        .success-message {
            background-color: #e8f5e9;
            color: #2e7d32;
            padding: 12px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 0.9rem;
            text-align: center;
            border-left: 4px solid #2e7d32;
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
        .field-animated:nth-child(5) { animation-delay: 0.6s; }
        .field-animated:nth-child(6) { animation-delay: 0.7s; }
        
        @keyframes slideIn {
            to { opacity: 1; transform: translateY(0); }
        }
        
        .password-input-group {
            position: relative;
        }
        
        .password-toggle {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            cursor: pointer;
            color: #6c757d;
        }
        
        .password-toggle:hover {
            color: #495057;
        }
        
        .password-requirements {
            font-size: 0.8rem;
            color: #6c757d;
            margin-top: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="register-container">
                    <div class="register-header">
                        <img src="./images/default_image.png" alt="Logo">
                        <h4>GANAGRAM</h4>
                        <div class="brand-name">Registrar Usuario</div>
                    </div>
                    <div class="register-body">
                        <?php if (!empty($error_message)): ?>
                            <div class="error-message">
                                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($success_message)): ?>
                            <div class="success-message">
                                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                                <div class="mt-3">
                                    <a href="login.php" class="btn btn-success">
                                        <i class="fas fa-sign-in-alt me-1"></i>Iniciar Sesión Ahora
                                    </a>
                                </div>
                            </div>
                        <?php else: ?>
                            <form method="post" action="">
                                <div class="form-group field-animated">
                                    <i class="fas fa-user input-icon"></i>
                                    <input type="text" class="form-control" name="username" placeholder="Nombre de usuario" value="<?php echo htmlspecialchars($username); ?>" required>
                                </div>
                                
                                <div class="form-group field-animated">
                                    <i class="fas fa-envelope input-icon"></i>
                                    <input type="email" class="form-control" name="email" placeholder="Correo electrónico" value="<?php echo htmlspecialchars($email); ?>" required>
                                </div>
                                
                                <div class="form-group field-animated">
                                    <i class="fas fa-user-alt input-icon"></i>
                                    <input type="text" class="form-control" name="firstName" placeholder="Nombre" value="<?php echo htmlspecialchars($firstName); ?>" required>
                                </div>
                                
                                <div class="form-group field-animated">
                                    <i class="fas fa-user-tag input-icon"></i>
                                    <input type="text" class="form-control" name="lastName" placeholder="Apellido" value="<?php echo htmlspecialchars($lastName); ?>" required>
                                </div>
                                
                                <div class="form-group field-animated">
                                    <i class="fas fa-mobile-alt input-icon"></i>
                                    <input type="text" class="form-control" name="mobile" placeholder="Ejemplo: 0414 333-2662" value="<?php echo htmlspecialchars($mobile); ?>" required>
                                </div>
                                
                                <div class="form-group field-animated">
                                    <i class="fas fa-lock input-icon"></i>
                                    <div class="password-input-group">
                                        <input type="password" class="form-control" name="password" id="password" placeholder="Contraseña" required>
                                        <i class="fas fa-eye password-toggle" id="togglePassword"></i>
                                    </div>
                                    <div class="password-requirements">
                                        Password must be at least 8 characters long and include uppercase, lowercase, numbers, and special characters.
                                    </div>
                                </div>
                                
                                <div class="form-group field-animated">
                                    <i class="fas fa-check-circle input-icon"></i>
                                    <div class="password-input-group">
                                        <input type="password" class="form-control" name="confirm_password" id="confirm_password" placeholder="Confirmar contraseña" required>
                                        <i class="fas fa-eye password-toggle" id="toggleConfirmPassword"></i>
                                    </div>
                                </div>
                                
                                <div class="field-animated">
                                    <button type="submit" class="btn btn-register">
                                        <i class="fas fa-user-plus me-2"></i>Registrarme
                                    </button>
                                </div>
                                
                                <div class="text-center mt-4">
                                    <p>¿Ya tienes una cuenta?</p>
                                    <a href="login.php" class="btn btn-outline-success w-100">
                                        <i class="fas fa-sign-in-alt me-2"></i>Iniciar Sesión
                                    </a>
                                </div>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="footer-text">
                    &copy; <?php echo date('Y'); ?> Sistema Ganagram - Todos los derechos reservados
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        $(document).ready(function() {
            $('.password-toggle').click(function() {
                var input = $(this).siblings('input');
                if (input.attr('type') === 'password') {
                    input.attr('type', 'text');
                    $(this).removeClass('fa-eye').addClass('fa-eye-slash');
                } else {
                    input.attr('type', 'password');
                    $(this).removeClass('fa-eye-slash').addClass('fa-eye');
                }
            });
            
            // Focus on username field when page loads
            const usernameField = document.querySelector('input[name="username"]');
            if (usernameField) {
                usernameField.focus();
            }
        });
    </script>
</body>
</html> 