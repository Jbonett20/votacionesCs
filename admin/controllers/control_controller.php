<?php
/**
 * Control de Asistencia al Voto
 * Lista votantes y permite marcar/desmarcar si ya votó.
 */

require_once '../config/db.php';
require_once '../config/session.php';
require_once '../../vendor/autoload.php';

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

requerirRol([1, 2, 3]); // SuperAdmin, Admin, Líder

$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($action !== 'exportar_control') {
    header('Content-Type: application/json; charset=utf-8');
}

switch ($action) {
    case 'listar':
        listarControlVotantes();
        break;
    case 'marcar_chequeado':
        marcarChequeado();
        break;
    case 'exportar_control':
        exportarControl();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

function obtenerIdLiderDesdeSesion($usuario_id) {
    try {
        $idLider = DB::queryOneValue(
            "SELECT l.id_lider
             FROM lideres l
             INNER JOIN usuarios u ON u.identificacion = l.identificacion
             WHERE u.id_usuario = ?
             LIMIT 1",
            $usuario_id
        );

        if (!empty($idLider)) {
            return (int)$idLider;
        }
    } catch (Exception $e) {
        error_log('No se pudo resolver líder por identificación: ' . $e->getMessage());
    }

    $usuario_username = $_SESSION['usuario'] ?? $_SESSION['usuario_username'] ?? '';
    if (!empty($usuario_username)) {
        try {
            $idLider = DB::queryOneValue(
                "SELECT id_lider FROM lideres WHERE usuario = ? LIMIT 1",
                $usuario_username
            );
            if (!empty($idLider)) {
                return (int)$idLider;
            }
        } catch (Exception $e) {
            error_log('No se pudo resolver líder por usuario: ' . $e->getMessage());
        }
    }

    return null;
}

function obtenerCondicionAccesoVotantes($usuario_id, $usuario_rol, &$params, $alias = 'v') {
    if ($usuario_rol == 1) {
        return '1=1';
    }

    if ($usuario_rol == 2) {
        $params[] = $usuario_id;
        $params[] = $usuario_id;
        return "(l.id_usuario_creador = ? OR {$alias}.id_administrador_directo = ?)";
    }

    $idLider = obtenerIdLiderDesdeSesion($usuario_id);
    if (!$idLider) {
        return '0=1';
    }

    $params[] = $idLider;
    return "{$alias}.id_lider = ?";
}

function obtenerCondicionAccesoLideres($usuario_id, $usuario_rol, &$params, $alias = 'l') {
    if ($usuario_rol == 1) {
        return '1=1';
    }

    if ($usuario_rol == 2) {
        $params[] = $usuario_id;
        return "{$alias}.id_usuario_creador = ?";
    }

    $idLider = obtenerIdLiderDesdeSesion($usuario_id);
    if (!$idLider) {
        return '0=1';
    }

    $params[] = $idLider;
    return "{$alias}.id_lider = ?";
}

function listarControlVotantes() {
    try {
        $usuario_id = $_SESSION['usuario_id'];
        $usuario_rol = $_SESSION['usuario_rol'];

        $paramsVotantes = [];
        $condicionVotantes = obtenerCondicionAccesoVotantes($usuario_id, $usuario_rol, $paramsVotantes);

        $queryVotantes = "SELECT
                            v.id_votante AS id_registro,
                            v.id_votante,
                            'votante' AS tipo_registro,
                            0 AS es_lider_registro,
                            v.nombres,
                            v.apellidos,
                            v.identificacion,
                            v.telefono,
                            v.sexo,
                            v.mesa,
                            v.lugar_mesa,
                            COALESCE(v.chequeado, 1) AS chequeado,
                            t.nombre_tipo,
                            l.id_lider,
                            l.nombres AS lider_nombres,
                            l.apellidos AS lider_apellidos,
                            CONCAT(u_admin.nombres, ' ', u_admin.apellidos) AS admin_directo,
                            CONCAT(u_prop.nombres, ' ', u_prop.apellidos) AS admin_propietario
                         FROM votantes v
                         INNER JOIN tipos_identificacion t ON v.id_tipo_identificacion = t.id_tipo_identificacion
                         LEFT JOIN lideres l ON v.id_lider = l.id_lider
                         LEFT JOIN usuarios u_admin ON v.id_administrador_directo = u_admin.id_usuario
                         LEFT JOIN usuarios u_prop ON u_prop.id_usuario = COALESCE(v.id_administrador_directo, l.id_usuario_creador)
                         WHERE v.id_estado = 1
                           AND {$condicionVotantes}";

        $votantes = DB::queryAllRows($queryVotantes, ...$paramsVotantes);

        $paramsLideres = [];
        $condicionLideres = obtenerCondicionAccesoLideres($usuario_id, $usuario_rol, $paramsLideres);

        $queryLideres = "SELECT
                            l.id_lider AS id_registro,
                            l.id_lider AS id_votante,
                            'lider' AS tipo_registro,
                            1 AS es_lider_registro,
                            l.nombres,
                            l.apellidos,
                            l.identificacion,
                            l.telefono,
                            l.sexo,
                            NULL AS mesa,
                            NULL AS lugar_mesa,
                            COALESCE(l.chequeado, 1) AS chequeado,
                            t.nombre_tipo,
                            l.id_lider,
                            l.nombres AS lider_nombres,
                            l.apellidos AS lider_apellidos,
                            CONCAT(
                                'Registrado por ',
                                CASE
                                    WHEN u_prop.id_rol = 1 THEN 'SuperAdministrador'
                                    WHEN u_prop.id_rol = 2 THEN 'Administrador'
                                    ELSE 'Usuario'
                                END,
                                ': ',
                                COALESCE(CONCAT(u_prop.nombres, ' ', u_prop.apellidos), 'N/A')
                            ) AS admin_directo,
                            CONCAT(u_prop.nombres, ' ', u_prop.apellidos) AS admin_propietario
                         FROM lideres l
                         INNER JOIN tipos_identificacion t ON l.id_tipo_identificacion = t.id_tipo_identificacion
                         LEFT JOIN usuarios u_prop ON u_prop.id_usuario = l.id_usuario_creador
                         WHERE l.id_estado = 1
                           AND {$condicionLideres}";

        $lideres = DB::queryAllRows($queryLideres, ...$paramsLideres);

        if (!is_array($votantes)) {
            $votantes = [];
        }
        if (!is_array($lideres)) {
            $lideres = [];
        }

        $registros = array_merge($votantes, $lideres);
        usort($registros, function ($a, $b) {
            return (int)($b['id_registro'] ?? 0) <=> (int)($a['id_registro'] ?? 0);
        });

        echo json_encode(['success' => true, 'data' => $registros]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al listar control: ' . $e->getMessage()]);
    }
}

function marcarChequeado() {
    try {
        $id_registro = isset($_POST['id_votante']) ? (int)$_POST['id_votante'] : 0;
        $tipo_registro = trim(strtolower($_POST['tipo_registro'] ?? 'votante'));

        if (!in_array($tipo_registro, ['votante', 'lider'], true)) {
            echo json_encode(['success' => false, 'message' => 'Tipo de registro inválido']);
            return;
        }

        if ($id_registro <= 0) {
            echo json_encode(['success' => false, 'message' => 'ID de registro inválido']);
            return;
        }

        $usuario_id = $_SESSION['usuario_id'];
        $usuario_rol = $_SESSION['usuario_rol'];

        if ($tipo_registro === 'lider') {
            $params = [];
            $condicionAcceso = obtenerCondicionAccesoLideres($usuario_id, $usuario_rol, $params);

            $query = "SELECT l.id_lider, COALESCE(l.chequeado, 1) AS chequeado
                      FROM lideres l
                      WHERE l.id_lider = ?
                        AND l.id_estado = 1
                        AND {$condicionAcceso}
                      LIMIT 1";

            $registro = DB::queryFirstRow($query, $id_registro, ...$params);

            if (!$registro) {
                echo json_encode(['success' => false, 'message' => 'No tienes permisos para este líder o no existe']);
                return;
            }

            $chequeado_actual = (int)($registro['chequeado'] ?? 1);
            $nuevo_estado = ($chequeado_actual === 2) ? 1 : 2;

            DB::update('lideres', [
                'chequeado' => $nuevo_estado,
                'fecha_edicion' => date('Y-m-d H:i:s')
            ], 'id_lider = ?', $id_registro);

            $mensaje = $nuevo_estado === 2
                ? 'Líder marcado como: YA VOTÓ'
                : 'Marca removida. El líder queda como: SIN VOTAR';

            echo json_encode([
                'success' => true,
                'message' => $mensaje,
                'chequeado' => $nuevo_estado,
                'tipo_registro' => 'lider'
            ]);
            return;
        }

        $params = [];
        $condicionAcceso = obtenerCondicionAccesoVotantes($usuario_id, $usuario_rol, $params);

        $query = "SELECT v.id_votante, COALESCE(v.chequeado, 1) AS chequeado
                  FROM votantes v
                  LEFT JOIN lideres l ON v.id_lider = l.id_lider
                  WHERE v.id_votante = ?
                    AND v.id_estado = 1
                    AND {$condicionAcceso}
                  LIMIT 1";

        $votante = DB::queryFirstRow($query, $id_registro, ...$params);

        if (!$votante) {
            echo json_encode(['success' => false, 'message' => 'No tienes permisos para este votante o no existe']);
            return;
        }

        $chequeado_actual = (int)($votante['chequeado'] ?? 1);
        $nuevo_estado = ($chequeado_actual === 2) ? 1 : 2;

        DB::update('votantes', [
            'chequeado' => $nuevo_estado,
            'fecha_edicion' => date('Y-m-d H:i:s')
        ], 'id_votante = ?', $id_registro);

        $mensaje = $nuevo_estado === 2
            ? 'Votante marcado como: YA VOTÓ'
            : 'Marca removida. El votante queda como: SIN VOTAR';

        echo json_encode([
            'success' => true,
            'message' => $mensaje,
            'chequeado' => $nuevo_estado,
            'tipo_registro' => 'votante'
        ]);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Error al actualizar estado: ' . $e->getMessage()]);
    }
}

function exportarControl() {
    $usuario_id = $_SESSION['usuario_id'];
    $usuario_rol = $_SESSION['usuario_rol'];

    $paramsVotantes = [];
    $condicionVotantes = obtenerCondicionAccesoVotantes($usuario_id, $usuario_rol, $paramsVotantes);

    $queryVotantes = "SELECT
                        v.id_votante AS id_registro,
                        'VOTANTE' AS tipo_registro,
                        v.nombres,
                        v.apellidos,
                        v.identificacion,
                        t.nombre_tipo,
                        v.telefono,
                        v.sexo,
                        v.mesa,
                        v.lugar_mesa,
                        CONCAT(l.nombres, ' ', l.apellidos) AS lider_nombre,
                        CONCAT(u_admin.nombres, ' ', u_admin.apellidos) AS admin_directo,
                        CONCAT(u_prop.nombres, ' ', u_prop.apellidos) AS admin_propietario,
                        COALESCE(v.chequeado, 1) AS chequeado
                     FROM votantes v
                     INNER JOIN tipos_identificacion t ON v.id_tipo_identificacion = t.id_tipo_identificacion
                     LEFT JOIN lideres l ON v.id_lider = l.id_lider
                     LEFT JOIN usuarios u_admin ON v.id_administrador_directo = u_admin.id_usuario
                     LEFT JOIN usuarios u_prop ON u_prop.id_usuario = COALESCE(v.id_administrador_directo, l.id_usuario_creador)
                     WHERE v.id_estado = 1
                       AND {$condicionVotantes}";

    $votantes = DB::queryAllRows($queryVotantes, ...$paramsVotantes);

    $paramsLideres = [];
    $condicionLideres = obtenerCondicionAccesoLideres($usuario_id, $usuario_rol, $paramsLideres);

    $queryLideres = "SELECT
                        l.id_lider AS id_registro,
                        'LIDER' AS tipo_registro,
                        l.nombres,
                        l.apellidos,
                        l.identificacion,
                        t.nombre_tipo,
                        l.telefono,
                        l.sexo,
                        NULL AS mesa,
                        NULL AS lugar_mesa,
                        'Registro líder' AS lider_nombre,
                        CONCAT(
                            'Registrado por ',
                            CASE
                                WHEN u_prop.id_rol = 1 THEN 'SuperAdministrador'
                                WHEN u_prop.id_rol = 2 THEN 'Administrador'
                                ELSE 'Usuario'
                            END,
                            ': ',
                            COALESCE(CONCAT(u_prop.nombres, ' ', u_prop.apellidos), 'N/A')
                        ) AS admin_directo,
                        CONCAT(u_prop.nombres, ' ', u_prop.apellidos) AS admin_propietario,
                        COALESCE(l.chequeado, 1) AS chequeado
                     FROM lideres l
                     INNER JOIN tipos_identificacion t ON l.id_tipo_identificacion = t.id_tipo_identificacion
                     LEFT JOIN usuarios u_prop ON u_prop.id_usuario = l.id_usuario_creador
                     WHERE l.id_estado = 1
                       AND {$condicionLideres}";

    $lideres = DB::queryAllRows($queryLideres, ...$paramsLideres);

    if (!is_array($votantes)) {
        $votantes = [];
    }
    if (!is_array($lideres)) {
        $lideres = [];
    }

    $registros = array_merge($votantes, $lideres);
    usort($registros, function ($a, $b) {
        return (int)($b['id_registro'] ?? 0) <=> (int)($a['id_registro'] ?? 0);
    });

    while (ob_get_level()) {
        ob_end_clean();
    }

    $spreadsheet = new Spreadsheet();
    $sheet = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Control');

    $headers = [
        'ID', 'Tipo Registro', 'Nombres', 'Apellidos', 'Identificación', 'Tipo ID', 'Teléfono', 'Sexo',
        'Mesa', 'Lugar Mesa', 'Líder', 'Admin Directo', 'Administrador', 'Estado Voto'
    ];

    $sheet->fromArray($headers, null, 'A1');

    $filaExcel = 2;
    foreach ($registros as $registro) {
        $sheet->fromArray([
            $registro['id_registro'],
            $registro['tipo_registro'],
            $registro['nombres'],
            $registro['apellidos'],
            $registro['identificacion'],
            $registro['nombre_tipo'],
            $registro['telefono'] ?? '',
            $registro['sexo'] == 'M' ? 'Masculino' : ($registro['sexo'] == 'F' ? 'Femenino' : 'Otro'),
            $registro['mesa'] ?: '',
            $registro['lugar_mesa'] ?? '',
            $registro['lider_nombre'] ?: 'Sin líder',
            $registro['admin_directo'] ?: 'N/A',
            $registro['admin_propietario'] ?: 'N/A',
            ((int)($registro['chequeado'] ?? 1) === 2) ? 'YA VOTÓ' : 'SIN VOTAR'
        ], null, 'A' . $filaExcel);
        $filaExcel++;
    }

    $sheet->getStyle('A1:N1')->getFont()->setBold(true);

    foreach (range('A', 'N') as $columna) {
        $sheet->getColumnDimension($columna)->setAutoSize(true);
    }

    $filename = 'control_votantes_lideres_' . date('Y-m-d_His') . '.xlsx';

    header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Pragma: no-cache');
    header('Expires: 0');

    $writer = new Xlsx($spreadsheet);
    $writer->save('php://output');
    exit;
}
