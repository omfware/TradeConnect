<?php
// editar_perfil.php
session_start();
require 'conexion.php';
require_once 'utils.php';

if (!isset($_SESSION['id_usuario'])) { header("Location: login.html"); exit; }
if (($_SESSION['rol'] ?? '') !== 'proveedor') { header("Location: inicio.php"); exit; }

$idUsuario = (int)$_SESSION['id_usuario'];
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$errores = [];
$ok = isset($_GET['ok']);

/* --------- Traer perfil actual --------- */
$stmt = $conn->prepare("
  SELECT nombre, apellido, usuario, correo, telefono, direccion, bio, avatar_url
  FROM usuario
  WHERE id_usuario = ? AND rol = 'proveedor'
  LIMIT 1
");
$stmt->bind_param("i", $idUsuario);
$stmt->execute();
$perfil = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$perfil) { header("Location: panel_proveedor.php"); exit; }

/* --------- Procesar envío --------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  // Los campos bloqueados no se actualizan
  $usuario   = trim($_POST['usuario'] ?? '');
  $telefono  = trim($_POST['telefono'] ?? '');
  $direccion = trim($_POST['direccion'] ?? '');
  $bio       = trim($_POST['bio'] ?? '');
  $quitar    = !empty($_POST['quitar_avatar']);
  $nuevoAvatar = null;

  // Validaciones básicas
  if ($usuario === '' || mb_strlen($usuario) > 100) $errores[] = 'Usuario obligatorio (máx. 100).';
  if (mb_strlen($telefono) > 100)                   $errores[] = 'Teléfono demasiado largo (máx. 100).';
  if (mb_strlen($direccion) > 150)                  $errores[] = 'Dirección demasiado larga (máx. 150).';

  // Unicidad de usuario
  if (!$errores) {
    $stmt = $conn->prepare("SELECT id_usuario FROM usuario WHERE usuario = ? AND id_usuario <> ? LIMIT 1");
    $stmt->bind_param("si", $usuario, $idUsuario);
    $stmt->execute();
    $dup = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if ($dup) $errores[] = 'El usuario ya está registrado por otra cuenta.';
  }

  // Avatar
  if (!$errores && !empty($_FILES['avatar']['name'])) {
    $f = $_FILES['avatar'];
    if ($f['error'] === UPLOAD_ERR_OK) {
      $mime = @mime_content_type($f['tmp_name']);
      $okMime = in_array($mime, ['image/jpeg','image/png','image/webp'], true);
      if (!$okMime) {
        $errores[] = 'Formato de avatar inválido (JPG/PNG/WEBP).';
      } elseif ($f['size'] > 2*1024*1024) {
        $errores[] = 'El avatar supera 2MB.';
      } else {
        $dir = __DIR__ . '/uploads/avatars';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $ext  = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
        $name = 'ava_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        if (move_uploaded_file($f['tmp_name'], "$dir/$name")) {
          $nuevoAvatar = 'uploads/avatars/' . $name;
        } else {
          $errores[] = 'No se pudo guardar el avatar.';
        }
      }
    } else {
      $errores[] = 'Error al subir el avatar.';
    }
  }

  if (!$errores) {
    $avatarFinal = $perfil['avatar_url'];
    if ($nuevoAvatar !== null) {
      if (!empty($perfil['avatar_url'])) {
        $base = realpath(__DIR__ . '/uploads/avatars') . DIRECTORY_SEPARATOR;
        $full = realpath(__DIR__ . '/' . $perfil['avatar_url']);
        if ($full && str_starts_with($full, $base) && file_exists($full)) @unlink($full);
      }
      $avatarFinal = $nuevoAvatar;
    } elseif ($quitar) {
      if (!empty($perfil['avatar_url'])) {
        $base = realpath(__DIR__ . '/uploads/avatars') . DIRECTORY_SEPARATOR;
        $full = realpath(__DIR__ . '/' . $perfil['avatar_url']);
        if ($full && str_starts_with($full, $base) && file_exists($full)) @unlink($full);
      }
      $avatarFinal = null;
    }

    $stmt = $conn->prepare("
      UPDATE usuario
      SET usuario = ?, telefono = ?, direccion = ?, bio = ?, avatar_url = ?
      WHERE id_usuario = ? AND rol = 'proveedor'
      LIMIT 1
    ");
    $stmt->bind_param("sssssi", $usuario, $telefono, $direccion, $bio, $avatarFinal, $idUsuario);
    if ($stmt->execute()) {
      $stmt->close();
      header("Location: editar_perfil.php?ok=1");
      exit;
    } else {
      $errores[] = 'No se pudo actualizar el perfil.';
      $stmt->close();
    }
  }

  $perfil['usuario']   = $usuario;
  $perfil['telefono']  = $telefono;
  $perfil['direccion'] = $direccion;
  $perfil['bio']       = $bio;
  if ($nuevoAvatar !== null) $perfil['avatar_url'] = $nuevoAvatar;
}

$preview = avatar_url($perfil['avatar_url'] ?? null);
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Editar perfil - TradeConnect</title>
<link rel="stylesheet" href="css/editar_perfil.css">
<style>
  /* Bloqueo visual de campos no editables */
  .locked{
    pointer-events:none;     /* no clic, no hover */
    user-select:none;        /* no selección de texto */
    cursor:default;          /* sin cursor de texto */
    background:#ffffffe0;    /* sutil cambio para indicar lectura */
    color:#666;
  }
</style>
</head>
<body>
<header>
  <div class="logo">
    <img src="images/logo.png" alt="Logo">
    <span><strong>Trade</strong>Connect</span>
  </div>
  <nav>
    <a href="panel_proveedor.php">Inicio</a>
    <a href="editar_perfil.php">Editar perfil</a>
    <a href="mensajes.php">Bandeja de entrada</a>
    <a class="logout" href="logout.php">Cerrar sesión</a>
  </nav>
</header>

<main class="main">
  <div class="hero">
    <div class="avatar-lg"><img id="avatarPreview" src="<?= e($preview) ?>" alt="Avatar"></div>
    <div>
      <h1><?= e(trim($perfil['nombre'].' '.$perfil['apellido'])) ?></h1>
      <div class="hint"><?= e($perfil['direccion'] ?: 'Sin dirección') ?> · <?= e($perfil['telefono'] ?: 'Sin teléfono') ?></div>
    </div>
  </div>

  <?php if (!empty($errores)): ?>
    <div class="error"><strong>Revisá estos puntos:</strong><ul><?php foreach ($errores as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
  <?php elseif ($ok): ?>
    <div class="ok">✅ Perfil actualizado con éxito.</div>
  <?php endif; ?>

  <form class="form-wrap" method="post" enctype="multipart/form-data">
    <div class="grid">
      <div>
        <label>Nombre *</label>
        <input type="text" value="<?= e($perfil['nombre']) ?>" class="locked" readonly tabindex="-1" aria-readonly="true">

        <label>Apellido *</label>
        <input type="text" value="<?= e($perfil['apellido']) ?>" class="locked" readonly tabindex="-1" aria-readonly="true">

        <label for="usuario">Usuario *</label>
        <input id="usuario" name="usuario" type="text" maxlength="100" value="<?= e($perfil['usuario']) ?>">

        <label>Correo *</label>
        <input type="text" value="<?= e($perfil['correo']) ?>" class="locked" readonly tabindex="-1" aria-readonly="true">

        <label for="telefono">Teléfono</label>
        <input id="telefono" name="telefono" type="text" maxlength="100" value<?= '="'.e($perfil['telefono']).'"' ?>>

        <label for="direccion">Dirección</label>
        <input id="direccion" name="direccion" type="text" maxlength="150" value<?= '="'.e($perfil['direccion']).'"' ?>>

        <label for="bio">Bio</label>
        <textarea id="bio" name="bio"><?= e($perfil['bio']) ?></textarea>

        <div class="actions">
          <a class="btn btn-outline" href="panel_proveedor.php">Cancelar</a>
          <button class="btn btn-green" type="submit">Guardar cambios</button>
        </div>
      </div>

      <div class="panel">
        <div class="center-btn">
          <label class="btn btn-primary" style="cursor:pointer;display:inline-block;">
            Cambiar avatar
            <input id="avatar" name="avatar" type="file" accept=".jpg,.jpeg,.png,.webp" style="display:none">
          </label>
        </div>
        <?php if (!empty($perfil['avatar_url'])): ?>
          <div style="margin-top:10px">
            <label style="display:flex;align-items:center;gap:8px;">
              <input type="checkbox" name="quitar_avatar" value="1">
              Eliminar avatar actual
            </label>
          </div>
        <?php endif; ?>
        <div class="hint">Formatos: JPG/PNG/WEBP · Máx.: 2MB.</div>
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
const input = document.getElementById('avatar');
const img   = document.getElementById('avatarPreview');
input?.addEventListener('change', () => {
  const f = input.files?.[0];
  if (!f) return;
  if (!['image/jpeg','image/png','image/webp'].includes(f.type)) {
    alert('Formato no soportado');
    input.value = '';
    return;
  }
  img.src = URL.createObjectURL(f);
});
</script>
</body>
</html>
