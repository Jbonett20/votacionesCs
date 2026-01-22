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
        $columnas_requeridas = ['nombres', 'apellidos', 'identificacion'];
        $columnas_faltantes = [];
        foreach ($columnas_requeridas as $col) {
            if (!in_array($col, $headers)) {
                $columnas_faltantes[] = $col;
            }
        }
        
        if (!empty($columnas_faltantes)) {
            echo json_encode([
                'success' => false, 
                'message' => 'ERROR: Faltan columnas obligatorias en el archivo Excel',
                'detalles' => 'Columnas faltantes: ' . implode(', ', $columnas_faltantes),
                'columnas_encontradas' => implode(', ', array_filter($headers)),
                    'columnas_requeridas' => 'nombres, apellidos, identificacion',
                    'columnas_opcionales' => 'telefono, mesa, lugar_mesa, identificacion_lider'
            ]);
            return;
        }
        
        // Obtener índices de columnas
        $idx_nombres = array_search('nombres', $headers);
        $idx_apellidos = array_search('apellidos', $headers);
        $idx_identificacion = array_search('identificacion', $headers);
        $idx_telefono = array_search('telefono', $headers);
        $idx_mesa = array_search('mesa', $headers);
        $idx_lugar_mesa = array_search('lugar_mesa', $headers);
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
            $telefono = trim($datos[$idx_telefono] ?? '');
            $mesa = $idx_mesa !== false ? trim($datos[$idx_mesa] ?? '') : '';
            $lugar_mesa = $idx_lugar_mesa !== false ? trim($datos[$idx_lugar_mesa] ?? '') : '';
            $identificacion_lider = trim($datos[$idx_identificacion_lider] ?? '');
            
            // Validar campos obligatorios
            $campos_faltantes = [];
            if (empty($nombres)) $campos_faltantes[] = 'nombres';
            if (empty($apellidos)) $campos_faltantes[] = 'apellidos';
            if (empty($identificacion)) $campos_faltantes[] = 'identificacion';
            
            if (!empty($campos_faltantes)) {
                $errores[] = "Línea $linea_num: Faltan campos obligatorios → " . implode(', ', $campos_faltantes) . " | Datos actuales: Nombres='$nombres', Apellidos='$apellidos', Identificación='$identificacion'";
                $linea_num++;
                continue;
            }
            
            // Validar que la identificación sea numérica
            if (!is_numeric($identificacion)) {
                $errores[] = "Línea $linea_num: La identificación '$identificacion' no es válida (debe contener solo números) | Votante: $nombres $apellidos";
                $linea_num++;
                continue;
            }
            
            // Validar longitud de identificación
            if (strlen($identificacion) < 6 || strlen($identificacion) > 12) {
                $errores[] = "Línea $linea_num: La identificación '$identificacion' tiene longitud inválida (debe tener entre 6 y 12 dígitos) | Votante: $nombres $apellidos";
                $linea_num++;
                continue;
            }
            
            // VALIDAR DUPLICADOS EN TODO EL SISTEMA CON INFORMACIÓN COMPLETA
            $validacion = LiderModel::identificacionExiste($identificacion);
            
            if ($validacion['existe']) {
                $mensaje_dup = "Línea $linea_num ($nombres $apellidos - $identificacion): Ya registrado como {$validacion['tipo']}: {$validacion['nombre']}";
                
                $detalles_existente = '';
                if ($validacion['tipo'] === 'votante') {
                    if (!empty($validacion['lider'])) {
                        $mensaje_dup .= " → Líder: {$validacion['lider']}";
                        $detalles_existente .= "Líder: {$validacion['lider']}";
                        
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
                            $detalles_existente .= " | Admin del líder: {$lider_info['admin']}";
                        }
                    } elseif (!empty($validacion['administrador'])) {
                        $mensaje_dup .= " → Administrador directo: {$validacion['administrador']}";
                        $detalles_existente .= "Administrador directo: {$validacion['administrador']}";
                    }
                } elseif ($validacion['tipo'] === 'líder') {
                    if (!empty($validacion['administrador'])) {
                        $mensaje_dup .= " → Creado por: {$validacion['administrador']}";
                        $detalles_existente .= "Creado por: {$validacion['administrador']}";
                    }
                } elseif ($validacion['tipo'] === 'usuario') {
                    if (isset($validacion['rol'])) {
                        $mensaje_dup .= " → Rol: {$validacion['rol']}";
                        $detalles_existente .= "Rol: {$validacion['rol']}";
                    }
                }
                
                // GUARDAR O ACTUALIZAR EN TABLA DE DUPLICADOS
                try {
                    // Obtener nombre completo del usuario desde la base de datos
                    $usuario_info = DB::queryFirstRow(
                        "SELECT CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE id_usuario = ?",
                        $usuario_id
                    );
                    $nombre_completo_usuario = $usuario_info ? $usuario_info['nombre_completo'] : ($_SESSION['usuario'] ?? 'Usuario');
                    
                    // Agregar información del líder o admin si existe
                    $nombre_usuario_intento_completo = $nombre_completo_usuario;
                    if (!empty($identificacion_lider)) {
                        // Primero intentar encontrar un líder con esa identificación
                        $lider = DB::queryFirstRow(
                            "SELECT id_lider, CONCAT(nombres, ' ', apellidos) as nombre FROM lideres WHERE identificacion = ? AND id_estado = 1",
                            $identificacion_lider
                        );
                        if ($lider) {
                            $nombre_usuario_intento_completo .= ', Líder: ' . $lider['nombre'];
                        } else {
                            // Si no es líder, intentar encontrar un usuario administrador (Admin/SuperAdmin)
                            $usuario_admin = DB::queryFirstRow(
                                "SELECT id_usuario, id_rol, CONCAT(nombres, ' ', apellidos) as nombre FROM usuarios WHERE identificacion = ? AND id_estado = 1",
                                $identificacion_lider
                            );
                            if ($usuario_admin && in_array($usuario_admin['id_rol'], [1,2])) {
                                $nombre_usuario_intento_completo .= ', Admin: ' . $usuario_admin['nombre'];
                            } else {
                                // Registrado directamente por el admin actual si no coincide con líder o admin
                                $nombre_usuario_intento_completo .= ' (Registro directo)';
                            }
                        }
                    } else {
                        // Registrado directamente por el admin
                        $nombre_usuario_intento_completo .= ' (Registro directo)';
                    }
                    
                    // Verificar si ya existe un registro con esta identificación
                    $duplicado_existente = DB::queryFirstRow(
                        "SELECT id_duplicado, nombre_usuario_intento FROM votantes_duplicados WHERE identificacion = ?",
                        $identificacion
                    );
                    
                    if ($duplicado_existente) {
                        // Actualizar agregando el nuevo intento
                        $nombres_acumulados = $duplicado_existente['nombre_usuario_intento'] . ' | ' . $nombre_usuario_intento_completo;
                        
                        DB::update('votantes_duplicados', [
                            'nombre_usuario_intento' => $nombres_acumulados,
                            'fecha_intento' => DB::sqleval('NOW()')
                        ], 'id_duplicado=%i', $duplicado_existente['id_duplicado']);
                    } else {
                        // Insertar nuevo registro
                        DB::insert('votantes_duplicados', [
                            'nombres' => $nombres,
                            'apellidos' => $apellidos,
                            'identificacion' => $identificacion,
                            'telefono' => $telefono ?: null,
                            'mesa' => !empty($mesa) ? (int)$mesa : 0,
                            'lugar_mesa' => !empty($lugar_mesa) ? $lugar_mesa : null,
                            'tipo_existente' => $validacion['tipo'],
                            'nombre_existente' => $validacion['nombre'],
                            'detalles_existente' => $detalles_existente,
                            'metodo_intento' => 'excel',
                            'identificacion_lider_intento' => $identificacion_lider ?: null,
                            'id_usuario_intento' => $usuario_id,
                            'nombre_usuario_intento' => $nombre_usuario_intento_completo
                        ]);
                    }
                } catch (Exception $e) {
                    // Si falla al guardar duplicado, continuar sin detener el proceso
                    error_log("Error al guardar duplicado: " . $e->getMessage());
                }
                
                $duplicados[] = $mensaje_dup;
                continue;
            }
            
            // Validar líder si se especifica
            $id_lider = null;
            $id_administrador_directo = null;
            
            if (!empty($identificacion_lider)) {
                // Intentar asignar a un líder primero
                $lider = DB::queryFirstRow("SELECT id_lider, CONCAT(nombres, ' ', apellidos) as nombre FROM lideres WHERE identificacion = ? AND id_estado = 1", $identificacion_lider);
                if ($lider) {
                    $id_lider = (int)$lider['id_lider'];
                } else {
                    // Si no es líder, verificar si la identificación corresponde a un usuario admin (SuperAdmin o Admin)
                    $usuario_admin = DB::queryFirstRow("SELECT id_usuario, id_rol, CONCAT(nombres, ' ', apellidos) as nombre FROM usuarios WHERE identificacion = ? AND id_estado = 1", $identificacion_lider);
                    if ($usuario_admin && in_array($usuario_admin['id_rol'], [1,2])) {
                        $id_administrador_directo = (int)$usuario_admin['id_usuario'];
                    } else {
                        $errores[] = "Línea $linea_num: ERROR - Líder o administrador no encontrado | Identificación buscada: '$identificacion_lider' | Votante: $nombres $apellidos (ID: $identificacion) | Solución: Verifique que el líder/administrador esté registrado en el sistema o deje el campo 'identificacion_lider' vacío para asignación directa";
                        $linea_num++;
                        continue;
                    }
                }
            } else {
                // Registro directo por admin que está importando
                $id_administrador_directo = $usuario_id;
            }
            
            // Insertar votante
            try {
                    DB::insert('votantes', [
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'identificacion' => $identificacion,
                    'id_tipo_identificacion' => 1, // Tipo por defecto: Cédula de Ciudadanía
                    'telefono' => $telefono ?: null,
                    'sexo' => 'O', // Sexo por defecto: Otro
                    'mesa' => !empty($mesa) ? (int)$mesa : 0,
                    'lugar_mesa' => !empty($lugar_mesa) ? $lugar_mesa : null,
                    'id_lider' => $id_lider,
                    'id_administrador_directo' => $id_administrador_directo,
                    'id_usuario_creador' => $usuario_id,
                    'id_estado' => 1
                ]);
                $insertados++;
            } catch (Exception $e) {
                $error_msg = $e->getMessage();
                if (strpos($error_msg, 'Duplicate entry') !== false) {
                    $errores[] = "Línea $linea_num: ERROR DE DUPLICADO EN BASE DE DATOS | Votante: $nombres $apellidos | Identificación: $identificacion | Causa: Este registro ya existe en la base de datos (posible error de validación previa)";
                } else {
                    $errores[] = "Línea $linea_num: ERROR DE BASE DE DATOS | Votante: $nombres $apellidos | Identificación: $identificacion | Detalles técnicos: $error_msg";
                }
            }
            
            $linea_num++;
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
