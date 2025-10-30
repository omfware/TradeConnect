<?php
session_start();
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Acerca de - TradeConnect</title>
  <link rel="stylesheet" href="css/acerca.css">
</head>
<body>
  <header>
    <div class="logo">
      <img src="images/logo.png" alt="Logo" />
      <span><strong>Trade</strong>Connect</span>
    </div>
    <nav>
      <?php if (isset($_SESSION['id_usuario'])): ?>
        <a href="panel_cliente.php">Inicio</a>
      <?php else: ?>
        <a href="index.html">Inicio</a>
      <?php endif; ?>

      <a href="categorias.php">Categorías</a>
      <a href="acerca.php">Acerca de</a>

      <?php if (isset($_SESSION['id_usuario'])): ?>
        <a class="logout" href="logout.php">Cerrar sesión</a>
      <?php else: ?>
        <a href="login.html">Ingresar</a>
      <?php endif; ?>
    </nav>
  </header>

  <div class="content">
    <h2>¿Qué es TradeConnect?</h2>
    <p>
      TradeConnect es una plataforma digital que conecta a personas que necesitan realizar tareas específicas (electricidad, fontanería, limpieza, pintura, entre otros) con profesionales confiables y calificados de su localidad.
    </p>

    <h3>Nuestra historia</h3>
    <p>
      TradeConnect nació con la visión de simplificar la manera en que las personas acceden a servicios esenciales. Frente a la dificultad de encontrar proveedores confiables, un grupo de estudiantes decidió crear una plataforma que una calidad, cercanía y confianza.
    </p>

    <h3>Nuestra misión</h3>
    <p>
      Facilitar el acceso a servicios de calidad y promover el trabajo de oficios a través de una herramienta moderna, transparente y segura.
    </p>

    <h3>¿Cómo funciona?</h3>
    <ul>
      <li>Los proveedores publican sus servicios en categorías específicas</li>
      <li>Los clientes pueden buscar servicios por ubicación, categoría y calificaciones</li>
      <li>Ambas partes pueden comunicarse mediante un sistema de mensajería interna</li>
      <li>Los clientes dejan reseñas para mejorar la calidad del servicio ofrecido</li>
    </ul>

    <h3>¿Por qué elegirnos?</h3>
    <ul>
      <li>Interfaz intuitiva y moderna</li>
      <li>Sistema de calificaciones transparente</li>
      <li>Seguridad en los datos de usuario</li>
      <li>Servicios locales confiables</li>
    </ul>

    <h3>Visión</h3>
    <p>Ser la plataforma de referencia para contratar servicios de oficios en todo Uruguay.</p>

    <h3>Valores</h3>
    <ul>
      <li>Transparencia</li>
      <li>Compromiso</li>
      <li>Confianza</li>
      <li>Innovación</li>
    </ul>

    <h3>Lo que dicen nuestros usuarios</h3>
    <blockquote>“Gracias a TradeConnect encontré un electricista en minutos y el trabajo fue impecable.” — Juan M., Montevideo</blockquote>
    <blockquote>“Fácil de usar y muy confiable. Lo recomiendo.” — Laura G., Canelones</blockquote>

    <div class="cta">
      <a href="index.html">¡Explora servicios ahora!</a>
    </div>
  </div>

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
    <div class="footer-right">
      © 2025 TradeConnect
    </div>
  </footer>
</body>
</html>
