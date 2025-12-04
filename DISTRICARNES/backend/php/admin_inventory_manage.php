<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

$action = $_POST['action'] ?? '';
if ($action !== 'restock') {
  echo json_encode(['success' => false, 'message' => 'Acción inválida']);
  exit;
}

// Campos desde el formulario de inventario
$productId = $_POST['product_id'] ?? null;
$addQuantity = isset($_POST['add_quantity']) ? (int)$_POST['add_quantity'] : null;
$notes = $_POST['notes'] ?? null;

if (!$productId || $addQuantity === null || $addQuantity < 1) {
  echo json_encode(['success' => false, 'message' => 'Datos incompletos para reabastecer']);
  exit;
}

// Detectar columnas de la tabla producto
function getColumns(mysqli $db, string $table): array {
  $cols = [];
  if ($res = $db->query("DESCRIBE `$table`")) {
    while ($row = $res->fetch_assoc()) {
      $cols[] = $row['Field'];
    }
    $res->close();
  }
  return $cols;
}

function findIdColumn(array $cols): ?string {
  foreach (['id', 'id_producto', 'producto_id', 'idProduct'] as $c) {
    if (in_array($c, $cols, true)) return $c;
  }
  return null;
}

$table = 'producto';
$columns = getColumns($conexion, $table);
$idCol = findIdColumn($columns);
$stockCol = in_array('stock', $columns, true) ? 'stock' : null;

if (!$idCol || !$stockCol) {
  echo json_encode(['success' => false, 'message' => 'Estructura de tabla inesperada (id/stock)']);
  exit;
}

// Obtener stock actual
$stmt = $conexion->prepare("SELECT `$stockCol` FROM `$table` WHERE `$idCol` = ? LIMIT 1");
$stmt->bind_param('s', $productId);
$stmt->execute();
$result = $stmt->get_result();
if (!$result || $result->num_rows === 0) {
  echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
  $stmt->close();
  exit;
}
$row = $result->fetch_assoc();
$currentStock = (int)$row[$stockCol];
$stmt->close();

$newStock = $currentStock + $addQuantity;

// Actualizar stock
$stmt2 = $conexion->prepare("UPDATE `$table` SET `$stockCol` = ? WHERE `$idCol` = ?");
$stmt2->bind_param('is', $newStock, $productId);
$ok = $stmt2->execute();
$stmt2->close();

if ($ok) {
  echo json_encode(['success' => true, 'message' => 'Stock actualizado correctamente', 'new_stock' => $newStock]);
} else {
  echo json_encode(['success' => false, 'message' => 'No fue posible actualizar el stock']);
}

$conexion->close();
?>