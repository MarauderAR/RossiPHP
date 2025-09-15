<?php
require_once("../config.php");
$id = intval($_GET['id'] ?? 0);
$c = $conn->query("SELECT * FROM contratistas WHERE id=$id")->fetch_assoc();
if(!$c) die("No existe contratista");
$res = $conn->query("SELECT * FROM pagos_contratistas WHERE contratista_id=$id ORDER BY fecha");
$pagado = $conn->query("SELECT COALESCE(SUM(monto),0) as pagado FROM pagos_contratistas WHERE contratista_id=$id")->fetch_assoc()['pagado'];
$saldo = $c['monto_acordado'] - $pagado;
if ($_SERVER['REQUEST_METHOD']=="POST") {
    // Agregar pago
    $fecha = $_POST['fecha'];
    $monto = floatval(str_replace(['.',' ', ','], ['', '', '.'], $_POST['monto']));
    $archivo = '';
    if(isset($_FILES['archivo']) && $_FILES['archivo']['tmp_name']){
        $fn = uniqid().basename($_FILES['archivo']['name']);
        move_uploaded_file($_FILES['archivo']['tmp_name'], "uploads/".$fn);
        $archivo = $fn;
    }
    $conn->query("INSERT INTO pagos_contratistas (contratista_id, fecha, monto, archivo) VALUES ($id, '$fecha', $monto, '$archivo')");
    header("Location: contratista_ver.php?id=$id");
    exit;
}
if(isset($_GET['borrar_pago'])){
    $pid = intval($_GET['borrar_pago']);
    $conn->query("DELETE FROM pagos_contratistas WHERE id=$pid");
    header("Location: contratista_ver.php?id=$id");
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Detalle Contratista</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f6f8fa;">
<div class="container my-4">
    <h2>Detalle de Contratista</h2>
    <div class="mb-3">
        <b>Nombre:</b> <?=htmlspecialchars($c['nombre'])?><br>
        <b>Rubro:</b> <?=htmlspecialchars($c['rubro'])?><br>
        <b>Observaciones:</b> <?=htmlspecialchars($c['observaciones'])?><br>
        <b>Monto acordado:</b> $<?=number_format($c['monto_acordado'],2,',','.')?><br>
        <b>Pagado:</b> $<?=number_format($pagado,2,',','.')?><br>
        <b>Saldo:</b> $<?=number_format($saldo,2,',','.')?><br>
    </div>
    <h4>Pagos realizados</h4>
    <table class="table table-bordered align-middle bg-white">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Monto</th>
                <th>Archivo</th>
                <th>Acción</th>
            </tr>
        </thead>
        <tbody>
        <?php while($p = $res->fetch_assoc()): ?>
            <tr>
                <td><?=htmlspecialchars($p['fecha'])?></td>
                <td>$<?=number_format($p['monto'],2,',','.')?></td>
                <td>
                    <?php if($p['archivo']): ?>
                        <a href="uploads/<?=htmlspecialchars($p['archivo'])?>" target="_blank">Ver Archivo</a>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="?id=<?=$id?>&borrar_pago=<?=$p['id']?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Borrar este pago?');">Borrar</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    <form method="post" enctype="multipart/form-data" class="bg-light p-3 rounded">
        <h5>Agregar Pago</h5>
        <div class="row g-2">
            <div class="col-md-3">
                <input type="date" name="fecha" class="form-control" required>
            </div>
            <div class="col-md-3">
                <input type="text" name="monto" class="form-control" placeholder="Monto" required>
            </div>
            <div class="col-md-3">
                <input type="file" name="archivo" class="form-control">
            </div>
            <div class="col-md-3">
                <button class="btn btn-success" type="submit">Agregar Pago</button>
                <a href="contratistas.php" class="btn btn-secondary">Volver</a>
            </div>
        </div>
    </form>
</div>
</body>
</html>
