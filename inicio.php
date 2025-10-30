<?php
session_start();
if (!isset($_SESSION['id_usuario'])) {
    header("Location: login.html");
    exit;
}

$rol = $_SESSION['rol'] ?? 'cliente';

if ($rol === 'proveedor') {
    header("Location: panel_proveedor.php");
    exit;
} elseif ($rol === 'cliente') {
    header("Location: panel_cliente.php");
    exit;
} else {
    header("Location: panel_admin.php"); // si no existe, podés redirigir a panel_cliente.php
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Panel de Usuario - TradeConnect</title>
  <link rel="stylesheet" href="css/inicio.css">
</head>
<body>
  <header>
    <div class="logo">
      <img src="logo.png" alt="Logo" />
      <span><strong>Trade</strong>Connect</span>
    </div>
    <nav>
      <a href="inicio.php">Inicio</a>
      <a href="categorias.php">Categorías</a>
      <a href="acerca.php">Acerca de</a>
      <a class="logout" href="logout.php">Cerrar sesión</a>
    </nav>
  </header>

  <main class="main-content">
    <h1>Bienvenido a tu panel</h1>
    <p>Explorá los servicios disponibles o utilizá el buscador para encontrar lo que necesitás. Estamos para conectarte con los mejores profesionales.</p>
    <a class="button" href="buscar.html">Comenzar</a>

    <div class="categories">
      <div class="category"><a href="electricidad.html">⚡ Electricidad</a></div>
      <div class="category"><a href="fontaneria.html">🔧 Fontanería</a></div>
      <div class="category"><a href="limpieza.html">🧼 Limpieza</a></div>
      <div class="category"><a href="pintura.html">🎨 Pintura</a></div>
    </div>
  </main>

  <footer>
    <div>
      <a href="#">Términos</a>
      <a href="#">Contacto</a>
      <a href="acerca.html">Acerca de</a>
    </div>
    <div>
      © 2025 TradeConnect
    </div>
  </footer>
</body>
</html>
