<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/sales_utils.php';

$raw = file_get_contents('php://input');
$input = json_decode($raw, true);
if (!is_array($input)) { echo json_encode(['ok'=>false,'error'=>'Invalid JSON']); exit; }

$orderId = isset($input['order_id']) ? intval($input['order_id']) : 0;
$status  = isset($input['status']) ? strtolower(trim($input['status'])) : '';

if ($orderId <= 0 || $status === '') {
  echo json_encode(['ok'=>false,'error'=>'order_id and status are required']);
  exit;
}

// Normalizar y validar estado
$map = ['pending'=>'PENDING','processing'=>'PROCESSING','completed'=>'COMPLETED','cancelled'=>'CANCELLED'];
$statusNorm = $map[$status] ?? strtoupper($status);
if (!in_array($statusNorm, ['PENDING','PROCESSING','COMPLETED','CANCELLED'], true)) {
  echo json_encode(['ok'=>false,'error'=>'invalid status']);
  exit;
}

$stmt = $conexion->prepare("UPDATE orders SET status = ? WHERE id = ?");
$stmt->bind_param('si', $statusNorm, $orderId);
$ok = $stmt->execute();
$stmt->close();

$sale = null;
if ($ok && $statusNorm === 'COMPLETED') {
  // Registrar venta automáticamente cuando la orden queda COMPLETED
  $sale = record_sale_for_order($conexion, $orderId);
}

echo json_encode(['ok'=>$ok, 'sale'=>$sale]);
exit;
?>