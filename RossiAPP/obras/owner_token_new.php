<?php
require_once("../config.php"); // usa la conexión existente

// Detectar handler de conexión ($conn o $mysqli)
$db = null;
if (isset($conn) && $conn instanceof mysqli)      { $db = $conn; }
elseif (isset($mysqli) && $mysqli instanceof mysqli){ $db = $mysqli; }
else { die("No hay conexión DB desde config.php"); }

// Token sin vencimiento
$token   = bin2hex(random_bytes(8)); // 16 chars
$filtros = '{}';
$expira  = null;

// Insertar en tabla de tokens usando prefijo de base
$stmt = $db->prepare("INSERT INTO db_gdc.view_tokens(token, filtros_json, expira_en) VALUES (?,?,?)");
$stmt->bind_param('sss', $token, $filtros, $expira);
$stmt->execute();

// Armar URL
$base = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off' ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];
$path = rtrim(dirname($_SERVER['PHP_SELF']), '/');
$url  = $base.$path."/owner_view.php?token=".$token;
?>
<!doctype html><html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<title>Link sólo lectura (Dueño)</title></head><body class="bg-light">
<div class="container py-4">
  <div class="card shadow-sm">
    <div class="card-body">
      <h5>Link de sólo lectura (Dueño)</h5>
      <p class="text-muted mb-2">No vence y no permite editar.</p>
      <div class="input-group">
        <input class="form-control" value="<?=htmlspecialchars($url)?>" readonly>
        <a class="btn btn-primary" target="_blank" href="<?=$url?>">Abrir</a>
      </div>
    </div>
  </div>
</div>
</body></html>

