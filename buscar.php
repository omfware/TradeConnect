<?php
// buscar.php
session_start();
require 'conexion.php';

$rol = $_SESSION['rol'] ?? null;

// A dónde debe ir "Inicio"
$inicioHref = 'index.html';
if (isset($_SESSION['id_usuario'])) {
  if ($rol === 'cliente')       $inicioHref = 'panel_cliente.php';
  elseif ($rol === 'proveedor') $inicioHref = 'panel_proveedor.php';
  else                          $inicioHref = 'inicio.php';
}

// --- Filtros ---
$q   = trim($_GET['q']   ?? '');
$loc = trim($_GET['loc'] ?? '');
$cat = (int)($_GET['cat'] ?? 0);

// Traer categorías para el filtro
$categorias = [];
if ($res = $conn->query("SELECT id_categoria, nombre FROM categoria ORDER BY nombre")) {
  $categorias = $res->fetch_all(MYSQLI_ASSOC);
  $res->close();
}

// Armar consulta dinámica
$where  = [];
$params = [];
$types  = '';

// 👇 Filtro base SIEMPRE: servicios visibles y proveedor no baneado
$where[] = "s.oculto = 0 AND u.baneado = 0";

if ($q !== '') {
  $where[] = "(s.titulo LIKE ? OR s.descripcion LIKE ?)";
  $params[] = "%$q%";
  $params[] = "%$q%";
  $types   .= 'ss';
}
if ($loc !== '') {
  $where[] = "s.ubicacion LIKE ?";
  $params[] = "%$loc%";
  $types   .= 's';
}
if ($cat > 0) {
  // Filtrar por categoría usando EXISTS para preservar el GROUP BY
  $where[] = "EXISTS (SELECT 1 FROM servicio_categoria sc WHERE sc.id_servicio = s.id_servicio AND sc.id_categoria = ?)";
  $params[] = $cat;
  $types   .= 'i';
}

$sql = "
  SELECT  s.id_servicio, s.titulo, s.descripcion, s.precio, s.ubicacion, s.imagen_url,
          u.nombre, u.apellido,
          COALESCE(GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', '), '') AS categorias
  FROM servicio s
    JOIN usuario u ON u.id_usuario = s.id_usuario
    LEFT JOIN servicio_categoria sc ON sc.id_servicio = s.id_servicio
    LEFT JOIN categoria c           ON c.id_categoria = sc.id_categoria
  WHERE " . implode(' AND ', $where) . "
  GROUP BY s.id_servicio
  ORDER BY s.fecha_publicacion DESC
  LIMIT 100
";

$stmt = $conn->prepare($sql);
if (!$stmt) { die('Error al preparar consulta: ' . $conn->error); }
if ($types !== '') { $stmt->bind_param($types, ...$params); }
$stmt->execute();
$result = $stmt->get_result();
$servicios = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link rel="stylesheet" href="css/buscar.css">
  <title>Buscar Servicios - TradeConnect</title>
</head>
<body>
  <header>
    <div class="logo">
      <img src="images/logo.png" alt="Logo">
      <span><strong>Trade</strong>Connect</span>
    </div>
    <nav>
      <a href="<?= e($inicioHref) ?>">Inicio</a>
      <?php if (($rol ?? null) === 'cliente'): ?>
        <a href="buscar.php">Buscar</a>
      <?php endif; ?>
      <a href="categorias.php">Categorías</a>
      <?php if (isset($_SESSION['id_usuario'])): ?>
        <a class="logout" href="logout.php">Cerrar sesión</a>
      <?php else: ?>
        <a href="acerca.php">Acerca de</a>
        <a href="login.html">Ingresar</a>
      <?php endif; ?>
    </nav>
  </header>

  <form class="search-section" method="get" action="buscar.php">
    <div class="search-bar">
      <input type="text" name="q" value="<?= e($q) ?>" placeholder="Buscar por palabra clave o servicio">
      <input type="text" name="loc" value="<?= e($loc) ?>" placeholder="Ubicación (ej.: Montevideo)">
      <select name="cat">
        <option value="0">Todas las categorías</option>
        <?php foreach ($categorias as $c): ?>
          <option value="<?= (int)$c['id_categoria'] ?>" <?= ($cat === (int)$c['id_categoria'] ? 'selected' : '') ?>>
            <?= e($c['nombre']) ?>
          </option>
        <?php endforeach; ?>
      </select>
      <button type="submit">🔎 Buscar</button>
    </div>
  </form>

  <div class="results">
    <?php if (!$servicios): ?>
      <div class="empty">No encontramos servicios con esos filtros.</div>
    <?php else: ?>
      <?php foreach ($servicios as $s): ?>
        <div class="result-card">
          <img src="<?= e($s['imagen_url'] ?: 'uploads/placeholder.webp') ?>" alt="Imagen del servicio"
               onerror="this.src='uploads/placeholder.webp'">
          <div>
            <h4><?= e($s['titulo']) ?></h4>
            <div class="muted">
              <?= $s['categorias'] ? "<span class='pill'>".e($s['categorias'])."</span>" : "" ?>
              <span class="pill"><?= e($s['ubicacion']) ?></span>
            </div>
            <div class="muted">
              <?= e(mb_strimwidth($s['descripcion'] ?? '', 0, 140, '…', 'UTF-8')) ?>
            </div>
          </div>
          <div class="cta" style="flex-direction:column; align-items:flex-end;">
            <div class="price">$ <?= number_format((float)$s['precio'], 0, ',', '.') ?></div>

            <?php if (isset($_SESSION['id_usuario'])): ?>
              <a href="ver_servicio.php?id=<?= (int)$s['id_servicio'] ?>">Ver servicio</a>
            <?php else: ?>
              <!-- Vista pública si no hay sesión -->
              <a href="ver_servicio_publico.php?id=<?= (int)$s['id_servicio'] ?>">Ver servicio</a>
            <?php endif; ?>

          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</body>
</html>
