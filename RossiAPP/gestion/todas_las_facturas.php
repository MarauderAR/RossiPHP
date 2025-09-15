<?php
require_once("../config.php");

// Traer obras para filtro
$obras = [];
$resOb = $conn->query("SELECT id, nombre FROM obras ORDER BY nombre");
while ($r = $resOb->fetch_assoc()) $obras[] = $r;

// Filtros
$busqueda = trim($_GET['busqueda'] ?? '');
$obra_id  = (int)($_GET['obra_id'] ?? 0);

// Armado de consulta con prepared statement
$sql = "SELECT f.*, p.nombre AS proveedor, o.nombre AS obra_nombre
        FROM facturas f
        JOIN proveedores p ON p.id = f.proveedor_id
        LEFT JOIN obras o ON o.id = f.obra_id
        WHERE 1";
$params = [];
$types  = "";

if ($busqueda !== '') {
    $sql .= " AND (f.numero LIKE ? OR p.nombre LIKE ?)";
    $like = "%{$busqueda}%";
    $params[] = $like; $types .= "s";
    $params[] = $like; $types .= "s";
}

if ($obra_id > 0) {
    $sql .= " AND f.obra_id = ?";
    $params[] = $obra_id; $types .= "i";
}

$sql .= " ORDER BY f.fecha DESC, f.id DESC";

$stmt = $conn->prepare($sql);
if ($types !== "") {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

// Acumuladores
$tot_monto = 0.0; $tot_debe = 0.0; $tot_haber = 0.0;
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Todas las Facturas</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="p-3">
    <h2 class="mb-3">Listado General de Facturas</h2>

    <form method="get" class="row g-2 mb-3">
        <div class="col-md-4">
            <label class="form-label">Buscar (Proveedor o Nº)</label>
            <input type="text" name="busqueda" value="<?=htmlspecialchars($busqueda)?>" class="form-control" placeholder="Ferretería / 0001-00012345">
        </div>
        <div class="col-md-4">
            <label class="form-label">Obra</label>
            <select name="obra_id" class="form-select">
                <option value="0">— Todas —</option>
                <?php foreach ($obras as $o): ?>
                    <option value="<?=$o['id']?>" <?=$obra_id==$o['id']?'selected':''?>><?=htmlspecialchars($o['nombre'])?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 d-flex align-items-end gap-2">
            <button class="btn btn-primary">Filtrar</button>
            <a class="btn btn-outline-secondary" href="todas_las_facturas.php">Ver todo</a>
        </div>
    </form>

    <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
            <thead class="table-light">
                <tr>
                    <th>Proveedor</th>
                    <th>Obra</th>
                    <th>N° Factura</th>
                    <th>Fecha</th>
                    <th class="text-end">Monto Total</th>
                    <th class="text-end">Debe</th>
                    <th class="text-end">Haber</th>
                    <th>Archivo</th>
                </tr>
            </thead>
            <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <?php
                    $tot_monto += (float)$row['monto_total'];
                    $tot_debe  += (float)$row['debe'];
                    $tot_haber += (float)$row['haber'];

                    // Resolver ruta del archivo: soporta 'uploads/...' o sólo nombre
                    $archivo = trim((string)$row['archivo']);
                    if ($archivo !== '') {
                        $href = (strpos($archivo, 'uploads/') === 0) ? $archivo : ('uploads/' . $archivo);
                    } else {
                        $href = null;
                    }
                ?>
                <tr>
                    <td><?=htmlspecialchars($row['proveedor'])?></td>
                    <td><?=htmlspecialchars($row['obra_nombre'] ?? '—')?></td>
                    <td><?=htmlspecialchars($row['numero'])?></td>
                    <td><?=htmlspecialchars($row['fecha'])?></td>
                    <td class="text-end"><?=number_format((float)$row['monto_total'], 2, ",", ".")?></td>
                    <td class="text-end"><?=number_format((float)$row['debe'], 2, ",", ".")?></td>
                    <td class="text-end"><?=number_format((float)$row['haber'], 2, ",", ".")?></td>
                    <td>
                        <?php if ($href): ?>
                            <a href="<?=htmlspecialchars($href)?>" target="_blank">Ver</a>
                        <?php else: ?>
                            —
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endwhile; ?>
            <?php if ($result->num_rows === 0): ?>
                <tr><td colspan="8" class="text-center text-muted">Sin resultados</td></tr>
            <?php endif; ?>
            </tbody>
            <tfoot>
                <tr class="table-secondary fw-bold">
                    <td colspan="4" class="text-end">Totales:</td>
                    <td class="text-end"><?=number_format($tot_monto, 2, ",", ".")?></td>
                    <td class="text-end"><?=number_format($tot_debe, 2, ",", ".")?></td>
                    <td class="text-end"><?=number_format($tot_haber, 2, ",", ".")?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>

    <p class="mt-3">
        <a class="btn btn-secondary" href="index.php">Volver al menú</a>
    </p>
</body>
</html>
