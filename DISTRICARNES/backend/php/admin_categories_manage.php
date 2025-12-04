<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['ok' => false, 'message' => 'Método no permitido']);
  exit;
}

$action = $_POST['action'] ?? '';

function getColumns(mysqli $db, string $table): array {
  $cols = [];
  if ($res = $db->query("DESCRIBE `$table`")) {
    while ($row = $res->fetch_assoc()) { $cols[] = $row['Field']; }
    $res->close();
  }
  return $cols;
}

$table = 'categorias';
$columns = getColumns($conexion, $table);
if (empty($columns)) {
  echo json_encode(['ok' => false, 'message' => 'Tabla categorias no encontrada']);
  exit;
}

// Detectar columnas comunes
$idCol = null;
foreach (['id', 'id_categoria', 'categoria_id'] as $c) { if (in_array($c, $columns, true)) { $idCol = $c; break; } }
$nameCol = in_array('nombre', $columns, true) ? 'nombre' : (in_array('name', $columns, true) ? 'name' : null);
if (!$nameCol) { echo json_encode(['ok' => false, 'message' => 'Columna nombre no encontrada']); exit; }

if ($action === 'create') {
  $nombre = trim((string)($_POST['nombre'] ?? ''));
  if ($nombre === '') { echo json_encode(['ok' => false, 'message' => 'Nombre de categoría requerido']); exit; }

  // Verificar duplicados (case-insensitive)
  $nombreEsc = $conexion->real_escape_string($nombre);
  $sqlCheck = "SELECT 1 FROM `$table` WHERE LOWER(TRIM(`$nameCol`)) = LOWER(TRIM('$nombreEsc')) LIMIT 1";
  if ($res = $conexion->query($sqlCheck)) {
    if ($res->num_rows > 0) { echo json_encode(['ok' => false, 'message' => 'La categoría ya existe']); $res->close(); exit; }
    $res->close();
  }

  // Insertar
  $stmt = $conexion->prepare("INSERT INTO `$table` (`$nameCol`) VALUES (?)");
  $stmt->bind_param('s', $nombre);
  $ok = $stmt->execute();
  $stmt->close();
  echo json_encode(['ok' => $ok, 'message' => $ok ? 'Categoría creada' : 'No se pudo crear la categoría']);
  $conexion->close();
  exit;
}

echo json_encode(['ok' => false, 'message' => 'Acción no soportada']);
$conexion->close();
?>