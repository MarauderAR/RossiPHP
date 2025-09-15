<?php
require_once("../config.php");

/* Conexión remota a Hostinger (ya habilitaste MySQL remoto) */
$T_HOST = 'srv1577.hstgr.io';      // o 193.203.175.107
$T_USER = 'u515133326_tarea';
$T_PASS = '3Mpr3s42025!';
$T_DB   = 'u515133326_tareas';

$dbT = @new mysqli($T_HOST,$T_USER,$T_PASS,$T_DB);
if($dbT->connect_errno){ die("Error conexión tareas: ".$dbT->connect_error); }

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }

/* Detectar nombres de columnas reales en la tabla 'tareas' */
$tbl = 'tareas';
$cols = [];
$cq = $dbT->query("SHOW COLUMNS FROM `$tbl`");
while($cq && $c=$cq->fetch_assoc()){ $cols[strtolower($c['Field'])]=true; }

$C_ID   = isset($cols['id']) ? 'id' : array_key_first($cols);
$C_TIT  = isset($cols['titulo']) ? 'titulo' : (isset($cols['title'])?'title':(isset($cols['nombre'])?'nombre':null));
$C_DESC = isset($cols['descripcion']) ? 'descripcion' : (isset($cols['description'])?'description':(isset($cols['detalle'])?'detalle':null));
$C_PRI  = isset($cols['prioridad']) ? 'prioridad' : (isset($cols['priority'])?'priority':null);
$C_VEN  = isset($cols['vence']) ? 'vence' :
          (isset($cols['vencimiento'])?'vencimiento':
          (isset($cols['fecha_vencimiento'])?'fecha_vencimiento':
          (isset($cols['deadline'])?'deadline':
          (isset($cols['due_date'])?'due_date':null))));
$C_EST  = isset($cols['estado']) ? 'estado' : (isset($cols['status'])?'status':null);

if(!$C_TIT){ $C_TIT = $C_ID; } // fallback para mostrar algo

// Filtros
$est  = $_GET['est']  ?? 'pendiente';
$prio = $_GET['prio'] ?? 'todas';
$qtxt = trim($_GET['q'] ?? '');

// Armar SELECT solo con columnas existentes
$sel = "`$C_ID` AS id, `$C_TIT` AS titulo";
if($C_DESC) $sel .= ", `$C_DESC` AS descripcion";
if($C_PRI)  $sel .= ", `$C_PRI` AS prioridad";
if($C_VEN)  $sel .= ", `$C_VEN` AS vence";
if($C_EST)  $sel .= ", `$C_EST` AS estado";

$where=[]; $types=''; $params=[];
if($C_EST && $est!=='todas'){ $where[]="`$C_EST`=?";  $types.='s'; $params[]=$est; }
if($C_PRI && $prio!=='todas'){ $where[]="`$C_PRI`=?"; $types.='s'; $params[]=$prio; }
if($qtxt!==''){
  if($C_DESC) { $where[]="(`$C_TIT` LIKE ? OR `$C_DESC` LIKE ?)"; $types.='ss'; $params[]="%$qtxt%"; $params[]="%$qtxt%"; }
  else        { $where[]="`$C_TIT` LIKE ?"; $types.='s';  $params[]="%$qtxt%"; }
}

$sql="SELECT $sel FROM `$tbl`".
     ($where? " WHERE ".implode(' AND ',$where):"").
     " ORDER BY ".($C_EST?"FIELD(`$C_EST`,'pendiente','progreso','hecha','archivada'),":"").
     ($C_PRI?" `$C_PRI`='alta' DESC,":"")." `$C_ID` DESC";

$rows=[];
if($st=$dbT->prepare($sql)){
  if($types) $st->bind_param($types, ...$params);
  $st->execute(); $rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);
}else{
  die("No se pudo leer la tabla de tareas: ".$dbT->error);
}

function badge_prio($p){ if($p===null || $p==='') return '—'; $m=['alta'=>'danger','media'=>'warning','baja'=>'secondary']; $t=strtolower((string)$p); $c=$m[$t]??'secondary'; return '<span class="badge text-bg-'.$c.'">'.h($t).'</span>'; }
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Tareas (solo lectura)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>body{background:#f6f7fb}.pill{border-radius:999px;padding:.25rem .6rem;background:#eef2ff;color:#3a47a6;font-weight:600}.rowcard{border-bottom:1px solid #f0f2f5}.rowcard:hover{background:#fcfcff}</style>
</head>
<body>
<div class="container py-4">

  <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
    <h4 class="mb-0">Tareas</h4>
    <span class="pill ms-2"><?=count($rows)?> visibles</span>
    <a class="btn btn-outline-dark btn-sm ms-auto" href="owner_index.php">← Menú</a>
  </div>

  <form class="row g-2 mb-3" method="get">
    <div class="col-12 col-md-3">
      <select name="est" class="form-select">
        <?php $ests=['todas'=>'Todas','pendiente'=>'Pendientes','progreso'=>'En progreso','hecha'=>'Hechas','archivada'=>'Archivadas'];
        foreach($ests as $k=>$v): ?><option value="<?=$k?>" <?=$est===$k?'selected':''?>><?=$v?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-3">
      <select name="prio" class="form-select">
        <?php $prios=['todas'=>'Prioridad (todas)','alta'=>'Alta','media'=>'Media','baja'=>'Baja'];
        foreach($prios as $k=>$v): ?><option value="<?=$k?>" <?=$prio===$k?'selected':''?>><?=$v?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="col-12 col-md-4"><input type="text" name="q" class="form-control" value="<?=h($qtxt)?>" placeholder="Buscar por título o descripción"></div>
    <div class="col-12 col-md-2 d-grid"><button class="btn btn-primary">Filtrar</button></div>
  </form>

  <div class="bg-white rounded-4 shadow-sm">
    <div class="p-3 row fw-semibold text-muted border-bottom"><div class="col-1">#</div><div class="col-6">Título</div><div class="col-2">Prioridad</div><div class="col-2">Vence</div><div class="col-1">Estado</div></div>
    <?php if(empty($rows)): ?>
      <div class="p-4 text-center text-muted">Sin resultados.</div>
    <?php else: foreach($rows as $r): ?>
      <div class="p-3 row align-items-center rowcard">
        <div class="col-1"><?=h($r['id'])?></div>
        <div class="col-6">
          <div class="fw-semibold text-primary"><?=h($r['titulo'])?></div>
          <div class="text-muted small"><?=h($r['descripcion'] ?? '')?></div>
        </div>
        <div class="col-2"><?=badge_prio($r['prioridad'] ?? '')?></div>
        <div class="col-2">
          <?php
            if(isset($r['vence']) && $r['vence'] && $r['vence']!=='0000-00-00'){
              echo date('d/m/Y', strtotime($r['vence']));
            } else { echo '—'; }
          ?>
        </div>
        <div class="col-1"><span class="badge text-bg-light"><?=h($r['estado'] ?? '—')?></span></div>
      </div>
    <?php endforeach; endif; ?>
  </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
