<?php
require_once("../config.php");

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $nombre = trim($_POST['nombre']);
    $capataz = trim($_POST['capataz']);

    if ($nombre) {
        $stmt = $conn->prepare("INSERT INTO obras (nombre, capataz) VALUES (?, ?)");
        $stmt->bind_param("ss", $nombre, $capataz);
        $stmt->execute();
        header("Location: index.php");
        exit;
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <title>Agregar Nueva Obra</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Agregar Nueva Obra</h2>
    <form method="post">
        Nombre de la Obra: <input type="text" name="nombre" required><br><br>
        Capataz: <input type="text" name="capataz"><br><br>
        <input type="submit" value="Agregar">
    </form>
    <br>
    <a href="index.php">Volver a obras</a>
</body>
</html>
