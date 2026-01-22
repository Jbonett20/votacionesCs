<?php
/**
 * Controlador de Exportación
 * Maneja exportaciones a Excel/CSV
 */

require_once '../config/db.php';
require_once '../config/session.php';

requerirRol([1, 2]); // Solo SuperAdmin y Admin

$action = $_GET['action'] ?? $_POST['action'] ?? '';

switch ($action) {
    case 'exportar_lideres':
        exportarLideres();
        break;
    case 'exportar_votantes':
        exportarVotantes();
        break;
    case 'exportar_reporte_completo':
        exportarReporteCompleto();
        break;
    case 'descargar_plantilla':
        descargarPlantilla();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Exportar líderes a Excel
 */
function exportarLideres() {
    $usuario_id = $_SESSION['usuario_id'];
    $usuario_rol = $_SESSION['usuario_rol'];
    
    // Query según rol
    if ($usuario_rol == 1) {
        $lideres = DB::queryAllRows("SELECT l.*, t.nombre_tipo, CONCAT(u.nombres, ' ', u.apellidos) as creador
                                     FROM lideres l
                                     LEFT JOIN tipos_identificacion t ON l.id_tipo_identificacion = t.id_tipo_identificacion
                                     LEFT JOIN usuarios u ON l.id_usuario_creador = u.id_usuario
                                     WHERE l.id_estado = 1
                                     ORDER BY l.fecha_creacion DESC");
    } else {
        $lideres = DB::queryAllRows("SELECT l.*, t.nombre_tipo, CONCAT(u.nombres, ' ', u.apellidos) as creador
                                     FROM lideres l
                                     LEFT JOIN tipos_identificacion t ON l.id_tipo_identificacion = t.id_tipo_identificacion
                                     LEFT JOIN usuarios u ON l.id_usuario_creador = u.id_usuario
                                     WHERE l.id_usuario_creador = ? AND l.id_estado = 1
                                     ORDER BY l.fecha_creacion DESC", $usuario_id);
    }
    
    // Limpiar buffers y configurar headers para descarga
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="lideres_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Crear archivo CSV
    $output = fopen('php://output', 'w');
    
    // BOM para UTF-8 (para que Excel reconozca acentos)
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, ['ID', 'Nombres', 'Apellidos', 'Identificación', 'Tipo ID', 'Sexo', 'Teléfono', 'Dirección', 'Creado por', 'Fecha Creación'], ';');
    
    // Datos
    foreach ($lideres as $lider) {
        fputcsv($output, [
            $lider['id_lider'],
            $lider['nombres'],
            $lider['apellidos'],
            $lider['identificacion'],
            $lider['nombre_tipo'],
            $lider['sexo'] == 'M' ? 'Masculino' : ($lider['sexo'] == 'F' ? 'Femenino' : 'Otro'),
            $lider['telefono'] ?? '',
            $lider['direccion'] ?? '',
            $lider['creador'] ?? '',
            date('d/m/Y H:i', strtotime($lider['fecha_creacion']))
        ], ';');
    }
    
    fclose($output);
    exit;
}

/**
 * Exportar votantes a Excel
 */
function exportarVotantes() {
    $usuario_id = $_SESSION['usuario_id'];
    $usuario_rol = $_SESSION['usuario_rol'];
    
    // Query según rol
    if ($usuario_rol == 1) {
        $votantes = DB::queryAllRows("SELECT v.*, t.nombre_tipo, 
                                      CONCAT(l.nombres, ' ', l.apellidos) as lider_nombre,
                                      CONCAT(u.nombres, ' ', u.apellidos) as admin_nombre
                                      FROM votantes v
                                      LEFT JOIN tipos_identificacion t ON v.id_tipo_identificacion = t.id_tipo_identificacion
                                      LEFT JOIN lideres l ON v.id_lider = l.id_lider
                                      LEFT JOIN usuarios u ON v.id_administrador_directo = u.id_usuario
                                      WHERE v.id_estado = 1
                                      ORDER BY v.fecha_creacion DESC");
    } else {
        $votantes = DB::queryAllRows("SELECT v.*, t.nombre_tipo, 
                                      CONCAT(l.nombres, ' ', l.apellidos) as lider_nombre,
                                      CONCAT(u.nombres, ' ', u.apellidos) as admin_nombre
                                      FROM votantes v
                                      LEFT JOIN tipos_identificacion t ON v.id_tipo_identificacion = t.id_tipo_identificacion
                                      LEFT JOIN lideres l ON v.id_lider = l.id_lider
                                      LEFT JOIN usuarios u ON v.id_administrador_directo = u.id_usuario
                                      WHERE v.id_usuario_creador = ? AND v.id_estado = 1
                                      ORDER BY v.fecha_creacion DESC", $usuario_id);
    }
    
    // Limpiar buffers y configurar headers
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="votantes_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, ['ID', 'Nombres', 'Apellidos', 'Identificación', 'Tipo ID', 'Teléfono', 'Sexo', 'Mesa', 'Lugar Mesa', 'Líder', 'Admin Directo', 'Fecha Creación'], ';');
    
    // Datos
    foreach ($votantes as $votante) {
        fputcsv($output, [
            $votante['id_votante'],
            $votante['nombres'],
            $votante['apellidos'],
            $votante['identificacion'],
            $votante['nombre_tipo'],
            $votante['telefono'] ?? '',
            $votante['sexo'] == 'M' ? 'Masculino' : ($votante['sexo'] == 'F' ? 'Femenino' : 'Otro'),
            $votante['mesa'] ?? 0,
            $votante['lugar_mesa'] ?? '',
            $votante['lider_nombre'] ?? 'Ninguno',
            $votante['admin_nombre'] ?? 'Ninguno',
            date('d/m/Y H:i', strtotime($votante['fecha_creacion']))
        ], ';');
    }
    
    fclose($output);
    exit;
}

/**
 * Exportar reporte completo
 */
function exportarReporteCompleto() {
    $usuario_id = $_SESSION['usuario_id'];
    $usuario_rol = $_SESSION['usuario_rol'];
    
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_completo_' . date('Y-m-d_His') . '.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Resumen
    if ($usuario_rol == 1) {
        $total_lideres = DB::queryOneValue("SELECT COUNT(*) FROM lideres WHERE id_estado = 1");
        $total_votantes = DB::queryOneValue("SELECT COUNT(*) FROM votantes WHERE id_estado = 1");
    } else {
        $total_lideres = DB::queryOneValue("SELECT COUNT(*) FROM lideres WHERE id_usuario_creador = ? AND id_estado = 1", $usuario_id);
        $total_votantes = DB::queryOneValue("SELECT COUNT(*) FROM votantes WHERE id_usuario_creador = ? AND id_estado = 1", $usuario_id);
    }
    
    fputcsv($output, ['REPORTE DE VOTACIONES - ' . date('d/m/Y H:i')], ';');
    fputcsv($output, [''], ';');
    fputcsv($output, ['RESUMEN'], ';');
    fputcsv($output, ['Total Líderes', $total_lideres], ';');
    fputcsv($output, ['Total Votantes', $total_votantes], ';');
    fputcsv($output, [''], ';');
    
    // Líderes con votantes
    fputcsv($output, ['LÍDERES Y SUS VOTANTES'], ';');
    fputcsv($output, ['Líder', 'Identificación', 'Total Votantes'], ';');
    
    if ($usuario_rol == 1) {
        $lideres = DB::queryAllRows("SELECT l.*, COUNT(v.id_votante) as total_votantes
                                     FROM lideres l
                                     LEFT JOIN votantes v ON l.id_lider = v.id_lider
                                     WHERE l.id_estado = 1
                                     GROUP BY l.id_lider
                                     ORDER BY total_votantes DESC");
    } else {
        $lideres = DB::queryAllRows("SELECT l.*, COUNT(v.id_votante) as total_votantes
                                     FROM lideres l
                                     LEFT JOIN votantes v ON l.id_lider = v.id_lider
                                     WHERE l.id_usuario_creador = ? AND l.id_estado = 1
                                     GROUP BY l.id_lider
                                     ORDER BY total_votantes DESC", $usuario_id);
    }
    
    foreach ($lideres as $lider) {
        fputcsv($output, [
            $lider['nombres'] . ' ' . $lider['apellidos'],
            $lider['identificacion'],
            $lider['total_votantes']
        ], ';');
    }
    
    fclose($output);
    exit;
}

/**
 * Descargar plantilla para carga masiva
 */
function descargarPlantilla() {
    while (ob_get_level()) { ob_end_clean(); }
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="plantilla_votantes.csv"');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Encabezados
    fputcsv($output, ['nombres', 'apellidos', 'identificacion', 'telefono', 'mesa', 'lugar_mesa', 'identificacion_lider'], ';');
    
    // Ejemplos
    fputcsv($output, ['Juan', 'Pérez González', '1234567890', '3101234567', '1', 'Escuela Centro', ''], ';');
    fputcsv($output, ['María', 'López Martínez', '0987654321', '3109876543', '0', '', ''], ';');
    
    fclose($output);
    exit;
}
?>
