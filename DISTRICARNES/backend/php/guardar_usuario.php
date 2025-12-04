<?php
// Respuestas JSON
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

// Solo permitir POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

// Sanitizar entradas
$nombre    = trim($_POST['nombre'] ?? '');
$cedula    = trim($_POST['cedula'] ?? '');
$direccion = trim($_POST['direccion'] ?? '');
$celular   = trim($_POST['celular'] ?? '');
$correo    = trim($_POST['email'] ?? '');
$clave     = trim($_POST['contrasena'] ?? '');

// Validaciones básicas
if (!$nombre || !$cedula || !$direccion || !$celular || !$correo || !$clave) {
  echo json_encode(['success' => false, 'message' => 'Todos los campos son obligatorios']);
  exit;
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['success' => false, 'message' => 'Correo electrónico inválido']);
  exit;
}

// Asegurar charset
$conexion->set_charset('utf8mb4');

// Crear tabla `usuario` si no existe (alineada con tu esquema)
$createSql = "CREATE TABLE IF NOT EXISTS `usuario` (
  `id_usuario` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  `nombres_completos` VARCHAR(255) NOT NULL,
  `cedula` VARCHAR(50) NOT NULL,
  `direccion` VARCHAR(255) NOT NULL,
  `celular` VARCHAR(50) NOT NULL,
  `correo_electronico` VARCHAR(255) NOT NULL,
  `contrasena` VARCHAR(255) NOT NULL,
  `rol` VARCHAR(50) NOT NULL DEFAULT 'trabajo',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uniq_email` (`correo_electronico`),
  UNIQUE KEY `uniq_cedula` (`cedula`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
$conexion->query($createSql);

// Comprobar duplicados por email
$stmt = $conexion->prepare('SELECT id_usuario FROM usuario WHERE correo_electronico = ? LIMIT 1');
if (!$stmt) { echo json_encode(['success' => false, 'message' => 'Error de servidor (prep email)']); exit; }
$stmt->bind_param('s', $correo);
$stmt->execute();
$res = $stmt->get_result();
if ($res && $res->num_rows > 0) {
  $stmt->close();
  echo json_encode(['success' => false, 'message' => 'El correo ya está registrado']);
  exit;
}
$stmt->close();

// Comprobar duplicados por cédula
$stmt2 = $conexion->prepare('SELECT id_usuario FROM usuario WHERE cedula = ? LIMIT 1');
if (!$stmt2) { echo json_encode(['success' => false, 'message' => 'Error de servidor (prep cedula)']); exit; }
$stmt2->bind_param('s', $cedula);
$stmt2->execute();
$res2 = $stmt2->get_result();
if ($res2 && $res2->num_rows > 0) {
  $stmt2->close();
  echo json_encode(['success' => false, 'message' => 'La cédula ya está registrada']);
  exit;
}
$stmt2->close();

// Hashear contraseña
$hash = password_hash($clave, PASSWORD_BCRYPT);

// Insertar en tabla `usuario` con rol explícito 'trabajo'
$insert = $conexion->prepare('INSERT INTO usuario (nombres_completos, cedula, direccion, celular, correo_electronico, contrasena, rol) VALUES (?,?,?,?,?,?,?)');
if (!$insert) { echo json_encode(['success' => false, 'message' => 'Error de servidor (prep insert)']); exit; }
$rol = 'trabajo';
$insert->bind_param('sssssss', $nombre, $cedula, $direccion, $celular, $correo, $hash, $rol);
$ok = $insert->execute();
$insert->close();

if ($ok) {
  echo json_encode(['success' => true, 'message' => 'Registro exitoso']);
} else {
  $msg = $conexion->error ? ('Error en BD: ' . $conexion->error) : 'No se pudo registrar';
  echo json_encode(['success' => false, 'message' => $msg]);
}

$conexion->close();
?>
