<?php
require_once __DIR__ . '/paypal_config.php';

$input = read_json_input();
$orderID = isset($input['orderID']) ? trim($input['orderID']) : '';
if ($orderID === ''){
  json_response(['error' => 'orderID is required'], 400);
}

$token = paypal_get_access_token($PAYPAL_CONFIG, $PAYPAL_BASE_URL);
if (!$token){ json_response(['error' => 'Unable to authenticate with PayPal'], 500); }

$ch = curl_init();
curl_setopt_array($ch, [
  CURLOPT_URL => $PAYPAL_BASE_URL . '/v2/checkout/orders/' . urlencode($orderID) . '/capture',
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    'Content-Type: application/json',
    'Authorization: Bearer ' . $token,
  ],
]);
// SSL verify fallback para entornos locales sin cacerts
$skipVerify = getenv('PAYPAL_SKIP_SSL_VERIFY') === '1';
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $skipVerify ? 0 : 1);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $skipVerify ? 0 : 2);

$res = curl_exec($ch);
if ($res === false){
  $err = curl_error($ch);
  curl_close($ch);
  json_response(['error' => 'Curl error: ' . $err], 500);
}
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);
$json = json_decode($res, true);

if ($code >= 200 && $code < 300){
  json_response(['status' => $json['status'] ?? null, 'raw' => $json]);
} else {
  json_response(['error' => 'PayPal API error', 'status_code' => $code, 'raw' => $json], $code);
}