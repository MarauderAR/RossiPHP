<?php
require_once("../config.php");
$id = intval($_GET['id'] ?? 0);
$conn->query("DELETE FROM pagos_contratistas WHERE contratista_id=$id");
$conn->query("DELETE FROM contratistas WHERE id=$id");
header("Location: contratistas.php");
exit;
?>
