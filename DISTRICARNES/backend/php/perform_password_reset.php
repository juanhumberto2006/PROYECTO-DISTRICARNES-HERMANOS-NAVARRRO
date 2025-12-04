<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

$token = trim($_POST['token'] ?? '');
$password = trim($_POST['password'] ?? '');
if ($token === '' || $password === '') {
  echo json_encode(['success' => false, 'message' => 'Datos incompletos']);
  exit;
}
if (strlen($password) < 8) {
  echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres']);
  exit;
}

try {
  $conexion->set_charset('utf8mb4');
  $tokenHash = hash('sha256', $token);
  // Buscar token válido
  $stmt = $conexion->prepare('SELECT id, user_id, expires_at, used FROM password_resets WHERE token_hash = ? LIMIT 1');
  $stmt->bind_param('s', $tokenHash);
  $stmt->execute();
  $res = $stmt->get_result();
  if (!$res || $res->num_rows !== 1) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'Token inválido']);
    exit;
  }
  $row = $res->fetch_assoc();
  $stmt->close();

  // Validar expiración y uso
  if (strtotime($row['expires_at']) < time()) {
    echo json_encode(['success' => false, 'message' => 'El enlace ha expirado']);
    exit;
  }
  if ((int)$row['used'] === 1) {
    echo json_encode(['success' => false, 'message' => 'Este enlace ya fue usado']);
    exit;
  }

  $userId = (int)$row['user_id'];

  // Actualizar contraseña del usuario
  $hash = password_hash($password, PASSWORD_BCRYPT);
  $up = $conexion->prepare('UPDATE usuario SET contrasena = ? WHERE id_usuario = ?');
  $up->bind_param('si', $hash, $userId);
  $ok = $up->execute();
  $up->close();
  if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'No se pudo actualizar la contraseña']);
    exit;
  }

  // Marcar token como usado
  $mark = $conexion->prepare('UPDATE password_resets SET used = 1 WHERE id = ?');
  $mark->bind_param('i', $row['id']);
  $mark->execute();
  $mark->close();

  echo json_encode(['success' => true, 'message' => 'Contraseña actualizada correctamente', 'redirect_url' => '../login/login.html']);
} catch (Throwable $e) {
  error_log('perform_password_reset.php error: ' . $e->getMessage());
  echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}

$conexion->close();
?>