<?php
require_once("../config.php");
$obras = $conn->query("SELECT * FROM obras ORDER BY nombre");

// Resumen contratistas
$res_contratistas = $conn->query("
    SELECT c.id, c.nombre, c.rubro, c.monto_acordado,
           COALESCE(SUM(pc.monto),0) as pagado,
           c.monto_acordado - COALESCE(SUM(pc.monto),0) as saldo
    FROM contratistas c
    LEFT JOIN pagos_contratistas pc ON c.id = pc.contratista_id
    GROUP BY c.id
    ORDER BY c.nombre
");

// alerta ok
$ok = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<title>Obras en Curso</title>
<link rel="stylesheet" href="style.css">
<style>
    .boton-accion {
        background: #245bd6;
        color: white;
        padding: 8px 18px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
        margin-right: 10px;
        margin-bottom: 18px;
        display: inline-block;
    }
    .boton-accion:hover { background: #183a8c; }
    .contratistas-box {
        background: #fffce8;
        border: 1px solid #e5d88b;
        border-radius: 7px;
        margin-top: 35px;
        padding: 20px 14px 6px 14px;
    }
    .contratistas-box h4 { margin-bottom: 14px; }
</style>
</head>
<body style="background: #f6f8fa;">

<div class="container py-4">
    <?php if($ok===1): ?>
      <div class="alert alert-success">Pago varios cargado correctamente.</div>
    <?php endif; ?>

    <!-- Botón Volver al Inicio -->
    <div class="mb-3">
        <a href="../index.php" class="boton-accion" style="background:#f6c900;color:#222;font-weight:bold;">← Menú Principal</a>
    </div>

    <h2 class="text-center mb-4">Lista de Obras</h2>

    <div class="mb-3 text-center">
        <a href="carga_masiva.php" class="boton-accion">+ Carga Masiva de Pagos</a>
        <a href="obra_nueva.php" class="boton-accion">+ Agregar Obra</a>
        <a href="obrero_nuevo.php" class="boton-accion">+ Agregar Obrero</a>
        <a href="cargar_pagos.php" class="boton-accion">+ Cargar Pagos Semanales</a>
        <a href="dashboard_obras.php" class="boton-accion" style="background:#4d9a38;">Dashboard</a>
        <a href="obrero_ver.php" class="boton-accion" style="background:#f4b942;">Ver Obreros</a>
        <a href="contratistas.php" class="boton-accion" style="background:#f6c900;color:#222;">Contratistas</a>
        <!-- NUEVO: botón pagos varios -->
        <a href="pagos_varios_nuevo.php" class="boton-accion" style="background:#7b61ff;">+ Cargar Pagos Varios</a>
    </div>

    <div class="table-responsive">
        <table class="table table-striped table-bordered align-middle shadow">
            <thead class="table-primary">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Capataz</th>
                    <th>Ver</th>
                    <th>Resumen</th>
                </tr>
            </thead>
            <tbody>
                <?php while($o = $obras->fetch_assoc()): ?>
                <tr>
                    <td><?= $o['id'] ?></td>
                    <td><?= htmlspecialchars($o['nombre']) ?></td>
                    <td><?= htmlspecialchars($o['capataz']) ?></td>
                    <td><a href="obra_ver.php?id=<?= $o['id'] ?>" class="btn btn-outline-secondary btn-sm">Ver Detalle</a></td>
                    <td><a href="obra_resumen.php?id=<?= $o['id'] ?>" class="btn btn-outline-primary btn-sm">Resumen</a></td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <!-- Resumen Contratistas -->
    <div class="contratistas-box mt-4">
        <h4>Resumen de Contratistas</h4>
        <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-warning">
                <tr>
                    <th>Nombre</th>
                    <th>Rubro</th>
                    <th>Monto Acordado</th>
                    <th>Pagado</th>
                    <th>Saldo</th>
                </tr>
            </thead>
            <tbody>
            <?php while($c = $res_contratistas->fetch_assoc()): ?>
                <tr>
                    <td><?=htmlspecialchars($c['nombre'])?></td>
                    <td><?=htmlspecialchars($c['rubro'])?></td>
                    <td>$<?=number_format($c['monto_acordado'],2,',','.')?></td>
                    <td>$<?=number_format($c['pagado'],2,',','.')?></td>
                    <td>$<?=number_format($c['saldo'],2,',','.')?></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
        </div>
    </div>
    <!-- Fin Resumen Contratistas -->
</div>
</body>
</html>
