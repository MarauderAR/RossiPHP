<?php
require_once '_db.php';
require_once '_auth.php';   // ← exige login contra rossi_db.usuarios

// Link sin filtros = muestra TODOS los cheques
$filtros = []; // vacío -> sin WHERE

$token  = bin2hex(random_bytes(8)); // 8 bytes -> 16 caracteres hexadecimales

$expira = null; // NULL = sin vencimiento

$stmt = $mysqli->prepare("INSERT INTO view_tokens(token,filtros_json,expira_en) VALUES (?,?,?)");
$json = json_encode($filtros, JSON_UNESCAPED_UNICODE);
$stmt->bind_param('sss', $token, $json, $expira);
$stmt->execute();

$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$url  = $base.$path."/view.php?token=".$token;

include '_layout_head.php'; ?>
<div class="card shadow-sm">
  <div class="card-body">
    <h5>Link de solo lectura creado</h5>
    <p class="text-muted mb-1">Este link <b>no tiene vencimiento</b>.</p>
    <div class="input-group">
      <input class="form-control" value="<?=$url?>" readonly>
      <a class="btn btn-primary" href="<?=$url?>" target="_blank">Abrir</a>
    </div>
  </div>
</div>
<?php include '_layout_foot.php'; ?>
