<?php
// login.php — muestra errores en el mismo formulario
session_start();
require 'conexion.php';
require_once 'utils.php'; // ← para usar Seguridad::verificar

$mensaje = '';

if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['usuario'], $_POST['contraseña'])) {
    $usuario_input = trim($_POST['usuario']);
    $contraseña = (string)$_POST['contraseña'];

    $sql = "SELECT id_usuario, `contraseña`, rol, baneado
            FROM usuario
            WHERE correo = ? OR usuario = ?
            LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("ss", $usuario_input, $usuario_input);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $stmt->close();

            if ((int)$user['baneado'] === 1) {
                $mensaje = "Tu cuenta está suspendida.";
            } elseif (!Seguridad::verificar($contraseña, $user['contraseña'])) { // ← uso de la clase
                $mensaje = "Contraseña incorrecta.";
            } else {
                // ✅ inicio de sesión correcto
                $_SESSION['id_usuario'] = (int)$user['id_usuario'];
                $_SESSION['rol']        = $user['rol'];

                if ($user['rol'] === 'admin') {
                    header("Location: admin.php");
                } elseif ($user['rol'] === 'proveedor') {
                    header("Location: panel_proveedor.php");
                } else {
                    header("Location: panel_cliente.php");
                }
                exit;
            }
        } else {
            $mensaje = "Usuario no encontrado.";
        }
    } else {
        $mensaje = "Error interno: no se pudo procesar la solicitud.";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ingresar - TradeConnect</title>
  <style>
    body{
      margin:0; font-family:'Segoe UI',sans-serif; color:#fff;
      background:url('images/background-blur.png') no-repeat center center fixed;
      background-size:cover; min-height:100vh; display:flex; flex-direction:column;
    }
    header{
      display:flex; justify-content:space-between; align-items:center;
      padding:16px 24px; background:rgba(0,0,0,.35); backdrop-filter: blur(6px);
    }
    nav a{
      color:#fff; margin:0 16px; text-decoration:none;
      font-weight:700; font-size:1.05rem;
    }
    .logo{ display:flex; align-items:center; gap:12px }
    .logo img{ width:80px; margin-right:15px }
    .logo span{ font-size:1.8em; font-weight:800 }

    .container{
      background-color:rgba(0,0,0,.40);
      padding:50px; border-radius:12px; width:400px; text-align:center; margin:auto;
      box-shadow:0 10px 40px rgba(0,0,0,.25);
      border:1px solid rgba(255,255,255,.15);
    }
    .container h2{ margin-bottom:20px }

    input[type="text"], input[type="password"]{
      width:100%; padding:12px; margin:12px 0; border-radius:6px; border:none; font-size:1em;
      background:#fffffff2; color:#0a0f1a;
    }
    .btn{
      width:100%; padding:12px; background:#3399ff; border:none; border-radius:8px;
      color:#fff; font-weight:800; font-size:1em; cursor:pointer; margin-top:10px;
    }
    .switch{ margin-top:15px; font-size:.9em }
    .switch a{ color:#3399ff; text-decoration:none; font-weight:700 }

    .error-box{
      background:rgba(255,80,80,.18);
      border:1px solid rgba(255,80,80,.35);
      padding:10px 14px;
      border-radius:10px;
      margin-bottom:15px;
      color:#fff;
      font-weight:600;
    }

    footer{
      margin-top:auto; font-size:.9em; color:#fff; padding:20px;
      display:flex; justify-content:space-between; align-items:center;
      background:rgba(0,0,0,.35);
    }
    .footer-left a{ color:#fff; margin-right:10px; text-decoration:none }
  </style>
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

  <div class="container">
    <h2>Iniciar Sesión</h2>

    <?php if ($mensaje): ?>
      <div class="error-box"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <form action="login.php" method="POST">
      <input type="text" name="usuario" placeholder="Correo o usuario" required value="<?= htmlspecialchars($_POST['usuario'] ?? '') ?>">
      <input type="password" name="contraseña" placeholder="Contraseña" required>
      <button class="btn" type="submit">Ingresar</button>
    </form>
    <div class="switch">
      ¿No tienes cuenta? <a href="register.html">Regístrate</a>
    </div>
  </div>

  <footer>
    <div class="footer-left">
      <a href="#">Términos</a>
      <a href="#">Contacto</a>
      <a href="acerca.html">Acerca de</a>
    </div>
    <div class="footer-right">
      2025 TradeConnect
    </div>
  </footer>
</body>
</html>
