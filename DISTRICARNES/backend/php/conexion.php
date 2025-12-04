<?php
// Configuración de la base de datos
$host = "localhost";
$username = "root";
$password = "";
$database = "districarnes_navarro";

// Crear la conexión a la base de datos
$conexion = new mysqli($host, $username, $password, $database);

// Verificar la conexión
if ($conexion->connect_error) {
    // Asegurarse de que la cabecera sea JSON en caso de error
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    // Devolver un error en formato JSON y terminar el script
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a la base de datos. Por favor, verifica las credenciales o el estado del servidor.',
        'error_details' => $conexion->connect_error // Opcional: para depuración
    ]);
    exit();
}

// Configuración de la codificación de caracteres para evitar problemas con caracteres especiales
$conexion->set_charset("utf8");
?>
