<?php
require 'conexion.php';
require_once 'utils.php'; // ← para usar new Seguridad(...)

// Obtener datos
$nombre_completo = trim($_POST['nombre_completo'] ?? '');
$correo          = trim($_POST['correo'] ?? '');
$usuario         = trim($_POST['usuario'] ?? '');
$telefono        = trim($_POST['telefono'] ?? '');
$direccion       = trim($_POST['direccion'] ?? '');
$rol             = trim($_POST['rol'] ?? '');
$pass            = $_POST['contraseña'] ?? '';
$pass2           = $_POST['confirmar_contraseña'] ?? '';

// Validaciones básicas
if ($nombre_completo === '' || $correo === '' || $usuario === '' || $telefono === '' || $direccion === '' || $rol === '' || $pass === '' || $pass2 === '') {
    exit('Faltan datos obligatorios.');
}
if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    exit('Correo inválido.');
}
if ($pass !== $pass2) {
    exit('Las contraseñas no coinciden.');
}

// Validar rol permitido 
$roles_permitidos = ['cliente', 'proveedor'];
if (!in_array($rol, $roles_permitidos, true)) {
    exit('Rol inválido.');
}

// Separar nombre y apellido 
$partes = preg_split('/\s+/', $nombre_completo, -1, PREG_SPLIT_NO_EMPTY);
$nombre   = $partes[0] ?? '';
$apellido = count($partes) > 1 ? implode(' ', array_slice($partes, 1)) : '';

// Verificar unicidad de correo y usuario
$check = $conn->prepare("SELECT 1 FROM usuario WHERE correo = ? OR usuario = ? LIMIT 1");
if (!$check) {
    exit('Error al preparar la consulta de verificación: ' . $conn->error);
}
$check->bind_param("ss", $correo, $usuario);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    $check->close();
    exit('El correo o el nombre de usuario ya está en uso.');
}
$check->close();

// Hashear contraseña con CONSTRUCTOR (requisito)
$seg  = new Seguridad((string)$pass);
$hash = $seg->getHash();

// Insertar
$sql = "INSERT INTO usuario (nombre, apellido, usuario, correo, `contraseña`, telefono, rol, direccion)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    exit('Error al preparar la consulta: ' . $conn->error);
}
$stmt->bind_param("ssssssss", $nombre, $apellido, $usuario, $correo, $hash, $telefono, $rol, $direccion);

if ($stmt->execute()) {
    // Iniciar sesión y redirigir según rol
    session_start();
    $_SESSION['id_usuario'] = $stmt->insert_id;
    $_SESSION['rol'] = $rol;

    if ($rol === 'proveedor') {
        header("Location: panel_proveedor.php");
    } else { // cliente
        header("Location: panel_cliente.php");
    }
    $stmt->close();
    $conn->close();
    exit;
} else {
    echo "Error al registrar: " . $stmt->error;
}

$stmt->close();
$conn->close();
