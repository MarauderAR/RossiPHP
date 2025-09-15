<?php
require_once("../config.php");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$conn->set_charset('utf8mb4');

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function ars($n){ return number_format((float)$n, 2, ',', '.'); }

/* ===================== Semanas (ultimas primero) ===================== */
$semanasDesc = [];
$rs = $conn->query("SELECT semana, MAX(id) AS last_id
                    FROM rossi_db.pagos_semanales
                    GROUP BY semana
                    ORDER BY last_id DESC");
while($r=$rs->fetch_assoc()){ $semanasDesc[]=$r['semana']; }
if(!$semanasDesc){ die("No hay semanas en pagos_semanales."); }
$semana = isset($_GET['semana']) && $_GET['semana']!=='' ? $_GET['semana'] : $semanasDesc[0];
$i = array_search($semana,$semanasDesc,true);
$semAnterior  = ($i!==false && $i+1<count($semanasDesc)) ? $semanasDesc[$i+1] : null;
$semSiguiente = ($i!==false && $i-1>=0) ? $semanasDesc[$i-1] : null;

/* Etiqueta legible */
function etiqueta_semana($sem){
  if(preg_match('/^([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)\s*-\s*Semana\s*(\d+)$/u',$sem,$m)){
    $mm=mb_strtolower($m[1],'UTF-8');
    $map=['january'=>'Enero','february'=>'Febrero','march'=>'Marzo','april'=>'Abril','may'=>'Mayo','june'=>'Junio',
          'july'=>'Julio','august'=>'Agosto','september'=>'Septiembre','october'=>'Octubre','november'=>'Noviembre','december'=>'Diciembre',
          'enero'=>'Enero','febrero'=>'Febrero','marzo'=>'Marzo','abril'=>'Abril','mayo'=>'Mayo','junio'=>'Junio',
          'julio'=>'Julio','agosto'=>'Agosto','septiembre'=>'Septiembre','octubre'=>'Octubre','noviembre'=>'Noviembre','diciembre'=>'Diciembre'];
    $mes=$map[$mm]??ucfirst($m[1]);
    return $mes.' - Semana '.intval($m[2]);
  }
  return $sem;
}

/* Rango de fechas (lun-dom) a partir del label de semana */
function rango_semana_desde_label(mysqli $db, string $label): array {
  $meses = ['enero'=>1,'febrero'=>2,'marzo'=>3,'abril'=>4,'mayo'=>5,'junio'=>6,
            'julio'=>7,'agosto'=>8,'septiembre'=>9,'octubre'=>10,'noviembre'=>11,'diciembre'=>12,
            'january'=>1,'february'=>2,'march'=>3,'april'=>4,'may'=>5,'june'=>6,
            'july'=>7,'august'=>8,'september'=>9,'october'=>10,'november'=>11,'december'=>12];

  if(!preg_match('/^([A-Za-zÁÉÍÓÚÜÑáéíóúüñ]+)\s*-\s*Semana\s*(\d+)$/u',$label,$m)){
    $hoy = new DateTimeImmutable('today');
    $ini = $hoy->modify('-'.((int)$hoy->format('N')-1).' days');
    $fin = $ini->modify('+6 days');
    return [$ini->format('Y-m-d'), $fin->format('Y-m-d')];
  }

  $mesTxt = mb_strtolower($m[1],'UTF-8'); $nSem = max(1,(int)$m[2]);
  $mesNum = $meses[$mesTxt] ?? (int)$m[1];
  $year = (int)date('Y');

  // intenta deducir año desde pagos_contratistas
  $st=$db->prepare("SELECT YEAR(MAX(fecha)) y FROM rossi_db.pagos_contratistas WHERE MONTH(fecha)=?");
  $st->bind_param('i',$mesNum); $st->execute();
  $yr=$st->get_result()->fetch_column(); if($yr){ $year=(int)$yr; }

  $primerDia = new DateTimeImmutable(sprintf('%04d-%02d-01',$year,$mesNum));
  $dow = (int)$primerDia->format('N');
  $primerLunes = ($dow===1)?$primerDia:$primerDia->modify('+'.(8-$dow).' days');
  $ini = $primerLunes->modify('+'.($nSem-1).' weeks');
  $fin = $ini->modify('+6 days');
  return [$ini->format('Y-m-d'), $fin->format('Y-m-d')];
}
[$ini,$fin] = rango_semana_desde_label($conn,$semana);

/* ===================== Totales (obreros / contratistas / varios) ===================== */
$st=$conn->prepare("SELECT SUM(COALESCE(pago_final,
      (COALESCE(sueldo_por_dia,0)*COALESCE(dias_asistidos,0)) +
      (COALESCE(pago_por_horas,0) * (COALESCE(sueldo_por_dia,0)/9 * 1.5)) +
      COALESCE(premios,0) - COALESCE(descuento,0)
)) t, COUNT(*) c
FROM rossi_db.pagos_semanales
WHERE semana=?");
$st->bind_param('s',$semana); $st->execute();
[$tot_obr,$items_obr] = $st->get_result()->fetch_row();
$tot_obr=(float)$tot_obr; $items_obr=(int)$items_obr;

$st=$conn->prepare("SELECT COALESCE(SUM(monto),0), COUNT(*) FROM rossi_db.pagos_contratistas WHERE fecha BETWEEN ? AND ?");
$st->bind_param('ss',$ini,$fin); $st->execute();
[$tot_contr,$items_contr] = $st->get_result()->fetch_row();
$tot_contr=(float)$tot_contr; $items_contr=(int)$items_contr;

$st=$conn->prepare("SELECT COALESCE(SUM(monto),0), COUNT(*) FROM rossi_db.pagos_varios WHERE fecha BETWEEN ? AND ?");
$st->bind_param('ss',$ini,$fin); $st->execute();
[$tot_varios,$items_varios] = $st->get_result()->fetch_row();
$tot_varios=(float)$tot_varios; $items_varios=(int)$items_varios;

$total_semana = $tot_obr + $tot_contr + $tot_varios;

/* ===================== Por obra (con detalle) ===================== */
$sqlObra="SELECT o.id,o.nombre,
  SUM(COALESCE(ps.pago_final,
      (COALESCE(ps.sueldo_por_dia,0)*COALESCE(ps.dias_asistidos,0)) +
      (COALESCE(ps.pago_por_horas,0) * (COALESCE(ps.sueldo_por_dia,0)/9 * 1.5)) +
      COALESCE(ps.premios,0) - COALESCE(ps.descuento,0)
  )) AS total_obra,
  COUNT(*) AS cant
  FROM rossi_db.pagos_semanales ps
  JOIN rossi_db.obras o ON o.id=ps.obra_id
  WHERE ps.semana=?
  GROUP BY o.id,o.nombre
  ORDER BY o.nombre";
$st=$conn->prepare($sqlObra); $st->bind_param('s',$semana); $st->execute();
$porObra=$st->get_result()->fetch_all(MYSQLI_ASSOC);

function detObra($db,$sem,$obraId){
  $sql="SELECT ob.nombre AS obrero,
    COALESCE(ps.pago_final,
      (COALESCE(ps.sueldo_por_dia,0)*COALESCE(ps.dias_asistidos,0)) +
      (COALESCE(ps.pago_por_horas,0) * (COALESCE(ps.sueldo_por_dia,0)/9 * 1.5)) +
      COALESCE(ps.premios,0) - COALESCE(ps.descuento,0)
    ) AS total
    FROM rossi_db.pagos_semanales ps
    JOIN rossi_db.obreros ob ON ob.id=ps.obrero_id
    WHERE ps.semana=? AND ps.obra_id=?
    ORDER BY ob.nombre";
  $st=$db->prepare($sql); $st->bind_param('si',$sem,$obraId); $st->execute();
  return $st->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ===================== Por obrero (con detalle) ===================== */
$sqlObrero="SELECT ob.id,ob.nombre,
  SUM(COALESCE(ps.pago_final,
      (COALESCE(ps.sueldo_por_dia,0)*COALESCE(ps.dias_asistidos,0)) +
      (COALESCE(ps.pago_por_horas,0) * (COALESCE(ps.sueldo_por_dia,0)/9 * 1.5)) +
      COALESCE(ps.premios,0) - COALESCE(ps.descuento,0)
  )) AS total_obrero,
  COUNT(*) AS cant,
  COUNT(DISTINCT ps.obra_id) AS obras_cnt
  FROM rossi_db.pagos_semanales ps
  JOIN rossi_db.obreros ob ON ob.id=ps.obrero_id
  WHERE ps.semana=?
  GROUP BY ob.id,ob.nombre
  ORDER BY ob.nombre";
$st=$conn->prepare($sqlObrero); $st->bind_param('s',$semana); $st->execute();
$porObrero=$st->get_result()->fetch_all(MYSQLI_ASSOC);

function detObrero($db,$sem,$obrId){
  $sql="SELECT o.nombre AS obra,
    COALESCE(ps.pago_final,
      (COALESCE(ps.sueldo_por_dia,0)*COALESCE(ps.dias_asistidos,0)) +
      (COALESCE(ps.pago_por_horas,0) * (COALESCE(ps.sueldo_por_dia,0)/9 * 1.5)) +
      COALESCE(ps.premios,0) - COALESCE(ps.descuento,0)
    ) AS total
    FROM rossi_db.pagos_semanales ps
    JOIN rossi_db.obras o ON o.id=ps.obra_id
    WHERE ps.semana=? AND ps.obrero_id=?
    ORDER BY o.nombre";
  $st=$db->prepare($sql); $st->bind_param('si',$sem,$obrId); $st->execute();
  return $st->get_result()->fetch_all(MYSQLI_ASSOC);
}

/* ===================== Contratistas (semana) ===================== */
$st=$conn->prepare("SELECT c.nombre, c.rubro, SUM(pc.monto) AS total, COUNT(*) cnt
                    FROM rossi_db.pagos_contratistas pc
                    JOIN rossi_db.contratistas c ON c.id=pc.contratista_id
                    WHERE pc.fecha BETWEEN ? AND ?
                    GROUP BY c.id,c.nombre,c.rubro
                    ORDER BY c.nombre");
$st->bind_param('ss',$ini,$fin); $st->execute();
$contr_semana=$st->get_result()->fetch_all(MYSQLI_ASSOC);

/* ===================== Pagos varios (semana) ===================== */
$st=$conn->prepare("SELECT beneficiario, descripcion, fecha, monto
                    FROM rossi_db.pagos_varios
                    WHERE fecha BETWEEN ? AND ?
                    ORDER BY fecha, id");
$st->bind_param('ss',$ini,$fin); $st->execute();
$varios=$st->get_result()->fetch_all(MYSQLI_ASSOC);
$tot_varios_list = 0; foreach($varios as $v){ $tot_varios_list += (float)$v['monto']; }

/* ===================== HTML ===================== */
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Dashboard de obras</title>
<meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:#f6f7fb}
  .summary-card{border:0;border-radius:18px;box-shadow:0 6px 16px rgba(20,20,20,.06)}
  .metric{font-size:1.25rem;font-weight:700}
  .muted{color:#6c757d}
  .accordion-button{border-radius:12px!important}
  .nav-tabs .nav-link{border:0;border-bottom:3px solid transparent}
  .nav-tabs .nav-link.active{border-bottom-color:#0d6efd;font-weight:600}
</style>
</head>
<body>
<div class="container py-4">

  <div class="d-flex align-items-center justify-content-between mb-3 flex-wrap gap-2">
    <h4 class="mb-0">Dashboard de obras</h4>
    <form method="get" class="d-flex gap-2">
      <select name="semana" class="form-select" onchange="this.form.submit()">
        <?php foreach($semanasDesc as $s): ?>
          <option value="<?=$s?>" <?=$s===$semana?'selected':''?>><?=h(etiqueta_semana($s))?></option>
        <?php endforeach; ?>
      </select>
      <div class="d-flex gap-2">
        <a class="btn btn-outline-secondary btn-sm <?=$semAnterior?'':'disabled'?>" href="<?=$semAnterior?('?semana='.h($semAnterior)):'#'?>">⟵ Anterior</a>
        <a class="btn btn-outline-secondary btn-sm <?=$semSiguiente?'':'disabled'?>" href="<?=$semSiguiente?('?semana='.h($semSiguiente)):'#'?>">Siguiente ⟶</a>
        <a class="btn btn-outline-dark btn-sm" href="index.php">← Menú</a>
      </div>
    </form>
  </div>

  <div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
      <div class="card summary-card"><div class="card-body">
        <div class="muted mb-1">Total a pagar (semana)</div>
        <div class="metric">$<?=ars($total_semana)?></div>
        <div class="small muted">
          Obreros $<?=ars($tot_obr)?> &middot; Contratistas $<?=ars($tot_contr)?> &middot; Varios $<?=ars($tot_varios)?>
        </div>
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
        <div class="metric"><?=$items_obr?></div>
        <div class="small muted">Filas en la semana</div>
      </div></div>
    </div>
  </div>

  <div class="card shadow-sm border-0">
    <div class="card-body">
      <ul class="nav nav-tabs" role="tablist">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-obra" type="button">Por obra</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-obrero" type="button">Por obrero</button></li>
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
                      <span class="badge text-bg-secondary ms-2"><?=$o['cant']?> pagos</span>
                    </div>
                    <div class="ms-3 fw-semibold">$<?=ars($o['total_obra'])?></div>
                  </button>
                </h2>
                <div id="c<?=$id?>" class="accordion-collapse collapse" data-bs-parent="#accObra">
                  <div class="accordion-body">
                    <?php $det = detObra($conn,$semana,(int)$o['id']); ?>
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

        <!-- Por obrero -->
        <div class="tab-pane fade" id="tab-obrero">
          <?php if(empty($porObrero)): ?>
            <div class="text-center text-muted py-4">Sin resultados.</div>
          <?php else: ?>
          <div class="accordion" id="accObrero">
            <?php $i=0; foreach($porObrero as $p){ $i++; $id="p$i"; ?>
              <div class="accordion-item mb-2 border-0">
                <h2 class="accordion-header">
                  <button class="accordion-button collapsed bg-light" type="button" data-bs-toggle="collapse" data-bs-target="#c<?=$id?>">
                    <div class="me-auto">
                      <b><?=h($p['nombre'])?></b>
                      <span class="badge text-bg-secondary ms-2"><?=$p['cant']?> pagos</span>
                      <span class="badge text-bg-info ms-2"><?=$p['obras_cnt']?> obras</span>
                    </div>
                    <div class="ms-3 fw-semibold">$<?=ars($p['total_obrero'])?></div>
                  </button>
                </h2>
                <div id="c<?=$id?>" class="accordion-collapse collapse" data-bs-parent="#accObrero">
                  <div class="accordion-body">
                    <?php $det = detObrero($conn,$semana,(int)$p['id']); ?>
                    <div class="table-responsive">
                      <table class="table table-sm align-middle">
                        <thead class="table-light"><tr><th>Obra</th><th class="text-end">Monto</th></tr></thead>
                        <tbody>
                          <?php foreach($det as $d): ?>
                            <tr><td><?=h($d['obra'])?></td><td class="text-end">$<?=ars($d['total'])?></td></tr>
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
              <thead class="table-light">
                <tr><th>Contratista</th><th>Rubro</th><th class="text-end">Pagos</th><th class="text-end">Total semana</th></tr>
              </thead>
              <tbody>
                <?php $sum=0; foreach($contr_semana as $c){ $sum += (float)$c['total']; ?>
                  <tr>
                    <td><?=h($c['nombre'])?></td>
                    <td><?=h($c['rubro'])?></td>
                    <td class="text-end"><?= (int)$c['cnt'] ?></td>
                    <td class="text-end">$<?=ars($c['total'])?></td>
                  </tr>
                <?php } ?>
                <tr class="table-light">
                  <th colspan="3" class="text-end">Total contratistas</th>
                  <th class="text-end">$<?=ars($sum)?></th>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Pagos varios -->
        <div class="tab-pane fade" id="tab-varios">
          <div class="table-responsive">
            <table class="table table-sm align-middle">
              <thead class="table-light">
                <tr><th>Beneficiario</th><th>Descripción</th><th>Fecha</th><th class="text-end">Monto</th></tr>
              </thead>
              <tbody>
                <?php if(empty($varios)): ?>
                  <tr><td colspan="4" class="text-center text-muted">Sin pagos varios en esta semana.</td></tr>
                <?php else: foreach($varios as $v): ?>
                  <tr>
                    <td><?=h($v['beneficiario'])?></td>
                    <td><?=h($v['descripcion'])?></td>
                    <td><?=h(date('d/m/Y', strtotime($v['fecha'])))?></td>
                    <td class="text-end">$<?=ars($v['monto'])?></td>
                  </tr>
                <?php endforeach; endif; ?>
                <tr class="table-light">
                  <th colspan="3" class="text-end">Total pagos varios</th>
                  <th class="text-end">$<?=ars($tot_varios_list)?></th>
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
