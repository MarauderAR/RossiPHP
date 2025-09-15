<?php
require_once("../config.php");

/**
 * CARGAR PAGOS SEMANALES — versión con fecha global y botones de auto-relleno
 * - Jornada base: 8 h/día
 * - Horas extra: cantidad de horas (no dinero). Multiplicador = 1
 * - Recalculo en backend
 * - Soporta fecha global y copia masiva a todas las filas
 */

const HORAS_DIARIAS = 8;
const MULT_HE       = 1;

// Traer obras
$obras = [];
if ($rs = $conn->query("SELECT id, nombre FROM obras ORDER BY nombre")) {
    while ($row = $rs->fetch_assoc()) { $obras[] = $row; }
}

// Obra seleccionada
$obra_id = isset($_GET['obra_id']) ? (int)$_GET['obra_id'] : (isset($_POST['obra_id']) ? (int)$_POST['obra_id'] : 0);

// Semanas existentes para la obra
$semanas_existentes = [];
if ($obra_id) {
    if ($sem_res = $conn->query("SELECT DISTINCT semana FROM pagos_semanales WHERE obra_id = $obra_id ORDER BY semana DESC")) {
        while ($rw = $sem_res->fetch_assoc()) { $semanas_existentes[] = $rw['semana']; }
    }
}

// Formato "YYYY-MM-N" a partir de fecha de inicio (YYYY-MM-DD)
function get_semana_label($fecha_inicio) {
    $dt = DateTime::createFromFormat('Y-m-d', $fecha_inicio);
    if (!$dt) return '';
    $anio = $dt->format('Y');
    $mes  = $dt->format('m');
    $dia  = (int)$dt->format('d');
    $semana = (int)ceil($dia / 7);
    return "$anio-$mes-$semana";
}

$msg = '';

// POST: guardar pagos
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $obra_id = (int)($_POST['obra_id'] ?? 0);
    $fecha_inicio      = $_POST['fecha_inicio'] ?? '';
    $fecha_pago_global = $_POST['fecha_pago']   ?? ''; // input global (YYYY-MM-DD)

    $semana = trim($_POST['semana_existente'] ?? '');
    if ($semana === '' && $fecha_inicio !== '') { $semana = get_semana_label($fecha_inicio); }

    if ($semana === '') {
        $msg = "<div style='color:red;'>Debés seleccionar una semana existente o poner la fecha de inicio.</div>";
    } else {
        $pagos_realizados = 0;

        // Prepared una vez. Usamos NULLIF para permitir NULL en fecha_pago.
        $stmt = $conn->prepare("
            INSERT INTO pagos_semanales
            (obra_id, obrero_id, semana, sueldo_por_dia, dias_asistidos, pago_por_horas, premios, descuento, pago_final, observaciones, fecha_pago)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULLIF(?, ''))
            ON DUPLICATE KEY UPDATE
                sueldo_por_dia=VALUES(sueldo_por_dia),
                dias_asistidos=VALUES(dias_asistidos),
                pago_por_horas=VALUES(pago_por_horas),
                premios=VALUES(premios),
                descuento=VALUES(descuento),
                pago_final=VALUES(pago_final),
                observaciones=VALUES(observaciones),
                fecha_pago=VALUES(fecha_pago)
        ");

        if (!$stmt) {
            $msg = "<div style='color:red;'>Error de preparación SQL: ".htmlspecialchars($conn->error)."</div>";
        } else {
            foreach ($_POST['obrero_id'] as $k => $obrero_id) {
                $obrero_id   = (int)$obrero_id;
                $dias        = (float)($_POST['dias_asistidos'][$k] ?? 0);
                $horas_extra = (float)($_POST['pago_por_horas'][$k] ?? 0); // horas
                $premios     = (float)($_POST['premios'][$k] ?? 0);
                $descuento   = (float)($_POST['descuento'][$k] ?? 0);
                $obs         = trim($_POST['observaciones'][$k] ?? '');
                // por-fila en YYYY-MM-DD o vacío; si está vacío cae a global; si la global también está vacía queda NULL por NULLIF
                $fpago_fila  = trim($_POST['fecha_pago'][$k] ?? '');
                $fpago       = ($fpago_fila !== '') ? $fpago_fila : trim($fecha_pago_global);

                // sueldo por día desde DB
                $sueldo = 0.0;
                if ($rs = $conn->query("SELECT sueldo_por_dia FROM obreros WHERE id = $obrero_id")) {
                    $rw = $rs->fetch_assoc(); $sueldo = (float)($rw['sueldo_por_dia'] ?? 0);
                }

                // si no hay nada en la fila, saltar
                $hay_datos = ($dias || $horas_extra || $premios || $descuento || $obs !== '' || $fpago !== '');
                if (!$hay_datos) { continue; }

                // cálculo backend
                $valorHora      = $sueldo > 0 ? ($sueldo / HORAS_DIARIAS) : 0;
                $pagoHorasExtra = $horas_extra * $valorHora * MULT_HE;
                $pago_final     = round(($sueldo * $dias) + $pagoHorasExtra + $premios - $descuento, 2);

                // bind y execute
                $stmt->bind_param(
                    "iisddddddss",
                    $obra_id,           // i
                    $obrero_id,         // i
                    $semana,            // s
                    $sueldo,            // d
                    $dias,              // d
                    $horas_extra,       // d
                    $premios,           // d
                    $descuento,         // d
                    $pago_final,        // d
                    $obs,               // s
                    $fpago              // s -> NULLIF(?, '') en SQL
                );
                $stmt->execute();
                $pagos_realizados++;
            }
            $msg = "<div style='color:green'>Pagos cargados correctamente. ($pagos_realizados pagos registrados)</div>";
        }
    }
}

// Obreros de la obra con sueldo_por_dia
$obreros = [];
if ($obra_id) {
    $q = "
        SELECT o.id, o.nombre, o.sueldo_por_dia
        FROM obreros o
        JOIN obra_obrero oo ON o.id = oo.obrero_id
        WHERE oo.obra_id = $obra_id
        ORDER BY o.nombre
    ";
    if ($rs = $conn->query($q)) {
        while ($row = $rs->fetch_assoc()) { $obreros[] = $row; }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Cargar Pagos Semanales</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
    .container { max-width: 98vw; }
    .table { width: 100%; }
    .table-responsive { overflow-x: auto; }
    input[type="number"], input[type="text"], input[type="date"] { width: 120px; max-width: 100%; }
    th, td { white-space: nowrap; }
    .boton-volver { margin-top: 20px; display: inline-block; }
</style>
</head>
<body>
<div class="container py-3">
    <h2>Cargar Pagos Semanales</h2>
    <?= $msg ?>

    <form method="get" action="">
        <label>Obra:</label>
        <select name="obra_id" onchange="this.form.submit()">
            <option value="">-- Seleccionar --</option>
            <?php foreach ($obras as $obra): ?>
                <option value="<?= (int)$obra['id'] ?>" <?= ($obra_id == $obra['id'] ? 'selected' : '') ?>>
                    <?= htmlspecialchars($obra['nombre']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </form>

    <br>

    <?php if ($obra_id && !empty($obreros)): ?>
        <form method="post" action="">
            <input type="hidden" name="obra_id" value="<?= (int)$obra_id ?>">

            <label>
                Semana:
                <select name="semana_existente" style="min-width:200px;">
                    <option value="">-- Seleccionar semana existente --</option>
                    <?php foreach ($semanas_existentes as $sem): ?>
                        <option value="<?= htmlspecialchars($sem) ?>"><?= htmlspecialchars($sem) ?></option>
                    <?php endforeach; ?>
                </select>
                <span class="ms-3">o Nueva:</span>
                <input type="date" name="fecha_inicio" value="">
                <span class="text-muted" style="font-size:12px;">(Elegí el lunes. Agrupa con formato YYYY-MM-N)</span>
            </label>

            <!-- Controles de fecha global -->
            <div class="my-2 d-flex align-items-center gap-2">
              <label class="me-2">Fecha pago global:</label>
              <input type="date" name="fecha_pago" id="fecha_pago_global" class="form-control" style="width:160px" value="">
              <button type="button" id="btnHoyTodos"      class="btn btn-sm btn-secondary">Hoy a todos</button>
              <button type="button" id="btnHoyVacios"     class="btn btn-sm btn-outline-secondary">Hoy solo vacíos</button>
              <button type="button" id="btnCopiarGlobal"  class="btn btn-sm btn-outline-primary">Copiar global</button>
            </div>

            <div class="table-responsive">
                <table class="table table-bordered table-striped align-middle">
                    <thead>
                        <tr>
                            <th>Obrero</th>
                            <th>Sueldo x Día</th>
                            <th>Días</th>
                            <th>Horas Extra (h)</th>
                            <th>Premios</th>
                            <th>Descuentos</th>
                            <th>Pago Final</th>
                            <th>Obs.</th>
                            <th>Fecha Pago</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($obreros as $obrero): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($obrero['nombre']) ?>
                                    <input type="hidden" name="obrero_id[]" value="<?= (int)$obrero['id'] ?>">
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="sueldo_por_dia[]" value="<?= htmlspecialchars($obrero['sueldo_por_dia'] ?? 0) ?>" readonly style="background:#eee; color:#444;">
                                </td>
                                <td><input type="number" step="0.25" name="dias_asistidos[]" ></td>
                                <td><input type="number" step="0.25" name="pago_por_horas[]" value="0"></td>
                                <td><input type="number" step="0.01" name="premios[]" value="0"></td>
                                <td><input type="number" step="0.01" name="descuento[]" value="0"></td>
                                <td><input type="number" step="0.01" name="pago_final[]" ></td>
                                <td><input type="text" name="observaciones[]"></td>
                                <td><input type="date" name="fecha_pago[]"></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-2 text-muted" style="font-size:12px;">
                Nota: La jornada laboral es de 8hs
            </div>

            <br>
            <input type="submit" value="Guardar Pagos" class="btn btn-success">
        </form>
    <?php elseif ($obra_id): ?>
        <div style="color:red">No hay obreros cargados para esta obra.</div>
    <?php endif; ?>

    <br>
    <a href="index.php" class="boton-volver">Volver</a>
</div>

<script>
// Cálculo en pantalla alineado al backend
const MULTIPLICADOR_HORAS_EXTRA = 1;
const HORAS_DIARIAS = 8;

document.addEventListener('DOMContentLoaded', function() {
    document.querySelectorAll('table tbody tr').forEach(function(row) {
        const sueldo     = row.querySelector('input[name^="sueldo_por_dia"]');
        const dias       = row.querySelector('input[name^="dias_asistidos"]');
        const horasExtra = row.querySelector('input[name^="pago_por_horas"]');
        const premios    = row.querySelector('input[name^="premios"]');
        const descuento  = row.querySelector('input[name^="descuento"]');
        const pagoFinal  = row.querySelector('input[name^="pago_final"]');

        if (!sueldo || !dias || !horasExtra || !premios || !descuento || !pagoFinal) return;

        function num(v) {
            if (v === null || v === undefined) return 0;
            const s = v.toString().replace(',', '.');
            const n = parseFloat(s);
            return isNaN(n) ? 0 : n;
        }

        function calcular() {
            const s   = num(sueldo.value);
            const d   = num(dias.value);
            const he  = num(horasExtra.value);
            const p   = num(premios.value);
            const des = num(descuento.value);

            const valorHora      = s / HORAS_DIARIAS;
            const pagoHorasExtra = he * valorHora * MULTIPLICADOR_HORAS_EXTRA;
            const total          = (s * d) + pagoHorasExtra + p - des;

            pagoFinal.value = total > 0 ? total.toFixed(2) : '0';
        }

        ['input','change'].forEach(evt => {
            dias.addEventListener(evt, calcular);
            horasExtra.addEventListener(evt, calcular);
            premios.addEventListener(evt, calcular);
            descuento.addEventListener(evt, calcular);
            sueldo.addEventListener(evt, calcular);
        });
    });

    // Botones de fecha masiva
    const $global = document.getElementById('fecha_pago_global');
    const filasFecha = () => document.querySelectorAll('input[name="fecha_pago[]"]');

    function hoyYMD(){
        const d=new Date();
        const y=d.getFullYear(), m=String(d.getMonth()+1).padStart(2,'0'), dd=String(d.getDate()).padStart(2,'0');
        return `${y}-${m}-${dd}`;
    }
    function setAll(val, soloVacios=false){
        filasFecha().forEach(el => { if (!soloVacios || !el.value) el.value = val; });
    }

    document.getElementById('btnHoyTodos')     ?.addEventListener('click', () => setAll(hoyYMD(), false));
    document.getElementById('btnHoyVacios')    ?.addEventListener('click', () => setAll(hoyYMD(), true));
    document.getElementById('btnCopiarGlobal') ?.addEventListener('click', () => { if ($global && $global.value) setAll($global.value,false); });
});
</script>
</body>
</html>
