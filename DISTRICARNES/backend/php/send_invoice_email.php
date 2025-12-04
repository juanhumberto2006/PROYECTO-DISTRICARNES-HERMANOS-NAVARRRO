<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/email_config.php';
require_once __DIR__ . '/smtp_mailer.php';
require_once __DIR__ . '/paypal_config.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
$orderId = isset($input['order_id']) ? intval($input['order_id']) : 0;
$toEmail = isset($input['to']) ? trim($input['to']) : null;
if($orderId <= 0){ echo json_encode(['ok'=>false,'error'=>'order_id is required']); exit; }

// Asegurar columnas opcionales de Factus existen para evitar errores y duplicados
function getColumns(mysqli $db, string $table): array {
  $cols = [];
  if ($res = $db->query("DESCRIBE `$table`")) {
    while ($row = $res->fetch_assoc()) { $cols[] = $row['Field']; }
    $res->close();
  }
  return $cols;
}

function ensureFactusColumns(mysqli $db): void {
  $cols = getColumns($db, 'orders');
  $alterParts = [];
  if (!in_array('factus_invoice_id', $cols, true)) { $alterParts[] = 'ADD COLUMN factus_invoice_id VARCHAR(128) NULL'; }
  if (!in_array('factus_number', $cols, true)) { $alterParts[] = 'ADD COLUMN factus_number VARCHAR(128) NULL'; }
  if (!in_array('factus_status', $cols, true)) { $alterParts[] = 'ADD COLUMN factus_status VARCHAR(32) NULL'; }
  if (!in_array('factus_pdf_url', $cols, true)) { $alterParts[] = 'ADD COLUMN factus_pdf_url TEXT NULL'; }
  if (!empty($alterParts)) {
    $db->query('ALTER TABLE orders ' . implode(', ', $alterParts));
  }
}

ensureFactusColumns($conexion);

// Cargar orden
$stmt = $conexion->prepare("SELECT id, paypal_id, user_email, user_name, status, total, delivery_method, address_json, schedule_json, created_at, factus_invoice_id, factus_number, factus_status, factus_pdf_url FROM orders WHERE id = ?");
$stmt->bind_param('i', $orderId);
$stmt->execute();
$res = $stmt->get_result();
$order = $res->fetch_assoc();
$stmt->close();
if(!$order){ echo json_encode(['ok'=>false,'error'=>'Order not found']); exit; }
if(!$toEmail){ $toEmail = $order['user_email']; }
if(!$toEmail){ echo json_encode(['ok'=>false,'error'=>'Recipient email is required']); exit; }

// Items
$stmtI = $conexion->prepare("SELECT title, price, qty FROM order_items WHERE order_id = ?");
$stmtI->bind_param('i', $orderId);
$stmtI->execute();
$itemsRes = $stmtI->get_result();
$items = [];
while($it = $itemsRes->fetch_assoc()){ $items[] = $it; }
$stmtI->close();

$address = $order['address_json'] ? json_decode($order['address_json'], true) : [];
$schedule = $order['schedule_json'] ? json_decode($order['schedule_json'], true) : [];

// Construir factura HTML simple y profesional
$companyName = 'DistriCarnes Hermanos Navarro';
$companyEmail = MAIL_FROM;
$companyPhone = '+57 300 000 0000';
$companyAddress = 'Calle Principal #123, Cartagena';
$companyNit = 'NIT 900000000-0';

// Moneda
$currency = defined('PAYPAL_CURRENCY') ? PAYPAL_CURRENCY : 'USD';

// Logo en base64 para embed en correo
$root = dirname(__DIR__); // .../backend
$logoPath = $root . '/assets/icon/LOGO-DISTRICARNES.png';
$logoData = '';
if (file_exists($logoPath)) {
  $logoData = 'data:image/png;base64,' . base64_encode(file_get_contents($logoPath));
}

$itemsHtml = '';
$subtotal = 0.0;
foreach($items as $it){
  $line = floatval($it['price']) * intval($it['qty']);
  $subtotal += $line;
  $itemsHtml .= '<tr>'
    . '<td style="padding:8px;border:1px solid #ddd;">' . htmlspecialchars($it['title'] ?: 'Producto') . '</td>'
    . '<td style="padding:8px;border:1px solid #ddd;text-align:right;">$' . number_format(floatval($it['price']), 2) . '</td>'
    . '<td style="padding:8px;border:1px solid #ddd;text-align:center;">' . intval($it['qty']) . '</td>'
    . '<td style="padding:8px;border:1px solid #ddd;text-align:right;">$' . number_format($line, 2) . '</td>'
    . '</tr>';
}
$taxRate = 0.0; // Ajusta si manejas IVA
$tax = $subtotal * $taxRate;
$total = floatval($order['total']);
if($total <= 0){ $total = $subtotal + $tax; }

$addrText = '';
if(is_array($address)){
  $addrText = ($address['street'] ?? '') . ', ' . ($address['city'] ?? '') . ', ' . ($address['dept'] ?? '');
}

// Código único de factura (determinístico por orden)
$invoiceCode = 'FAC-' . date('Ymd', strtotime($order['created_at'])) . '-' . strtoupper(base_convert($orderId, 10, 36));

$html = '<!DOCTYPE html><html lang="es"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
  . '<title>Factura ' . htmlspecialchars($invoiceCode) . ' • DistriCarnes</title>'
  . '<style>body{font-family:Arial,Helvetica,sans-serif;color:#222;margin:16px} .header{display:flex;gap:12px;align-items:center} .header img{height:56px} .company{line-height:1.4} .meta{color:#666} table{width:100%;border-collapse:collapse;margin-top:8px} th,td{padding:10px;border:1px solid #e5e5e5} th{background:#fafafa;text-align:left} .totals td{padding:6px 0} .footer{margin-top:18px;font-size:12px;color:#666}</style></head><body>'
  . '<div class="header">'
    . ($logoData ? ('<img src="' . $logoData . '" alt="DistriCarnes"/>') : '')
    . '<div class="company">'
      . '<h2 style="margin:0">' . htmlspecialchars($companyName) . '</h2>'
      . '<div>' . htmlspecialchars($companyNit) . '</div>'
      . '<div>' . htmlspecialchars($companyAddress) . '</div>'
      . '<div>Tel: ' . htmlspecialchars($companyPhone) . ' • Email: ' . htmlspecialchars($companyEmail) . '</div>'
      . '<div class="meta">Moneda: ' . htmlspecialchars($currency) . '</div>'
    . '</div>'
  . '</div>'
  . '<h3 style="margin-top:10px;">Factura ' . htmlspecialchars($invoiceCode) . '</h3>'
  . '<p><strong>Cliente:</strong> ' . htmlspecialchars($order['user_name'] ?: '') . ' (' . htmlspecialchars($toEmail) . ')</p>'
  . '<p class="meta">Fecha: ' . htmlspecialchars(date('Y-m-d H:i', strtotime($order['created_at']))) . ' • Pago: PayPal' . (!empty($order['paypal_id']) ? (' • Transacción ' . htmlspecialchars($order['paypal_id'])) : '') . '</p>'
  . (!empty($order['factus_number']) || !empty($order['factus_invoice_id']) ? ('<p class="meta">Factura electrónica Factus: <strong>' . htmlspecialchars($order['factus_number'] ?: $order['factus_invoice_id']) . '</strong>' . (!empty($order['factus_pdf_url']) ? (' • <a href="' . htmlspecialchars($order['factus_pdf_url']) . '" target="_blank">Descargar PDF</a>') : '') . '</p>') : '')
  . '<table><thead><tr><th>Producto</th><th style="text-align:right;">Precio (' . htmlspecialchars($currency) . ')</th><th style="text-align:center;">Cant.</th><th style="text-align:right;">Subtotal</th></tr></thead><tbody>' . $itemsHtml . '</tbody></table>'
  . '<table class="totals"><tr><td style="text-align:right;">Subtotal: $' . number_format($subtotal,2) . '</td></tr><tr><td style="text-align:right;">Impuestos: $' . number_format($tax,2) . '</td></tr><tr><td style="text-align:right;"><strong>Total: $' . number_format($total,2) . '</strong></td></tr></table>'
  . '<p style="margin-top:14px;color:#555;">Método de entrega: ' . htmlspecialchars($order['delivery_method']) . '</p>'
  . '<div class="footer"><p>Gracias por tu compra. Conserva esta factura para tus registros.</p><p>Este documento corresponde a una factura de venta emitida por ' . htmlspecialchars($companyName) . '. Ante cualquier inquietud contáctanos: ' . htmlspecialchars($companyEmail) . '.</p><p>Dirección: ' . htmlspecialchars($companyAddress) . ' • Tel: ' . htmlspecialchars($companyPhone) . '.</p></div>'
  . '</body></html>';

$subject = 'Factura de compra ' . $invoiceCode . ' - ' . $companyName;
$cfg = [ 'host'=>SMTP_HOST, 'port'=>SMTP_PORT, 'secure'=>SMTP_SECURE, 'user'=>SMTP_USER, 'pass'=>SMTP_PASS ];
$send = smtp_send_mail($toEmail, $subject, $html, MAIL_FROM, MAIL_FROM_NAME, $cfg, 'text/html');
if(!$send['ok']){ echo json_encode(['ok'=>false,'error'=>$send['error'] ?? 'send failed']); exit; }

echo json_encode(['ok'=>true]);
exit;
?>