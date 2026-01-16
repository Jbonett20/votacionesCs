<?php
/**
 * Modelo de Líderes
 * Gestiona las operaciones de la tabla lideres
 */

require_once __DIR__ . '/../config/db.php';

class LiderModel {
    
    /**
     * Obtener todos los líderes según el usuario
     */
    public static function obtenerLideres($usuario_id, $usuario_rol) {
        if ($usuario_rol == 1) {
            // SuperAdmin ve todos
            return DB::queryAllRows(
                "SELECT l.*, t.nombre_tipo,
                        CONCAT(u.nombres, ' ', u.apellidos) as creador
                 FROM lideres l
                 INNER JOIN tipos_identificacion t ON l.id_tipo_identificacion = t.id_tipo_identificacion
                 INNER JOIN usuarios u ON l.id_usuario_creador = u.id_usuario
                 ORDER BY l.id_lider DESC"
            );
        } else {
            // Admin solo ve los que él creó
            return DB::queryAllRows(
                "SELECT l.*, t.nombre_tipo,
                        CONCAT(u.nombres, ' ', u.apellidos) as creador
                 FROM lideres l
                 INNER JOIN tipos_identificacion t ON l.id_tipo_identificacion = t.id_tipo_identificacion
                 INNER JOIN usuarios u ON l.id_usuario_creador = u.id_usuario
                 WHERE l.id_usuario_creador = ?
                 ORDER BY l.id_lider DESC",
                $usuario_id
            );
        }
    }
    
    /**
     * Obtener un líder por ID
     */
    public static function obtenerLiderPorId($id) {
        return DB::queryFirstRow(
            "SELECT * FROM lideres WHERE id_lider = ?",
            $id
        );
    }
    
    /**
     * Verificar si un líder pertenece a un usuario
     */
    public static function liderPerteneceAUsuario($id_lider, $id_usuario) {
        $count = DB::queryOneValue(
            "SELECT COUNT(*) FROM lideres WHERE id_lider = ? AND id_usuario_creador = ?",
            $id_lider, $id_usuario
        );
        return $count > 0;
    }
    
    /**
     * Crear un líder
     */
    public static function crear($datos) {
        return DB::insert('lideres', $datos);
    }
    
    /**
     * Actualizar un líder
     */
    public static function actualizar($id, $datos) {
        return DB::update('lideres', $datos, 'id_lider = ?', $id);
    }
    
    /**
     * Cambiar estado de un líder
     */
    public static function cambiarEstado($id, $estado) {
        return DB::update('lideres', ['id_estado' => $estado], 'id_lider = ?', $id);
    }
    
    /**
     * Verificar si existe una identificación y retornar información del cruce
     */
    public static function identificacionExiste($identificacion, $excluir_id = null, $tipo_excluir = 'lider') {
        // Verificar en lideres
        if ($tipo_excluir == 'lider' && $excluir_id) {
            $lider = DB::queryFirstRow(
                "SELECT l.id_lider, CONCAT(l.nombres, ' ', l.apellidos) as nombre, 
                        CONCAT(u.nombres, ' ', u.apellidos) as administrador
                 FROM lideres l
                 LEFT JOIN usuarios u ON l.id_usuario_creador = u.id_usuario
                 WHERE l.identificacion = ? AND l.id_lider != ?",
                $identificacion, $excluir_id
            );
        } else {
            $lider = DB::queryFirstRow(
                "SELECT l.id_lider, CONCAT(l.nombres, ' ', l.apellidos) as nombre,
                        CONCAT(u.nombres, ' ', u.apellidos) as administrador
                 FROM lideres l
                 LEFT JOIN usuarios u ON l.id_usuario_creador = u.id_usuario
                 WHERE l.identificacion = ?",
                $identificacion
            );
        }
        
        if ($lider) {
            return [
                'existe' => true,
                'tipo' => 'líder',
                'nombre' => $lider['nombre'],
                'administrador' => $lider['administrador']
            ];
        }
        
        // Verificar en usuarios
        $usuario = DB::queryFirstRow(
            "SELECT u.id_usuario, CONCAT(u.nombres, ' ', u.apellidos) as nombre, r.nombre_rol
             FROM usuarios u
             INNER JOIN roles r ON u.id_rol = r.id_rol
             WHERE u.identificacion = ?",
            $identificacion
        );
        
        if ($usuario) {
            return [
                'existe' => true,
                'tipo' => 'usuario',
                'nombre' => $usuario['nombre'],
                'rol' => $usuario['nombre_rol']
            ];
        }
        
        // Verificar en votantes
        if ($tipo_excluir == 'votante' && $excluir_id) {
            $votante = DB::queryFirstRow(
                "SELECT id_votante, CONCAT(v.nombres, ' ', v.apellidos) as nombre,
                        CONCAT(l.nombres, ' ', l.apellidos) as lider,
                        CONCAT(u.nombres, ' ', u.apellidos) as administrador
                 FROM votantes v
                 LEFT JOIN lideres l ON v.id_lider = l.id_lider
                 LEFT JOIN usuarios u ON v.id_administrador_directo = u.id_usuario
                 WHERE v.identificacion = ? AND v.id_votante != ?",
                $identificacion, $excluir_id
            );
        } else {
            $votante = DB::queryFirstRow(
                "SELECT id_votante, CONCAT(v.nombres, ' ', v.apellidos) as nombre,
                        CONCAT(l.nombres, ' ', l.apellidos) as lider,
                        CONCAT(u.nombres, ' ', u.apellidos) as administrador
                 FROM votantes v
                 LEFT JOIN lideres l ON v.id_lider = l.id_lider
                 LEFT JOIN usuarios u ON v.id_administrador_directo = u.id_usuario
                 WHERE v.identificacion = ?",
                $identificacion
            );
        }
        
        if ($votante) {
            return [
                'existe' => true,
                'tipo' => 'votante',
                'nombre' => $votante['nombre'],
                'lider' => $votante['lider'],
                'administrador' => $votante['administrador']
            ];
        }
        
        return ['existe' => false];
    }
    
    /**
     * Obtener líderes activos para select
     */
    public static function obtenerLideresActivos($usuario_id, $usuario_rol) {
        if ($usuario_rol == 1) {
            // SuperAdmin ve todos
            return DB::queryAllRows(
                "SELECT id_lider, nombres, apellidos 
                 FROM lideres 
                 WHERE id_estado = 1 
                 ORDER BY nombres, apellidos"
            );
        } else {
            // Admin solo ve los suyos
            return DB::queryAllRows(
                "SELECT id_lider, nombres, apellidos 
                 FROM lideres 
                 WHERE id_estado = 1 AND id_usuario_creador = ?
                 ORDER BY nombres, apellidos",
                $usuario_id
            );
        }
    }
    
    /**
     * Contar líderes
     */
    public static function contarLideres($usuario_id = null, $usuario_rol = null) {
        if ($usuario_rol == 1) {
            return DB::queryOneValue("SELECT COUNT(*) FROM lideres WHERE id_estado = 1");
        } elseif ($usuario_id) {
            return DB::queryOneValue(
                "SELECT COUNT(*) FROM lideres WHERE id_estado = 1 AND id_usuario_creador = ?",
                $usuario_id
            );
        }
        return 0;
    }
}
?>
