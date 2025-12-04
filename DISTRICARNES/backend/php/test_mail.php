<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/smtp_mailer.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'error' => 'Método no permitido']);
  exit;
}

$placeholderPasses = ['tu_contrasena_de_aplicacion', 'APP_PASSWORD_AQUI', ''];
if (SMTP_USER === 'tu_correo@gmail.com' || in_array(SMTP_PASS, $placeholderPasses, true)) {
  echo json_encode(['ok' => false, 'error' => 'Configura SMTP con Contraseña de aplicación en backend/php/email_config.php.']);
  exit;
}

// Validar formato de Contraseña de aplicación (16 caracteres alfanuméricos)
if (SMTP_HOST === 'smtp.gmail.com' && !preg_match('/^[A-Za-z0-9]{16}$/', SMTP_PASS)) {
  echo json_encode(['ok' => false, 'error' => 'SMTP_PASS debe ser una Contraseña de aplicación de 16 caracteres alfanuméricos (sin espacios).']);
  exit;
}

$to = trim($_POST['to'] ?? '');
if ($to === '' || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
  echo json_encode(['ok' => false, 'error' => 'Correo destino inválido']);
  exit;
}

$subject = 'Prueba SMTP Districarnes';
$body = "Este es un mensaje de prueba enviado por SMTP.\n\nSi ves este correo, la configuración SMTP funciona.";

$res = smtp_send_mail(
  $to,
  $subject,
  $body,
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

echo json_encode(['ok' => $res['ok'], 'error' => $res['error'] ?? null]);
?>