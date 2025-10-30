<?php
// ver_servicio.php
session_start();
require 'conexion.php';
require_once 'utils.php';

if (!isset($_SESSION['id_usuario'])) { header("Location: login.html"); exit; }

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: panel_cliente.php"); exit; }

// Traer servicio + proveedor + categorías + flags de moderación
$sql = "
  SELECT
    s.id_servicio, s.id_usuario AS id_proveedor, s.titulo, s.descripcion, s.precio, s.ubicacion,
    s.imagen_url, s.fecha_publicacion, s.oculto,
    u.nombre, u.apellido, u.usuario AS prov_user, u.telefono AS prov_tel,
    u.direccion AS prov_dir, u.avatar_url AS prov_avatar, u.baneado AS prov_baneado,
    COALESCE(GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', '),'') AS categorias
  FROM servicio s
  JOIN usuario u            ON u.id_usuario = s.id_usuario
  LEFT JOIN servicio_categoria sc ON sc.id_servicio = s.id_servicio
  LEFT JOIN categoria c           ON c.id_categoria = sc.id_categoria
  WHERE s.id_servicio = ?
  GROUP BY s.id_servicio
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$svc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$svc) {
  $msg = "El servicio no existe o fue eliminado.";
} else {
  // Moderación: no permitir ver si está oculto o el proveedor está baneado
  if ((int)($svc['oculto'] ?? 0) === 1 || (int)($svc['prov_baneado'] ?? 0) === 1) {
    $msg = "Este servicio no está disponible en este momento.";
  }
}

// ---- Reseñas (promedio, cantidad, paginado) ----
$perPage = 20;
$page    = max(1, (int)($_GET['page'] ?? 1));
$offset  = ($page - 1) * $perPage;

/* Promedio y total */
$avgSql = "
  SELECT AVG(rs.calificación) AS avg_rating, COUNT(*) AS total
  FROM Reseña rs
  JOIN Reserva rv ON rv.id_reserva = rs.id_reserva
  WHERE rv.id_servicio = ?
";
$stmt = $conn->prepare($avgSql);
$stmt->bind_param("i", $id);
$stmt->execute();
$meta = $stmt->get_result()->fetch_assoc() ?: ['avg_rating'=>null, 'total'=>0];
$stmt->close();
$avgRating    = $meta['avg_rating'] ? round((float)$meta['avg_rating'], 1) : null;
$totalReviews = (int)($meta['total'] ?? 0);

/* Listado */
$listSql = "
  SELECT rs.id_reseña, rs.calificación, rs.comentario, rs.fecha,
         u.usuario, u.avatar_url
  FROM Reseña rs
  JOIN Reserva rv ON rv.id_reserva = rs.id_reserva
  JOIN Usuario u  ON u.id_usuario  = rv.id_cliente
  WHERE rv.id_servicio = ?
  ORDER BY rs.fecha DESC
  LIMIT ? OFFSET ?
";
$stmt = $conn->prepare($listSql);
$stmt->bind_param("iii", $id, $perPage, $offset);
$stmt->execute();
$reviews = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$totalPages = max(1, (int)ceil($totalReviews / $perPage));

function stars_txt($n){
  $n = max(1, min(5, (int)$n));
  return str_repeat('★', $n) . str_repeat('☆', 5-$n);
}

// Flags para mostrar/ocultar "Contratar"
$soyCliente = (($_SESSION['rol'] ?? '') === 'cliente');
$soyDueno   = $svc ? ((int)$svc['id_proveedor'] === (int)($_SESSION['id_usuario'] ?? 0)) : false;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= isset($svc) ? e($svc['titulo']).' - ' : '' ?>TradeConnect</title>
<link rel="stylesheet" href="css/ver_servicio.css">
</head>
<body>
<header>
  <div class="logo">
    <img src="images/logo.png" alt="Logo">
    <span><strong>Trade</strong>Connect</span>
  </div>
  <nav>
    <?php if (($_SESSION['rol'] ?? '') === 'cliente'): ?>
      <a href="panel_cliente.php">Inicio</a>
      <a href="categorias.php">Categorías</a>
    <?php else: ?>
      <a href="inicio.php">Inicio</a>
    <?php endif; ?>
    <a class="logout" href="logout.php">Cerrar sesión</a>
  </nav>
</header>

<main class="main">
  <div class="breadcrumbs">
    <a href="javascript:history.back()">← Volver</a>
  </div>

  <?php if (!empty($msg)): ?>
    <div class="panel">
      <h3>Ups…</h3>
      <div class="muted"><?= e($msg) ?></div>
      <div class="actions" style="margin-top:12px">
        <a class="btn btn-outline" href="panel_cliente.php">Ir al inicio</a>
      </div>
    </div>
  <?php else: ?>
    <div class="hero">
      <h1><?= e($svc['titulo']) ?></h1>
      <div class="pills">
        <?php if ($svc['categorias']): ?>
          <span class="pill"><?= e($svc['categorias']) ?></span>
        <?php endif; ?>
        <span class="pill">Ubicación: <?= e($svc['ubicacion']) ?></span>
        <span class="pill">Publicado: <?= e(date('d/m/Y', strtotime($svc['fecha_publicacion'] ?? 'now'))) ?></span>
        <?php if ($totalReviews > 0): ?>
          <span class="pill">⭐ <?= e($avgRating) ?> · <?= $totalReviews ?> reseña<?= $totalReviews===1?'':'s' ?></span>
        <?php endif; ?>
      </div>
      <div class="price">$ <?= number_format((float)$svc['precio'], 0, ',', '.') ?></div>
      <div class="muted">Precio referencial por servicio.</div>
    </div>

    <div class="svc-grid">
      <div class="media">
        <img src="<?= e($svc['imagen_url'] ?: 'background-herramientas.jpg.png') ?>" alt="Imagen del servicio">
      </div>

      <div class="panel">
        <h3>Descripción</h3>
        <div class="desc"><?= e($svc['descripcion'] ?: 'Sin descripción.') ?></div>

        <h3 style="margin-top:18px">Proveedor</h3>
        <div class="prov">
          <div class="avatar">
            <img src="<?= e(avatar_url($svc['prov_avatar'] ?? null)) ?>" alt="Avatar del proveedor">
          </div>
          <div>
            <div><strong><?= e(trim(($svc['nombre'] ?? '').' '.($svc['apellido'] ?? ''))) ?></strong> <span class="muted">(@<?= e($svc['prov_user']) ?>)</span></div>
            <div class="muted"><?= e($svc['prov_dir'] ?: 'Sin dirección') ?></div>
            <div class="muted"><?= e($svc['prov_tel'] ?: 'Sin teléfono') ?></div>
          </div>
        </div>

        <div class="actions">
          <?php if ($soyCliente && !$soyDueno): ?>
            <a class="btn btn-primary" href="reservar.php?id_servicio=<?= (int)$svc['id_servicio'] ?>">Contratar</a>
          <?php endif; ?>
          <!-- Contactar: ahora envía to (proveedor) y servicio -->
          <a
            class="btn btn-outline"
            href="mensajes.php?to=<?= (int)$svc['id_proveedor'] ?>&servicio=<?= (int)$svc['id_servicio'] ?>"
          >Contactar</a>
          <a class="btn btn-outline" href="javascript:history.back()">Volver</a>
        </div>
      </div>
    </div>

    <!-- ===== Reseñas ===== -->
    <section class="reviews">
      <h2>Reseñas</h2>

      <div class="reviews-meta">
        <?php if ($totalReviews > 0): ?>
          <span class="avg"><?= e($avgRating) ?> / 5</span>
          <span class="count">· <?= $totalReviews ?> reseña<?= $totalReviews===1?'':'s' ?></span>
        <?php else: ?>
          <span class="count muted">Todavía no hay reseñas para este servicio.</span>
        <?php endif; ?>
      </div>

      <?php if ($reviews): ?>
        <div class="reviews-list">
          <?php foreach ($reviews as $r): ?>
            <article class="review-card">
              <div class="rev-head">
                <div class="rev-avatar">
                  <img src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt="">
                </div>
                <div class="rev-who">
                  <div class="user">@<?= e($r['usuario']) ?></div>
                  <div class="rev-when"><?= e(date('d/m/Y H:i', strtotime($r['fecha']))) ?></div>
                </div>
                <div class="rev-rating"><?= e(stars_txt($r['calificación'])) ?></div>
              </div>
              <?php if (trim($r['comentario']) !== ''): ?>
                <p class="rev-body"><?= nl2br(e($r['comentario'])) ?></p>
              <?php endif; ?>
            </article>
          <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
          <div class="pager">
            <?php if ($page > 1): ?>
              <a class="btn btn-outline" href="ver_servicio.php?id=<?= (int)$id ?>&page=<?= $page-1 ?>">← Anterior</a>
            <?php endif; ?>
            <span class="muted">Página <?= $page ?> de <?= $totalPages ?></span>
            <?php if ($page < $totalPages): ?>
              <a class="btn btn-outline" href="ver_servicio.php?id=<?= (int)$id ?>&page=<?= $page+1 ?>">Siguiente →</a>
            <?php endif; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </section>
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
