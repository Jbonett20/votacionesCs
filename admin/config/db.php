<?php
/**
 * Configuración de Base de Datos
 * Utilizando MeekroDB para consultas seguras
 */

// Incluir MeekroDB
require_once __DIR__ . '/../../vendor/meekrodb/db.class.php';

// Configuración de la base de datos
DB::$host = 'localhost';
DB::$user = 'root';
DB::$password = '';
DB::$dbName = 'bd_votaciones';
DB::$encoding = 'utf8mb4';
DB::$error_handler = 'sql_error_handler';
DB::$nonsql_error_handler = 'nonsql_error_handler';

/**
 * Manejador de errores SQL
 */
function sql_error_handler($params) {
    error_log("SQL Error: " . $params['error']);
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<div style='background: #f8d7da; color: #721c24; padding: 15px; margin: 10px; border-radius: 5px;'>";
        echo "<strong>SQL Error:</strong> " . htmlspecialchars($params['error']) . "<br>";
        echo "<strong>Query:</strong> " . htmlspecialchars($params['query']);
        echo "</div>";
    }
}

/**
 * Manejador de errores no SQL
 */
function nonsql_error_handler($params) {
    error_log("Non-SQL Error: " . $params['error']);
    if (defined('DEBUG_MODE') && DEBUG_MODE) {
        echo "<div style='background: #fff3cd; color: #856404; padding: 15px; margin: 10px; border-radius: 5px;'>";
        echo "<strong>Error:</strong> " . htmlspecialchars($params['error']);
        echo "</div>";
    }
}

// Definir modo debug (cambiar a false en producción)
define('DEBUG_MODE', true);

// Verificar conexión
try {
    DB::query("SELECT 1");
} catch (Exception $e) {
    die("Error de conexión a la base de datos. Por favor, verifica la configuración.");
}
?>
