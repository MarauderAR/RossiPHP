<?php
// --- DB ---
$db_host='localhost'; $db_user='empresa'; $db_pass='v3t3r4n0'; $db_name='asistencia';
mysqli_report(MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT);
$conn=new mysqli($db_host,$db_user,$db_pass,$db_name);
$conn->set_charset('utf8mb4');

// --- Obras ---
$obras=[]; $q=$conn->query("SELECT id,nombre FROM obras ORDER BY nombre");
while($r=$q->fetch_assoc()) $obras[]=$r;
$obra_id = (int)($_GET['obra_id'] ?? ($_POST['obra_id'] ?? ($obras[0]['id']??0)));
$buscar = trim($_GET['buscar'] ?? '');

// --- Acciones ---
if ($_SERVER['REQUEST_METHOD']==='POST') {
  $act = $_POST['act'] ?? '';
  if ($act==='add') {
    $nombre = trim($_POST['nombre']??'');
    if ($nombre!=='') {
      $stmt=$conn->prepare("INSERT INTO obreros (nombre,obra_id,activo) VALUES (?,?,1)
                            ON DUPLICATE KEY UPDATE activo=VALUES(activo), obra_id=VALUES(obra_id)");
      $stmt->bind_param("si",$nombre,$obra_id); $stmt->execute(); $stmt->close();
    }
  } elseif ($act==='rename') {
    $id=(int)$_POST['id']; $nombre=trim($_POST['nombre']??'');
    if ($nombre!=='') {
      // Evita choque con UNIQUE: si existe mismo nombre en esa obra, lo reactivamos y movemos asistencias
      $stmt=$conn->prepare("SELECT id FROM obreros WHERE obra_id=? AND nombre=? LIMIT 1");
      $stmt->bind_param("is",$obra_id,$nombre); $stmt->execute(); $stmt->bind_result($dup_id);
      $exists=$stmt->fetch(); $stmt->close();
      if ($exists && $dup_id!=$id) {
        // migrar asistencias del viejo id al duplicado
        $conn->query("UPDATE asistencia SET obrero_id=$dup_id WHERE obrero_id=$id");
        // baja lógica del viejo
        $conn->query("UPDATE obreros SET activo=0 WHERE id=$id");
        // reactivar duplicado
        $conn->query("UPDATE obreros SET activo=1 WHERE id=$dup_id");
      } else {
        $stmt=$conn->prepare("UPDATE obreros SET nombre=? WHERE id=?");
        $stmt->bind_param("si",$nombre,$id); $stmt->execute(); $stmt->close();
      }
    }
  } elseif ($act==='del') {
    $id=(int)$_POST['id'];
    $conn->query("UPDATE obreros SET activo=0 WHERE id=".$id);
  } elseif ($act==='restore') {
    $id=(int)$_POST['id'];
    $conn->query("UPDATE obreros SET activo=1 WHERE id=".$id);
  }
  header("Location: ?obra_id=$obra_id&buscar=".urlencode($buscar)); exit;
}

// --- Listado obreros obra ---
$params = [$obra_id]; $sql="SELECT id,nombre,activo FROM obreros WHERE obra_id=? ";
if ($buscar!=='') { $sql.="AND nombre LIKE ? "; $like="%$buscar%"; $params[]=&$like; }
$sql.="ORDER BY activo DESC, nombre";
$stmt=$conn->prepare($sql);
if (count($params)==1) $stmt->bind_param("i",$params[0]); else $stmt->bind_param("is",$params[0],$params[1]);
$stmt->execute(); $res=$stmt->get_result(); $obreros=[]; while($r=$res->fetch_assoc()) $obreros[]=$r; $stmt->close();

?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Obreros - Admin</title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
body{background:#f6f8fa}
.badge-off{background:#adb5bd}
.table td{vertical-align:middle}
</style>
</head>
<body>
<div class="container py-4">
  <h3 class="mb-3">Administrar obreros</h3>

  <form class="row g-2 mb-3" method="get">
    <div class="col-md-4">
      <label class="form-label">Obra</label>
      <select name="obra_id" class="form-select" onchange="this.form.submit()">
        <?php foreach($obras as $o): ?>
          <option value="<?=$o['id']?>" <?=($obra_id==(int)$o['id'])?'selected':''?>><?=htmlspecialchars($o['nombre'])?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-md-4">
      <label class="form-label">Buscar</label>
      <input type="text" name="buscar" class="form-control" value="<?=htmlspecialchars($buscar)?>" placeholder="Nombre...">
    </div>
    <div class="col-md-4 d-flex align-items-end">
      <button class="btn btn-outline-secondary">Filtrar</button>
      <a href="?obra_id=<?=$obra_id?>" class="btn btn-link ms-2">Limpiar</a>
    </div>
  </form>

  <div class="card shadow-sm mb-3">
    <div class="card-header">Agregar nuevo obrero</div>
    <div class="card-body">
      <form method="post" class="row g-2">
        <input type="hidden" name="act" value="add">
        <input type="hidden" name="obra_id" value="<?=$obra_id?>">
        <div class="col-md-8">
          <input type="text" name="nombre" class="form-control" placeholder="Nombre completo" required>
        </div>
        <div class="col-md-4">
          <button class="btn btn-success w-100">Agregar</button>
        </div>
      </form>
      <small class="text-muted">Si el nombre ya existía en esta obra, se reactiva automáticamente.</small>
    </div>
  </div>

  <div class="table-responsive">
    <table class="table table-bordered bg-white">
      <thead class="table-light">
        <tr>
          <th style="width:60px">ID</th>
          <th>Nombre</th>
          <th style="width:120px">Estado</th>
          <th style="width:260px">Acciones</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach($obreros as $o): ?>
        <tr>
          <td><?=$o['id']?></td>
          <td>
            <form method="post" class="d-flex gap-2">
              <input type="hidden" name="act" value="rename">
              <input type="hidden" name="id" value="<?=$o['id']?>">
              <input type="hidden" name="obra_id" value="<?=$obra_id?>">
              <input type="text" name="nombre" class="form-control" value="<?=htmlspecialchars($o['nombre'])?>" required>
              <button class="btn btn-outline-primary btn-sm">Renombrar</button>
            </form>
          </td>
          <td>
            <?php if($o['activo']): ?>
              <span class="badge text-bg-success">Activo</span>
            <?php else: ?>
              <span class="badge badge-off">Inactivo</span>
            <?php endif; ?>
          </td>
          <td>
            <?php if($o['activo']): ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="act" value="del">
                <input type="hidden" name="id" value="<?=$o['id']?>">
                <input type="hidden" name="obra_id" value="<?=$obra_id?>">
                <button class="btn btn-outline-danger btn-sm"
                        onclick="return confirm('Dar de baja a <?=htmlspecialchars($o['nombre'])?>?')">Baja</button>
              </form>
            <?php else: ?>
              <form method="post" class="d-inline">
                <input type="hidden" name="act" value="restore">
                <input type="hidden" name="id" value="<?=$o['id']?>">
                <input type="hidden" name="obra_id" value="<?=$obra_id?>">
                <button class="btn btn-outline-success btn-sm">Restaurar</button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
        <?php if(empty($obreros)): ?>
          <tr><td colspan="4" class="text-center text-muted">Sin obreros.</td></tr>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <a href="index.php" class="btn btn-link">← Volver a asistencias</a>
</div>
</body>
</html>
