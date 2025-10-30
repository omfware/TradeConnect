<?php
session_start();
require 'conexion.php';
require_once 'utils.php';

if (!isset($_SESSION['id_usuario'])) { header("Location: login.html"); exit; }
if (($_SESSION['rol'] ?? '') !== 'proveedor') { header("Location: inicio.php"); exit; }

$idProveedor = (int)$_SESSION['id_usuario'];
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

// Servicios del proveedor
$sql = "
  SELECT s.id_servicio, s.titulo, s.precio, s.ubicacion, s.imagen_url, s.fecha_publicacion,
         COALESCE(GROUP_CONCAT(c.nombre ORDER BY c.nombre SEPARATOR ', '), '') AS categorias
  FROM servicio s
  LEFT JOIN servicio_categoria sc ON sc.id_servicio = s.id_servicio
  LEFT JOIN categoria c           ON c.id_categoria = sc.id_categoria
  WHERE s.id_usuario = ?
  GROUP BY s.id_servicio
  ORDER BY s.fecha_publicacion DESC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idProveedor);
$stmt->execute();
$servicios = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Solicitudes pendientes
$stmt = $conn->prepare("
  SELECT r.id_reserva, r.fecha, r.hora, r.estado, r.id_servicio, r.id_cliente,
         s.titulo AS srv_titulo,
         u.nombre, u.apellido, u.usuario, u.avatar_url
  FROM reserva r
  JOIN servicio s ON s.id_servicio = r.id_servicio
  JOIN usuario  u ON u.id_usuario  = r.id_cliente
  WHERE s.id_usuario = ? AND r.estado = 'pendiente'
  ORDER BY r.fecha ASC, r.hora ASC
  LIMIT 8
");
$stmt->bind_param("i", $idProveedor);
$stmt->execute();
$pendientes = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Próximas aceptadas (hoy con hora futura o días posteriores)
$stmt = $conn->prepare("
  SELECT r.id_reserva, r.fecha, r.hora, r.estado, r.id_servicio, r.id_cliente,
         s.titulo AS srv_titulo,
         u.nombre, u.apellido, u.usuario, u.avatar_url
  FROM reserva r
  JOIN servicio s ON s.id_servicio = r.id_servicio
  JOIN usuario  u ON u.id_usuario  = r.id_cliente
  WHERE s.id_usuario = ?
    AND r.estado = 'aceptada'
    AND (
          r.fecha > CURDATE() OR
          (r.fecha = CURDATE() AND r.hora >= CURTIME())
        )
  ORDER BY r.fecha ASC, r.hora ASC
  LIMIT 8
");
$stmt->bind_param("i", $idProveedor);
$stmt->execute();
$proximas = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<title>Panel de Proveedor - TradeConnect</title>
<link rel="stylesheet" href="css/panel_proveedor.css">
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
      <a href="mensajes.php">Bandeja de Entrada</a>
      <a class="logout" href="logout.php">Cerrar sesión</a>
    </nav>
  </header>

  <main class="main">
    <?php if (isset($_GET['ok'])): ?><div class="ok">✅ Servicio publicado con éxito.</div><?php endif; ?>
    <?php if (isset($_GET['del_ok'])): ?><div class="ok">🗑️ Servicio eliminado correctamente.</div><?php endif; ?>
    <?php if (isset($_GET['del_err'])): ?><div class="error">❗ No se pudo eliminar el servicio.</div><?php endif; ?>
    <?php if (isset($_GET['res_ok'])): ?><div class="ok">✅ Estado de reserva actualizado.</div><?php endif; ?>
    <?php if (isset($_GET['res_err'])): ?><div class="error">❗ No se pudo actualizar la reserva.</div><?php endif; ?>

    <h1>Publicá y gestioná tus servicios</h1>
    <div class="sub">Desde acá podés crear nuevas publicaciones, editar precios y responder consultas.</div>

    <div class="actions">
      <a class="btn btn-primary" href="publicar_servicio.php">+ Publicar servicio</a>
      <a class="btn btn-outline" href="agenda.php">Mi agenda</a>
    </div>

    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Título</th>
            <th class="muted">Categorías</th>
            <th>Precio</th>
            <th>Ubicación</th>
            <th>Estado</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
        <?php if (!$servicios): ?>
          <tr><td colspan="6" class="muted" style="padding:22px;">Aún no publicaste servicios. ¡Empezá con el botón <strong>“Publicar servicio”</strong>!</td></tr>
        <?php else: foreach ($servicios as $s): ?>
          <tr>
            <td><?= e($s['titulo']) ?></td>
            <td class="muted"><?= $s['categorias'] ? e($s['categorias']) : '—' ?></td>
            <td>$ <?= number_format((float)$s['precio'], 0, ',', '.') ?></td>
            <td><?= e($s['ubicacion']) ?></td>
            <td><span class="pill">Publicado</span></td>
            <td>
              <a class="link-danger" href="editar_servicio.php?id=<?= (int)$s['id_servicio'] ?>">Editar</a> ·
              <a class="link-danger" href="eliminar_servicio.php?id=<?= (int)$s['id_servicio'] ?>">Eliminar</a>
            </td>
          </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Solicitudes -->
    <h2 style="margin:6px 0 10px">Solicitudes de reservas</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Servicio</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Acción</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$pendientes): ?>
            <tr><td colspan="5" class="muted" style="padding:22px;">No tenés solicitudes pendientes.</td></tr>
          <?php else: foreach ($pendientes as $r): ?>
            <tr class="act-row">
              <td>
                <span class="mini-avatar"><img src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt=""></span>
                <?= e(trim(($r['nombre'] ?? '').' '.($r['apellido'] ?? ''))) ?>
                <span class="muted">(@<?= e($r['usuario']) ?>)</span>
              </td>
              <td><?= e($r['srv_titulo']) ?></td>
              <td><?= e(date('d/m/Y', strtotime($r['fecha']))) ?></td>
              <td><?= e(substr($r['hora'],0,5)) ?></td>
              <td>
                <!-- Abrir modal de verificación -->
                <button
                  class="btn-accept js-accept"
                  type="button"
                  data-id="<?= (int)$r['id_reserva'] ?>"
                  data-fecha="<?= e($r['fecha']) ?>"
                  data-hora="<?= e(substr($r['hora'],0,5)) ?>"
                  data-cliente="<?= (int)$r['id_cliente'] ?>"
                  data-servicio="<?= (int)$r['id_servicio'] ?>"
                >Aceptar</button>

                <!-- Rechazar directo -->
                <form method="post" action="reserva_accion.php" style="display:inline" onsubmit="return confirm('¿Rechazar esta solicitud?');">
                  <input type="hidden" name="id_reserva" value="<?= (int)$r['id_reserva'] ?>">
                  <input type="hidden" name="accion" value="rechazar">
                  <button class="btn-reject" type="submit">Rechazar</button>
                </form>
              </td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>

    <!-- Próximas aceptadas -->
    <h2 style="margin:6px 0 10px">Próximas reservas aceptadas</h2>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Cliente</th>
            <th>Servicio</th>
            <th>Fecha</th>
            <th>Hora</th>
            <th>Estado</th>
          </tr>
        </thead>
        <tbody>
          <?php if (!$proximas): ?>
            <tr><td colspan="5" class="muted" style="padding:22px;">No hay próximas reservas.</td></tr>
          <?php else: foreach ($proximas as $r): ?>
            <tr>
              <td>
                <span class="mini-avatar"><img src="<?= e(avatar_url($r['avatar_url'] ?? null)) ?>" alt=""></span>
                <?= e(trim(($r['nombre'] ?? '').' '.($r['apellido'] ?? ''))) ?>
                <span class="muted">(@<?= e($r['usuario']) ?>)</span>
              </td>
              <td><?= e($r['srv_titulo']) ?></td>
              <td><?= e(date('d/m/Y', strtotime($r['fecha']))) ?></td>
              <td><?= e(substr($r['hora'],0,5)) ?></td>
              <td><span class="pill">Aceptada</span></td>
            </tr>
          <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </main>

  <!-- MODAL ACEPTAR -->
  <div id="acceptModal" class="modal hidden" role="dialog" aria-modal="true" aria-labelledby="m-title">
    <div class="modal-card">
      <h3 id="m-title" style="margin:0 0 10px">¿Confirmar esta reserva?</h3>
      <div id="m-info" class="muted">Fecha y hora…</div>

      <div class="modal-actions">
        <a id="m-agenda" class="btn-ghost" href="agenda.php" target="_blank">Ver agenda</a>
        <a id="m-chat" class="btn-ghost" href="#" target="_blank">Hablar con el cliente</a>

        <form id="m-form" method="post" action="reserva_accion.php" style="margin-left:auto">
          <input type="hidden" name="id_reserva" id="m-id">
          <input type="hidden" name="accion" value="aceptar">
          <button class="btn btn-primary" type="submit">Confirmar aceptación</button>
        </form>

        <button class="btn-ghost" type="button" id="m-cancel">Cancelar</button>
      </div>
    </div>
  </div>

  <footer>
    <div>
      <a href="#">Términos</a>
      <a href="#">Contacto</a>
      <a href="acerca.php">Acerca de</a>
    </div>
    <div>© 2025 TradeConnect</div>
  </footer>

<script>
  // Modal aceptar
  const modal = document.getElementById('acceptModal');
  const mId   = document.getElementById('m-id');
  const mInfo = document.getElementById('m-info');
  const mAgenda = document.getElementById('m-agenda');
  const mChat   = document.getElementById('m-chat');
  const mCancel = document.getElementById('m-cancel');

  document.querySelectorAll('.js-accept').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      const id    = btn.dataset.id;
      const fecha = btn.dataset.fecha; // YYYY-MM-DD
      const hora  = btn.dataset.hora;  // HH:MM
      const cli   = btn.dataset.cliente;

      mId.value = id;
      // Info visible
      const f = new Date(fecha+'T00:00:00');
      const fNice = `${('0'+f.getDate()).slice(-2)}/${('0'+(f.getMonth()+1)).slice(-2)}/${f.getFullYear()}`;
      mInfo.textContent = `Vas a aceptar la reserva del ${fNice} a las ${hora}. ¿Estás libre ese día/horario?`;

      // Links útiles
      mAgenda.href = `agenda.php?date=${fecha}`;
      mChat.href   = `mensajes.php?to=${cli}`;

      modal.classList.remove('hidden');
    });
  });

  mCancel?.addEventListener('click', ()=> modal.classList.add('hidden'));
  modal.addEventListener('click', (e)=>{ if(e.target === modal) modal.classList.add('hidden'); });
</script>
</body>
</html>
