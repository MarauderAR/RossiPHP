<?php
require_once("../config.php");
$factura_id = intval($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $archivo = null;
    if (!empty($_FILES['archivo']['name'])) {
        $carpeta = "uploads/";
        if (!file_exists($carpeta)) mkdir($carpeta, 0777, true);
        $archivo = time() . "_" . basename($_FILES["archivo"]["name"]);
        move_uploaded_file($_FILES["archivo"]["tmp_name"], $carpeta . $archivo);

        // Guardar el archivo en la base de datos
        $stmt = $conn->prepare("UPDATE facturas SET archivo = ? WHERE id = ?");
        $stmt->bind_param("si", $archivo, $factura_id);
        $stmt->execute();
    }
    // Redirigir de nuevo a la vista de facturas del proveedor
    $proveedor_id = $conn->query("SELECT proveedor_id FROM facturas WHERE id = $factura_id")->fetch_assoc()['proveedor_id'];
    header("Location: ver_proveedor.php?id=$proveedor_id");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

    <title>Adjuntar Archivo</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <h2>Adjuntar PDF a la factura</h2>
    <form method="post" enctype="multipart/form-data">
        <input type="file" name="archivo" accept="application/pdf" required>
        <button type="submit">Subir PDF</button>
    </form>
    <br>
    <a href="javascript:history.back()">Volver</a>
</body>
</html>
