<?php
require_once("../config.php");

$id = intval($_POST['id'] ?? 0);
$contratista_id = intval($_POST['contratista_id'] ?? 0);

$pago = $conn->query("SELECT foto FROM pagos_contratistas WHERE id=$id")->fetch_assoc();
if ($pago && $pago['foto']) {
    $foto_path = __DIR__ . "/uploads/" . $pago['foto'];
    if (file_exists($foto_path)) unlink($foto_path);
}

$conn->query("DELETE FROM pagos_contratistas WHERE id=$id");

header("Location: contratista_ver.php?id=$contratista_id");
exit;
