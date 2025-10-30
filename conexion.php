<?php
$host = 'localhost';
$db = 'PlataformaOficios';
$user = 'root'; // Cambiar si usás otro usuario
$pass = 'rootpassword';     // Cambiar si tu usuario tiene otra contraseña

$conn = new mysqli($host, $user, $pass, $db);
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}
?>