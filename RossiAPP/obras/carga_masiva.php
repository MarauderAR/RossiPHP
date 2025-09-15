<?php
require_once("../config.php");

// Obtener obras
$obras = $conn->query("SELECT * FROM obras ORDER BY nombre");

// Función para calcular semana formato "YYYY-MM-N" a partir de fecha de inicio
function get_semana_label($fecha_inicio) {
    $dt = DateTime::createFromFormat('Y-m-d', $fecha_inicio);
    if (!$dt) return '';
    $anio = $dt->format('Y');
    $mes = $dt->format('m');
    $dia = intval($dt->format('d'));
    $semana = ceil($dia / 7);
    return "$anio-$mes-$semana";
}

// FUNCION PARA NORMALIZAR NOMBRES
function normalizar($txt) {
    $txt = preg_replace('/\s+/', ' ', $txt);      // Un solo espacio
    $txt = trim($txt);
    $txt = mb_strtolower($txt, 'UTF-8');
    $txt = str_replace(
        ['á','é','í','ó','ú','ü','ñ','.','-',','],
        ['a','e','i','o','u','u','n','','',''],
        $txt
    );
    return $txt;
}

$errores = [];
$guardados = 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['obra_id']) && !empty($_POST['fecha_inicio']) && !empty($_POST['datos'])) {
    $obra_id = intval($_POST['obra_id']);
    $fecha_inicio = trim($_POST['fecha_inicio']);
    $semana = get_semana_label($fecha_inicio);
    $datos = trim($_POST['datos']);

    // Traer obreros de la obra (por nombre, clave normalizada)
    $obreros_res = $conn->query("
        SELECT o.id, o.nombre, o.sueldo_por_dia
        FROM obreros o
        JOIN obra_obrero oo ON o.id = oo.obrero_id
        WHERE oo.obra_id = $obra_id
    ");
    $obreros = [];
    while ($o = $obreros_res->fetch_assoc()) {
        $clave = normalizar($o['nombre']);
        $obreros[$clave] = [
            'id' => $o['id'],
            'sueldo_por_dia' => floatval($o['sueldo_por_dia'])
        ];
    }

    $lineas = preg_split('/\r\n|\n|\r/', $datos);
    $a_guardar = [];

    foreach ($lineas as $fila) {
        if (trim($fila) === '') continue;
        $cols = preg_split("/\t|;|,/", $fila);

        $nombre = trim($cols[0]);
        $nombre_key = normalizar($nombre);

        // Columnas: Nombre, Días, Horas Extra, Premio, Descuento
        $dias        = intval($cols[1] ?? 0);
        $horas_extra = floatval(str_replace(",", ".", $cols[2] ?? 0));
        $premios     = floatval(str_replace(",", ".", $cols[3] ?? 0));
        $descuento   = floatval(str_replace(",", ".", $cols[4] ?? 0));

        if (!isset($obreros[$nombre_key])) {
            $errores[] = "Obrero no encontrado: ".htmlspecialchars($nombre);
            continue;
        }
        $sueldo_por_dia = $obreros[$nombre_key]['sueldo_por_dia'];
        $valor_hora = $sueldo_por_dia / 9; // 9 horas diarias

        // El pago final lo calcula el sistema
        $pago_final = ($sueldo_por_dia * $dias) + ($horas_extra * $valor_hora) + $premios - $descuento;

        $a_guardar[] = [
            'obra_id'     => $obra_id,
            'obrero_id'   => $obreros[$nombre_key]['id'],
            'semana'      => $semana,
            'sueldo'      => $sueldo_por_dia,
            'dias'        => $dias,
            'horas_extra' => $horas_extra,
            'premios'     => $premios,
            'descuento'   => $descuento,
            'pago_final'  => $pago_final
        ];
    }

    // Si no hay errores, guardar todo
    if (empty($errores)) {
        foreach ($a_guardar as $fila) {
            $stmt = $conn->prepare("INSERT INTO pagos_semanales (obra_id, obrero_id, semana, sueldo_por_dia, dias_asistidos, pago_por_horas, premios, descuento, pago_final)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE sueldo_por_dia=VALUES(sueldo_por_dia), dias_asistidos=VALUES(dias_asistidos), pago_por_horas=VALUES(pago_por_horas), premios=VALUES(premios), descuento=VALUES(descuento), pago_final=VALUES(pago_final)");
            $stmt->bind_param("iisdidddd", $fila['obra_id'], $fila['obrero_id'], $fila['semana'], $fila['sueldo'], $fila['dias'], $fila['horas_extra'], $fila['premios'], $fila['descuento'], $fila['pago_final']);
            $stmt->execute();
            $guardados++;
        }
    }
}
?>
<!DOCTYPE html>
<html>
<head>
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Carga Masiva de Pagos</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body { background: #f7f9fb; }
.card-custom { box-shadow: 0 0 24px rgba(100,100,130,0.09); border-radius: 18px; padding: 30px 22px 16px 22px; max-width: 950px; margin: 40px auto 0 auto; background: #fff; }
label { font-weight: 500; margin-bottom: 7px; }
textarea { width: 100%; min-height: 210px; font-size: 15px; border-radius: 10px; border: 1px solid #b9c0c8; padding: 12px; resize: vertical; }
.btn-main { background: #2266ee; color: #fff; font-weight: 500; border-radius: 10px; padding: 10px 28px; font-size: 17px; border: none; margin-top: 10px; box-shadow: 0 3px 15px rgba(35,88,210,0.07);}
.btn-main:hover { background: #1754c8; }
.volver-link { margin-left: 18px; color: #2266ee; font-weight: 500; }
.alert-error { margin-top: 18px; border-radius: 12px; background: #ffeaea; color: #900; border: 1.5px solid #f88; padding: 15px 25px; }
@media (max-width: 700px) { .card-custom { padding: 12px 2vw 8px 2vw; } textarea { font-size: 13.7px; } }
</style>
</head>
<body>
<div class="card-custom">
    <h2 class="mb-4" style="font-weight: 700; color:#223;">Carga Masiva de Pagos Semanales</h2>
    <form method="post" action="">
        <div class="mb-3">
            <label>Obra:</label>
            <select name="obra_id" class="form-select" required>
                <option value="">-- Elegir obra --</option>
                <?php foreach($obras as $o): ?>
                    <option value="<?=$o['id']?>" <?=isset($_POST['obra_id']) && $_POST['obra_id']==$o['id']?'selected':''?>>
                        <?=htmlspecialchars($o['nombre'])?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="mb-3">
            <label>Fecha de inicio (lunes):</label>
            <input type="date" name="fecha_inicio" class="form-control" value="<?=htmlspecialchars($_POST['fecha_inicio'] ?? '')?>" required>
        </div>
        <div class="mb-3">
            <label>Pegá acá los datos (copiados desde Excel, columnas: Nombre, Días, Horas Extra, Premio, Descuento):</label>
            <textarea name="datos" placeholder="Ejemplo:
Emanuel	5	0	0	0
Arnaldo	4	0	0	0
..."><?=htmlspecialchars($_POST['datos'] ?? '')?></textarea>
        </div>
        <button type="submit" class="btn-main">Cargar Datos</button>
        <a href="index.php" class="volver-link">Volver</a>
    </form>
    <?php if ($guardados > 0): ?>
        <div class="alert alert-success mt-4">¡Pagos cargados correctamente! (<?=$guardados?> registros)</div>
    <?php endif; ?>
    <?php if (!empty($errores)): ?>
        <div class="alert-error">
            <b>Errores encontrados:</b>
            <ul class="mb-1">
                <?php foreach($errores as $e): ?><li><?=$e?></li><?php endforeach; ?>
            </ul>
            <div>Corregí los errores y volvé a cargar.</div>
        </div>
    <?php endif; ?>
    <hr>
    <div style="font-size:92%;color:#447;margin-bottom:0;">
        <b>TIP:</b> Copiá desde el Excel las columnas <b>Nombre, Días, Horas Extra, Premio, Descuento</b> (separados por tabulaciones, como vienen por defecto al copiar desde Excel).<br>
        Si te tira "Fila incompleta", revisá que cada línea tenga las 5 columnas.<br>
        El sistema ahora ignora el sueldo pegado y lo calcula siempre según lo que tiene cargado el obrero en la base.
    </div>
</div>
</body>
</html>
