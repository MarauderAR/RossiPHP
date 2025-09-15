<?php
require_once("../config.php");

$proveedor_id = intval($_GET['id'] ?? 0);

// Traer obras para el select
$obras = [];
$obRes = $conn->query("SELECT id, nombre FROM obras ORDER BY nombre");
while ($r = $obRes->fetch_assoc()) $obras[] = $r;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $numero       = trim($_POST['numero'] ?? '');
    $fecha        = $_POST['fecha'] ?? date('Y-m-d');
    $monto_total  = (float) str_replace(',', '.', $_POST['monto_total'] ?? '0');
    $debe         = (float) str_replace(',', '.', $_POST['debe'] ?? '0');
    $haber        = (float) str_replace(',', '.', $_POST['haber'] ?? '0');
    $obra_id      = (int)($_POST['obra_id'] ?? 0);

    // Manejo de archivo adjunto
    $archivo_db = null;
    if (!empty($_FILES['archivo']['name']) && is_uploaded_file($_FILES['archivo']['tmp_name'])) {
        $allowed = ['pdf','jpg','jpeg','png','webp','xls','xlsx'];
        $ext = strtolower(pathinfo($_FILES['archivo']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $allowed)) {
            $baseDir = __DIR__ . "/uploads/facturas";
            if (!is_dir($baseDir)) @mkdir($baseDir, 0775, true);

            $safeName = preg_replace('/[^A-Za-z0-9_\.-]/', '_', basename($_FILES['archivo']['name']));
            $uniq = date('Ymd_His') . '_' . substr(md5(uniqid('', true)), 0, 8);
            $destFs = $baseDir . "/" . $uniq . "_" . $safeName;
            if (move_uploaded_file($_FILES['archivo']['tmp_name'], $destFs)) {
                // Ruta relativa para la web
                $archivo_db = "uploads/facturas/" . basename($destFs);
            }
        }
    }

    // Insert (obra_id NULL si viene 0)
    $stmt = $conn->prepare("
        INSERT INTO facturas (proveedor_id, obra_id, numero, fecha, monto_total, debe, haber, archivo)
        VALUES (?, NULLIF(?,0), ?, ?, ?, ?, ?, ?)
    ");
    $stmt->bind_param(
        "iissddds",
        $proveedor_id, $obra_id, $numero, $fecha, $monto_total, $debe, $haber, $archivo_db
    );
    $stmt->execute();
    $stmt->close();

    header("Location: ver_proveedor.php?id=" . $proveedor_id);
    exit;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Nueva Factura</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="style.css">
</head>
<body class="p-3">
    <h2 class="mb-3">Agregar Factura</h2>
    <form method="post" enctype="multipart/form-data" class="row g-3">

        <div class="col-md-6">
            <label class="form-label">Número o Comprobante</label>
            <input type="text" name="numero" class="form-control" required>
        </div>

        <div class="col-md-3">
            <label class="form-label">Fecha</label>
            <input type="date" name="fecha" id="fecha_date" class="form-control" required>
        </div>
        <div class="col-md-3">
            <label class="form-label">Fecha (dd/mm/yyyy)</label>
            <input type="text" id="fecha_texto" class="form-control" placeholder="dd/mm/yyyy">
        </div>

        <div class="col-md-4">
            <label class="form-label">Obra (opcional)</label>
            <select name="obra_id" class="form-select">
                <option value="0">-- sin obra --</option>
                <?php foreach ($obras as $o): ?>
                    <option value="<?= $o['id'] ?>"><?= htmlspecialchars($o['nombre']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="col-md-4">
            <label class="form-label">Monto Total</label>
            <input type="number" step="0.01" name="monto_total" class="form-control" required>
        </div>

        <div class="col-md-2">
            <label class="form-label">Debe</label>
            <input type="number" step="0.01" name="debe" class="form-control">
        </div>

        <div class="col-md-2">
            <label class="form-label">Haber</label>
            <input type="number" step="0.01" name="haber" class="form-control">
        </div>

        <div class="col-md-6">
            <label class="form-label">Adjunto (PDF/JPG/PNG/WEBP/XLS/XLSX)</label>
            <input type="file" name="archivo" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp,.xls,.xlsx">
        </div>

        <div class="col-12">
            <button class="btn btn-primary" type="submit">Agregar</button>
            <a class="btn btn-secondary" href="ver_proveedor.php?id=<?= $proveedor_id ?>">Volver</a>
        </div>
    </form>

<script>
document.getElementById('fecha_date').addEventListener('input', function() {
    let d = this.value;
    let t = document.getElementById('fecha_texto');
    if (d) {
        let p = d.split("-");
        t.value = `${p[2]}/${p[1]}/${p[0]}`;
    } else {
        t.value = "";
    }
});
document.getElementById('fecha_texto').addEventListener('input', function() {
    let val = this.value.replace(/-/g, "/").trim();
    let m = val.match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
    let f = document.getElementById('fecha_date');
    if (m) {
        f.value = `${m[3]}-${m[2]}-${m[1]}`;
        this.setCustomValidity('');
    } else if (val.length > 0) {
        this.setCustomValidity('Formato: dd/mm/yyyy');
    } else {
        f.value = "";
        this.setCustomValidity('');
    }
});
</script>
</body>
</html>
