<?php
require_once '_db.php';

$token = $_GET['token'] ?? '';
if (!$token){ http_response_code(404); die('Token faltante'); }

$qr=$mysqli->prepare("SELECT filtros_json, expira_en FROM view_tokens WHERE token=?");
$qr->bind_param('s',$token); $qr->execute();
$t=$qr->get_result()->fetch_assoc();
if(!$t){ http_response_code(404); die('Token inválido'); }
// sin vencimiento
// if($t['expira_en'] && strtotime($t['expira_en']) < time()){ die('Token vencido'); }

$defaults = json_decode($t['filtros_json'] ?? '{}', true) ?: [];

// Filtros GET
$proveedor = trim($_GET['proveedor'] ?? ($defaults['proveedor'] ?? ''));
$desde     = $_GET['desde'] ?? ($defaults['desde'] ?? '');
$hasta     = $_GET['hasta'] ?? ($defaults['hasta'] ?? '');
$min       = $_GET['min'] ?? ($defaults['min'] ?? '');
$max       = $_GET['max'] ?? ($defaults['max'] ?? '');

function build_where(&$types,&$params,$proveedor,$desde,$hasta,$min,$max){
  $where=[];
  if ($proveedor!==''){ $where[]="proveedor LIKE ?"; $types.='s'; $params[]="%$proveedor%"; }
  if ($desde!==''){     $where[]="fecha_pago >= ?";  $types.='s'; $params[]=$desde; }
  if ($hasta!==''){     $where[]="fecha_pago <= ?";  $types.='s'; $params[]=$hasta; }
  if ($min!==''){       $where[]="monto >= ?";       $types.='d'; $params[]=parse_money_ar($min); }
  if ($max!==''){       $where[]="monto <= ?";       $types.='d'; $params[]=parse_money_ar($max); }
  return $where;
}

// Por entrar
$types_pe=''; $params_pe=[]; $where_pe = build_where($types_pe,$params_pe,$proveedor,$desde,$hasta,$min,$max);
$where_pe[]="estado='por_entrar'";
$sql_pe="SELECT * FROM cheques".($where_pe? " WHERE ".implode(" AND ",$where_pe):"")." ORDER BY fecha_pago ASC, id ASC";
$stmt=$mysqli->prepare($sql_pe);
if (!empty($types_pe)) { $stmt->bind_param($types_pe, ...$params_pe); }
$stmt->execute(); $res=$stmt->get_result();
$rows_pe=[]; $total_pe=0; while($r=$res->fetch_assoc()){ $rows_pe[]=$r; $total_pe+=(float)$r['monto']; }

// Pagados
$types_pg=''; $params_pg=[]; $where_pg = build_where($types_pg,$params_pg,$proveedor,$desde,$hasta,$min,$max);
$where_pg[]="estado='pagado'";
$sql_pg="SELECT * FROM cheques".($where_pg? " WHERE ".implode(" AND ",$where_pg):"")." ORDER BY fecha_pago ASC, id ASC";
$stmt=$mysqli->prepare($sql_pg);
if (!empty($types_pg)) { $stmt->bind_param($types_pg, ...$params_pg); }
$stmt->execute(); $res=$stmt->get_result();
$rows_pg=[]; $total_pg=0; while($r=$res->fetch_assoc()){ $rows_pg[]=$r; $total_pg+=(float)$r['monto']; }

include '_layout_head.php';
?>
<div class="d-flex align-items-center mb-3">
  <h4 class="mb-0">Cheques (solo lectura)</h4>
  <a href="../rossiapp/obras/owner_index.php" class="btn btn-outline-dark ms-auto d-print-none">
    ← Volver al menú
  </a>
</div>

<div class="card shadow-sm filter-card d-print-none mb-3">
  <div class="card-body">
    <form class="row g-2" method="get">
      <input type="hidden" name="token" value="<?=h($token)?>">
      <div class="col-12 col-md-4">
        <label class="form-label">Proveedor</label>
        <input name="proveedor" class="form-control" value="<?=h($proveedor)?>" placeholder="Buscar proveedor">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Desde</label>
        <input type="date" name="desde" class="form-control" value="<?=h($desde)?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Hasta</label>
        <input type="date" name="hasta" class="form-control" value="<?=h($hasta)?>">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Monto mín.</label>
        <input name="min" class="form-control" value="<?=h($min)?>" placeholder="0">
      </div>
      <div class="col-6 col-md-2">
        <label class="form-label">Monto máx.</label>
        <input name="max" class="form-control" value="<?=h($max)?>" placeholder="∞">
      </div>
      <div class="col-12 d-flex gap-2">
        <button class="btn btn-outline-primary">Filtrar</button>
        <a href="view.php?token=<?=h($token)?>" class="btn btn-outline-secondary">Limpiar</a>
        <div class="ms-auto">
          <span class="badge rounded-pill text-bg-danger">Por entrar: $<?=ars_money($total_pe)?></span>
          <span class="badge rounded-pill text-bg-success">Pagados: $<?=ars_money($total_pg)?></span>
        </div>
      </div>
    </form>
  </div>
</div>

<div class="card shadow-sm">
  <div class="card-body">
    <ul class="nav nav-tabs" role="tablist">
      <li class="nav-item" role="presentation">
        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#v-pe" type="button" role="tab">
          Por entrar <span class="badge text-bg-danger ms-1">$<?=ars_money($total_pe)?></span>
        </button>
      </li>
      <li class="nav-item" role="presentation">
        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#v-pg" type="button" role="tab">
          Pagados <span class="badge text-bg-success ms-1">$<?=ars_money($total_pg)?></span>
        </button>
      </li>
    </ul>

    <div class="tab-content pt-3">
      <!-- POR ENTRAR -->
      <div class="tab-pane fade show active" id="v-pe" role="tabpanel">
        <!-- DESKTOP -->
        <div class="desktop-only">
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead class="table-dark">
                <tr>
                  <th>Proveedor</th>
                  <th>N° Cheque</th>
                  <th>Fecha</th>
                  <th class="text-end">Monto</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($rows_pe as $r): ?>
                  <tr class="table-danger">
                    <td><?=h($r['proveedor'])?></td>
                    <td><?=h($r['nro_cheque'])?></td>
                    <td><?=date('d/m/Y', strtotime($r['fecha_pago']))?></td>
                    <td class="text-end">$<?=ars_money($r['monto'])?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if(empty($rows_pe)): ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">Sin resultados.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <!-- MÓVIL -->
        <div class="mobile-only">
          <?php if(empty($rows_pe)): ?>
            <div class="text-center text-muted py-3">Sin resultados.</div>
          <?php endif; ?>
          <div class="row g-2">
            <?php foreach($rows_pe as $r): ?>
              <div class="col-12">
                <div class="card cheque-card border-0 shadow-sm" style="border-left:6px solid #dc3545;">
                  <div class="card-body py-3">
                    <div class="d-flex justify-content-between">
                      <div class="me-2">
                        <div class="label">Proveedor</div>
                        <div class="value"><?=h($r['proveedor'])?></div>
                      </div>
                      <div class="text-end">
                        <div class="label">Monto</div>
                        <div class="value">$<?=ars_money($r['monto'])?></div>
                      </div>
                    </div>
                    <div class="mt-2 d-flex justify-content-between">
                      <div>
                        <div class="label">N° Cheque</div>
                        <div><?=h($r['nro_cheque']) ?: '—'?></div>
                      </div>
                      <div class="text-end">
                        <div class="label">Fecha</div>
                        <div><?=date('d/m/Y', strtotime($r['fecha_pago']))?></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>

      <!-- PAGADOS -->
      <div class="tab-pane fade" id="v-pg" role="tabpanel">
        <!-- DESKTOP -->
        <div class="desktop-only">
          <div class="table-responsive">
            <table class="table table-sm table-striped align-middle">
              <thead class="table-dark">
                <tr>
                  <th>Proveedor</th>
                  <th>N° Cheque</th>
                  <th>Fecha</th>
                  <th class="text-end">Monto</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach($rows_pg as $r): ?>
                  <tr class="table-success">
                    <td><?=h($r['proveedor'])?></td>
                    <td><?=h($r['nro_cheque'])?></td>
                    <td><?=date('d/m/Y', strtotime($r['fecha_pago']))?></td>
                    <td class="text-end">$<?=ars_money($r['monto'])?></td>
                  </tr>
                <?php endforeach; ?>
                <?php if(empty($rows_pg)): ?>
                  <tr><td colspan="4" class="text-center text-muted py-4">Sin resultados.</td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
        <!-- MÓVIL -->
        <div class="mobile-only">
          <?php if(empty($rows_pg)): ?>
            <div class="text-center text-muted py-3">Sin resultados.</div>
          <?php endif; ?>
          <div class="row g-2">
            <?php foreach($rows_pg as $r): ?>
              <div class="col-12">
                <div class="card cheque-card border-0 shadow-sm" style="border-left:6px solid #198754;">
                  <div class="card-body py-3">
                    <div class="d-flex justify-content-between">
                      <div class="me-2">
                        <div class="label">Proveedor</div>
                        <div class="value"><?=h($r['proveedor'])?></div>
                      </div>
                      <div class="text-end">
                        <div class="label">Monto</div>
                        <div class="value">$<?=ars_money($r['monto'])?></div>
                      </div>
                    </div>
                    <div class="mt-2 d-flex justify-content-between">
                      <div>
                        <div class="label">N° Cheque</div>
                        <div><?=h($r['nro_cheque']) ?: '—'?></div>
                      </div>
                      <div class="text-end">
                        <div class="label">Fecha</div>
                        <div><?=date('d/m/Y', strtotime($r['fecha_pago']))?></div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div><!-- tab-content -->
  </div>
</div>

<?php include '_layout_foot.php'; ?>
