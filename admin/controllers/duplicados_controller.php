<?php
/**
 * Controlador de Votantes Duplicados
 * Gestiona la visualización de intentos de registro duplicados
 */

require_once '../config/db.php';
require_once '../config/session.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Este módulo es visible para todos los usuarios
requerirRol([1, 2, 3]); // SuperAdmin, Admin y Líder

$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Acciones que no devuelven JSON
if ($action === 'exportar') {
    exportarDuplicados();
    exit;
}

header('Content-Type: application/json; charset=utf-8');

switch ($action) {
    case 'listar':
        listarDuplicados();
        break;
    case 'eliminar':
        eliminarDuplicado();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Listar todos los votantes duplicados
 */
function listarDuplicados() {
    try {
        $usuario_rol = $_SESSION['usuario_rol'];
        $usuario_id = $_SESSION['usuario_id'];
        
        // SuperAdmin ve todos los duplicados
        if ($usuario_rol == 1) {
            $duplicados = DB::queryAllRows(
                "SELECT 
                    id_duplicado,
                    nombres,
                    apellidos,
                    identificacion,
                    telefono,
                    mesa,
                    lugar_mesa,
                    tipo_existente,
                    nombre_existente,
                    detalles_existente,
                    metodo_intento,
                    identificacion_lider_intento,
                    nombre_usuario_intento,
                    DATE_FORMAT(fecha_intento, '%d/%m/%Y %H:%i:%s') as fecha_intento_formato
                FROM votantes_duplicados
                ORDER BY fecha_intento DESC"
            );
        }
        // Admin ve los duplicados de su sistema y sus líderes
        elseif ($usuario_rol == 2) {
            $duplicados = DB::queryAllRows(
                "SELECT 
                    id_duplicado,
                    nombres,
                    apellidos,
                    identificacion,
                    telefono,
                    mesa,
                    lugar_mesa,
                    tipo_existente,
                    nombre_existente,
                    detalles_existente,
                    metodo_intento,
                    identificacion_lider_intento,
                    nombre_usuario_intento,
                    DATE_FORMAT(fecha_intento, '%d/%m/%Y %H:%i:%s') as fecha_intento_formato
                FROM votantes_duplicados
                WHERE id_usuario_intento IN (
                    SELECT id_usuario FROM usuarios WHERE id_usuario = ?
                    UNION
                    SELECT id_usuario FROM usuarios WHERE id_usuario IN (
                        SELECT DISTINCT id_usuario_creador FROM lideres WHERE id_usuario_creador = ?
                    )
                )
                ORDER BY fecha_intento DESC",
                $usuario_id,
                $usuario_id
            );
        }
        // Líder ve solo los duplicados que él intentó registrar
        else {
            $duplicados = DB::queryAllRows(
                "SELECT 
                    id_duplicado,
                    nombres,
                    apellidos,
                    identificacion,
                    telefono,
                    mesa,
                    lugar_mesa,
                    tipo_existente,
                    nombre_existente,
                    detalles_existente,
                    metodo_intento,
                    identificacion_lider_intento,
                    nombre_usuario_intento,
                    DATE_FORMAT(fecha_intento, '%d/%m/%Y %H:%i:%s') as fecha_intento_formato
                FROM votantes_duplicados
                WHERE id_usuario_intento = ?
                ORDER BY fecha_intento DESC",
                $usuario_id
            );
        }
        
        // Asegurar que $duplicados sea un array
        if (!is_array($duplicados)) {
            $duplicados = [];
        }
        
        echo json_encode(['success' => true, 'data' => $duplicados]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al listar duplicados: ' . $e->getMessage()]);
    }
}

/**
 * Eliminar un registro de duplicado (solo SuperAdmin)
 */
function eliminarDuplicado() {
    try {
        // Solo SuperAdmin puede eliminar registros de duplicados
        if ($_SESSION['usuario_rol'] != 1) {
            echo json_encode(['success' => false, 'message' => 'No tiene permisos para eliminar registros']);
            return;
        }
        
        $id = $_POST['id'] ?? 0;
        
        if (empty($id)) {
            echo json_encode(['success' => false, 'message' => 'ID no válido']);
            return;
        }
        
        DB::delete('votantes_duplicados', 'id_duplicado=%i', $id);
        
        echo json_encode(['success' => true, 'message' => 'Registro eliminado correctamente']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al eliminar: ' . $e->getMessage()]);
    }
}

/**
 * Exportar duplicados a Excel
 */
function exportarDuplicados() {
    try {
        $usuario_rol = $_SESSION['usuario_rol'];
        $usuario_id = $_SESSION['usuario_id'];
        
        // Obtener datos según rol (misma lógica que listar)
        if ($usuario_rol == 1) {
            $duplicados = DB::queryAllRows(
                "SELECT 
                    id_duplicado,
                    nombres,
                    apellidos,
                    identificacion,
                    telefono,
                    mesa,
                    lugar_mesa,
                    tipo_existente,
                    nombre_existente,
                    detalles_existente,
                    metodo_intento,
                    identificacion_lider_intento,
                    nombre_usuario_intento,
                    DATE_FORMAT(fecha_intento, '%d/%m/%Y %H:%i:%s') as fecha_intento_formato
                FROM votantes_duplicados
                ORDER BY fecha_intento DESC"
            );
        } elseif ($usuario_rol == 2) {
            $duplicados = DB::queryAllRows(
                "SELECT 
                    id_duplicado,
                    nombres,
                    apellidos,
                    identificacion,
                    telefono,
                    mesa,
                    tipo_existente,
                    nombre_existente,
                    detalles_existente,
                    metodo_intento,
                    identificacion_lider_intento,
                    nombre_usuario_intento,
                    DATE_FORMAT(fecha_intento, '%d/%m/%Y %H:%i:%s') as fecha_intento_formato
                FROM votantes_duplicados
                WHERE id_usuario_intento IN (
                    SELECT id_usuario FROM usuarios WHERE id_usuario = ?
                    UNION
                    SELECT id_usuario FROM usuarios WHERE id_usuario IN (
                        SELECT DISTINCT id_usuario_creador FROM lideres WHERE id_usuario_creador = ?
                    )
                )
                ORDER BY fecha_intento DESC",
                $usuario_id,
                $usuario_id
            );
        } else {
            $duplicados = DB::queryAllRows(
                "SELECT 
                    id_duplicado,
                    nombres,
                    apellidos,
                    identificacion,
                    telefono,
                    mesa,
                    tipo_existente,
                    nombre_existente,
                    detalles_existente,
                    metodo_intento,
                    identificacion_lider_intento,
                    nombre_usuario_intento,
                    DATE_FORMAT(fecha_intento, '%d/%m/%Y %H:%i:%s') as fecha_intento_formato
                FROM votantes_duplicados
                WHERE id_usuario_intento = ?
                ORDER BY fecha_intento DESC",
                $usuario_id
            );
        }
        
        // Crear Excel
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        
        // Encabezados
        $sheet->setCellValue('A1', 'ID');
        $sheet->setCellValue('B1', 'Fecha/Hora Intento');
        $sheet->setCellValue('C1', 'Método');
        $sheet->setCellValue('D1', 'Nombres');
        $sheet->setCellValue('E1', 'Apellidos');
        $sheet->setCellValue('F1', 'Identificación');
        $sheet->setCellValue('G1', 'Teléfono');
        $sheet->setCellValue('H1', 'Mesa');
        $sheet->setCellValue('I1', 'Lugar Mesa');
        $sheet->setCellValue('J1', 'Ya existe como');
        $sheet->setCellValue('K1', 'Nombre existente');
        $sheet->setCellValue('L1', 'Detalles');
        $sheet->setCellValue('M1', 'Intentos de registro (usuarios)');
        
        // Estilo de encabezados
        $sheet->getStyle('A1:M1')->getFont()->setBold(true);
        $sheet->getStyle('A1:M1')->getFill()
            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
            ->getStartColor()->setARGB('FF4472C4');
        $sheet->getStyle('A1:M1')->getFont()->getColor()->setARGB('FFFFFFFF');
        
        // Datos
        $fila = 2;
        foreach ($duplicados as $dup) {
            $sheet->setCellValue('A' . $fila, $dup['id_duplicado']);
            $sheet->setCellValue('B' . $fila, $dup['fecha_intento_formato']);
            $sheet->setCellValue('C' . $fila, ucfirst($dup['metodo_intento']));
            $sheet->setCellValue('D' . $fila, $dup['nombres']);
            $sheet->setCellValue('E' . $fila, $dup['apellidos']);
            $sheet->setCellValue('F' . $fila, $dup['identificacion']);
            $sheet->setCellValue('G' . $fila, $dup['telefono'] ?: 'N/A');
            $sheet->setCellValue('H' . $fila, $dup['mesa']);
            $sheet->setCellValue('I' . $fila, $dup['lugar_mesa'] ?: '');
            $sheet->setCellValue('J' . $fila, ucfirst($dup['tipo_existente']));
            $sheet->setCellValue('K' . $fila, $dup['nombre_existente']);
            $sheet->setCellValue('L' . $fila, $dup['detalles_existente'] ?: 'N/A');
            $sheet->setCellValue('M' . $fila, $dup['nombre_usuario_intento']);
            $fila++;
        }
        
        // Autoajustar columnas
        foreach (range('A', 'M') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
        
        // Enviar archivo
        $filename = 'votantes_duplicados_' . date('Y-m-d_His') . '.xlsx';
        
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment;filename="' . $filename . '"');
        header('Cache-Control: max-age=0');
        
        $writer = new Xlsx($spreadsheet);
        $writer->save('php://output');
        exit;
        
    } catch (Exception $e) {
        die('Error al exportar: ' . $e->getMessage());
    }
}
?>
