<?php
// reserva_accion.php — Aceptar / Rechazar reserva
session_start();
require 'conexion.php';
require_once 'utils.php';

if (!isset($_SESSION['id_usuario'])) { header("Location: login.html"); exit; }
if (($_SESSION['rol'] ?? '') !== 'proveedor') { header("Location: inicio.php"); exit; }

$idProveedor = (int)$_SESSION['id_usuario'];

$accion     = $_POST['accion']     ?? '';
$idReserva  = (int)($_POST['id_reserva'] ?? 0);

if (!$idReserva || !in_array($accion, ['aceptar','rechazar'], true)) {
  header("Location: panel_proveedor.php?res_err=1"); exit;
}

// Traer la reserva + verificar que pertenece a un servicio del proveedor
$sql = "
  SELECT r.id_reserva, r.id_servicio, r.id_cliente, r.fecha, r.hora, r.estado,
         s.titulo AS srv_titulo, s.id_usuario AS id_prov_duenio,
         u.usuario AS cli_user
  FROM reserva r
  JOIN servicio s ON s.id_servicio = r.id_servicio
  JOIN usuario  u ON u.id_usuario  = r.id_cliente
  WHERE r.id_reserva = ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $idReserva);
$stmt->execute();
$rv = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rv || (int)$rv['id_prov_duenio'] !== $idProveedor) {
  header("Location: panel_proveedor.php?res_err=1"); exit;
}

$idCliente = (int)$rv['id_cliente'];
$fecha     = $rv['fecha'];
$horaSQL   = $rv['hora'];
$titulo    = $rv['srv_titulo'];

// Si rechazar → actualizar y avisar
if ($accion === 'rechazar') {
  $stmt = $conn->prepare("UPDATE reserva SET estado='rechazada' WHERE id_reserva=? LIMIT 1");
  $stmt->bind_param("i", $idReserva);
  if ($stmt->execute()) {
    $stmt->close();

    // Aviso al cliente
    $texto = "Tu solicitud para \"{$titulo}\" el ".date('d/m/Y', strtotime($fecha))." a las ".substr($horaSQL,0,5)." fue rechazada.";
    $stmt = $conn->prepare("
      INSERT INTO mensaje (id_emisor, id_receptor, contenido, fecha_envio, leido, eliminado_por_emisor, eliminado_por_receptor)
      VALUES (?, ?, ?, NOW(), 0, 0, 0)
    ");
    $stmt->bind_param("iis", $idProveedor, $idCliente, $texto);
    $stmt->execute();
    $stmt->close();

    header("Location: panel_proveedor.php?res_ok=1"); exit;
  } else {
    $stmt->close();
    header("Location: panel_proveedor.php?res_err=1"); exit;
  }
}

// Aceptar → verificar conflicto y actualizar
// Conflicto: mismo proveedor, misma fecha y hora, en estado pendiente/aceptada (otra reserva distinta)
$sql = "
  SELECT 1
  FROM reserva r
  JOIN servicio s ON s.id_servicio = r.id_servicio
  WHERE s.id_usuario = ?
    AND r.fecha = ?
    AND r.hora  = ?
    AND r.estado IN ('pendiente','aceptada')
    AND r.id_reserva <> ?
  LIMIT 1
";
$stmt = $conn->prepare($sql);
$stmt->bind_param("issi", $idProveedor, $fecha, $horaSQL, $idReserva);
$stmt->execute();
$conflict = (bool)$stmt->get_result()->fetch_row();
$stmt->close();

if ($conflict) {
  header("Location: panel_proveedor.php?res_err=1"); exit;
}

// Actualizar a aceptada
$stmt = $conn->prepare("UPDATE reserva SET estado='aceptada' WHERE id_reserva=? LIMIT 1");
$stmt->bind_param("i", $idReserva);
if ($stmt->execute()) {
  $stmt->close();

  // Avisar al cliente
  $texto = "¡Tu solicitud para \"{$titulo}\" fue aceptada! Día ".date('d/m/Y', strtotime($fecha))." a las ".substr($horaSQL,0,5).".";
  $stmt = $conn->prepare("
    INSERT INTO mensaje (id_emisor, id_receptor, contenido, fecha_envio, leido, eliminado_por_emisor, eliminado_por_receptor)
    VALUES (?, ?, ?, NOW(), 0, 0, 0)
  ");
  $stmt->bind_param("iis", $idProveedor, $idCliente, $texto);
  $stmt->execute();
  $stmt->close();

  header("Location: panel_proveedor.php?res_ok=1"); exit;
} else {
  $stmt->close();
  header("Location: panel_proveedor.php?res_err=1"); exit;
}
