<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['id_usuario'])) { header("Location: login.html"); exit; }
if (($_SESSION['rol'] ?? '') !== 'cliente') { header("Location: inicio.php"); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* -------------------- CONSULTAS -------------------- */

// Sugeridos: últimos 3 publicados (sin ocultos, sin baneados)
$sqlSug = "
  SELECT s.id_servicio, s.titulo, s.descripcion, s.precio, s.ubicacion, s.imagen_url,
         COALESCE(MAX(c.nombre),'') AS categoria
  FROM servicio s
  JOIN usuario u ON u.id_usuario = s.id_usuario
  LEFT JOIN servicio_categoria sc ON sc.id_servicio = s.id_servicio
  LEFT JOIN categoria c ON c.id_categoria = sc.id_categoria
  WHERE s.oculto = 0 AND u.baneado = 0
  GROUP BY s.id_servicio
  ORDER BY s.fecha_publicacion DESC, s.id_servicio DESC
  LIMIT 3
";
$sug = [];
if ($r = $conn->query($sqlSug)) while ($row = $r->fetch_assoc()) $sug[] = $row;

// Económicos: SOLO precio < 1500 (sin ocultos, sin baneados)
$sqlCheap = "
  SELECT s.id_servicio, s.titulo, s.descripcion, s.precio, s.ubicacion, s.imagen_url,
         COALESCE(MAX(c.nombre),'') AS categoria
  FROM servicio s
  JOIN usuario u ON u.id_usuario = s.id_usuario
  LEFT JOIN servicio_categoria sc ON sc.id_servicio = s.id_servicio
  LEFT JOIN categoria c ON c.id_categoria = sc.id_categoria
  WHERE s.precio < 1500 AND s.oculto = 0 AND u.baneado = 0
  GROUP BY s.id_servicio
  ORDER BY s.precio ASC, s.id_servicio DESC
  LIMIT 8
";
$cheap = [];
if ($r = $conn->query($sqlCheap)) while ($row = $r->fetch_assoc()) $cheap[] = $row;

// Chips de categorías
$cats = [];
if ($r = $conn->query("SELECT id_categoria, nombre FROM categoria ORDER BY nombre LIMIT 16")) {
  while ($row = $r->fetch_assoc()) $cats[] = $row;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Panel de Cliente - TradeConnect</title>
  <link rel="stylesheet" href="css/panel_cliente.css">
</head>
<body>
<header>
  <div class="logo">
    <img src="images/logo.png" alt="Logo">
    <span><strong>Trade</strong>Connect</span>
  </div>
  <nav>
    <a href="panel_cliente.php">Inicio</a>
    <a href="categorias.php">Categorías</a>
    <a href="mis_reservas_cliente.php">Mis reservas</a>
    <a href="editar_perfil_cliente.php">Editar Perfil</a>
    <a href="mensajes.php">Bandeja de Entrada</a>
    <a class="logout" href="logout.php">Cerrar Sesión</a>
  </nav>
</header>

<main class="main">
  <div class="hero">
    <h1>Encontrá y contratá servicios</h1>
    <div class="sub">Usá el buscador o explorá por categorías para contactar a los mejores proveedores.</div>
  </div>

  <form class="search" action="buscar.php" method="get">
    <input type="text" name="q" placeholder="¿Qué servicio necesitás? (ej.: electricista, plomero, pintura)" autocomplete="off" aria-label="Buscar servicios">
    <button type="submit">Buscar</button>
  </form>

  <!-- Sugeridos -->
  <section class="section">
    <h2>Sugeridos para vos</h2>
    <div class="lead">Algunas opciones recientes que podrían interesarte.</div>

    <div class="grid cols-auto">
      <?php if (!$sug): ?>
        <div class="card" style="grid-column:1/-1">
          <div class="title">Todavía no hay sugerencias</div>
          <div class="muted">Se irán mostrando a medida que se publiquen nuevos servicios.</div>
        </div>
      <?php else: foreach ($sug as $s):
        $img = $s['imagen_url'] ?: 'background-herramientas.jpg.png';
        $cat = $s['categoria'] ?: 'Sin categoría';
      ?>
        <div class="card">
          <div class="thumb"><img src="<?= e($img) ?>" alt="<?= e($s['titulo']) ?>"></div>
          <div class="title"><?= e($s['titulo']) ?></div>
          <span class="pill"><?= e($cat) ?> · <?= e($s['ubicacion']) ?></span>
          <div class="price">$ <?= number_format((float)$s['precio'], 0, ',', '.') ?></div>
          <div class="muted"><?= e(mb_strimwidth($s['descripcion'] ?? '', 0, 140, '…', 'UTF-8')) ?></div>
          <div><a class="ver-link" href="ver_servicio.php?id=<?= (int)$s['id_servicio'] ?>">Ver servicio</a></div>
        </div>
      <?php endforeach; endif; ?>
    </div>

    <div class="cta"><a class="btn-outline" href="buscar.php">Ver más servicios</a></div>
  </section>

  <!-- Económicos -->
  <section class="section">
    <h2>Servicios económicos</h2>
    <div class="lead">Servicios con precio menor a $ 1.500.</div>

    <div class="grid cols-auto">
      <?php if (!$cheap): ?>
        <div class="card" style="grid-column:1/-1">
          <div class="title">Por ahora no encontramos servicios económicos</div>
          <div class="muted">Probá buscando por categoría o ajustá tu presupuesto.</div>
        </div>
      <?php else: foreach ($cheap as $s):
        $img = $s['imagen_url'] ?: 'background-herramientas.jpg.png';
        $cat = $s['categoria'] ?: 'Sin categoría';
      ?>
        <div class="card">
          <div class="thumb"><img src="<?= e($img) ?>" alt="<?= e($s['titulo']) ?>"></div>
          <div class="title"><?= e($s['titulo']) ?></div>
          <span class="pill"><?= e($cat) ?> · <?= e($s['ubicacion']) ?></span>
          <div class="price">$ <?= number_format((float)$s['precio'], 0, ',', '.') ?></div>
          <div class="muted"><?= e(mb_strimwidth($s['descripcion'] ?? '', 0, 140, '…', 'UTF-8')) ?></div>
          <div><a class="ver-link" href="ver_servicio.php?id=<?= (int)$s['id_servicio'] ?>">Ver servicio</a></div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <!-- Categorías -->
  <section class="section">
    <h2>Explorá por categorías</h2>
    <div class="lead">Encontrá especialistas según el tipo de trabajo que necesitás.</div>
    <div class="chips">
      <?php if (!$cats): ?>
        <span class="muted">Sin categorías por ahora.</span>
      <?php else: foreach ($cats as $c): ?>
        <a class="chip" href="buscar.php?cat=<?= (int)$c['id_categoria'] ?>"><?= e($c['nombre']) ?></a>
      <?php endforeach; endif; ?>
    </div>
    <div class="cta" style="margin-top:12px"><a class="btn-outline" href="categorias.php">Ver todas las categorías</a></div>
  </section>
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
