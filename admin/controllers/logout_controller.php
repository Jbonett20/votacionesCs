<?php
/**
 * Controlador de Logout
 * Cierra la sesión del usuario
 */

session_start();
session_unset();
session_destroy();

// Eliminar cookie de recordar
if (isset($_COOKIE['remember_token'])) {
    setcookie('remember_token', '', time() - 3600, '/');
}

header('Location: ../../index.php');
exit();
?>
