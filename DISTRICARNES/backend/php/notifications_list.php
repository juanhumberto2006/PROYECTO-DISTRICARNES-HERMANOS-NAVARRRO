<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

function format_order_event(array $row): array {
  $id = intval($row['id']);
  $status = strtoupper((string)$row['status']);
  $user = $row['user_name'] ?: 'Cliente';
  $created = $row['created_at'];
  $total = isset($row['total']) ? floatval($row['total']) : 0.0;

  $type = 'order';
  $title = '';
  $message = '';

  if ($status === 'PENDING') {
    $type = 'order';
    $title = "Nuevo pedido #$id";
    $message = "$user realizó un pedido";
  } else if ($status === 'PROCESSING') {
    $type = 'order';
    $title = "Pedido en proceso #$id";
    $message = "El pedido está siendo preparado/envíado";
  } else if ($status === 'COMPLETED') {
    $type = 'sale';
    $title = "Orden completada #$id";
    $message = "Venta registrada: $" . number_format($total, 2);
  } else if ($status === 'CANCELLED') {
    $type = 'order';
    $title = "Pedido cancelado #$id";
    $message = "El pedido fue cancelado";
  } else {
    $type = 'order';
    $title = "Actualización de pedido #$id";
    $message = "Estado: $status";
  }

  return [
    'type' => $type,
    'title' => $title,
    'message' => $message,
    'created_at' => $created,
    'link' => "../admin/admin_orders.html?orderId=$id"
  ];
}

function detect_stock_column(mysqli $db, string $table = 'producto'): ?string {
  if ($res = $db->query("DESCRIBE `$table`")) {
    $cols = [];
    while ($r = $res->fetch_assoc()) { $cols[] = $r['Field']; }
    $res->close();
    foreach (['stock','existencias','cantidad','qty'] as $c) { if (in_array($c, $cols, true)) return $c; }
  }
  return null;
}

function maybe_low_stock_alert(mysqli $db): ?array {
  $tbl = 'producto';
  $stockCol = detect_stock_column($db, $tbl);
  if (!$stockCol) return null;
  $sql = "SELECT COUNT(*) AS cnt FROM `$tbl` WHERE `$stockCol` < 5";
  if ($res = $db->query($sql)) {
    $row = $res->fetch_assoc();
    $res->close();
    $cnt = isset($row['cnt']) ? intval($row['cnt']) : 0;
    if ($cnt > 0) {
      return [
        'type' => 'inventory',
        'title' => 'Alerta de stock bajo',
        'message' => "$cnt productos con stock crítico (<5)",
        'created_at' => date('Y-m-d H:i:s'),
        'link' => '../admin/admin_inventory.html'
      ];
    }
  }
  return null;
}

$notifications = [];

// Últimos eventos de órdenes
$stmt = $conexion->prepare("SELECT id, user_name, status, total, created_at FROM orders ORDER BY created_at DESC LIMIT 20");
if ($stmt && $stmt->execute()) {
  $res = $stmt->get_result();
  while ($row = $res->fetch_assoc()) {
    $notifications[] = format_order_event($row);
  }
}
if ($stmt) { $stmt->close(); }

// Agregar alerta de stock bajo si aplica
$low = maybe_low_stock_alert($conexion);
if ($low) { array_unshift($notifications, $low); }

// Contar "no leídas" => últimas 60 minutos
$unread = 0;
$threshold = strtotime('-60 minutes');
foreach ($notifications as $n) {
  $ts = strtotime($n['created_at']);
  if ($ts !== false && $ts >= $threshold) { $unread++; }
}

echo json_encode(['ok' => true, 'notifications' => $notifications, 'unread_count' => $unread]);
exit;
?>