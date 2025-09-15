<?php
// --- CONFIG DB ---
$db_host = 'localhost';
$db_user = 'empresa';
$db_pass = 'v3t3r4n0';
$db_name = 'asistencia';

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn = new mysqli($db_host, $db_user, $db_pass, $db_name);
$conn->set_charset('utf8mb4');

// --- UTILIDADES ---
function iso($d){ return date('Y-m-d', strtotime($d)); }
function semana_actual_rango($baseIso){
    $ts = strtotime($baseIso ?: date('Y-m-d'));
    $dow = (int)date('N',$ts); 
    $from = date('Y-m-d', strtotime("-".($dow-1)." days", $ts));
    $to   = date('Y-m-d', strtotime("+4 days", strtotime($from)));
    return [$from,$to];
}
function dias($fromIso,$toIso){
    $out=[]; $t=strtotime($fromIso); $t2=strtotime($toIso);
    while($t <= $t2){ $out[] = date('Y-m-d',$t); $t=strtotime('+1 day',$t); }
    return $out;
}
$dias_esp = ["Lun","Mar","Mié","Jue","Vie","Sáb","Dom"];
function dia_corto($iso){
    global $dias_esp;
    $n = (int)date('N', strtotime($iso)); 
    return $dias_esp[$n-1].' '.date('d/m', strtotime($iso));
}

// --- INPUTS ---
$obra_id = isset($_GET['obra_id']) ? (int)$_GET['obra_id'] : 0;
$hoyIso  = date('Y-m-d');
$base    = $_GET['fecha_base'] ?? $hoyIso;
[$fromDefault,$toDefault] = semana_actual_rango($base);
$from = iso($_GET['from'] ?? $fromDefault);
$to   = iso($_GET['to']   ?? $toDefault);
if (strtotime($from) > strtotime($to)) { $tmp=$from; $from=$to; $to=$tmp; }

// --- DATOS OBRAS ---
$obras=[]; $q=$conn->query("SELECT id,nombre FROM obras ORDER BY nombre");
while($r=$q->fetch_assoc()) $obras[]=$r;
if ($obra_id===0 && !empty($obras)) $obra_id=(int)$obras[0]['id'];

// --- OBREROS DE ESA OBRA ---
$obreros=[]; 
$stmt=$conn->prepare("SELECT id,nombre FROM obreros WHERE obra_id=? ORDER BY nombre");
$stmt->bind_param("i",$obra_id);
$stmt->execute();
$res=$stmt->get_result();
while($r=$res->fetch_assoc()) $obreros[]=$r;
$stmt->close();

// --- ASISTENCIAS EN RANGO ---
$asis=[];
if (!empty($obreros)) {
    $ids = array_column($obreros,'id');
    $in  = implode(',', array_map('intval',$ids));
    $sql = "SELECT obrero_id, fecha, presente, observacion
            FROM asistencia
            WHERE fecha >= ? AND fecha <= ? AND obrero_id IN ($in)";
    $stmt=$conn->prepare($sql);
    $stmt->bind_param("ss",$from,$to);
    $stmt->execute();
    $res=$stmt->get_result();
    while($r=$res->fetch_assoc()){
        $f = $r['fecha'];
        $oid = (int)$r['obrero_id'];
        $asis[$f][$oid] = [
            'presente'=>(int)$r['presente'],
            'observacion'=>$r['observacion']??''
        ];
    }
    $stmt->close();
}

$fechas = dias($from,$to);
$is_hoy = fn($f)=>$f===date('Y-m-d');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Asistencias - Vista dueño</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{ background:#f6f8fa; }
.table-sm td, .table-sm th{ padding:.4rem .5rem; }
.sticky-th { position: sticky; top: 0; z-index: 1; }
.today { font-weight:700; color:#0d6efd; }
.obs { font-size:.82em; color:#6c757d; }
.totals { background:#fff; border:1px solid #dee2e6; }
</style>
</head>
<body>
<div class="container py-3">

  <h3 class="mb-3">Asistencias</h3>
  <a class="btn btn-outline-dark btn-sm ms-auto" href="owner_index.php">← Menú</a>

  <form class="row g-2 align-items-end mb-3" method="get" id="formFiltro">
    <div class="col-md-3">
      <label class="form-label">Obra</label>
      <select name="obra_id" class="form-select" onchange="document.getElementById('formFiltro').submit()">
        <?php foreach($obras as $o): ?>
          <!-- Opción recomendada: casteo a int -->
<option value="<?=$o['id']?>" <?=($obra_id == (int)$o['id']) ? 'selected' : ''?>><?=htmlspecialchars($o['nombre'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-3">
      <label class="form-label">Desde</label>
      <input type="date" name="from" class="form-control" value="<?=htmlspecialchars($from)?>" onchange="document.getElementById('formFiltro').submit()">
    </div>
    <div class="col-md-3">
      <label class="form-label">Hasta</label>
      <input type="date" name="to" class="form-control" value="<?=htmlspecialchars($to)?>" onchange="document.getElementById('formFiltro').submit()">
    </div>
  </form>

  <?php if(empty($obreros)): ?>
    <div class="alert alert-warning">No hay obreros cargados para esta obra.</div>
  <?php else: ?>

  <!-- Resumen -->
  <div class="totals p-2 mb-3">
    <strong>Resumen:</strong>
    <?php foreach($fechas as $f):
      $presentes=0;
      foreach($obreros as $o){ $oid=(int)$o['id'];
        if(isset($asis[$f][$oid]) && $asis[$f][$oid]['presente']==1) $presentes++;
      } ?>
      <span class="<?=$is_hoy($f)?'today':''?>" style="margin-right:12px;">
        <?=dia_corto($f)?>: <?=$presentes?> / <?=count($obreros)?>
      </span>
    <?php endforeach; ?>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered table-sm align-middle bg-white">
      <thead class="table-light sticky-th">
        <tr>
          <th style="min-width:220px;">Obrero</th>
          <?php foreach($fechas as $f): ?>
            <th class="<?=$is_hoy($f)?'today':''?>"><?=dia_corto($f)?></th>
          <?php endforeach; ?>
        </tr>
      </thead>
      <tbody>
        <?php foreach($obreros as $o): $oid=(int)$o['id']; ?>
          <tr>
            <td><?=htmlspecialchars($o['nombre'])?></td>
            <?php foreach($fechas as $f): ?>
              <td class="text-center <?=$is_hoy($f)?'today':''?>">
                <?php if(isset($asis[$f][$oid])): 
                    $a=$asis[$f][$oid]; $ok=($a['presente']==1); ?>
                  <?=$ok?'✔':'—'?>
                  <?php if(trim($a['observacion'])): ?>
                    <div class="obs"><?=htmlspecialchars($a['observacion'])?></div>
                  <?php endif; ?>
                <?php else: ?>
                  <span class="text-muted">-</span>
                <?php endif; ?>
              </td>
            <?php endforeach; ?>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <?php endif; ?>

</div>
</body>
</html>
