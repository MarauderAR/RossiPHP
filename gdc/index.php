<?php
require_once '_auth.php';   // panel protegido por login de tu app
require_once '_db.php';

// === Alta rápida (POST) ===
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['nuevo'])) {
  $proveedor = trim($_POST['proveedor'] ?? '');
  $nroCheque = trim($_POST['nro_cheque'] ?? '');
  $fechaPago = $_POST['fecha_pago'] ?? '';
  $estado    = ($_POST['estado'] ?? 'por_entrar') === 'pagado' ? 'pagado' : 'por_entrar';
  $monto     = parse_money_ar($_POST['monto'] ?? '0');

  if ($proveedor && $fechaPago && $monto >= 0){
    $stmt=$mysqli->prepare("INSERT INTO cheques(proveedor,nro_cheque,fecha_pago,estado,monto) VALUES (?,?,?,?,?)");
    $stmt->bind_param('ssssd', $proveedor, $nroCheque, $fechaPago, $estado, $monto);
    $stmt->execute();
  }
  header("Location: index.php");
  exit;
}

// === Filtros (GET) ===
$proveedor = trim($_GET['proveedor'] ?? '');
$desde     = $_GET['desde'] ?? '';
$hasta     = $_GET['hasta'] ?? '';
$min       = $_GET['min'] ?? '';
$max       = $_GET['max'] ?? '';

function build_where(&$types,&$params,$proveedor,$desde,$hasta,$min,$max){
  $where=[];
  if ($proveedor!==''){ $where[]="proveedor LIKE ?"; $types.='s'; $params[]="%$proveedor%"; }
  if ($desde!==''){     $where[]="fecha_pago >= ?";  $types.='s'; $params[]=$desde; }
  if ($hasta!==''){     $where[]="fecha_pago <= ?";  $types.='s'; $params[]=$hasta; }
  if ($min!==''){       $where[]="monto >= ?";       $types.='d'; $params[]=parse_money_ar($min); }
  if ($max!==''){       $where[]="monto <= ?";       $types.='d'; $params[]=parse_money_ar($max); }
  return $where;
}

// === POR ENTRAR ===
$types_pe=''; $params_pe=[]; $where_pe = build_where($types_pe,$params_pe,$proveedor,$desde,$hasta,$min,$max);
$where_pe[]="estado='por_entrar'";
$sql_pe = "SELECT * FROM cheques".($where_pe? " WHERE ".implode(" AND ",$where_pe):"")." ORDER BY fecha_pago ASC, id ASC";
$stmt = $mysqli->prepare($sql_pe);
if (!empty($types_pe)) { $stmt->bind_param($types_pe, ...$params_pe); }
$stmt->execute(); $res = $stmt->get_result();
$rows_pe=[]; $total_pe=0; while($r=$res->fetch_assoc()){ $rows_pe[]=$r; $total_pe += (float)$r['monto']; }

// === PAGADOS ===
$types_pg=''; $params_pg=[]; $where_pg = build_where($types_pg,$params_pg,$proveedor,$desde,$hasta,$min,$max);
$where_pg[]="estado='pagado'";
$sql_pg = "SELECT * FROM cheques".($where_pg? " WHERE ".implode(" AND ",$where_pg):"")." ORDER BY fecha_pago ASC, id ASC";
$stmt = $mysqli->prepare($sql_pg);
if (!empty($types_pg)) { $stmt->bind_param($types_pg, ...$params_pg); }
$stmt->execute(); $res = $stmt->get_result();
$rows_pg=[]; $total_pg=0; while($r=$res->fetch_assoc()){ $rows_pg[]=$r; $total_pg += (float)$r['monto']; }

// Totales generales
$tg = ($total_pe + $total_pg);

include '_layout_head.php';
?>
<div class="row g-3">
  <!-- Alta -->
  <div class="col-12 col-lg-4 new-card d-print-none">
    <div class="card shadow-sm">
      <div class="card-body">
        <h5 class="card-title mb-3">Nuevo cheque</h5>
        <form method="post" class="row g-2">
          <input type="hidden" name="nuevo" value="1">
          <div class="col-12">
            <label class="form-label">Proveedor</label>
            <input name="proveedor" class="form-control" placeholder="Ej: Dommarco" required>
          </div>
          <div class="col-12">
            <label class="form-label">N° de cheque</label>
            <input name="nro_cheque" class="form-control" placeholder="Ej: 000123 (opcional)">
          </div>
          <div class="col-6">
            <label class="form-label">Fecha (programada / débito)</label>
            <input type="date" name="fecha_pago" class="form-control" required>
          </div>
          <div class="col-6">
            <label class="form-label">Monto</label>
            <input name="monto" class="form-control" placeholder="1.234.567,89" required>
          </div>
          <div class="col-12">
            <label class="form-label">Estado</label>
            <select name="estado" class="form-select">
              <option value="por_entrar" selected>Por entrar</option>
              <option value="pagado">Pagado</option>
            </select>
          </div>
          <div class="col-12 d-grid">
            <button class="btn btn-primary">Guardar</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Filtros + Totales -->
  <div class="col-12 col-lg-8">
    <div class="card shadow-sm filter-card">
      <div class="card-body">
        <form class="row g-2" method="get">
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
            <a href="index.php" class="btn btn-outline-secondary">Limpiar</a>
            <div class="ms-auto">
              <span class="badge rounded-pill text-bg-danger">Por entrar: $<?=ars_money($total_pe)?></span>
              <span class="badge rounded-pill text-bg-success">Pagados: $<?=ars_money($total_pg)?></span>
              <span class="badge rounded-pill badge-soft ms-1">Total: $<?=ars_money($tg)?></span>
              <button type="button" class="btn btn-success d-print-none ms-2" onclick="imprimir()">Imprimir / PDF</button>
            </div>
          </div>
        </form>
      </div>
    </div>

    <!-- TABS -->
    <div class="card mt-3 shadow-sm">
      <div class="card-body">
        <ul class="nav nav-tabs" id="chequesTabs" role="tablist">
          <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-pe" data-bs-toggle="tab" data-bs-target="#pane-pe" type="button" role="tab">
              Por entrar <span class="badge text-bg-danger ms-1">$<?=ars_money($total_pe)?></span>
            </button>
          </li>
          <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-pg" data-bs-toggle="tab" data-bs-target="#pane-pg" type="button" role="tab">
              Pagados <span class="badge text-bg-success ms-1">$<?=ars_money($total_pg)?></span>
            </button>
          </li>
        </ul>

        <div class="tab-content pt-3">
          <!-- TAB POR ENTRAR -->
          <div class="tab-pane fade show active" id="pane-pe" role="tabpanel" aria-labelledby="tab-pe">
            <!-- DESKTOP -->
            <div class="desktop-only">
              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                  <thead class="table-dark">
                    <tr>
                      <th>Proveedor</th>
                      <th>N° Cheque</th>
                      <th class="text-nowrap">Fecha</th>
                      <th class="text-end">Monto</th>
                      <th class="text-end d-print-none">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($rows_pe as $r): ?>
                      <tr class="table-danger">
                        <td><?=h($r['proveedor'])?></td>
                        <td><?=h($r['nro_cheque'])?></td>
                        <td class="text-nowrap"><?=date('d/m/Y', strtotime($r['fecha_pago']))?></td>
                        <td class="text-end">$<?=ars_money($r['monto'])?></td>
                        <td class="text-end d-print-none">
                          <form method="post" action="update_estado.php" class="d-inline">
                            <input type="hidden" name="id" value="<?=$r['id']?>">
                            <input type="hidden" name="estado" value="pagado">
                            <button class="btn btn-sm btn-success" onclick="return confirm('¿Marcar como pagado?')">Pagado</button>
                          </form>
                          <a class="btn btn-sm btn-outline-danger" href="delete.php?id=<?=$r['id']?>" onclick="return confirm('¿Borrar cheque?');">Borrar</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if(empty($rows_pe)): ?>
                      <tr><td colspan="5" class="text-center text-muted py-4">Sin resultados.</td></tr>
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
                        <div class="cheque-actions mt-3 d-grid gap-2 d-print-none">
                          <form method="post" action="update_estado.php">
                            <input type="hidden" name="id" value="<?=$r['id']?>">
                            <input type="hidden" name="estado" value="pagado">
                            <button class="btn btn-success" onclick="return confirm('¿Marcar como pagado?')">Pagado</button>
                          </form>
                          <a class="btn btn-outline-danger" href="delete.php?id=<?=$r['id']?>" onclick="return confirm('¿Borrar cheque?');">Borrar</a>
                        </div>
                      </div>
                    </div>
                  </div>
                <?php endforeach; ?>
              </div>
            </div>
          </div>

          <!-- TAB PAGADOS -->
          <div class="tab-pane fade" id="pane-pg" role="tabpanel" aria-labelledby="tab-pg">
            <!-- DESKTOP -->
            <div class="desktop-only">
              <div class="table-responsive">
                <table class="table table-sm table-striped align-middle">
                  <thead class="table-dark">
                    <tr>
                      <th>Proveedor</th>
                      <th>N° Cheque</th>
                      <th class="text-nowrap">Fecha</th>
                      <th class="text-end">Monto</th>
                      <th class="text-end d-print-none">Acciones</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach($rows_pg as $r): ?>
                      <tr class="table-success">
                        <td><?=h($r['proveedor'])?></td>
                        <td><?=h($r['nro_cheque'])?></td>
                        <td class="text-nowrap"><?=date('d/m/Y', strtotime($r['fecha_pago']))?></td>
                        <td class="text-end">$<?=ars_money($r['monto'])?></td>
                        <td class="text-end d-print-none">
                          <a class="btn btn-sm btn-outline-danger" href="delete.php?id=<?=$r['id']?>" onclick="return confirm('¿Borrar cheque?');">Borrar</a>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if(empty($rows_pg)): ?>
                      <tr><td colspan="5" class="text-center text-muted py-4">Sin resultados.</td></tr>
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
                        <div class="cheque-actions mt-3 d-grid gap-2 d-print-none">
                          <a class="btn btn-outline-danger" href="delete.php?id=<?=$r['id']?>" onclick="return confirm('¿Borrar cheque?');">Borrar</a>
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
  </div>
</div>
<?php include '_layout_foot.php'; ?>
