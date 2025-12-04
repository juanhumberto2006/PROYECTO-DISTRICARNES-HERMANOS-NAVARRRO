Configurar SMTP (Gmail) en Districarnes
======================================

Requisitos previos
------------------
- Tener activada la "Verificación en dos pasos" en tu cuenta de Google.
- Generar una "Contraseña de aplicación" (16 caracteres) para Gmail.

Pasos para generar la Contraseña de aplicación
---------------------------------------------
1) Abre tu cuenta de Google > Seguridad.
2) Activa Verificación en dos pasos si aún no lo hiciste.
3) En "Contraseñas de aplicación", crea una nueva para "Correo" y dispositivo "Windows" (o "Otro").
4) Copia los 16 caracteres (sin espacios). Esta NO es tu contraseña normal.

Actualizar configuración en el proyecto
--------------------------------------
Edita el archivo `backend/php/email_config.php`:

- `SMTP_USER`: tu Gmail remitente (ej. tu_correo@gmail.com).
- `SMTP_PASS`: pega aquí la Contraseña de aplicación generada.
- Mantén: `SMTP_HOST = smtp.gmail.com`, `SMTP_PORT = 587`, `SMTP_SECURE = 'tls'`.

Probar el envío de correo
-------------------------
Envía un POST a `backend/php/test_mail.php` con el parámetro `to` (correo destino). Ejemplo en Postman/curl:

POST http://localhost/DISTRICARNES/backend/php/test_mail.php
Body: x-www-form-urlencoded
to=tu_correo@dominio.com

Respuesta esperada:
{"ok":true}

Si ves:
- {"ok":false, "error":"Configura SMTP..."} => Falta configurar `SMTP_PASS`.
- {"ok":false, "error":"Contraseña SMTP no aceptada"} => Revisa que pegaste bien los 16 caracteres y que 2FA está activo.

Recuperación de contraseña durante la configuración
--------------------------------------------------
Mientras configuras SMTP, el enlace de recuperación se guarda en `backend/php/reset_links.log`.
Busca la última línea y abre la URL `.../login/cambiar_contrasena.php?token=...` para restablecer la contraseña.