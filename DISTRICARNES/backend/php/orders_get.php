<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

// Permitir tanto GET como POST para flexibilidad
$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
$email = isset($_GET['email']) ? trim($_GET['email']) : null;
if(!$email && is_array($input)){ $email = isset($input['email']) ? trim($input['email']) : null; }

if(!$email){
  echo json_encode(['ok'=>false,'error'=>'email is required']);
  exit;
}

// Consultar órdenes del usuario
$stmt = $conexion->prepare("SELECT id, paypal_id, user_email, user_name, status, total, delivery_method, address_json, schedule_json, created_at FROM orders WHERE user_email = ? ORDER BY created_at DESC");
$stmt->bind_param('s', $email);
$ok = $stmt->execute();
if(!$ok){ echo json_encode(['ok'=>false,'error'=>$stmt->error]); exit; }
$res = $stmt->get_result();
$orders = [];
while($row = $res->fetch_assoc()){
  $row['address'] = $row['address_json'] ? json_decode($row['address_json'], true) : null;
  $row['schedule'] = $row['schedule_json'] ? json_decode($row['schedule_json'], true) : null;
  unset($row['address_json']);
  unset($row['schedule_json']);
  // Items de la orden
  $stmtI = $conexion->prepare("SELECT title, price, qty, image FROM order_items WHERE order_id = ?");
  $stmtI->bind_param('i', $row['id']);
  $stmtI->execute();
  $itemsRes = $stmtI->get_result();
  $items = [];
  while($it = $itemsRes->fetch_assoc()){ $items[] = $it; }
  $stmtI->close();
  $row['items'] = $items;
  $orders[] = $row;
}
$stmt->close();

echo json_encode(['ok'=>true, 'orders'=>$orders]);
exit;
?>