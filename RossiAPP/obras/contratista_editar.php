<?php
require_once("../config.php");
$id = intval($_GET["id"] ?? 0);
$row = $conn->query("SELECT * FROM contratistas WHERE id=$id")->fetch_assoc();
if(!$row) die("No encontrado.");
if ($_SERVER["REQUEST_METHOD"]=="POST") {
    $nombre = $_POST["nombre"];
    $rubro = $_POST["rubro"];
    $monto = floatval(str_replace(['.',' ', ','], ['', '', '.'], $_POST["monto_acordado"]));
    $obs = $_POST["observaciones"];
    $conn->query("UPDATE contratistas SET nombre='".$conn->real_escape_string($nombre)."', rubro='".$conn->real_escape_string($rubro)."', monto_acordado=$monto, observaciones='".$conn->real_escape_string($obs)."' WHERE id=$id");
    header("Location: contratistas.php");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Editar Contratista</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f6f8fa;">
<div class="container my-4">
    <h2>Editar Contratista</h2>
    <form method="post">
        <div class="mb-3">
            <label class="form-label">Nombre</label>
            <input name="nombre" class="form-control" value="<?=htmlspecialchars($row['nombre'])?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Rubro</label>
            <input name="rubro" class="form-control" value="<?=htmlspecialchars($row['rubro'])?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Monto acordado ($)</label>
            <input name="monto_acordado" class="form-control" value="<?=number_format($row['monto_acordado'],2,',','.')?>" required>
        </div>
        <div class="mb-3">
            <label class="form-label">Observaciones</label>
            <input name="observaciones" class="form-control" value="<?=htmlspecialchars($row['observaciones'])?>">
        </div>
        <button class="btn btn-success">Guardar</button>
        <a href="contratistas.php" class="btn btn-secondary">Volver</a>
    </form>
</div>
</body>
</html>
