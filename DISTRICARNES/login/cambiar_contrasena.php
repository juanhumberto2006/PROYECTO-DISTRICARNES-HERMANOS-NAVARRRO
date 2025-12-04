<?php
require_once __DIR__ . '/../backend/php/conexion.php';

$token = isset($_GET['token']) ? trim($_GET['token']) : '';
$valid = false;
$errorMessage = '';

if ($token !== '') {
  $tokenHash = hash('sha256', $token);
  $stmt = $conexion->prepare('SELECT user_id, expires_at, used FROM password_resets WHERE token_hash = ? LIMIT 1');
  if ($stmt) {
    $stmt->bind_param('s', $tokenHash);
    $stmt->execute();
    $res = $stmt->get_result();
    if ($res && $res->num_rows === 1) {
      $row = $res->fetch_assoc();
      $expired = (strtotime($row['expires_at']) < time());
      $valid = !$expired && (int)$row['used'] === 0;
      if (!$valid) {
        $errorMessage = $expired ? 'El enlace ha expirado. Solicita uno nuevo.' : 'Este enlace ya fue usado.';
      }
    } else {
      $errorMessage = 'Token inválido.';
    }
    $stmt->close();
  } else {
    $errorMessage = 'No se pudo validar el token.';
  }
} else {
  $errorMessage = 'Falta el token de recuperación.';
}

?>
<!DOCTYPE html>
<html lang="es">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Cambiar contraseña | Districarnes</title>
  <link rel="icon" type="image/png" href="../assets/icon/LOGO-DISTRICARNES.png" />
  <link rel="stylesheet" href="./css/login.css" />
  <style>
    /* Tema negro/rojo alineado con restablecer_contrasena.html */
    :root {
      --blue: #0a74b7;
      --blue-dark: #085f95;
    }

    body {
      background: #000;
      color: #111;
      font-family: Segoe UI, system-ui, -apple-system, Roboto, Arial, sans-serif;
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0;
      flex-direction: column;
    }

    .login-header {
      width: 100vw;
      /* ocupar todo el ancho de la ventana */
      max-width: none;
      /* sin límite de ancho */
      padding: 20px 24px;
      background: linear-gradient(90deg, #5b0000, #ff0000);
      color: #fff;
      box-shadow: 0 8px 28px rgba(0, 0, 0, .55);
      border-bottom: 1px solid rgba(255, 255, 255, 0.15);
      box-sizing: border-box;
      /* incluir padding en el ancho */
    }

    .login-header h1 {
      margin: 0;
      font-size: 1.6rem;
    }

    .login-header .lead {
      margin: 6px 0 0;
      font-size: 0.95rem;
      opacity: 0.9;
    }

    .container {
      width: 100%;
      max-width: 640px;
      background: #ffffff00;
      border-radius: 6px;
      box-shadow: 0 8px 28px rgba(0, 0, 0, .55);
      padding: 28px;
      margin-top: 16px;
    }

    .title {
      text-align: center;
      color: #ff0000;
      font-size: 2rem;
      font-weight: 700;
      margin: 0 0 18px;
    }

    label {
      display: block;
      margin-bottom: 8px;
      color: #ffffff;
      font-weight: 600;
    }

    .input-wrap {
      background: #ffffff93;
      border-radius: 4px;
      border: 1px solid #ff0000;
    }

    .input-wrap input {
      width: 100%;
      border: none;
      outline: none;
      padding: 12px 14px;
      font-size: 1rem;
    }

    .btn {
      width: 100%;
      margin-top: 14px;
      background: var(--blue);
      color: #fff;
      border: none;
      padding: 12px;
      border-radius: 4px;
      cursor: pointer;
      font-weight: 700;
    }

    .btn:hover {
      background: var(--blue-dark);
    }

    .alert {
      display: none;
      margin-top: 14px;
      padding: 12px 14px;
      border-radius: 6px;
      font-weight: 600;
    }

    .alert.success {
      display: block;
      background: #d1fae5;
      color: #065f46;
      border: 1px solid #10b981;
    }

    .alert.error {
      display: block;
      background: #fee2e2;
      color: #7f1d1d;
      border: 1px solid #ef4444;
    }

    .link-back {
      display: block;
      text-align: center;
      margin-top: 18px;
      color: #0a74b7;
      text-decoration: none;
      font-weight: 600;
    }

    .link-back:hover {
      text-decoration: underline;
    }
  </style>
</head>

<body>
  <div class="login-header">

    <p class="lead">Ingresa tu nueva contraseña y confírmala para completar el proceso.</p>
  </div>
  <div class="container" style="display:flex; flex-direction:column; align-items:center; justify-content:center; min-height:100vh; margin-top:0;">
    <h1 class="title"><span style="color:white">Crear nueva</span> Contraseña</h1>
    <?php if (!$valid): ?>
      <div class="alert error" role="alert"><?php echo htmlspecialchars($errorMessage); ?></div>
      <a class="link-back" href="./restablecer_contrasena.html">Solicitar nuevo enlace</a>
    <?php else: ?>
      <form id="changeForm">
        <input type="hidden" id="token" name="token" value="<?php echo htmlspecialchars($token); ?>" />
        <label for="password">Nueva contraseña</label>
        <div class="input-wrap">
          <input type="password" id="password" name="password" style="background-color:#000;color:#fff;" placeholder="Tu nueva contraseña" required />
        </div>
        <label for="confirm">Confirmar contraseña</label>
        <div class="input-wrap">
          <input type="password" id="confirm" name="confirm" style="background-color:#000;color:#fff;" placeholder="Repite la contraseña" required />
        </div>
        <button style="background-color: red" class="btn" type="submit">Cambiar contraseña</button>
        <div id="alert" class="alert" role="alert"></div>
      </form>
      <a class="link-back" href="./login.html">Volver al inicio de sesión</a>
    <?php endif; ?>
  </div>

  <script>
    const form = document.getElementById('changeForm');
    if (form) {
      const alertBox = document.getElementById('alert');
      form.addEventListener('submit', async (e) => {
        e.preventDefault();
        alertBox.className = 'alert';
        alertBox.style.display = 'none';
        const token = document.getElementById('token').value;
        const password = document.getElementById('password').value;
        const confirm = document.getElementById('confirm').value;
        if (password.length < 8) {
          alertBox.textContent = 'La contraseña debe tener al menos 8 caracteres.';
          alertBox.classList.add('error');
          alertBox.style.display = 'block';
          return;
        }
        if (password !== confirm) {
          alertBox.textContent = 'Las contraseñas no coinciden.';
          alertBox.classList.add('error');
          alertBox.style.display = 'block';
          return;
        }
        try {
          const resp = await fetch('../backend/php/perform_password_reset.php', {
            method: 'POST',
            headers: {
              'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: new URLSearchParams({
              token,
              password
            })
          });
          const data = await resp.json();
          alertBox.textContent = data.message || 'Listo.';
          alertBox.classList.add(data.success ? 'success' : 'error');
          alertBox.style.display = 'block';
          if (data.success && data.redirect_url) {
            setTimeout(() => {
              window.location.href = data.redirect_url;
            }, 2000);
          }
        } catch (err) {
          alertBox.textContent = 'Error inesperado, intenta de nuevo.';
          alertBox.classList.add('error');
          alertBox.style.display = 'block';
        }
      });
    }
  </script>
</body>

</html>