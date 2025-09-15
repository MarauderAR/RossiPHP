<?php
require_once("../config.php");

// Total general de facturas, debe, haber y saldo
$sqlTotal = "SELECT 
    SUM(monto_total) AS total_facturas, 
    SUM(debe) AS total_debe, 
    SUM(haber) AS total_haber,
    SUM(COALESCE(debe,0) - COALESCE(haber,0)) AS total_saldo
    FROM facturas";
$total = $conn->query($sqlTotal)->fetch_assoc();

// Resumen por proveedor
$sqlProvs = "SELECT 
    p.id, 
    p.nombre, 
    COUNT(f.id) AS cantidad, 
    SUM(f.monto_total) AS total, 
    SUM(f.debe) AS total_debe, 
    SUM(f.haber) AS total_haber,
    SUM(COALESCE(f.debe,0) - COALESCE(f.haber,0)) AS saldo
    FROM proveedores p
    LEFT JOIN facturas f ON f.proveedor_id = p.id
    GROUP BY p.id
    ORDER BY p.nombre";
$provs = $conn->query($sqlProvs);

// Saldos de cuenta corriente por proveedor (preconsulta, indexado por id)
$res_cc = [];
$q_cc = $conn->query("SELECT proveedor_id, SUM(importe) as saldo_cc FROM proveedor_resumen_cuenta GROUP BY proveedor_id");
while($rw = $q_cc->fetch_assoc()) { $res_cc[$rw['proveedor_id']] = $rw['saldo_cc']; }
$total_saldo_cc = array_sum($res_cc);

?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<title>Dashboard - Proveedores</title>
<link rel="stylesheet" href="style.css">
<style>
    .dashboard-resumen {margin-bottom:2em; background:#eaeaea; border-radius:8px; padding:20px;}
    .big {font-size:2em; font-weight:bold;}
    .saldo-neg {color:#b20d0d; font-weight:bold;}
    .saldo-pos {color:#26842a; font-weight:bold;}
</style>
</head>
<body style="background: #f6f8fa;">
<div class="container py-4">
    <h2 class="text-center mb-4">Resumen General de Proveedores</h2>
    <div class="dashboard-resumen text-center mb-4">
        <strong>Total facturas cargadas:</strong>
        <span class="big"><?= number_format($total['total_facturas'], 2, ',', '.') ?></span> ARS<br>
        <strong>Total Debe:</strong> <?= number_format($total['total_debe'], 2, ',', '.') ?> &nbsp; 
        <strong>Total Haber:</strong> <?= number_format($total['total_haber'], 2, ',', '.') ?> &nbsp;
        <strong>Total SALDO (solo facturas):</strong>
        <span class="<?= ($total['total_saldo'] < 0 ? 'saldo-neg' : 'saldo-pos') ?>">
            <?= number_format($total['total_saldo'], 2, ',', '.') ?>
        </span>
        <br>
        <strong>Total Resumen de cuenta:</strong>
        <span class="<?= ($total_saldo_cc < 0 ? 'saldo-neg' : 'saldo-pos') ?>">
            <?= number_format($total_saldo_cc, 2, ',', '.') ?>
        </span>
        <br>
        <strong>Total Global (Facturas + Resumen de cuenta):</strong>
        <span class="<?= ($total['total_saldo'] + $total_saldo_cc < 0 ? 'saldo-neg' : 'saldo-pos') ?>">
            <?= number_format($total['total_saldo'] + $total_saldo_cc, 2, ',', '.') ?>
        </span>
    </div>
    <h4 class="mb-3">Por Proveedor</h4>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle">
            <thead class="table-primary">
                <tr>
                    <th>Proveedor</th>
                    <th>Cantidad Facturas</th>
                    <th>Total Monto</th>
                    <th>Total Debe</th>
                    <th>Total Haber</th>
                    <th>SALDO (Facturas)</th>
                    <th>Saldo Resumen de cuenta</th>
                    <th>SALDO TOTAL</th>
                    <th>Ver</th>
                </tr>
            </thead>
            <tbody>
                <?php while ($row = $provs->fetch_assoc()): 
                    $saldo_cc = $res_cc[$row['id']] ?? 0;
                    $saldo_total = $row['saldo'] + $saldo_cc;
                ?>
                <tr>
                    <td><?= htmlspecialchars($row['nombre']) ?></td>
                    <td><?= $row['cantidad'] ?></td>
                    <td><?= number_format($row['total'], 2, ',', '.') ?></td>
                    <td><?= number_format($row['total_debe'], 2, ',', '.') ?></td>
                    <td><?= number_format($row['total_haber'], 2, ',', '.') ?></td>
                    <td class="<?= ($row['saldo'] < 0 ? 'saldo-neg' : 'saldo-pos') ?>">
                        <?= number_format($row['saldo'], 2, ',', '.') ?>
                    </td>
                    <td class="<?= ($saldo_cc < 0 ? 'saldo-neg' : 'saldo-pos') ?>">
                        <?= number_format($saldo_cc, 2, ',', '.') ?>
                    </td>
                    <td class="<?= ($saldo_total < 0 ? 'saldo-neg' : 'saldo-pos') ?>">
                        <?= number_format($saldo_total, 2, ',', '.') ?>
                    </td>
                    <td>
                        <a class="btn btn-outline-primary btn-sm" href="ver_proveedor.php?id=<?=$row['id']?>">Ver Facturas</a>
                    </td>
                </tr>
                <?php endwhile; ?>
            </tbody>
            <tfoot>
                <tr class="fw-bold" style="background:#f0f0f0;">
                    <td colspan="5" class="text-end">TOTALES GENERALES:</td>
                    <td><?= number_format($total['total_saldo'], 2, ',', '.') ?></td>
                    <td><?= number_format($total_saldo_cc, 2, ',', '.') ?></td>
                    <td><?= number_format($total['total_saldo'] + $total_saldo_cc, 2, ',', '.') ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <a href="index.php" class="btn btn-link mt-3">← Volver a proveedores</a>
</div>
</body>
</html>
