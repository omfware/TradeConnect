<?php
session_start();
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tutorial / Tour - TradeConnect</title>
<link rel="stylesheet" href="css/tour.css">
</head>
<body>
<header>
  <div class="logo"><img src="logo.png" alt=""><span><strong>Trade</strong>Connect</span></div>
  <nav>
    <a href="index.html">Inicio</a>
    <a href="categorias.php">Categorías</a>
    <a href="acerca.php">Acerca de</a>
    <a href="login.html">Ingresar</a>
  </nav>
</header>

<main class="main">
  <h1>Tour rápido (3 pasos)</h1>
  <div class="lead">Un vistazo para empezar a usar la plataforma en minutos.</div>

  <div class="steps">
    <div class="step">
      <div class="num">1</div>
      <div>
        <h3>Buscá o explorá servicios</h3>
        <p>Usá el buscador por palabra clave, categoría y ubicación para encontrar a los mejores proveedores.</p>
        <div class="actions">
          <a class="btn" href="buscar.php">Ir a Buscar</a>
          <a class="btn" href="categorias.php">Ver categorías</a>
        </div>
      </div>
    </div>

    <div class="step">
      <div class="num">2</div>
      <div>
        <h3>Contactá o reservá</h3>
        <p>Desde la ficha del servicio podés enviar mensajes o agendar una reserva con fecha y hora.</p>
        <div class="actions">
          <a class="btn" href="panel_cliente.php">Ir al panel</a>
        </div>
      </div>
    </div>

    <div class="step">
      <div class="num">3</div>
      <div>
        <h3>Completá tu perfil</h3>
        <p>Mejorá tu visibilidad agregando avatar, bio, teléfono y dirección. Si sos proveedor, publicá tus servicios.</p>
        <div class="actions">
          <a class="btn" href="editar_perfil_cliente.php">Editar perfil</a>
          <a class="btn" href="buscar.php">Buscar servicios</a>
        </div>
      </div>
    </div>
  </div>
</main>

<footer>
  <div class="footer-left">
    <a href="terminos.php">Términos</a>

    <a href="#" class="contact-link">Contacto</a>
    <div class="contact-tooltip">
      📧 <a href="mailto:soporte@tradeconnect.com">soporte@tradeconnect.com</a><br>
      📸 <a href="https://instagram.com/tradeconnect.uy" target="_blank">@tradeconnect.uy</a>
    </div>

    <a href="acerca.php">Acerca de</a>
  </div>
  <div>© 2025 TradeConnect</div>
</footer>
</body>
</html>
