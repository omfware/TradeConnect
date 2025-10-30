<?php
session_start();

$rol = $_SESSION['rol'] ?? null;
$isCliente = isset($_SESSION['id_usuario']) && $rol === 'cliente';
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Categorías - TradeConnect</title>
  <link rel="stylesheet" href="css/categorias.css">
</head>
<body>
  <header>
    <div class="logo">
      <img src="images/logo.png" alt="Logo">
      <span><strong>Trade</strong>Connect</span>
    </div>

    <nav>
      <?php if ($isCliente): ?>
        <a href="panel_cliente.php">Inicio</a>
        <a href="categorias.php">Categorías</a>
        <a href="mis_reservas_cliente.php">Mis reservas</a>
        <a href="editar_perfil_cliente.php">Editar Perfil</a>
        <a href="mensajes.php">Bandeja de Entrada</a>
        <a class="logout" href="logout.php">Cerrar Sesión</a>
      <?php else: ?>
        <a href="<?= isset($_SESSION['id_usuario']) ? 'inicio.php' : 'index.html' ?>">Inicio</a>
        <a href="categorias.php">Categorías</a>
        <?php if (isset($_SESSION['id_usuario'])): ?>
          <a class="logout" href="logout.php">Cerrar Sesión</a>
        <?php else: ?>
          <a href="acerca.php">Acerca de</a>
          <a href="login.html">Ingresar</a>
        <?php endif; ?>
      <?php endif; ?>
    </nav>
  </header>

  <main>
    <h1>Explorá Categorías</h1>
    <div class="subtitle">Conectá con profesionales locales para tus necesidades</div>

    <div class="categories">
      <a href="categoria_panel.php?slug=electricidad" class="category-card">
        <span class="category-icon">⚡</span>
        <div class="category-name">Electricidad</div>
        <div class="category-desc">Instalaciones, reparaciones y mantenimiento.</div>
      </a>

      <a href="categoria_panel.php?slug=fontaneria" class="category-card">
        <span class="category-icon">🛠️</span>
        <div class="category-name">Fontanería</div>
        <div class="category-desc">Cañerías, grifería y sistemas sanitarios.</div>
      </a>

      <a href="categoria_panel.php?slug=limpieza" class="category-card">
        <span class="category-icon">🧹</span>
        <div class="category-name">Limpieza</div>
        <div class="category-desc">Hogar y oficinas impecables.</div>
      </a>

      <a href="categoria_panel.php?slug=pintura" class="category-card">
        <span class="category-icon">🎨</span>
        <div class="category-name">Pintura</div>
        <div class="category-desc">Transformá tus espacios con color.</div>
      </a>
    </div>

    <div class="benefits">
      <h2>¿Por qué elegir TradeConnect?</h2>
      <p>✔️ Profesionales verificados y de confianza.</p>
      <p>✔️ Búsqueda por categoría y ubicación.</p>
      <p>✔️ Experiencia clara y sencilla para encontrar lo que necesitás.</p>
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
