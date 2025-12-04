<?php
/**
 * Envío SMTP simple con autenticación y STARTTLS (compatible con Gmail)
 * Sin dependencias externas.
 */

function smtp_send_mail(string $to, string $subject, string $body, string $from, string $fromName, array $cfg, string $contentType = 'text/plain'): array {
  $host = $cfg['host'] ?? 'smtp.gmail.com';
  $port = (int)($cfg['port'] ?? 587);
  $secure = $cfg['secure'] ?? 'tls';
  $user = $cfg['user'] ?? '';
  $pass = $cfg['pass'] ?? '';

  $timeout = 30;
  $errno = 0; $errstr = '';
  $fp = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout, STREAM_CLIENT_CONNECT);
  if (!$fp) return ['ok' => false, 'error' => "Conexión fallida: {$errstr} ({$errno})"];
  stream_set_timeout($fp, $timeout);

  $readBlock = function() use ($fp) {
    $data = '';
    while (($line = fgets($fp, 1024)) !== false) {
      $data .= $line;
      if (preg_match('/^[0-9]{3} \S/', $line)) break; // línea final con código + espacio
      if (feof($fp)) break;
    }
    return $data;
  };

  $write = function(string $cmd) use ($fp) {
    fwrite($fp, $cmd . "\r\n");
  };

  // Banner
  $banner = $readBlock();
  if (!preg_match('/^220/', $banner)) { fclose($fp); return ['ok' => false, 'error' => 'Servidor SMTP no listo']; }

  // EHLO
  $write('EHLO districarnes.local');
  $ehloResp = $readBlock();
  if (!preg_match('/^250/', $ehloResp)) { fclose($fp); return ['ok' => false, 'error' => 'EHLO no aceptado']; }

  // STARTTLS si corresponde
  if ($secure === 'tls') {
    $write('STARTTLS');
    $tlsResp = $readBlock();
    if (!preg_match('/^220/', $tlsResp)) { fclose($fp); return ['ok' => false, 'error' => 'No se pudo iniciar STARTTLS']; }
    if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT)) { fclose($fp); return ['ok' => false, 'error' => 'Fallo al activar TLS']; }
    // EHLO de nuevo tras TLS
    $write('EHLO districarnes.local');
    $ehloResp = $readBlock();
    if (!preg_match('/^250/', $ehloResp)) { fclose($fp); return ['ok' => false, 'error' => 'EHLO tras TLS no aceptado']; }
  }

  // AUTH LOGIN
  $write('AUTH LOGIN');
  $auth1 = $readBlock();
  if (!preg_match('/^334/', $auth1)) { fclose($fp); return ['ok' => false, 'error' => 'AUTH LOGIN no aceptado']; }
  $write(base64_encode($user));
  $auth2 = $readBlock();
  if (!preg_match('/^334/', $auth2)) { fclose($fp); return ['ok' => false, 'error' => 'Usuario SMTP no aceptado']; }
  $write(base64_encode($pass));
  $auth3 = $readBlock();
  if (!preg_match('/^235/', $auth3)) { fclose($fp); return ['ok' => false, 'error' => 'Contraseña SMTP no aceptada']; }

  // MAIL FROM
  $write("MAIL FROM:<{$from}>");
  $mf = $readBlock();
  if (!preg_match('/^250/', $mf)) { fclose($fp); return ['ok' => false, 'error' => 'MAIL FROM rechazado']; }

  // RCPT TO
  $write("RCPT TO:<{$to}>");
  $rt = $readBlock();
  if (!preg_match('/^(250|251)/', $rt)) { fclose($fp); return ['ok' => false, 'error' => 'RCPT TO rechazado']; }

  // DATA
  $write('DATA');
  $dr = $readBlock();
  if (!preg_match('/^354/', $dr)) { fclose($fp); return ['ok' => false, 'error' => 'DATA no aceptado']; }

  // Encabezados
  $headers = '';
  $headers .= "From: {$fromName} <{$from}>\r\n";
  $headers .= "To: <{$to}>\r\n";
  $headers .= "Subject: " . encode_header($subject) . "\r\n";
  $headers .= "MIME-Version: 1.0\r\n";
  // Permitir HTML o texto según parámetro
  $ct = strtolower($contentType);
  if ($ct !== 'text/plain' && $ct !== 'text/html') { $ct = 'text/plain'; }
  $headers .= "Content-Type: {$ct}; charset=UTF-8\r\n";
  $headers .= "Content-Transfer-Encoding: 8bit\r\n";

  $data = $headers . "\r\n" . normalize_dots($body) . "\r\n."; // punto final
  fwrite($fp, $data . "\r\n");
  $sent = $readBlock();
  if (!preg_match('/^250/', $sent)) { fclose($fp); return ['ok' => false, 'error' => 'Envío rechazado']; }

  // QUIT
  $write('QUIT');
  $readBlock();
  fclose($fp);
  return ['ok' => true];
}

function normalize_dots(string $text): string {
  // Escapa líneas que comienzan con un punto según RFC5321
  return preg_replace('/\r?\n\./', "\r\n..", $text);
}

function encode_header(string $header): string {
  // Codifica UTF-8 en formato MIME-header si hay caracteres no ASCII
  return preg_match('/[\x80-\xFF]/', $header) ? '=?UTF-8?B?' . base64_encode($header) . '?=' : $header;
}

?>