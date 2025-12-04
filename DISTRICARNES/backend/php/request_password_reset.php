<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/smtp_mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

$email = trim($_POST['email'] ?? '');
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['success' => false, 'message' => 'Correo electrónico inválido']);
  exit;
}

try {
  $conexion->set_charset('utf8mb4');
  // Verificar usuario
  $stmt = $conexion->prepare('SELECT id_usuario, nombres_completos FROM usuario WHERE correo_electronico = ? LIMIT 1');
  $stmt->bind_param('s', $email);
  $stmt->execute();
  $res = $stmt->get_result();
  if (!$res || $res->num_rows === 0) {
    $stmt->close();
    echo json_encode(['success' => false, 'message' => 'El correo no está registrado en la base de datos.']);
    exit;
  }
  $user = $res->fetch_assoc();
  $stmt->close();

  // Crear tabla de tokens si no existe
  $conexion->query("CREATE TABLE IF NOT EXISTS password_resets (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX(user_id),
    UNIQUE KEY token_hash_idx (token_hash)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  // Generar token
  $token = bin2hex(random_bytes(32));
  $tokenHash = hash('sha256', $token);
  $expires = date('Y-m-d H:i:s', time() + 60 * 30); // 30 minutos

  // Guardar token
  $ins = $conexion->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?,?,?)');
  $ins->bind_param('iss', $user['id_usuario'], $tokenHash, $expires);
  $ok = $ins->execute();
  $ins->close();
  if (!$ok) {
    echo json_encode(['success' => false, 'message' => 'No se pudo generar el enlace.']);
    exit;
  }

  // Construir enlace
  $resetUrl = (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] : 'http://localhost') . '/DISTRICARNES/login/cambiar_contrasena.php?token=' . $token;

  // Intentar enviar correo (requiere configurar SMTP/"mail" en php.ini)
  $subject = 'Recuperación de contraseña - Districarnes';
  $message = "Hola " . ($user['nombres_completos'] ?? '') . ",\n\n" .
             "Recibimos una solicitud para restablecer tu contraseña.\n" .
             "Usa el siguiente enlace para crear una nueva contraseña (válido por 30 minutos):\n" .
             $resetUrl . "\n\n" .
             "Si no solicitaste esto, puedes ignorar este mensaje.";

  // Guardar enlace en log para pruebas locales
  file_put_contents(__DIR__ . '/reset_links.log', date('c') . ' ' . $email . ' => ' . $resetUrl . "\n", FILE_APPEND);

  // Validar configuración SMTP (evita el error de contraseña no aceptada por valores de ejemplo)
  $placeholderPasses = ['tu_contrasena_de_aplicacion', 'APP_PASSWORD_AQUI', ''];
  if (SMTP_USER === 'tu_correo@gmail.com' || in_array(SMTP_PASS, $placeholderPasses, true)) {
    echo json_encode([
      'success' => false,
      'message' => 'Configura SMTP con una "Contraseña de aplicación" en backend/php/email_config.php. Mientras tanto, puedes usar este enlace directo para restablecer tu contraseña.',
      'reset_url' => $resetUrl
    ]);
    exit;
  }

  // Validar formato de Contraseña de aplicación de Google (16 caracteres alfanuméricos, sin espacios)
  if (SMTP_HOST === 'smtp.gmail.com' && !preg_match('/^[A-Za-z0-9]{16}$/', SMTP_PASS)) {
    echo json_encode([
      'success' => false,
      'message' => 'Tu contraseña SMTP debe ser una "Contraseña de aplicación" de 16 caracteres alfanuméricos (sin espacios). Activa 2FA y genera una nueva en Google > Seguridad.',
      'reset_url' => $resetUrl
    ]);
    exit;
  }

  // Enviar por SMTP (Gmail)
  $send = smtp_send_mail(
    $email,
    $subject,
    $message,
    MAIL_FROM,
    MAIL_FROM_NAME,
    [
      'host' => SMTP_HOST,
      'port' => SMTP_PORT,
      'secure' => SMTP_SECURE,
      'user' => SMTP_USER,
      'pass' => SMTP_PASS,
    ]
  );

  if (!$send['ok']) {
    error_log('SMTP error: ' . ($send['error'] ?? 'unknown'));
    echo json_encode(['success' => false, 'message' => 'No se pudo enviar el correo: ' . ($send['error'] ?? 'Error desconocido')]);
  } else {
    echo json_encode(['success' => true, 'message' => 'Te enviamos el enlace para restablecer la contraseña. Revisa tu correo.']);
  }
} catch (Throwable $e) {
  error_log('request_password_reset.php error: ' . $e->getMessage());
  echo json_encode(['success' => false, 'message' => 'Error en el servidor']);
}

$conexion->close();
?>