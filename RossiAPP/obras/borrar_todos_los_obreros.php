<?php
require_once("../config.php");

// Borra primero los pagos y asignaciones para evitar errores de FK
$conn->query("DELETE FROM pagos_semanales");
$conn->query("DELETE FROM obra_obrero");

// Ahora borra todos los obreros
$conn->query("DELETE FROM obreros");

echo "<h2 style='color:green;'>¡Listo! Todos los obreros, pagos y asignaciones fueron borrados.</h2>";
echo "<a href='obrero_ver.php'>Volver a gestión de obreros</a>";
?>
