<?php
/**
 * Controlador de Líderes
 * Gestiona las operaciones CRUD de líderes
 */

require_once '../config/db.php';
require_once '../config/session.php';
require_once '../models/LiderModel.php';

header('Content-Type: application/json; charset=utf-8');

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
        $usuario_id = $_SESSION['usuario_id'];
        $usuario_rol = $_SESSION['usuario_rol'];
        
        $lideres = LiderModel::obtenerLideres($usuario_id, $usuario_rol);
        
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
        // Validar campos requeridos (sin usuario/clave ya que los líderes NO se loguean)
        $campos = ['nombres', 'apellidos', 'identificacion', 'id_tipo_identificacion', 'sexo'];
        foreach ($campos as $campo) {
            if (empty($_POST[$campo])) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
                return;
            }
        }
        
        // Validar que la identificación no exista
        $validacion = LiderModel::identificacionExiste($_POST['identificacion']);
        if ($validacion['existe']) {
            $mensaje = "<strong>⚠️ La identificación ya está registrada</strong><br><br>";
            $mensaje .= "<strong>Tipo:</strong> {$validacion['tipo']}<br>";
            $mensaje .= "<strong>Nombre:</strong> {$validacion['nombre']}<br>";
            
            if ($validacion['tipo'] === 'líder' && isset($validacion['administrador'])) {
                $mensaje .= "<strong>Creado por:</strong> {$validacion['administrador']}";
            } elseif ($validacion['tipo'] === 'votante') {
                if (isset($validacion['lider']) && $validacion['lider']) {
                    $mensaje .= "<strong>Pertenece al líder:</strong> {$validacion['lider']}";
                } elseif (isset($validacion['administrador']) && $validacion['administrador']) {
                    $mensaje .= "<strong>Registrado por:</strong> {$validacion['administrador']}";
                }
            } elseif ($validacion['tipo'] === 'usuario' && isset($validacion['rol'])) {
                $mensaje .= "<strong>Rol:</strong> {$validacion['rol']}";
            }
            
            echo json_encode(['success' => false, 'message' => $mensaje]);
            return;
        }
        
        // Insertar líder (sin usuario/clave)
        $datos = [
            'nombres' => trim($_POST['nombres']),
            'apellidos' => trim($_POST['apellidos']),
            'identificacion' => trim($_POST['identificacion']),
            'id_tipo_identificacion' => $_POST['id_tipo_identificacion'],
            'sexo' => $_POST['sexo'],
            'telefono' => trim($_POST['telefono'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'id_usuario_creador' => $_SESSION['usuario_id'],
            'id_estado' => 1 // Activo
        ];
        
        $id = LiderModel::crear($datos);
        
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
        $usuario_id = $_SESSION['usuario_id'];
        $usuario_rol = $_SESSION['usuario_rol'];
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de líder no válido']);
            return;
        }
        
        // Admin solo puede editar sus líderes
        if ($usuario_rol == 2 && !LiderModel::liderPerteneceAUsuario($id, $usuario_id)) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para editar este líder']);
            return;
        }
        
        // Validar campos requeridos (sin usuario/clave)
        $campos = ['nombres', 'apellidos', 'identificacion', 'id_tipo_identificacion', 'sexo'];
        foreach ($campos as $campo) {
            if (empty($_POST[$campo])) {
                echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
                return;
            }
        }
        
        // Validar que la identificación no exista (excepto la actual)
        $validacion = LiderModel::identificacionExiste($_POST['identificacion'], $id);
        if ($validacion['existe']) {
            $mensaje = "<strong>⚠️ La identificación ya está registrada</strong><br><br>";
            $mensaje .= "<strong>Tipo:</strong> {$validacion['tipo']}<br>";
            $mensaje .= "<strong>Nombre:</strong> {$validacion['nombre']}<br>";
            
            if ($validacion['tipo'] === 'líder' && isset($validacion['administrador'])) {
                $mensaje .= "<strong>Creado por:</strong> {$validacion['administrador']}";
            } elseif ($validacion['tipo'] === 'votante') {
                if (isset($validacion['lider']) && $validacion['lider']) {
                    $mensaje .= "<strong>Pertenece al líder:</strong> {$validacion['lider']}";
                } elseif (isset($validacion['administrador']) && $validacion['administrador']) {
                    $mensaje .= "<strong>Registrado por:</strong> {$validacion['administrador']}";
                }
            } elseif ($validacion['tipo'] === 'usuario' && isset($validacion['rol'])) {
                $mensaje .= "<strong>Rol:</strong> {$validacion['rol']}";
            }
            
            echo json_encode(['success' => false, 'message' => $mensaje]);
            return;
        }
        
        // Actualizar líder (sin usuario/clave)
        $datos = [
            'nombres' => trim($_POST['nombres']),
            'apellidos' => trim($_POST['apellidos']),
            'identificacion' => trim($_POST['identificacion']),
            'id_tipo_identificacion' => $_POST['id_tipo_identificacion'],
            'sexo' => $_POST['sexo'],
            'telefono' => trim($_POST['telefono'] ?? ''),
            'direccion' => trim($_POST['direccion'] ?? ''),
            'id_estado' => $_POST['id_estado'] ?? 1
        ];
        
        LiderModel::actualizar($id, $datos);
        
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
        $usuario_id = $_SESSION['usuario_id'];
        $usuario_rol = $_SESSION['usuario_rol'];
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de líder no válido']);
            return;
        }
        
        // Admin solo puede eliminar sus líderes
        if ($usuario_rol == 2 && !LiderModel::liderPerteneceAUsuario($id, $usuario_id)) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para eliminar este líder']);
            return;
        }
        
        // Verificar si el líder tiene votantes asignados
        $tieneVotantes = DB::queryOneValue("SELECT COUNT(*) FROM votantes WHERE id_lider = ?", $id);
        if ($tieneVotantes > 0) {
            echo json_encode(['success' => false, 'message' => "No se puede eliminar el líder porque tiene {$tieneVotantes} votante(s) asignado(s). Primero reasigna o elimina sus votantes."]);
            return;
        }
        
        // Eliminar permanentemente el líder
        DB::delete('lideres', 'id_lider = ?', $id);
        
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
        
        $lider = LiderModel::obtenerLiderPorId($id);
        
        if (!$lider) {
            echo json_encode(['success' => false, 'message' => 'Líder no encontrado']);
            return;
        }
        
        // Verificar permisos
        $usuario_rol = $_SESSION['usuario_rol'];
        if ($usuario_rol == 2 && $lider['id_usuario_creador'] != $_SESSION['usuario_id']) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para ver este líder']);
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
