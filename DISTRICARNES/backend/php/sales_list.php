<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/sales_utils.php';

$tbl = choose_sales_table($conexion);
ensure_table_schema($conexion, $tbl);

$from = isset($_GET['from']) ? trim($_GET['from']) : '';
$to   = isset($_GET['to']) ? trim($_GET['to']) : '';

$query = "SELECT id, order_id, paypal_id, customer_email, customer_name, total, created_at FROM `$tbl`";
$where = [];
$params = [];
$types = '';
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
$sales = [];
while($row = $res->fetch_assoc()){
  $sales[] = [
    'id' => intval($row['id']),
    'order_id' => intval($row['order_id']),
    'paypal_id' => $row['paypal_id'],
    'customer_email' => $row['customer_email'],
    'customer_name' => $row['customer_name'],
    'total' => floatval($row['total']),
    'created_at' => $row['created_at'],
  ];
}
$stmt->close();

echo json_encode(['ok'=>true, 'sales'=>$sales]);
exit;
?>