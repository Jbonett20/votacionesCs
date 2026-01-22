<?php
/**
 * Controlador de Votantes
 * Gestiona las operaciones CRUD de votantes
 */

require_once '../config/db.php';
require_once '../config/session.php';
require_once '../models/LiderModel.php';

header('Content-Type: application/json; charset=utf-8');

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
            // Verificar si el usuario es líder en la tabla lideres
            $usuario_username = $_SESSION['usuario'] ?? $_SESSION['usuario_username'] ?? '';
            $idLider = DB::queryOneValue("SELECT id_lider FROM lideres WHERE usuario = ?", $usuario_username);
            
            if (!$idLider) {
                echo json_encode(['success' => true, 'data' => []]);
                return;
            }
            
            $votantes = DB::queryAllRows(
                "SELECT v.*, t.nombre_tipo, l.nombres as lider_nombres, l.apellidos as lider_apellidos
                 FROM votantes v
                 INNER JOIN tipos_identificacion t ON v.id_tipo_identificacion = t.id_tipo_identificacion
                 LEFT JOIN lideres l ON v.id_lider = l.id_lider
                 WHERE v.id_lider = ?
                 ORDER BY v.id_votante DESC",
                $idLider
            );
        } elseif ($usuario_rol == 2) {
            // Admin solo ve votantes de sus líderes o registrados directamente por él
            $votantes = DB::queryAllRows(
                "SELECT v.*, t.nombre_tipo, 
                        l.nombres as lider_nombres, l.apellidos as lider_apellidos,
                        CONCAT(u.nombres, ' ', u.apellidos) as admin_directo
                 FROM votantes v
                 INNER JOIN tipos_identificacion t ON v.id_tipo_identificacion = t.id_tipo_identificacion
                 LEFT JOIN lideres l ON v.id_lider = l.id_lider
                 LEFT JOIN usuarios u ON v.id_administrador_directo = u.id_usuario
                 WHERE (l.id_usuario_creador = ? OR v.id_administrador_directo = ?)
                 ORDER BY v.id_votante DESC",
                $usuario_id,
                $usuario_id
            );
        } else {
            // SuperAdmin ve todos
            $votantes = DB::queryAllRows(
                "SELECT v.*, t.nombre_tipo, 
                        l.nombres as lider_nombres, l.apellidos as lider_apellidos,
                        CONCAT(u.nombres, ' ', u.apellidos) as admin_directo,
                        v.mesa
                 FROM votantes v
                 INNER JOIN tipos_identificacion t ON v.id_tipo_identificacion = t.id_tipo_identificacion
                 LEFT JOIN lideres l ON v.id_lider = l.id_lider
                 LEFT JOIN usuarios u ON v.id_administrador_directo = u.id_usuario
                 ORDER BY v.id_votante DESC"
            );
        }
        
        // Asegurar que $votantes sea un array
        if (!is_array($votantes)) {
            $votantes = [];
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
        
        // Determinar el líder o administrador directo
        $id_lider = null;
        $id_administrador_directo = null;
        $usuario_rol = $_SESSION['usuario_rol'];
        $usuario_id = $_SESSION['usuario_id'];
        
        if ($usuario_rol == 3) {
            // Si es líder (usuario que SÍ se loguea), buscar su ID en la tabla lideres
            $id_lider = DB::queryOneValue("SELECT id_lider FROM lideres WHERE usuario = ?", $_SESSION['usuario_username']);
            
            if (!$id_lider) {
                echo json_encode(['success' => false, 'message' => 'Usuario líder no encontrado']);
                return;
            }
        } else {
            // Si es Admin o SuperAdmin
            $id_lider_post = $_POST['id_lider'] ?? '';
            
            if ($id_lider_post === 'actual' || $id_lider_post === 'yo') {
                // Registrar por mí (sin líder, directamente por el admin)
                $id_lider = null;
                $id_administrador_directo = $usuario_id;
            } else if (!empty($id_lider_post)) {
                // Asignar a un líder existente
                $id_lider = $id_lider_post;
                $id_administrador_directo = null;
            } else {
                // Sin líder, registrado directamente por admin
                $id_lider = null;
                $id_administrador_directo = $usuario_id;
            }
        }
        
        // VALIDACIÓN DE DUPLICADOS EN TODO EL SISTEMA
        require_once '../models/LiderModel.php';
        $validacion = LiderModel::identificacionExiste($_POST['identificacion']);
        if ($validacion['existe']) {
            $mensaje = "<strong>⚠️ La identificación ya está registrada</strong><br><br>";
            $mensaje .= "<strong>Tipo:</strong> {$validacion['tipo']}<br>";
            $mensaje .= "<strong>Nombre:</strong> {$validacion['nombre']}<br>";
            
            $detalles_existente = '';
            
            if ($validacion['tipo'] === 'líder' && isset($validacion['administrador'])) {
                $mensaje .= "<strong>Creado por:</strong> {$validacion['administrador']}";
                $detalles_existente = "Creado por: {$validacion['administrador']}";
            } elseif ($validacion['tipo'] === 'votante') {
                if (isset($validacion['lider']) && $validacion['lider']) {
                    $mensaje .= "<strong>Pertenece al líder:</strong> {$validacion['lider']}";
                    $detalles_existente = "Pertenece al líder: {$validacion['lider']}";
                } elseif (isset($validacion['administrador']) && $validacion['administrador']) {
                    $mensaje .= "<strong>Registrado por:</strong> {$validacion['administrador']}";
                    $detalles_existente = "Registrado por: {$validacion['administrador']}";
                }
            } elseif ($validacion['tipo'] === 'usuario' && isset($validacion['rol'])) {
                $mensaje .= "<strong>Rol:</strong> {$validacion['rol']}";
                $detalles_existente = "Rol: {$validacion['rol']}";
            }
            
            // GUARDAR O ACTUALIZAR EN TABLA DE DUPLICADOS
            try {
                // Obtener nombre completo del usuario desde la base de datos
                $usuario_info = DB::queryFirstRow(
                    "SELECT CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE id_usuario = ?",
                    $usuario_id
                );
                $nombre_completo_usuario = $usuario_info ? $usuario_info['nombre_completo'] : ($_SESSION['usuario'] ?? 'Usuario');
                
                // Agregar información del líder o admin
                $nombre_usuario_intento_completo = $nombre_completo_usuario;
                $id_lider_post = $_POST['id_lider'] ?? '';
                
                if (!empty($id_lider_post) && $id_lider_post !== 'actual' && $id_lider_post !== 'yo') {
                    // Seleccionó un líder específico
                    $lider = DB::queryFirstRow(
                        "SELECT CONCAT(nombres, ' ', apellidos) as nombre FROM lideres WHERE id_lider = ?",
                        $id_lider_post
                    );
                    if ($lider) {
                        $nombre_usuario_intento_completo .= ', Líder: ' . $lider['nombre'];
                    }
                } else {
                    // Registrado directamente por el admin/usuario actual
                    $nombre_usuario_intento_completo .= ' (Registro directo)';
                }
                
                // Verificar si ya existe un registro con esta identificación
                $duplicado_existente = DB::queryFirstRow(
                    "SELECT id_duplicado, nombre_usuario_intento FROM votantes_duplicados WHERE identificacion = ?",
                    trim($_POST['identificacion'])
                );
                
                if ($duplicado_existente) {
                    // Actualizar agregando el nuevo intento
                    $nombres_acumulados = $duplicado_existente['nombre_usuario_intento'] . ' | ' . $nombre_usuario_intento_completo;
                    
                    DB::update('votantes_duplicados', [
                        'nombre_usuario_intento' => $nombres_acumulados,
                        'fecha_intento' => DB::sqleval('NOW()'),
                        'lugar_mesa' => !empty($_POST['lugar_mesa']) ? trim($_POST['lugar_mesa']) : $duplicado_existente['lugar_mesa'] ?? null
                    ], 'id_duplicado=%i', $duplicado_existente['id_duplicado']);
                } else {
                    // Insertar nuevo registro
                    DB::insert('votantes_duplicados', [
                        'nombres' => trim($_POST['nombres']),
                        'apellidos' => trim($_POST['apellidos']),
                        'identificacion' => trim($_POST['identificacion']),
                        'telefono' => trim($_POST['telefono'] ?? ''),
                        'mesa' => !empty($_POST['mesa']) ? intval($_POST['mesa']) : 0,
                        'lugar_mesa' => !empty($_POST['lugar_mesa']) ? trim($_POST['lugar_mesa']) : null,
                        'tipo_existente' => $validacion['tipo'],
                        'nombre_existente' => $validacion['nombre'],
                        'detalles_existente' => $detalles_existente,
                        'metodo_intento' => 'formulario',
                        'identificacion_lider_intento' => null,
                        'id_usuario_intento' => $usuario_id,
                        'nombre_usuario_intento' => $nombre_usuario_intento_completo
                    ]);
                }
            } catch (Exception $e) {
                // Si falla al guardar duplicado, continuar sin detener el proceso
                error_log("Error al guardar duplicado: " . $e->getMessage());
            }
            
            echo json_encode(['success' => false, 'message' => $mensaje]);
            return;
        }
        
        // Insertar votante (puede tener líder o admin directo)
        $datos = [
            'nombres' => trim($_POST['nombres']),
            'apellidos' => trim($_POST['apellidos']),
            'identificacion' => trim($_POST['identificacion']),
            'id_tipo_identificacion' => $_POST['id_tipo_identificacion'],
            'sexo' => $_POST['sexo'],
            'telefono' => trim($_POST['telefono'] ?? ''), // No obligatorio
            'mesa' => !empty($_POST['mesa']) ? intval($_POST['mesa']) : 0, // Por defecto 0
            'lugar_mesa' => !empty($_POST['lugar_mesa']) ? trim($_POST['lugar_mesa']) : null,
            'id_lider' => $id_lider, // Puede ser NULL
            'id_administrador_directo' => $id_administrador_directo, // Puede ser NULL
            'id_usuario_creador' => $usuario_id, // Usuario que crea el registro
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
        $usuario_id = $_SESSION['usuario_id'];
        $usuario_rol = $_SESSION['usuario_rol'];
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID de votante no válido']);
            return;
        }
        
        // Validar permisos (líder solo puede editar sus votantes)
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
        
        // VALIDACIÓN DE DUPLICADOS EN TODO EL SISTEMA (excepto el actual)
        require_once '../models/LiderModel.php';
        $validacion = LiderModel::identificacionExiste($_POST['identificacion'], $id, 'votante');
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
        
        // Actualizar votante
        $datos = [
            'nombres' => trim($_POST['nombres']),
            'apellidos' => trim($_POST['apellidos']),
            'identificacion' => trim($_POST['identificacion']),
            'id_tipo_identificacion' => $_POST['id_tipo_identificacion'],
            'sexo' => $_POST['sexo'],
            'telefono' => trim($_POST['telefono'] ?? ''), // No obligatorio
            'mesa' => !empty($_POST['mesa']) ? intval($_POST['mesa']) : 0, // Por defecto 0
            'lugar_mesa' => !empty($_POST['lugar_mesa']) ? trim($_POST['lugar_mesa']) : null,
            'id_estado' => $_POST['id_estado'] ?? 1
        ];
        
        // Si es admin/superadmin, puede cambiar el líder
        if ($usuario_rol != 3 && isset($_POST['id_lider'])) {
            $id_lider_post = $_POST['id_lider'];
            if ($id_lider_post === 'actual' || $id_lider_post === 'yo') {
                // Registrar por mí (sin líder, directamente por el admin)
                $datos['id_lider'] = null;
                $datos['id_administrador_directo'] = $usuario_id;
            } else if (!empty($id_lider_post)) {
                // Asignar a un líder específico
                $datos['id_lider'] = $id_lider_post;
                $datos['id_administrador_directo'] = null;
            } else {
                // Sin líder, registrado directamente por admin
                $datos['id_lider'] = null;
                $datos['id_administrador_directo'] = $usuario_id;
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
        
        // Eliminar permanentemente el votante
        DB::delete('votantes', 'id_votante = ?', $id);
        
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
        $usuario_id = $_SESSION['usuario_id'];
        $usuario_rol = $_SESSION['usuario_rol'];
        
        $lideres = LiderModel::obtenerLideresActivos($usuario_id, $usuario_rol);
        
        echo json_encode([
            'success' => true, 
            'data' => $lideres
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al obtener líderes: ' . $e->getMessage()]);
    }
}
?>
