<?php
// publicar_servicio.php
session_start();
require 'conexion.php';

if (!isset($_SESSION['id_usuario'])) {
  header("Location: login.html"); exit;
}
if (($_SESSION['rol'] ?? '') !== 'proveedor') {
  header("Location: inicio.php"); exit;
}

$idProveedor = (int)$_SESSION['id_usuario'];
$errores = [];
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Cargar categorías
$categorias = [];
$res = $conn->query("SELECT id_categoria, nombre FROM categoria ORDER BY nombre");
if ($res) while ($row = $res->fetch_assoc()) $categorias[] = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $titulo      = trim($_POST['titulo'] ?? '');
  $idCategoria = (int)($_POST['categoria'] ?? 0);
  $precio      = trim($_POST['precio'] ?? '');
  $ubicacion   = trim($_POST['ubicacion'] ?? '');
  $descripcion = trim($_POST['descripcion'] ?? '');
  $imgPath     = null;

  if ($titulo === '' || mb_strlen($titulo) > 100) $errores[] = 'Título obligatorio (máx. 100).';
  if ($idCategoria <= 0)                          $errores[] = 'Seleccioná una categoría.';
  if ($precio === '' || !is_numeric($precio) || (float)$precio < 0) $errores[] = 'Precio inválido.';
  if ($ubicacion === '' || mb_strlen($ubicacion) > 100) $errores[] = 'Ubicación obligatoria (máx. 100).';
  if ($descripcion === '')                        $errores[] = 'Descripción obligatoria.';

  if (empty($_FILES['imagen']['name'])) {
    $errores[] = 'Debes seleccionar una imagen de portada.';
  } else {
    $f = $_FILES['imagen'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      $mime = @mime_content_type($f['tmp_name']);
      $ok = in_array($mime, ['image/jpeg','image/png','image/webp'], true);
      if (!$ok) {
        $errores[] = 'Formato de imagen inválido (JPG/PNG/WEBP).';
      } elseif ($f['size'] > 2*1024*1024) {
        $errores[] = 'La imagen supera 2MB.';
      } else {
        $updir = __DIR__ . '/uploads';
        if (!is_dir($updir)) @mkdir($updir, 0755, true);
        $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $name = 'svc_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($f['tmp_name'], "$updir/$name")) {
          $imgPath = 'uploads/' . $name;
        } else {
          $errores[] = 'No se pudo guardar la imagen.';
        }
      }
    } else {
      $errores[] = 'Error al subir la imagen.';
    }
  }

  if (!$errores) {
    $stmt = $conn->prepare(
      "INSERT INTO servicio
       (id_usuario, titulo, descripcion, precio, ubicacion, imagen_url)
       VALUES (?,?,?,?,?,?)"
    );
    if (!$stmt) {
      $errores[] = 'Error al preparar la consulta: ' . $conn->error;
    } else {
      $precioF = (float)$precio;
      $stmt->bind_param("issdss", $idProveedor, $titulo, $descripcion, $precioF, $ubicacion, $imgPath);
      if ($stmt->execute()) {
        $idServicio = $stmt->insert_id;

        $sc = $conn->prepare("INSERT INTO servicio_categoria (id_servicio, id_categoria) VALUES (?,?)");
        $sc->bind_param("ii", $idServicio, $idCategoria);
        $sc->execute();
        $sc->close();

        header("Location: panel_proveedor.php?ok=1"); exit;
      } else {
        $errores[] = 'No se pudo guardar el servicio: ' . $stmt->error;
      }
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
<title>Publicar servicio - TradeConnect</title>
<link rel="stylesheet" href="css/publicar_servicio.css">
</head>
<body>
<header>
  <div class="logo">
    <img src="logo.png" alt="Logo">
    <span><strong>Trade</strong>Connect</span>
  </div>
  <nav>
    <a href="panel_proveedor.php">Inicio</a>
    <a href="mis_servicios.php">Mis servicios</a>
    <a href="mensajes.php">Mensajes</a>
    <a class="logout" href="logout.php">Cerrar sesión</a>
  </nav>
</header>

<main class="main">
  <div class="hero">
    <h1>Formulario de Publicación de Servicios</h1>
    <div class="sub">Completá los datos y publicá tu servicio para que los clientes te encuentren.</div>
  </div>

  <!-- Contenedor de errores -->
  <div id="errorImagen" class="error" style="display:none;">
    <strong>Debes seleccionar una imagen de portada.</strong>
  </div>

  <?php if (!empty($errores)): ?>
    <div class="error">
      <strong>Revisá estos puntos:</strong>
      <ul>
        <?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form class="form-wrap" method="post" enctype="multipart/form-data" id="form-publicar">
    <div class="grid">
      <div>
        <label for="titulo">Título *</label>
        <input id="titulo" name="titulo" type="text" maxlength="100"
               placeholder="Servicio de Reparación Eléctrica"
               value="<?= e($_POST['titulo'] ?? '') ?>">

        <label for="categoria">Categoría *</label>
        <select id="categoria" name="categoria">
          <option value="">Seleccioná una categoría</option>
          <?php foreach ($categorias as $c): ?>
            <option value="<?= (int)$c['id_categoria'] ?>"
              <?= ((int)($c['id_categoria']) === (int)($_POST['categoria'] ?? 0)) ? 'selected' : '' ?>>
              <?= e($c['nombre']) ?>
            </option>
          <?php endforeach; ?>
        </select>

        <label for="precio">Precio (UYU) *</label>
        <input id="precio" name="precio" type="number" step="0.01" min="0"
               placeholder="1400" value="<?= e($_POST['precio'] ?? '') ?>">

        <label for="ubicacion">Ubicación *</label>
        <input id="ubicacion" name="ubicacion" type="text" maxlength="100"
               placeholder="Montevideo" value="<?= e($_POST['ubicacion'] ?? '') ?>">

        <label for="descripcion">Descripción *</label>
        <textarea id="descripcion" name="descripcion"
                  placeholder="Contá qué ofrecés, alcance, materiales, tiempos, etc."><?= e($_POST['descripcion'] ?? '') ?></textarea>

        <div class="actions">
          <a class="btn btn-outline" href="panel_proveedor.php">Cancelar</a>
          <button class="btn btn-green" type="submit">Publicar</button>
        </div>
      </div>

      <div class="media">
        <div class="preview">
          <img id="imgPreview" src="background-herramientas.jpg.png" alt="Previsualización">
        </div>
        <div class="actions" style="margin-top:14px">
          <label class="btn btn-primary" style="cursor:pointer;">
            Agregar imagen
            <input id="imagen" name="imagen" type="file" accept=".jpg,.jpeg,.png,.webp" style="display:none">
          </label>
        </div>
        <div class="hint">Formatos: JPG/PNG/WEBP · Tamaño máx.: 2MB.</div>
      </div>
    </div>
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
const input = document.getElementById('imagen');
const img   = document.getElementById('imgPreview');
const form  = document.getElementById('form-publicar');
const errorDiv = document.getElementById('errorImagen');

input?.addEventListener('change', () => {
  const f = input.files?.[0];
  if (!f) return;
  const ok = ['image/jpeg','image/png','image/webp'].includes(f.type);
  if (!ok) { 
    input.value=''; 
    img.src='background-herramientas.jpg.png';
    errorDiv.style.display = 'block';
    errorDiv.textContent = 'Formato de imagen no válido.';
    return; 
  }
  img.src = URL.createObjectURL(f);
  errorDiv.style.display = 'none';
});

// Validación al enviar
form?.addEventListener('submit', (e) => {
  if (!input.files || input.files.length === 0) {
    e.preventDefault();
    errorDiv.style.display = 'block';
    errorDiv.innerHTML = '<strong>Debes seleccionar una imagen de portada.</strong>';
    input.scrollIntoView({ behavior: 'smooth', block: 'center' });
  } else {
    errorDiv.style.display = 'none';
  }
});
</script>
</body>
</html>
