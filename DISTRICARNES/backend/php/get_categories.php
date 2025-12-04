<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/conexion.php';

// Detectar columnas de producto para compatibilidad (texto vs FK)
function getColumns(mysqli $db, string $table): array {
  $cols = [];
  if ($res = $db->query("DESCRIBE `$table`")) {
    while ($row = $res->fetch_assoc()) { $cols[] = $row['Field']; }
    $res->close();
  }
  return $cols;
}

$productoCols = getColumns($conexion, 'producto');
$categoriaIdCol = null;
foreach (['categoria_id', 'id_categoria', 'category_id'] as $c) {
  if (in_array($c, $productoCols, true)) { $categoriaIdCol = $c; break; }
}
$categoriaTextCol = in_array('categoria', $productoCols, true) ? 'categoria' : null;

// Verificar si existe tabla 'categorias'
$hasCategorias = false;
if ($desc = $conexion->query("DESCRIBE `categorias`")) { $hasCategorias = true; $desc->close(); }

// Cargar categorías desde tabla 'categorias' si existe; de lo contrario, hacer fallback desde producto
// Detectar columnas reales en tabla categorias (id y nombre)
$categories = [];
if ($hasCategorias) {
  $catCols = getColumns($conexion, 'categorias');
  $idCol = null;
  foreach (['id', 'id_categoria', 'categoria_id'] as $c) { if (in_array($c, $catCols, true)) { $idCol = $c; break; } }
  $nameCol = in_array('nombre', $catCols, true) ? 'nombre' : (in_array('nombre_categoria', $catCols, true) ? 'nombre_categoria' : (in_array('name', $catCols, true) ? 'name' : null));

  // Si no se encuentran columnas conocidas, hacer fallback desde producto
  if ($idCol && $nameCol) {
    $sqlCategorias = "SELECT `$idCol` AS id_cat, `$nameCol` AS nom_cat FROM categorias ORDER BY `$nameCol`";
    $resultCat = $conexion->query($sqlCategorias);
    if ($resultCat) {
      while ($row = $resultCat->fetch_assoc()) {
        $id = isset($row['id_cat']) ? (string)$row['id_cat'] : null;
        $nombre = trim((string)($row['nom_cat'] ?? ''));
        $name = mb_strtolower($nombre);
        $display = mb_strtoupper($nombre);

        // Contar productos por categoría (soporte para FK o texto)
        $count = 0;
        if ($categoriaIdCol && $id !== null) {
          $idEsc = $conexion->real_escape_string($id);
          $sqlCount = "SELECT COUNT(*) AS c FROM producto WHERE `$categoriaIdCol` = '$idEsc'";
          if ($resC = $conexion->query($sqlCount)) { $count = (int)($resC->fetch_assoc()['c'] ?? 0); $resC->close(); }
        } elseif ($categoriaTextCol) {
          $nameEsc = $conexion->real_escape_string($name);
          $sqlCount = "SELECT COUNT(*) AS c FROM producto WHERE LOWER(TRIM(`$categoriaTextCol`)) = '$nameEsc'";
          if ($resC = $conexion->query($sqlCount)) { $count = (int)($resC->fetch_assoc()['c'] ?? 0); $resC->close(); }
        }

        $categories[] = [
          'id' => $id,
          'name' => $name,
          'display' => $display,
          'product_count' => $count
        ];
      }
      $resultCat->close();
    }
  } else {
    // Si no se detectan columnas esperadas, hacemos fallback
    $hasCategorias = false;
  }
}

if (!$hasCategorias) {
  // Fallback: derivar categorías desde producto y contar
  $result = $conexion->query("SELECT LOWER(TRIM(categoria)) AS categoria, COUNT(*) AS c FROM producto WHERE categoria IS NOT NULL AND categoria <> '' GROUP BY LOWER(TRIM(categoria)) ORDER BY categoria");
  if ($result) {
    while ($row = $result->fetch_assoc()) {
      $name = (string)$row['categoria'];
      $display = mb_strtoupper($name);
      $count = (int)($row['c'] ?? 0);
      $categories[] = [
        'id' => null,
        'name' => $name,
        'display' => $display,
        'product_count' => $count
      ];
    }
    $result->close();
  }
}

echo json_encode([
  'ok' => true,
  'count' => count($categories),
  'categories' => $categories
], JSON_UNESCAPED_UNICODE);
?>