<?php
/**
 * MeekroDB - Simple MySQL Library
 * Versión simplificada para el proyecto
 */

class DB {
    public static $host = 'localhost';
    public static $user = 'root';
    public static $password = '';
    public static $dbName = '';
    public static $encoding = 'utf8mb4';
    public static $error_handler = null;
    public static $nonsql_error_handler = null;
    
    private static $instance = null;
    private static $connection = null;
    
    /**
     * Obtener conexión a la base de datos
     */
    private static function getConnection() {
        if (self::$connection === null) {
            try {
                self::$connection = new PDO(
                    "mysql:host=" . self::$host . ";dbname=" . self::$dbName . ";charset=" . self::$encoding,
                    self::$user,
                    self::$password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false
                    ]
                );
                // Forzar UTF-8 en la conexión
                self::$connection->exec("SET NAMES utf8mb4");
                self::$connection->exec("SET CHARACTER SET utf8mb4");
            } catch (PDOException $e) {
                self::handleError(['error' => $e->getMessage(), 'query' => 'Connection']);
                throw $e;
            }
        }
        return self::$connection;
    }
    
    /**
     * Ejecutar una consulta
     */
    public static function query($query, ...$params) {
        try {
            $conn = self::getConnection();
            $stmt = $conn->prepare($query);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            self::handleError(['error' => $e->getMessage(), 'query' => $query]);
            throw $e;
        }
    }
    
    /**
     * Obtener todas las filas
     */
    public static function queryAllRows($query, ...$params) {
        $stmt = self::query($query, ...$params);
        return $stmt->fetchAll();
    }
    
    /**
     * Obtener una sola fila
     */
    public static function queryFirstRow($query, ...$params) {
        $stmt = self::query($query, ...$params);
        return $stmt->fetch();
    }
    
    /**
     * Obtener un solo valor
     */
    public static function queryOneValue($query, ...$params) {
        $stmt = self::query($query, ...$params);
        $row = $stmt->fetch(PDO::FETCH_NUM);
        return $row ? $row[0] : null;
    }
    
    /**
     * Obtener una sola columna
     */
    public static function queryOneColumn($column, $query, ...$params) {
        $stmt = self::query($query, ...$params);
        return $stmt->fetchAll(PDO::FETCH_COLUMN, 0);
    }
    
    /**
     * Insertar un registro
     */
    public static function insert($table, $data) {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($values), '?');
        
        $query = "INSERT INTO `$table` (`" . implode('`, `', $columns) . "`) VALUES (" . implode(', ', $placeholders) . ")";
        self::query($query, ...$values);
        
        return self::getConnection()->lastInsertId();
    }
    
    /**
     * Actualizar registros
     */
    public static function update($table, $data, $where = '', ...$whereParams) {
        $sets = [];
        $values = [];
        
        foreach ($data as $column => $value) {
            $sets[] = "`$column` = ?";
            $values[] = $value;
        }
        
        $query = "UPDATE `$table` SET " . implode(', ', $sets);
        
        if ($where) {
            $query .= " WHERE $where";
            $values = array_merge($values, $whereParams);
        }
        
        $stmt = self::query($query, ...$values);
        return $stmt->rowCount();
    }
    
    /**
     * Eliminar registros
     */
    public static function delete($table, $where = '', ...$whereParams) {
        $query = "DELETE FROM `$table`";
        
        if ($where) {
            $query .= " WHERE $where";
        }
        
        $stmt = self::query($query, ...$whereParams);
        return $stmt->rowCount();
    }
    
    /**
     * Contar registros
     */
    public static function count($table, $where = '', ...$whereParams) {
        $query = "SELECT COUNT(*) FROM `$table`";
        
        if ($where) {
            $query .= " WHERE $where";
        }
        
        return self::queryOneValue($query, ...$whereParams);
    }
    
    /**
     * Manejar errores
     */
    private static function handleError($params) {
        if (self::$error_handler && is_callable(self::$error_handler)) {
            call_user_func(self::$error_handler, $params);
        }
    }
}
?>
