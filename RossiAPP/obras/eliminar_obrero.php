<?php
require_once("../config.php");
$obrero_id = intval($_POST['obrero_id'] ?? 0);
$obra_id = intval($_POST['obra_id'] ?? 0);

if ($obrero_id > 0) {
    // Eliminar el obrero (y pagos asociados por ON DELETE CASCADE)
    $conn->query("DELETE FROM obreros WHERE id = $obrero_id");
}
header("Location: obra_ver.php?id=" . $obra_id);
exit;
?>
