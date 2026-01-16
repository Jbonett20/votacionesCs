<?php
/**
 * Gestión de Sesiones
 * Validación y control de acceso
 */

// Iniciar sesión si no está iniciada
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Verificar si hay sesión activa
 * @return bool
 */
function validarSesion() {
    return isset($_SESSION['usuario_id']) && isset($_SESSION['usuario_rol']);
}

/**
 * Verificar si el usuario tiene un rol específico
 * @param array $roles_permitidos Array de IDs de roles permitidos
 * @return bool
 */
function validarRol($roles_permitidos = []) {
    if (!validarSesion()) {
        return false;
    }
    
    if (empty($roles_permitidos)) {
        return true;
    }
    
    return in_array($_SESSION['usuario_rol'], $roles_permitidos);
}

/**
 * Redirigir si no hay sesión
 * @param string $url URL de redirección
 */
function requerirSesion($url = '../../index.php') {
    if (!validarSesion()) {
        header("Location: $url");
        exit();
    }
}

/**
 * Redirigir si no tiene el rol permitido
 * @param array $roles_permitidos Array de IDs de roles permitidos
 * @param string $url URL de redirección
 */
function requerirRol($roles_permitidos, $url = 'dashboard.php') {
    if (!validarRol($roles_permitidos)) {
        header("Location: $url");
        exit();
    }
}

/**
 * Cerrar sesión
 */
function cerrarSesion() {
    session_start();
    session_unset();
    session_destroy();
    header("Location: ../../index.php");
    exit();
}

/**
 * Obtener nombre del rol actual
 * @return string
 */
function obtenerNombreRol() {
    if (!validarSesion()) {
        return '';
    }
    
    $roles = [
        1 => 'SuperAdministrador',
        2 => 'Administrador',
        3 => 'Líder',
        4 => 'Votante'
    ];
    
    return $roles[$_SESSION['usuario_rol']] ?? '';
}

/**
 * Verificar si es SuperAdministrador
 * @return bool
 */
function esSuperAdmin() {
    return validarSesion() && $_SESSION['usuario_rol'] == 1;
}

/**
 * Verificar si es Administrador o superior
 * @return bool
 */
function esAdmin() {
    return validarSesion() && in_array($_SESSION['usuario_rol'], [1, 2]);
}

/**
 * Verificar si es Líder o superior
 * @return bool
 */
function esLider() {
    return validarSesion() && in_array($_SESSION['usuario_rol'], [1, 2, 3]);
}
?>
