<?php
require_once("../config.php");
$id = intval($_GET['id'] ?? 0);
$obra_id = intval($_GET['obra_id'] ?? 0);
$semana = $_GET['semana'] ?? '';

if ($id > 0) {
    $conn->query("DELETE FROM pagos_semanales WHERE id=$id LIMIT 1");
}
header("Location: obra_resumen.php?id=$obra_id&semana=".urlencode($semana));
exit;
?>
