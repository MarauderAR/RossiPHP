<?php
require_once("../config.php");
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
$db = (isset($conn) && $conn instanceof mysqli) ? $conn : $mysqli;
$db->set_charset("utf8mb4");

function h($s){ return htmlspecialchars((string)$s,ENT_QUOTES,'UTF-8'); }
function ars($n){ return number_format((float)$n,2,',','.'); }

/* Helpers semana */
function rango_semana_por_fecha(string $iso): array {
  $dt = new DateTimeImmutable($iso);
  $dow = (int)$dt->format('N');
  $ini = $dt->modify('-'.($dow-1).' days')->format('Y-m-d');
  $fin = (new DateTimeImmutable($ini))->modify('+6 days')->format('Y-m-d');
  return [$ini,$fin];
}
$hoy = date('Y-m-d');
[$ini_def,$fin_def] = rango_semana_por_fecha($hoy);

/* Alta */
$msg = null;
if($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['accion']) && $_POST['accion']==='guardar'){
  $beneficiario = trim($_POST['beneficiario']??'');
  $descripcion  = trim($_POST['descripcion']??'');
  $fecha        = $_POST['fecha']??date('Y-m-d');
  $monto        = (float)str_replace(['.','.'],['',''], str_replace(',','.',$_POST['monto']??'0'));
  if($beneficiario!=='' && $monto>0){
    $st=$db->prepare("INSERT INTO rossi_db.pagos_varios (beneficiario,descripcion,fecha,monto) VALUES (?,?,?,?)");
    $st->bind_param('sssd',$beneficiario,$descripcion,$fecha,$monto);
    $st->execute();
    $msg = "Pago cargado.";
  }else{
    $msg = "Completá beneficiario y monto.";
  }
}

/* Borrar */
if(isset($_GET['del']) && ctype_digit($_GET['del'])){
  $id=(int)$_GET['del'];
  $db->query("DELETE FROM rossi_db.pagos_varios WHERE id=".$id);
  header("Location: pagos_varios_nuevo.php");
  exit;
}

/* Filtro semana para la grilla inferior */
$base = $_GET['fecha_base'] ?? $hoy;
[$ini,$fin] = rango_semana_por_fecha($base);

$st=$db->prepare("SELECT id,beneficiario,descripcion,DATE_FORMAT(fecha,'%d/%m/%Y') f,monto
                  FROM rossi_db.pagos_varios
                  WHERE fecha BETWEEN ? AND ?
                  ORDER BY fecha,id");
$st->bind_param('ss',$ini,$fin); $st->execute();
$rows=$st->get_result()->fetch_all(MYSQLI_ASSOC);

$total=0.0; foreach($rows as $r){ $total+=(float)$r['monto']; }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Pagos varios — alta y listado</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:#f6f7fb}
  .card{border:0;border-radius:18px;box-shadow:0 6px 16px rgba(20,20,20,.06)}
</style>
</head>
<body>
<div class="container py-4">

  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0">Pagos varios</h4>
    <a class="btn btn-outline-dark btn-sm" href="index.php">← Volver al inicio</a>
  </div>

  <?php if($msg): ?>
    <div class="alert alert-info py-2"><?=$msg?></div>
  <?php endif; ?>

  <div class="card mb-4">
    <div class="card-body">
      <form method="post" class="row g-3">
        <input type="hidden" name="accion" value="guardar">
        <div class="col-sm-4">
          <label class="form-label">Beneficiario</label>
          <input name="beneficiario" class="form-control" required>
        </div>
        <div class="col-sm-4">
          <label class="form-label">Descripción</label>
          <input name="descripcion" class="form-control">
        </div>
        <div class="col-sm-2">
          <label class="form-label">Fecha</label>
          <input type="date" name="fecha" value="<?=h($hoy)?>" class="form-control" required>
        </div>
        <div class="col-sm-2">
          <label class="form-label">Monto</label>
          <input name="monto" class="form-control" placeholder="0,00" required>
        </div>
        <div class="col-12">
          <button class="btn btn-primary">Guardar</button>
        </div>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-body">
      <form class="row g-2 align-items-end mb-3" method="get">
        <div class="col-sm-3">
          <label class="form-label">Ver semana que contiene</label>
          <input type="date" name="fecha_base" value="<?=h($base)?>" class="form-control">
        </div>
        <div class="col-sm-2">
          <button class="btn btn-outline-primary">Filtrar</button>
        </div>
        <div class="col-sm-7 text-end">
          <div class="text-muted">Rango: <?=date('d/m/Y',strtotime($ini))?> a <?=date('d/m/Y',strtotime($fin))?></div>
        </div>
      </form>

      <div class="table-responsive">
        <table class="table table-sm align-middle">
          <thead class="table-light"><tr>
            <th>Beneficiario</th><th>Descripción</th><th>Fecha</th><th class="text-end">Monto</th><th class="text-center">Acciones</th>
          </tr></thead>
          <tbody>
            <?php if(empty($rows)): ?>
              <tr><td colspan="5" class="text-center text-muted">Sin pagos en esta semana.</td></tr>
            <?php else: foreach($rows as $r): ?>
              <tr>
                <td><?=h($r['beneficiario'])?></td>
                <td><?=h($r['descripcion'])?></td>
                <td><?=h($r['f'])?></td>
                <td class="text-end">$<?=ars($r['monto'])?></td>
                <td class="text-center">
                  <a class="btn btn-sm btn-outline-danger" onclick="return confirm('¿Borrar pago?')" href="?del=<?=$r['id']?>">Borrar</a>
                </td>
              </tr>
            <?php endforeach; endif; ?>
            <tr class="table-light">
              <th colspan="3">Total</th>
              <th class="text-end">$<?=ars($total)?></th>
              <th></th>
            </tr>
          </tbody>
        </table>
      </div>

    </div>
  </div>

</div>
</body>
</html>
