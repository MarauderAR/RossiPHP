<?php
require_once("../config.php");

$contratista_id = intval($_POST['contratista_id'] ?? 0);
$fecha = $_POST['fecha'] ?? '';
$monto = floatval($_POST['monto'] ?? 0);
$observaciones = $_POST['observaciones'] ?? '';
$foto = null;

// Manejar foto subida
if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
    $ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
    $foto = uniqid('pago_').'.'.$ext;
    move_uploaded_file($_FILES['foto']['tmp_name'], __DIR__ . "/uploads/" . $foto);
}

$stmt = $conn->prepare("INSERT INTO pagos_contratistas (contratista_id, fecha, monto, observaciones, foto) VALUES (?, ?, ?, ?, ?)");
$stmt->bind_param("isdss", $contratista_id, $fecha, $monto, $observaciones, $foto);
$stmt->execute();
$stmt->close();

header("Location: contratista_ver.php?id=$contratista_id");
exit;
