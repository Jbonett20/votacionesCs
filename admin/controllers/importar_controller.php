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

switch ($action) {
    case 'importar_votantes':
        importarVotantes();
        break;
    case 'registrar_duplicados_importacion':
        registrarDuplicadosImportacion();
        break;
    default:
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
        $duplicados_detalle = [];
        
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
                
                $duplicados_detalle[] = [
                    'linea' => $linea_num,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'identificacion' => $identificacion,
                    'tipo' => $validacion['tipo'],
                    'nombre' => $validacion['nombre'],
                    'detalles' => $detalles_existente
                ];
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
            'duplicados_detalle' => $duplicados_detalle,
            'errores' => $errores
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al procesar archivo: ' . $e->getMessage()]);
    }
}

/**
 * Guardar duplicados detectados en importacion
 */
function registrarDuplicadosImportacion() {
    try {
        $identificaciones_raw = $_POST['identificaciones'] ?? '[]';
        $id_lider_intento = $_POST['id_lider_intento'] ?? '';

        if (is_array($identificaciones_raw)) {
            $identificaciones = $identificaciones_raw;
        } else {
            $identificaciones = json_decode($identificaciones_raw, true);
        }

        if (empty($identificaciones) || !is_array($identificaciones)) {
            echo json_encode(['success' => false, 'message' => 'No hay identificaciones para guardar']);
            return;
        }

        if (empty($id_lider_intento)) {
            echo json_encode(['success' => false, 'message' => 'Debe seleccionar quien intento registrar']);
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $usuario_info = DB::queryFirstRow(
            "SELECT CONCAT(nombres, ' ', apellidos) as nombre_completo FROM usuarios WHERE id_usuario = ?",
            $usuario_id
        );
        $nombre_usuario_intento = $usuario_info ? $usuario_info['nombre_completo'] : ($_SESSION['usuario'] ?? 'Usuario');

        if (!empty($id_lider_intento) && $id_lider_intento !== 'actual' && $id_lider_intento !== 'yo') {
            $lider = DB::queryFirstRow(
                "SELECT CONCAT(nombres, ' ', apellidos) as nombre FROM lideres WHERE id_lider = ?",
                $id_lider_intento
            );
            if ($lider) {
                $nombre_usuario_intento .= ', Lider: ' . $lider['nombre'];
            }
        } else {
            $nombre_usuario_intento .= ' (Registro directo)';
        }

        $guardados = 0;
        $no_encontrados = [];
        $fecha_intento = date('Y-m-d H:i:s');

        foreach ($identificaciones as $identificacion) {
            $identificacion = trim($identificacion);
            if ($identificacion === '') {
                continue;
            }

            $validacion = LiderModel::identificacionExiste($identificacion);
            if (!$validacion['existe']) {
                $no_encontrados[] = $identificacion;
                continue;
            }

            $detalles_existente = '';
            $nombres = '';
            $apellidos = '';
            $telefono = null;
            $mesa = 0;
            $lugar_mesa = null;
            $id_departamento = null;
            $id_municipio = null;

            if ($validacion['tipo'] === 'votante') {
                $votante = DB::queryFirstRow(
                    "SELECT v.*, 
                            l.nombres as lider_nombres, l.apellidos as lider_apellidos,
                            CONCAT(u.nombres, ' ', u.apellidos) as admin_directo
                     FROM votantes v
                     LEFT JOIN lideres l ON v.id_lider = l.id_lider
                     LEFT JOIN usuarios u ON v.id_administrador_directo = u.id_usuario
                     WHERE v.identificacion = ?
                     LIMIT 1",
                    $identificacion
                );

                if (!$votante) {
                    $no_encontrados[] = $identificacion;
                    continue;
                }

                $nombres = $votante['nombres'];
                $apellidos = $votante['apellidos'];
                $telefono = $votante['telefono'] ?? null;
                $mesa = !empty($votante['mesa']) ? intval($votante['mesa']) : 0;
                $lugar_mesa = $votante['lugar_mesa'] ?? null;
                $id_departamento = !empty($votante['id_departamento']) ? intval($votante['id_departamento']) : null;
                $id_municipio = !empty($votante['id_municipio']) ? intval($votante['id_municipio']) : null;

                if (!empty($votante['lider_nombres'])) {
                    $detalles_existente = 'Pertenece al lider: ' . trim($votante['lider_nombres'] . ' ' . $votante['lider_apellidos']);
                } elseif (!empty($votante['admin_directo'])) {
                    $detalles_existente = 'Registrado por: ' . $votante['admin_directo'];
                }
            } elseif ($validacion['tipo'] === 'líder') {
                $lider = DB::queryFirstRow(
                    "SELECT l.*, CONCAT(u.nombres, ' ', u.apellidos) as administrador
                     FROM lideres l
                     LEFT JOIN usuarios u ON l.id_usuario_creador = u.id_usuario
                     WHERE l.identificacion = ?
                     LIMIT 1",
                    $identificacion
                );

                if (!$lider) {
                    $no_encontrados[] = $identificacion;
                    continue;
                }

                $nombres = $lider['nombres'];
                $apellidos = $lider['apellidos'];
                $telefono = $lider['telefono'] ?? null;
                $id_departamento = !empty($lider['id_departamento']) ? intval($lider['id_departamento']) : null;
                $id_municipio = !empty($lider['id_municipio']) ? intval($lider['id_municipio']) : null;

                if (!empty($lider['administrador'])) {
                    $detalles_existente = 'Creado por: ' . $lider['administrador'];
                }
            } elseif ($validacion['tipo'] === 'usuario') {
                $usuario = DB::queryFirstRow(
                    "SELECT u.*, r.nombre_rol
                     FROM usuarios u
                     INNER JOIN roles r ON u.id_rol = r.id_rol
                     WHERE u.identificacion = ?
                     LIMIT 1",
                    $identificacion
                );

                if (!$usuario) {
                    $no_encontrados[] = $identificacion;
                    continue;
                }

                $nombres = $usuario['nombres'];
                $apellidos = $usuario['apellidos'];
                $telefono = $usuario['telefono'] ?? null;

                if (!empty($usuario['nombre_rol'])) {
                    $detalles_existente = 'Rol: ' . $usuario['nombre_rol'];
                }
            }

            $duplicado_existente = DB::queryFirstRow(
                "SELECT id_duplicado, nombre_usuario_intento FROM votantes_duplicados WHERE identificacion = ?",
                $identificacion
            );

            if ($duplicado_existente) {
                $nombres_acumulados = $duplicado_existente['nombre_usuario_intento'] . ' | ' . $nombre_usuario_intento;
                DB::update(
                    'votantes_duplicados',
                    [
                        'nombre_usuario_intento' => $nombres_acumulados,
                        'fecha_intento' => $fecha_intento
                    ],
                    'id_duplicado = ?',
                    $duplicado_existente['id_duplicado']
                );
            } else {
                DB::insert('votantes_duplicados', [
                    'nombres' => trim($nombres),
                    'apellidos' => trim($apellidos),
                    'identificacion' => $identificacion,
                    'telefono' => $telefono ?: null,
                    'mesa' => $mesa,
                    'lugar_mesa' => $lugar_mesa,
                    'tipo_existente' => $validacion['tipo'],
                    'nombre_existente' => $validacion['nombre'],
                    'detalles_existente' => $detalles_existente,
                    'metodo_intento' => 'excel',
                    'identificacion_lider_intento' => null,
                    'id_usuario_intento' => $usuario_id,
                    'nombre_usuario_intento' => $nombre_usuario_intento,
                    'id_departamento' => $id_departamento,
                    'id_municipio' => $id_municipio,
                    'fecha_intento' => $fecha_intento
                ]);
            }

            $guardados++;
        }

        $mensaje = "$guardados duplicados guardados.";
        if (!empty($no_encontrados)) {
            $mensaje .= ' No encontrados: ' . implode(', ', $no_encontrados);
        }

        echo json_encode(['success' => true, 'message' => $mensaje]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al guardar duplicados: ' . $e->getMessage()]);
    }
}
?>
