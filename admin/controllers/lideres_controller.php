<?php
/**
 * Controlador de Líderes
 * Gestiona las operaciones CRUD de líderes
 */

require_once '../config/db.php';
require_once '../config/session.php';

header('Content-Type: application/json');

// Validar sesión y permisos
requerirRol([1, 2]); // Solo SuperAdmin y Admin

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'listar':
        listarLideres();
        break;
    case 'crear':
        crearLider();
        break;
    case 'editar':
        editarLider();
        break;
    case 'eliminar':
        eliminarLider();
        break;
    case 'obtener':
        obtenerLider();
        break;
    case 'obtener_tipos_identificacion':
        obtenerTiposIdentificacion();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Listar líderes
 */
function listarLideres() {
    try {
        $lideres = DB::queryAllRows(
            "SELECT u.*, t.nombre_tipo 
             FROM usuarios u
             INNER JOIN tipos_identificacion t ON u.id_tipo_identificacion = t.id_tipo_identificacion
             WHERE u.id_rol = 3
             ORDER BY u.id_usuario DESC"
        );
        
        echo json_encode(['success' => true, 'data' => $lideres]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al listar líderes: ' . $e->getMessage()]);
    }
}

/**
 * Crear líder
 */
function crearLider() {
    try {
        // Validar campos requeridos
        $campos = ['nombres', 'apellidos', 'identificacion', 'id_tipo_identificacion', 'sexo', 'usuario', 'clave'];
        foreach ($campos as $campo) {
            if (empty($_POST[$campo])) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
                return;
            }
        }
        
        // Validar que el usuario no exista
        $usuarioExiste = DB::queryOneValue("SELECT COUNT(*) FROM usuarios WHERE usuario = ?", $_POST['usuario']);
        if ($usuarioExiste > 0) {
            echo json_encode(['success' => false, 'message' => 'El usuario ya existe']);
            return;
        }
        
        // Validar que la identificación no exista
        $identificacionExiste = DB::queryOneValue("SELECT COUNT(*) FROM usuarios WHERE identificacion = ?", $_POST['identificacion']);
        if ($identificacionExiste > 0) {
            echo json_encode(['success' => false, 'message' => 'La identificación ya está registrada']);
            return;
        }
        
        // Validar longitud de contraseña
        if (strlen($_POST['clave']) < 6) {
            echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 6 caracteres']);
            return;
        }
        
        // Insertar líder
        $datos = [
            'nombres' => trim($_POST['nombres']),
            'apellidos' => trim($_POST['apellidos']),
            'identificacion' => trim($_POST['identificacion']),
            'id_tipo_identificacion' => $_POST['id_tipo_identificacion'],
            'sexo' => $_POST['sexo'],
            'usuario' => trim($_POST['usuario']),
            'clave' => password_hash($_POST['clave'], PASSWORD_DEFAULT),
            'id_rol' => 3, // Líder
            'id_estado' => 1 // Activo
        ];
        
        $id = DB::insert('usuarios', $datos);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Líder creado exitosamente',
            'id' => $id
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al crear líder: ' . $e->getMessage()]);
    }
}

/**
 * Editar líder
 */
function editarLider() {
    try {
        $id = $_POST['lider_id'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de líder no válido']);
            return;
        }
        
        // Validar campos requeridos
        $campos = ['nombres', 'apellidos', 'identificacion', 'id_tipo_identificacion', 'sexo', 'usuario'];
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
        
        // Actualizar líder
        $datos = [
            'nombres' => trim($_POST['nombres']),
            'apellidos' => trim($_POST['apellidos']),
            'identificacion' => trim($_POST['identificacion']),
            'id_tipo_identificacion' => $_POST['id_tipo_identificacion'],
            'sexo' => $_POST['sexo'],
            'usuario' => trim($_POST['usuario']),
            'id_estado' => $_POST['id_estado'] ?? 1
        ];
        
        DB::update('usuarios', $datos, 'id_usuario = ?', $id);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Líder actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar líder: ' . $e->getMessage()]);
    }
}

/**
 * Eliminar líder (cambiar estado a inactivo)
 */
function eliminarLider() {
    try {
        $id = $_POST['id'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de líder no válido']);
            return;
        }
        
        // Cambiar estado a inactivo
        DB::update('usuarios', ['id_estado' => 2], 'id_usuario = ?', $id);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Líder eliminado exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar líder: ' . $e->getMessage()]);
    }
}

/**
 * Obtener datos de un líder
 */
function obtenerLider() {
    try {
        $id = $_POST['id'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de líder no válido']);
            return;
        }
        
        $lider = DB::queryFirstRow(
            "SELECT * FROM usuarios WHERE id_usuario = ? AND id_rol = 3",
            $id
        );
        
        if (!$lider) {
            echo json_encode(['success' => false, 'message' => 'Líder no encontrado']);
            return;
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $lider
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener líder: ' . $e->getMessage()]);
    }
}

/**
 * Obtener tipos de identificación
 */
function obtenerTiposIdentificacion() {
    try {
        $tipos = DB::queryAllRows("SELECT * FROM tipos_identificacion ORDER BY nombre_tipo");
        
        echo json_encode([
            'success' => true, 
            'data' => $tipos
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener tipos: ' . $e->getMessage()]);
    }
}
?>
