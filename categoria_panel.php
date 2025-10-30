<?php
// categoria_panel.php — Panel estético de servicios por categoría
session_start();
require 'conexion.php';
require_once 'utils.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$rol     = $_SESSION['rol'] ?? null;
$isLogin = isset($_SESSION['id_usuario']);

$slugMap = [
  'electricidad' => 'Electricidad',
  'fontaneria'   => 'Fontanería',
  'limpieza'     => 'Limpieza',
  'pintura'      => 'Pintura',
];

$slug = strtolower(trim($_GET['slug'] ?? ''));
$catNombre = $slugMap[$slug] ?? null;
if (!$catNombre) { header('Location: categorias.php'); exit; }

$q   = trim($_GET['q']   ?? '');
$ubi = trim($_GET['ubi'] ?? '');

$sql = "
  SELECT s.id_servicio, s.titulo, s.descripcion, s.precio, s.ubicacion,
         s.imagen_url, s.fecha_publicacion,
         u.id_usuario AS id_proveedor, u.usuario, u.avatar_url
  FROM servicio s
  JOIN servicio_categoria sc ON sc.id_servicio = s.id_servicio
  JOIN categoria c           ON c.id_categoria = sc.id_categoria
  JOIN usuario u             ON u.id_usuario   = s.id_usuario
  WHERE c.nombre = ?
";
$types = "s";
$params = [$catNombre];

if ($q !== '') {
  $sql .= " AND (s.titulo LIKE CONCAT('%', ?, '%') OR s.descripcion LIKE CONCAT('%', ?, '%'))";
  $types .= "ss"; $params[] = $q; $params[] = $q;
}
if ($ubi !== '') {
  $sql .= " AND s.ubicacion LIKE CONCAT('%', ?, '%')";
  $types .= "s"; $params[] = $ubi;
}

$sql .= " GROUP BY s.id_servicio ORDER BY s.fecha_publicacion DESC LIMIT 120";

$stmt = $conn->prepare($sql);
$stmt->bind_param($types, ...$params);
$stmt->execute();
$servicios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($catNombre) ?> • Categoría | TradeConnect</title>
<link rel="stylesheet" href="css/categoria_panel.css">
</head>
<body>
<header>
  <div class="logo">
    <img src="images/logo.png" alt="Logo">
    <span><strong>Trade</strong>Connect</span>
  </div>
  <nav>
    <?php if ($rol === 'cliente'): ?>
      <a href="panel_cliente.php">Inicio</a>
      <a href="categorias.php">Categorías</a>
      <a href="editar_perfil_cliente.php">Editar Perfil</a>
      <a href="mensajes.php">Bandeja de Entrada</a>
      <a class="logout" href="logout.php">Cerrar sesión</a>
    <?php elseif ($rol === 'proveedor'): ?>
      <a href="panel_proveedor.php">Inicio</a>
      <a href="categorias.php">Categorías</a>
      <a class="logout" href="logout.php">Cerrar sesión</a>
    <?php else: ?>
      <a href="<?= $isLogin ? 'inicio.php' : 'index.html' ?>">Inicio</a>
      <a href="categorias.php">Categorías</a>
      <?php if ($isLogin): ?>
        <a class="logout" href="logout.php">Cerrar sesión</a>
      <?php else: ?>
        <a href="acerca.php">Acerca de</a>
        <a href="login.html">Ingresar</a>
      <?php endif; ?>
    <?php endif; ?>
  </nav>
</header>

<main>
  <section class="hero">
    <h1><?= e($catNombre) ?></h1>
    <div class="sub">Explorá servicios verificados, ordenados por más recientes.</div>
    <div class="chips">
      <span class="chip">Categoría seleccionada</span>
      <?php if ($ubi !== ''): ?><span class="chip">Ubicación: <?= e($ubi) ?></span><?php endif; ?>
      <?php if ($q   !== ''): ?><span class="chip">Búsqueda: “<?= e($q) ?>”</span><?php endif; ?>
    </div>

    <form class="filters" method="get" action="categoria_panel.php">
      <input type="hidden" name="slug" value="<?= e($slug) ?>">
      <input class="input" type="text" name="q"   value="<?= e($q)   ?>" placeholder="Buscar en títulos y descripciones…">
      <input class="input" type="text" name="ubi" value="<?= e($ubi) ?>" placeholder="Ubicación (ej.: Canelones)">
      <button class="btn btn-primary" type="submit">Aplicar</button>
      <a class="btn btn-ghost" href="categoria_panel.php?slug=<?= e($slug) ?>">Limpiar</a>
    </form>
  </section>

  <?php if (!$servicios): ?>
    <div class="empty">No hay servicios publicados en <strong><?= e($catNombre) ?></strong> con los filtros actuales.</div>
  <?php else: ?>
    <div class="grid">
      <?php foreach ($servicios as $s): ?>
        <article class="card">
          <div class="thumb">
            <img src="<?= e($s['imagen_url'] ?: 'images/fondo-herramientas.jpg.png') ?>" alt="">
          </div>
          <div class="body">
            <div class="title"><?= e($s['titulo']) ?></div>
            <div class="row">
              <span class="muted"><?= e($s['ubicacion']) ?></span>
              <span class="price">$ <?= number_format((float)$s['precio'], 0, ',', '.') ?></span>
            </div>
            <div class="muted"><?= e(mb_strimwidth($s['descripcion'] ?? '', 0, 140, '…', 'UTF-8')) ?></div>

            <div class="prov">
              <div class="avatar"><img src="<?= e(avatar_url($s['avatar_url'] ?? null)) ?>" alt=""></div>
              <div class="muted">@<?= e($s['usuario']) ?></div>
            </div>

            <div class="actions">
              <a class="a a-primary" href="ver_servicio.php?id=<?= (int)$s['id_servicio'] ?>">Ver</a>
              <a class="a a-ghost" href="mensajes.php?to=<?= (int)$s['id_proveedor'] ?>&servicio=<?= (int)$s['id_servicio'] ?>">Contactar</a>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</main>

<footer>
  <div>
    <a href="#">Términos</a>
    <a href="#">Contacto</a>
    <a href="acerca.php">Acerca de</a>
  </div>
  <div>© 2025 TradeConnect</div>
</footer>
</body>
</html>
