<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/conexion.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['success' => false, 'message' => 'Método no permitido']);
  exit;
}

$action = $_POST['action'] ?? '';

// Utilidades de esquema
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
if (!$idCol) {
  echo json_encode(['success' => false, 'message' => 'No se encontró columna ID en producto']);
  exit;
}

// Toggle activo/inactivo
if ($action === 'toggle') {
  $productId = $_POST['product_id'] ?? null;
  if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'ID de producto faltante']);
    exit;
  }

  // Detectar columna de estado entre varias opciones comunes
  $statusCandidates = ['estado','activo','status','disponible','habilitado'];
  $statusCol = null;
  foreach ($statusCandidates as $c) {
    if (in_array($c, $columns, true)) { $statusCol = $c; break; }
  }
  // Si no existe ninguna, intentar crear la columna 'estado' como TINYINT(1)
  if (!$statusCol) {
    @$conexion->query("ALTER TABLE `$table` ADD COLUMN `estado` TINYINT(1) NOT NULL DEFAULT 1");
    // Recargar columnas y reintentar
    $columns = getColumns($conexion, $table);
    if (in_array('estado', $columns, true)) {
      $statusCol = 'estado';
    } else {
      echo json_encode(['success' => false, 'message' => 'Columna de estado no encontrada y no se pudo crear']);
      exit;
    }
  }

  $stmt = $conexion->prepare("SELECT `$statusCol` FROM `$table` WHERE `$idCol` = ? LIMIT 1");
  $stmt->bind_param('s', $productId);
  $stmt->execute();
  $res = $stmt->get_result();
  if (!$res || $res->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Producto no encontrado']);
    $stmt->close();
    exit;
  }
  $row = $res->fetch_assoc();
  $current = $row[$statusCol];
  $stmt->close();

  // Determinar nuevo estado
  if (is_numeric($current)) {
    $new = ((int)$current) === 1 ? 0 : 1;
  } else {
    $lc = strtolower((string)$current);
    $new = ($lc === 'activo' || $lc === 'active') ? 'inactivo' : 'activo';
  }

  $stmt2 = $conexion->prepare("UPDATE `$table` SET `$statusCol` = ? WHERE `$idCol` = ?");
  $stmt2->bind_param('ss', $new, $productId);
  $ok = $stmt2->execute();
  $stmt2->close();

  echo json_encode(['success' => $ok, 'message' => $ok ? 'Estado actualizado' : 'No se pudo actualizar el estado']);
  $conexion->close();
  exit;
}

// Eliminar producto
if ($action === 'delete') {
  $productId = $_POST['product_id'] ?? null;
  if (!$productId) {
    echo json_encode(['success' => false, 'message' => 'ID de producto faltante']);
    exit;
  }

  // Intentar obtener columna de imagen para eliminar archivo físico
  $imageCol = null;
  foreach (['imagen','image','imagen_url','image_url','foto','imagen_producto','url_imagen'] as $c) {
    if (in_array($c, $columns, true)) { $imageCol = $c; break; }
  }
  $oldImage = null;
  if ($imageCol) {
    $stmtImg = $conexion->prepare("SELECT `$imageCol` FROM `$table` WHERE `$idCol` = ? LIMIT 1");
    $stmtImg->bind_param('s', $productId);
    $stmtImg->execute();
    $resImg = $stmtImg->get_result();
    if ($resImg && $resImg->num_rows > 0) {
      $rowImg = $resImg->fetch_assoc();
      $oldImage = $rowImg[$imageCol] ?? null;
    }
    $stmtImg->close();
  }

  $stmt = $conexion->prepare("DELETE FROM `$table` WHERE `$idCol` = ?");
  $stmt->bind_param('s', $productId);
  $ok = $stmt->execute();
  $stmt->close();

  // Si se eliminó el registro, intentar borrar el archivo de imagen
  if ($ok && $oldImage) {
    $rootDir = dirname(__DIR__, 2);
    // Convertir ruta web a ruta filesystem
    $fsPath = $rootDir . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $oldImage), DIRECTORY_SEPARATOR);
    @unlink($fsPath);
  }

  echo json_encode(['success' => $ok, 'message' => $ok ? 'Producto eliminado' : 'No se pudo eliminar el producto']);
  $conexion->close();
  exit;
}

// Crear / Actualizar producto
if ($action === 'create' || $action === 'update') {
  // Helper para subir imagen con validaciones
  function upload_product_image(array $file, string $rootDir): array {
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
      // Intentar deducir por MIME
      $map = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp', 'image/gif' => 'gif'];
      $ext = $map[$type] ?? 'jpg';
    }
    $safeName = uniqid('prod_', true) . '.' . $ext;
    $uploadDir = $rootDir . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'images' . DIRECTORY_SEPARATOR . 'products';
    if (!is_dir($uploadDir)) { @mkdir($uploadDir, 0777, true); }
    $dest = $uploadDir . DIRECTORY_SEPARATOR . $safeName;
    if (!@move_uploaded_file($file['tmp_name'], $dest)) { $result['error'] = 'No se pudo guardar la imagen'; return $result; }
    $result['ok'] = true; $result['path'] = '/static/images/products/' . $safeName; return $result;
  }

  $fields = [
    'nombre' => $_POST['productName'] ?? null,
    'precio' => isset($_POST['productPrice']) ? (float)$_POST['productPrice'] : null,
    'stock' => isset($_POST['productStock']) ? (int)$_POST['productStock'] : null,
    'stock_minimo' => isset($_POST['stock_minimo']) ? (int)$_POST['stock_minimo'] : null,
    'fecha_vencimiento' => $_POST['productExpiry'] ?? null,
    'lote' => ($_POST['batchNumber'] ?? null),
    'precio_compra' => isset($_POST['purchasePrice']) ? (float)$_POST['purchasePrice'] : null,
    'descripcion' => $_POST['productDescription'] ?? ($_POST['descripcion'] ?? null),
    'subcategoria' => $_POST['subcategoria'] ?? null
  ];

  // Asegurar un mínimo de 5 unidades para stock_minimo
  if (!isset($fields['stock_minimo']) || $fields['stock_minimo'] === null) {
    $fields['stock_minimo'] = 5;
  } else {
    $fields['stock_minimo'] = max(5, (int)$fields['stock_minimo']);
  }

  // Mapear categoría enviada desde el formulario a la columna correcta
  $selectedCategory = $_POST['productCategory'] ?? null;
  $catIdCol = null;
  foreach (['categoria_id','id_categoria','category_id'] as $c) {
    if (in_array($c, $columns, true)) { $catIdCol = $c; break; }
  }
  $catTextCol = in_array('categoria', $columns, true) ? 'categoria' : null;
  if ($selectedCategory !== null) {
    if ($catIdCol) {
      // Usar el ID de categoría (FK)
      $fields[$catIdCol] = $selectedCategory;
    } elseif ($catTextCol) {
      // Usar el nombre de categoría en texto
      $fields[$catTextCol] = $selectedCategory;
    }
  }

  // Mapear sinónimos de columnas si las originales no existen
  if (!in_array('fecha_vencimiento', $columns, true) && in_array('fecha_caducidad', $columns, true) && isset($fields['fecha_vencimiento'])) {
    $fields['fecha_caducidad'] = $fields['fecha_vencimiento'];
    unset($fields['fecha_vencimiento']);
  }
  if (!in_array('lote', $columns, true) && in_array('numero_lote', $columns, true) && isset($fields['lote'])) {
    $fields['numero_lote'] = $fields['lote'];
    unset($fields['lote']);
  }
  if (!in_array('precio_compra', $columns, true) && in_array('precio_compra_lote', $columns, true) && isset($fields['precio_compra'])) {
    $fields['precio_compra_lote'] = $fields['precio_compra'];
    unset($fields['precio_compra']);
  }
  // Generar número de lote automáticamente si no se envió y existe alguna columna compatible
  $batchCandidates = ['lote','numero_lote','num_lote','lote_numero'];
  $existingBatchCol = null;
  foreach ($batchCandidates as $bc) {
    if (in_array($bc, $columns, true)) { $existingBatchCol = $bc; break; }
  }
  // Si no se envió lote en ningún alias, generarlo
  $hasBatchValue = false;
  foreach ($batchCandidates as $bc) {
    if (isset($fields[$bc]) && $fields[$bc] !== null && $fields[$bc] !== '') { $hasBatchValue = true; break; }
  }
  if ($existingBatchCol && !$hasBatchValue) {
    // Ej: L20241012-ABC123
    $rand = bin2hex(random_bytes(3));
    $autoBatch = 'L' . date('Ymd') . '-' . strtoupper(substr($rand, 0, 6));
    $fields[$existingBatchCol] = $autoBatch;
    // Remover otros alias vacíos si existían
    foreach ($batchCandidates as $bc) {
      if ($bc !== $existingBatchCol) { unset($fields[$bc]); }
    }
  }
  // Adicionales: num_lote/lote_numero, valor_compra/costo_compra, inscripcion, min_stock/stock_min
  if (!in_array('lote', $columns, true) && isset($fields['lote'])) {
    foreach (['num_lote','lote_numero'] as $alt) {
      if (in_array($alt, $columns, true)) { $fields[$alt] = $fields['lote']; unset($fields['lote']); break; }
    }
  }
  if (!in_array('precio_compra', $columns, true) && isset($fields['precio_compra'])) {
    foreach (['valor_compra','costo_compra','precio_lote_compra'] as $alt) {
      if (in_array($alt, $columns, true)) { $fields[$alt] = $fields['precio_compra']; unset($fields['precio_compra']); break; }
    }
  }
  if (!in_array('descripcion', $columns, true) && isset($fields['descripcion'])) {
    foreach (['inscripcion'] as $alt) {
      if (in_array($alt, $columns, true)) { $fields[$alt] = $fields['descripcion']; unset($fields['descripcion']); break; }
    }
  }
  if (!in_array('stock_minimo', $columns, true) && isset($fields['stock_minimo'])) {
    foreach (['min_stock','stock_min'] as $alt) {
      if (in_array($alt, $columns, true)) { $fields[$alt] = $fields['stock_minimo']; unset($fields['stock_minimo']); break; }
    }
  }

  // Manejo de imagen si la columna existe
  $imageCol = null;
  foreach (['imagen','image','imagen_url','image_url','foto','imagen_producto','url_imagen'] as $c) {
    if (in_array($c, $columns, true)) { $imageCol = $c; break; }
  }
  // Si no existe ninguna columna de imagen, crear `imagen`
  if (!$imageCol) {
    @$conexion->query("ALTER TABLE `$table` ADD COLUMN `imagen` VARCHAR(255) NULL");
    $columns = getColumns($conexion, $table);
    if (in_array('imagen', $columns, true)) { $imageCol = 'imagen'; }
  }

  // En creación: manejar nueva imagen directamente
  if ($action === 'create' && $imageCol && isset($_FILES['productImage']) && is_array($_FILES['productImage'])) {
    $rootDir = dirname(__DIR__, 2); // DISTRICARNES
    $up = upload_product_image($_FILES['productImage'], $rootDir);
    if ($up['ok']) { $fields[$imageCol] = $up['path']; }
    elseif ($up['error']) { echo json_encode(['success' => false, 'message' => $up['error']]); $conexion->close(); exit; }
  }

  // Solo usar columnas existentes
  $filtered = [];
  foreach ($fields as $col => $val) {
    if ($val !== null && in_array($col, $columns, true)) {
      $filtered[$col] = $val;
    }
  }

  if ($action === 'create') {
    if (empty($filtered)) {
      echo json_encode(['success' => false, 'message' => 'No hay campos válidos para crear']);
      exit;
    }
    $colsStr = '`' . implode('`,`', array_keys($filtered)) . '`';
    $placeholders = rtrim(str_repeat('?,', count($filtered)), ',');
    $stmt = $conexion->prepare("INSERT INTO `$table` ($colsStr) VALUES ($placeholders)");
    $types = str_repeat('s', count($filtered));
    $values = array_values($filtered);
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Producto creado' : 'No se pudo crear el producto']);
    $conexion->close();
    exit;
  } else {
    $productId = $_POST['product_id'] ?? null;
    if (!$productId) {
      echo json_encode(['success' => false, 'message' => 'ID de producto faltante']);
      exit;
    }
    // En actualización: si hay nueva imagen, subirla y eliminar la anterior
    if ($imageCol && isset($_FILES['productImage']) && is_array($_FILES['productImage'])) {
      // Obtener imagen previa
      $prev = null;
      $stmtPrev = $conexion->prepare("SELECT `$imageCol` FROM `$table` WHERE `$idCol` = ? LIMIT 1");
      $stmtPrev->bind_param('s', $productId);
      $stmtPrev->execute();
      $resPrev = $stmtPrev->get_result();
      if ($resPrev && $resPrev->num_rows > 0) {
        $rPrev = $resPrev->fetch_assoc();
        $prev = $rPrev[$imageCol] ?? null;
      }
      $stmtPrev->close();
      $rootDir = dirname(__DIR__, 2);
      $up = upload_product_image($_FILES['productImage'], $rootDir);
      if ($up['ok']) {
        $newRel = $up['path'];
        $fields[$imageCol] = $newRel;
        $filtered[$imageCol] = $newRel;
        if ($prev) {
          $fsPrev = $rootDir . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $prev), DIRECTORY_SEPARATOR);
          @unlink($fsPrev);
        }
      } elseif ($up['error'] && ($_FILES['productImage']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        echo json_encode(['success' => false, 'message' => $up['error']]); $conexion->close(); exit;
      }
    }
    if (empty($filtered)) {
      echo json_encode(['success' => false, 'message' => 'No hay campos válidos para actualizar']);
      exit;
    }
    $setParts = [];
    foreach (array_keys($filtered) as $col) {
      $setParts[] = "`$col` = ?";
    }
    $sql = "UPDATE `$table` SET " . implode(', ', $setParts) . " WHERE `$idCol` = ?";
    $stmt = $conexion->prepare($sql);
    $types = str_repeat('s', count($filtered)) . 's';
    $values = array_values($filtered);
    $values[] = $productId;
    $stmt->bind_param($types, ...$values);
    $ok = $stmt->execute();
    $stmt->close();
    echo json_encode(['success' => $ok, 'message' => $ok ? 'Producto actualizado' : 'No se pudo actualizar el producto']);
    $conexion->close();
    exit;
  }
}

echo json_encode(['success' => false, 'message' => 'Acción no soportada']);
$conexion->close();
?>