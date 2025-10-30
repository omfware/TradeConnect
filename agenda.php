<?php
// agenda.php — Calendario del PROVEEDOR (cuadrícula uniforme + tamaño grande + meses en español)
session_start();
require 'conexion.php';
require_once 'utils.php';

if (!isset($_SESSION['id_usuario'])) { header("Location: login.html"); exit; }
if (($_SESSION['rol'] ?? '') !== 'proveedor') { header("Location: inicio.php"); exit; }

$idProveedor = (int)$_SESSION['id_usuario'];
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

/* ----------------- Navegación de mes ----------------- */
$today = new DateTime('today');

if (isset($_GET['ym']) && preg_match('/^\d{4}-\d{2}$/', $_GET['ym'])) {
  $ref = DateTime::createFromFormat('Y-m-d', $_GET['ym'].'-01') ?: new DateTime(date('Y-m-01'));
} elseif (isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date'])) {
  $ref = DateTime::createFromFormat('Y-m-d', $_GET['date']) ?: new DateTime(date('Y-m-01'));
  $ref->modify('first day of this month');
} else {
  $ref = new DateTime(date('Y-m-01'));
}

$startOfMonth = clone $ref;
$endOfMonth   = (clone $ref)->modify('last day of this month');

// Rango visible (domingo a sábado)
$wStart    = (int)$startOfMonth->format('w');           // 0=Dom..6=Sáb
$startGrid = (clone $startOfMonth)->modify("-{$wStart} days");
$wEnd      = (int)$endOfMonth->format('w');
$endGrid   = (clone $endOfMonth)->modify('+'.(6-$wEnd).' days');

// Nav
$prevYm = (clone $ref)->modify('-1 month')->format('Y-m');
$nextYm = (clone $ref)->modify('+1 month')->format('Y-m');

// Mes en español (sin setlocale)
$mesesEs = ['enero','febrero','marzo','abril','mayo','junio','julio','agosto','septiembre','octubre','noviembre','diciembre'];
$mesTitulo = ucfirst($mesesEs[(int)$ref->format('n')-1]).' '.$ref->format('Y');

/* ----------------- Cargas de reservas ----------------- */
$inicio = $startGrid->format('Y-m-d');
$fin    = $endGrid->format('Y-m-d');

$sql = "
  SELECT r.id_reserva, r.fecha, r.hora, r.estado, r.id_servicio, r.id_cliente,
         s.titulo AS srv_titulo,
         u.nombre, u.apellido, u.usuario, u.avatar_url
  FROM reserva r
  JOIN servicio s ON s.id_servicio = r.id_servicio
  JOIN usuario  u ON u.id_usuario  = r.id_cliente
  WHERE s.id_usuario = ?
    AND r.estado = 'aceptada'
    AND r.fecha BETWEEN ? AND ?
  ORDER BY r.fecha ASC, r.hora ASC
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("iss", $idProveedor, $inicio, $fin);
$stmt->execute();
$res = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$byDay = [];
foreach ($res as $r) {
  $byDay[$r['fecha']][] = $r;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Mi agenda - TradeConnect</title>
<link rel="stylesheet" href="css/agenda.css">
</head>
<body>
<header>
  <div class="logo">
    <img src="images/logo.png" alt="Logo"><span><strong>Trade</strong>Connect</span>
  </div>
  <nav>
    <a href="panel_proveedor.php">Inicio</a>
    <a href="editar_perfil.php">Editar perfil</a>
    <a href="mensajes.php">Bandeja de Entrada</a>
    <a class="logout" href="logout.php">Cerrar sesión</a>
  </nav>
</header>

<main class="main">
  <h1>Mi agenda</h1>
  <div class="sub">Vistas de reservas aceptadas. Los días sin marcas están libres.</div>

  <div class="toolbar">
    <a class="btn btn-outline" href="?ym=<?= e($prevYm) ?>">← Mes anterior</a>
    <div class="btn btn-outline" style="pointer-events:none;opacity:.95">
      <?= e($mesTitulo) ?>
    </div>
    <a class="btn btn-outline" href="?ym=<?= e($nextYm) ?>">Mes siguiente →</a>
    <a class="btn btn-accent" href="?ym=<?= e($today->format('Y-m')) ?>">Hoy</a>
  </div>

  <div class="legend">
    <span class="chip"><span class="dot"></span> Libre</span>
    <span class="chip"><span class="dot dot-ok"></span> Con reservas aceptadas</span>
  </div>

  <!-- Encabezados -->
  <div class="grid" style="margin-top:10px">
    <?php foreach (['Dom','Lun','Mar','Mié','Jue','Vie','Sáb'] as $d) echo '<div class="colhead">'.$d.'</div>'; ?>
  </div>

  <!-- Celdas -->
  <div class="grid">
    <?php
      $iter = clone $startGrid;
      $todayYmd = $today->format('Y-m-d');
      while ($iter <= $endGrid) {
        $ymd = $iter->format('Y-m-d');
        $outMonth = ($iter->format('m') !== $ref->format('m')) ? ' out' : '';
        $isToday  = ($ymd === $todayYmd) ? ' today' : '';
        echo '<div class="card'.$outMonth.$isToday.'" data-day="'.$ymd.'">';
          $has = isset($byDay[$ymd]);
          echo '<span class="dot-abs'.($has?' ok':'').'"></span>';
          echo '<div class="card-body">';
            echo '<div class="daynum">'.(int)$iter->format('d').'</div>';
            echo '<div class="tags">';
            if ($has) {
              $list = $byDay[$ymd]; $max = 2;
              for ($i=0; $i<min(count($list), $max); $i++){
                $t = $list[$i]; $hh = substr($t['hora'],0,5);
                echo '<span class="tag">'.$hh.' · '.e($t['srv_titulo']).'</span>';
              }
              if (count($list) > $max) echo '<span class="more">+'.(count($list)-$max).' más</span>';
            }
            echo '</div>'; // .tags
          echo '</div>';   // .card-body
        echo '</div>';
        $iter->modify('+1 day');
      }
    ?>
  </div>
</main>

<!-- Modal detalle día -->
<div class="modal" id="modal">
  <div class="modal-card">
    <h3 class="modal-title" id="m-title">Reservas del día</h3>
    <div class="modal-list" id="m-list"></div>
    <div class="modal-actions">
      <button class="btn btn-outline" id="m-close" type="button">Cerrar</button>
    </div>
  </div>
</div>

<footer>
  <div class="footer-left">
    <a href="terminos.php">Términos</a>

    <!-- Hover Contacto -->
    <a href="#" class="contact-link">Contacto</a>
    <div class="contact-tooltip">
      📧 <a href="mailto:soporte@tradeconnect.com">soporte@tradeconnect.com</a><br>
      📸 <a href="https://instagram.com/tradeconnect.uy" target="_blank">@tradeconnect.uy</a>
    </div>

    <a href="acerca.php">Acerca de</a>
  </div>
  <div>© 2025 TradeConnect</div>
</footer>

<script>
  // PHP → JS
  const byDay = <?= json_encode($byDay, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  const modal  = document.getElementById('modal');
  const mTitle = document.getElementById('m-title');
  const mList  = document.getElementById('m-list');
  const mClose = document.getElementById('m-close');

  const formatDMY = (ymd) => {
    const d = new Date(ymd + 'T00:00:00');
    const dd = ('0'+d.getDate()).slice(-2);
    const mm = ('0'+(d.getMonth()+1)).slice(-2);
    return dd+'/'+mm+'/'+d.getFullYear();
  };

  document.querySelectorAll('.card').forEach(c=>{
    c.addEventListener('click', ()=>{
      const day = c.dataset.day;
      if (!byDay[day]) return; // libre

      mTitle.textContent = 'Reservas del ' + formatDMY(day);
      mList.innerHTML = '';
      byDay[day].forEach(r => {
        const row = document.createElement('div');
        row.className = 'row';
        const ava = r.avatar_url ? r.avatar_url : 'uploads/default-avatar.webp';
        row.innerHTML = `
          <div class="avatar"><img src="${ava}" alt=""></div>
          <div>
            <div><strong>${r.srv_titulo}</strong></div>
            <div class="muted">${r.hora.substring(0,5)} · @${r.usuario}</div>
          </div>
          <div style="margin-left:auto">
            <a class="btn btn-outline" href="mensajes.php?to=${r.id_cliente}" target="_blank">Abrir chat</a>
          </div>
        `;
        mList.appendChild(row);
      });

      modal.classList.add('show');
    });
  });

  mClose.addEventListener('click', ()=> modal.classList.remove('show'));
  modal.addEventListener('click', (e)=>{ if(e.target===modal) modal.classList.remove('show'); });
</script>
</body>
</html>
