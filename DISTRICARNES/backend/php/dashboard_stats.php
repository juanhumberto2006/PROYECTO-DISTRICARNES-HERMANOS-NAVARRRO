<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

function safe_sum_today_sales(mysqli $db): float {
  // Sumar total de órdenes COMPLETED del día actual
  $sql = "SELECT SUM(total) AS suma FROM orders WHERE status = 'COMPLETED' AND DATE(created_at) = CURDATE()";
  if ($res = $db->query($sql)) {
    $row = $res->fetch_assoc();
    $res->close();
    return isset($row['suma']) ? floatval($row['suma']) : 0.0;
  }
  return 0.0;
}

function safe_orders_in_route(mysqli $db): int {
  // Mapeamos "en ruta" a PROCESSING por ahora
  $sql = "SELECT COUNT(*) AS cnt FROM orders WHERE status = 'PROCESSING'";
  if ($res = $db->query($sql)) {
    $row = $res->fetch_assoc();
    $res->close();
    return isset($row['cnt']) ? intval($row['cnt']) : 0;
  }
  return 0;
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

function safe_low_stock_count(mysqli $db): int {
  $tbl = 'producto';
  $stockCol = detect_stock_column($db, $tbl);
  if (!$stockCol) return 0;
  $sql = "SELECT COUNT(*) AS cnt FROM `$tbl` WHERE `$stockCol` < 10";
  if ($res = $db->query($sql)) {
    $row = $res->fetch_assoc();
    $res->close();
    return isset($row['cnt']) ? intval($row['cnt']) : 0;
  }
  return 0;
}

function safe_active_customers(mysqli $db): int {
  // Clientes con órdenes en los últimos 30 días
  $sql = "SELECT COUNT(DISTINCT user_email) AS cnt FROM orders WHERE user_email IS NOT NULL AND created_at >= (NOW() - INTERVAL 30 DAY)";
  if ($res = $db->query($sql)) {
    $row = $res->fetch_assoc();
    $res->close();
    return isset($row['cnt']) ? intval($row['cnt']) : 0;
  }
  return 0;
}

$stats = [
  'salesToday' => safe_sum_today_sales($conexion),
  'ordersInRoute' => safe_orders_in_route($conexion),
  'lowStockItems' => safe_low_stock_count($conexion),
  'activeCustomers' => safe_active_customers($conexion),
];

echo json_encode(['ok' => true, 'stats' => $stats]);
exit;
?>