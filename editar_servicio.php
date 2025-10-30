<?php
// editar_servicio.php
session_start();
require 'conexion.php';

if (!isset($_SESSION['id_usuario'])) {
  header("Location: login.html"); exit;
}
if (($_SESSION['rol'] ?? '') !== 'proveedor') {
  header("Location: inicio.php"); exit;
}

$idProveedor = (int)$_SESSION['id_usuario'];
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// ---------- Servicio a editar ----------
$idServicio = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$idServicio) { header("Location: panel_proveedor.php"); exit; }

// Traer servicio del proveedor
$sql = "SELECT id_servicio, id_usuario, titulo, descripcion, precio, ubicacion, imagen_url
        FROM servicio
        WHERE id_servicio = ? AND id_usuario = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $idServicio, $idProveedor);
$stmt->execute();
$serv = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$serv) { header("Location: panel_proveedor.php"); exit; }

// Traer categoría actual (una sola para este formulario)
$catActual = 0;
$stmt = $conn->prepare("SELECT id_categoria FROM servicio_categoria WHERE id_servicio = ? LIMIT 1");
$stmt->bind_param("i", $idServicio);
$stmt->execute();
$r = $stmt->get_result()->fetch_assoc();
$stmt->close();
if ($r) $catActual = (int)$r['id_categoria'];

// Cargar categorías
$categorias = [];
$res = $conn->query("SELECT id_categoria, nombre FROM categoria ORDER BY nombre");
if ($res) while ($row = $res->fetch_assoc()) $categorias[] = $row;

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

$errores = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf   = $_POST['csrf'] ?? '';
  if (!$postedCsrf || !hash_equals($_SESSION['csrf'], $postedCsrf)) {
    $errores[] = 'La sesión expiró. Volvé a intentar.';
  }

  $titulo      = trim($_POST['titulo'] ?? '');
  $idCategoria = (int)($_POST['categoria'] ?? 0);
  $precioIn    = trim($_POST['precio'] ?? '');
  $ubicacion   = trim($_POST['ubicacion'] ?? '');
  $descripcion = trim($_POST['descripcion'] ?? '');
  $quitarImg   = isset($_POST['quitar_imagen']) && $_POST['quitar_imagen'] === '1';

  // Normalizar precio (acepta 1.000,50 o 1000.50)
  $precioSan = str_replace(['. ',','], ['','.'], $precioIn);
  $precio    = is_numeric($precioSan) ? (float)$precioSan : null;

  if ($titulo === '' || mb_strlen($titulo) > 100) $errores[] = 'Título obligatorio (máx. 100).';
  if ($idCategoria <= 0)                          $errores[] = 'Seleccioná una categoría.';
  if ($precio === null || $precio < 0)            $errores[] = 'Precio inválido.';
  if ($ubicacion === '' || mb_strlen($ubicacion) > 100) $errores[] = 'Ubicación obligatoria (máx. 100).';
  if ($descripcion === '')                        $errores[] = 'Descripción obligatoria.';

  // Manejo de imagen
  $nuevaImagenPath = null;      // si se sube nueva
  $viejaImagenPath = $serv['imagen_url']; // para borrar si corresponde

  if (!$errores) {
    // Si suben una nueva imagen, tiene prioridad sobre "quitar"
    if (!empty($_FILES['imagen']['name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
      $f = $_FILES['imagen'];
      $mime = @mime_content_type($f['tmp_name']);
      $ok = in_array($mime, ['image/jpeg','image/png','image/webp'], true);
      if (!$ok) {
        $errores[] = 'Formato de imagen inválido (JPG/PNG/WEBP).';
      } elseif ($f['size'] > 2*1024*1024) {
        $errores[] = 'La imagen supera 2MB.';
      } else {
        $updir = __DIR__ . '/uploads';
        if (!is_dir($updir)) @mkdir($updir, 0755, true);
        $ext = match($mime){
          'image/jpeg' => 'jpg',
          'image/png'  => 'png',
          'image/webp' => 'webp',
          default      => 'dat'
        };
        $name = 'svc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($f['tmp_name'], "$updir/$name")) {
          $nuevaImagenPath = 'uploads/' . $name;
        } else {
          $errores[] = 'No se pudo guardar la imagen.';
        }
      }
    }
  }

  if (!$errores) {
    $conn->begin_transaction();
    try {
      // Decidir imagen_final
      $imagenFinal = $serv['imagen_url'];
      if ($nuevaImagenPath) {                // nueva imagen
        $imagenFinal = $nuevaImagenPath;
      } elseif ($quitarImg) {                // quitar
        $imagenFinal = null;
      }

      // Actualizar servicio
      $sql = "UPDATE servicio
              SET titulo = ?, descripcion = ?, precio = ?, ubicacion = ?, imagen_url = ?
              WHERE id_servicio = ? AND id_usuario = ?";
      $stmt = $conn->prepare($sql);
      $stmt->bind_param("ssdssii",
        $titulo, $descripcion, $precio, $ubicacion, $imagenFinal, $idServicio, $idProveedor
      );
      $stmt->execute();
      $stmt->close();

      // Actualizar categoría (una)
      $stmt = $conn->prepare("DELETE FROM servicio_categoria WHERE id_servicio = ?");
      $stmt->bind_param("i", $idServicio);
      $stmt->execute();
      $stmt->close();

      $stmt = $conn->prepare("INSERT INTO servicio_categoria (id_servicio, id_categoria) VALUES (?,?)");
      $stmt->bind_param("ii", $idServicio, $idCategoria);
      $stmt->execute();
      $stmt->close();

      $conn->commit();

      // Borrar imagen vieja si la cambiamos o la quitamos (seguro dentro de /uploads)
      if (($nuevaImagenPath || $quitarImg) && $viejaImagenPath) {
        $base = realpath(__DIR__ . '/uploads') . DIRECTORY_SEPARATOR;
        $full = realpath(__DIR__ . '/' . $viejaImagenPath);
        if ($full && str_starts_with($full, $base) && file_exists($full)) {
          @unlink($full);
        }
      }

      header("Location: panel_proveedor.php?edit_ok=1");
      exit;

    } catch (Throwable $e) {
      $conn->rollback();
      $errores[] = 'No se pudieron guardar los cambios.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editar servicio - TradeConnect</title>
<link rel="stylesheet" href="css/editar_servicio.css">
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

<main class="main">
  <div class="hero">
    <h1>Editar Servicio</h1>
    <div class="sub">Actualizá la información de tu publicación.</div>
  </div>

  <?php if (!empty($errores)): ?>
    <div class="error">
      <strong>Revisá estos puntos:</strong>
      <ul>
        <?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form class="form-wrap" method="post" enctype="multipart/form-data">
    <div class="grid">
      <!-- Columna izquierda -->
      <div>
        <label for="titulo">Título *</label>
        <input id="titulo" name="titulo" type="text" maxlength="100"
               value="<?= e($_POST['titulo'] ?? $serv['titulo']) ?>">

        <label for="categoria">Categoría *</label>
        <select id="categoria" name="categoria">
          <option value="">Seleccioná una categoría</option>
          <?php
          $selCat = (int)($_POST['categoria'] ?? $catActual);
          foreach ($categorias as $c):
          ?>
            <option value="<?= (int)$c['id_categoria'] ?>"
              <?= ((int)$c['id_categoria'] === $selCat) ? 'selected' : '' ?>>
              <?= e($c['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label for="precio">Precio (UYU) *</label>
        <input id="precio" name="precio" type="number" step="0.01" min="0"
               value="<?= e($_POST['precio'] ?? (string)$serv['precio']) ?>">

        <label for="ubicacion">Ubicación *</label>
        <input id="ubicacion" name="ubicacion" type="text" maxlength="100"
               value="<?= e($_POST['ubicacion'] ?? $serv['ubicacion']) ?>">

        <label for="descripcion">Descripción *</label>
        <textarea id="descripcion" name="descripcion"><?= e($_POST['descripcion'] ?? $serv['descripcion']) ?></textarea>

        <div class="actions">
          <a class="btn btn-outline" href="panel_proveedor.php">Cancelar</a>
          <button class="btn btn-green" type="submit">Guardar</button>
        </div>
      </div>

      <!-- Columna derecha (preview / imagen) -->
      <div class="media">
        <div class="preview">
          <?php
          $imgSrc = $serv['imagen_url'] ?: 'images/background-herramientas.jpg.png';
          ?>
          <img id="imgPreview" src="<?= e($imgSrc) ?>" alt="Previsualización">
        </div>

        <div class="actions" style="margin-top:14px">
          <label class="btn btn-primary" style="cursor:pointer;">
            Reemplazar imagen
            <input id="imagen" name="imagen" type="file" accept=".jpg,.jpeg,.png,.webp" style="display:none">
          </label>
        </div>

        <?php if (!empty($serv['imagen_url'])): ?>
          <div class="hint">
            <label style="cursor:pointer;">
              <input type="checkbox" name="quitar_imagen" value="1">
              Quitar imagen actual
            </label>
          </div>
        <?php endif; ?>

        <div class="hint">Formatos: JPG/PNG/WEBP · Tamaño máx.: 2MB.</div>
      </div>
    </div>

    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
  </form>
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
// Preview de imagen
const input = document.getElementById('imagen');
const img   = document.getElementById('imgPreview');
input?.addEventListener('change', () => {
  const f = input.files?.[0];
  if (!f) return;
  const ok = ['image/jpeg','image/png','image/webp'].includes(f.type);
  if (!ok) { alert('Formato no soportado'); input.value=''; return; }
  img.src = URL.createObjectURL(f);
});
</script>
</body>
</html>
