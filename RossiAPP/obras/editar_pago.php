<?php
require_once("../config.php");

// Traemos los datos del pago seleccionado
$obra_id = intval($_GET['obra_id'] ?? 0);
$semana = $_GET['semana'] ?? '';
$obrero_nombre = $_GET['obrero'] ?? '';

$pago = $conn->query("
    SELECT ps.*, o.nombre 
    FROM pagos_semanales ps
    JOIN obreros o ON o.id = ps.obrero_id
    WHERE ps.obra_id = $obra_id AND ps.semana = '{$conn->real_escape_string($semana)}' AND o.nombre = '{$conn->real_escape_string($obrero_nombre)}'
    LIMIT 1
")->fetch_assoc();

if (!$pago) die("Registro no encontrado.");

// --- CORRECCIÓN: recalcula el total siempre que se edita ---
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $sueldo_por_dia = floatval(str_replace(',', '.', $_POST['sueldo_por_dia']));
    $dias_asistidos = floatval(str_replace(',', '.', $_POST['dias_asistidos']));
    $pago_por_horas = floatval(str_replace(',', '.', $_POST['pago_por_horas']));
    $premios = floatval(str_replace(',', '.', $_POST['premios']));
    $descuento = floatval(str_replace(',', '.', $_POST['descuento']));
    $observaciones = $conn->real_escape_string($_POST['observaciones']);

    // ==== Cálculo igual que en carga masiva ====
    $HORAS_DIARIAS = 9;
    $MULTIPLICADOR_HORAS_EXTRA = 1.5;
    $valor_hora = $sueldo_por_dia / $HORAS_DIARIAS;
    $total_horas = $pago_por_horas * $valor_hora * $MULTIPLICADOR_HORAS_EXTRA;
    $pago_final = ($sueldo_por_dia * $dias_asistidos) + $total_horas + $premios - $descuento;

    $sql = "UPDATE pagos_semanales SET 
        sueldo_por_dia = $sueldo_por_dia,
        dias_asistidos = $dias_asistidos,
        pago_por_horas = $pago_por_horas,
        premios = $premios,
        descuento = $descuento,
        observaciones = '$observaciones',
        pago_final = $pago_final
        WHERE id = {$pago['id']}
        ";
    $conn->query($sql);
    header("Location: obra_resumen.php?id=$obra_id&semana=" . urlencode($semana));
    exit;
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <title>Editar pago - <?=$pago['nombre']?></title>
    <link rel="stylesheet" href="style.css">
    <style>
        form {max-width:400px; margin:30px auto; background:#f8fafd; padding:20px; border-radius:7px;}
        label {display:block; margin-bottom:5px;}
        input[type=text], input[type=number] {width:100%; margin-bottom:13px; padding:6px;}
        button {background:#244bbd; color:white; border:none; padding:8px 18px; border-radius:3px;}
    </style>
</head>
<body>
    <h2>Editar pago de <?=htmlspecialchars($pago['nombre'])?></h2>
    <form method="post">
        <label>Sueldo x Día:</label>
        <input type="text" name="sueldo_por_dia" value="<?=htmlspecialchars($pago['sueldo_por_dia'])?>" required>
        <label>Días trabajados:</label>
        <input type="number" step="0.01" name="dias_asistidos" value="<?=htmlspecialchars($pago['dias_asistidos'])?>" required>
        <label>Pago por Horas:</label>
        <input type="text" name="pago_por_horas" value="<?=htmlspecialchars($pago['pago_por_horas'])?>">
        <label>Premios:</label>
        <input type="text" name="premios" value="<?=htmlspecialchars($pago['premios'])?>">
        <label>Descuento:</label>
        <input type="text" name="descuento" value="<?=htmlspecialchars($pago['descuento'])?>">
        <label>Observaciones:</label>
        <input type="text" name="observaciones" value="<?=htmlspecialchars($pago['observaciones'])?>">
        <button type="submit">Guardar</button>
    </form>
    <div style="text-align:center;">
        <a href="obra_resumen.php?id=<?=$obra_id?>&semana=<?=urlencode($semana)?>">← Volver</a>
    </div>
</body>
</html>
