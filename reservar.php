<?php
// reservar.php  (reservas por DÍA + HORA)
session_start();
require 'conexion.php';
require_once 'utils.php';

if (!isset($_SESSION['id_usuario'])) { header("Location: login.html"); exit; }
if (($_SESSION['rol'] ?? '') !== 'cliente') { header("Location: inicio.php"); exit; }

$idCliente = (int)$_SESSION['id_usuario'];
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Servicio
$idServicio = (int)($_GET['id_servicio'] ?? $_POST['id_servicio'] ?? 0);
if ($idServicio <= 0) { header("Location: panel_cliente.php"); exit; }

$stmt = $conn->prepare("
  SELECT s.id_servicio, s.titulo, s.precio, s.ubicacion, s.id_usuario AS id_proveedor,
         u.nombre, u.apellido, u.usuario, u.avatar_url
  FROM servicio s
  JOIN usuario u ON u.id_usuario = s.id_usuario
  WHERE s.id_servicio = ? LIMIT 1
");
$stmt->bind_param("i", $idServicio);
$stmt->execute();
$svc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$svc) { die("Servicio no encontrado."); }
$idProveedor = (int)$svc['id_proveedor'];
if ($idProveedor === $idCliente) { die("No podés contratar tu propio servicio."); }

$errores = [];

/* ===== Requisito: mínimo 1 día de anticipación (día completo) =====
   Se permite reservar cualquier hora del *día siguiente en adelante*. */
$minDayPHP   = new DateTime('tomorrow');     // 00:00 del día siguiente
$minDateYmd  = $minDayPHP->format('Y-m-d');  // para <input type="date" min=...>

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $fecha = trim($_POST['fecha'] ?? '');    // YYYY-MM-DD
  $hora  = trim($_POST['hora']  ?? '');    // HH:MM (24h)
  $nota  = trim($_POST['nota']  ?? '');    // solo para aviso por mensaje

  // Validaciones básicas
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) $errores[] = "Fecha inválida.";
  if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $hora))   $errores[] = "Hora inválida (formato 24h HH:MM).";

  if (!$errores) {
    $horaSQL = $hora . ':00';

    // Validación de *día completo*: fecha debe ser >= mañana
    if ($fecha < $minDateYmd) {
      $errores[] = "La reserva debe realizarse con al menos 1 día de anticipación (primer día disponible: ".$minDayPHP->format('d/m/Y').").";
    }

    // Conflicto exacto (día + hora)
    if (!$errores) {
      $sql = "
        SELECT 1
        FROM reserva r
        JOIN servicio s ON s.id_servicio = r.id_servicio
        WHERE s.id_usuario = ?
          AND r.fecha = ?
          AND r.hora  = ?
          AND r.estado IN ('pendiente','aceptada')
        LIMIT 1
      ";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("iss", $idProveedor, $fecha, $horaSQL);
      $stmt->execute();
      $ocupado = (bool)$stmt->get_result()->fetch_row();
      $stmt->close();

      if ($ocupado) $errores[] = "El proveedor ya tiene una reserva en esa franja.";
    }
  }

  // Insertar
  if (!$errores) {
    $stmt = $conn->prepare("
      INSERT INTO reserva (id_servicio, id_cliente, fecha, hora, estado)
      VALUES (?, ?, ?, ?, 'pendiente')
    ");
    $stmt->bind_param("iiss", $idServicio, $idCliente, $fecha, $horaSQL);
    if ($stmt->execute()) {
      $stmt->close();

      // Aviso por mensaje (extra)
      $texto = "Nueva solicitud de reserva para \"{$svc['titulo']}\" el ".
               date('d/m/Y', strtotime($fecha))." a las ".substr($horaSQL,0,5).
               ". Nota: ".($nota !== '' ? $nota : '—');

      $stmt = $conn->prepare("
        INSERT INTO mensaje (id_emisor, id_receptor, contenido, fecha_envio, leido, eliminado_por_emisor, eliminado_por_receptor)
        VALUES (?, ?, ?, NOW(), 0, 0, 0)
      ");
      $stmt->bind_param("iis", $idCliente, $idProveedor, $texto);
      $stmt->execute();
      $stmt->close();

      header("Location: panel_cliente.php?res_ok=1");
      exit;
    } else {
      $errores[] = "No se pudo crear la reserva.";
      $stmt->close();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Contratar (por día) - <?= e($svc['titulo']) ?> | TradeConnect</title>
<link rel="stylesheet" href="css/reservar.css">
</head>
<body>
<header>
  <div class="logo">
    <img src="images/logo.png" alt="Logo"><span><strong>Trade</strong>Connect</span>
  </div>
  <nav>
    <a href="panel_cliente.php">Inicio</a>
    <a href="categorias.php">Categorías</a>
    <a href="mensajes.php">Bandeja de entrada</a>
    <a class="logout" href="logout.php">Cerrar sesión</a>
  </nav>
</header>

<main class="main">
  <div class="grid">
    <div class="panel">
      <h1>Contratar (por día): <?= e($svc['titulo']) ?></h1>

      <!-- Aviso permanente del requisito de 1 día -->
      <div class="info">
        Las reservas se realizan con <strong>1 día</strong> de anticipación.
        Primer día disponible: <strong><?= e($minDayPHP->format('d/m/Y')) ?></strong>.
      </div>

      <?php if ($errores): ?>
        <div class="error"><?= e(implode(' ', $errores)) ?></div>
      <?php endif; ?>

      <form method="post" novalidate>
        <input type="hidden" name="id_servicio" value="<?= (int)$idServicio ?>">

        <label for="fecha">Fecha</label>
        <input id="fecha" name="fecha" type="date" required min="<?= e($minDateYmd) ?>">

        <label for="hora">Hora</label>
        <input id="hora" name="hora" type="text" required
               placeholder="HH:MM (24h)"
               inputmode="numeric"
               maxlength="5"
               pattern="^(?:[01]\d|2[0-3]):[0-5]\d$"
               title="Usá formato 24h: HH:MM (ej.: 08:30, 14:00)">

        <label for="nota">Nota para el proveedor (opcional)</label>
        <textarea id="nota" name="nota" placeholder="Detalle breve del trabajo, acceso, materiales, etc. (se envía por mensaje)"></textarea>

        <div class="actions">
          <a class="btn btn-outline" href="ver_servicio.php?id=<?= (int)$idServicio ?>">Volver</a>
          <button class="btn btn-primary" type="submit">Confirmar solicitud</button>
        </div>
      </form>
    </div>

    <div class="panel">
      <h3>Proveedor</h3>
      <div class="prov">
        <div class="avatar"><img src="<?= e(avatar_url($svc['avatar_url'] ?? null)) ?>" alt=""></div>
        <div>
          <div style="font-weight:800"><?= e(trim(($svc['nombre'] ?? '').' '.($svc['apellido'] ?? ''))) ?></div>
          <div class="muted">@<?= e($svc['usuario']) ?></div>
          <div class="muted"><?= e($svc['ubicacion']) ?></div>
        </div>
      </div>
      <hr style="border-color:#ffffff24;border-width:0 0 1px;margin:16px 0">
      <div class="muted">Precio referencial: <strong>$ <?= number_format((float)$svc['precio'], 0, ',', '.') ?></strong></div>
      <div class="muted">La solicitud quedará <strong>pendiente</strong> hasta que el proveedor confirme.</div>
    </div>
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

<script>
// UX: setear por defecto el primer día disponible (mañana)
const f = document.getElementById('fecha');
if (f && !f.value) f.value = "<?= e($minDateYmd) ?>";

// Autoinsertar ":" y limitar a HH:MM para móviles
const h = document.getElementById('hora');
if (h){
  h.addEventListener('input', () => {
    let v = h.value.replace(/[^\d]/g,'').slice(0,4);
    if (v.length >= 3) v = v.slice(0,2) + ':' + v.slice(2);
    h.value = v;
  });
}
</script>
</body>
</html>
