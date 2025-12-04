<?php
require_once __DIR__ . '/factus_config.php';

function factus_http_post(string $url, array $headers, $body, bool $isJson = true): array {
  $ch = curl_init();
  curl_setopt_array($ch, [
    CURLOPT_URL => $url,
    CURLOPT_POST => true,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_POSTFIELDS => $isJson ? json_encode($body) : $body,
  ]);
  // Evitar problemas de certificados en dev local
  $skipVerify = getenv('FACTUS_SKIP_SSL_VERIFY') === '1';
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $skipVerify ? 0 : 1);
  curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, $skipVerify ? 0 : 2);
  $res = curl_exec($ch);
  if ($res === false) { $err = curl_error($ch); curl_close($ch); return ['ok'=>false, 'error'=>'curl_error', 'message'=>$err, 'status'=>0, 'raw'=>null, 'json'=>null]; }
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  $json = null; 
  if ($res){ $decoded = json_decode($res, true); if (is_array($decoded)) { $json = $decoded; } }
  return [ 'ok' => ($code>=200 && $code<300), 'status'=>$code, 'raw'=>$res, 'json'=>$json ];
}

function factus_get_access_token(): ?string {
  $url = rtrim(FACTUS_BASE_URL, '/') . FACTUS_OAUTH_TOKEN_PATH;
  // Flujo típico OAuth2 Client Credentials
  $body = http_build_query([
    'grant_type' => 'client_credentials',
    'client_id' => FACTUS_CLIENT_ID,
    'client_secret' => FACTUS_CLIENT_SECRET,
  ]);
  $res = factus_http_post($url, [ 'Content-Type: application/x-www-form-urlencoded' ], $body, false);
  if ($res['ok'] && isset($res['json']['access_token'])){ return $res['json']['access_token']; }
  // Algunos proveedores exigen username/password
  if (!$res['ok']){
    $res = factus_http_post($url, [ 'Content-Type: application/x-www-form-urlencoded' ], http_build_query([
      'grant_type' => 'password',
      'username'   => FACTUS_USERNAME,
      'password'   => FACTUS_PASSWORD,
      'client_id'  => FACTUS_CLIENT_ID,
      'client_secret' => FACTUS_CLIENT_SECRET,
    ]), false);
    if ($res['ok'] && isset($res['json']['access_token'])){ return $res['json']['access_token']; }
  }
  return null;
}

function factus_emit_invoice(array $order, array $items): array {
  $token = factus_get_access_token();
  if (!$token) { return ['ok'=>false, 'error'=>'auth_failed', 'message'=>'No se pudo obtener token']; }

  $url = rtrim(FACTUS_BASE_URL, '/') . FACTUS_INVOICE_CREATE_PATH;
  $currency = $order['currency'] ?? FACTUS_CURRENCY_DEFAULT;

  // Construir payload genérico (ajustar conforme a documentación Factus)
  $lines = [];
  foreach ($items as $it){
    $lines[] = [
      'description' => $it['title'] ?? ($it['name'] ?? 'Producto'),
      'quantity'    => intval($it['qty'] ?? ($it['quantity'] ?? 1)),
      'unit_price'  => floatval($it['price'] ?? 0),
      'total'       => floatval($it['price'] ?? 0) * intval($it['qty'] ?? ($it['quantity'] ?? 1)),
    ];
  }

  $payload = [
    'seller' => [
      'name'    => FACTUS_COMPANY_NAME,
      'nit'     => FACTUS_COMPANY_NIT,
      'email'   => FACTUS_COMPANY_EMAIL,
      'phone'   => FACTUS_COMPANY_PHONE,
      'address' => FACTUS_COMPANY_ADDRESS,
    ],
    'buyer' => [
      'name'  => $order['user_name'] ?? '',
      'email' => $order['user_email'] ?? '',
    ],
    'currency'    => $currency,
    'issue_date'  => $order['created_at'] ?? date('Y-m-d\TH:i:sP'),
    'payment_means' => 'IMMEDIATE',
    'items'       => $lines,
    'totals'      => [
      'subtotal' => floatval($order['subtotal'] ?? 0),
      'tax'      => floatval($order['tax'] ?? 0),
      'total'    => floatval($order['total'] ?? 0),
    ],
    'notes'       => $order['notes'] ?? null,
  ];

  $res = factus_http_post($url, [ 'Content-Type: application/json', 'Authorization: Bearer ' . $token ], $payload, true);
  $out = [ 'ok'=>$res['ok'], 'status'=>$res['status'], 'raw'=>$res['raw'], 'json'=>$res['json'] ];
  if ($res['ok'] && is_array($res['json'])){
    $j = $res['json'];
    // Intentar mapear posibles campos de respuesta
    $out['invoice_id']   = $j['id'] ?? ($j['uuid'] ?? ($j['invoiceId'] ?? null));
    $out['invoice_num']  = $j['number'] ?? ($j['consecutive'] ?? null);
    $out['invoice_status']= $j['status'] ?? null;
    $out['pdf_url']      = $j['pdf_url'] ?? ($j['pdf'] ?? null);
  }
  return $out;
}

?>