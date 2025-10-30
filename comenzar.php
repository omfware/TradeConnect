<?php
// comenzar.php
session_start();
function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Comenzar - TradeConnect</title>
<link rel="stylesheet" href="css/comenzar.css">
</head>
<body>

<header>
  <div class="logo">
    <img src="images/logo.png" alt="Logo">
    <span><strong>Trade</strong>Connect</span>
  </div>
  <nav>
    <a href="index.html">Inicio</a>
    <a href="categorias.php">Categorías</a>
    <a href="acerca.php">Acerca de</a>
    <a href="login.html">Ingresar</a>
  </nav>
</header>

<main class="main">
  <div class="hero">
    <div>
      <h1>¿Qué querés hacer primero?</h1>
      <div class="lead">Acciones rápidas para empezar: buscar, publicar, completar tu perfil o ver tutoriales.</div>
    </div>
    <div style="margin-left:auto" class="note">
      Sugerencia: completá tu perfil para obtener mejores coincidencias.
    </div>
  </div>

  <div class="grid">
    <div class="card">
      <h3>Buscar servicios</h3>
      <p>Encontrá profesionales por categoría, ubicación o palabra clave.</p>
      <div class="actions">
        <a class="btn" href="buscar.php">Ir a Buscar</a>
        <a class="btn" href="categorias.php">Ver categorías</a>
      </div>
    </div>

    <div class="card">
      <h3>Publicar servicio</h3>
      <p>¿Sos proveedor? Publicá tu servicio para empezar a recibir contactos.</p>
      <div class="actions">
        <a class="btn" href="login.html">Iniciar sesión como proveedor</a>
      </div>
    </div>

    <div class="card">
      <h3>Completar perfil</h3>
      <p>Mejorá tu visibilidad completando dirección, avatar y bio.</p>
      <div class="actions">
        <a class="btn" href="login.html">Ingresar</a>
      </div>
    </div>

    <div class="card">
      <h3>Tutorial / Tour</h3>
      <p>Un tour de 3 pasos para aprender a usar la plataforma, y respuestas a las dudas comunes.</p>
      <div class="actions">
        <a class="btn" href="tour.php">Ver tutorial</a>
        <a class="btn" href="faq.php">Preguntas frecuentes</a>
      </div>
    </div>
  </div>

  <div class="center">
    <a class="cta-large" href="buscar.php">Ir a buscar servicios</a>
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
