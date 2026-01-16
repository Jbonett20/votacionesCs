<?php
/**
 * Controlador de Login
 * Maneja la autenticación de usuarios
 */

session_start();
require_once '../config/db.php';

header('Content-Type: application/json; charset=utf-8');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit();
}

$action = $_POST['action'] ?? '';

if ($action === 'login') {
    login();
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Función de login
 */
function login() {
    $usuario = trim($_POST['usuario'] ?? '');
    $clave = $_POST['clave'] ?? '';
    $recordarme = isset($_POST['recordarme']) && $_POST['recordarme'] === 'true';
    
    // Validar campos vacíos
    if (empty($usuario) || empty($clave)) {
        echo json_encode([
            'success' => false,
            'message' => 'Por favor, completa todos los campos'
        ]);
        return;
    }
    
    try {
        // Buscar usuario en la base de datos
        $user = DB::queryFirstRow(
            "SELECT u.*, r.nombre_rol, e.nombre_estado 
             FROM usuarios u
             INNER JOIN roles r ON u.id_rol = r.id_rol
             INNER JOIN estados e ON u.id_estado = e.id_estado
             WHERE u.usuario = ?",
            $usuario
        );
        
        // Verificar si el usuario existe
        if (!$user) {
            echo json_encode([
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos'
            ]);
            return;
        }
        
        // Verificar si el usuario está activo
        if ($user['id_estado'] != 1) {
            echo json_encode([
                'success' => false,
                'message' => 'Tu cuenta está inactiva. Contacta al administrador.'
            ]);
            return;
        }
        
        // Verificar la contraseña
        if (!password_verify($clave, $user['clave'])) {
            echo json_encode([
                'success' => false,
                'message' => 'Usuario o contraseña incorrectos'
            ]);
            return;
        }
        
        // Verificar que no sea rol Votante (los votantes no tienen acceso al sistema)
        if ($user['id_rol'] == 4) {
            echo json_encode([
                'success' => false,
                'message' => 'No tienes permisos para acceder al sistema'
            ]);
            return;
        }
        
        // Crear sesión
        $_SESSION['usuario_id'] = $user['id_usuario'];
        $_SESSION['usuario_nombre'] = $user['nombres'] . ' ' . $user['apellidos'];
        $_SESSION['usuario_rol'] = $user['id_rol'];
        $_SESSION['usuario_rol_nombre'] = $user['nombre_rol'];
        $_SESSION['usuario_username'] = $user['usuario'];
        $_SESSION['usuario_activo'] = true;
        
        // Configurar cookie si se marca "Recordarme"
        if ($recordarme) {
            $token = bin2hex(random_bytes(32));
            setcookie('remember_token', $token, time() + (86400 * 30), '/'); // 30 días
            // Aquí podrías guardar el token en la BD para mayor seguridad
        }
        
        echo json_encode([
            'success' => true,
            'message' => '¡Bienvenido, ' . $user['nombres'] . '!',
            'user' => [
                'id' => $user['id_usuario'],
                'nombre' => $user['nombres'] . ' ' . $user['apellidos'],
                'rol' => $user['nombre_rol']
            ]
        ]);
        
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al procesar la solicitud: ' . $e->getMessage()
        ]);
    }
}
?>
