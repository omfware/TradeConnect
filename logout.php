<?php
session_start();

// Destruir todas las variables de sesión
$_SESSION = [];

// Destruir la sesión
session_destroy();

// Redirigir al inicio (index.html)
header("Location: index.html");
exit;
?>
