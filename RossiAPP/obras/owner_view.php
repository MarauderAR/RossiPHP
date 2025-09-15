<?php
require_once("../config.php");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = (isset($conn) && $conn instanceof mysqli) ? $conn : $mysqli;
$db->set_charset("utf8mb4");

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function ars($n){ return number_format((float)$n,2,',','.'); }

/* =================== Semanas disponibles =================== */
$semanasDesc = [];
$rs = $db->query("SELECT semana, MAX(id) AS last_id
                  FROM rossi_db.pagos_semanales
                  GROUP BY semana
                  ORDER BY last_id DESC");
while($r=$rs->fetch_assoc()){ $semanasDesc[]=$r['semana']; }
if(!$semanasDesc){ die("Sin datos."); }

$semana = isset($_GET['semana']) && $_GET['semana']!=='' ? $_GET['semana'] : $semanasDesc[0];

$i = array_search($semana,$semanasDesc,true);
$semAnterior  = ($i!==false && $i+1<count($semanasDesc)) ? $semanasDesc[$i+1] : null;
$semSiguiente = ($i!==false && $i-1>=0) ? $semanasDesc[$i-1] : null;

/* Etiqueta del selector */
function etiqueta_semana($sem){
  if(preg_match('/^(\d{4})-(\d{2})-(\d+)$/',$sem,$m)){
    $mes=['01'=>'Enero','02'=>'Febrero','03'=>'Marzo','04'=>'Abril','05'=>'Mayo','06'=>'Junio','07'=>'Julio','08'=>'Agosto','09'=>'Septiembre','10'=>'Octubre','11'=>'Noviembre','12'=>'Diciembre'];
    return ($mes[$m[2]]??$m[2]).' - Semana '.intval($m[3]);
  }
  if(preg_match('/^([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)\s*-\s*Semana\s*(\d+)$/u',$sem,$m)){
    $map=['january'=>'Enero','february'=>'Febrero','march'=>'Marzo','april'=>'Abril','may'=>'Mayo','june'=>'Junio',
          'july'=>'Julio','august'=>'Agosto','september'=>'Septiembre','october'=>'Octubre','november'=>'Noviembre','december'=>'Diciembre',
          'enero'=>'Enero','febrero'=>'Febrero','marzo'=>'Marzo','abril'=>'Abril','mayo'=>'Mayo','junio'=>'Junio',
          'julio'=>'Julio','agosto'=>'Agosto','septiembre'=>'Septiembre','octubre'=>'Octubre','noviembre'=>'Noviembre','diciembre'=>'Diciembre'];
    $mes=$map[mb_strtolower($m[1],'UTF-8')]??ucfirst($m[1]);
    return $mes.' - Semana '.intval($m[2]);
  }
  return $sem;
}

/* =================== Rango lun–dom + semana canónica =================== */
function rango_semana_desde_label(mysqli $db, string $label): array {
  $meses = ['enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,
            'julio'=>7,'agosto'=>8,'septiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12,
            'january'=>1,'february'=>2,'march'=>3,'april'=>4,'may'=>5,'june'=>6,
            'july'=>7,'august'=>8,'september'=>9,'october'=>10,'november'=>11,'december'=>12];

  if(!preg_match('/^([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)\s*-\s*Semana\s*(\d+)$/u',$label,$m)){
    $hoy = new DateTimeImmutable('today');
    $dow = (int)$hoy->format('N');
    $ini = $hoy->modify('-'.($dow-1).' days');
    $fin = $ini->modify('+6 days');
    return [$ini->format('Y-m-d'), $fin->format('Y-m-d'), 1];
  }
  $mesTxt = mb_strtolower($m[1],'UTF-8'); $nSem = max(1,(int)$m[2]);
  $mesesNum = ['enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,'julio'=>7,'agosto'=>8,'septiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12,
               'january'=>1,'february'=>2,'march'=>3,'april'=>4,'may'=>5,'june'=>6,'july'=>7,'august'=>8,'september'=>9,'october'=>10,'november'=>11,'december'=>12];
  $mesNum = $mesesNum[$mesTxt] ?? 1;

  $year = (int)date('Y');
  $st = $db->prepare("SELECT YEAR(MAX(fecha)) y FROM rossi_db.pagos_contratistas WHERE MONTH(fecha)=?");
  $st->bind_param('i',$mesNum); $st->execute();
  $yr = $st->get_result()->fetch_column();
  if($yr){ $year = (int)$yr; }

  $primerDiaMes = new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$mesNum));
  $dow = (int)$primerDiaMes->format('N');
  $primerLunes = ($dow===1)?$primerDiaMes:$primerDiaMes->modify('+'.(8-$dow).' days');

  $ini = $primerLunes->modify('+'.($nSem-1).' weeks');
  $fin = $ini->modify('+6 days');
  return [$ini->format('Y-m-d'), $fin->format('Y-m-d'), $nSem];
}

[$ini_semana, $fin_semana, $num_sem] = rango_semana_desde_label($db, $semana);
$sem_canon = date('Y-m', strtotime($ini_semana)) . '-' . $num_sem;

/* === Elegir UN solo valor de semana para obreros (evita duplicados) === */
$cnt = function(mysqli $db, string $s){
  $st=$db->prepare("SELECT COUNT(*) c FROM rossi_db.pagos_semanales WHERE semana=?");
  $st->bind_param('s',$s); $st->execute();
  return (int)$st->get_result()->fetch_column();
};
$semFiltro = $cnt($db,$semana)>0 ? $semana : ($cnt($db,$sem_canon)>0 ? $sem_canon : $semana);

/* =================== Fórmula sin recargo de horas =================== */
$FORMULA = "(COALESCE(ps.sueldo_por_dia,0)*COALESCE(ps.dias_asistidos,0)) +
            (COALESCE(ps.pago_por_horas,0) * (COALESCE(ps.sueldo_por_dia,0)/8)) +
            COALESCE(ps.premios,0) - COALESCE(ps.descuento,0)";

/* =================== OBREROS: totales y por obra =================== */
$sqlTot = "SELECT SUM(COALESCE(ps.pago_final, $FORMULA)) AS total_semana,
                  COUNT(*) AS items
           FROM rossi_db.pagos_semanales ps
           WHERE ps.semana = ?";
$st=$db->prepare($sqlTot);
$st->bind_param('s',$semFiltro);
$st->execute();
$rowTot=$st->get_result()->fetch_assoc()?:['total_semana'=>0,'items'=>0];
$total_semana_obreros=(float)$rowTot['total_semana']; $items_semana=(int)$rowTot['items'];

$sqlObra="SELECT o.id,o.nombre,
                 SUM(COALESCE(ps.pago_final, $FORMULA)) AS total_obra,
                 COUNT(*) AS cant
          FROM rossi_db.pagos_semanales ps
          JOIN rossi_db.obras o ON o.id=ps.obra_id
          WHERE ps.semana = ?
          GROUP BY o.id,o.nombre
          ORDER BY o.nombre";
$st=$db->prepare($sqlObra);
$st->bind_param('s',$semFiltro);
$st->execute();
$porObra=$st->get_result()->fetch_all(MYSQLI_ASSOC);

/* =================== CONTRATISTAS (rango lun–dom) =================== */
$st=$db->prepare("SELECT c.nombre, COUNT(pc.id) pagos, SUM(pc.monto) total
                  FROM rossi_db.pagos_contratistas pc
                  JOIN rossi_db.contratistas c ON c.id=pc.contratista_id
                  WHERE pc.fecha BETWEEN ? AND ?
                  GROUP BY c.id,c.nombre
                  ORDER BY c.nombre");
$st->bind_param('ss',$ini_semana,$fin_semana);
$st->execute();
$contr_sem = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$total_contratistas_sem = 0.0;
foreach($contr_sem as $x){ $total_contratistas_sem += (float)$x['total']; }

/* =================== PAGOS VARIOS (rango lun–dom) =================== */
$st=$db->prepare("SELECT beneficiario, descripcion, DATE_FORMAT(fecha,'%d/%m/%Y') f, monto
                  FROM rossi_db.pagos_varios
                  WHERE fecha BETWEEN ? AND ?
                  ORDER BY fecha, id");
$st->bind_param('ss',$ini_semana,$fin_semana);
$st->execute();
$varios = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$total_varios = 0.0; foreach($varios as $v){ $total_varios += (float)$v['monto']; }

/* =================== Totales header =================== */
$total_general = $total_semana_obreros + $total_contratistas_sem + $total_varios;
$rango_label = date('d/m/Y',strtotime($ini_semana))." a ".date('d/m/Y',strtotime($fin_semana));
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pagos semanales (solo lectura)</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:#f6f7fb}
  .summary-card{border:0;border-radius:18px;box-shadow:0 6px 16px rgba(20,20,20,.06)}
  .metric{font-size:1.25rem;font-weight:700}
  .muted{color:#6c757d}
  .nav-tabs .nav-link{border:0;border-bottom:3px solid transparent}
  .nav-tabs .nav-link.active{border-bottom-color:#0d6efd;font-weight:600}
  .accordion-button{border-radius:12px!important}
  @media (max-width: 768px){ .table td,.table th{white-space:nowrap} }
</style>
</head>
<body>
<div class="container py-4">

  <div class="mb-3 d-flex justify-content-between align-items-end flex-wrap gap-2">
    <div>
      <h4 class="mb-2">Pagos semanales</h4>
      <div class="text-muted small">Rango: <b><?=$rango_label?></b></div>
    </div>
    <form class="d-flex gap-2 flex-wrap" method="get">
      <select name="semana" class="form-select" onchange="this.form.submit()">
        <?php foreach($semanasDesc as $s): ?>
          <option value="<?=$s?>" <?=$s===$semana?'selected':''?>><?=h(etiqueta_semana($s))?></option>
        <?php endforeach; ?>
      </select>
      <div class="d-flex gap-2 mt-2">
        <a class="btn btn-outline-secondary btn-sm <?=$semAnterior?'':'disabled'?>" href="<?=$semAnterior?('?semana='.h($semAnterior)):'#'?>">⟵ Anterior</a>
        <a class="btn btn-outline-secondary btn-sm <?=$semSiguiente?'':'disabled'?>" href="<?=$semSiguiente?('?semana='.h($semSiguiente)):'#'?>">Siguiente ⟶</a>
        <a class="btn btn-outline-dark btn-sm" href="owner_index.php">← Menú</a>
      </div>
    </form>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
      <div class="card summary-card"><div class="card-body">
        <div class="muted mb-1">Total a pagar (semana)</div>
        <div class="metric">$<?=ars($total_general)?></div>
        <div class="small muted">Obreros: $<?=ars($total_semana_obreros)?> · Contratistas: $<?=ars($total_contratistas_sem)?> · Varios: $<?=ars($total_varios)?></div>
      </div></div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card summary-card"><div class="card-body">
        <div class="muted mb-1">Obras con pagos</div>
        <div class="metric"><?=count($porObra)?></div>
        <div class="small muted">Cantidad</div>
      </div></div>
    </div>
    <div class="col-6 col-md-4">
      <div class="card summary-card"><div class="card-body">
        <div class="muted mb-1">Registros (obreros)</div>
        <div class="metric"><?=$items_semana?></div>
        <div class="small muted">Filas en la semana</div>
      </div></div>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body">
      <ul class="nav nav-tabs d-print-none" id="tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-obra" type="button">Por obra</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-contr" type="button">Contratistas (semana)</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-varios" type="button">Pagos varios</button></li>
      </ul>

      <div class="tab-content pt-3">
        <!-- Por obra -->
        <div class="tab-pane fade show active" id="tab-obra">
          <?php if(empty($porObra)): ?>
            <div class="text-center text-muted py-4">Sin resultados.</div>
          <?php else: ?>
            <div class="accordion" id="accObra">
              <?php $i=0; foreach($porObra as $o){ $i++; $id="o$i"; ?>
              <div class="accordion-item mb-2 border-0">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#c<?=$id?>">
                    <div class="me-auto">
                      <b><?=h($o['nombre'])?></b>
                      <span class="badge rounded-pill text-bg-secondary ms-2"><?=$o['cant']?> pagos</span>
                    </div>
                    <div class="ms-3 fw-semibold">$<?=ars($o['total_obra'])?></div>
                  </button>
                </h2>
                <div id="c<?=$id?>" class="accordion-collapse collapse" data-bs-parent="#accObra">
                  <div class="accordion-body">
                    <?php
                      $sqlDet = "SELECT ob.nombre AS obrero,
                                       COALESCE(ps.pago_final, $FORMULA) AS total
                                 FROM rossi_db.pagos_semanales ps
                                 JOIN rossi_db.obreros ob ON ob.id=ps.obrero_id
                                 WHERE ps.semana = ? AND ps.obra_id=?
                                 ORDER BY ob.nombre";
                      $st=$db->prepare($sqlDet);
                      $oid=(int)$o['id']; $st->bind_param('si',$semFiltro,$oid); $st->execute();
                      $det=$st->get_result()->fetch_all(MYSQLI_ASSOC);
                    ?>
                    <div class="table-responsive">
                      <table class="table table-sm align-middle">
                        <thead class="table-light"><tr><th>Obrero</th><th class="text-end">Monto</th></tr></thead>
                        <tbody>
                          <?php foreach($det as $d): ?>
                            <tr><td><?=h($d['obrero'])?></td><td class="text-end">$<?=ars($d['total'])?></td></tr>
                          <?php endforeach; ?>
                        </tbody>
                      </table>
                    </div>
                  </div>
                </div>
              </div>
              <?php } ?>
            </div>
          <?php endif; ?>
        </div>

        <!-- Contratistas -->
        <div class="tab-pane fade" id="tab-contr">
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr>
                <th>Contratista</th><th class="text-center">Pagos</th><th class="text-end">Total semana</th>
              </tr></thead>
              <tbody>
                <?php if(empty($contr_sem)): ?>
                  <tr><td colspan="3" class="text-center text-muted">Sin pagos de contratistas en esta semana.</td></tr>
                <?php else: foreach($contr_sem as $c): ?>
                  <tr>
                    <td><?=h($c['nombre'])?></td>
                    <td class="text-center"><?=$c['pagos']?></td>
                    <td class="text-end">$<?=ars($c['total'])?></td>
                  </tr>
                <?php endforeach; endif; ?>
                <tr class="table-light">
                  <th>Total contratistas</th><th></th><th class="text-end">$<?=ars($total_contratistas_sem)?></th>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pagos varios -->
        <div class="tab-pane fade" id="tab-varios">
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light"><tr>
                <th>Beneficiario</th><th>Descripción</th><th>Fecha</th><th class="text-end">Monto</th>
              </tr></thead>
              <tbody>
                <?php if(empty($varios)): ?>
                  <tr><td colspan="4" class="text-center text-muted">Sin pagos varios en esta semana.</td></tr>
                <?php else: foreach($varios as $v): ?>
                  <tr>
                    <td><?=h($v['beneficiario'])?></td>
                    <td><?=h($v['descripcion'])?></td>
                    <td><?=h($v['f'])?></td>
                    <td class="text-end">$<?=ars($v['monto'])?></td>
                  </tr>
                <?php endforeach; endif; ?>
                <tr class="table-light">
                  <th colspan="3">Total pagos varios</th>
                  <th class="text-end">$<?=ars($total_varios)?></th>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>
  </div>

</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
