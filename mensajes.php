<?php
// mensajes.php
session_start();
require 'conexion.php';
require_once 'utils.php';

if (!isset($_SESSION['id_usuario'])) { header("Location: login.html"); exit; }

$idYo = (int)$_SESSION['id_usuario'];
$rol  = $_SESSION['rol'] ?? '';
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ¿Con quién chateamos?
$idTo = isset($_GET['to']) ? (int)$_GET['to'] : 0;
if ($idTo === $idYo) $idTo = 0;

// ¿Vino un servicio desde el cual se abrió el chat?
$idServicio = isset($_GET['servicio']) ? (int)$_GET['servicio'] : 0;
$svcTitulo  = null;

// Si vino ?servicio= y no vino ?to=, deducir el proveedor y redirigir manteniendo servicio
if ($idTo === 0 && $idServicio > 0) {
  $stmt = $conn->prepare("SELECT id_usuario, titulo FROM servicio WHERE id_servicio=? LIMIT 1");
  $stmt->bind_param("i", $idServicio);
  $stmt->execute();
  $res = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($res) {
    $idTo = (int)$res['id_usuario'];
    if ($idTo === $idYo) {
      $idTo = 0; // no abrir chat con uno mismo
    } else {
      // Redirigir limpiamente a ?to=...&servicio=...
      header("Location: mensajes.php?to=".$idTo."&servicio=".$idServicio);
      exit;
    }
  }
}

// Si ya tenemos servicio y to, traemos el título (para prefill)
if ($idServicio > 0) {
  $stmt = $conn->prepare("SELECT titulo FROM servicio WHERE id_servicio=? LIMIT 1");
  $stmt->bind_param("i", $idServicio);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($row) $svcTitulo = $row['titulo'];
}

// Enviar mensaje
$errores = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['para'], $_POST['contenido'])) {
  $para = (int)$_POST['para'];
  $contenido = trim($_POST['contenido']);
  if ($para <= 0 || $para === $idYo) $errores[] = 'Destino inválido.';
  if ($contenido === '') $errores[] = 'Escribí un mensaje.';
  if (!$errores) {
    $stmt = $conn->prepare("
      INSERT INTO mensaje (id_emisor, id_receptor, contenido, fecha_envio, leido, eliminado_por_emisor, eliminado_por_receptor)
      VALUES (?, ?, ?, NOW(), 0, 0, 0)
    ");
    $stmt->bind_param("iis", $idYo, $para, $contenido);
    if ($stmt->execute()) {
      $idTo = $para;
    }
    $stmt->close();
    // Ya no necesitamos conservar ?servicio= tras enviar
    header("Location: mensajes.php?to=".$idTo);
    exit;
  }
}

// Lista de conversaciones
$conv = [];
$sqlConv = "
  SELECT u.id_usuario, u.nombre, u.apellido, u.usuario, u.avatar_url,
         MAX(m.fecha_envio) AS last_time
  FROM mensaje m
  JOIN usuario u
    ON u.id_usuario = CASE WHEN m.id_emisor = ? THEN m.id_receptor ELSE m.id_emisor END
  WHERE (m.id_emisor = ? AND (m.eliminado_por_emisor IS NULL OR m.eliminado_por_emisor=0))
     OR (m.id_receptor = ? AND (m.eliminado_por_receptor IS NULL OR eliminado_por_receptor=0))
  GROUP BY u.id_usuario
  ORDER BY last_time DESC
";
$stmt = $conn->prepare($sqlConv);
$stmt->bind_param("iii", $idYo, $idYo, $idYo);
$stmt->execute();
$r = $stmt->get_result();
while ($row = $r->fetch_assoc()) $conv[] = $row;
$stmt->close();

// Asegurar que el destinatario (si vino por ?to=) aparezca en la lista
if ($idTo > 0 && !array_filter($conv, fn($c)=> (int)$c['id_usuario']===$idTo)) {
  $stmt = $conn->prepare("SELECT id_usuario, nombre, apellido, usuario, avatar_url FROM usuario WHERE id_usuario=? LIMIT 1");
  $stmt->bind_param("i", $idTo);
  $stmt->execute();
  if ($u = $stmt->get_result()->fetch_assoc()) $conv = array_merge([ $u ], $conv);
  $stmt->close();
}

// Mensajes de la conversación
$mensajes = [];
$destino = null;
if ($idTo > 0) {
  $stmt = $conn->prepare("SELECT id_usuario, nombre, apellido, usuario, avatar_url FROM usuario WHERE id_usuario=? LIMIT 1");
  $stmt->bind_param("i", $idTo);
  $stmt->execute();
  $destino = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if ($destino) {
    $stmt = $conn->prepare("UPDATE mensaje SET leido=1 WHERE id_emisor=? AND id_receptor=? AND leido=0");
    $stmt->bind_param("ii", $idTo, $idYo);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("
      SELECT id_mensaje, id_emisor, id_receptor, contenido, fecha_envio
      FROM mensaje
      WHERE (id_emisor=? AND id_receptor=? AND (eliminado_por_emisor IS NULL OR eliminado_por_emisor=0))
         OR (id_emisor=? AND id_receptor=? AND (eliminado_por_receptor IS NULL OR eliminado_por_receptor=0))
      ORDER BY fecha_envio ASC, id_mensaje ASC
    ");
    $stmt->bind_param("iiii", $idYo, $idTo, $idTo, $idYo);
    $stmt->execute();
    $r = $stmt->get_result();
    while ($row = $r->fetch_assoc()) $mensajes[] = $row;
    $stmt->close();
  }
}

// Prefill del texto inicial si se abrió desde un servicio y el usuario aún no escribió
$textoInicial = '';
if ($destino && $idServicio > 0 && $svcTitulo && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  // Si no hay mensajes previos con esta persona, sugerimos un primer mensaje
  if (count($mensajes) === 0) {
    $textoInicial = "Hola, vi tu servicio «{$svcTitulo}». ¿Está disponible esta semana?";
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mensajes - TradeConnect</title>
<link rel="stylesheet" href="css/mensajes.css">
</head>
<body>
<header>
  <div class="logo">
    <img src="images/logo.png" alt="Logo"><span><strong>Trade</strong>Connect</span>
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
      <!-- Sin Categorías para proveedor -->
      <a href="editar_perfil.php">Editar Perfil</a>
      <a href="mensajes.php">Bandeja de Entrada</a>
      <a class="logout" href="logout.php">Cerrar sesión</a>
    <?php else: ?>
      <a href="inicio.php">Inicio</a>
      <a href="categorias.php">Categorías</a>
      <a href="login.html">Ingresar</a>
    <?php endif; ?>
  </nav>
</header>

<main class="main">
  <h1>Mensajes</h1>

  <div class="grid">
    <!-- Conversaciones -->
    <div class="panel convs">
      <?php if (!$conv): ?>
        <div class="empty">Todavía no tenés conversaciones.</div>
      <?php else: foreach ($conv as $c): ?>
        <a class="conv-item <?= ($idTo === (int)$c['id_usuario']) ? 'active':'' ?>" href="mensajes.php?to=<?= (int)$c['id_usuario'] ?>">
          <div class="avatar"><img src="<?= e(avatar_url($c['avatar_url'] ?? null)) ?>" alt=""></div>
          <div class="conv-text">
            <span class="name"><?= e(trim(($c['nombre'] ?? '').' '.($c['apellido'] ?? ''))) ?></span>
            <span class="muted">@<?= e($c['usuario'] ?? '') ?></span>
          </div>
        </a>
      <?php endforeach; endif; ?>
    </div>

    <!-- Chat -->
    <div class="panel chat">
      <div class="chat-head">
        <?php if ($destino): ?>
          <div class="avatar"><img src="<?= e(avatar_url($destino['avatar_url'] ?? null)) ?>" alt=""></div>
          <div>
            <div style="font-weight:800"><?= e(trim(($destino['nombre'] ?? '').' '.($destino['apellido'] ?? ''))) ?></div>
            <div class="muted">@<?= e($destino['usuario'] ?? '') ?></div>
          </div>
        <?php else: ?>
          <div class="muted">Elegí una conversación de la izquierda, o iniciá una desde un servicio.</div>
        <?php endif; ?>
      </div>

      <div class="chat-body" id="msgs">
        <?php if ($destino && !$mensajes): ?>
          <div class="muted">Todavía no hay mensajes. ¡Escribí el primero!</div>
        <?php endif; ?>
        <?php foreach ($mensajes as $m): ?>
          <div class="msg <?= ((int)$m['id_emisor']===$idYo)?'me':'' ?>">
            <div><?= nl2br(e($m['contenido'])) ?></div>
            <div class="meta"><?= e(date('d/m/Y H:i', strtotime($m['fecha_envio']))) ?></div>
          </div>
        <?php endforeach; ?>
      </div>

      <?php if ($destino): ?>
        <?php if ($errores): ?>
          <div class="error"><?php foreach($errores as $er) echo e($er).' '; ?></div>
        <?php endif; ?>
        <form class="composer" method="post">
          <textarea id="txt" name="contenido" placeholder="Escribí tu mensaje..." required><?= e($textoInicial) ?></textarea>
          <input type="hidden" name="para" value="<?= (int)$idTo ?>">
          <button class="btn btn-primary" type="submit">Enviar</button>
        </form>
      <?php endif; ?>
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
  // scroll al final del chat
  const box = document.getElementById('msgs'); box && (box.scrollTop = box.scrollHeight);

  // autosize del textarea
  const ta = document.getElementById('txt');
  if (ta) {
    const autosize = () => {
      ta.style.height = '56px';
      ta.style.height = Math.min(ta.scrollHeight, 160) + 'px';
    };
    ta.addEventListener('input', autosize);
    autosize();
  }
</script>
</body>
</html>