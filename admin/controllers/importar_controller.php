<?php
/**
 * Controlador de Importación Masiva
 * Maneja la carga masiva de votantes desde Excel/CSV
 */

require_once '../config/db.php';
require_once '../config/session.php';

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
        $extension = pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION);
        
        if (!in_array(strtolower($extension), ['csv', 'txt'])) {
            echo json_encode(['success' => false, 'message' => 'Solo se permiten archivos CSV']);
            return;
        }
        
        // Leer archivo
        $handle = fopen($archivo, 'r');
        if (!$handle) {
            echo json_encode(['success' => false, 'message' => 'No se pudo abrir el archivo']);
            return;
        }
        
        // Buscar la línea de encabezados
        $headers = null;
        $linea_num = 0;
        while (($linea = fgetcsv($handle, 1000, ';')) !== false) {
            $linea_num++;
            // Buscar la línea que contiene "nombres"
            if (isset($linea[0]) && strtolower(trim($linea[0])) === 'nombres') {
                $headers = array_map('trim', array_map('strtolower', $linea));
                break;
            }
        }
        
        if (!$headers) {
            fclose($handle);
            echo json_encode(['success' => false, 'message' => 'No se encontraron encabezados válidos en el archivo']);
            return;
        }
        
        // Validar columnas requeridas
        $columnas_requeridas = ['nombres', 'apellidos', 'identificacion', 'tipo_id', 'sexo'];
        foreach ($columnas_requeridas as $col) {
            if (!in_array($col, $headers)) {
                fclose($handle);
                echo json_encode(['success' => false, 'message' => "Falta la columna requerida: $col"]);
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
        $idx_id_lider = array_search('id_lider', $headers);
        
        $usuario_id = $_SESSION['usuario_id'];
        $insertados = 0;
        $errores = [];
        $duplicados = [];
        
        // Procesar datos
        while (($datos = fgetcsv($handle, 1000, ';')) !== false) {
            $linea_num++;
            
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
            $id_lider = trim($datos[$idx_id_lider] ?? '');
            
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
            
            // VALIDAR DUPLICADOS EN TODO EL SISTEMA
            $existe_votante = DB::queryFirstRow("SELECT id_votante FROM votantes WHERE identificacion = ?", $identificacion);
            $existe_lider = DB::queryFirstRow("SELECT id_lider, CONCAT(nombres, ' ', apellidos) as nombre FROM lideres WHERE identificacion = ?", $identificacion);
            
            if ($existe_votante) {
                $duplicados[] = "Línea $linea_num: Identificación $identificacion ya existe como votante";
                continue;
            }
            
            if ($existe_lider) {
                $duplicados[] = "Línea $linea_num: Identificación $identificacion ya existe como líder ({$existe_lider['nombre']})";
                continue;
            }
            
            // Validar líder si se especifica
            $id_administrador_directo = null;
            if (!empty($id_lider)) {
                $lider = DB::queryFirstRow("SELECT id_lider FROM lideres WHERE id_lider = ? AND id_estado = 1", $id_lider);
                if (!$lider) {
                    $errores[] = "Línea $linea_num: Líder ID $id_lider no existe";
                    continue;
                }
                $id_lider = (int)$id_lider;
            } else {
                // Registro directo por admin
                $id_lider = null;
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
        
        fclose($handle);
        
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
