<?php
// calificar.php
session_start();
require 'conexion.php';
require_once 'utils.php';

if (!isset($_SESSION['id_usuario'])) { header("Location: login.html"); exit; }
if (($_SESSION['rol'] ?? '') !== 'cliente') { header("Location: inicio.php"); exit; }

$idCliente = (int)$_SESSION['id_usuario'];
$idReserva = (int)($_GET['id_reserva'] ?? $_POST['id_reserva'] ?? 0);
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

if ($idReserva <= 0) { header("Location: mis_reservas_cliente.php"); exit; }

// Traer reserva + si ya tiene reseña
$sql = "
  SELECT r.id_reserva, r.fecha, r.hora, r.estado,
         s.titulo, p.usuario, p.nombre, p.apellido, p.avatar_url
  FROM reserva r
  JOIN servicio s ON s.id_servicio = r.id_servicio
  JOIN usuario  p ON p.id_usuario  = s.id_usuario
  WHERE r.id_reserva = ? AND r.id_cliente = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $idReserva, $idCliente);
$stmt->execute();
$rv = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rv) { die("Reserva no encontrada."); }

$yaPaso = (strtotime($rv['fecha'].' '.$rv['hora']) < time());

// Ya existe reseña?
$stmt = $conn->prepare("SELECT id_reseña FROM `reseña` WHERE id_reserva = ? LIMIT 1");
$stmt->bind_param("i", $idReserva);
$stmt->execute();
$yaTiene = (bool)$stmt->get_result()->fetch_row();
$stmt->close();

if (!$yaPaso) { die("Todavía no podés calificar esta reserva."); }
if ($yaTiene) { die("Esta reserva ya fue calificada."); }

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $rating = (int)($_POST['rating'] ?? 0);
  $coment = trim($_POST['comentario'] ?? '');

  if ($rating < 1 || $rating > 5) $errores[] = "Elegí una calificación de 1 a 5 estrellas.";
  if (mb_strlen($coment) > 1000)  $errores[] = "El comentario es muy largo (máx. 1000).";

  if (!$errores) {
    $stmt = $conn->prepare("INSERT INTO `reseña` (`id_reserva`, `calificación`, `comentario`, `fecha`) VALUES (?, ?, ?, NOW())");
    $stmt->bind_param("iis", $idReserva, $rating, $coment);
    $ok1 = $stmt->execute();
    $stmt->close();

    if ($ok1) {
      header("Location: mis_reservas_cliente.php?ok=1");
      exit;
    } else {
      $errores[] = "No se pudo guardar la reseña.";
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Calificar servicio - TradeConnect</title>
<link rel="stylesheet" href="css/calificar.css">
</head>
<body>
<header>
  <div class="logo"><img src="images/logo.png" alt="Logo"><span><strong>Trade</strong>Connect</span></div>
  <nav>
    <a href="panel_cliente.php">Inicio</a>
    <a href="mis_reservas_cliente.php">Mis reservas</a>
    <a class="logout" href="logout.php">Cerrar sesión</a>
  </nav>
</header>

<main class="main">
  <div class="panel">
    <div class="prov">
      <div class="avatar"><img src="<?= e(avatar_url($rv['avatar_url'] ?? null)) ?>" alt=""></div>
      <div>
        <div><strong><?= e($rv['titulo']) ?></strong></div>
        <div class="muted"><?= e(date('d/m/Y', strtotime($rv['fecha']))) ?> · <?= e(substr($rv['hora'],0,5)) ?> · Proveedor @<?= e($rv['usuario']) ?></div>
      </div>
    </div>

    <?php if ($errores): ?>
      <div class="error"><?= e(implode(' ', $errores)) ?></div>
    <?php endif; ?>

    <form method="post">
      <input type="hidden" name="id_reserva" value="<?= (int)$idReserva ?>">

      <div class="stars rtl">
        <input type="radio" id="s5" name="rating" value="5"><label for="s5">⭐</label>
        <input type="radio" id="s4" name="rating" value="4"><label for="s4">⭐</label>
        <input type="radio" id="s3" name="rating" value="3"><label for="s3">⭐</label>
        <input type="radio" id="s2" name="rating" value="2"><label for="s2">⭐</label>
        <input type="radio" id="s1" name="rating" value="1" checked><label for="s1">⭐</label>
      </div>

      <label for="coment" class="label">Comentario (opcional)</label>
      <textarea id="coment" name="comentario" maxlength="1000" placeholder="¿Cómo te fue con el servicio?"></textarea>

      <div class="row-actions">
        <a class="btn btn-outline" href="mis_reservas_cliente.php">Volver</a>
        <button class="btn btn-accent" type="submit">Enviar reseña</button>
      </div>
    </form>
  </div>
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
