<?php
require_once("../config.php");
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $direccion = $_POST['direccion'];
    $stmt = $conn->prepare("INSERT INTO proveedores (nombre, telefono, direccion) VALUES (?, ?, ?)");
    $stmt->bind_param("sss", $nombre, $telefono, $direccion);
    $stmt->execute();
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<link rel="stylesheet" href="style.css">

    <title>Agregar Proveedor</title>
</head>
<body>
    <h2>Nuevo Proveedor</h2>
    <form method="post">
        Nombre: <input type="text" name="nombre" required><br>
        Teléfono: <input type="text" name="telefono"><br>
        Dirección: <input type="text" name="direccion"><br>
        <input type="submit" value="Agregar">
    </form>
    <a href="index.php">Volver</a>
</body>
</html>
