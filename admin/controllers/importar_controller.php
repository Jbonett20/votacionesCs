<?php
/**
 * Controlador de Importación Masiva
 * Maneja la carga masiva de votantes desde Excel/CSV
 */

require_once '../config/db.php';
require_once '../config/session.php';
require_once '../models/LiderModel.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

header('Content-Type: application/json; charset=utf-8');

requerirRol([1, 2]); // Solo SuperAdmin y Admin

$action = $_POST['action'] ?? '';

if ($action === 'importar_votantes') {
    importarVotantes();
} else {
    echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Importar votantes desde archivo CSV
 */
function importarVotantes() {
    try {
        // Validar que se haya subido un archivo
        if (!isset($_FILES['archivo']) || $_FILES['archivo']['error'] !== UPLOAD_ERR_OK) {
            echo json_encode(['success' => false, 'message' => 'No se ha subido ningún archivo']);
            return;
        }
        
        $archivo = $_FILES['archivo']['tmp_name'];
        $extension = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        
        if (!in_array($extension, ['csv', 'txt', 'xlsx', 'xls'])) {
            echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos CSV o Excel']);
            return;
        }
        
        // Procesar archivo según tipo
        $datos_archivo = [];
        
        if (in_array($extension, ['xlsx', 'xls'])) {
            // Leer archivo Excel
            try {
                $spreadsheet = IOFactory::load($archivo);
                $worksheet = $spreadsheet->getActiveSheet();
                $datos_archivo = $worksheet->toArray();
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'message' => 'Error al leer archivo Excel: ' . $e->getMessage()]);
                return;
            }
        } else {
            // Leer archivo CSV
            $handle = fopen($archivo, 'r');
            if (!$handle) {
                echo json_encode(['success' => false, 'message' => 'No se pudo abrir el archivo']);
                return;
            }
            while (($linea = fgetcsv($handle, 1000, ';')) !== false) {
                $datos_archivo[] = $linea;
            }
            fclose($handle);
        }
        
        // Buscar la línea de encabezados
        $headers = null;
        $linea_inicio = 0;
        foreach ($datos_archivo as $index => $linea) {
            // Filtrar valores nulos y vacíos
            $linea_limpia = array_filter($linea, function($val) {
                return $val !== null && trim($val) !== '';
            });
            
            // Buscar "nombres" en cualquier posición de la fila
            if (!empty($linea_limpia)) {
                foreach ($linea as $celda) {
                    if ($celda !== null) {
                        // Limpiar BOM y espacios
                        $celda_limpia = strtolower(trim(str_replace("\xEF\xBB\xBF", '', $celda)));
                        if ($celda_limpia === 'nombres') {
                            // Encontramos los encabezados, limpiar BOM y valores nulos
                            $headers = [];
                            foreach ($linea as $header) {
                                if ($header !== null) {
                                    // Remover BOM UTF-8 si existe
                                    $header_limpio = str_replace("\xEF\xBB\xBF", '', $header);
                                    $headers[] = strtolower(trim($header_limpio));
                                } else {
                                    $headers[] = '';
                                }
                            }
                            $linea_inicio = $index + 1;
                            break 2; // Salir de ambos loops
                        }
                    }
                }
            }
        }
        
        if (!$headers) {
            echo json_encode(['success' => false, 'message' => 'No se encontraron encabezados válidos en el archivo']);
            return;
        }
        
        // Validar columnas requeridas
        $columnas_requeridas = ['nombres', 'apellidos', 'identificacion', 'tipo_id', 'sexo'];
        foreach ($columnas_requeridas as $col) {
            if (!in_array($col, $headers)) {
                fclose($handle);
                return;
            }
        }
        
        // Obtener índices de columnas
        $idx_nombres = array_search('nombres', $headers);
        $idx_apellidos = array_search('apellidos', $headers);
        $idx_identificacion = array_search('identificacion', $headers);
        $idx_tipo_id = array_search('tipo_id', $headers);
        $idx_telefono = array_search('telefono', $headers);
        $idx_sexo = array_search('sexo', $headers);
        $idx_identificacion_lider = array_search('identificacion_lider', $headers);
        
        $usuario_id = $_SESSION['usuario_id'];
        $insertados = 0;
        $errores = [];
        $duplicados = [];
        
        // Procesar datos
        $linea_num = $linea_inicio;
        
        // Procesar datos desde el array
        for ($i = $linea_inicio; $i < count($datos_archivo); $i++) {
            $datos = $datos_archivo[$i];
            
            // Saltar líneas vacías
            if (empty(array_filter($datos))) {
                continue;
            }
            
            // Obtener valores
            $nombres = trim($datos[$idx_nombres] ?? '');
            $apellidos = trim($datos[$idx_apellidos] ?? '');
            $identificacion = trim($datos[$idx_identificacion] ?? '');
            $tipo_id = trim($datos[$idx_tipo_id] ?? '');
            $telefono = trim($datos[$idx_telefono] ?? '');
            $sexo = strtoupper(trim($datos[$idx_sexo] ?? ''));
            $identificacion_lider = trim($datos[$idx_identificacion_lider] ?? '');
            
            // Validar campos obligatorios
            if (empty($nombres) || empty($apellidos) || empty($identificacion) || empty($tipo_id) || empty($sexo)) {
                $errores[] = "Línea $linea_num: Faltan datos obligatorios";
                continue;
            }
            
            // Validar sexo
            if (!in_array($sexo, ['M', 'F', 'O'])) {
                $errores[] = "Línea $linea_num: Sexo inválido (debe ser M, F o O)";
                continue;
            }
            
            // Validar tipo_id
            if (!in_array($tipo_id, ['1', '2', '3', '4'])) {
                $errores[] = "Línea $linea_num: Tipo de identificación inválido";
                continue;
            }
            
            // VALIDAR DUPLICADOS EN TODO EL SISTEMA CON INFORMACIÓN COMPLETA
            $validacion = LiderModel::identificacionExiste($identificacion);
            
            if ($validacion['existe']) {
                $mensaje_dup = "Línea $linea_num ($nombres $apellidos - $identificacion): Ya registrado como {$validacion['tipo']}: {$validacion['nombre']}";
                
                if ($validacion['tipo'] === 'votante') {
                    if (!empty($validacion['lider'])) {
                        $mensaje_dup .= " → Líder: {$validacion['lider']}";
                        
                        // Buscar el admin del líder
                        $lider_info = DB::queryFirstRow(
                            "SELECT CONCAT(u.nombres, ' ', u.apellidos) as admin 
                             FROM lideres l
                             INNER JOIN votantes v ON v.id_lider = l.id_lider
                             LEFT JOIN usuarios u ON l.id_usuario_creador = u.id_usuario
                             WHERE v.identificacion = ?",
                            $identificacion
                        );
                        if ($lider_info) {
                            $mensaje_dup .= " (Admin del líder: {$lider_info['admin']})";
                        }
                    } elseif (!empty($validacion['administrador'])) {
                        $mensaje_dup .= " → Administrador directo: {$validacion['administrador']}";
                    }
                } elseif ($validacion['tipo'] === 'líder') {
                    if (!empty($validacion['administrador'])) {
                        $mensaje_dup .= " → Creado por: {$validacion['administrador']}";
                    }
                } elseif ($validacion['tipo'] === 'usuario') {
                    if (isset($validacion['rol'])) {
                        $mensaje_dup .= " → Rol: {$validacion['rol']}";
                    }
                }
                
                $duplicados[] = $mensaje_dup;
                continue;
            }
            
            // Validar líder si se especifica
            $id_lider = null;
            $id_administrador_directo = null;
            
            if (!empty($identificacion_lider)) {
                $lider = DB::queryFirstRow("SELECT id_lider, CONCAT(nombres, ' ', apellidos) as nombre FROM lideres WHERE identificacion = ? AND id_estado = 1", $identificacion_lider);
                if (!$lider) {
                    $errores[] = "Línea $linea_num: No se encontró líder con identificación $identificacion_lider";
                    continue;
                }
                $id_lider = (int)$lider['id_lider'];
            } else {
                // Registro directo por admin
                $id_administrador_directo = $usuario_id;
            }
            
            // Insertar votante
            try {
                DB::insert('votantes', [
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'identificacion' => $identificacion,
                    'id_tipo_identificacion' => (int)$tipo_id,
                    'telefono' => $telefono ?: null,
                    'sexo' => $sexo,
                    'id_lider' => $id_lider,
                    'id_administrador_directo' => $id_administrador_directo,
                    'id_usuario_creador' => $usuario_id,
                    'id_estado' => 1
                ]);
                $insertados++;
            } catch (Exception $e) {
                $errores[] = "Línea $linea_num: Error al insertar - " . $e->getMessage();
            }
        }
        
        // Preparar respuesta
        $mensaje = "Proceso completado. ";
        $mensaje .= "$insertados votantes insertados. ";
        
        if (count($duplicados) > 0) {
            $mensaje .= count($duplicados) . " duplicados encontrados. ";
        }
        
        if (count($errores) > 0) {
            $mensaje .= count($errores) . " errores. ";
        }
        
        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'insertados' => $insertados,
            'duplicados' => $duplicados,
            'errores' => $errores
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al procesar archivo: ' . $e->getMessage()]);
    }
}
?>
