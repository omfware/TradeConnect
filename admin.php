<?php
// admin.php — login + panel + acciones (PlataformaOficios)
session_start();
require 'conexion.php';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function is_admin(){ return isset($_SESSION['id_usuario']) && (($_SESSION['rol'] ?? '') === 'admin'); }

// ===== Generador de hash (opcional, solo cuando lo pedís con ?genhash=1) =====
if (isset($_GET['genhash'])) {
  $clave = 'Admin123!'; // cambiá si querés generar otro hash
  echo password_hash($clave, PASSWORD_DEFAULT);
  exit;
}

// CSRF
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$CSRF = $_SESSION['csrf'];

/* =========================
   mini-login interno admin
========================= */
$loginError = '';
if (!is_admin() && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['__form']??'')==='admin_login') {
  $user = trim($_POST['usuario'] ?? '');
  $pass = (string)($_POST['password'] ?? '');

  $stmt = $conn->prepare("SELECT id_usuario, usuario, `contraseña`, rol, baneado
                          FROM usuario
                          WHERE usuario=? AND rol='admin'
                          LIMIT 1");
  $stmt->bind_param("s", $user);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  if (!$row || !password_verify($pass, $row['contraseña'])) {
    $loginError = 'Credenciales inválidas.';
  } elseif ((int)($row['baneado'] ?? 0) === 1) {
    $loginError = 'Cuenta de admin suspendida.';
  } else {
    $_SESSION['id_usuario'] = (int)$row['id_usuario'];
    $_SESSION['rol']        = 'admin';
    header('Location: admin.php'); exit;
  }
}

/* =========================
   acciones (POST)
========================= */
$flash = $_GET['msg'] ?? '';
if (is_admin() && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['__form']??'')==='accion') {
  if (!isset($_POST['csrf']) || $_POST['csrf'] !== $CSRF) { http_response_code(403); die('CSRF inválido'); }

  $accion = $_POST['accion'] ?? '';
  switch ($accion) {
    // Usuarios
    case 'ban_user':
    case 'unban_user': {
      $id = (int)($_POST['id_usuario'] ?? 0);
      if ($id > 0) {
        // no permitir banear admins
        $stmt = $conn->prepare("SELECT rol FROM usuario WHERE id_usuario=?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $rol = ($stmt->get_result()->fetch_assoc()['rol'] ?? '');
        $stmt->close();
        if ($rol !== 'admin') {
          $flag = ($accion === 'ban_user') ? 1 : 0;
          $stmt = $conn->prepare("UPDATE usuario SET baneado=? WHERE id_usuario=? LIMIT 1");
          $stmt->bind_param("ii", $flag, $id);
          $stmt->execute(); $stmt->close();
        }
      }
      $tab = 'usuarios';
      break;
    }

    case 'del_user': {
      $id = (int)($_POST['id_usuario'] ?? 0);
      if ($id > 0) {
        if ($id === (int)$_SESSION['id_usuario']) {
          $flash = 'No podés eliminar tu propio usuario.';
        } else {
          // Chequear rol del usuario a eliminar
          $stmt = $conn->prepare("SELECT rol, usuario FROM usuario WHERE id_usuario=?");
          $stmt->bind_param("i", $id);
          $stmt->execute();
          $info = $stmt->get_result()->fetch_assoc();
          $stmt->close();

          if (!$info) {
            $flash = 'Usuario no encontrado.';
          } elseif ($info['rol'] === 'admin') {
            $flash = 'No se puede eliminar un usuario con rol admin.';
          } else {
            // Borrar (el resto cae por ON DELETE CASCADE)
            $stmt = $conn->prepare("DELETE FROM usuario WHERE id_usuario=? LIMIT 1");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
            $flash = 'Usuario @' . ($info['usuario'] ?? '') . ' eliminado.';
          }
        }
      } else {
        $flash = 'Solicitud inválida.';
      }
      header('Location: admin.php?t=usuarios&msg='.urlencode($flash)); exit;
    }

    // Servicios
    case 'hide_service':
    case 'show_service': {
      $idS = (int)($_POST['id_servicio'] ?? 0);
      if ($idS > 0) {
        $flag = ($accion === 'hide_service') ? 1 : 0;
        $stmt = $conn->prepare("UPDATE servicio SET oculto=? WHERE id_servicio=? LIMIT 1");
        $stmt->bind_param("ii", $flag, $idS);
        $stmt->execute(); $stmt->close();
      }
      $tab = 'servicios';
      break;
    }

    // Eliminar servicio
    case 'del_service': {
      $idS = (int)($_POST['id_servicio'] ?? 0);
      if ($idS > 0) {
        $stmt = $conn->prepare("SELECT titulo FROM servicio WHERE id_servicio=?");
        $stmt->bind_param("i", $idS);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($row) {
          $stmt = $conn->prepare("DELETE FROM servicio WHERE id_servicio=? LIMIT 1");
          $stmt->bind_param("i", $idS);
          $stmt->execute();
          $stmt->close();
          $flash = 'Servicio «' . ($row['titulo'] ?? 'sin título') . '» eliminado.';
        } else {
          $flash = 'Servicio no encontrado.';
        }
      } else {
        $flash = 'Solicitud inválida.';
      }
      header('Location: admin.php?t=servicios&msg='.urlencode($flash)); exit;
    }

    // Categorías
    case 'add_cat': {
      $nombre = trim($_POST['nombre'] ?? '');
      if ($nombre === '' || mb_strlen($nombre) > 100) {
        $flash = 'Nombre inválido.';
      } else {
        $stmt = $conn->prepare("SELECT 1 FROM categoria WHERE LOWER(nombre)=LOWER(?) LIMIT 1");
        $stmt->bind_param("s", $nombre);
        $stmt->execute();
        $existe = (bool)$stmt->get_result()->fetch_row();
        $stmt->close();
        if ($existe) {
          $flash = 'La categoría ya existe.';
        } else {
          $stmt = $conn->prepare("INSERT INTO categoria (nombre) VALUES (?)");
          $stmt->bind_param("s", $nombre);
          $stmt->execute();
          $stmt->close();
          $flash = 'Categoría creada.';
        }
      }
      header('Location: admin.php?t=categorias&msg='.urlencode($flash)); exit;
    }

    case 'del_cat': {
      $idc = (int)($_POST['id_categoria'] ?? 0);
      if ($idc > 0) {
        $stmt = $conn->prepare("SELECT COUNT(*) AS n FROM servicio_categoria WHERE id_categoria=?");
        $stmt->bind_param("i", $idc);
        $stmt->execute();
        $n = (int)($stmt->get_result()->fetch_assoc()['n'] ?? 0);
        $stmt->close();

        if ($n > 0) {
          $flash = 'No se puede eliminar: la categoría está en uso.';
        } else {
          $stmt = $conn->prepare("DELETE FROM categoria WHERE id_categoria=? LIMIT 1");
          $stmt->bind_param("i", $idc);
          $stmt->execute();
          $stmt->close();
          $flash = 'Categoría eliminada.';
        }
      }
      header('Location: admin.php?t=categorias&msg='.urlencode($flash)); exit;
    }
  }

  if (!isset($tab)) $tab = str_contains($accion,'user') ? 'usuarios' : (str_contains($accion,'service') ? 'servicios' : 'usuarios');
  header('Location: admin.php?t='.$tab); exit;
}

/* =========================
   si NO es admin: mostrar login interno y salir
========================= */
if (!is_admin()):
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin • Login</title>
<link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-login">
  <form class="card" method="post">
    <h1>Panel Admin</h1>
    <?php if ($loginError): ?><div class="err"><?= e($loginError) ?></div><?php endif; ?>
    <input type="hidden" name="__form" value="admin_login">
    <div class="fld">
      <label>Usuario</label>
      <input type="text" name="usuario" required autocomplete="username">
    </div>
    <div class="fld">
      <label>Contraseña</label>
      <input type="password" name="password" required autocomplete="current-password">
    </div>
    <button class="btn" type="submit">Ingresar</button>
    <div class="help">¿Necesitás un hash para crear el admin? <a href="?genhash=1" target="_blank">Generar hash</a></div>
  </form>
</body>
</html>
<?php
exit;
endif;

/* =========================
   datos del panel (ya somos admin)
========================= */
$t = $_GET['t'] ?? 'usuarios';

// usuarios
$usuarios = [];
$stmt = $conn->prepare("
  SELECT id_usuario, usuario, nombre, apellido, correo, rol, baneado
  FROM usuario
  ORDER BY (rol='admin') DESC, id_usuario DESC
");
$stmt->execute();
$usuarios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// servicios
$servicios = [];
$stmt = $conn->prepare("
  SELECT s.id_servicio, s.titulo, s.descripcion, s.imagen_url, s.precio, s.ubicacion, s.oculto, s.fecha_publicacion,
         u.id_usuario AS id_prov, u.usuario, u.nombre, u.apellido, u.baneado
  FROM servicio s
  JOIN usuario u ON u.id_usuario = s.id_usuario
  ORDER BY s.fecha_publicacion DESC
  LIMIT 200
");
$stmt->execute();
$servicios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// categorías
$cats = [];
$stmt = $conn->prepare("
  SELECT c.id_categoria, c.nombre, COUNT(sc.id_servicio) AS usados
  FROM categoria c
  LEFT JOIN servicio_categoria sc ON sc.id_categoria = c.id_categoria
  GROUP BY c.id_categoria
  ORDER BY c.nombre
");
$stmt->execute();
$cats = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin • TradeConnect</title>
<link rel="stylesheet" href="css/admin.css">
</head>
<body class="admin-panel">
<header>
  <div class="admin-brand">
    <img src="images/logo.png" alt="Logo"><span>Panel <strong>Admin</strong></span>
  </div>
  <nav>
    <a href="admin.php?t=usuarios"   class="tab <?= $t==='usuarios'?'active':'' ?>">Usuarios</a>
    <a href="admin.php?t=servicios"  class="tab <?= $t==='servicios'?'active':'' ?>">Servicios</a>
    <a href="admin.php?t=categorias" class="tab <?= $t==='categorias'?'active':'' ?>">Categorías</a>
    <a class="logout" href="logout.php">Cerrar sesión</a>
  </nav>
</header>

<main>
  <h1>Moderación</h1>

  <?php if ($flash): ?><div class="notice"><?= e($flash) ?></div><?php endif; ?>

  <?php if ($t==='usuarios'): ?>
  <div class="panel">
    <table>
      <thead><tr>
        <th>Usuario</th><th>Rol</th><th>Estado</th><th>Correo</th><th style="width:320px">Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach ($usuarios as $u): ?>
        <tr>
          <td><strong>@<?= e($u['usuario']) ?></strong> <span class="muted"><?= e($u['nombre'].' '.$u['apellido']) ?></span></td>
          <td><?= e($u['rol']) ?></td>
          <td><?= ((int)$u['baneado']===1) ? '<span class="pill pill-bad">Baneado</span>' : '<span class="pill pill-ok">Activo</span>' ?></td>
          <td class="muted"><?= e($u['correo']) ?></td>
          <td class="actions">
            <?php if ($u['rol'] !== 'admin'): ?>
              <?php if ((int)$u['baneado']===1): ?>
                <form method="post" onsubmit="return confirm('¿Quitar ban a @<?= e($u['usuario']) ?>?');">
                  <input type="hidden" name="__form" value="accion">
                  <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                  <input type="hidden" name="accion" value="unban_user">
                  <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                  <button class="btn btn-ok" type="submit">Quitar ban</button>
                </form>
              <?php else: ?>
                <form method="post" onsubmit="return confirm('¿Banear a @<?= e($u['usuario']) ?>?');">
                  <input type="hidden" name="__form" value="accion">
                  <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                  <input type="hidden" name="accion" value="ban_user">
                  <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                  <button class="btn btn-danger" type="submit">Banear</button>
                </form>
              <?php endif; ?>

              <form method="post"
                    onsubmit="return confirm('Vas a eliminar a @<?= e($u['usuario']) ?>.\nSe borrarán sus servicios, reservas, reseñas y mensajes (en cascada).\n¿Confirmás?');">
                <input type="hidden" name="__form" value="accion">
                <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                <input type="hidden" name="accion" value="del_user">
                <input type="hidden" name="id_usuario" value="<?= (int)$u['id_usuario'] ?>">
                <button class="btn btn-danger" type="submit">Eliminar</button>
              </form>
            <?php else: ?>
              <span class="muted">—</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php elseif ($t==='servicios'): ?>
  <div class="panel">
    <table>
      <thead><tr>
        <th>Servicio</th><th>Proveedor</th><th>Ubicación</th><th>Precio</th><th>Visibilidad</th><th style="width:260px">Acciones</th>
      </tr></thead>
      <tbody>
      <?php foreach ($servicios as $s): ?>
        <tr>
          <td><strong><?= e($s['titulo']) ?></strong></td>
          <td>
            <strong>@<?= e($s['usuario']) ?></strong>
            <?php if ((int)$s['baneado']===1): ?><span class="pill pill-bad">Proveedor baneado</span><?php endif; ?>
          </td>
          <td class="muted"><?= e($s['ubicacion']) ?></td>
          <td>$ <?= number_format((float)$s['precio'], 0, ',', '.') ?></td>
          <td><?= ((int)$s['oculto']===1)?'<span class="pill">Oculto</span>':'<span class="pill pill-ok">Publicado</span>' ?></td>
          <td class="actions">
            <?php if ((int)$s['oculto']===1): ?>
              <form method="post">
                <input type="hidden" name="__form" value="accion">
                <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                <input type="hidden" name="accion" value="show_service">
                <input type="hidden" name="id_servicio" value="<?= (int)$s['id_servicio'] ?>">
                <button class="btn btn-ok" type="submit">Mostrar</button>
              </form>
            <?php else: ?>
              <form method="post" onsubmit="return confirm('¿Ocultar este servicio?');">
                <input type="hidden" name="__form" value="accion">
                <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                <input type="hidden" name="accion" value="hide_service">
                <input type="hidden" name="id_servicio" value="<?= (int)$s['id_servicio'] ?>">
                <button class="btn btn-danger" type="submit">Ocultar</button>
              </form>
            <?php endif; ?>

            <form method="post"
                  onsubmit="return confirm('¿Eliminar el servicio «<?= e($s['titulo']) ?>»?\\nSe eliminarán reservas, reseñas y categorías asociadas (en cascada).');">
              <input type="hidden" name="__form" value="accion">
              <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
              <input type="hidden" name="accion" value="del_service">
              <input type="hidden" name="id_servicio" value="<?= (int)$s['id_servicio'] ?>">
              <button class="btn btn-danger" type="submit">Eliminar</button>
            </form>

            <button
              class="btn btn-ghost js-view"
              type="button"
              data-id="<?= (int)$s['id_servicio'] ?>"
              data-title="<?= e($s['titulo']) ?>"
              data-img="<?= e($s['imagen_url'] ?: 'uploads/placeholder.webp') ?>"
              data-desc="<?= e($s['descripcion'] ?: 'Sin descripción.') ?>"
              data-price="<?= number_format((float)$s['precio'], 0, ',', '.') ?>"
              data-loc="<?= e($s['ubicacion']) ?>"
              data-date="<?= e(date('d/m/Y', strtotime($s['fecha_publicacion']))) ?>"
              data-oculto="<?= (int)$s['oculto'] ?>"
              data-prov-id="<?= (int)$s['id_prov'] ?>"
              data-prov-user="@<?= e($s['usuario']) ?>"
              data-prov-name="<?= e(trim(($s['nombre'] ?? '').' '.($s['apellido'] ?? ''))) ?>"
              data-prov-banned="<?= (int)$s['baneado'] ?>"
            >Ver</button>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php else: /* === CATEGORÍAS === */ ?>
  <div class="panel" style="padding:14px">
    <form method="post" class="inline-form">
      <input type="hidden" name="__form" value="accion">
      <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
      <input type="hidden" name="accion" value="add_cat">
      <input type="text" name="nombre" placeholder="Nueva categoría" maxlength="100" required class="text-input">
      <button class="btn btn-ok" type="submit">Crear</button>
    </form>
  </div>

  <div class="panel">
    <table>
      <thead><tr>
        <th>Nombre</th><th>Usada por</th><th style="width:220px">Acciones</th>
      </tr></thead>
      <tbody>
      <?php if (!$cats): ?>
        <tr><td colspan="3" class="muted" style="padding:16px">No hay categorías creadas.</td></tr>
      <?php else: foreach ($cats as $c): ?>
        <tr>
          <td><strong><?= e($c['nombre']) ?></strong></td>
          <td><?= (int)$c['usados'] ?> servicio(s)</td>
          <td class="actions">
            <?php if ((int)$c['usados'] === 0): ?>
              <form method="post" onsubmit="return confirm('¿Eliminar la categoría «<?= e($c['nombre']) ?>»?');">
                <input type="hidden" name="__form" value="accion">
                <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
                <input type="hidden" name="accion" value="del_cat">
                <input type="hidden" name="id_categoria" value="<?= (int)$c['id_categoria'] ?>">
                <button class="btn btn-danger" type="submit">Eliminar</button>
              </form>
            <?php else: ?>
              <span class="muted">No se puede eliminar (en uso)</span>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</main>

<footer>
  <div>© 2025 TradeConnect</div>
  <div><a href="admin.php" class="admin-link">Admin</a></div>
</footer>

<!-- MODAL PREVIEW SERVICIO -->
<div id="modal" class="modal" role="dialog" aria-modal="true" aria-labelledby="m-title">
  <div class="modal-card">
    <div class="modal-head">
      <div class="m-title" id="m-title">Servicio</div>
      <button class="close" id="m-close">Cerrar</button>
    </div>
    <div class="modal-body">
      <div class="media"><img id="m-img" src="uploads/placeholder.webp" alt=""></div>
      <div class="block">
        <div class="row"><span class="pill" id="m-loc">Ubicación</span><span class="pill" id="m-date">Fecha</span></div>
        <div class="row"><div class="m-title" id="m-price">$ 0</div></div>
        <div class="muted" id="m-desc" style="margin-top:8px">Descripción…</div>
        <hr class="sep">
        <div><strong id="m-prov-name"></strong> <span class="muted" id="m-prov-user"></span> <span id="m-prov-flag"></span></div>

        <div class="row" style="margin-top:12px">
          <form method="post" id="m-ban-form">
            <input type="hidden" name="__form" value="accion">
            <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
            <input type="hidden" name="accion" id="m-ban-action" value="ban_user">
            <input type="hidden" name="id_usuario" id="m-prov-id" value="">
            <button class="btn btn-danger" type="submit" id="m-ban-btn">Banear proveedor</button>
          </form>

          <form method="post" id="m-vis-form" style="margin-left:8px">
            <input type="hidden" name="__form" value="accion">
            <input type="hidden" name="csrf" value="<?= e($CSRF) ?>">
            <input type="hidden" name="accion" id="m-vis-action" value="hide_service">
            <input type="hidden" name="id_servicio" id="m-serv-id" value="">
            <button class="btn btn-danger" type="submit" id="m-vis-btn">Ocultar servicio</button>
          </form>
        </div>

        <small class="muted">Desde esta vista podés banear/quitar ban y ocultar/mostrar el servicio.</small>
      </div>
    </div>
    <div class="m-cta">
      <button class="close" id="m-close-2">Cerrar</button>
    </div>
  </div>
</div>

<script>
  const modal = document.getElementById('modal');
  const closeBtns = [document.getElementById('m-close'), document.getElementById('m-close-2')];
  function openModal(){ modal.classList.add('show'); }
  function closeModal(){ modal.classList.remove('show'); }
  closeBtns.forEach(b=> b.addEventListener('click', closeModal));
  modal.addEventListener('click', (e)=>{ if(e.target===modal) closeModal(); });

  document.querySelectorAll('.js-view').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const d = btn.dataset;

      document.getElementById('m-title').textContent = d.title || 'Servicio';

      const img = document.getElementById('m-img');
      img.src = d.img || 'uploads/placeholder.webp';
      img.onerror = ()=> { img.src = 'uploads/placeholder.webp'; };

      document.getElementById('m-loc').textContent  = d.loc ? ('Ubicación: '+d.loc) : 'Ubicación';
      document.getElementById('m-date').textContent = d.date ? ('Publicado: '+d.date) : 'Publicado';
      document.getElementById('m-price').textContent= '$ ' + (d.price || '0');
      document.getElementById('m-desc').textContent = d.desc || 'Sin descripción.';

      document.getElementById('m-prov-name').textContent = d.provName || '';
      document.getElementById('m-prov-user').textContent = d.provUser || '';
      const banned = parseInt(d.provBanned||'0',10)===1;
      document.getElementById('m-prov-flag').innerHTML = banned
        ? '<span class="pill pill-bad" style="margin-left:6px">Baneado</span>'
        : '<span class="pill pill-ok"  style="margin-left:6px">Activo</span>';

      const oculto = parseInt(d.oculto||'0',10)===1;
      document.getElementById('m-serv-id').value = d.id || '';
      const visAction = oculto ? 'show_service' : 'hide_service';
      document.getElementById('m-vis-action').value = visAction;
      const visBtn = document.getElementById('m-vis-btn');
      visBtn.textContent = oculto ? 'Mostrar servicio' : 'Ocultar servicio';
      visBtn.className = oculto ? 'btn btn-ok' : 'btn btn-danger';

      openModal();
    });
  });
</script>
</body>
</html>
