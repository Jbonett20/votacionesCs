<?php
/**
 * Controlador de Ubicaciones
 * Gestiona las consultas de departamentos y municipios
 */

require_once '../config/db.php';
require_once '../config/session.php';

header('Content-Type: application/json; charset=utf-8');

// Validar sesión
requerirRol([1, 2, 3]); // SuperAdmin, Admin y Líder

$action = $_POST['action'] ?? $_GET['action'] ?? '';

switch ($action) {
    case 'obtener_departamentos':
        obtenerDepartamentos();
        break;
    case 'obtener_municipios':
        obtenerMunicipios();
        break;
    default:
        echo json_encode(['success' => false, 'message' => 'Acción no válida']);
}

/**
 * Obtener todos los departamentos
 */
function obtenerDepartamentos() {
    try {
        $departamentos = DB::queryAllRows(
            "SELECT id_departamento, codigo_dane, nombre 
             FROM departamentos 
             ORDER BY nombre ASC"
        );
        
        echo json_encode([
            'success' => true,
            'data' => $departamentos
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener departamentos: ' . $e->getMessage()
        ]);
    }
}

/**
 * Obtener municipios de un departamento
 */
function obtenerMunicipios() {
    try {
        $id_departamento = $_POST['id_departamento'] ?? $_GET['id_departamento'] ?? 0;
        
        if (!$id_departamento) {
            echo json_encode([
                'success' => false,
                'message' => 'Debe especificar el departamento'
            ]);
            return;
        }
        
        $municipios = DB::queryAllRows(
            "SELECT id_municipio, codigo_dane, nombre 
             FROM municipios 
             WHERE id_departamento = ?
             ORDER BY nombre ASC",
            $id_departamento
        );
        
        echo json_encode([
            'success' => true,
            'data' => $municipios
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => 'Error al obtener municipios: ' . $e->getMessage()
        ]);
    }
}
