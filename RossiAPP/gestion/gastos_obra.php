<?php
// gestion/gastos_obra.php
require_once("../config.php");
$conn->set_charset('utf8mb4');

/* Crear la tabla solo si no existe (check liviano) */
$existe = $conn->query("
  SELECT 1
  FROM information_schema.tables
  WHERE table_schema = DATABASE()
    AND table_name   = 'gastos_obra'
")->num_rows;

if ($existe == 0) {
  $conn->query("
    CREATE TABLE gastos_obra (
      id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      obra_id INT NOT NULL,
      fecha DATE NOT NULL,
      categoria VARCHAR(60) DEFAULT NULL,
      concepto VARCHAR(255) NOT NULL,
      cantidad DECIMAL(10,2) NOT NULL DEFAULT 1,
      precio_unitario DECIMAL(12,2) NOT NULL DEFAULT 0,
      total DECIMAL(12,2) NOT NULL DEFAULT 0,
      proveedor_libre VARCHAR(150) DEFAULT NULL,
      observacion VARCHAR(255) DEFAULT NULL,
      adjunto_path VARCHAR(255) DEFAULT NULL,
      created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
      INDEX idx_gastos_obra_obra_id (obra_id),
      INDEX idx_gastos_obra_fecha (obra_id, fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
  ");
}

/* Cargar obras */
$obras = [];
$res = $conn->query("SELECT id, nombre FROM obras ORDER BY nombre");
while ($r = $res->fetch_assoc()) $obras[] = $r;

/* Eliminar gasto (y adjunto) */
if (isset($_GET['del'])) {
    $del = (int)$_GET['del'];
    $q = $conn->prepare("SELECT adjunto_path FROM gastos_obra WHERE id=?");
    $q->bind_param("i",$del);
    $q->execute();
    $q->bind_result($ap);
    $has = $q->fetch();
    $q->close();
    if ($has && $ap) {
        $abs = realpath(__DIR__ . '/' . $ap);
        $base = realpath(__DIR__);
        if ($abs && strpos($abs,$base)===0 && file_exists($abs)) @unlink($abs);
    }
    $d = $conn->prepare("DELETE FROM gastos_obra WHERE id=?");
    $d->bind_param("i",$del);
    $d->execute();
    $d->close();
    header("Location: gastos_obra.php"); exit;
}

/* Alta de gasto */
$msg = null; $err = null;
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['guardar'])) {
    $obra_id  = (int)($_POST['obra_id'] ?? 0);
    $fecha    = $_POST['fecha'] ?? date('Y-m-d');
    $concepto = trim($_POST['concepto'] ?? '');
    $total_in = (string)($_POST['total_simple'] ?? '');
    // avanzados
    $categoria = trim($_POST['categoria'] ?? '');
    $proveedor = trim($_POST['proveedor_libre'] ?? '');
    $obs       = trim($_POST['observacion'] ?? '');
    $cant      = (float)str_replace(',','.', $_POST['cantidad'] ?? '0');
    $pu        = (float)str_replace(',','.', $_POST['precio_unitario'] ?? '0');

    if (!$obra_id || !$fecha || $concepto==='') {
        $err = "Completá Obra, Fecha y Concepto.";
    } else {
        $total = 0.0;
        $total_simple = (float)str_replace(',','.', $total_in);
        if ($cant > 0 && $pu > 0) {
            $total = round($cant * $pu, 2);
        } elseif ($total_simple > 0) {
            $total = round($total_simple, 2);
            $cant = 1;
            $pu   = $total;
        } else {
            $err = "Ingresá Total o Cantidad y Precio unitario.";
        }
    }

    // Subida de adjunto (opcional) con validación fuerte
    $adjunto_rel = null;
    if (!$err && !empty($_FILES['adjunto']['name']) && is_uploaded_file($_FILES['adjunto']['tmp_name'])) {
        $maxBytes = 15 * 1024 * 1024; // 15MB
        if ($_FILES['adjunto']['size'] > $maxBytes) {
            $err = "El adjunto supera 15 MB.";
        } else {
            $allow = ['pdf','jpg','jpeg','png','webp'];
            $ext = strtolower(pathinfo($_FILES['adjunto']['name'], PATHINFO_EXTENSION));
            if (!in_array($ext,$allow)) {
                $err = "Formato no permitido. Usá PDF/JPG/PNG/WEBP.";
            } else {
                $dir = __DIR__ . '/uploads/gastos';
                if (!is_dir($dir)) @mkdir($dir,0775,true);
                $safe = preg_replace('/[^A-Za-z0-9_\.-]/','_', basename($_FILES['adjunto']['name']));
                $name = date('Ymd_His') . '_' . substr(md5(uniqid('',true)),0,8) . '_' . $safe;
                $dest = $dir . '/' . $name;
                if (move_uploaded_file($_FILES['adjunto']['tmp_name'],$dest)) {
                    $adjunto_rel = 'uploads/gastos/' . $name;
                } else {
                    $err = "No se pudo guardar el archivo.";
                }
            }
        }
    }

    if (!$err) {
        $stmt = $conn->prepare("INSERT INTO gastos_obra
            (obra_id, fecha, categoria, concepto, cantidad, precio_unitario, total, proveedor_libre, observacion, adjunto_path)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        // tipos (10): i s s s d d d s s s
        $stmt->bind_param("isssdddsss",
            $obra_id, $fecha, $categoria, $concepto, $cant, $pu, $total, $proveedor, $obs, $adjunto_rel
        );
        $stmt->execute();
        $stmt->close();
        $msg = "✅ Gasto guardado.";
    }
}

/* Filtros */
$f_obra  = (int)($_GET['obra_id'] ?? 0);
$f_desde = $_GET['desde'] ?? date('Y-m-01');
$f_hasta = $_GET['hasta'] ?? date('Y-m-t');
$f_q     = trim($_GET['q'] ?? '');

$sql = "SELECT g.*, o.nombre AS obra FROM gastos_obra g
        JOIN obras o ON o.id=g.obra_id WHERE 1";
$types = ""; $params = [];

if ($f_obra>0) { $sql.=" AND g.obra_id=?"; $types.="i"; $params[]=$f_obra; }
if ($f_desde) { $sql.=" AND g.fecha>=?";  $types.="s"; $params[]=$f_desde; }
if ($f_hasta) { $sql.=" AND g.fecha<=?";  $types.="s"; $params[]=$f_hasta; }
if ($f_q!=='') {
    $sql.=" AND (g.concepto LIKE ? OR g.proveedor_libre LIKE ? OR g.categoria LIKE ?)";
    $like = "%$f_q%"; $types.="sss"; $params[]=$like; $params[]=$like; $params[]=$like;
}
$sql.=" ORDER BY g.fecha DESC, g.id DESC";

/* Export CSV */
if (isset($_GET['export']) && $_GET['export']=='1') {
    $st = $conn->prepare($sql);
    if ($types) $st->bind_param($types, ...$params);
    $st->execute();
    $rs = $st->get_result();

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="gastos_obra_'.date('Ymd_His').'.csv"');
    $out = fopen('php://output','w');
    fputcsv($out, ['Fecha','Obra','Categoría','Concepto','Proveedor','Cantidad','P.Unit','Total','Adjunto']);
    while ($g = $rs->fetch_assoc()) {
        fputcsv($out, [
            $g['fecha'],$g['obra'],$g['categoria'],$g['concepto'],
            $g['proveedor_libre'], (float)$g['cantidad'], (float)$g['precio_unitario'],
            (float)$g['total'], $g['adjunto_path']
        ]);
    }
    fclose($out); exit;
}

/* Listado normal */
$st = $conn->prepare($sql);
if ($types) $st->bind_param($types, ...$params);
$st->execute();
$lista = $st->get_result()->fetch_all(MYSQLI_ASSOC);
$st->close();

$total_periodo = 0.0;
foreach ($lista as $it) $total_periodo += (float)$it['total'];
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Gastos por Obra</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
<style>
  body{background:#f6f8fa}
  .table td,.table th{font-size:.95em}
  .form-help{font-size:.85em;color:#6c757d}
</style>
</head>
<body class="p-3">
<div class="container">
  <h3 class="mb-3">Gastos por Obra</h3>

  <?php if($msg): ?><div class="alert alert-success"><?=htmlspecialchars($msg)?></div><?php endif; ?>
  <?php if($err): ?><div class="alert alert-warning"><?=htmlspecialchars($err)?></div><?php endif; ?>

  <!-- Alta rápida -->
  <div class="card mb-4">
    <div class="card-header">Cargar gasto (rápido)</div>
    <div class="card-body">
      <form method="post" enctype="multipart/form-data" class="row g-3" id="formGasto">
        <div class="col-12 col-md-3">
          <label class="form-label">Obra *</label>
          <select name="obra_id" class="form-select" required>
            <option value="">--</option>
            <?php foreach($obras as $o): ?>
              <option value="<?=$o['id']?>"><?=htmlspecialchars($o['nombre'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-12 col-md-2">
          <label class="form-label">Fecha *</label>
          <input type="date" name="fecha" class="form-control" value="<?=date('Y-m-d')?>" required>
        </div>
        <div class="col-12 col-md-4">
          <label class="form-label">Concepto *</label>
          <input type="text" name="concepto" class="form-control" placeholder="Guantes de nitrilo x12" required>
        </div>
        <div class="col-12 col-md-3">
          <label class="form-label">Total (rápido)</label>
          <div class="input-group">
            <span class="input-group-text">$</span>
            <input type="number" step="0.01" name="total_simple" id="total_simple" class="form-control" placeholder="0.00">
          </div>
          <div class="form-help">Si usás este campo, no hace falta cantidad y P.U.</div>
        </div>

        <!-- Adjunto visible siempre -->
        <div class="col-12 col-md-4">
          <label class="form-label">Comprobante (PDF/JPG/PNG/WEBP)</label>
          <input type="file" name="adjunto" id="adjunto" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp">
          <div class="form-help">Máx. 15 MB</div>
        </div>

        <div class="col-12">
          <a class="link-secondary" data-bs-toggle="collapse" href="#masOpciones" role="button" aria-expanded="false" aria-controls="masOpciones">Mostrar opciones avanzadas</a>
        </div>

        <div class="collapse" id="masOpciones">
          <div class="row g-3 mt-1">
            <div class="col-md-3">
              <label class="form-label">Categoría</label>
              <input list="cats" name="categoria" class="form-control" placeholder="EPP / Materiales / ...">
              <datalist id="cats">
                <option value="EPP / Materiales">
                <option value="Transporte">
                <option value="Herramientas">
                <option value="Alquiler">
                <option value="Servicios">
                <option value="Viáticos">
              </datalist>
            </div>
            <div class="col-md-3">
              <label class="form-label">Proveedor (libre)</label>
              <input type="text" name="proveedor_libre" class="form-control" placeholder="Ferretería X">
            </div>
            <div class="col-md-2">
              <label class="form-label">Cantidad</label>
              <input type="number" step="0.01" name="cantidad" id="cant" class="form-control" value="1">
            </div>
            <div class="col-md-2">
              <label class="form-label">P. unitario</label>
              <input type="number" step="0.01" name="precio_unitario" id="pu" class="form-control" value="0">
            </div>
            <div class="col-md-12">
              <label class="form-label">Observación</label>
              <input type="text" name="observacion" class="form-control">
            </div>
          </div>
        </div>

        <div class="col-12 d-flex gap-2">
          <button class="btn btn-primary" name="guardar" value="1">Guardar gasto</button>
          <a class="btn btn-outline-secondary" href="index.php">← Volver</a>
        </div>
      </form>
    </div>
  </div>

  <!-- Listado -->
  <div class="card">
    <div class="card-header">Listado</div>
    <div class="card-body">
      <form class="row g-2 mb-3" method="get">
        <div class="col-md-3">
          <select name="obra_id" class="form-select">
            <option value="0">Todas las obras</option>
            <?php foreach($obras as $o): ?>
              <option value="<?=$o['id']?>" <?=$f_obra==$o['id']?'selected':''?>><?=htmlspecialchars($o['nombre'])?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="col-md-2"><input type="date" name="desde" class="form-control" value="<?=htmlspecialchars($f_desde)?>"></div>
        <div class="col-md-2"><input type="date" name="hasta" class="form-control" value="<?=htmlspecialchars($f_hasta)?>"></div>
        <div class="col-md-3"><input type="text" name="q" class="form-control" value="<?=htmlspecialchars($f_q)?>" placeholder="Buscar: concepto/categoría/proveedor"></div>
        <div class="col-md-2 d-flex gap-2">
          <button class="btn btn-outline-secondary w-50">Filtrar</button>
          <a class="btn btn-outline-success w-50"
             href="?obra_id=<?=$f_obra?>&desde=<?=urlencode($f_desde)?>&hasta=<?=urlencode($f_hasta)?>&q=<?=urlencode($f_q)?>&export=1">Export CSV</a>
        </div>
        <div class="col-12 text-end">
          <span class="fw-bold">Total período: $<?=number_format($total_periodo,2,',','.')?></span>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-bordered table-sm align-middle">
          <thead class="table-light">
            <tr>
              <th>Fecha</th>
              <th>Obra</th>
              <th>Categoría</th>
              <th>Concepto</th>
              <th>Proveedor</th>
              <th class="text-end">Cant</th>
              <th class="text-end">P.U.</th>
              <th class="text-end">Total</th>
              <th>Adjunto</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($lista as $g): ?>
            <tr>
              <td><?=htmlspecialchars($g['fecha'])?></td>
              <td><?=htmlspecialchars($g['obra'])?></td>
              <td><?=htmlspecialchars($g['categoria'])?></td>
              <td><?=htmlspecialchars($g['concepto'])?></td>
              <td><?=htmlspecialchars($g['proveedor_libre'])?></td>
              <td class="text-end"><?=number_format((float)$g['cantidad'],2,',','.')?></td>
              <td class="text-end"><?=number_format((float)$g['precio_unitario'],2,',','.')?></td>
              <td class="text-end fw-semibold"><?=number_format((float)$g['total'],2,',','.')?></td>
              <td>
                <?php if($g['adjunto_path']): ?>
                  <a href="<?=htmlspecialchars($g['adjunto_path'])?>" target="_blank">Ver</a>
                  &nbsp;/&nbsp;
                  <a href="<?=htmlspecialchars($g['adjunto_path'])?>" download>Descargar</a>
                <?php else: ?>—<?php endif; ?>
              </td>
              <td class="text-center">
                <a class="btn btn-sm btn-outline-danger"
                   onclick="return confirm('¿Eliminar gasto?')"
                   href="?del=<?=$g['id']?>">Borrar</a>
              </td>
            </tr>
          <?php endforeach; ?>
          <?php if(!$lista): ?>
            <tr><td colspan="10" class="text-center text-muted">Sin resultados</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// cálculo rápido: si llenás cantidad y p.u., sugerimos total en el campo rápido
const cant = document.getElementById('cant');
const pu   = document.getElementById('pu');
const tot  = document.getElementById('total_simple');
function recalc(){
  if(!cant || !pu || !tot) return;
  const c = parseFloat(cant.value||'0');
  const p = parseFloat(pu.value||'0');
  if(c>0 && p>0) tot.value = (c*p).toFixed(2);
}
if(cant) cant.addEventListener('input', recalc);
if(pu)   pu.addEventListener('input', recalc);

// Validación cliente antes de enviar
document.getElementById('formGasto').addEventListener('submit', function(e){
  const obra = this.querySelector('select[name="obra_id"]').value;
  const fecha = this.querySelector('input[name="fecha"]').value;
  const concepto = this.querySelector('input[name="concepto"]').value.trim();
  const total = parseFloat((this.querySelector('#total_simple').value||'0').replace(',','.'));
  const c = parseFloat((this.querySelector('#cant')?.value||'0').replace(',','.'));
  const p = parseFloat((this.querySelector('#pu')?.value||'0').replace(',','.'));
  const file = document.getElementById('adjunto').files[0];

  if(!obra || !fecha || !concepto){
    e.preventDefault(); alert('Completá Obra, Fecha y Concepto.'); return;
  }
  if(!(total>0) && !(c>0 && p>0)){
    e.preventDefault(); alert('Ingresá Total o Cantidad y Precio unitario.'); return;
  }
  if(file){
    const okExt = ['pdf','jpg','jpeg','png','webp'];
    const ext = (file.name.split('.').pop()||'').toLowerCase();
    if(!okExt.includes(ext)){ e.preventDefault(); alert('Adjunto inválido. Usá PDF/JPG/PNG/WEBP.'); return; }
    if(file.size > 15*1024*1024){ e.preventDefault(); alert('Adjunto supera 15 MB.'); return; }
  }
});
</script>
</body>
</html>
