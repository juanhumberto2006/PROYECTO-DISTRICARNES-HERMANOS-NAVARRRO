<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

try {
  $q = isset($_GET['q']) ? trim($_GET['q']) : '';
  $role = isset($_GET['role']) ? trim($_GET['role']) : '';

  $sql = "SELECT * FROM usuario";
  $where = [];
  if ($q !== '') {
    $safe = '%' . $conexion->real_escape_string($q) . '%';
    $where[] = "(nombres_completos LIKE '$safe' OR correo_electronico LIKE '$safe' OR telefono LIKE '$safe')";
  }
  if ($role !== '') {
    $safeRole = $conexion->real_escape_string($role);
    $where[] = "(rol = '$safeRole')";
  }
  if (!empty($where)) {
    $sql .= ' WHERE ' . implode(' AND ', $where);
  }
  $sql .= ' ORDER BY id_usuario DESC';

  $result = $conexion->query($sql);
  if (!$result) {
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $conexion->error]);
    exit;
  }

  $users = [];
  while ($row = $result->fetch_assoc()) {
    $users[] = $row;
  }

  echo json_encode(['ok' => true, 'count' => count($users), 'users' => $users], JSON_UNESCAPED_UNICODE);
} catch (Exception $e) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => 'Server error']);
}

$conexion->close();
?>