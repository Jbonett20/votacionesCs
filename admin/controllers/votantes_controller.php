<?php
/**
 * Controlador de Votantes
 * Gestiona las operaciones CRUD de votantes
 */

require_once '../config/db.php';
require_once '../config/session.php';

header('Content-Type: application/json');

// Validar sesión y permisos
requerirRol([1, 2, 3]); // SuperAdmin, Admin y Líder

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'listar':
        listarVotantes();
        break;
    case 'crear':
        crearVotante();
        break;
    case 'editar':
        editarVotante();
        break;
    case 'eliminar':
        eliminarVotante();
        break;
    case 'obtener':
        obtenerVotante();
        break;
    case 'obtener_tipos_identificacion':
        obtenerTiposIdentificacion();
        break;
    case 'obtener_lideres':
        obtenerLideres();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Listar votantes según rol
 */
function listarVotantes() {
    try {
        $usuario_id = $_SESSION['usuario_id'];
        $usuario_rol = $_SESSION['usuario_rol'];
        
        // Si es líder, solo ve sus votantes
        if ($usuario_rol == 3) {
            $votantes = DB::queryAllRows(
                "SELECT v.*, t.nombre_tipo, u.nombres as lider_nombres, u.apellidos as lider_apellidos
                 FROM votantes v
                 INNER JOIN tipos_identificacion t ON v.id_tipo_identificacion = t.id_tipo_identificacion
                 INNER JOIN usuarios u ON v.id_lider = u.id_usuario
                 WHERE v.id_lider = ?
                 ORDER BY v.id_votante DESC",
                $usuario_id
            );
        } else {
            // SuperAdmin y Admin ven todos
            $votantes = DB::queryAllRows(
                "SELECT v.*, t.nombre_tipo, u.nombres as lider_nombres, u.apellidos as lider_apellidos
                 FROM votantes v
                 INNER JOIN tipos_identificacion t ON v.id_tipo_identificacion = t.id_tipo_identificacion
                 INNER JOIN usuarios u ON v.id_lider = u.id_usuario
                 ORDER BY v.id_votante DESC"
            );
        }
        
        echo json_encode(['success' => true, 'data' => $votantes]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al listar votantes: ' . $e->getMessage()]);
    }
}

/**
 * Crear votante
 */
function crearVotante() {
    try {
        // Validar campos requeridos
        $campos = ['nombres', 'apellidos', 'identificacion', 'id_tipo_identificacion', 'sexo'];
        foreach ($campos as $campo) {
            if (empty($_POST[$campo])) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
                return;
            }
        }
        
        // Determinar el líder
        $id_lider = null;
        $usuario_rol = $_SESSION['usuario_rol'];
        
        if ($usuario_rol == 3) {
            // Si es líder, él mismo es el líder
            $id_lider = $_SESSION['usuario_id'];
        } else {
            // Si es Admin o SuperAdmin
            $id_lider_post = $_POST['id_lider'] ?? '';
            
            if ($id_lider_post === 'actual' || $id_lider_post === 'yo') {
                // Registrarse como líder (el admin se convierte en líder de este votante)
                $id_lider = $_SESSION['usuario_id'];
            } else {
                $id_lider = $id_lider_post;
            }
        }
        
        if (empty($id_lider)) {
            echo json_encode(['success' => false, 'message' => 'Debe seleccionar un líder']);
            return;
        }
        
        // Validar que la identificación no exista
        $identificacionExiste = DB::queryOneValue(
            "SELECT COUNT(*) FROM votantes WHERE identificacion = ?", 
            $_POST['identificacion']
        );
        if ($identificacionExiste > 0) {
            echo json_encode(['success' => false, 'message' => 'La identificación ya está registrada']);
            return;
        }
        
        // Validar que el usuario existe en la tabla usuarios también
        $identificacionExisteUsuarios = DB::queryOneValue(
            "SELECT COUNT(*) FROM usuarios WHERE identificacion = ?", 
            $_POST['identificacion']
        );
        if ($identificacionExisteUsuarios > 0) {
            echo json_encode(['success' => false, 'message' => 'La identificación ya está registrada en usuarios']);
            return;
        }
        
        // Insertar votante
        $datos = [
            'nombres' => trim($_POST['nombres']),
            'apellidos' => trim($_POST['apellidos']),
            'identificacion' => trim($_POST['identificacion']),
            'id_tipo_identificacion' => $_POST['id_tipo_identificacion'],
            'sexo' => $_POST['sexo'],
            'id_lider' => $id_lider,
            'id_estado' => 1 // Activo
        ];
        
        $id = DB::insert('votantes', $datos);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Votante registrado exitosamente',
            'id' => $id
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al crear votante: ' . $e->getMessage()]);
    }
}

/**
 * Editar votante
 */
function editarVotante() {
    try {
        $id = $_POST['votante_id'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de votante no válido']);
            return;
        }
        
        // Validar permisos (líder solo puede editar sus votantes)
        $usuario_rol = $_SESSION['usuario_rol'];
        if ($usuario_rol == 3) {
            $votante = DB::queryFirstRow(
                "SELECT * FROM votantes WHERE id_votante = ? AND id_lider = ?",
                $id, $_SESSION['usuario_id']
            );
            
            if (!$votante) {
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para editar este votante']);
                return;
            }
        }
        
        // Validar campos requeridos
        $campos = ['nombres', 'apellidos', 'identificacion', 'id_tipo_identificacion', 'sexo'];
        foreach ($campos as $campo) {
            if (empty($_POST[$campo])) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
                return;
            }
        }
        
        // Validar que la identificación no exista (excepto la actual)
        $identificacionExiste = DB::queryOneValue(
            "SELECT COUNT(*) FROM votantes WHERE identificacion = ? AND id_votante != ?", 
            $_POST['identificacion'], 
            $id
        );
        if ($identificacionExiste > 0) {
            echo json_encode(['success' => false, 'message' => 'La identificación ya está registrada']);
            return;
        }
        
        // Actualizar votante
        $datos = [
            'nombres' => trim($_POST['nombres']),
            'apellidos' => trim($_POST['apellidos']),
            'identificacion' => trim($_POST['identificacion']),
            'id_tipo_identificacion' => $_POST['id_tipo_identificacion'],
            'sexo' => $_POST['sexo'],
            'id_estado' => $_POST['id_estado'] ?? 1
        ];
        
        // Si es admin/superadmin, puede cambiar el líder
        if ($usuario_rol != 3 && isset($_POST['id_lider'])) {
            $id_lider_post = $_POST['id_lider'];
            if ($id_lider_post === 'actual' || $id_lider_post === 'yo') {
                $datos['id_lider'] = $_SESSION['usuario_id'];
            } else {
                $datos['id_lider'] = $id_lider_post;
            }
        }
        
        DB::update('votantes', $datos, 'id_votante = ?', $id);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Votante actualizado exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar votante: ' . $e->getMessage()]);
    }
}

/**
 * Eliminar votante (cambiar estado a inactivo)
 */
function eliminarVotante() {
    try {
        $id = $_POST['id'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de votante no válido']);
            return;
        }
        
        // Validar permisos (líder solo puede eliminar sus votantes)
        $usuario_rol = $_SESSION['usuario_rol'];
        if ($usuario_rol == 3) {
            $votante = DB::queryFirstRow(
                "SELECT * FROM votantes WHERE id_votante = ? AND id_lider = ?",
                $id, $_SESSION['usuario_id']
            );
            
            if (!$votante) {
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar este votante']);
                return;
            }
        }
        
        // Cambiar estado a inactivo
        DB::update('votantes', ['id_estado' => 2], 'id_votante = ?', $id);
        
        echo json_encode([
            'success' => true, 
            'message' => 'Votante eliminado exitosamente'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar votante: ' . $e->getMessage()]);
    }
}

/**
 * Obtener datos de un votante
 */
function obtenerVotante() {
    try {
        $id = $_POST['id'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de votante no válido']);
            return;
        }
        
        // Validar permisos
        $usuario_rol = $_SESSION['usuario_rol'];
        if ($usuario_rol == 3) {
            $votante = DB::queryFirstRow(
                "SELECT * FROM votantes WHERE id_votante = ? AND id_lider = ?",
                $id, $_SESSION['usuario_id']
            );
        } else {
            $votante = DB::queryFirstRow(
                "SELECT * FROM votantes WHERE id_votante = ?",
                $id
            );
        }
        
        if (!$votante) {
            echo json_encode(['success' => false, 'message' => 'Votante no encontrado']);
            return;
        }
        
        echo json_encode([
            'success' => true, 
            'data' => $votante
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener votante: ' . $e->getMessage()]);
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

/**
 * Obtener líderes activos
 */
function obtenerLideres() {
    try {
        $lideres = DB::queryAllRows(
            "SELECT id_usuario, nombres, apellidos 
             FROM usuarios 
             WHERE id_rol = 3 AND id_estado = 1 
             ORDER BY nombres, apellidos"
        );
        
        echo json_encode([
            'success' => true, 
            'data' => $lideres
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener líderes: ' . $e->getMessage()]);
    }
}
?>
