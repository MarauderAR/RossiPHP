<?php
// api.php
header('Content-Type: application/json; charset=utf-8');
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// ====== CONFIG DB ======
$host = "localhost";
$user = "empresa";
$pass = "v3t3r4n0";
$conn = new mysqli($host, $user, $pass);
$conn->set_charset("utf8mb4");

// ====== HELPERS ======
$compatBare = isset($_GET['compat']) && $_GET['compat'] === 'bare'; // ?compat=bare -> array “pelado”
function out($ok, $data=null, $msg=null) {
    global $compatBare;
    if ($compatBare && $ok && is_array($data)) { echo json_encode($data, JSON_UNESCAPED_UNICODE); exit; }
    echo json_encode(['success'=>$ok,'data'=>$data,'message'=>$msg], JSON_UNESCAPED_UNICODE);
    exit;
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ====== GET OBRAS (rossi_db) ======
if ($action === 'get_obras') {
    $sql = "SELECT id, nombre FROM rossi_db.obras ORDER BY nombre";
    $res = $conn->query($sql);
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = ['id'=>(int)$r['id'],'nombre'=>$r['nombre']];
    out(true, $rows);
}

// ====== GET OBREROS POR OBRA (rossi_db)  ======
if ($action === 'get_obreros') {
    $obra_id = (int)($_GET['obra_id'] ?? 0);
    if ($obra_id <= 0) out(false, null, 'obra_id requerido');

    $sql = "SELECT o.id, o.nombre
            FROM rossi_db.obreros o
            INNER JOIN rossi_db.obra_obrero oo ON oo.obrero_id = o.id
            WHERE oo.obra_id = $obra_id
            ORDER BY o.nombre";
    $res = $conn->query($sql);
    $rows = [];
    while ($r = $res->fetch_assoc()) $rows[] = ['id'=>(int)$r['id'],'nombre'=>$r['nombre']];
    out(true, $rows);
}

// ====== GET ASISTENCIA (lee esquema asistencia) ======
if ($action === 'get_asistencia') {
    $obra_id = (int)($_GET['obra_id'] ?? 0);
    $fecha   = $_GET['fecha'] ?? date('Y-m-d');
    if ($obra_id <= 0 || !$fecha) out(false, null, 'obra_id/fecha requeridos');

    // 1) Obreros asignados a la obra
    $sqlObr = "SELECT o.id AS obrero_id, o.nombre
               FROM rossi_db.obreros o
               INNER JOIN rossi_db.obra_obrero oo ON oo.obrero_id = o.id
               WHERE oo.obra_id = $obra_id
               ORDER BY o.nombre";
    $resObr = $conn->query($sqlObr);
    $obreros = [];
    while ($r = $resObr->fetch_assoc()) $obreros[(int)$r['obrero_id']] = $r['nombre'];

    // 2) Marcas del día (OJO: esquema correcto)
    $stmt = $conn->prepare("SELECT obrero_id, presente, COALESCE(observaciones,'') AS observaciones
                            FROM asistencia.asistencia
                            WHERE obra_id=? AND fecha=?");
    $stmt->bind_param('is', $obra_id, $fecha);
    $stmt->execute();
    $rs = $stmt->get_result();

    $marcas = [];
    while ($a = $rs->fetch_assoc()) {
        $marcas[(int)$a['obrero_id']] = [
            'presente' => ((int)$a['presente'] === 1),
            'observaciones' => $a['observaciones']
        ];
    }

    // 3) Merge obreros + marcas
    $estados = [];
    foreach ($obreros as $oid => $nombre) {
        $m = $marcas[$oid] ?? null;
        $estados[] = [
            'obrero_id'     => $oid,
            'nombre'        => $nombre,
            'presente'      => $m['presente'] ?? false,
            'observaciones' => $m['observaciones'] ?? ''
        ];
    }
    out(true, $estados);
}

// ====== SAVE ASISTENCIA (upsert en asistencia.asistencia) ======
if ($action === 'save_asistencia') {
    $raw = file_get_contents('php://input');
    $body = json_decode($raw, true);

    $obra_id = (int)($body['obra_id'] ?? 0);
    $fecha   = $body['fecha'] ?? date('Y-m-d');
    $reg     = $body['registros'] ?? [];

    if ($obra_id <= 0 || !$fecha || !is_array($reg)) out(false, null, 'Datos incompletos');

    $stmt = $conn->prepare("
        INSERT INTO asistencia.asistencia (obra_id, obrero_id, fecha, presente, observaciones)
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            presente = VALUES(presente),
            observaciones = VALUES(observaciones)
    ");

    $updated = 0;
    foreach ($reg as $r) {
        $oid  = (int)($r['obrero_id'] ?? 0);
        if ($oid <= 0) continue;
        $pres = !empty($r['presente']) ? 1 : 0;
        $obs  = trim($r['observaciones'] ?? '');
        $stmt->bind_param('iisis', $obra_id, $oid, $fecha, $pres, $obs); // i,i,s,i,s
        $stmt->execute();
        $updated++;
    }
    out(true, ['updated'=>$updated], 'OK');
}

// ====== LOGIN (opcional) usa rossi_db.usuarios ======
if ($action === 'login') {
    $user = $_POST['user'] ?? '';
    $pass = $_POST['pass'] ?? '';
    $stmt = $conn->prepare("SELECT id FROM rossi_db.usuarios WHERE usuario=? AND password=? LIMIT 1");
    $stmt->bind_param('ss', $user, $pass);
    $stmt->execute(); $stmt->store_result();
    if ($stmt->num_rows > 0) out(true, ['token'=>null,'user'=>$user], null);
    out(false, null, 'Credenciales inválidas');
}

out(false, null, 'Acción no encontrada');
