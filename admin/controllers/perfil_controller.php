<?php
/**
 * Controlador de Perfil
 * Gestiona el cambio de contraseña del usuario actual
 */

require_once '../config/db.php';
require_once '../config/session.php';

header('Content-Type: application/json; charset=utf-8');

requerirSesion();

$action = $_POST['action'] ?? '';

if ($action === 'cambiar_clave_propia') {
    cambiarClavePropia();
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Cambiar la propia contraseña
 */
function cambiarClavePropia() {
    try {
        $clave_actual = $_POST['clave_actual'] ?? '';
        $nueva_clave = $_POST['nueva_clave'] ?? '';
        
        if (empty($clave_actual) || empty($nueva_clave)) {
            echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
            return;
        }
        
        if (strlen($nueva_clave) < 6) {
            echo json_encode(['success' => false, 'message' => 'La nueva contraseña debe tener al menos 6 caracteres']);
            return;
        }
        
        // Obtener usuario actual
        $usuario = DB::queryFirstRow(
            "SELECT * FROM usuarios WHERE id_usuario = ?",
            $_SESSION['usuario_id']
        );
        
        if (!$usuario) {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
            return;
        }
        
        // Verificar contraseña actual
        if (!password_verify($clave_actual, $usuario['clave'])) {
            echo json_encode(['success' => false, 'message' => 'La contraseña actual es incorrecta']);
            return;
        }
        
        // Actualizar contraseña
        DB::update('usuarios', [
            'clave' => password_hash($nueva_clave, PASSWORD_DEFAULT)
        ], 'id_usuario = ?', $_SESSION['usuario_id']);
        
        echo json_encode([
            'success' => true,
            'message' => 'Contraseña cambiada exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al cambiar contraseña: ' . $e->getMessage()]);
    }
}
?>
