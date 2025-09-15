<?php
require_once("../config.php");
$obra_id = intval($_GET['id'] ?? 0);

$obra = $conn->query("SELECT * FROM obras WHERE id=$obra_id")->fetch_assoc();
if (!$obra) die("Obra no encontrada.");

// Obreros asignados a la obra
$res = $conn->query("SELECT ob.* FROM obra_obrero oo JOIN obreros ob ON oo.obrero_id=ob.id WHERE oo.obra_id=$obra_id");

// Traer todos los contratistas
$contratistas = $conn->query("SELECT * FROM contratistas ORDER BY nombre");
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Detalle de Obra</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container my-4">
    <h2 class="text-center mb-3"><?=htmlspecialchars($obra['nombre'])?></h2>
    <div class="mb-3"><b>Capataz:</b> <?=htmlspecialchars($obra['capataz'])?></div>
    <div class="mb-3"><b>Ubicación:</b> <?=htmlspecialchars($obra['ubicacion'])?></div>
    <div class="mb-3"><b>Fecha de inicio:</b> <?=htmlspecialchars($obra['fecha_inicio'])?></div>
    <a href="dashboard_obras.php" class="btn btn-outline-secondary mb-3">← Volver</a>
    
    <!-- OBREROS -->
    <div class="table-responsive mb-5">
        <h4>Obreros Asignados</h4>
        <table class="table table-bordered table-hover align-middle">
            <thead class="table-light">
            <tr>
                <th>Obrero</th>
                <th>CUIL</th>
                <th>Categoría</th>
                <th>Acciones</th>
            </tr>
            </thead>
            <tbody>
            <?php while($o = $res->fetch_assoc()): ?>
                <tr>
                    <td><?=htmlspecialchars($o['nombre'])?></td>
                    <td><?= htmlspecialchars($o['cuil'] ?? '') ?></td>
                    <td><?= htmlspecialchars($o['categoria'] ?? '') ?></td>
                    <td>
                        <a href="ver_legajo.php?id=<?=$o['id']?>" class="btn btn-sm btn-info">Legajo</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- CONTRATISTAS (todos) -->
    <div class="table-responsive">
        <h4>Contratistas</h4>
        <a href="contratista_nuevo.php" class="btn btn-success btn-sm mb-2">+ Agregar Contratista</a>
        <table class="table table-bordered table-hover align-middle">
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
            <?php while($c = $contratistas->fetch_assoc()): ?>
                <tr>
                    <td><?=htmlspecialchars($c['nombre'])?></td>
                    <td><?=htmlspecialchars($c['rubro'])?></td>
                    <td>$<?=number_format($c['monto_acordado'],2,',','.')?></td>
                    <td>$<?=number_format($c['pagado'],2,',','.')?></td>
                    <td>$<?=number_format($c['monto_acordado'] - $c['pagado'],2,',','.')?></td>
                    <td><?=htmlspecialchars($c['observaciones'])?></td>
                    <td>
                        <a href="contratista_editar.php?id=<?=$c['id']?>" class="btn btn-primary btn-sm">Editar</a>
                        <a href="contratista_borrar.php?id=<?=$c['id']?>" class="btn btn-danger btn-sm" onclick="return confirm('¿Seguro de borrar?')">Borrar</a>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
