<?php
// Configuración de PayPal (Sandbox por defecto)
// Define constantes para uso global (frontend y backend)
if (!defined('PAYPAL_CLIENT_ID')) {
  // Credenciales de Sandbox provistas por el usuario
  define('PAYPAL_CLIENT_ID', getenv('PAYPAL_CLIENT_ID') ?: 'AXmQDD-XaDYP0p91YTznhmVRRWm7a7_3jFUrBc0LS_MGAYJApVLcT_qbCuP1nRym-pKukzyd9AoW5zbT');
}
if (!defined('PAYPAL_SECRET')) {
  define('PAYPAL_SECRET', getenv('PAYPAL_SECRET') ?: 'EMeRSfEwJQ7HlMsGdUbCCIKKhi29bmMOh1cJmTX_3CrPjUICeqiUik1ofoXLVqEQej2c5Ygg3tlzsgHB');
}
if (!defined('PAYPAL_ENV')) {
  define('PAYPAL_ENV', getenv('PAYPAL_ENV') ?: 'sandbox'); // 'live' para producción
}
if (!defined('PAYPAL_CURRENCY')) {
  define('PAYPAL_CURRENCY', getenv('PAYPAL_CURRENCY') ?: 'USD');
}

$PAYPAL_CONFIG = [
  'client_id' => PAYPAL_CLIENT_ID,// Usa la constante definida anteriormente para mayor seguridad
  'secret'    => PAYPAL_SECRET, // Usa la constante definida anteriormente para mayor seguridad
  'env'       => PAYPAL_ENV, // 'live' para producción
];

$PAYPAL_BASE_URL = ($PAYPAL_CONFIG['env'] === 'live')
  ? 'https://api-m.paypal.com'
  : 'https://api-m.sandbox.paypal.com';

function paypal_get_access_token($config, $baseUrl){
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $baseUrl . '/v1/oauth2/token',
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => http_build_query(['grant_type' => 'client_credentials']),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [ 'Accept: application/json', 'Accept-Language: en_US' ],
    CURLOPT_USERPWD => $config['client_id'] . ':' . $config['secret'],
  ]);
  // Permitir pruebas locales cuando el certificado raíz no esté configurado
  $skipVerify = getenv('PAYPAL_SKIP_SSL_VERIFY') === '1';
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $skipVerify ? 0 : 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $skipVerify ? 0 : 2);
  $res = curl_exec($ch);
  if ($res === false){
    curl_close($ch);
    return null;
  }
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code >= 200 && $code < 300){
    $json = json_decode($res, true);
    return $json['access_token'] ?? null;
  }
  return null;
}

function json_response($data, $status = 200){
  http_response_code($status);
  header('Content-Type: application/json');
  echo json_encode($data);
  exit;
}

function read_json_input(){
  $raw = file_get_contents('php://input');
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}