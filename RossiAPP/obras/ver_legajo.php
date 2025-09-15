<?php
require_once("../config.php");
$id = intval($_GET['id'] ?? 0);
$legajo = $conn->query("SELECT * FROM legajos WHERE obrero_id = $id")->fetch_assoc();

if (!$legajo) {
    echo "<b>Legajo no cargado.</b>";
    exit;
}
?>
<b>Apellido y Nombre:</b> <?=htmlspecialchars($legajo['apellido_nombre'])?><br>
<b>CUIL:</b> <?=htmlspecialchars($legajo['cuil'])?><br>
<b>Convenio:</b> <?=htmlspecialchars($legajo['convenio'])?><br>
<b>Categoría:</b> <?=htmlspecialchars($legajo['categoria'])?><br>
<b>Fecha Ingreso:</b> <?=htmlspecialchars($legajo['fecha_ingresa'])?><br>
<b>Fecha Egreso:</b> <?=htmlspecialchars($legajo['fecha_egreso'])?><br>
<button onclick="cerrarModal()" style="margin-top:16px;">Cerrar</button>
