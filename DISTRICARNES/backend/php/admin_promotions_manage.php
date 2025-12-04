<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

// Crea la tabla de ofertas si no existe
function ensure_offers_table(mysqli $db): void {
  $db->query("CREATE TABLE IF NOT EXISTS ofertas (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nombre VARCHAR(255) NOT NULL,
    descripcion TEXT NULL,
    tipo ENUM('percentage','fixed','bogo') NOT NULL DEFAULT 'percentage',
    valor_descuento DECIMAL(12,2) NOT NULL DEFAULT 0,
    fecha_inicio DATETIME NULL,
    fecha_fin DATETIME NULL,
    limite_usos INT NULL,
    estado VARCHAR(16) NOT NULL DEFAULT 'inactive',
    productos_json TEXT NULL,
    imagen VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}

// Asegurar columna de imagen si la tabla ya existía sin ella
function ensure_offer_image_column(mysqli $db): void {
  $has = false;
  if ($res = $db->query("DESCRIBE ofertas")) {
    while ($row = $res->fetch_assoc()) {
      if (isset($row['Field']) && $row['Field'] === 'imagen') { $has = true; break; }
    }
    $res->close();
  }
  if (!$has) { @$db->query("ALTER TABLE ofertas ADD COLUMN imagen VARCHAR(255) NULL"); }
}

function fetch_list(mysqli $db, string $status = '', string $type = '', string $q = ''): array {
  ensure_offers_table($db);
  ensure_offer_image_column($db);
  $query = "SELECT id, nombre, descripcion, tipo, valor_descuento, fecha_inicio, fecha_fin, limite_usos, estado, productos_json, imagen, created_at FROM ofertas";
  $where = [];
  $params = [];
  $types = '';
  if ($status !== '') { $where[] = 'status = ?'; $params[] = $status; $types .= 's'; }
  if ($type !== '') { $where[] = 'type = ?'; $params[] = $type; $types .= 's'; }
  if ($q !== '') { $where[] = '(title LIKE ? OR description LIKE ?)'; $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; $types .= 'ss'; }
  // Ajuste de nombres de columnas a la tabla ofertas
  $query = "SELECT id, nombre, descripcion, tipo, valor_descuento, fecha_inicio, fecha_fin, limite_usos, estado, productos_json, imagen, created_at FROM ofertas";
  if ($status !== '') { $where[] = 'estado = ?'; }
  if ($type !== '') { $where[] = 'tipo = ?'; }
  if ($q !== '') { $where[] = '(nombre LIKE ? OR descripcion LIKE ?)'; }
  if (!empty($where)) { $query .= ' WHERE ' . implode(' AND ', $where); }
  $query .= ' ORDER BY created_at DESC';
  $stmt = $db->prepare($query);
  if (!$stmt) { return ['ok' => false, 'error' => $db->error]; }
  // Recalcular params con nombres correctos
  $params = [];
  $types = '';
  if ($status !== '') { $params[] = $status; $types .= 's'; }
  if ($type !== '') { $params[] = $type; $types .= 's'; }
  if ($q !== '') { $params[] = '%' . $q . '%'; $params[] = '%' . $q . '%'; $types .= 'ss'; }
  if (!empty($params)) { $stmt->bind_param($types, ...$params); }
  $stmt->execute();
  $res = $stmt->get_result();
  $promotions = [];
  while ($row = $res->fetch_assoc()) {
    $promotions[] = [
      'id' => intval($row['id']),
      'title' => $row['nombre'],
      'description' => $row['descripcion'],
      'type' => $row['tipo'],
      'discount_value' => floatval($row['valor_descuento']),
      'start_date' => $row['fecha_inicio'],
      'end_date' => $row['fecha_fin'],
      'usage_limit' => isset($row['limite_usos']) ? intval($row['limite_usos']) : null,
      'products' => $row['productos_json'] ? json_decode($row['productos_json'], true) : null,
      'status' => $row['estado'],
      'image' => $row['imagen'] ?? null,
      'created_at' => $row['created_at'],
    ];
  }
  $stmt->close();
  return ['ok' => true, 'success' => true, 'promotions' => $promotions];
}

function fetch_stats(mysqli $db): array {
  ensure_offers_table($db);
  $total = 0; $active = 0; $scheduled = 0; $avgDiscount = 0.0;
  if ($res = $db->query("SELECT COUNT(*) AS c FROM ofertas")) { $row = $res->fetch_assoc(); $total = intval($row['c'] ?? 0); $res->close(); }
  if ($res = $db->query("SELECT COUNT(*) AS c FROM ofertas WHERE estado = 'active'")) { $row = $res->fetch_assoc(); $active = intval($row['c'] ?? 0); $res->close(); }
  if ($res = $db->query("SELECT COUNT(*) AS c FROM ofertas WHERE estado = 'scheduled'")) { $row = $res->fetch_assoc(); $scheduled = intval($row['c'] ?? 0); $res->close(); }
  if ($res = $db->query("SELECT AVG(valor_descuento) AS avgd FROM ofertas WHERE tipo = 'percentage'")) { $row = $res->fetch_assoc(); $avgDiscount = floatval($row['avgd'] ?? 0); $res->close(); }
  return [
    'ok' => true,
    'success' => true,
    'total_promotions' => $total,
    'active_promotions' => $active,
    'scheduled_promotions' => $scheduled,
    'average_discount' => $avgDiscount,
  ];
}

function create_promotion(mysqli $db, array $data): array {
  ensure_offers_table($db);
  ensure_offer_image_column($db);
  $name = trim($data['name'] ?? $data['title'] ?? '');
  if ($name === '') return ['ok' => false, 'success' => false, 'message' => 'Nombre requerido'];
  $description = $data['description'] ?? null;
  $type = $data['type'] ?? 'percentage';
  if (!in_array($type, ['percentage','fixed','bogo'], true)) $type = 'percentage';
  $discount = isset($data['value']) ? floatval($data['value']) : (isset($data['discount_value']) ? floatval($data['discount_value']) : 0);
  $start = $data['start_date'] ?? null;
  $end = $data['end_date'] ?? null;
  $limit = isset($data['usage_limit']) && $data['usage_limit'] !== '' ? intval($data['usage_limit']) : null;
  $status = $data['status'] ?? 'inactive';
  $productsJson = $data['applicable_products'] ?? null;
  $image = $data['image'] ?? null;
  $stmt = $db->prepare("INSERT INTO ofertas (nombre, descripcion, tipo, valor_descuento, fecha_inicio, fecha_fin, limite_usos, estado, productos_json, imagen) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
  if (!$stmt) return ['ok' => false, 'success' => false, 'message' => $db->error];
  $stmt->bind_param('sssdsissss', $name, $description, $type, $discount, $start, $end, $limit, $status, $productsJson, $image);
  $ok = $stmt->execute();
  $id = $ok ? $stmt->insert_id : null;
  $stmt->close();
  return ['ok' => $ok, 'success' => $ok, 'id' => $id, 'message' => $ok ? 'Promoción creada' : 'No se pudo crear la promoción'];
}

function update_promotion(mysqli $db, int $id, array $data): array {
  ensure_offers_table($db);
  ensure_offer_image_column($db);
  $name = trim($data['name'] ?? $data['title'] ?? '');
  if ($name === '') return ['ok' => false, 'success' => false, 'message' => 'Nombre requerido'];
  $description = $data['description'] ?? null;
  $type = $data['type'] ?? 'percentage';
  if (!in_array($type, ['percentage','fixed','bogo'], true)) $type = 'percentage';
  $discount = isset($data['value']) ? floatval($data['value']) : (isset($data['discount_value']) ? floatval($data['discount_value']) : 0);
  $start = $data['start_date'] ?? null;
  $end = $data['end_date'] ?? null;
  $limit = isset($data['usage_limit']) && $data['usage_limit'] !== '' ? intval($data['usage_limit']) : null;
  $status = $data['status'] ?? 'inactive';
  $productsJson = $data['applicable_products'] ?? null;
  $image = $data['image'] ?? null;
  $stmt = $db->prepare("UPDATE ofertas SET nombre = ?, descripcion = ?, tipo = ?, valor_descuento = ?, fecha_inicio = ?, fecha_fin = ?, limite_usos = ?, estado = ?, productos_json = ?, imagen = COALESCE(?, imagen) WHERE id = ?");
  if (!$stmt) return ['ok' => false, 'success' => false, 'message' => $db->error];
  $stmt->bind_param('sssdsissssi', $name, $description, $type, $discount, $start, $end, $limit, $status, $productsJson, $image, $id);
  $ok = $stmt->execute();
  $stmt->close();
  return ['ok' => $ok, 'success' => $ok, 'message' => $ok ? 'Promoción actualizada' : 'No se pudo actualizar la promoción'];
}

// Subir imagen de oferta
function upload_offer_image(array $file, string $rootDir): array {
  $result = ['ok' => false, 'path' => null, 'error' => null];
  if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    $result['error'] = 'Archivo de imagen no recibido';
    return $result;
  }
  $size = (int)($file['size'] ?? 0);
  if ($size <= 0) { $result['error'] = 'Archivo vacío'; return $result; }
  if ($size > 5 * 1024 * 1024) { $result['error'] = 'La imagen supera 5MB'; return $result; }
  $type = (string)($file['type'] ?? '');
  if (strpos($type, 'image/') !== 0) { $result['error'] = 'Tipo de archivo no permitido'; return $result; }
  $origName = basename((string)$file['name']);
  $ext = strtolower(pathinfo($origName, PATHINFO_EXTENSION));
  if (!$ext) {
    $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
    $ext = $map[$type] ?? 'jpg';
  }
  $safeName = uniqid('offer_', true) . '.' . $ext;
  $uploadDir = $rootDir . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'offers';
  if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
  $dest = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
  if (!@move_uploaded_file($file['tmp_name'], $dest)) { $result['error'] = 'No se pudo guardar la imagen'; return $result; }
  $result['ok'] = true; $result['path'] = '/static/images/offers/' . $safeName; return $result;
}

function delete_promotion(mysqli $db, int $id): array {
  ensure_offers_table($db);
  $stmt = $db->prepare("DELETE FROM ofertas WHERE id = ?");
  if (!$stmt) return ['ok' => false, 'success' => false, 'message' => $db->error];
  $stmt->bind_param('i', $id);
  $ok = $stmt->execute();
  $stmt->close();
  return ['ok' => $ok, 'success' => $ok, 'message' => $ok ? 'Promoción eliminada' : 'No se pudo eliminar la promoción'];
}

function toggle_status(mysqli $db, int $id): array {
  ensure_offers_table($db);
  $stmt = $db->prepare("SELECT estado FROM ofertas WHERE id = ? LIMIT 1");
  if (!$stmt) return ['ok' => false, 'success' => false, 'message' => $db->error];
  $stmt->bind_param('i', $id);
  $stmt->execute();
  $res = $stmt->get_result();
  $row = $res ? $res->fetch_assoc() : null;
  $stmt->close();
  if (!$row) return ['ok' => false, 'success' => false, 'message' => 'Promoción no encontrada'];
  $current = strtolower((string)$row['estado']);
  $next = ($current === 'active') ? 'inactive' : 'active';
  $stmt2 = $db->prepare("UPDATE ofertas SET estado = ? WHERE id = ?");
  if (!$stmt2) return ['ok' => false, 'success' => false, 'message' => $db->error];
  $stmt2->bind_param('si', $next, $id);
  $ok = $stmt2->execute();
  $stmt2->close();
  return ['ok' => $ok, 'success' => $ok, 'new_status' => $next, 'message' => $ok ? 'Estado actualizado' : 'No se pudo actualizar el estado'];
}

// Router sencillo
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
  $action = $_GET['action'] ?? 'list';
  $status = trim($_GET['status'] ?? '');
  $type = trim($_GET['type'] ?? '');
  $q = trim($_GET['q'] ?? '');
  if ($action === 'list') {
    echo json_encode(fetch_list($conexion, $status, $type, $q));
    exit;
  }
  if ($action === 'stats') {
    echo json_encode(fetch_stats($conexion));
    exit;
  }
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Acción inválida']);
  exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $action = $_POST['action'] ?? '';
  if ($action === 'create') {
    // subir imagen si viene
    $data = $_POST;
    if (isset($_FILES['offerImage']) && is_array($_FILES['offerImage'])) {
      $up = upload_offer_image($_FILES['offerImage'], dirname(__DIR__, 2));
      if ($up['ok']) { $data['image'] = $up['path']; }
    }
    echo json_encode(create_promotion($conexion, $data));
    exit;
  }
  if ($action === 'update') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) { echo json_encode(['ok' => false, 'success' => false, 'message' => 'ID inválido']); exit; }
    $data = $_POST;
    if (isset($_FILES['offerImage']) && is_array($_FILES['offerImage'])) {
      $up = upload_offer_image($_FILES['offerImage'], dirname(__DIR__, 2));
      if ($up['ok']) { $data['image'] = $up['path']; }
    }
    echo json_encode(update_promotion($conexion, $id, $data));
    exit;
  }
  if ($action === 'delete') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) { echo json_encode(['ok' => false, 'success' => false, 'message' => 'ID inválido']); exit; }
    echo json_encode(delete_promotion($conexion, $id));
    exit;
  }
  if ($action === 'toggle') {
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    if ($id <= 0) { echo json_encode(['ok' => false, 'success' => false, 'message' => 'ID inválido']); exit; }
    echo json_encode(toggle_status($conexion, $id));
    exit;
  }
  echo json_encode(['ok' => false, 'success' => false, 'message' => 'Acción inválida']);
  exit;
}

echo json_encode(['ok' => false, 'success' => false, 'message' => 'Método no permitido']);
exit;
?>