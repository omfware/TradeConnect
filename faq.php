<?php
session_start();
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Preguntas frecuentes - TradeConnect</title>
<link rel="stylesheet" href="css/faq.css">
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
  <h1>Preguntas frecuentes</h1>
  <div class="lead">Resolvé las dudas más comunes sobre el funcionamiento de TradeConnect.</div>

  <div class="faq" id="faq">
    <div class="item">
      <div class="q">¿Cómo busco un servicio?<span class="chev">▶</span></div>
      <div class="a">Ingresá a <strong>Buscar</strong>, escribí una palabra clave (ej.: “electricista”) y, si querés, filtrá por ubicación y categoría.</div>
    </div>
    <div class="item">
      <div class="q">¿Cómo contacto a un proveedor?<span class="chev">▶</span></div>
      <div class="a">Desde la ficha del servicio, hacé clic en <em>Contactar</em> para abrir el chat y coordinar detalles.</div>
    </div>
    <div class="item">
      <div class="q">¿Puedo reservar fecha y hora?<span class="chev">▶</span></div>
      <div class="a">Sí. En servicios que lo permitan vas a ver el botón <em>Contratar / Reservar</em> para elegir día y hora.</div>
    </div>
    <div class="item">
      <div class="q">Soy proveedor, ¿cómo publico?<span class="chev">▶</span></div>
      <div class="a">Iniciá sesión como proveedor y entrá a <strong>Publicar servicio</strong>. Completá título, descripción, precio, ubicación e imagen.</div>
    </div>
    <div class="item">
      <div class="q">¿Cómo mejoro mi visibilidad?<span class="chev">▶</span></div>
      <div class="a">Completá tu perfil (avatar, bio, teléfono y dirección) y mantené buenas reseñas de tus clientes.</div>
    </div>
  </div>

  <div class="actions">
    <a class="btn" href="buscar.php">Ir a Buscar</a>
    <a class="btn" href="panel_cliente.php">Ir al panel</a>
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

<script>
  // Acordeón simple
  document.querySelectorAll('#faq .item .q').forEach(q=>{
    q.addEventListener('click', ()=> q.parentElement.classList.toggle('open'));
  });
</script>
</body>
</html>
