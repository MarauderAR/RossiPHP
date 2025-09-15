<?php
require_once("../config.php");

// Conexión (para token de Cheques)
$db = (isset($conn) && $conn instanceof mysqli) ? $conn :
     ((isset($mysqli) && $mysqli instanceof mysqli) ? $mysqli : null);
if(!$db){ die("No hay conexión DB desde config.php"); }

function ensure_token(mysqli $db, string $markerValue): string {
  $like = '%"owner_index":"'.$db->real_escape_string($markerValue).'"%';
  $sel = $db->prepare("SELECT token FROM db_gdc.view_tokens WHERE filtros_json LIKE ? ORDER BY id DESC LIMIT 1");
  $sel->bind_param('s',$like); $sel->execute();
  if ($r = $sel->get_result()->fetch_assoc()) return $r['token'];
  $token = bin2hex(random_bytes(8));
  $json  = json_encode(['owner_index'=>$markerValue], JSON_UNESCAPED_UNICODE);
  $ins = $db->prepare("INSERT INTO db_gdc.view_tokens(token,filtros_json,expira_en) VALUES (?,?,NULL)");
  $ins->bind_param('ss',$token,$json); $ins->execute();
  return $token;
}
$tokenCheques = ensure_token($db,'cheques');

$URL_CHEQUES = "/gdc/view.php?token=".urlencode($tokenCheques);
$URL_PAGOS   = "owner_view.php";
$URL_ASIS    = "owner_asistencias.php";
$URL_TAREAS  = "owner_tareas.php";
?>
<!doctype html>
<html lang="es"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Panel del Dueño</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<style>
  body{background:#f6f7fb}
  .wrap{max-width:980px;margin:40px auto}
  .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:14px}
  .cardlink{display:block;padding:22px;border-radius:18px;text-decoration:none;
            box-shadow:0 10px 22px rgba(16,24,40,.08);background:#fff;transition:transform .06s}
  .cardlink:hover{transform:translateY(-2px)}
  .ttl{font-weight:800;font-size:1.25rem;margin:0 0 6px}
  .sub{color:#6c757d;margin:0}
</style>
</head>
<body>
  <div class="container wrap">
    <h2 class="fw-bold mb-3">Rossi Sistema de gestion</h2>
    

    <div class="grid">
      <a class="cardlink" href="<?=$URL_CHEQUES?>">
        <div class="ttl">Cheques</div><p class="sub">Listado con filtros</p>
      </a>
      <a class="cardlink" href="<?=$URL_PAGOS?>">
        <div class="ttl">Pagos</div><p class="sub">Totales por semana / obra / obrero</p>
      </a>
      <a class="cardlink" href="<?=$URL_ASIS?>">
        <div class="ttl">Asistencias</div><p class="sub">Resumen semanal</p>
      </a>
      <a class="cardlink" href="<?=$URL_TAREAS?>">
        <div class="ttl">Tareas</div><p class="sub">Tablero solo lectura</p>
      </a>
    </div>
  </div>
</body></html>
