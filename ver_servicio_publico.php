<?php
// ver_servicio_publico.php
session_start();
require 'conexion.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header("Location: buscar.php"); exit; }

// Traer servicio (visible y proveedor no baneado)
$sql = "
  SELECT s.id_servicio, s.titulo, s.descripcion, s.precio, s.ubicacion, s.imagen_url,
         u.nombre, u.apellido,
         COALESCE(GROUP_CONCAT(DISTINCT c.nombre ORDER BY c.nombre SEPARATOR ', '),'') AS categorias
  FROM servicio s
  JOIN usuario u ON u.id_usuario = s.id_usuario
  LEFT JOIN servicio_categoria sc ON sc.id_servicio = s.id_servicio
  LEFT JOIN categoria c ON c.id_categoria = sc.id_categoria
  WHERE s.id_servicio = ?
    AND s.oculto = 0
    AND u.baneado = 0
  GROUP BY s.id_servicio
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $id);
$stmt->execute();
$svc = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$svc) { header("Location: buscar.php"); exit; }

// Para header
$inicioHref = 'index.html';
$rol = $_SESSION['rol'] ?? null;
if (isset($_SESSION['id_usuario'])) {
  if ($rol === 'cliente')       $inicioHref = 'panel_cliente.php';
  elseif ($rol === 'proveedor') $inicioHref = 'panel_proveedor.php';
  else                          $inicioHref = 'inicio.php';
}

// Imagen fallback
$img = $svc['imagen_url'] ?: 'uploads/placeholder.webp';
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($svc['titulo']) ?> — TradeConnect</title>
<style>
  :root{
    --ink:#fff; --bg:#0a0f1a;
    --card:rgba(255,255,255,.06); --bd:rgba(255,255,255,.16);
    --muted:#cfe9ff; --accent:#3399ff; --accent-ink:#001018;
  }
  *{box-sizing:border-box}
  body{
    margin:0; font-family:'Segoe UI',sans-serif; color:var(--ink);
    background: var(--bg) url('images/background-blur.png') center/cover fixed;
    min-height:100vh; display:flex; flex-direction:column;
  }
  header, footer{display:flex;justify-content:space-between;align-items:center;padding:16px 24px;background:rgba(0,0,0,.35)}
  nav a{color:#fff;margin:0 15px;text-decoration:none;font-weight:700;font-size:1.05rem}
  .logout{color:#ff6b6b}
  .logo{display:flex;align-items:center;gap:12px}
  .logo img{width:80px}
  .logo span{font-size:1.8em;font-weight:800}

  .main{flex:1;max-width:1100px;margin:0 auto;padding:36px 18px}
  .card{
    display:grid; grid-template-columns: 1.2fr 1fr; gap:20px;
    background:rgba(0,0,0,.38); border:1px solid var(--bd); border-radius:18px;
    overflow:hidden; box-shadow:0 16px 46px rgba(0,0,0,.35)
  }
  @media (max-width:980px){ .card{ grid-template-columns:1fr } }
  .media{ background:#000; min-height:280px }
  .media img{ width:100%; height:100%; object-fit:cover; display:block }
  .body{ padding:18px 18px 22px }
  h1{ margin:0 0 8px }
  .row{ display:flex; gap:10px; flex-wrap:wrap; margin:10px 0 }
  .pill{
    display:inline-block; padding:6px 10px; border-radius:999px;
    background:rgba(0,198,255,.15); border:1px solid rgba(0,198,255,.35); font-size:.9rem;
  }
  .price{ font-weight:900; font-size:1.1rem }
  .muted{ opacity:.9 }
  .desc{ line-height:1.6; margin-top:8px }
  .cta{
    margin-top:14px; display:flex; gap:10px; flex-wrap:wrap;
  }
  .btn{
    text-decoration:none; display:inline-block; border:none; border-radius:12px; font-weight:900; cursor:pointer;
    padding:12px 16px; font-size:1rem;
  }
  .btn-primary{
    background:linear-gradient(135deg,#38d0ff,#0aa3ff); color:var(--accent-ink);
    box-shadow:0 10px 28px rgba(0,166,255,.35);
  }
  .btn-outline{ background:transparent; border:1px solid rgba(255,255,255,.35); color:#fff }
  .hint{ opacity:.9; font-size:.95rem }
  footer a, footer a:visited, footer a:hover, footer a:active{ color:#fff; text-decoration:none; margin-right:12px; font-size:.95rem }
</style>
</head>
<body>
<header>
  <div class="logo">
    <img src="images/logo.png" alt="Logo">
    <span><strong>Trade</strong>Connect</span>
  </div>
  <nav>
    <a href="<?= e($inicioHref) ?>">Inicio</a>
    <a href="categorias.php">Categorías</a>
    <a href="acerca.php">Acerca de</a>
    <?php if (!isset($_SESSION['id_usuario'])): ?>
      <a href="login.html">Ingresar</a>
    <?php else: ?>
      <a class="logout" href="logout.php">Cerrar sesión</a>
    <?php endif; ?>
  </nav>
</header>

<main class="main">
  <div class="card">
    <div class="media">
      <img src="<?= e($img) ?>" alt="<?= e($svc['titulo']) ?>" onerror="this.src='uploads/placeholder.webp'">
    </div>
    <div class="body">
      <h1><?= e($svc['titulo']) ?></h1>

      <div class="row">
        <?php if (!empty($svc['categorias'])): ?>
          <span class="pill"><?= e($svc['categorias']) ?></span>
        <?php endif; ?>
        <span class="pill">📍 <?= e($svc['ubicacion']) ?></span>
        <span class="pill price">$ <?= number_format((float)$svc['precio'], 0, ',', '.') ?></span>
      </div>

      <div class="muted">Proveedor: <?= e(trim(($svc['nombre'] ?? '').' '.($svc['apellido'] ?? ''))) ?></div>

      <div class="desc"><?= nl2br(e(mb_strimwidth($svc['descripcion'] ?? 'Sin descripción', 0, 700, '…', 'UTF-8'))) ?></div>

      <div class="cta">
        <a class="btn btn-primary" href="login.html">¿Querés saber más? Iniciá sesión</a>
        <a class="btn btn-outline" href="register.html">Crear cuenta</a>
      </div>
      <div class="hint">Al iniciar sesión vas a poder chatear con el proveedor, ver datos completos y reservar.</div>
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
</body>
</html>
