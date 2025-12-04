<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

// Lista todas las órdenes para el administrador
// Opcional: filtros básicos por estado y rango de fechas
$conexion->query("CREATE TABLE IF NOT EXISTS orders (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$conexion->query("CREATE TABLE IF NOT EXISTS order_items (
  id INT AUTO_INCREMENT PRIMARY KEY,
  order_id INT NOT NULL,
  title VARCHAR(255) NULL,
  price DECIMAL(12,2) NOT NULL DEFAULT 0,
  qty INT NOT NULL DEFAULT 1,
  image TEXT NULL,
  FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$status = isset($_GET['status']) ? trim($_GET['status']) : '';
$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) ? trim($_GET['to']) : '';

$query = "SELECT id, paypal_id, user_email, user_name, status, total, delivery_method, address_json, schedule_json, created_at FROM orders";
$where = [];
$params = [];
$types = '';
if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; $types .= 's'; }
if ($from !== '') { $where[] = 'created_at >= ?'; $params[] = $from; $types .= 's'; }
if ($to !== '') { $where[] = 'created_at <= ?'; $params[] = $to; $types .= 's'; }
if (!empty($where)) { $query .= ' WHERE ' . implode(' AND ', $where); }
$query .= ' ORDER BY created_at DESC';

$stmt = $conexion->prepare($query);
if(!$stmt){ echo json_encode(['ok'=>false,'error'=>$conexion->error]); exit; }
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$ok = $stmt->execute();
if(!$ok){ echo json_encode(['ok'=>false,'error'=>$stmt->error]); exit; }
$res = $stmt->get_result();
$orders = [];
while($row = $res->fetch_assoc()){
  $addr = $row['address_json'] ? json_decode($row['address_json'], true) : null;
  $sched = $row['schedule_json'] ? json_decode($row['schedule_json'], true) : null;
  unset($row['address_json']);
  unset($row['schedule_json']);

  // Items
  $stmtI = $conexion->prepare("SELECT title, price, qty, image FROM order_items WHERE order_id = ?");
  $stmtI->bind_param('i', $row['id']);
  $stmtI->execute();
  $itemsRes = $stmtI->get_result();
  $items = [];
  while($it = $itemsRes->fetch_assoc()){ $items[] = $it; }
  $stmtI->close();

  $orders[] = [
    'id' => intval($row['id']),
    'paypal_id' => $row['paypal_id'],
    'customer_email' => $row['user_email'],
    'customer_name' => $row['user_name'],
    'status' => $row['status'],
    'total' => floatval($row['total']),
    'delivery_method' => $row['delivery_method'],
    'created_at' => $row['created_at'],
    'address' => $addr,
    'schedule' => $sched,
    'items' => $items,
    'items_count' => count($items)
  ];
}
$stmt->close();

echo json_encode(['ok'=>true, 'orders'=>$orders]);
exit;
?>