<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/paypal_config.php';

$client = defined('PAYPAL_CLIENT_ID') ? PAYPAL_CLIENT_ID : ($PAYPAL_CONFIG['client_id'] ?? (getenv('PAYPAL_CLIENT_ID') ?: ''));
$currency = defined('PAYPAL_CURRENCY') ? PAYPAL_CURRENCY : ($PAYPAL_CONFIG['currency'] ?? (getenv('PAYPAL_CURRENCY') ?: 'USD'));
$components = 'buttons';
$funding = 'card,venmo,paylater';

echo json_encode([
  'client_id' => $client,
  'currency' => $currency,
  'components' => $components,
  'enable_funding' => $funding
]);
exit;