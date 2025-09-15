<?php
// CONFIGURACIÓN MYSQL
$db_host = 'localhost';
$db_user = 'empresa';
$db_pass = 'v3t3r4n0';
$db_name = 'asistencia';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

// === TABLAS BASE ===
$conn->query("CREATE TABLE IF NOT EXISTS obras (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL UNIQUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS obreros (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(100) NOT NULL,
  obra_id INT DEFAULT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  CONSTRAINT fk_obra FOREIGN KEY (obra_id) REFERENCES obras(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$conn->query("CREATE TABLE IF NOT EXISTS asistencia (
  id INT AUTO_INCREMENT PRIMARY KEY,
  obrero_id INT NOT NULL,
  fecha DATE NOT NULL,
  presente TINYINT(1) NOT NULL DEFAULT 0,
  observacion VARCHAR(255),
  UNIQUE KEY uniq_obrero_fecha (obrero_id, fecha),
  CONSTRAINT fk_obrero FOREIGN KEY (obrero_id) REFERENCES obreros(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// === MIGRACIONES SUAVES ===

// 1) Columna 'activo' si no existe
$col = $conn->prepare("SELECT COUNT(*) c FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA=? AND TABLE_NAME='obreros' AND COLUMN_NAME='activo'");
$col->bind_param("s", $db_name);
$col->execute(); $has = $col->get_result()->fetch_assoc()['c'] ?? 0; $col->close();
if (!$has) {
  $conn->query("ALTER TABLE obreros ADD COLUMN activo TINYINT(1) NOT NULL DEFAULT 1 AFTER obra_id");
}

// 2) Índice único (obra_id, nombre) si no existe
$idx = $conn->prepare("SELECT COUNT(*) c FROM information_schema.STATISTICS
  WHERE TABLE_SCHEMA=? AND TABLE_NAME='obreros' AND INDEX_NAME='uniq_obra_nombre'");
$idx->bind_param("s", $db_name);
$idx->execute(); $hasIdx = $idx->get_result()->fetch_assoc()['c'] ?? 0; $idx->close();
if (!$hasIdx) {
  $conn->query("CREATE UNIQUE INDEX uniq_obra_nombre ON obreros (obra_id, nombre)");
}

// === SEMILLAS ===
$conn->query("INSERT IGNORE INTO obras (id,nombre) VALUES 
  (1,'Alvear'),(2,'Castelli'),(3,'Belgrano')");

$obreros_base = [
  1 => ["Emanuel","Arnaldo","Lulo","Lucho","Mariano","Silvano","Alejandro","Chirola","Diego","Aldo","Alexis Arn.","Ivan","Juan","Claudio","Fernando"],
  2 => ["Celso","Ariel","Ivan","Antoño","Jesus","Emilio","Carlos Bogado","Vicente","Richard","Matias","Andre","Fernando","Cristian","Carlos","Mario","Catalino","Dario"],
  3 => ["Alberto Nuñez","Angel Sanchez","Ariel Zacarias","Brian Javier Montiel","Enzo Franco","Justino Romero Alvarez","Martinez Acosta","Rodrigo Ramon","nino","Pablo Diaz","Roberto jose Baliñes","Victor David Portillo"]
];

foreach ($obreros_base as $obra=>$lista) {
  $stmt = $conn->prepare("INSERT IGNORE INTO obreros (nombre, obra_id, activo) VALUES (?,?,1)");
  foreach ($lista as $n) { $stmt->bind_param("si",$n,$obra); $stmt->execute(); }
  $stmt->close();
}

// === DATOS DE PANTALLA ===
$obras = [];
$res = $conn->query("SELECT id,nombre FROM obras ORDER BY nombre");
while ($row = $res->fetch_assoc()) $obras[] = $row;
$obra_id = (int)($_GET['obra_id'] ?? $_POST['obra_id'] ?? ($obras[0]['id'] ?? 1));

// SOLO ACTIVOS
$obreros = [];
$res = $conn->query("SELECT id,nombre FROM obreros WHERE obra_id=$obra_id AND activo=1 ORDER BY nombre");
while ($o = $res->fetch_assoc()) $obreros[] = $o;

// GUARDAR ASISTENCIA
$msg = null;
if (isset($_POST['guardar'])) {
  $fecha = $_POST['fecha'] ?? date('Y-m-d');
  foreach ($_POST['presente'] as $id => $v) {
    $presente = (int)$v;
    $obs = trim($_POST['observacion'][$id] ?? '');
    $stmt = $conn->prepare("INSERT INTO asistencia (obrero_id,fecha,presente,observacion)
      VALUES (?,?,?,?)
      ON DUPLICATE KEY UPDATE presente=VALUES(presente), observacion=VALUES(observacion)");
    $stmt->bind_param("isis", $id, $fecha, $presente, $obs);
    $stmt->execute(); $stmt->close();
  }
  $msg = "✅ Asistencia guardada para $fecha";
}

// SEMANA
$sel_fecha = $_GET['fecha'] ?? $_POST['fecha'] ?? date('Y-m-d');
$ts = strtotime($sel_fecha);
$dia_sem = (int)date('N',$ts);
$lunes = date('Y-m-d', strtotime("-".($dia_sem-1)." days", $ts));
$fechas_sem = [];
for ($i=0;$i<5;$i++) $fechas_sem[] = date('Y-m-d', strtotime("+$i days", strtotime($lunes)));

// ASISTENCIAS DE LA SEMANA
$asis = [];
$fin = end($fechas_sem);
$q = $conn->query("SELECT a.* FROM asistencia a
  JOIN obreros o ON a.obrero_id=o.id
  WHERE o.obra_id=$obra_id AND a.fecha>='$lunes' AND a.fecha<='$fin'");
while ($row = $q->fetch_assoc()) { $asis[$row['fecha']][$row['obrero_id']] = $row; }
function es_hoy($f){ return $f===date('Y-m-d'); }
$dias_esp = ["Lun","Mar","Mié","Jue","Vie","Sáb","Dom"];
function dia_es($f){ global $dias_esp; $d=(int)date('N',strtotime($f)); return $dias_esp[$d-1]; }
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Asistencia de Obra</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f6f8fa;}
.asist-table td,.asist-table th{font-size:0.95em}
</style>
</head>
<body>
<div class="container py-3">
  <div class="d-flex justify-content-between align-items-center">
    <h2 class="mb-3">Asistencia - Obra</h2>
    <a class="btn btn-outline-secondary btn-sm" href="obreros_admin.php?obra_id=<?=$obra_id?>">⚙️ Administrar obreros</a>
  </div>

  <form method="get" class="mb-2">
    <select name="obra_id" onchange="this.form.submit()" class="form-select d-inline-block w-auto">
      <?php foreach($obras as $o): ?>
        <option value="<?=$o['id']?>" <?=($obra_id==(int)$o['id'])?'selected':''?>><?=htmlspecialchars($o['nombre'])?></option>
      <?php endforeach; ?>
    </select>
    <input type="hidden" name="fecha" value="<?=htmlspecialchars($sel_fecha)?>">
  </form>

  <?php if ($msg): ?><div class="alert alert-success"><?=$msg?></div><?php endif; ?>

  <form method="post" class="mb-4">
    <input type="hidden" name="obra_id" value="<?=$obra_id?>">
    <button class="btn btn-primary mb-3" name="guardar" value="1">Guardar asistencia</button>

    <div class="row g-2 mb-2 align-items-center">
      <div class="col-auto"><strong>Día:</strong></div>
      <div class="col-auto">
        <input type="date" name="fecha" class="form-control" required value="<?=htmlspecialchars($sel_fecha)?>">
      </div>
    </div>

    <div class="table-responsive">
      <table class="table table-bordered asist-table bg-white">
        <thead class="table-primary">
          <tr><th>Obrero</th><th class="text-center">Presente</th><th>Observaciones</th></tr>
        </thead>
        <tbody>
        <?php foreach ($obreros as $o): ?>
          <tr>
            <td><?=htmlspecialchars($o['nombre'])?></td>
            <td class="text-center">
              <input type="hidden" name="presente[<?=$o['id']?>]" value="0">
              <input type="checkbox" name="presente[<?=$o['id']?>]" value="1"
                <?php if(isset($asis[$sel_fecha][$o['id']]) && (int)$asis[$sel_fecha][$o['id']]['presente']===1) echo 'checked';?>>
            </td>
            <td>
              <input type="text" name="observacion[<?=$o['id']?>]" class="form-control"
                value="<?=isset($asis[$sel_fecha][$o['id']]) ? htmlspecialchars($asis[$sel_fecha][$o['id']]['observacion']) : ''?>">
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <button class="btn btn-primary mt-3" name="guardar" value="1">Guardar asistencia</button>
  </form>

  <h5 class="mt-4 mb-2">Historial de la semana</h5>
  <div class="table-responsive">
    <table class="table table-bordered table-sm asist-table bg-white">
      <thead class="table-secondary">
        <tr>
          <th>Obrero</th>
          <?php foreach($fechas_sem as $f): ?>
            <th><?=dia_es($f)?> <?=date('d/m', strtotime($f))?></th>
          <?php endforeach; ?>
          <th class="text-center">Total</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($obreros as $o): ?>
        <?php $total=0; ?>
        <tr>
          <td><?=htmlspecialchars($o['nombre'])?></td>
          <?php foreach($fechas_sem as $f): ?>
            <?php
              $pres = isset($asis[$f][$o['id']]) ? (int)$asis[$f][$o['id']]['presente'] : null;
              if ($pres===1) $total++;
            ?>
            <td class="text-center <?=es_hoy($f)?'fw-bold text-primary':''?>">
              <?php if(isset($asis[$f][$o['id']])): ?>
                <?= ($pres===1) ? '✔' : '—' ?>
                <?php if (trim($asis[$f][$o['id']]['observacion']??'')!==''): ?>
                  <div class="text-muted" style="font-size:.85em;"><?=htmlspecialchars($asis[$f][$o['id']]['observacion'])?></div>
                <?php endif; ?>
              <?php else: ?><span class="text-muted">-</span><?php endif; ?>
            </td>
          <?php endforeach; ?>
          <td class="text-center fw-bold"><?=$total?></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function(){
  const fecha = document.querySelector('input[type="date"][name="fecha"]');
  if(fecha){
    fecha.addEventListener('change', function(){
      const url = new URL(location.href);
      url.searchParams.set('obra_id', '<?=$obra_id?>');
      url.searchParams.set('fecha', this.value);
      location.href = url.toString();
    });
  }
});
</script>
</body>
</html>
