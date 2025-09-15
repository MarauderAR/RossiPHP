<?php
require_once("../config.php");
$result = $conn->query("SELECT * FROM proveedores ORDER BY nombre");
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<title>Proveedores</title>
<style>
    .boton-masivo {
        background: #47a647;
        color: white;
        padding: 7px 16px;
        border-radius: 5px;
        text-decoration: none;
        font-weight: bold;
        margin-left: 14px;
    }
    .boton-masivo:hover { background: #337a33; }
    .acciones a { color: #2767d6; text-decoration: underline;}
    .boton-menu {
        background: #f6c900;
        color: #222;
        font-weight: bold;
        padding: 8px 18px;
        border-radius: 5px;
        text-decoration: none;
        margin-bottom: 18px;
        display: inline-block;
    }
    .boton-menu:hover { background: #f4b500; color: #111; }
</style>
</head>
<body style="background: #f6f8fa;">
<div class="container py-4">
    <!-- Botón Volver al Menú Principal -->
    <div class="mb-3">
        <a href="../index.php" class="boton-menu">← Menú Principal</a>
	
    </div>
    <h2 class="text-center mb-4">Lista de Proveedores</h2>
    <div class="mb-3">
        <a href="proveedor_nuevo.php" class="btn btn-primary">+ Agregar Proveedor</a>
        <a href="importar_desde_excel.php" class="boton-masivo">+ Importar Facturas Masivas</a>
        <a href="dashboard.php" class="boton-masivo">Dashboard General</a>
		<a href="gastos_obra.php" class="btn btn-info">Gastos de Obra</a>

    </div>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-primary">
                <tr>
                    <th>ID</th>
                    <th>Nombre</th>
                    <th>Teléfono</th>
                    <th>Dirección</th>
                    <th style="text-align:right;">Saldo Total</th>
                    <th>Facturas</th>
                </tr>
            </thead>
            <tbody>
            <?php 
            while($row = $result->fetch_assoc()):
                // Calcular saldo total del proveedor
                $proveedor_id = $row['id'];
                $saldo_total = 0;
                $facturas = $conn->query("SELECT debe, haber FROM facturas WHERE proveedor_id = $proveedor_id");
                while ($fac = $facturas->fetch_assoc()) {
                    $saldo_total += floatval($fac['debe']) - floatval($fac['haber']);
                }
            ?>
            <tr>
                <td><?= $row['id'] ?></td>
                <td><?= htmlspecialchars($row['nombre']) ?></td>
                <td><?= htmlspecialchars($row['telefono']) ?></td>
                <td><?= htmlspecialchars($row['direccion']) ?></td>
                <td style="text-align:right; font-weight:bold;"><?= number_format($saldo_total, 2, ',', '.') ?></td>
                <td class="acciones">
                    <a href="ver_proveedor.php?id=<?= $row['id'] ?>">Ver / Cargar Facturas</a>
                </td>
            </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
