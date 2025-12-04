<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/factus_config.php';
require_once __DIR__ . '/factus_client.php';
require_once __DIR__ . '/sales_utils.php';

// Lee JSON
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

// Datos básicos
$paypalId = $input['paypal_id'] ?? null;
$status   = $input['status'] ?? 'PENDING';
$total    = isset($input['total']) ? floatval($input['total']) : 0.0;
$delivery = $input['delivery'] ?? 'domicilio';
$address  = $input['address'] ?? [];
$schedule = $input['schedule'] ?? [];
$items    = $input['items'] ?? [];
$user     = $input['user'] ?? [];

// Crear tablas si no existen
$sqlOrders = "CREATE TABLE IF NOT EXISTS orders (
  id INT AUTO_INCREMENT PRIMARY KEY,
  paypal_id VARCHAR(64) NULL,
  user_email VARCHAR(255) NULL,
  user_name VARCHAR(255) NULL,
  status VARCHAR(32) NOT NULL,
  total DECIMAL(12,2) NOT NULL DEFAULT 0,
  delivery_method VARCHAR(32) NOT NULL,
  address_json TEXT NULL,
  schedule_json TEXT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conexion->query($sqlOrders);

// Extender tabla orders con campos de Factus si faltan (evitar duplicados)
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

$sqlItems = "CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  title VARCHAR(255) NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  qty INT NOT NULL DEFAULT 1,
  image TEXT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
$conexion->query($sqlItems);

// Insertar orden
$stmt = $conexion->prepare("INSERT INTO orders (paypal_id, user_email, user_name, status, total, delivery_method, address_json, schedule_json) VALUES (?,?,?,?,?,?,?,?)");
$addrJson = json_encode($address);
$schJson  = json_encode($schedule);
$userEmail = $user['email'] ?? null;
$userName  = $user['name'] ?? null;
$stmt->bind_param('sssdsiss', $paypalId, $userEmail, $userName, $status, $total, $delivery, $addrJson, $schJson);
$ok = $stmt->execute();
if(!$ok){ echo json_encode(['ok'=>false,'error'=>$stmt->error]); exit; }
$orderId = $stmt->insert_id;
$stmt->close();

// Insertar items
if (is_array($items)){
  $stmtItem = $conexion->prepare("INSERT INTO order_items (order_id, title, price, qty, image) VALUES (?,?,?,?,?)");
  foreach($items as $it){
    $title = isset($it['title']) ? $it['title'] : (isset($it['name']) ? $it['name'] : 'Producto');
    $price = isset($it['price']) ? floatval($it['price']) : 0.0;
    $qty   = isset($it['qty']) ? intval($it['qty']) : (isset($it['quantity']) ? intval($it['quantity']) : 1);
    $image = isset($it['image']) ? $it['image'] : (isset($it['img']) ? $it['img'] : null);
    $stmtItem->bind_param('isdis', $orderId, $title, $price, $qty, $image);
    $stmtItem->execute();
  }
  $stmtItem->close();
}

// Emisión Factus si la orden está completada
try {
  if (strtoupper($status) === 'COMPLETED') {
    // Registrar la venta en la tabla 'sales' (idempotente)
    record_sale_for_order($conexion, isset($orderId) ? intval($orderId) : 0);
    // Calcular subtotal y tax
    $subtotal = 0.0; foreach($items as $it){ $subtotal += floatval($it['price'] ?? 0) * intval($it['qty'] ?? ($it['quantity'] ?? 1)); }
    $tax = 0.0; // Ajustar si manejas IVA
    $currency = defined('PAYPAL_CURRENCY') ? PAYPAL_CURRENCY : FACTUS_CURRENCY_DEFAULT;
    $orderData = [
      'id' => $orderId,
      'user_email' => $userEmail,
      'user_name'  => $userName,
      'status'     => $status,
      'total'      => $total,
      'subtotal'   => $subtotal,
      'tax'        => $tax,
      'currency'   => $currency,
      'created_at' => date('Y-m-d H:i:s'),
      'notes'      => is_array($address) ? ($address['notes'] ?? null) : null,
    ];
    $emit = factus_emit_invoice($orderData, $items);
    if ($emit['ok']){
      $fid = $emit['invoice_id'] ?? null;
      $fnum = $emit['invoice_num'] ?? null;
      $fstatus = $emit['invoice_status'] ?? null;
      $fpdf = $emit['pdf_url'] ?? null;
      $stmtUp = $conexion->prepare("UPDATE orders SET factus_invoice_id = ?, factus_number = ?, factus_status = ?, factus_pdf_url = ? WHERE id = ?");
      $stmtUp->bind_param('ssssi', $fid, $fnum, $fstatus, $fpdf, $orderId);
      $stmtUp->execute();
      $stmtUp->close();
    }
  }
} catch (Throwable $e) { /* no bloquear la respuesta */ }

echo json_encode(['ok'=>true, 'order_id'=>$orderId]);
exit;