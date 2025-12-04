<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/conexion.php';

// Normaliza el campo productos_json para que siempre regrese un array de IDs
function normalize_products_field($raw){
  if ($raw === null) return [];
  $s = trim((string)$raw);
  if ($s === '') return [];
  // Intentar JSON primero
  $decoded = json_decode($s, true);
  if (is_array($decoded)) {
    $out = [];
    foreach ($decoded as $v) {
      $t = trim((string)$v);
      if ($t !== '') { $out[] = $t; }
    }
    return $out;
  }
  // Fallback: CSV (coma, punto y coma o barra vertical)
  $parts = preg_split('/[,;|]/', $s);
  $out = [];
  foreach ($parts as $p) {
    $t = trim((string)$p);
    if ($t !== '') { $out[] = $t; }
  }
  return $out;
}

// Asegura que exista la tabla 'ofertas'
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

// Asegura que la columna 'imagen' exista en tablas ya creadas previamente
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

ensure_offers_table($conexion);
ensure_offer_image_column($conexion);

$status = isset($_GET['status']) ? strtolower(trim($_GET['status'])) : 'active';
$onlyActive = isset($_GET['only_active']) ? (trim($_GET['only_active']) !== '' ? filter_var($_GET['only_active'], FILTER_VALIDATE_BOOLEAN) : null) : null;
$q = isset($_GET['q']) ? $conexion->real_escape_string(trim($_GET['q'])) : '';

// Construir consulta seleccionando solo ofertas disponibles actualmente
$query = "SELECT id, nombre, descripcion, tipo, valor_descuento, fecha_inicio, fecha_fin, limite_usos, estado, productos_json, imagen, created_at FROM ofertas";
$where = [];
$params = [];
$types = '';

// Filtro por estado
if ($onlyActive === null) {
  // Si no se pasa only_active, usar 'status' (por defecto 'active')
  if ($status !== '') { $where[] = 'estado = ?'; $params[] = $status; $types .= 's'; }
} else if ($onlyActive) {
  $where[] = "estado = 'active'";
}

// Vigencia por fecha (si están dentro del rango o sin límites)
$where[] = "( (fecha_inicio IS NULL OR fecha_inicio = '0000-00-00 00:00:00' OR fecha_inicio <= NOW()) AND (fecha_fin IS NULL OR fecha_fin = '0000-00-00 00:00:00' OR fecha_fin >= NOW()) )";

// Búsqueda por texto opcional
if ($q !== '') { $where[] = '(nombre LIKE ? OR descripcion LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; $types .= 'ss'; }

if (!empty($where)) { $query .= ' WHERE ' . implode(' AND ', $where); }
$query .= ' ORDER BY created_at DESC';

$stmt = $conexion->prepare($query);
if (!$stmt) { echo json_encode(['ok' => false, 'error' => $conexion->error]); exit; }
if (!empty($params)) { $stmt->bind_param($types, ...$params); }
$ok = $stmt->execute();
if (!$ok) { echo json_encode(['ok' => false, 'error' => $stmt->error]); exit; }
$res = $stmt->get_result();

$offers = [];
while ($row = $res->fetch_assoc()) {
  // Normalizar fechas inválidas como null para el frontend
  $start = $row['fecha_inicio'];
  if ($start === '0000-00-00 00:00:00') { $start = null; }
  $end = $row['fecha_fin'];
  if ($end === '0000-00-00 00:00:00') { $end = null; }

  // Normalizar productos
  $products = normalize_products_field(isset($row['productos_json']) ? $row['productos_json'] : null);

  $offers[] = [
    'id' => intval($row['id']),
    'title' => $row['nombre'],
    'description' => $row['descripcion'],
    'type' => $row['tipo'],
    'discount_value' => floatval($row['valor_descuento']),
    'start_date' => $start,
    'end_date' => $end,
    'usage_limit' => isset($row['limite_usos']) ? intval($row['limite_usos']) : null,
    'products' => $products,
    'status' => $row['estado'],
    'image' => (isset($row['imagen']) && trim((string)$row['imagen']) !== '' ? $row['imagen'] : null),
    'created_at' => $row['created_at'],
  ];
}
$stmt->close();

echo json_encode(['ok' => true, 'offers' => $offers]);
exit;
?>