<?php
// ============================================
// conexion.php - Configuración de base de datos
// ============================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Cambia por tu usuario
define('DB_PASS', '');            // Cambia por tu contraseña
define('DB_NAME', 'banco_conversion');

function conectar() {
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    if ($conn->connect_error) {
        die("Error de conexión: " . $conn->connect_error);
    }
    $conn->set_charset("utf8mb4");
    return $conn;
}
?>
