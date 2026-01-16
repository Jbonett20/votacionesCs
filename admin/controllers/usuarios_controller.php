<?php
/**
 * Controlador de Usuarios (Administradores)
 * Solo accesible por SuperAdmin
 */

require_once '../config/db.php';
require_once '../config/session.php';

header('Content-Type: application/json; charset=utf-8');

// Solo SuperAdmin puede gestionar usuarios
requerirRol([1]);

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'listar':
        listarUsuarios();
        break;
    case 'crear':
        crearUsuario();
        break;
    case 'editar':
        editarUsuario();
        break;
    case 'cambiar_clave':
        cambiarClave();
        break;
    case 'cambiar_estado':
        cambiarEstado();
        break;
    case 'obtener':
        obtenerUsuario();
        break;
    case 'obtener_tipos_identificacion':
        obtenerTiposIdentificacion();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Listar usuarios administradores
 */
function listarUsuarios() {
    try {
        $usuarios = DB::queryAllRows(
            "SELECT u.*, r.nombre_rol, e.nombre_estado,
                    t.nombre_tipo
             FROM usuarios u
             INNER JOIN roles r ON u.id_rol = r.id_rol
             INNER JOIN estados e ON u.id_estado = e.id_estado
             INNER JOIN tipos_identificacion t ON u.id_tipo_identificacion = t.id_tipo_identificacion
             WHERE u.id_rol IN (1, 2)
             ORDER BY u.id_usuario DESC"
        );
        
        echo json_encode(['success' => true, 'data' => $usuarios]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al listar usuarios: ' . $e->getMessage()]);
    }
}

/**
 * Crear usuario administrador
 */
function crearUsuario() {
    try {
        // Validar campos requeridos
        $campos = ['nombres', 'apellidos', 'identificacion', 'id_tipo_identificacion', 'sexo', 'usuario', 'clave', 'id_rol'];
        foreach ($campos as $campo) {
            if (empty($_POST[$campo])) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
                return;
            }
        }
        
        // Validar que el rol sea Admin o SuperAdmin
        if (!in_array($_POST['id_rol'], [1, 2])) {
            echo json_encode(['success' => false, 'message' => 'Rol no válido']);
            return;
        }
        
        // Validar que el usuario no exista
        $usuarioExiste = DB::queryOneValue("SELECT COUNT(*) FROM usuarios WHERE usuario = ?", $_POST['usuario']);
        if ($usuarioExiste > 0) {
            echo json_encode(['success' => false, 'message' => 'El usuario ya existe']);
            return;
        }
        
        // Validar que la identificación no exista
        $identificacionExiste = DB::queryOneValue(
            "SELECT COUNT(*) FROM usuarios WHERE identificacion = ?",
            $_POST['identificacion']
        );
        if ($identificacionExiste > 0) {
            echo json_encode(['success' => false, 'message' => 'La identificación ya está registrada']);
            return;
        }
        
        // Validar longitud de contraseña
        if (strlen($_POST['clave']) < 6) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
            return;
        }
        
        // Insertar usuario
        $datos = [
            'nombres' => trim($_POST['nombres']),
            'apellidos' => trim($_POST['apellidos']),
            'identificacion' => trim($_POST['identificacion']),
            'id_tipo_identificacion' => $_POST['id_tipo_identificacion'],
            'sexo' => $_POST['sexo'],
            'usuario' => trim($_POST['usuario']),
            'clave' => password_hash($_POST['clave'], PASSWORD_DEFAULT),
            'id_rol' => $_POST['id_rol'],
            'id_estado' => 1 // Activo
        ];
        
        $id = DB::insert('usuarios', $datos);
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario creado exitosamente',
            'id' => $id
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al crear usuario: ' . $e->getMessage()]);
    }
}

/**
 * Editar usuario
 */
function editarUsuario() {
    try {
        $id = $_POST['usuario_id'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de usuario no válido']);
            return;
        }
        
        // Validar campos requeridos
        $campos = ['nombres', 'apellidos', 'identificacion', 'id_tipo_identificacion', 'sexo', 'usuario', 'id_rol'];
        foreach ($campos as $campo) {
            if (empty($_POST[$campo])) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
                return;
            }
        }
        
        // Validar que el usuario no exista (excepto el actual)
        $usuarioExiste = DB::queryOneValue(
            "SELECT COUNT(*) FROM usuarios WHERE usuario = ? AND id_usuario != ?",
            $_POST['usuario'],
            $id
        );
        if ($usuarioExiste > 0) {
            echo json_encode(['success' => false, 'message' => 'El usuario ya existe']);
            return;
        }
        
        // Validar que la identificación no exista (excepto la actual)
        $identificacionExiste = DB::queryOneValue(
            "SELECT COUNT(*) FROM usuarios WHERE identificacion = ? AND id_usuario != ?",
            $_POST['identificacion'],
            $id
        );
        if ($identificacionExiste > 0) {
            echo json_encode(['success' => false, 'message' => 'La identificación ya está registrada']);
            return;
        }
        
        // Actualizar usuario
        $datos = [
            'nombres' => trim($_POST['nombres']),
            'apellidos' => trim($_POST['apellidos']),
            'identificacion' => trim($_POST['identificacion']),
            'id_tipo_identificacion' => $_POST['id_tipo_identificacion'],
            'sexo' => $_POST['sexo'],
            'usuario' => trim($_POST['usuario']),
            'id_rol' => $_POST['id_rol'],
            'id_estado' => $_POST['id_estado'] ?? 1
        ];
        
        DB::update('usuarios', $datos, 'id_usuario = ?', $id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Usuario actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar usuario: ' . $e->getMessage()]);
    }
}

/**
 * Cambiar contraseña de usuario
 */
function cambiarClave() {
    try {
        $id = $_POST['usuario_id'] ?? 0;
        $nueva_clave = $_POST['nueva_clave'] ?? '';
        
        if (empty($id) || empty($nueva_clave)) {
            echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
            return;
        }
        
        if (strlen($nueva_clave) < 6) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
            return;
        }
        
        DB::update('usuarios', [
            'clave' => password_hash($nueva_clave, PASSWORD_DEFAULT)
        ], 'id_usuario = ?', $id);
        
        echo json_encode([
            'success' => true,
            'message' => 'Contraseña cambiada exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al cambiar contraseña: ' . $e->getMessage()]);
    }
}

/**
 * Cambiar estado de usuario
 */
function cambiarEstado() {
    try {
        $id = $_POST['id'] ?? 0;
        $estado = $_POST['estado'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID no válido']);
            return;
        }
        
        // No permitir desactivar el propio usuario
        if ($id == $_SESSION['usuario_id']) {
            echo json_encode(['success' => false, 'message' => 'No puedes desactivar tu propio usuario']);
            return;
        }
        
        DB::update('usuarios', ['id_estado' => $estado], 'id_usuario = ?', $id);
        
        echo json_encode(['success' => true, 'message' => 'Estado actualizado']);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Obtener un usuario por ID
 */
function obtenerUsuario() {
    try {
        $id = $_POST['id'] ?? 0;
        
        $usuario = DB::queryFirstRow(
            "SELECT * FROM usuarios WHERE id_usuario = ?",
            $id
        );
        
        if ($usuario) {
            unset($usuario['clave']); // No enviar la contraseña
            echo json_encode(['success' => true, 'data' => $usuario]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}

/**
 * Obtener tipos de identificación
 */
function obtenerTiposIdentificacion() {
    try {
        $tipos = DB::queryAllRows("SELECT * FROM tipos_identificacion ORDER BY nombre_tipo");
        echo json_encode(['success' => true, 'data' => $tipos]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error: ' . $e->getMessage()]);
    }
}
?>
