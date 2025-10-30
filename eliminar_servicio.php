<?php
session_start();
require 'conexion.php';

if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.html");
    exit;
}
if (($_SESSION['rol'] ?? '') !== 'proveedor') {
    header("Location: inicio.php");
    exit;
}

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$idProveedor = (int)($_SESSION['id_usuario'] ?? 0);

/* -------------------------------------------
   MODO POST: ejecutar eliminación definitiva
-------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $csrf = $_POST['csrf'] ?? '';
    $id   = isset($_POST['id']) ? (int)$_POST['id'] : 0;

    if (!$id || !$csrf || !hash_equals($_SESSION['csrf'] ?? '', $csrf)) {
        header("Location: panel_proveedor.php?del_err=1");
        exit;
    }

    // Verificar que el servicio sea del proveedor y obtener imagen
    $sql = "SELECT imagen_url FROM servicio WHERE id_servicio = ? AND id_usuario = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $id, $idProveedor);
    $stmt->execute();
    $res = $stmt->get_result();
    $serv = $res->fetch_assoc();
    $stmt->close();

    if (!$serv) {
        header("Location: panel_proveedor.php?del_err=1");
        exit;
    }

    // Eliminar relaciones y servicio (transacción)
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("DELETE FROM servicio_categoria WHERE id_servicio = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();

        $stmt = $conn->prepare("DELETE FROM servicio WHERE id_servicio = ? AND id_usuario = ?");
        $stmt->bind_param("ii", $id, $idProveedor);
        $stmt->execute();
        $rows = $stmt->affected_rows;
        $stmt->close();

        if ($rows < 1) {
            $conn->rollback();
            header("Location: panel_proveedor.php?del_err=1");
            exit;
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        header("Location: panel_proveedor.php?del_err=1");
        exit;
    }

    // Intentar sacar la imagen de /uploads
    $path = $serv['imagen_url'] ?? '';
    if ($path) {
        $base = __DIR__ . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR;
        $full = realpath($base . basename($path));
        if ($full && str_starts_with($full, $base) && file_exists($full)) {
            @unlink($full);
        }
    }

    header("Location: panel_proveedor.php?del_ok=1");
    exit;
}

/* -------------------------------------------
   MODO GET: mostrar confirmación
-------------------------------------------- */
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id < 1) {
    header("Location: panel_proveedor.php");
    exit;
}

// Generar CSRF si no existe
if (empty($_SESSION['csrf'])) {
    $_SESSION['csrf'] = bin2hex(random_bytes(16));
}

// Traer datos del servicio (solo si pertenece al proveedor)
$sql = "
  SELECT s.id_servicio, s.titulo, s.descripcion, s.precio, s.ubicacion, s.imagen_url, s.fecha_publicacion,
         COALESCE(GROUP_CONCAT(c.nombre ORDER BY c.nombre SEPARATOR ', '), '') AS categorias
  FROM servicio s
  LEFT JOIN servicio_categoria sc ON sc.id_servicio = s.id_servicio
  LEFT JOIN categoria c           ON c.id_categoria = sc.id_categoria
  WHERE s.id_servicio = ? AND s.id_usuario = ?
  GROUP BY s.id_servicio
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $id, $idProveedor);
$stmt->execute();
$servicio = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$servicio) {
    header("Location: panel_proveedor.php?del_err=1");
    exit;
}

$img = trim((string)($servicio['imagen_url'] ?? ''));
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Confirmar eliminación - TradeConnect</title>
<link rel="stylesheet" href="css/eliminar_servicio.css">
</head>
<body>
<header>
  <div class="logo">
    <img src="images/logo.png" alt="Logo">
    <span><strong>Trade</strong>Connect</span>
  </div>
  <nav>
    <a href="panel_proveedor.php">Inicio</a>
    <a href="mensajes.php">Mensajes</a>
    <a class="logout" href="logout.php">Cerrar sesión</a>
  </nav>
</header>

<main class="wrap">
  <h1>Eliminar servicio</h1>
  <p class="muted">Revisá la información antes de confirmar. Esta acción no se puede deshacer.</p>

  <div class="box">
    <div class="info">
      <p><span class="label">Título:</span> <span class="value"><?= e($servicio['titulo']) ?></span></p>
      <?php if (!empty($servicio['categorias'])): ?>
        <p><span class="label">Categorías:</span> <span class="value"><?= e($servicio['categorias']) ?></span></p>
      <?php endif; ?>
      <p><span class="label">Precio:</span> <span class="value">$ <?= number_format((float)$servicio['precio'], 0, ',', '.') ?></span></p>
      <p><span class="label">Ubicación:</span> <span class="value"><?= e($servicio['ubicacion']) ?></span></p>
      <?php if (!empty($servicio['descripcion'])): ?>
        <p><span class="label">Descripción:</span><br><?= nl2br(e($servicio['descripcion'])) ?></p>
      <?php endif; ?>

      <div class="confirm">
        <strong>¿Estás seguro de que querés eliminar este servicio?</strong>
        <div class="cta">
          <form action="eliminar_servicio.php" method="post" style="display:inline">
            <input type="hidden" name="csrf" value="<?= e($_SESSION['csrf']) ?>">
            <input type="hidden" name="id" value="<?= (int)$servicio['id_servicio'] ?>">
            <button type="submit" class="btn btn-success">Confirmar eliminación</button>
          </form>
          <a href="panel_proveedor.php" class="btn btn-danger">Cancelar</a>
        </div>
      </div>
    </div>

    <div class="imgbox">
      <?php if ($img): ?>
        <img src="<?= e($img) ?>" alt="Imagen del servicio">
      <?php else: ?>
        <div class="muted">Sin imagen</div>
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
</body>
</html>
