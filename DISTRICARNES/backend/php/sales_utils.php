<?php
// Utilidades para registrar ventas cuando una orden se completa
// Crea la tabla 'sales' si no existe y registra una fila por cada orden COMPLETED

require_once __DIR__ . '/conexion.php';

function table_exists(mysqli $db, string $name): bool {
  $nameEsc = $db->real_escape_string($name);
  if ($res = $db->query("SHOW TABLES LIKE '$nameEsc'")) {
    $exists = $res->num_rows > 0;
    $res->close();
    return $exists;
  }
  return false;
}

function ensure_table_schema(mysqli $db, string $table): void {
  $db->query("CREATE TABLE IF NOT EXISTS `$table` (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    paypal_id VARCHAR(64) NULL,
    customer_email VARCHAR(255) NULL,
    customer_name VARCHAR(255) NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function choose_sales_table(mysqli $db): string {
  if (table_exists($db, 'venta')) { return 'venta'; }
  if (table_exists($db, 'ventas')) { return 'ventas'; }
  if (table_exists($db, 'sales')) { return 'sales'; }
  ensure_table_schema($db, 'venta');
  return 'venta';
}

function ensure_sales_table(mysqli $db): void {
  $db->query("CREATE TABLE IF NOT EXISTS sales (
    id INT AUTO_INCREMENT PRIMARY KEY,
    order_id INT NOT NULL UNIQUE,
    paypal_id VARCHAR(64) NULL,
    customer_email VARCHAR(255) NULL,
    customer_name VARCHAR(255) NULL,
    total DECIMAL(12,2) NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

function record_sale_for_order(mysqli $db, int $orderId): array {
  $table = choose_sales_table($db);
  ensure_table_schema($db, $table);

  // Verificar si ya existe venta para esta orden
  $stmtChk = $db->prepare("SELECT id FROM `$table` WHERE order_id = ? LIMIT 1");
  $stmtChk->bind_param('i', $orderId);
  $stmtChk->execute();
  $resChk = $stmtChk->get_result();
  if ($resChk && $resChk->num_rows > 0) {
    $stmtChk->close();
    return ['ok' => true, 'created' => false, 'reason' => 'already_recorded'];
  }
  $stmtChk->close();

  // Cargar datos de la orden
  $stmt = $db->prepare("SELECT id, paypal_id, user_email, user_name, status, total, created_at FROM orders WHERE id = ? LIMIT 1");
  $stmt->bind_param('i', $orderId);
  $stmt->execute();
  $res = $stmt->get_result();
  $order = $res ? $res->fetch_assoc() : null;
  $stmt->close();

  if (!$order) { return ['ok' => false, 'error' => 'order_not_found']; }
  if (strtoupper((string)$order['status']) !== 'COMPLETED') { return ['ok' => true, 'created' => false, 'reason' => 'not_completed']; }

  // Insertar venta usando la fecha de creación de la orden
  $paypalId = $order['paypal_id'] ?? null;
  $email = $order['user_email'] ?? null;
  $name = $order['user_name'] ?? null;
  $total = floatval($order['total'] ?? 0);
  $createdAt = $order['created_at'] ?? null;

  // Si tenemos created_at de la orden, usarlo; de lo contrario, dejar default
  if ($createdAt) {
    $stmtIns = $db->prepare("INSERT INTO `$table` (order_id, paypal_id, customer_email, customer_name, total, created_at) VALUES (?, ?, ?, ?, ?, ?)");
    $stmtIns->bind_param('isssds', $orderId, $paypalId, $email, $name, $total, $createdAt);
  } else {
    $stmtIns = $db->prepare("INSERT INTO `$table` (order_id, paypal_id, customer_email, customer_name, total) VALUES (?, ?, ?, ?, ?)");
    $stmtIns->bind_param('isssd', $orderId, $paypalId, $email, $name, $total);
  }

  $ok = $stmtIns->execute();
  $stmtIns->close();
  return ['ok' => $ok, 'created' => $ok];
}

?>