<?php
require_once("../config.php");
$obra_id = intval($_GET['id'] ?? 0);

$obra = $conn->query("SELECT * FROM obras WHERE id=$obra_id")->fetch_assoc();
if (!$obra) die("Obra no encontrada.");

// Traer todas las semanas de esa obra (distintas)
$semanas = [];
$res = $conn->query("SELECT DISTINCT semana FROM pagos_semanales WHERE obra_id = $obra_id ORDER BY semana");
while ($row = $res->fetch_assoc()) {
    $semanas[] = $row['semana'];
}

// Función para transformar '2025-07-2' => 'Julio - Semana 2'
function semana_legible($semana) {
    if (preg_match('/^(\d{4})-(\d{2})-(\d+)$/', $semana, $m)) {
        setlocale(LC_TIME, "es_AR.UTF-8");
        $mes = strftime('%B', strtotime($m[1].'-'.$m[2].'-01'));
        $mes = ucfirst($mes);
        return "$mes - Semana $m[3]";
    }
    return $semana;
}

$semana = $_GET['semana'] ?? ($semanas[0] ?? '');

// Calcular MES de la semana seleccionada
$mes_actual = '';
if (preg_match('/^(\d{4})-(\d{2})-(\d+)$/', $semana, $mx)) {
    $mes_actual = $mx[1] . '-' . $mx[2]; // Ej: 2025-07
}

// === RESUMEN DE LA SEMANA ===
$pagos_array = [];
$total_pagado = 0;
$total_sueldos = 0;
$total_premios = 0;
$total_horas = 0;
$total_descuentos = 0;
if ($semana) {
    $sqlPagos = "SELECT ps.id, ob.nombre, ps.semana, ps.sueldo_por_dia, ps.dias_asistidos, ps.pago_por_horas, ps.premios, ps.descuento, ps.observaciones, ps.fecha_pago, ps.pago_final
        FROM pagos_semanales ps
        JOIN obreros ob ON ob.id = ps.obrero_id
        WHERE ps.obra_id = $obra_id AND ps.semana = ?
        ORDER BY ob.nombre";
    $stmt = $conn->prepare($sqlPagos);
    $stmt->bind_param("s", $semana);
    $stmt->execute();
    $pagos = $stmt->get_result();

    if($pagos && $pagos->num_rows){
        while ($row = $pagos->fetch_assoc()) {
            $valor_dia = floatval(str_replace(',', '.', $row['sueldo_por_dia']));
            // Cambiado: NO redondear días
            $dias = floatval(str_replace(',', '.', $row['dias_asistidos']));
            $pago_horas = isset($row['pago_por_horas']) ? floatval(str_replace(',', '.', $row['pago_por_horas'])) : 0;
            $premios = floatval(str_replace(',', '.', $row['premios']));
            $descuento = floatval(str_replace(',', '.', $row['descuento']));

            if (isset($row['pago_final']) && floatval($row['pago_final']) > 0) {
                $total_a_pagar = floatval($row['pago_final']);
            } else if ($pago_horas > 0) {
                $total_a_pagar = $pago_horas + $premios - $descuento;
                $total_horas += $pago_horas;
            } else {
                $total_a_pagar = ($valor_dia * $dias) + $premios - $descuento;
                $total_sueldos += ($valor_dia * $dias);
            }

            $total_pagado += $total_a_pagar;
            $total_premios += $premios;
            $total_descuentos += $descuento;

            $row['total_a_pagar'] = $total_a_pagar;
            $row['pago_por_horas'] = $pago_horas;
            $pagos_array[] = $row;
        }
    }
}

// === RESUMEN DEL MES ===
$resMes = $conn->query("
    SELECT ps.sueldo_por_dia, ps.dias_asistidos, ps.pago_por_horas, ps.premios, ps.descuento, ps.pago_final
    FROM pagos_semanales ps
    WHERE ps.obra_id = $obra_id AND ps.semana LIKE '$mes_actual-%'
");
$total_mes_pagado = 0; $total_mes_sueldos = 0; $total_mes_premios = 0; $total_mes_horas = 0; $total_mes_descuentos = 0;
while($row = $resMes->fetch_assoc()) {
    $valor_dia = floatval(str_replace(',', '.', $row['sueldo_por_dia']));
    // Cambiado: NO redondear días
    $dias = floatval(str_replace(',', '.', $row['dias_asistidos']));
    $pago_horas = isset($row['pago_por_horas']) ? floatval(str_replace(',', '.', $row['pago_por_horas'])) : 0;
    $premios = floatval(str_replace(',', '.', $row['premios']));
    $descuento = floatval(str_replace(',', '.', $row['descuento']));

    if (isset($row['pago_final']) && floatval($row['pago_final']) > 0) {
        $total = floatval($row['pago_final']);
    } else if ($pago_horas > 0) {
        $total = $pago_horas + $premios - $descuento;
        $total_mes_horas += $pago_horas;
    } else {
        $total = ($valor_dia * $dias) + $premios - $descuento;
        $total_mes_sueldos += ($valor_dia * $dias);
    }
    $total_mes_pagado += $total;
    $total_mes_premios += $premios;
    $total_mes_descuentos += $descuento;
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Resumen de <?=htmlspecialchars($obra['nombre'])?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <style>
        .summary-box {margin: 0 auto 20px auto; background:#f8fafd; border:1px solid #e0e0e0; padding:14px 22px; border-radius:7px; max-width:540px;}
        .summary-mes-box {margin: 0 auto 35px auto; background:#f7f3e8; border:1px solid #e5d88b; padding:10px 20px; border-radius:7px; max-width:540px;}
        .btn-editar {background: #ffa500; color:white; padding:3px 10px; border-radius:3px; text-decoration:none;}
        .btn-editar:hover {background: #e28a00;}
    </style>
</head>
<body style="background: #f6f8fa;">
<div class="container py-4">
    <h2 class="text-center mb-4">Resumen de <?=htmlspecialchars($obra['nombre'])?></h2>
    <div class="row mb-3">
        <div class="col-md-4"><strong>Capataz:</strong> <?=htmlspecialchars($obra['capataz'])?></div>
        <div class="col-md-4"><strong>Ubicación:</strong> <?=htmlspecialchars($obra['ubicacion'])?></div>
        <div class="col-md-4"><strong>Inicio:</strong> <?=htmlspecialchars($obra['fecha_inicio'])?></div>
    </div>
    <form method="get" action="" class="mb-3">
        <input type="hidden" name="id" value="<?=$obra_id?>">
        <div class="input-group" style="max-width:340px;margin:auto;">
            <label class="input-group-text">Semana</label>
            <select name="semana" class="form-select" onchange="this.form.submit()">
                <?php foreach($semanas as $s): ?>
                    <option value="<?=$s?>" <?=($s == $semana ? "selected" : "")?>><?=semana_legible($s)?></option>
                <?php endforeach; ?>
            </select>
        </div>
    </form>
    <!-- RESUMEN DE LA SEMANA -->
    <div class="summary-box mb-3">
        <b>Total Pagado (Semana):</b> $<?=number_format($total_pagado, 2, ',', '.')?><br>
        <b>Total Sueldos (Semana):</b> $<?=number_format($total_sueldos, 2, ',', '.')?><br>
        <b>Total Pago por Horas (Semana):</b> $<?=number_format($total_horas, 2, ',', '.')?><br>
        <b>Total Premios (Semana):</b> $<?=number_format($total_premios, 2, ',', '.')?><br>
        <b>Total Descuentos (Semana):</b> $<?=number_format($total_descuentos, 2, ',', '.')?><br>
    </div>
    <!-- RESUMEN DEL MES -->
    <div class="summary-mes-box mb-4">
        <b>Resumen del Mes Actual:</b><br>
        <b>Total Pagado (Mes):</b> $<?=number_format($total_mes_pagado, 2, ',', '.')?><br>
        <b>Total Sueldos (Mes):</b> $<?=number_format($total_mes_sueldos, 2, ',', '.')?><br>
        <b>Total Pago por Horas (Mes):</b> $<?=number_format($total_mes_horas, 2, ',', '.')?><br>
        <b>Total Premios (Mes):</b> $<?=number_format($total_mes_premios, 2, ',', '.')?><br>
        <b>Total Descuentos (Mes):</b> $<?=number_format($total_mes_descuentos, 2, ',', '.')?><br>
    </div>
    <a href="dashboard_obras.php" class="btn btn-outline-secondary mb-3">← Volver al Dashboard</a>
    <a href="index.php" class="btn btn-outline-secondary mb-3">← Volver al Inicio</a>
    <div class="table-responsive">
        <table class="table table-bordered table-striped align-middle shadow pagos-table">
            <thead class="table-primary">
                <tr>
                    <th>Obrero</th>
                    <th>Semana</th>
                    <th>Sueldo x Día</th>
                    <th>Días</th>
                    <th>Pago x Horas</th>
                    <th>Premios</th>
                    <th>Descuentos</th>
                    <th style="font-weight:bold;">TOTAL A PAGAR</th>
                    <th>Fecha Pago</th>
                    <th>Obs.</th>
                    <th>Acción</th>
                </tr>
            </thead>
            <tbody>
            <?php if(!empty($pagos_array)): ?>
                <?php foreach ($pagos_array as $p): ?>
                <tr>
                    <td><?=htmlspecialchars($p['nombre'])?></td>
                    <td><?=semana_legible($p['semana'])?></td>
                    <td><?=number_format($p['sueldo_por_dia'], 2, ',', '.')?></td>
                    <td><?=rtrim(rtrim(number_format($p['dias_asistidos'], 2, ',', '.'), '0'), ',.')?></td>
                    <td><?=number_format($p['pago_por_horas'], 2, ',', '.')?></td>
                    <td><?=number_format($p['premios'], 2, ',', '.')?></td>
                    <td><?=number_format($p['descuento'], 2, ',', '.')?></td>
                    <td style="font-weight:bold; color:#244bbd;"><?=number_format($p['total_a_pagar'], 2, ',', '.')?></td>
                    <td><?=htmlspecialchars($p['fecha_pago'])?></td>
                    <td><?=htmlspecialchars($p['observaciones'])?></td>
                    <td>
                        <a href="editar_pago.php?obra_id=<?=$obra_id?>&semana=<?=urlencode($p['semana'])?>&obrero=<?=urlencode($p['nombre'])?>" class="btn-editar btn btn-sm">Editar</a>
                        <a href="borrar_pago.php?id=<?=$p['id']?>&obra_id=<?=$obra_id?>&semana=<?=urlencode($p['semana'])?>" 
                            class="btn btn-danger btn-sm"
                            onclick="return confirm('¿Seguro que querés borrar este pago?');"
                        >Borrar</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr><td colspan="11" class="text-center">No hay datos para la semana seleccionada.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
