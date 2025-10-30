<?php
// mis_reservas_cliente.php
session_start();
require 'conexion.php';
require_once 'utils.php';

if (!isset($_SESSION['id_usuario'])) { header("Location: login.html"); exit; }
if (($_SESSION['rol'] ?? '') !== 'cliente') { header("Location: inicio.php"); exit; }

$idCliente = (int)$_SESSION['id_usuario'];
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- PENDIENTES ----------
$sqlPend = "
  SELECT r.id_reserva, r.fecha, r.hora, r.estado,
         s.id_servicio, s.titulo, s.ubicacion, s.precio,
         u.id_usuario AS id_proveedor, u.usuario AS prov_user, u.nombre, u.apellido, u.avatar_url
  FROM reserva r
  JOIN servicio s ON s.id_servicio = r.id_servicio
  JOIN usuario  u ON u.id_usuario  = s.id_usuario
  WHERE r.id_cliente = ?
    AND r.estado = 'pendiente'
  ORDER BY r.fecha ASC, r.hora ASC
";
$stmt = $conn->prepare($sqlPend);
$stmt->bind_param("i", $idCliente);
$stmt->execute();
$pend = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- PROXIMAS ----------
$sqlProx = "
  SELECT r.id_reserva, r.fecha, r.hora, r.estado,
         s.id_servicio, s.titulo, s.ubicacion, s.precio,
         u.id_usuario AS id_proveedor, u.usuario AS prov_user, u.nombre, u.apellido, u.avatar_url
  FROM reserva r
  JOIN servicio s ON s.id_servicio = r.id_servicio
  JOIN usuario  u ON u.id_usuario  = s.id_usuario
  WHERE r.id_cliente = ?
    AND r.estado = 'aceptada'
    AND CONCAT(r.fecha,' ',r.hora) >= NOW()
  ORDER BY r.fecha ASC, r.hora ASC
";
$stmt = $conn->prepare($sqlProx);
$stmt->bind_param("i", $idCliente);
$stmt->execute();
$prox = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// ---------- PARA CALIFICAR ----------
$sqlRate = "
  SELECT r.id_reserva, r.fecha, r.hora, r.estado,
         s.id_servicio, s.titulo, s.ubicacion, s.precio,
         u.id_usuario AS id_proveedor, u.usuario AS prov_user, u.nombre, u.apellido, u.avatar_url
  FROM reserva r
  JOIN servicio s ON s.id_servicio = r.id_servicio
  JOIN usuario  u ON u.id_usuario  = s.id_usuario
  LEFT JOIN reseña re ON re.id_reserva = r.id_reserva
  WHERE r.id_cliente = ?
    AND r.estado = 'aceptada'
    AND CONCAT(r.fecha,' ',r.hora) < NOW()
    AND re.id_reserva IS NULL
  ORDER BY r.fecha DESC, r.hora DESC
";
$stmt = $conn->prepare($sqlRate);
$stmt->bind_param("i", $idCliente);
$stmt->execute();
$rate = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mis reservas — TradeConnect</title>
<link rel="stylesheet" href="css/mis_reservas_cliente.css">
<style>
  /* Ajustes de coherencia visual */
  .logo{display:flex;align-items:center;gap:12px}
  .logo img{width:80px;margin-right:15px}
  .logo span{font-size:1.8rem;font-weight:800}

  footer a{
    color:#fff;
    text-decoration:none;
    margin-right:10px;
    font-weight:600;
    transition:opacity .2s ease;
  }
  footer a:hover{opacity:.75;}
</style>
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
    <a class="logout" href="logout.php">Cerrar sesión</a>
  </nav>
</header>

<main class="main">
  <h1>Mis reservas</h1>
  <div class="sub">Acá ves tus solicitudes, próximas citas y las que podés calificar.</div>

  <!-- Pendientes -->
  <section class="section">
    <h2>Pendientes</h2>
    <div class="grid">
      <?php if (!$pend): ?>
        <div class="card muted">No tenés solicitudes pendientes.</div>
      <?php else: foreach($pend as $r): ?>
        <div class="card">
          <div class="row">
            <div class="avatar"><img src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt=""></div>
            <div>
              <div><strong><?= e($r['titulo']) ?></strong></div>
              <div class="muted">@<?= e($r['prov_user']) ?> · <?= e($r['ubicacion']) ?></div>
            </div>
          </div>
          <div><?= e(date('d/m/Y', strtotime($r['fecha']))) ?> · <?= e(substr($r['hora'],0,5)) ?></div>
          <div class="price">$ <?= number_format((float)$r['precio'], 0, ',', '.') ?></div>
          <div style="display:flex;gap:8px;margin-top:6px">
            <a class="btn btn-outline" href="ver_servicio.php?id=<?= (int)$r['id_servicio'] ?>">Ver servicio</a>
            <a class="btn btn-outline" href="mensajes.php?to=<?= (int)$r['id_proveedor'] ?>">Mensaje</a>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <!-- Próximas aceptadas -->
  <section class="section">
    <h2>Próximas</h2>
    <div class="grid">
      <?php if (!$prox): ?>
        <div class="card muted">No tenés próximas reservas.</div>
      <?php else: foreach($prox as $r): ?>
        <div class="card">
          <div class="row">
            <div class="avatar"><img src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt=""></div>
            <div>
              <div><strong><?= e($r['titulo']) ?></strong> <span class="pill">Aceptada</span></div>
              <div class="muted">@<?= e($r['prov_user']) ?> · <?= e($r['ubicacion']) ?></div>
            </div>
          </div>
          <div><?= e(date('d/m/Y', strtotime($r['fecha']))) ?> · <?= e(substr($r['hora'],0,5)) ?></div>
          <div class="price">$ <?= number_format((float)$r['precio'], 0, ',', '.') ?></div>
          <div style="display:flex;gap:8px;margin-top:6px">
            <a class="btn btn-outline" href="mensajes.php?to=<?= (int)$r['id_proveedor'] ?>">Coordinar detalles</a>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
  </section>

  <!-- Para calificar -->
  <section class="section">
    <h2>Para calificar</h2>
    <div class="grid">
      <?php if (!$rate): ?>
        <div class="card muted">Cuando termine un servicio, vas a poder calificarlo acá.</div>
      <?php else: foreach($rate as $r): ?>
        <div class="card">
          <div class="row">
            <div class="avatar"><img src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt=""></div>
            <div>
              <div><strong><?= e($r['titulo']) ?></strong></div>
              <div class="muted">@<?= e($r['prov_user']) ?> · <?= e($r['ubicacion']) ?></div>
            </div>
          </div>
          <div><?= e(date('d/m/Y', strtotime($r['fecha']))) ?> · <?= e(substr($r['hora'],0,5)) ?></div>
          <div style="display:flex;gap:8px;margin-top:6px">
            <a class="btn btn-primary" href="calificar.php?id_reserva=<?= (int)$r['id_reserva'] ?>">Calificar</a>
            <a class="btn btn-outline" href="mensajes.php?to=<?= (int)$r['id_proveedor'] ?>">Contactar</a>
          </div>
        </div>
      <?php endforeach; endif; ?>
    </div>
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
