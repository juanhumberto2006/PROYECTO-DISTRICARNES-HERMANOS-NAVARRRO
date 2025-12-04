<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
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

function findIdColumn(array $cols): ?string {
  foreach (['id_usuario', 'id', 'usuario_id'] as $c) {
    if (in_array($c, $cols, true)) return $c;
  }
  return null;
}

$table = 'usuario';
$columns = getColumns($conexion, $table);
$idCol = findIdColumn($columns);
if (!$idCol) {
  echo json_encode(['success' => false, 'message' => 'No se encontró columna ID en usuario']);
  exit;
}

if ($action === 'toggle_status') {
  $userId = $_POST['user_id'] ?? null;
  if (!$userId) { echo json_encode(['success' => false, 'message' => 'ID faltante']); exit; }
  // Usamos columna rol para alternar entre 'trabajo' y 'admin'
  $roleCol = in_array('rol', $columns, true) ? 'rol' : null;
  if (!$roleCol) { echo json_encode(['success' => false, 'message' => 'Columna rol no encontrada']); exit; }
  $stmt = $conexion->prepare("SELECT `$roleCol` FROM `$table` WHERE `$idCol` = ? LIMIT 1");
  $stmt->bind_param('s', $userId);
  $stmt->execute();
  $res = $stmt->get_result();
  if (!$res || $res->num_rows === 0) { echo json_encode(['success' => false, 'message' => 'Usuario no encontrado']); $stmt->close(); exit; }
  $row = $res->fetch_assoc();
  $current = strtolower((string)$row[$roleCol]);
  $stmt->close();
  $new = ($current === 'admin') ? 'trabajo' : 'admin';
  $stmt2 = $conexion->prepare("UPDATE `$table` SET `$roleCol` = ? WHERE `$idCol` = ?");
  $stmt2->bind_param('ss', $new, $userId);
  $ok = $stmt2->execute();
  $stmt2->close();
  echo json_encode(['success' => $ok, 'message' => $ok ? 'Rol actualizado' : 'No se pudo actualizar el rol']);
  $conexion->close();
  exit;
}

if ($action === 'delete') {
  $userId = $_POST['user_id'] ?? null;
  if (!$userId) { echo json_encode(['success' => false, 'message' => 'ID faltante']); exit; }
  $stmt = $conexion->prepare("DELETE FROM `$table` WHERE `$idCol` = ?");
  $stmt->bind_param('s', $userId);
  $ok = $stmt->execute();
  $stmt->close();
  echo json_encode(['success' => $ok, 'message' => $ok ? 'Usuario eliminado' : 'No se pudo eliminar el usuario']);
  $conexion->close();
  exit;
}

if ($action === 'create' || $action === 'update') {
  $first = $_POST['first_name'] ?? '';
  $last = $_POST['last_name'] ?? '';
  $name = trim($first . ' ' . $last);
  $email = $_POST['email'] ?? '';
  $phone = $_POST['phone'] ?? '';
  $address = $_POST['address'] ?? '';
  $userType = $_POST['user_type'] ?? 'user';
  $password = $_POST['password'] ?? '';

  $role = ($userType === 'staff') ? 'admin' : 'trabajo';

  // Mapear solo columnas existentes
  $data = [];
  if (in_array('nombres_completos', $columns, true)) $data['nombres_completos'] = $name;
  if (in_array('correo_electronico', $columns, true)) $data['correo_electronico'] = $email;
  if (in_array('telefono', $columns, true)) $data['telefono'] = $phone;
  if (in_array('direccion', $columns, true)) $data['direccion'] = $address;
  if (in_array('rol', $columns, true)) $data['rol'] = $role;
  if ($password && in_array('contrasena', $columns, true)) $data['contrasena'] = $password;

  if ($action === 'create') {
    if (in_array('fecha_registro', $columns, true)) {
      // Establece la fecha de registro si existe la columna
      $data['fecha_registro'] = date('Y-m-d H:i:s');
    }
    if (empty($data)) { echo json_encode(['success' => false, 'message' => 'No hay campos válidos']); exit; }
    $colsStr = '`' . implode('`,`', array_keys($data)) . '`';
    $placeholders = rtrim(str_repeat('?,', count($data)), ',');
    $stmt = $conexion->prepare("INSERT INTO `$table` ($colsStr) VALUES ($placeholders)");
    $types = str_repeat('s', count($data));
    $values = array_values($data);
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Usuario creado' : 'No se pudo crear el usuario']);
    $conexion->close();
    exit;
  } else {
    $userId = $_POST['user_id'] ?? null;
    if (!$userId) { echo json_encode(['success' => false, 'message' => 'ID faltante']); exit; }
    if (empty($data)) { echo json_encode(['success' => false, 'message' => 'No hay campos válidos']); exit; }
    $setParts = [];
    foreach (array_keys($data) as $col) { $setParts[] = "`$col` = ?"; }
    $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE `$idCol` = ?";
    $stmt = $conexion->prepare($sql);
    $types = str_repeat('s', count($data)) . 's';
    $values = array_values($data);
    $values[] = $userId;
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Usuario actualizado' : 'No se pudo actualizar el usuario']);
    $conexion->close();
    exit;
  }
}

echo json_encode(['success' => false, 'message' => 'Acción no soportada']);
$conexion->close();
?>