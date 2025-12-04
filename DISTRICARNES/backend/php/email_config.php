<?php
// Configuración SMTP para envío de correos (Gmail)
// IMPORTANTE: rellena estas constantes con tus datos reales.
// Para Gmail usa una "Contraseña de aplicación" (no tu contraseña normal).

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_SECURE', 'tls'); // tls para puerto 587
define('SMTP_USER', 'districarnesnavarro@gmail.com'); // CORREO GMAIL REMITENTE
// IMPORTANTE: NO usar tu contraseña normal de Google aquí.
// Debe ser una "Contraseña de aplicación" (16 caracteres) generada en tu cuenta Google.
// Usa tu Contraseña de aplicación de Google (16 caracteres, sin espacios)
define('SMTP_PASS', 'vtgkrvsiglhkdias');

define('MAIL_FROM', SMTP_USER);
define('MAIL_FROM_NAME', 'DISTRICARNES HERMANOS NAVARRO');

?>