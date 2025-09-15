<?php
require_once("../config.php");
$res = $conn->query("SELECT c.*, COALESCE(SUM(p.monto),0) as pagado, c.monto_acordado - COALESCE(SUM(p.monto),0) as saldo
                    FROM contratistas c
                    LEFT JOIN pagos_contratistas p ON c.id = p.contratista_id
                    GROUP BY c.id
                    ORDER BY c.nombre");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Contratistas</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body style="background:#f6f8fa;">
<div class="container my-4">
    <h2>Contratistas</h2>
    <a href="contratista_nuevo.php" class="btn btn-success mb-3">+ Agregar Contratista</a>
	<a href="index.php" class="btn btn-success mb-3">Volver al Inicio</a>
	
    <div class="table-responsive">
    <table class="table table-bordered table-striped align-middle" style="background:#fff;">
        <thead class="table-warning">
        <tr>
            <th>Nombre</th>
            <th>Rubro</th>
            <th>Monto Acordado</th>
            <th>Pagado</th>
            <th>Saldo</th>
            <th>Observaciones</th>
            <th>Acciones</th>
        </tr>
        </thead>
        <tbody>
        <?php while($c = $res->fetch_assoc()): ?>
            <tr>
                <td><?=htmlspecialchars($c['nombre'])?></td>
                <td><?=htmlspecialchars($c['rubro'])?></td>
                <td>$<?=number_format($c['monto_acordado'],2,',','.')?></td>
                <td>$<?=number_format($c['pagado'],2,',','.')?></td>
                <td>$<?=number_format($c['saldo'],2,',','.')?></td>
                <td><?=htmlspecialchars($c['observaciones'])?></td>
                <td>
                    <a href="contratista_ver.php?id=<?=$c['id']?>" class="btn btn-primary btn-sm">Ver</a>
                    <a href="contratista_editar.php?id=<?=$c['id']?>" class="btn btn-info btn-sm">Editar</a>
                    <a href="contratista_borrar.php?id=<?=$c['id']?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Borrar contratista?');">Borrar</a>
                </td>
            </tr>
        <?php endwhile; ?>
        </tbody>
    </table>
    </div>
</div>
</body>
</html>
