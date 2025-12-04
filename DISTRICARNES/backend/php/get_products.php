<?php
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

require_once __DIR__ . '/conexion.php';

$categoria_id = isset($_GET['categoria_id']) ? $conexion->real_escape_string($_GET['categoria_id']) : null;
$idsParam = isset($_GET['ids']) ? trim((string)$_GET['ids']) : null; // lista de IDs para filtrar
$categoria = isset($_GET['categoria']) ? $conexion->real_escape_string($_GET['categoria']) : null;
$subcategoria = isset($_GET['subcategoria']) ? $conexion->real_escape_string($_GET['subcategoria']) : null;
$q = isset($_GET['q']) ? $conexion->real_escape_string($_GET['q']) : null;

$sql = "SELECT * FROM producto";
$where = [];
// Detectar columnas para categoría
$catIdCol = null;
$idCol = null;
$colsRes = $conexion->query("DESCRIBE `producto`");
if ($colsRes) {
  while ($cRow = $colsRes->fetch_assoc()) {
    $field = $cRow['Field'];
    if (in_array($field, ['categoria_id','id_categoria','category_id'], true)) { $catIdCol = $field; }
    if (in_array($field, ['id_producto','id','ID','codigo','producto_id'], true)) { $idCol = $field; }
  }
  $colsRes->close();
}

if ($categoria_id && $catIdCol) {
  $where[] = "`$catIdCol` = '$categoria_id'";
} elseif ($categoria) {
  $where[] = "(categoria LIKE '%$categoria%' OR categoria = '$categoria')";
}
if ($subcategoria) {
  $where[] = "(subcategoria LIKE '%$subcategoria%' OR subcategoria = '$subcategoria')";
}
if ($q) {
  $where[] = "(nombre LIKE '%$q%' OR descripcion LIKE '%$q%')";
}
// Filtro por IDs específicos si se pasa 'ids=1,2,3'
if ($idsParam && $idCol) {
  $parts = array_filter(array_map(function($x){ return trim($x); }, explode(',', $idsParam)));
  if (!empty($parts)) {
    // Escapar cada valor de manera segura
    $vals = array_map(function($v) use ($conexion){ return "'" . $conexion->real_escape_string($v) . "'"; }, $parts);
    $where[] = "`$idCol` IN (" . implode(',', $vals) . ")";
  }
}
if (!empty($where)) {
  $sql .= ' WHERE ' . implode(' AND ', $where);
}

$result = $conexion->query($sql);
if (!$result) {
  http_response_code(500);
  echo json_encode(['ok' => false, 'error' => $conexion->error], JSON_UNESCAPED_UNICODE);
  exit;
}

$rootDir = dirname(__DIR__, 2);
// Detectar si existe un directorio de imágenes estándar
$imagesDir = $rootDir . DIRECTORY_SEPARATOR . 'static' . DIRECTORY_SEPARATOR . 'images';
$imagesProductsDir = $imagesDir . DIRECTORY_SEPARATOR . 'products';

// Prefijo base cuando la app corre bajo subcarpeta (p.ej. /DISTRICARNES)
// Prefijo base del proyecto (evitar prefijar '/backend/php')
function base_prefix_root(): string {
  $script = (string)($_SERVER['SCRIPT_NAME'] ?? '');
  $dir = rtrim(dirname($script), '/');
  // Si el script vive bajo '/<base>/backend/php', usar solo '/<base>'
  if (preg_match('#/(.+)/backend/php$#', $dir, $m)) {
    return '/' . $m[1];
  }
  // Si el proyecto corre en raíz, no devolver prefijo
  // En otros casos, devolver el dirname si no es '/'
  return ($dir && $dir !== '/') ? $dir : '';
}

function apply_base_prefix(string $webPath): string {
  $p = str_replace('\\','/', $webPath);
  if ($p === '') return $p;
  if ($p[0] !== '/') { $p = '/' . $p; }
  $base = base_prefix_root();
  if ($base && strpos($p, $base . '/') !== 0) { $p = $base . $p; }
  return $p;
}

function normalize_web_path(string $fsPath, string $rootDir): string {
  $p = str_replace('\\', '/', $fsPath);
  $root = str_replace('\\', '/', $rootDir);
  if (strpos($p, $root) === 0) { $p = substr($p, strlen($root)); }
  return apply_base_prefix($p);
}

function find_fallback_image(?string $name, string $imagesDir, string $imagesProductsDir, string $rootDir): ?string {
  $lower = trim(mb_strtolower((string)$name));
  if ($lower === '') return normalize_web_path($imagesDir . '/image.png', $rootDir);
  $strip = preg_replace('/[^a-z0-9]+/i', '', $lower);
  $candidates = [];
  $exts = ['jpg','jpeg','png','webp','gif'];
  $dirs = [$imagesProductsDir, $imagesDir];
  foreach ($dirs as $dir) {
    foreach ($exts as $ext) {
      foreach (glob($dir . DIRECTORY_SEPARATOR . '*.' . $ext) as $file) {
        $base = mb_strtolower(pathinfo($file, PATHINFO_FILENAME));
        $baseStripped = preg_replace('/[^a-z0-9]+/i', '', $base);
        if ($baseStripped === $strip || strpos($baseStripped, $strip) !== false) {
          return normalize_web_path($file, $rootDir);
        }
      }
    }
  }
  // Fallback genérico si no se encuentra ninguna coincidencia
  $generic = $imagesDir . DIRECTORY_SEPARATOR . 'image.png';
  return file_exists($generic) ? normalize_web_path($generic, $rootDir) : null;
}

// ===== Proveedores: detección y utilidades =====
$hasProveedorTable = false;
$provIdPk = null;
$provNameCol = null;
if ($descProv = $conexion->query("DESCRIBE `proveedor`")) {
  $hasProveedorTable = true;
  $provCols = [];
  while ($r = $descProv->fetch_assoc()) { $provCols[] = $r['Field']; }
  $descProv->close();
  foreach (['id_proveedor','proveedor_id','id'] as $c) { if (in_array($c, $provCols, true)) { $provIdPk = $c; break; } }
  foreach (['nombre','nombre_proveedor','razon_social','name'] as $c) { if (in_array($c, $provCols, true)) { $provNameCol = $c; break; } }
}

$providerCache = [];
function provider_name(mysqli $db, $id, ?string $idCol, ?string $nameCol, array &$cache): ?string {
  $idKey = (string)$id;
  if ($idKey === '' || !$idCol || !$nameCol) return null;
  if (isset($cache[$idKey])) return $cache[$idKey];
  $idEsc = $db->real_escape_string($idKey);
  $sql = "SELECT `$nameCol` AS n FROM proveedor WHERE `$idCol` = '$idEsc' LIMIT 1";
  if ($res = $db->query($sql)) {
    $row = $res->fetch_assoc();
    $name = isset($row['n']) ? trim((string)$row['n']) : null;
    $res->close();
    $cache[$idKey] = ($name !== '' ? $name : null);
    return $cache[$idKey];
  }
  return null;
}

$products = [];
while ($row = $result->fetch_assoc()) {
  // Unificar/normalizar campo de imagen
  $image = null;
  foreach (['imagen','image','imagen_url','image_url','foto','imagen_producto','url_imagen'] as $c) {
    if (isset($row[$c]) && trim((string)$row[$c]) !== '') { $image = (string)$row[$c]; break; }
  }
  if ($image !== null) {
    $image = str_replace('\\', '/', $image);
    // Resolver ruta de imagen de manera robusta
    if (preg_match('#^https?://#i', $image)) {
      // URL absoluta: usar tal cual
    } else {
      // Caso 1: ya contiene 'static/images'
      $pos = strpos($image, 'static/images');
      if ($pos !== false) {
        $rel = substr($image, $pos); // 'static/images/...'
        // Verificar existencia en disco contra raíz del proyecto
        $fsCandidate = $rootDir . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $rel);
        if (file_exists($fsCandidate)) {
          $image = apply_base_prefix('/' . $rel);
        } else {
          // Intentar con basename dentro de directorios conocidos
          $base = basename($image);
          $try1 = $imagesProductsDir . DIRECTORY_SEPARATOR . $base;
          $try2 = $imagesDir . DIRECTORY_SEPARATOR . $base;
          if (file_exists($try1)) { $image = normalize_web_path($try1, $rootDir); }
          elseif (file_exists($try2)) { $image = normalize_web_path($try2, $rootDir); }
          else { $image = find_fallback_image(($row['nombre'] ?? null), $imagesDir, $imagesProductsDir, $rootDir); }
        }
      } else {
        // Caso 2: valor es relativo o solo nombre de archivo
        $base = basename($image);
        // Candidatos en directorios esperados
        $try1 = $imagesProductsDir . DIRECTORY_SEPARATOR . $base;
        $try2 = $imagesDir . DIRECTORY_SEPARATOR . $base;
        $try3 = $rootDir . DIRECTORY_SEPARATOR . ltrim(str_replace('/', DIRECTORY_SEPARATOR, $image), DIRECTORY_SEPARATOR);
        if (file_exists($try1)) { $image = normalize_web_path($try1, $rootDir); }
        elseif (file_exists($try2)) { $image = normalize_web_path($try2, $rootDir); }
        elseif (file_exists($try3)) { $image = normalize_web_path($try3, $rootDir); }
        else {
          // Asegurar prefijo para no romper el frontend, pero preferir fallback por nombre
          $fallback = find_fallback_image(($row['nombre'] ?? null), $imagesDir, $imagesProductsDir, $rootDir);
          $image = $fallback ?? apply_base_prefix('/' . ltrim($image, '/'));
        }
      }
    }
  } else {
    // Sin imagen en BD: intentar encontrar por nombre
    $image = find_fallback_image(($row['nombre'] ?? null), $imagesDir, $imagesProductsDir, $rootDir);
  }
  // Añadir campo 'imagen' para el frontend
  $row['imagen'] = $image ?? '';
  
  // Resolver proveedor: usar texto existente o buscar por ID en tabla proveedor
  $providerText = null;
  foreach (['proveedor','supplier','proveedor_nombre'] as $c) {
    if (isset($row[$c]) && trim((string)$row[$c]) !== '') { $providerText = (string)$row[$c]; break; }
  }
  if ($providerText === null) {
    $providerId = null;
    foreach (['id_proveedor','proveedor_id'] as $c) {
      if (isset($row[$c]) && trim((string)$row[$c]) !== '') { $providerId = (string)$row[$c]; break; }
    }
    if ($providerId !== null && $hasProveedorTable) {
      $name = provider_name($conexion, $providerId, $provIdPk, $provNameCol, $providerCache);
      if ($name !== null) { $providerText = $name; }
    }
  }
  if ($providerText !== null) { $row['proveedor'] = $providerText; }
  $products[] = $row;
}

echo json_encode([
  'ok' => true,
  'count' => count($products),
  'products' => $products
], JSON_UNESCAPED_UNICODE);
?>